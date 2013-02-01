<?php
/**
 * @package archetype
 * @subpackage avatars
 */

define( 'AT_AVATAR_PATH_META', 'at_avatar_path' );
define( 'AT_AVATAR_OPTION', 'at_avatar_option' );

abstract class Archetype_Avatar {

	protected $avatar;
	protected $user_id;
	protected $type;

	function __construct( $user_id, $type ) {
		$this->user_id = $user_id;
		$this->type = $type;
	}

	/**
	 * Factory method to find the right avatar for the user
	 * @param  mixed $id_or_email
	 * @return mixed false|Archetype_Avatar
	 */
	public static function get_for_user( $id ) {
		$type = get_user_meta( $id, AT_AVATAR_OPTION, true );
		
		if( !$type )
			return false;

		$class = 'Archetype_' . ucfirst( $type ) . '_Avatar';

		if( class_exists( $class ) )
			return new $class( $id );

		return false;
	}

	/**
	 * Get the avatar bits and write them to the $avatar property
	 * @param string $source where it comes from
	 * @return void 
	 */
	abstract function procure( $source );

	/**
	 * Get the filename to call this avatar (without extension)
	 * @return string 
	 */
	protected function get_filename_without_extension() {
		return $this->user_id . '_' . $this->type;
	}

	/**
	 * Get the local dir to save the avatar to
	 * @return string 
	 */
	protected function get_local_dir() {
		$uploads = wp_upload_dir();
		$upload_path = trailingslashit( $uploads['basedir'] ) . 'avatars/' . $this->user_id .'/';

		if ( !is_dir( $upload_path ) ) {
    		mkdir( $upload_path, 755, true );
		}

		return $upload_path;
	}

	/**
	 * Save the avatar and make a note of its location in usermeta
	 * @return void 
	 */
	function save() {

		$path = $this->get_local_dir() . $this->get_filename_without_extension();
		$mime_type = at_get_string_mime_type( $this->avatar );
		$ext = at_get_mime_type_ext( $mime_type ); 

		$fully_qualified_path = $path . '.' . $ext;

		file_put_contents( $fully_qualified_path, $this->avatar );

		// save it with siteurl stripped so we can move domains
		$relative = str_replace( ABSPATH, '', $fully_qualified_path );
		$relative = update_user_meta( $this->user_id, AT_AVATAR_PATH_META, $relative );

		$chosen = get_user_meta( $this->user_id, AT_AVATAR_OPTION, true );

		if( !$chosen )
			update_user_meta( $this->user_id, AT_AVATAR_OPTION, $this->type );

		return $path;
	}

	/**
	 * Get the avatar
	 * @return string uri to the avatar
	 */
	function get_uri() {
		$relative = get_user_meta( $this->user_id, AT_AVATAR_PATH_META, true );

		if( !$relative )
			return false;

		return trailingslashit( site_url() ) . $relative;
	}

}

class Archetype_Facebook_Avatar extends Archetype_Avatar {

	function __construct( $user_id ) {
		parent::__construct( $user_id, 'facebook' );
	}

	function procure( $fb_username ) {
		$this->avatar = file_get_contents( "http://graph.facebook.com/${fb_username}/picture?width=200&height=200" );
	}

}

/**
 * Filter function so we can use our own avatars
 */
function at_custom_avatar_filter( $avatar, $id_or_email, $size, $default, $alt ) {

	$custom_avatar = Archetype_Avatar::get_for_user( $id_or_email );

	if ( $custom_avatar ) 
		$src = $custom_avatar->get_uri();
	elseif ( $avatar )
		return $avatar;
	else
		$src = $default;

	return sprintf( '<img src="%s" width="%s" height="%s" alt="%s" />',
		$src, $size, $size, $alt ); 
}

add_filter( 'get_avatar', 'at_custom_avatar_filter', 10, 5 );