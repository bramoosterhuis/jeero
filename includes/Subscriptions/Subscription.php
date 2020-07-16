<?php
namespace Jeero\Subscriptions;

use Jeero\Db;
use Jeero\Admin;
use Jeero\Mother;

/**
 * Subscription class.
 * 
 */
class Subscription {
	
	/**
	 * The ID of this Subscription.
	 * @var		string 	$ID
	 * @since	1.0
	 */
	public $ID;
	
	/**
	 * The fields of this Subscription.
	 * The fields are provided by Mother, based on the value of $settings.
	 * @var		array	$fields
	 * @since	1.0
	 */
	public $fields = array();
	
	public $limit;
	
	public $logo;
	
	public $interval;
	
	/**
	 * Time (UTC) after which there will be an update for this Subscription.
	 * So no need to check for updates before this time.
	 * 
	 * @var	int
	 */
	public $next_delivery;
	
	public $theater = array();
	
	/**
	 * The settings of this Subscription.
	 * @var array	$settings
	 * @since	1.0
	 */
	public $settings = array();
	
	/**
	 * The status of this Subscription.
	 * @var 	string	$status
	 * @since	1.0
	 */	
	public $status;
	
	function __construct( $ID ) {
		
		$this->set( 'ID', $ID );
		$this->load();
		
	}

	function get( $key ) {
		
		if ( !isset( $this->{ $key } ) ) {
			return null;
		}
		
		return $this->{ $key };
		
	}
	
	/**
	 * Gets all fields of this Subscription.
	 * 
	 * @since	1.0
	 * @return 	Field[]		All fields of this Subscription.
	 */
	function get_fields() {
		
		$fields = array();

		foreach( $this->fields as $config ) {
			$setting = null;

			if ( $setting = $this->get_setting( $config[ 'name' ] ) ) {
				$fields[] = Fields\get_field_from_config( $config, $this->ID, $setting );
			} else {
				$fields[] = Fields\get_field_from_config( $config, $this->ID );				
			}
		}
		
		return $fields;		
		
	}
		
	function get_setting( $name ) {
		
		$settings = $this->get( 'settings' );
		
		if ( isset( $settings[ $name ] ) ) {
			return $settings[ $name ];
		}
		
		return false;

	}
	

	function load( ) {
		
		$data = Db\Subscriptions\get_subscription( $this->ID );
		
		if ( is_null( $data ) ) {
			return;
		}
		
		$defaults = array(
			'theater' => false,
		);
		
		$settings = wp_parse_args( $data[ 'settings' ], $defaults );
		
		$this->settings = $settings;

	}

	function set( $key, $value ) {
		
		$this->{ $key } = $value;
		
	}
	
	function save() {
		
		$data = array(
			'settings' => $this->settings,
		);
		
		Db\Subscriptions\save_subscription( $this->ID, $data );
		
		Mother\update_subscription( $this->ID, $this->settings );
		
		$this->load();
		
	}
	

}