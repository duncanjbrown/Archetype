<?php
/**
 * @package archetype
 * @subpackage  archetype.users
 */

define( 'AT_USER_NONCE', '_at_update_user_profile' );

/**
 * Frontend profile fields
 */
class Archetype_UserProfile {

	/**
	 *
	 *
	 * @var $fields the text fields in the form
	 * @access private
	 */
	private $fields;

	/**
	 *
	 *
	 * @var $array_fields the array fields in the form (eg checkbox arrays)
	 * @access private
	 */
	private $array_fields;

	/**
	 * @var $checkboxes the checkboxes in the form
	 * @access private
	 */
	private $checkboxes;

	/**
	 *
	 *
	 * @var $_user the wp_user in question
	 * @access private
	 */
	private $_user;

	/**
	 * Handle form submission and updating metadata for a custom user profile page.
	 * You need to provide the form yourself and register fields with at_register_profile_field()
	 *
	 * @param int     $user_id the user in question
	 * @param int     $nonce   the nonce for the form
	 */
	function __construct( $user_id, $nonce ) {

		$this->_user = User::get( $user_id );
		$this->fields = apply_filters( 'at_profile_fields', array() );
		$this->array_fields = apply_filters( 'at_profile_array_fields', array() );
		$this->checkbox_fields = apply_filters( 'at_profile_checkbox_fields', array() );

		if ( !isset( $_POST[ '_wpnonce' ] ) || !wp_verify_nonce( $_POST[ '_wpnonce'], AT_USER_NONCE ) )
			return; // we won't parse this if there's no nonce

		if ( $this->fields )
			$updated = $this->parse_fields();

		if( is_wp_error( $updated ) ) {
			tn_add_message( 'error', $updated->get_error_message() );
			return;
		}

		if ( $this->array_fields ) {
			$array_updated = $this->parse_array_fields();
			if( $array_updated )
				$updated = true;
		}

		if( $this->checkbox_fields ) {
			$boxes_updated = $this->parse_checkbox_fields();
			if( $boxes_updated )
				$updated = true;
		}

		if ( $updated ) {
			tn_add_static_message( 'success', '<span class="icon-ok"></span>&emsp;Your details have been updated' );
		}

	}

	/**
	 * Cycle through the fields registered and try and get posted data
	 * Then update the user accordingly
	 *
	 * @return bool|wp_error
	 */
	public function parse_fields() {

		$updated = false;

		foreach ( $this->fields as $field ) {
			if ( isset( $_POST[ $field ] ) ) {
					$field_updated = $this->update_userdata( $field, $_POST[ $field ] );
				if( $field_updated && is_wp_error( $field_updated ) )
					return $field_updated;
				if( $field_updated ) 
					$updated = true;
			}
		}

		return $updated;
	}

	/**
	 * Cycle through the array fields registered and try and get posted data
	 * Then update the user accordingly
	 *
	 * @return true
	 */
	public function parse_array_fields() {

		$updated = false;

		foreach ( $this->array_fields as $array_field ) {
			if ( isset( $_POST[ $array_field ] ) ) {
				$fields_updated = $this->update_userdata_array( $array_field, $_POST[ $array_field ] );
				if( $fields_updated )
					$updated = true;
			} else { // if nothing is passed, we assume the boxes are cleared, so we pass []
				$this->update_userdata( $array_field, array( ) );
				$updated = true;
			}
		}

		return $updated;
	}

	public function parse_checkbox_fields() {

		$updated = false; 

		foreach( $this->checkbox_fields as $cb_field ) {
			if( isset( $_POST[ $cb_field ] ) ) {
				$cb_updated = $this->update_userdata( $cb_field, 1 );
				if( $cb_updated )
					$updated = true;
			} else {
				$cb_updated = $this->update_userdata( $cb_field, false );
				$updated = true;
			}
		}

		return $updated;
	}

	/**
	 * Update the usermeta for the field in question
	 *
	 * @param unknown $field string the key for the metafield
	 * @param unknown $new   string the new metavalue
	 */
	private function update_userdata( $field, $new ) {

		// if the field is native user data delegate to the update wp_user_function
		if ( in_array( $field, array( 'password' ) ) ) {
			return $this->update_password( sanitize_text_field( $new ), sanitize_text_field( $_POST['confirm_password'] ) );
		}

		$_new = sanitize_text_field( $new );

		if ( $new && !$_new ) //some deeply unsafe input, everything was sanitized, change nothing
			return false;

		$old = $this->_user->get_meta( $field, true );

		if ( $old !== $new ) {
			$this->_user->update_meta( $field, $new );
			return true;
		}

		if ( $old && !$new ) {
			$this->_user->update_meta( $field, null );
			return true;
		}
	}

	/**
	 * Update the user's password using two fields, $new and $confirmation (passed from update_userdata)
	 * @param $new string the new password
	 * @param $confirmation string the password to check against
	 */
	private function update_password( $new, $confirmation ) {

		if( ( $new && !$confirmation ) || ( !$new && $confirmation ) )
			return new WP_Error( 'at_passwords_not_matching', 'Passwords do not match' );

		if( !$new || !$confirmation )
			return false;

		if( $new != $confirmation )
			return new WP_Error( 'at_passwords_not_matching', 'Passwords do not match' );

		wp_set_password( $new, $this->_user->get_id() );
		wp_set_current_user(  $this->_user->get_id() );
		wp_set_auth_cookie(  $this->_user->get_id(), true );
		return true;
	}

