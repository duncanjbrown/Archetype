<?php
/**
 *
 *
 * @package archetype
 * @subpackage archetype.sso
 */

// Facebook SDK
require 'lib/facebook-php-sdk/src/facebook.php';

define( 'AT_FB_ID', get_option( 'at_fb_app_id' ) );
define( 'AT_FB_SECRET', get_option( 'at_fb_app_secret' ) );
define( 'AT_FB_PERMS', get_option( 'at_fb_perms' ) );
define( 'AT_FB_ID_META', 'at_fb_id' );
define( 'AT_FB_TOKEN_META', 'at_fb_token' );
define( 'AT_FB_TOKEN_EXPIRES', 'at_fb_expires' );

class Archetype_Facebook {

	var $app_id;
	var $app_secret;
	var $domain;

	/**
	 * An instance of the singleton
	 *
	 * @var Archetype_Facebook
	 */
	private static $_instance = false;

	/**
	 * Static flag if we're reauthing so we don't hook up the reauth flow twice
	 * @var boolean
	 */
	private static $reauth = false;

	/**
	 * The facebook sdk object
	 *
	 * @var Facebook
	 */
	private $facebook;

	/**
	 * The access token for the current user
	 *
	 * @var string
	 */
	private $token;

	/**
	 * Convenience function to display the JS Sdk code for the page header
	 *
	 * @return void
	 */
	public static function js_sdk() {
		$fb = self::get_instance();
		$fb->_js_sdk();
	}

	/**
	 * Provide the output FB expects for its 'channel URL'
	 *
	 * @return void
	 */
	public static function channel() {
		$fb = self::get_instance();
		$fb->_channel();
	}

	/**
	 * Render a login button
	 *
	 * @return void
	 */
	public static function login_button( ) {
		$fb = self::get_instance();
		$fb->_login_button();
	}

	/**
	 * Render a login button
	 *
	 * @return void
	 */
	public static function connect_button( ) {
		$fb = self::get_instance();
		$fb->_connect_button();
	}

	/**
	 * Render a login button
	 *
	 * @return void
	 */
	public static function reauth_button( ) {
		$fb = self::get_instance();
		$fb->_reauth_button();
	}

	/**
	 * Get an instance of the Singleton
	 *
	 * @return Archetype_Facebook
	 */
	public static function get_instance( $token = false ) {

		if ( self::$_instance )
			return self::$_instance;

		if ( !AT_FB_ID || !AT_FB_SECRET )
			die( 'archetype.facebook needs a Facebook App Id and Secret to be defined in Settings > Advanced Settings' );

		return self::$_instance = new Archetype_Facebook( AT_FB_ID, AT_FB_SECRET, $token );
	}

	/**
	 * Convert the data from FB into a manageable format
	 *
	 * @param unknown $data $data the $_POST array
	 * @return array
	 */
	public static function parse_fb_postdata( $data ) {

		$authresponse = $data['authResponse'];

		$response['token'] = $authresponse['accessToken'];
		$response['id'] = $authresponse['userID'];
		$response['expires'] = $authresponse['expiresIn'];

		return $response;
	}

	/**
	 * Find a user by their FB ID
	 * @param string $fb_id
	 * @param string $fb_token (optional)
	 *
	 * @return mixed User|false
	 */
	public static function find_user( $fb_id, $fb_token = false ) {

		$user = get_users( array(
				'meta_key' => AT_FB_ID_META,
				'meta_value' => $fb_id,
				'number' => 1
			) );

		// found a user by FB ID - return them
		if( !empty( $user ) ) {
			$_user = array_pop( $user );
			return User::get( $_user->ID );
		}

		// try using the token to find out their email
		if ( $fb_token ) {
			$fb = self::get_instance();
			$fb->set_access_token( $fb_token );
			$details = $fb->get_userinfo();
			if( $user = User::get_by_email( $details['email'] ) )
				return $user;
		}

		// no dice
		return false;
	}

