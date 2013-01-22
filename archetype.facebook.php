<?php
/**
 * @package archetype
 * @subpackage archetype.sso
 */

// Facebook SDK
require( 'lib/facebook-php-sdk/src/facebook.php' );

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
	 * @var Archetype_Facebook
	 */
	private static $_instance = false;

	/**
	 * The facebook sdk object
	 * @var Facebook
	 */
	private $facebook;

	/**
	 * The access token for the current user
	 * @var string  
	 */
	private $token;

	/**
	 * Convenience function to display the JS Sdk code for the page header
	 * @return void
	 */
	public static function js_sdk() {
		$fb = self::get_instance();
		$fb->_js_sdk();
	}

	/**
	 * Provide the output FB expects for its 'channel URL'
	 * @return void 
	 */
	public static function channel() {
		$fb = self::get_instance();
		$fb->_channel();
	}

	/**
	 * Render a login button
	 * @return void
	 */
	public static function login_button( ) {
		$fb = self::get_instance();
		$fb->_login_button();
	}

	/**
	 * Render a login button
	 * @return void
	 */
	public static function connect_button( ) {
		$fb = self::get_instance();
		$fb->_connect_button();
	}

	/**
	 * Get an instance of the Singleton
	 * @return Archetype_Facebook
	 */
	public static function get_instance() {

		if( self::$_instance )
			return self::$_instance;

		if( !AT_FB_ID || !AT_FB_SECRET )
			die( 'archetype.facebook needs a Facebook App Id and Secret to be defined in Settings > Advanced Settings' );

		return self::$_instance = new Archetype_Facebook( AT_FB_ID, AT_FB_SECRET );
	}

	/**
	 * Convert the data from FB into a manageable format
	 * @param  $data $data the $_POST array
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
	 * @return User
	 */
	public static function find_user( $fb_id ) {

		$user = get_users( array(
			'meta_key' => AT_FB_ID_META,
			'meta_value' => $fb_id,
			'number' => 1
			) );

		if( empty( $user ) )
			return false;

		$_user = array_pop( $user );
		return User::get( $_user->ID );
	}

	/**
	 * When FB sends back data for a user who has authorised us, find or create a WP user to go with it
	 * @param  array $response
	 * @return User
	 */
	public static function find_or_create_user( $response ) {
		
		if( $user = self::find_user( $response['id'] ) )
			return $user;

		$fb = Archetype_Facebook::get_instance();
		$fb->set_access_token( $response['token'] );

		$details = $fb->get_userinfo();

		if( !( $user = User::get_by_email( $details['email'] ) ) )
			$user = User::register_by_email( $details['email'] );

		self::bind_user( $user, $response );

		ob_start();
		print_r( $user );
		error_log( ob_get_clean( ) );

		return $user;
	}

	/**
	 * Associate a WP user with a set of FB data
	 * @param  User   $user     
	 * @param  array $response the data from Facebook
	 * @return  void
	 */
	public static function bind_user( User $user, $response ) {
		$user->update_meta( AT_FB_ID_META, $response['id'] );
		$user->update_meta( AT_FB_TOKEN_META, $response['token'] );
		$user->update_meta( AT_FB_TOKEN_EXPIRES, $response['expires'] );
	}

	/**
	 * Create a new Facebook singleton
	 * @param string $app_id     the FB app id
	 * @param string $app_secret the FB app secret
	 */
	private function __construct( $app_id, $app_secret ) {
		$this->app_id = $app_id;
		$this->app_secret = $app_secret;

		$this->facebook = new Facebook(array(
		  'appId'  => $app_id,
		  'secret' => $app_secret
		));
	}

	/**
	 * Set the access token for this api session
	 * @param string $token
	 */
	public function set_access_token( $token ) {
		$this->token = $token;
		$this->facebook->setAccessToken( $token );
	}

	/**
	 * Get all the available info for this user from FB
	 * @return array 
	 */
	public function get_userinfo() {
		return $this->facebook->api( '/me' );
	}

	/**
	 * Display the login button
	 * @param string $text
	 * @return void
	 */
	public function _login_button( $text = 'Log in with Facebook' ) {
		include( 'views/fb_button.php' );
	}

	/**
	 * Display the login button
	 * @param string $text
	 * @return void
	 */
	public function _connect_button( $text = 'Connect with Facebook' ) {
		include( 'views/fb_connect_button.php' );
	}

	/**
	 * Get the permission string
	 * @return mixed string|false
	 */
	private function get_perms() {
		return AT_FB_PERMS;
	}

	/**
	 * Display the Js sdk code for the page header
	 * @return void
	 */
	public function _js_sdk() {
		include( 'views/fb_sdk.php' );
	}

	/**
	 * Generate the output for the FB channel url
	 * @return [type] [description]
	 */
	private function _channel() {
		include( 'views/fb_channel.php' );
	}
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
 * Set a URL on the site for FB to use as its channel URL
 */
add_action( 'init', function() {
	new Archetype_Route( '_fb_channel/?$', array(
		'query_callback' => function( $query ) {
			Archetype_Facebook::channel();
			die();
		}
	) );
});

/**
 * Handle AJAX callbacks from the JS SDK
 */
add_action( 'wp_ajax_nopriv_fb_login', 'at_fb_login' );

function at_fb_login() {
	$response = Archetype_Facebook::parse_fb_postdata( $_POST['response'] );
	$user = Archetype_Facebook::find_or_create_user( $response );
	$user->login();
	at_ajax_response( array( 'answer' => 'yes' ) );
}

add_action( 'wp_ajax_fb_connect', 'at_fb_connect' );

/**
 * Connect the currently logged in user to Facebook
 * @return void      
 */
function at_fb_connect( ) {
	$user = User::current_user(); // not hooked to nopriv_ so user is def. present
	$response = Archetype_Facebook::parse_fb_postdata( $_POST['response'] );
	$user = Archetype_Facebook::bind_user( $user, $response );
	at_ajax_response( array( 'answer' => 'yes' ) );
}

/**
 * Add scripts and styles
 */
add_action( 'init', function() {
	wp_enqueue_script( 'at_fb', AT_PLUGIN_URL . 'js/facebook.js' );
	wp_enqueue_style( 'at_fb_css', AT_PLUGIN_URL . 'css/facebook.css' );
});

/**
 * Add facebook script to the footer
 */
add_action( 'wp_footer', function() {
	Archetype_Facebook::js_sdk();
});

/**
 * Add Login with FB button to the login form
 */
add_filter( 'login_form_bottom', function() {
	$fb = Archetype_Facebook::get_instance();
	return at_buffer( array( $fb, 'login_button' ) ) . "<div id='fb_result'></div>";
});