	/**
	 * Update the usermeta for the field in question, which should hold an array
	 *
	 * @param unknown $field     string the key for the metafield
	 * @param unknown $new_array string the new array to put in
	 */
	private function update_userdata_array( $field, $new_array ) {

		$updated = false;

		$new_array = array_map( function ( $member ) {
				return sanitize_text_field( $member );
			}, $new_array );


		$old_array = ( array ) $this->_user->get_meta( $field, true );

		if ( array_diff( $new_array, $old_array ) || array_diff( $old_array, $new_array ) ) {
			$this->_user->update_meta( $field, $new_array );
			$updated = true;
		}

		return $updated;
	}

}

/**
 * Add a profile field
 *
 * @param string $field the field name
 */
function at_register_profile_field( $field ) {
	add_filter( 'at_profile_fields', function( $fields ) use ( $field ) {
		$fields[] = $field;
		return $fields;
	} );
}

/**
 * Add an array of profile fields
 *
 * @param unknown $field string field name
 */
function at_register_profile_field_array( $field ) {
	add_filter( 'at_profile_array_fields', function( $fields ) use ( $field ) {
		$fields[] = $field;
		return $fields;
	} );
}

/**
 * Add an array of profile fields using a filter
 *
 * @param unknown $field string field name
 */
function at_register_profile_checkbox_field( $field ) {
	add_filter( 'at_profile_checkbox_fields', function( $fields ) use ( $field ) {
		$fields[] = $field;
		return $fields;
	} );
}

/**
 * Fields in the back end
 */

/**
 * Add fields to the user profile area by extending this class
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
		$this->slug = sanitize_title( $name );
		$this->desc = $description;

		$defaults = array( 
			'validation' => '__return_true',
			'required_for_signup' => false,
			'show_in_signup' => false,
			'signup_only' => false 
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
			return;

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


/**
 * On WP loaded, parse any posted fields against the current user id
 *
 * @todo move this over to AT_Frontend_Submission
 */
add_action( 'wp', function() {

	if ( !is_user_logged_in() )
		return;

	$user_id = get_current_user_id();
	//new Archetype_UserProfile( $user_id, AT_USER_NONCE );

} );

if ( is_admin() ) {

	add_action( 'admin_init', function() {
			//new Archetype_User_Field( 'Credit limit', 'The user\'s credit limit', 'at_credit_limit' );
			new Archetype_User_Admin_Field( 'Field X', 'My Field X', 'at_tab' );
			/*new Archetype_User_Field( 'Credit limit', 'The user\'s credit limit', 'at_credit_limit' );
			new Archetype_User_Admin_Checkbox_Field( 'Completed Signup', 'Has the user completed signup?', 'at_completed_signup' );
			new Archetype_User_Admin_Checkbox_Field( 'Taken Tour', 'Has the user taken the tour?', at_TOUR_META );
			new Archetype_User_Admin_Checkbox_Field( 'Receive roundup', 'Should the user receive the roundup email?', 'at_receive_roundup' );
			new Archetype_User_Admin_Select_Field( 'Timezone', 'What timezone is this user in?', 'at_timezone', 'at_get_timezone_array' );
			new Archetype_User_Admin_Button( 'Send a test welcome email', '', function( $user ) { 
				add_filter( 'at_send_admin_notifications', '__return_false' );
				wp_new_user_notification( $user->get_id() ); 
			} );
			new Archetype_User_Admin_Button( 'Send a test roundup email', '', function( $user ) { 
				$date = at_normalize_date( date( 'd/m/Y', time() ) );
				$edition = AT_Edition::get_by_date( $date );
				if( !$edition )
					return;
				add_filter( 'wp_mail_content_type', function () { return "text/html"; } );
				$user->send_email( 'The Daily Mix', $edition->get_daily_roundup( $user ) ); 
			} );
			new Archetype_User_Admin_Button( 'Send a test credit warning', '', function( $user ) { 
				$user->warn_low_credit();
			} );
			new Archetype_User_Admin_Button( 'Send a test end of sub email', '', function( $user ) { 
				$sub = $user->get_sub();
				$user->send_email( 'Your subscription has been cancelled', $sub->get_sub_cancelled_email() );
			} );
			new Archetype_User_Admin_Button( 'Send a test mailing list mail', '', function( $user ) { 
				$user->send_email( 'Welcome to the Daily Mix', $user->get_new_mailing_list_sub_email() );
			} );
			new Archetype_User_Admin_Button( 'Send a test trial sub warning email', '', function( $user ) {
				$content = hm_get_template_part( 'emails/trial_warning', array( 'days' => 3, 'return' => true ) );
				$user->send_email( 'Your trial subscription will expire in 3 days', $content );
			});
			new Archetype_User_Admin_Button( 'Send a test editorial', '', function( $user ) {

				error_reporting( 0 );

				$date = at_normalize_date( date( 'd/m/Y', time() ) );
				$edition = AT_Edition::get_by_date( $date );

				$editorial = $edition->get_editorial();
				
				if ( $editorial ) {
					$content = hm_get_template_part( 'emails/editorial', array( 'editorial' => $editorial, 'date' => new DateTime(), 'return' => true ) );
					$user->send_email( html_entity_decode( 'Real Interest: '. $editorial->get_title(), ENT_QUOTES, 'UTF-8' ), $content );
				}

			});*/


		} );

} 