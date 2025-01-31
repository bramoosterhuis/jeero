<?php
/**
 * Handles all Jeero admin screens.
 */
namespace Jeero\Admin;

add_action( 'admin_menu', __NAMESPACE__.'\add_menu_item' );
add_action( 'admin_enqueue_scripts', __NAMESPACE__.'\enqueue_scripts' );

/**
 * Adds the Jeero menu to the admin.
 * 
 * @since	1.0
 * @since	1.1		Renamed 'subscriptions' to 'imports'.
 * @since	1.18	Added a debug admin page.
 * @return 	void
 */
function add_menu_item() {
	
	add_menu_page(
        __( 'Jeero Imports', 'jeero' ),
        'Jeero',
        'manage_options',
        'jeero/imports',
        __NAMESPACE__.'\Subscriptions\do_admin_page',
        'dashicons-tickets-alt',
        90
    );
    
	add_submenu_page(
		'',
        __( 'Jeero Logs', 'jeero' ),
        __( 'Jeero Logs', 'jeero' ),
        'manage_options',
        'jeero/debug',
        __NAMESPACE__.'\Debug\do_admin_page',
    );
    
}

/**
 * Enqueues Jeero admin scripts and styles.
 * 
 * @since	1.5
 * @since	1.10	Only enqueue on Jeero admin pages.
 * @since	1.17	Enqueue on all pages Jeero now leaves footprints all over the place.
 *					Eg. meta boxes on all event admin pages.
 * @return 	void
 */
function enqueue_scripts( ) {
	
	wp_enqueue_script( 'jeero/admin', \Jeero\PLUGIN_URI . 'assets/js/admin.js', array( 'jquery' ), \Jeero\VERSION );
	wp_enqueue_style( 'jeero/admin', \Jeero\PLUGIN_URI . 'assets/css/admin.css', array(), \Jeero\VERSION, 'all' );

}


/**
 * Redirects admin users.
 *
 * @since	1.10
 * @param	string	$url		The URL for the redirect.
 * @return	void
 */
function redirect( $url ) {

	// Should we do redirects?
	// Filter is used by unit tests to prevents redirects.
	$do_redirects = apply_filters( 'jeero\admin\do_redirects', true );

	// Bail if we are not doing redirects.
	if ( !$do_redirects ) {
		return $url;
	}

	// Redirect and exit.
	\wp_safe_redirect( $url );
	exit;

}

