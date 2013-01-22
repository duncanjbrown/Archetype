<?php
/**
 * This class handles the customised login process, redirecting wp login and out actions
 * to our handlers, handling emails for usernames, etc.
 * __construct merely hooks things up in the right place
 *
 * @package archetype.users
 */

class Archetype_Email_Authentication {

	function __construct() {
		remove_filter( 'authenticate', 'wp_authenticate_username_password', 20, 3 );
		add_filter( 'authenticate', array( &$this, 'authenticate_email_password' ), 20, 3 );
		add_filter( 'login_url', array( &$this, 'login_url' ) );
		add_filter( 'logout_url', array( &$this, 'logout_url' ) );
		add_action( 'wp_login_failed', array( &$this, 'login_failed' ) );
	} 

	/**
	 * Return a custom login url for the wordpress loginout() function
	 * @return string the new url
	 */
	public function login_url() {
		return site_url( 'login', 'login' );
	}

	/**
	 * Return a custom login url for the wordpress loginout() function
	 * @return string the new url
	 */
	public function logout_url( $url ) {
		$new_url = remove_query_arg( 'redirect_to', $url );
		return add_query_arg( 'redirect_to', '/?logged_out=1', $new_url );
	}

	/**
	 * Redirect to our login page on failed login, too
	 * @return void
	 */
	public function login_failed() {

		$referer = $_SERVER['HTTP_REFERER'];  //check where login attempt came from

		if ( !empty( $referer ) && !strstr( $referer, 'wp-login' ) && !strstr( $referer, 'wp-admin' ) ) { //not login or admin

			if ( isset( $_POST['log'] ) && $login = $_POST['log'] ) {

				if ( $user = User::get_by_email( $login ) ) {

					if( is_wp_error( $user ) ) {
						tn_add_message( 'error', $user->get_error_message() );
					} else {
						tn_add_message( 'error', 'Incorrect password' );
					}

					$new_args = array(
						'login_failed' => true,
						'email' => urlencode( $login )
					);

					wp_redirect( add_query_arg( $new_args, $referer ) );
					die();
				}

			}

			if( strstr( $referer, 'register' ) ) {
				tn_add_message( 'error', 'That email is already registered' );
			} else {
				tn_add_message( 'error', 'Incorrect login details' );
			}
			wp_redirect( $referer );
			exit;
		}
	}

	/**
	 * If an email address is entered in the username box, then look up the matching username and authenticate as per normal, using that.
	 *
	 * @param string  $user
	 * @param string  $username
	 * @param string  $password
	 * @return Results of autheticating via wp_authenticate_username_password(), using the username found when looking up via email.
	 */
	public function authenticate_email_password( $user, $username, $password ) {

		if ( !empty( $username ) ) {
			$user = get_user_by( 'email', $username );
			if ( isset( $user, $user->user_login, $user->user_status ) && 0 == (int) $user->user_status )
				$username = $user->user_login;
		}

		if( $user ) {
			$ri_user = new User( $user->ID );
			
			// don't let a soft-deleted user log in
			if( $ri_user->is_deleted() )
				return false;
		}

		if( !$password || !$user )
			return false;

		return wp_authenticate_username_password( null, $username, $password );
	}
}

if( defined( 'AT_EMAIL_SIGNUPS' ) && AT_EMAIL_SIGNUPS == true )
	new Archetype_Email_Authentication();
