<?php
/**
 * Users
 *
 * @package archetype
 * @subpackage archetype.users
 */

define( 'AT_DELETED_META', 'at_deleted' );

/**
 * Encapsulate the WP User so it can be treated as an object
 *
 */
class User {

	private static $_current_user;
	protected static $users;

	/**
	 * Get the current user
	 * @return User
	 */
	public static function current_user() {

		if ( is_user_logged_in() )
			return self::get( get_current_user_id() );

		else
			return false;
	}

	/**
	 * Get the user by their email address
	 *
	 * @param string  $email the email to look for
	 * @return User|WP_Error
	 */
	public static function get_by_email( $email ) {

		if ( !is_email( $email ) )
			return false;

		$user = get_user_by( 'email', $email );

		if ( ! $user ) {
			return false;
		}

		return new User( $user->ID );
	}

	/**
	 * Convert an array of ids into an array of _users,
	 * leaving out deleted users
	 * @param mized int|array $ids the id(s) to convert
	 * @return User[]
	 */
	public static function users_from_ids( $ids ) {

		//accept an int
		if ( !is_array( $ids )) {
			$ids = (array) $ids;
		}

		// don't get deleted users
		foreach( $ids as $key => $id ) {
			if( get_user_meta( $id, AT_DELETED_META, true ) )
				unset( $ids[$key] );
		}

		return array_map( function( $id ) {
			return new User( $id );
		}, $ids );

	}

	/**
	 * Register a user from an email address
	 *
	 * @param string  $email    the email address to register by
	 * @param string  $password optional the password to register them with
	 * @param string  $username optional the username (or a random one will be assigned)
	 * @return User|WP_Error
	 */
	public static function register_by_email( $email, $password = false, $username = false ) {

		$validation = self::email_can_register( $email );

		if( is_wp_error( $validation ) )
			return $validation;

		if ( !$password )
			$password = wp_generate_password();

		// create the user 
		if( !$username )
			$username = self::get_unique_uid();

		$user = wp_create_user( $username, $password, $email );

		if ( is_wp_error( $user ) ) {
			return $user;
		}

		$_user = new User( $user );

		wp_set_password( $password, $_user->get_id() );

		do_action( 'at_user_created', $_user, 'email' );

		if( get_option( 'at_send_notification_email' ) )
			wp_new_user_notification( $_user->get_id(), $password );

		return $_user;
	}

	/**
	 * Check if an email is allowed to register
	 * @param  string $email 	
	 * @return mixed         true | WP_Error
	 */
	public static function email_can_register( $email = '' ) {

		if ( !$email ) {
			return new WP_Error( 'email_missing', 'You need to enter an email address' );
		}

		if ( !is_email( $email ) ) {
			return new WP_Error( 'invalid_email', 'The address you entered was invalid' );
		}

		if ( $existing_user = self::get_by_email( $email ) ) {
			return new WP_Error( 'email_registered', 'That address is already registered', $existing_user );
		}

		return true;
	}

	/**
	 * Get a user by ID
	 * Will return cached user if it can
	 *
	 * @param int     $id the id to get
	 */
	public static function get( $id ) {
		if ( ! isset( self::$users[$id] ) )
			self::$users[$id] = new User( $id );

		return self::$users[$id];
	}

	/**
	 * @param int     $user_id
	 */
	public function __construct( $user_id ) {

		if ( empty( $user_id ) )
			throw new Exception( '$user_id empty' );

		$this->_id = $user_id;

		if ( ! $this->get_user() )
			throw new Exception( '$user_id does not exist' );
	}

	/**
	 * Check if this user is the currently logged in user
	 *
	 * @return bool
	 */
	public function is_current_user() {

		return $this->get_id() == get_current_user_id();
	}

	/**
	 * Get the WordPress WP_User object
	 *
	 * @return WP_User
	 */
	public function get_user() {

		if ( ! isset( $this->_user ) )
			$this->_user = new WP_User( $this->_id );

		if ( ! $this->_user->ID ) {
			unset( $this->_user );
			return null;
		}

		return $this->_user;
	}

	/**
	 * Get the ID of the user
	 *
	 * @return int
	 */
	public function get_id() {
		return $this->_id;
	}

	/**
	 * Get the email address of the user
	 *
	 * @return string
	 */
	public function get_email() {
		return $this->get_user()->user_email;
	}

	/**
	 * Get the first name of the user
	 *
	 * @return string
	 */
	public function get_first_name() {
		return $this->get_user()->first_name;
	}

	/**
	 * Get the last name of the user
	 *
	 * @return string
	 */
	public function get_last_name() {
		return $this->get_user()->last_name;
	}

	/**
	 * Get the user's proper name if possible
	 * @return string
	 */
	public function get_proper_name() {

		return trim( $this->get_first_name() . ' ' . $this->get_last_name() );
	}

