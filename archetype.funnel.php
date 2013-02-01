<?php


/**
 * Pass the user through a series of pages so they must visit them in order.
 *
 * This class is instatiated from init.
 * The first test that fails gets to execute a callback, typically a redirect,
 * on a hook of its choice, though it could be something more (or less) trivial
 *
 */
class Archetype_Funnel {

	/**
	 * This is the user to be put through the process
	 *
	 * @var User
	 */
	protected $user;

	protected $name;

	/**
	 * Register a user as having completed the signup process
	 * This should be called from a handler accepting the user's 
	 * completion of the process, eg a submit saying 'I'm done'.
	 * 
	 * Or you could complete them in a phase callback, below.
	 *
	 * @param User $user
	 * @param string $path where to send them on completion
	 */
	public static function complete( $user, $path = '/', $redirect = true ) {
		
		$user->register_signup_completed();

		wp_redirect( '/' );
		die();

		return;
	}

	/**
	 * Pass in a user who hasn't finished signup.
	 *
	 * This method takes the phases defined in $this->get_phases()
	 * and subjects the user to each of their test cases.
	 * In the event that a user fails a test, that test
	 * is allowed to execute its callback.
	 *
	 * @param User $user the user to test
	 */
	function __construct( User $user ) {

		$this->user = $user;

		// get the first phase which the user fails (eg the one they need to do next)
		$next = $this->get_next_phase();

		// if no phases are failed, do nothing. ?should call ::complete() here?
		if( !$next )
			return;

		// we may want to check if we're on such a page in future
		add_filter( 'at_is_funnel', '__return_true' );

		// main bit: if there is a phase, hand control to it
		$phases = $this->get_phases();
		$this->current_phase = $phases[$next];
		add_action( $this->current_phase['hook'], $this->current_phase['callback'] );

		// add some data to the ri_signup_process_page hook so the user knows where they are
		//add_action( 'at_funnel_process_page', array( &$this, 'display_progress' ) );
	}

	/**
	 * Show the user's progress through the signup process
	 *
	 * @return void
	 */
	public function display_progress() {
		$phases = $this->get_phases();
		hm_get_template_part( 'signup/progress_bar', array( 'phases' => $phases, 'current_phase' => $this->current_phase, 'phase_count' => count( $phases ) ) );
	}

	/**
	 * Get the next phase that the user hasn't completed
	 *
	 * @return void
	 * @access private
	 */
	protected function get_next_phase() {

		$tests = $this->get_tests();

		foreach ( $tests as $phase => $test ) {

			if ( call_user_func( $test, $this->user ) === false ) {
				return $phase; // if they fail the test
			}
		}

		return false;
	}

	/**
	 * Get the test function for each of the phases described in get_phases, below.
	 *
	 * @return array of functions
	 * @access private
	 */
	protected function get_tests() {

		$tests = array();

		foreach ( $this->get_phases() as $phase => $data ) {
			switch ( $data['test_type'] ) {

			case 'usermeta' :
				$tests[$phase] = $this->create_usermeta_test( $data );
				break;

			case 'once' :
				$name = $this->name;
				$tests[$phase] = function( $user ) use ( $phase, $name ) {
					if( !$progress = $user->get_meta( $name, true ) ) {
						$progress = array();
					}

					return in_array( $phase, $progress );
				};
				break;

			}
		}
		return $tests;
	}

	/**
	 * Pile up phases here in the order they should be completed by the user
	 *
	 *
	 * @access protected
	 * @return array
	 */
	protected function get_phases() {

		/* 
			EXAMPLE
		$phases = array(
			'tour' => array(
				'name' => 'Take a tour',
				'shortname' => 'Tour',
				'test_type' => 'usermeta',
				'meta_key' => RI_TOUR_META,
				'hook' => 'template_redirect',
				'callback' => function() use ( $template ) {

					global $wp_query;

					if ( !get_query_var( 'is_tour' ) ) {
						wp_redirect( site_url( $template->get_url_for( 'tour' ) ) );
						die();
					}
				}
			)

			return $phases;
		*/
	
	}

	/**
	 * Generate a test function for a usermeta value
	 *
	 * @return callable the function to test ( returns true or false )
	 */
	private function create_usermeta_test( $phase ) {

		return function( $user ) use( $phase ) {
			if ( $user->get_meta( $phase[ 'meta_key' ], true ) ) {
				return true;
			}
			return false;
		};
	}
}

/**
 * Are we on a signup process page?
 * @return bool
 */
function at_is_funnel() {
	return apply_filters( 'at_is_funnel', false );
}