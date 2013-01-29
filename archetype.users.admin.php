<?php
/**
 * @package archetype
 * @subpackage  archetype.users
 */

define( 'AT_USER_NONCE', '_at_update_user_profile' );

/**
 * Add fields to the user profile by extending this class
 *
 * @package Archetype_Users
 */
abstract class Archetype_User_Field {

	/**
	 *
	 *
	 * @var $name the name of this field (used in the label)
	 * @access protected
	 */
	protected $name;

	/**
	 *
	 *
	 * @var $slug the id for this field
	 * @access protected
	 */
	protected $slug;

	/**
	 * Other options - validation etc
	 * @var array
	 */
	protected $opts;

	/**
	 *
	 *
	 * @var $meta_key the usermeta key this field will work on
	 * @access protected
	 */
	protected $meta_key;

	/**
	 *
	 *
	 * @var $desc the description to accompany the field
	 * @access protected
	 */
	protected $desc;

	/**
	 * Add a new field to the user-admin page or the front end
	 * Hook this on admin_init.
	 *
	 * @param string  $name        the name of the profile field
	 * @param string  $description the field's description to display alongside it
	 * @param string  $meta_key    the usermeta key under which you'll store this data
	 */
	function __construct( $name, $description, $meta_key, $opts = array() ) {
		$this->name = $name;
		$this->slug = $meta_key;
		$this->desc = $description;

		$defaults = array( 
			'validation' => '__return_true',
			'required_for_signup' => false,
			'show_in_signup' => false,
			'signup_only' => false,
			'hidden' => false
		);

		$opts = wp_parse_args( $opts, $defaults );
		$this->opts = $opts;
		$this->meta_key = $meta_key;

		if( $opts['show_in_signup'] || $opts['signup_only'] )
			$this->register_as_signup_field();

		if( !$opts['signup_only'] )
			$this->attach_hooks();
	}

	/**
	 * Hook this field up to the signup form
	 * @return void 
	 */
	private function register_as_signup_field() {
		add_filter( 'at_signup_fields', function( $fields ) {
			$fields[] = $this;
			return $fields;
		} );
	}

	/**
	 * Get the posted value for this field if there is one
	 * @return mixed field data, or false
	 */
	public function get_posted_value() {
		if( isset( $_POST[$this->slug] ) ) 
			return $_POST[$this->slug];

		return false;
	}

	/**
	 * Is this valid input for this field?
	 * @param mixed $input
	 * @return boolean [description]
	 */
	function is_valid( $input ) {
		return call_user_func( $this->opts['validation'], $input );
	}
	

	/**
	 * Set up the hooks for displaying and saving this field
	 * @return void 
	 */
	abstract function attach_hooks();

	/**
	 * Update the usermeta on the profile page save action
	 * Won't save if the validation doesn't return true
	 *
	 * @param int     $user_id the ID of the user to update (supplied by WP)
	 */
	public function save( $user_id ) {

		if ( !current_user_can( 'edit_user', $user_id ) || !current_user_can( 'administrator' ) )
			return false;

		$user = User::get( $user_id );

		$value = sanitize_text_field( $_POST[$this->meta_key] );

		$valid = call_user_func( $this->opts['validation'],  $value );

		if( !$valid )
			return false;

		$user->update_meta( $this->meta_key, $value );
	}

	/**
	 * Get the name
	 *
	 * @return string the field name
	 */
	public function get_name() {
		return $this->name;
	}

	/**
	 * Get the meta key
	 *
	 * @return string the meta key
	 */
	public function get_meta_key() {
		return $this->meta_key;
	}

	/**
	 * Get the slug
	 *
	 * @return string the slug
	 */
	public function get_slug() {
		return $this->slug;
	}

	/**
	 * Get the desription
	 *
	 * @return string the field desc
	 */
	public function get_desc() {
		return $this->desc;
	}

	/**
	 * Show the field using an include
	 *
	 * @return void
	 */
	public function show_field( $user ) {
		include 'views/admin_text_field.php';
	}

}

class Archetype_User_Admin_Field extends Archetype_User_Field {

	function attach_hooks() {
		add_action( 'show_user_profile', array( &$this, 'show_field' ) );
		add_action( 'edit_user_profile', array( &$this, 'show_field' ) );
		add_action( 'personal_options_update', array( &$this, 'save' ) );
		add_action( 'edit_user_profile_update', array( &$this, 'save' ) );
	}
}

class Archetype_User_Frontend_Field extends Archetype_User_Field {

	function attach_hooks() {
		add_action( 'at_show_frontend_fields', array( &$this, 'show_field' ) );
		add_action( 'at_save_frontend_fields', array( &$this, 'save' ) );
	}

	function show_field() {
		include( 'views/fields/text_field.php' );
	}

}

/**
 * Implement checkboxes for the User Admin page
 *
 */
class Archetype_User_Admin_Checkbox_Field extends Archetype_User_Admin_Field {

	/**
	 * Show the field using an include
	 *
	 * @return void
	 */
	public function show_field( $user ) {
		include 'views/admin_checkbox_field.php';
	}

	/**
	 * Update the usermeta on the profile page save action
	 * For internal wp use
	 *
	 * @param int     $user_id the ID of the user to update (supplied by WP)
	 */
	public function save( $user_id ) {

		if ( !current_user_can( 'edit_user', $user_id ) || !current_user_can( 'administrator' ) )
			return false;

		$user = User::get( $user_id );

		if ( !isset( $_POST[$this->meta_key] ) ) {
			$user->update_meta( $this->meta_key, '0' );
			return;
		}

		$value = sanitize_text_field( $_POST[$this->meta_key] );
		$user->update_meta( $this->meta_key, $value );
	}

}

class Archetype_User_Admin_Select_Field extends Archetype_User_Admin_Field {

	protected $options_callback;

	/**
	 * As Archetype_User_Admin_Field, except takes an extra arg to supply the options for the select
	 * in 'value' => 'description' format
	 */
	function __construct( $name, $desc, $key, $callback, $options_callback ) {
		parent::__construct( $name, $desc, $key, $callback );
		$this->options_callback = $options_callback;
	}

	function show_field( $user ) {
		$options = call_user_func( $this->options_callback );
		include 'views/admin_select_field.php';
	}

}