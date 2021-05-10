<?php
namespace Jeero\Calendars;

use Jeero\Helpers\Images as Images;

/**
 * Modern_Events_Calendar class.
 *
 * @since	1.0.3
 * 
 * @extends Calendar
 */
class Modern_Events_Calendar extends Calendar {

	function __construct() {
		
		$this->slug = 'Modern_Events_Calendar';
		$this->name = __( 'Modern Events Calendar', 'jeero' );
		
		parent::__construct();
		
	}
	
	function get_event_by_ref( $ref, $theater ) {
		
		error_log( sprintf( '[%s] Looking for existing %s item %s.', $this->get( 'name' ), $theater, $ref ) );
		
		$args = array(
			'post_type' => $this->get_mec_instance( 'main' )->get_main_post_type(),
			'post_status' => 'any',
			'meta_query' => array(
				array(
					'key' => $this->get_ref_key( $theater ),
					'value' => $ref,					
				),
			),
		);
		
		$events = \get_posts( $args );
		
		if ( empty( $events ) ) {
			return false;
		}
		
		return $events[ 0 ]->ID;
		
	}

	/**
	 * Gets all fields for this calendar.
	 * 
	 * @since	1.9
	 * @since	1.10		Added the $subscription param.
	 * @return	array
	 */
	function get_fields( $subscription ) {
		
		$fields = parent::get_fields();
		
		$fields = array_merge( $fields, $this->get_import_status_fields() );
		$fields = array_merge( $fields, $this->get_import_update_fields() );
		
		return $fields;
		
	}

	function get_mec_instance( $lib ) {
		return \MEC::getInstance( sprintf( 'app.libraries.%s', $lib ) );		
	}
	
	function get_venue_id( $title ) {
		$venue_id = wp_cache_get( $title, 'jeero/venue_id' );

		if ( false === $venue_id ) {
		
			$venue_post = get_page_by_title( $title, OBJECT, 'tribe_venue' );
			
			if ( !( $venue_post ) ) {
				$venue_id = tribe_create_venue( 
					array( 
						'Venue' => $title,
					)
				);
			} else {
				$venue_id = $venue_post->ID;
			}
			
			wp_cache_set( $title, $venue_id, 'jeero/venue_id' );
			
		}
		
		return $venue_id;		
	}
	
	/**
	 * Processes event data from Inbox items.
	 * 
	 * @since	1.?
	 * @since	1.4	Added the subscription param.
	 * @since	1.9		Added support for import settings to decide whether to 
	 * 					overwrite title/description/image/category during import.
	 * 					Added support for post status settings during import.
	 *					Added support for categories.
	 *
	 * @param 	mixed 			$result
	 * @param 	array			$data		The structured data of the event.
	 * @param 	array			$raw		The raw data of the event.
	 * @param	string			$theater		The theater.
	 * @param	Subscription		$theater		The subscription.
	 * @return	int|WP_Error
	 */
	function process_data( $result, $data, $raw, $theater, $subscription ) {
		
		$result = parent::process_data( $result, $data, $raw, $theater, $subscription );
		
		if ( \is_wp_error( $result ) ) {			
			return $result;
		}
		
		$ref = $data[ 'ref' ];

		$event_start = strtotime( $data[ 'start' ] );

		$args = array (
		    'start'=> date( 'Y-m-d', $event_start ),
		    'start_time_hour' => date( 'g', $event_start ),
		    'start_time_minutes'=> date( 'i', $event_start ),
		    'start_time_ampm' => date( 'A', $event_start ),
		    'interval' => NULL,
		    'repeat_type' => '',
		    'repeat_status' => 0,
		    'meta' => array (
		        'mec_source' => $theater,
                'mec_more_info'=> $data[ 'tickets_url' ],
		    )
		);
		
		if ( !empty( $data[ 'end' ] ) ) {
			$event_end = strtotime( $data[ 'end' ] );
		} else {
			$event_end = $event_start;			
			$args[ 'date' ] = array(
				'hide_end_time' => 1,
			);
		}

		$args = array_merge( $args, array(
		    'end' => date( 'Y-m-d', $event_end ),
		    'end_time_hour' => date( 'g', $event_end ),
		    'end_time_minutes' => date( 'i', $event_end ),
		    'end_time_ampm' => date( 'A', $event_end ),				
		) );
			

		// Temporarily disable new event notifications.
		remove_action( 'mec_event_published', array( $this->get_mec_instance( 'notifications' ), 'user_event_publishing'), 10 );

		if ( $event_id = $this->get_event_by_ref( $ref, $theater ) ) {
			error_log( sprintf( '[%s] Updating event %d / %d.', $this->name, $ref, $event_id ) );

			
			if ( 'always' == $this->get_setting( 'import/update/title', $subscription, 'once' ) ) {
				$args[ 'title' ] = $data[ 'production' ][ 'title' ];
			}
			
			if ( 'always' == $this->get_setting( 'import/update/description', $subscription, 'once' ) ) {
				$args[ 'content' ] = $data[ 'production' ][ 'description' ];
			}
						
			$this->get_mec_instance( 'main' )->save_event( $args, $event_id );        	

			if ( 
				'always' == $this->get_setting( 'import/update/image', $subscription, 'once' ) && 
				!empty( $data[ 'production' ][ 'img' ] )
			) {
				$thumbnail_id = Images\update_featured_image_from_url( 
					$event_id,
					$data[ 'production' ][ 'img' ]
				);
			}

			if ( 'always' == $this->get_setting( 'import/update/categories', $subscription, 'once' ) ) {
				if ( empty( $data[ 'production' ][ 'categories' ] ) ) {
					\wp_set_object_terms( 
						$event_id, 
						array(), 
						$this->get_mec_instance( 'main' )->get_category_slug(), 
						false  
					);			
				} else {
					\wp_set_object_terms( 
						$event_id, 
						$data[ 'production' ][ 'categories' ], 
						$this->get_mec_instance( 'main' )->get_category_slug(), 
						false  
					);
				}
			}

		} else {
			error_log( sprintf( '[%s] Creating event %d.', $this->name, $ref ) );

			$args[ 'title' ]= $data[ 'production' ][ 'title' ];
			$args[ 'content' ]= $data[ 'production' ][ 'description' ];
			$args[ 'status' ] = $this->get_setting( 'import/status', $subscription, 'draft' );
			
			$event_id = $this->get_mec_instance( 'main' )->save_event( $args );        				

			$thumbnail_id = Images\update_featured_image_from_url( 
				$event_id,
				$data[ 'production' ][ 'img' ]
			);

			if ( !empty( $data[ 'production' ][ 'categories' ] ) ) {
				\wp_set_object_terms( 
					$event_id, 
					$data[ 'production' ][ 'categories' ], 
					$this->get_mec_instance( 'main' )->get_category_slug(), 
					false  
				);
			}
		
			\add_post_meta( $event_id, $this->get_ref_key( $theater ), $data[ 'ref' ] );
		}		

		// Re-enable new event notifications.
		add_action( 'mec_event_published', array( $this->get_mec_instance( 'notifications' ), 'user_event_publishing'), 10, 3 );

		return $event_id;
		
	}
	
}