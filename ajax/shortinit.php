<?php

ini_set('html_errors', 0);
define('SHORTINIT', true);

require '../../../../wp-load.php';
require( ABSPATH . WPINC . '/formatting.php' );
require( ABSPATH . WPINC . '/meta.php' );
require( ABSPATH . WPINC . '/post.php' );
require( ABSPATH . WPINC . '/link-template.php' );
require( ABSPATH . WPINC . '/theme.php' );
wp_plugin_directory_constants();
require( '../init.php' );

do_action( 'archetype_shortinit', $_POST['action'] ); ?>