	/**
	 * Associate a WP user with a set of FB data
	 *
	 * @param User    $user
	 * @param array   $response the response from FB
	 * @return User
	 */
	public static function bind_user( User $user, $response ) {

		$long_token = self::get_long_lived_access_token( $response['token'] );

		$user->update_meta( AT_FB_ID_META, $response['id'] );
		$user->update_meta( AT_FB_TOKEN_META, $long_token['token'] );
		$user->update_meta( AT_FB_TOKEN_EXPIRES, $long_token['expires'] + time() );

		$fb = self::get_instance( $long_token['token'] );
		$info = $fb->get_userinfo();

		$avatar = new Archetype_Facebook_Avatar( $user->get_id() );
		$avatar->procure( $info['username'] );
		$avatar->save();

		return $user;	
	}

	/**
	 * Create a new Facebook singleton
	 *
	 * @param string  $app_id     the FB app id
	 * @param string  $app_secret the FB app secret
	 * @param string  $token 	  access token
	 */
	private function __construct( $app_id, $app_secret, $token = false ) {
		$this->app_id = $app_id;
		$this->app_secret = $app_secret;

		$this->facebook = new Facebook( array(
				'appId'  => $app_id,
				'secret' => $app_secret,
				'cookie' => true
			) );

		if( $token )
			$this->set_access_token( $token );

		$this->get_userinfo();
	}

	public static function get_long_lived_access_token( $old_token ) {

		$id = AT_FB_ID;
		$secret = AT_FB_SECRET;

		$url = "https://graph.facebook.com/oauth/access_token?client_id={$id}&client_secret={$secret}&grant_type=fb_exchange_token&fb_exchange_token={$old_token}";

    	$response = curl_get_file_contents( $url );

    	parse_str( $response, $result );

    	return array( 
    		'token' => $result['access_token'],
    		'expires' => $result['expires']
    	);
	}

	/**
	 * Set the access token for this api session
	 *
	 * @param string  $token
	 */
	public function set_access_token( $token ) {
		$this->token = $token;
		$this->facebook->setAccessToken( $token );
	}

	/**
	 * Get all the available info for this user from FB
	 *
	 * @return array
	 */
	public function get_userinfo() {
		try {
			return $this->facebook->api( '/me' );
		} catch( Exception $e ) { 

			if( self::$reauth === true ) // only do this once
				return;

			self::$reauth = true;
			$this->nag_to_reauth();
		}
	}

	/**
	 * Show a message reminding the user they need to reconnect with FB
	 */
	public function nag_to_reauth() {
		tn_add_static_message( 'error', 'Your Facebook account needs to be reconnected. Visit your settings page to fix it.' );
	}

	/**
	 * @secrettodo make all these buttons static methods
	 */

	/**
	 * Show a button inviting the user to refresh their connection with FB
	 * @return void 
	 */
	public function _reauth_button() {
		if( self::$reauth )
			$this->_connect_button( 'Reconnect with Facebook' );
	}

	/**
	 * Display the login button
	 *
	 * @param string  $text
	 * @return void
	 */
	public function _login_button( $text = 'Log in with Facebook' ) {
		include 'views/fb_button.php';
	}

	/**
	 * Display the connect button
	 *
	 * @param string  $text
	 * @return void
	 */
	public function _connect_button( $text = 'Connect with Facebook' ) {
		include 'views/fb_connect_button.php';
	}

	/**
	 * Get the permission string
	 *
	 * @return mixed string|false
	 */
	private function get_perms() {
		return AT_FB_PERMS;
	}

	/**
	 * Display the Js sdk code for the page header
	 *
	 * @return void
	 */
	public function _js_sdk() {
		include 'views/fb_sdk.php';
	}

	/**
	 * Generate the output for the FB channel url
	 *
	 * @return [type] [description]
	 */
	private function _channel() {
		include 'views/fb_channel.php';
	}
}