	/**
	 * Get the login name for the user
	 *
	 * @return string
	 */
	public function get_login() {
		return $this->get_user()->user_login;
	}

	/**
	 * Get the role for the user
	 *
	 * @return string
	 */
	public function get_role() {
		return reset( $this->get_user()->roles );
	}

	/**
	 * Get meta for the user
	 *
	 * @param string  $key
	 * @param bool    $single
	 * @return mixed
	 */
	public function get_meta( $key, $single = false ) {
		return get_user_meta( $this->get_id(), $key, $single );
	}

	/**
	 * Update a meta key for the user
	 *
	 * @param string  $key
	 * @param mixed   $value
	 * @return bool
	 */
	public function update_meta( $key, $value ) {
		return update_user_meta( $this->get_id(), $key, $value );
	}

	/**
	 * Add a meta key=>value for the user
	 *
	 * @param string  $key
	 * @param mixed   $value
	 * @return bool
	 */
	public function add_meta( $key, $value ) {
		return add_user_meta( $this->get_id(), $key, $value );
	}

	/**
	 * Delete a meta key=>value for the user
	 *
	 * @param string  $key
	 * @param mixed   $value optional
	 * @return bool
	 */
	public function delete_meta( $key, $value = '' ) {
		return delete_user_meta( $this->get_id(), $key, $value );
	}

	/**
	 * Log the user in
	 * @return void 
	 */
	public function login() {
		wp_set_current_user( $this->get_id() );
		wp_set_auth_cookie( $this->get_id() );
		do_action('wp_login', $this->get_login() );
	}

	public function __toString() {
		return $this->get_id();
	}

	/**
	 * @return void
	 */
	public function soft_delete() {
		$this->update_meta( AT_DELETED_META, time() );
	}

	/**
	 * Has this user been deleted?
	 */
	public function is_deleted() {
		return (bool) $this->get_deletion_date();
	}

	/**
	 * When was the user deleted?
	 *
	 * @return timestamp|false
	 */
	public function get_deletion_date() {
		return $this->get_meta( AT_DELETED_META, true );
	}

	/**
	 * Undelete this user
	 */
	public function undelete() {
		$this->delete_meta( AT_DELETED_META );
	}

	/**
	 * Send an html email to this user
	 *
	 * @param string  $subject     the subject line
	 * @param string  $content     the content of the email
	 * @param array|string $headers     the headers (see wp_mail)
	 * @param array|string $attachments the attachments (see wp_mail)
	 */
	public function send_email( $subject, $content, $headers = false, $attachments = false ) {
		ri_log_email( $this->get_email(), $subject );
		add_filter( 'wp_mail_content_type', function () { return "text/html"; } );
		error_reporting( 0 );
		return wp_mail( $this->get_email(), $subject, $content, $headers, $attachments );
	}

	/**
	 * Generate a unique username for this user
	 *
	 * @return string the new user id
	 */
	public static function get_unique_uid() {

		do {
			$id = uniqid( 'user_' );
		} while ( $user = get_user_by( 'login', $id ) );

		return $id;
	}

	/**
	 * Is this user connected to Facebook?
	 * @return boolean 
	 */
	public function has_facebook() {
		return (bool) $this->get_meta( AT_FB_ID_META );
	}

	public function get_avatar() {
		return get_avatar( $this->get_id() );
	}

}


/**
 * This class finds users according to timestamp criteria in their usermeta
 */
class Users_Timegroup {

	/**
	 * Pass in the meta_key, the strtotime (relative to now), and number of seconds error to allow 
	 * Eg to find user's whose 'deleted_on' timestamp is 1 week away, to the precision of an hour either side, pass
	 * new Users_Timegroup( 'deleted_on', 'now + 1 week', 3599 )
	 * 
	 * Note that you should knock a second off eg 3600 to make sure you don't duplicate users across hour boundaries
	 *
	 * @param string $meta_key the usermeta key for the timestamp
	 * @param string $strtotime the time you want to group users by (relative to now)
	 * @param int $precision seconds error to allow either side
	 */
	function __construct( $meta_key, $strtotime, $precision ) {
		$this->meta_key = $meta_key;
		$target_ts = strtotime( $strtotime );
		$this->upper_bound = $target_ts + ( $precision ); 
		$this->lower_bound = $target_ts - ( $precision );
	}

	public function get_users() {
		return User::users_from_ids( $this->do_query() );
	}

	private function do_query() {

		global $wpdb;

		$sql = $wpdb->prepare( "SELECT user_id FROM wp_usermeta WHERE meta_key = %s AND meta_value BETWEEN %d AND %d", $this->meta_key, $this->lower_bound, $this->upper_bound );

		return $wpdb->get_col( $sql );
	}


}
