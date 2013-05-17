<?php
/**
 * @package archetype
 */

/**
 * Call a function and return any output it generates
 * @param  callable $func the function to return
 * @param  array $args any arguments to pass the function
 * @return string       the function output
 */
function at_buffer( $func, $args = array() ) {
	ob_start();
	call_user_func_array( $func, $args );
	return ob_get_clean();
}

/**
 * Echo out some json for an ajax request and die();
 * @param  mixed $data anything
 * @return void
 */
function at_ajax_response( $data ) {
	// handle unescaped slashes
	echo str_replace( '\/' , '/' , json_encode( $data ) );
	die();
}

/**
 * Create a slug with underscores instead of dashes
 * @param  string $string 
 * @return string         
 */
function at_underscore_slug( $string ) {
	$_string = sanitize_title( $string );
	return str_replace( '-', '_', $_string );
}

/**
 * Does a number lie within a range?
 * @param  int $num the number to check
 * @param  min $min the low end
 * @param  max $max the high end
 * @return bool      
 */
function at_in_range( $num, $min, $max ) {
	return ($num >= $min && $num <= $max);
}

/**
 * Display some WP Error messages
 * @param  array $wp_errors some wp errors
 * @return void            
 */
function at_display_errors( $wp_errors ) {
	foreach( $wp_errors as $error ) {
		tn_add_static_message( 'error', $error->get_error_message() );
	}
} 

/**
 * Log an array or object
 * @param  mixed $thing
 * @return void          
 */
function at_log_object( $thing ) {
	ob_start();
	print_r( $thing );
	error_log( ob_get_clean());
}

/**
 * Get the MIME type of a string
 * @param string $string the file in a string
 * @return string
 */
function at_get_string_mime_type( $string ) {
	$finfo = new finfo( FILEINFO_MIME_TYPE );
	return $finfo->buffer( $string );
}

/**
 * Get a POSTed value, checking if isset() beforehand
 * @param  string $key the POST key
 * @param  mixed $default the default value to return if not found
 * @return mixed      $_POST value or null on no value present
 */
function at_get_post_value( $key, $default = null ) {
	return isset( $_POST[$key] ) ? $_POST[$key] : $default;
}

/**
 * Check for presence of a wp_query property
 * @param  string $var the varname
 * @return boolean      
 */
function at_is( $var ) {
	global $wp_query;
	return !empty( $wp_query->$var ) && $wp_query->$var;
}

/**
 * Get the file extension for a given MIME type
 * @param  string $mime_type the MIME type
 * @return string            the extension
 */
function at_get_mime_type_ext( $mime_type ) {
	$types = array(
		'image/jpeg' 	=> 'jpg',
		'image/png' 	=> 'png' );
	return $types[$mime_type];
}

/**
 * Like get_template_part() put lets you pass args to the template file
 * Args are available in the tempalte as $template_args array
 * @param string filepart
 * @param mixed wp_args style argument list
 */
function hm_get_template_part( $file, $template_args = array(), $cache_args = array() ) {

	$template_args = wp_parse_args( $template_args );
	$cache_args = wp_parse_args( $cache_args );

	if ( $cache_args ) {

		foreach ( $template_args as $key => $value ) {
			if ( is_scalar( $value ) || is_array( $value ) ) {
				$cache_args[$key] = $value;
			} else if ( is_object( $value ) && method_exists( $value, 'get_id' ) ) {
				$cache_args[$key] = call_user_method( 'get_id', $value );
			}
		}

		if ( ( $cache = wp_cache_get( $file, serialize( $cache_args ) ) ) !== false ) {

			if ( ! empty( $template_args['return'] ) )
				return $cache;

			echo $cache;
			return;
		}
	}

	if ( file_exists( get_stylesheet_directory() . '/' . $file . '.php' ) )
		$file_path = get_stylesheet_directory() . '/' . $file . '.php';

	elseif ( file_exists( get_template_directory() . '/' . $file . '.php' ) )
		$file_path = get_template_directory() . '/' . $file . '.php';

	ob_start();
	$return = require( $file_path );
	$data = ob_get_clean();

	if ( $cache_args ) {
		wp_cache_set( $file, $data, serialize( $cache_args ), 3600 );
	}

	if ( ! empty( $template_args['return'] ) )
		if ( $return === false )
			return false;
		else
			return $data;

	echo $data;
}