function curl_get_file_contents($URL) {
	$c = curl_init();
	curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($c, CURLOPT_URL, $URL);
	$contents = curl_exec($c);
	$err  = curl_getinfo($c,CURLINFO_HTTP_CODE);
	curl_close($c);
	if ($contents) return $contents;
	else return FALSE;
}

/**
 * Options page
 */
add_action( 'at_main_options_page', function( $options_page ) {

		$facebook = new Archetype_Options_Page_Section( 'Facebook Settings' );

		$facebook->add_field( new Archetype_Options_Page_Text_Field( array(
					'key' => 'at_fb_app_id',
					'name' => 'Facebook App ID'
				) ) );

		$facebook->add_field( new Archetype_Options_Page_Text_Field( array(
					'key' => 'at_fb_app_secret',
					'name' => 'Facebook App Secret'
				) ) );

		$facebook->add_field( new Archetype_Options_Page_Text_Field( array(
					'key' => 'at_fb_perms',
					'name' => 'Facebook Permissions (comma-separated)'
				) ) );

		$options_page->add_section( $facebook );

	} );

/**
 * If the user has facebook set up their access token
 */
add_action( 'init', function() {
	if ( $user = User::current_user() ) {
		if( $user->has_facebook() ) {
			$token = $user->get_meta( AT_FB_TOKEN_META, true );
			$fb = Archetype_Facebook::get_instance( $token );
		}
	}
} );

/**
 * Set a URL on the site for FB to use as its channel URL
 */
add_action( 'init', function() {
		new Archetype_Route( '_fb_channel/?$', array(
				'query_callback' => function( $query ) {
					Archetype_Facebook::channel();
					die();
				}
			) );
	} );

/**
 * Handle AJAX callbacks from the JS SDK
 */
add_action( 'wp_ajax_nopriv_fb_login', 'at_fb_login' );

function at_fb_login() {

	$response = Archetype_Facebook::parse_fb_postdata( $_POST['response'] );
	
	if( $user = Archetype_Facebook::find_user( $response['id'], $response['token'] ) ) {
		Archetype_Facebook::bind_user( $user, $response );
		$user->login();
		at_ajax_response( array(
			'newUser' => false
		) );
	} else {
		at_ajax_response( array( 
			'newUser' => true,
			'facebookData' => $response,
			'avatar' => $user->get_avatar()
		) );
	}
}

add_action( 'wp_ajax_fb_connect', 'at_fb_connect' );

/**
 * Connect the currently logged in user to Facebook
 *
 * @return void
 */
function at_fb_connect( ) {
	$user = User::current_user(); // not hooked to nopriv_ so user is def. present
	$response = Archetype_Facebook::parse_fb_postdata( $_POST['response'] );
	$user = Archetype_Facebook::bind_user( $user, $response );
	at_ajax_response( array( 
		'newUser' => false,
		'facebookData' => $response,
		'avatar' => $user->get_avatar()
	) );
}

/**
 * If a user is created, check for FB postdata and bind them if it's present
 */
add_action( 'at_user_created', function( $user ) {

	if( isset( $_POST[AT_FB_ID_META] ) && isset( $_POST[AT_FB_TOKEN_META] ) )
		Archetype_Facebook::bind_user( $user, array( 
			'token' => $_POST[AT_FB_TOKEN_META],
			'id'    => $_POST[AT_FB_ID_META] ) );
} );

/**
 * Add scripts and styles
 */
add_action( 'init', function() {
		wp_enqueue_script( 'at_fb', AT_PLUGIN_URL . 'js/facebook.js' );
		wp_enqueue_style( 'at_fb_css', AT_PLUGIN_URL . 'css/facebook.css' );
	} );

/**
 * Add facebook script to the footer
 */
add_action( 'wp_footer', function() {
		Archetype_Facebook::js_sdk();
	} );