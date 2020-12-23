<?php
/**
 * Manages all of Jeero's Subscriptions.
 *
 */
namespace Jeero\Subscriptions;

use Jeero\Db;
use Jeero\Mother;
use Jeero\Calendars;

const JEERO_SUBSCRIPTIONS_STATUS_SETUP = 'setup';
const JEERO_SUBSCRIPTIONS_STATUS_READY = 'ready';

/**
 * Adds a new Subscription.
 *
 * Asks Mother to setup a new Subscription.
 * 
 * @return	string	The ID of the new Subscription.
 */
function add_subscription( ) {
	
	$answer = Mother\subscribe_me();

	if ( is_wp_error( $answer ) ) {
		return $answer;
	}

	return $answer[ 'id' ];

}


/**
 * Gets the Settings of all Subscriptions.
 * 
 * @since	1.0
 * @return	array
 */
function get_setting_values() {

	$subscriptions = Db\Subscriptions\get_subscriptions();

	$settings = array();	
	foreach( $subscriptions as $subscription_id => $subscription_data ) {
		
		$subscription = new Subscription( $subscription_id );
		$settings[ $subscription_id ] = $subscription->get( 'settings' );
	}
	
	return $settings;	
			
}
/**
 * Gets a Subscription.
 *
 * Gets the Subscription info from Mother and loads the settings from the DB.
 * 
 * @since	1.0
 * @since	1.4		Set a default value for 'interval'.
 * 					Added support for custom calendar fields.
 *
 * @param 	int						$subscription_id	The Subscription ID.
 * @return	Subscription|WP_Error	The Subscription. Or an error if something went wrong.
 */
function get_subscription( $subscription_id ) {

	// Get the settings from the DB.
	$subscription = new \Jeero\Subscriptions\Subscription( $subscription_id );

	// Ask Mother for subscription info, based on the current settings.
	$answer = Mother\get_subscription( $subscription_id, $subscription->get( 'settings' ) );

	if ( is_wp_error( $answer ) ) {
		return $answer;
	}
	
	$defaults = array(
		'status' => false,
		'logo' => false,
		'fields' => array(),
		'inactive' => false,
		'interval' => null,
		'next_delivery' => null,
		'theater' => array(),
		'limit' => null,
	);
	
	$answer = wp_parse_args( $answer, $defaults );
	
	$fields = array();
	
	// Add calendar checkboxes.
	$fields[] = Calendars\get_calendars_field();

	// Add custom fields for selected calendars.
	$calendar_slugs = $subscription->get_setting( 'calendar' );

	if ( !empty( $calendar_slugs ) ) {

		foreach( $calendar_slugs as $calendar_slug ) {
			$calendar = Calendars\get_calendar( $calendar_slug );
			$fields = array_merge( $fields, $calendar->get_fields() );
		}
		
	}

	// Prepend fields from Mother.
	if ( !empty( $answer[ 'fields' ] ) ) {
		$fields = array_merge( $answer[ 'fields' ], $fields );
	}
	
	// Add the subscription info to the Subscription.
	$subscription->set( 'status', $answer[ 'status' ] );
	$subscription->set( 'logo', $answer[ 'logo' ] );
	$subscription->set( 'fields', $fields );
	$subscription->set( 'inactive', $answer[ 'inactive' ] );
	$subscription->set( 'interval', $answer[ 'interval' ] );
	$subscription->set( 'next_delivery', $answer[ 'next_delivery' ] );
	$subscription->set( 'limit', $answer[ 'limit' ] );
	$subscription->set( 'theater', $answer[ 'theater' ] );

	return $subscription;
}

/**
 * Gets all of Jeero's Subscriptions.
 * 
 * Gets the Subscriptions settings from the database and the info from Mother.
 * 
 * @return	Subscription[]	An array containing all of Jeero's Subscriptions.
 */
function get_subscriptions() {

	$settings = get_setting_values();

	// Ask Mother for a list of up-to-date subscriptions.
	$answers = Mother\get_subscriptions( $settings );

	// Update the Subscriptions in the DB.
	$subscriptions = array();
	foreach( $answers as $answer ) {
		
		$defaults = array(
			'status' => false,
			'logo' => false,
			'fields' => array(),
			'inactive' => null,
			'interval' => null,
			'next_delivery' => null,
			'limit' => null,
		);
		
		$answer = wp_parse_args( $answer, $defaults );

		$subscription = new Subscription( $answer[ 'id' ] );
		$subscription->set( 'status', $answer[ 'status' ] );
		$subscription->set( 'logo', $answer[ 'logo' ] );
		$subscription->set( 'fields', $answer[ 'fields' ] );
				
		$subscription->set( 'inactive', $answer[ 'inactive' ] );
		$subscription->set( 'interval', $answer[ 'interval' ] );
		$subscription->set( 'next_delivery', $answer[ 'next_delivery' ] );
		$subscription->set( 'limit', $answer[ 'limit' ] );
		
		if ( isset( $answer[ 'theater' ] ) ) {
			$subscription->set( 'theater', $answer[ 'theater' ] );
		}

		$subscriptions[ $subscription->get( 'ID' ) ] = $subscription;
	}
	
	return $subscriptions;	
		
}