<?php
/**
 * @package archetype
 */

define( 'AT_PLUGIN_PATH', trailingslashit( plugin_dir_path( __FILE__ ) ) );
define( 'AT_PLUGIN_URL', trailingslashit( plugin_dir_url( __FILE__ ) ) );

add_action( 'init', function() {
	wp_enqueue_script( 'jquery' );
	wp_enqueue_script( 'at_wp_js', AT_PLUGIN_URL . 'js/archetype.wp.js' );
	wp_localize_script( 'at_wp_js', 'at_wp_js', array( 'ajaxurl' => admin_url( 'admin-ajax.php' ) ) ); 
});

include( 'archetype.functions.php' );
include( 'archetype.users.php' );
include( 'archetype.users.admin.php' );
include( 'archetype.options.php' );
include( 'archetype.messages.php' );
include( 'archetype.routes.php' );
include( 'archetype.facebook.php' );
include( 'archetype.users.email-auth.php' );

add_action( 'admin_menu', function() {

	$main_options = new Archetype_Options_Page( array( 
		'slug' 			=> 'at_main_options_page',
		'menu_title' 	=> __( 'Advanced Options', 'ses4wp' ),
		'page_title' 	=> __( 'Advanced Options', 'ses4wp' ),
		'capability' 	=> 'manage_options',
		'icon_url'		=> false,
		'position'		=> false
	) );

	do_action( 'at_main_options_page', $main_options );

} );