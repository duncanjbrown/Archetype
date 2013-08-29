<?php
/**
 * @package archetype
 * @subpackage messages
 */

define( 'TN_MESSAGES_COOKIE', 'TN_theme_notices' );
define( 'TN_MESSAGES_URI', trailingslashit( plugin_dir_url( __FILE__ ) ) );
define( 'TN_MESSAGES_PATH', trailingslashit( plugin_dir_path( __FILE__ ) ) );

/**
 * Style
 */
add_action( 'init', function() {
	wp_enqueue_style( 'theme-notices-css', TN_MESSAGES_URI . 'css/theme-notices.css' );
});

/**
 * Stores messages in a cookie 
 * That cookie holds a JSON hash of types => messages
 * You can get the hash with tn_get_messages()
 * @package archetype.messages
 */
class TN_Messages {

	/* A native array of $type => $message */
	private $messages = false;

	/* A key to get the cookie */
	private $cookie_handle;

	/**
	 * @var an instance of this singleton
	 */
	private static $_instance;

	/**
	 * Set up the object with a reference to the cookie it will be dealing with
	 * @access private
	 */
	private function __construct( $cookie_handle ) {
		$this->cookie_handle = $cookie_handle;
	}

	public static function get_instance() {
		if( !self::$_instance ) {
			self::$_instance = new TN_Messages( TN_MESSAGES_COOKIE ); 
		}
		return self::$_instance;
	}

	/**
	 * Applies filters to the results of _read_messages so you can hook 'static' messages
	 * as well as cookie-fied redirect messages
	 * @return array ( 'type' => 'message' )
	 */
	public function get_messages() {
		$messages = $this->_read_messages();
		$this->clear_messages();
		return apply_filters( 'tn_get_messages', $messages );	
	}

	/**
	 * Add a message to this object's cookie
	 * Pass an array as specified for $messages, above
	 * @param $array the messages to add
	 * @return void
	 */
	public function add_message( $new_message ) {	

		$messages = $this->_read_messages();

		if( !is_array( $messages ) )
			$messages = array();

		array_push( $messages, $new_message );

		$this->_write_messages( $messages );
	}

	/**
	 * Write to the messages to the internal record
	 * And store them in the cookie
	 * @param messages a 2d array of messages
	 */
	private function _write_messages( $messages ) {
		$this->messages = $messages;
		$y = $this->encode_cookie( $messages );
		setcookie( $this->cookie_handle, $y, 0, '/' );
	}

	/**
	 * Get the messages out of the cookie if there are any
	 * @return array 
	 */
	private function _read_messages() {

		if( $this->messages ) {
			return $this->messages;
		}

		if( !isset( $_COOKIE[ $this->cookie_handle ] ) || $_COOKIE[ $this->cookie_handle ] == "null" ) {
			return array();
		}

		return $this->parse_cookie( $_COOKIE[ $this->cookie_handle ] );
	}

	/* Clear out the cookie */
	public function clear_messages() {
		$this->_write_messages( null );
	}

	/**
	 * Get the native array values out of a JSON cookie value
	 * @param string $value some JSON
	 * @return array
	 */
	private function parse_cookie( $value ) {

		if( !$value || $value == 'null' )
			return array();

		if(  $array = json_decode( stripslashes( $value ), true ) ) // strip slashes and return array
			return $array;

		return array();
	}

	/**
	 * Prepare an array for writing to the cookie
	 * @param array $value the array to encode
	 * @return string JSON to save in the cookie
	 */
	protected function encode_cookie( $value ) {
		$json = json_encode( $value );
		return $json;
	}

	/**
	 * Add a message to the cookie messages array, to show on this page
	 * This will show a message without needing a redirect
	 *
	 * @param array $message the 'success' => 'message' (eg) to add
	 * @return void
	 */
	public static function add_static_message( $message ) {

		add_filter( 'tn_get_messages', function( $messages ) use ( $message ) {

			array_push( $messages, $message );
			return $messages;

		},10 );

	}

}

// Make messages available in the admin area and the front end
add_action( 'template_redirect', 'tn_prepare_messages' );
add_action( 'admin_init', 'tn_prepare_messages' );

/**
 * Get the messages ready to show in the tn_messages action
 * @package TN_FrontEnd
 */
function tn_prepare_messages() {

	$_messages = TN_Messages::get_instance();

	$messages = $_messages->get_messages();

	if( $messages ) :

		add_action( 'tn_messages', function() use ( $messages ) { 

			foreach( $messages as $key => $message ) {
				// we add data-message - useful if element is eg cloned to pull it downscreen
				if( isset( $message['success'] ) ) : ?>
					<div data-message="<?php echo ++$key; ?>" class="tn_message success"><?php echo $message['success']; ?></div>
				<?php elseif ( isset( $message['error'] ) ) : ?>
					<div data-message="<?php echo ++$key; ?>" class="tn_message error"><?php echo $message['error']; ?></div>	
				<?php endif; 
			}

		});

	endif;
}

/**
 * Add a message to show on the next page
 * @param string $type error|success
 * @param string $message the message itself
 */
function tn_add_message( $type, $message ) {
	$m = TN_Messages::get_instance();
	$m->add_message( array( $type => $message ) );
}

/**
 * Add a static message to the array (ie to show on this page)
 * @param string $type error|success
 * @param string $message the message itself
 */
function tn_add_static_message( $type, $message ) {
	TN_Messages::add_static_message( array( $type => $message ) );
}

/**
 * Display any messages added on the last page and static messages from this page
 */
function tn_messages() {
	do_action( 'tn_messages' );
}