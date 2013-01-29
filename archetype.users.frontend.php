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
		$user = User::register_by_email( $_POST['email'], $_POST['password'] );
		return $user;	
	}

	/**
	 * Process the info submitted from the front end
	 * @return mixed WP_Error|User user_id on success, error on failure 
	 */
	public function handle_submit() {

		foreach( $this->fields as $field ) {
			$posted = $field->get_posted_value();
			if( !$field->is_valid( $posted ) && $field->opts['required_for_signup'] )
				return new WP_Error( 'invalid_signup_details', $field->name );			
		}

		$user = $this->do_signup();

		if( is_wp_error( $user ) ) {
			tn_add_static_message( 'error', $user->get_error_message() );
			return $user;
		}

		foreach( $this->fields as $field ) {
			$field->save( $user->get_id() );
		}

		return $user;
	}

}

// init the form here to pick up submissions and set up fields
add_action( 'wp_head', function() {
	Archetype_User_Signup_Form::get_instance();
});