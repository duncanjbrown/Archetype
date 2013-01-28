<?php
/**
 * @package archetype
 * @subpackage users
 */

/**
 * Front end signup
 */

class Archetype_User_Signup_Form {

	private static $_instance;

	public static function get_instance() {

		if ( self::$_instance )
			return self::$_instance;

		return self::$_instance = new Archetype_User_Signup_Form();
	}

	private function __construct() {
		$this->fields = apply_filters( 'at_signup_fields', array() );
		add_action( 'at_show_signup_fields', array( $this, 'show_fields' ) );
	}

	public function show_fields() {
		foreach( $this->fields as $field )
			$field->show_field();
	}

	/**
	 * Attempt to register the user
	 * @return mixed WP_Error|User user_id on success, otherwise false
	 */
	protected function do_signup() {
		$user = User::register_by_email( $_POST['at_email'], $_POST['at_password'] );
		return $user;	
	}

	/**
	 * Process the info submitted from the front end
	 * @return mixed WP_Error|User user_id on success, error on failure 
	 */
	public function handle_submit() {

		$user = $this->do_signup();

		if( is_wp_error( $user ) ) {
			tn_add_static_message( 'error', $user->get_error_message() );
			return $user;
		}

		foreach( $this->fields as $field ) {

			if( !$field->is_valid() && $field->opts['required_for_signup'] )
				return new WP_Error( 'invalid_signup_details', $field->name );
			
			$field->save( $user->get_id() );
		}

		return $user;
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

	function is_valid() {
		if( isset( $_POST[$this->slug] ) ) {
			return call_user_func( $this->opts['validation'], $_POST[$this->slug] );
		}
	}
}

add_action( 'wp_head', function() {
	// init the form here to pick up submissions and set up fields
	Archetype_User_Signup_Form::get_instance();
});

/**
 * Show the signup form
 */
function at_signup_form() {
	include( 'views/signup.php' );
}