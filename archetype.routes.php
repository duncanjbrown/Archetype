<?php
/**
 * @package archetype
 * @subpackage route
 */

/**
 * Add arbitrary routing points - hook it on init.
 * Although all the code is original,this class owes a huge debt 
 * to hm-rewrite by Joe Hoyle at Humanmade - credit him, not me, if you must.
 * 
 * @package archetype.route
 */
class Archetype_Route {

	// The rule against which we match a request
	private $regex;

	// Callbacks for the matched rule
	private $args;

	// Names for query vars required
	private $names;

	/**
	 * Send in a regex, eg 'myroute/?$', plus args for callbacks, and an array of query vars used
	 *
	 * @param string $regex the rewrite regex
	 * @param array $args see below
	 * @param array $names query vars to add
	 */
	function __construct( $regex, $args, $names  = array() ) {

		$this->names = $names;
		$this->args = $args;
		$this->regex = $regex;

		add_action( 'rewrite_rules_array', function( $rules ) use ( $regex, $args ) {
			$new_rules = array();

			$new_rules[$regex] = 'index.php?' . ( empty( $args['rewrite'] ) ? '' : $args['rewrite'] );

			return $new_rules + $rules;
		});

		add_action( 'query_vars', function( $vars ) use ( $names ) {
			foreach( $names as $name )  {
				$vars[] = $name;
			}
			return $vars;
		});

		add_filter( 'parse_request', array( &$this, 'try_match' ) ); 
	}

	/**
	 * Get the regex for this rule
	 * @return string 
	 */
	public function get_regex() {
		return $this->regex;
	}

	/**
	 * Attempt to match the regex against the request
	 * & engage callbacks if it matches
	 * 
	 * @param  string $request 
	 * @return bool
	 */
	public function try_match( $request ) {

		if ( $this->regex == $request->matched_rule )
			$this->engage_callbacks();
	}

	private function engage_callbacks() {

		if ( isset( $this->args['query_callback'] ) )
			add_filter( 'pre_get_posts', array( &$this, 'query_callback' ) );

		if ( isset( $this->args['title_callback'] ) )
			add_filter( 'wp_title', array( &$this, 'title_callback' ) );

		if ( isset( $this->args['wp'] ) )
			add_action( 'wp', array( &$this, 'wp' ) );

		if ( isset( $this->args['template'] ) ) {
			$this->template = $this->args['template'];
			add_filter( 'template_redirect', array( &$this, 'template_redirect' ), 1, 0 );
		}

	}

	function query_callback( &$query ) {

		if ( ! $query->is_main_query() )
			return;

		call_user_func( $this->args['query_callback'], $query );
	}

	function title_callback( $title ) {

		return call_user_func( $this->args['title_callback'], $title );
	}

	function wp( $wp ) {
		call_user_func( $this->args['wp'], $wp );
	}

	function template_redirect() {
		include locate_template( $this->template );
		exit;
	}

}



/**
 * Call this action on routes that require login
 * The login page may do what it likes with the referer (eg redirect it)
 *
 * cf http://stackoverflow.com/a/2158327/751089
 */
add_action( 'at_before_logged_in_page', function( $path ) {

	if ( !is_user_logged_in() ) {
		tn_add_message( 'error', 'You must be logged in to do that' );
		wp_redirect( '/login?referer=' . $path, 302 );
		die();
	}

} );

add_action( 'at_before_logged_out_page', function( ) {

	if ( is_user_logged_in() ) {
		tn_add_message( 'success', 'You cannot visit that page when logged in' );
		wp_redirect( '/', 302 );
		die();
	}

} );