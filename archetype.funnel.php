<?php
/**
 * @package archetype
 * @subpackage funnel
 */

define( 'AT_FUNNEL_META', 'at_funnel_progress' );

/**
 * Pass the user through a series of pages so they must visit them in order.
 *
 * This class is instatiated from init.
 * The first test that fails gets to execute a callback, typically a redirect,
 * on a hook of its choice, though it could be something more (or less) trivial
 *
 * @package archetype.funnel
 */
class Archetype_Funnel {

	/**
	 * This is the user to be put through the process
	 *
	 * @var User
	 */
	protected $user;

	/**
	 * Pass in a user who hasn't finished signup.
	 *
	 * This method takes in the phases from the second param
	 * and subjects the user to each of their test cases.
	 * In the event that a user fails a test, that test
	 * is allowed to execute its callback.
	 *
	 * @param array $phases the phases to go through
	 * @param array $opts other settings
	 */
	function __construct( $phases = array(), $opts = array() ) {

		$this->user = User::current_user();
		$this->phases = $phases;

		$this->opts = wp_parse_args( $opts, array(
			'meta_key' => AT_FUNNEL_META ) );

		// get the first phase which the user fails (eg the one they need to do next)
		$next = $this->get_next_phase();

		// if no phases are failed, do nothing. ?should call ::complete() here?
		if( !$next )
			return;

		// we may want to check if we're on such a page in future - give em the funnel name if so
		add_filter( 'at_is_funnel', '__return_true' );

		// main bit: if there is a phase, hand control to it
		$this->current_phase = $next;
		add_action( $this->current_phase->hook, $this->current_phase->callback );
	}

	/**
	 * Get the next phase that the user hasn't completed
	 *
	 * @return void
	 * @access private
	 */
	protected function get_next_phase() {

		foreach( $this->phases as $phase ) {
			if ( ! $phase->do_test( $this->user, $this->opts['meta_key'] ) )
				return $phase;
		}

		return false;
	}


}

/**
 * @package archetype.funnel
 */
class Archetype_Signup_Phase {

	var $slug;
	var $title;
	var $short_title;
	var $callback;
	var $hook;
	var $test;

	function __construct( $options ) {

		$options = wp_parse_args( $options, array( 
			'title' => null,
			'short_title' => null,
			'slug' => null,
			'hook' => 'template_redirect',
			'callback' => array( $this, 'redirect_to_self' ),
			'test' => array( $this, 'test_seen_once' ) 
		) );

		if( !$options['short_title'] )
			$options['short_title'] = $options['title'];

		if( !$options['slug'] )
			throw new Exception( 'Missing slug in signup phase' );

		foreach( $options as $name => $opt ) {
			$this->{$name} = $opt;
		}
	}

	function do_test( $user, $meta_key ) {
		return call_user_func_array( $this->test, array( $user, $meta_key ) );
	}

	function test_seen_once( $user, $meta_key ) {
		
		$seen = $user->get_meta( $meta_key, true );

		if( !is_array( $seen ) ) {
			$seen = array();
		}

		if( in_array( $this->slug, $seen ) ) {
			return true;
		}

		array_push( $seen, $this->slug );
		$user->update_meta( $meta_key, $seen );

		return false;
	}

	function redirect_to_self() {
		wp_redirect( get_permalink( get_page_by_path( $this->slug ) ) );
		die();
	}

}

/**
 * Are we on a funnel page?
 * @return bool
 */
function at_is_funnel() {
	return apply_filters( 'at_is_funnel', false );
}