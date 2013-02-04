<?php
/**
 * @package archetype
 * @subpackage forms
 */

/**
 * @package archetype.forms
 */
class Archetype_Form {

	public $fields;
	public $errors = array();
	private $processor;
	private $options;

	public static $forms = array();

	/**
	 * Add a new form
	 * @param string $form_name the name of the form
	 * @param array $fields    array of Archetype_Form_Fields
	 * @param array $options
	 */
	public static function add( $form_name, $fields, $options = false ) {
		$form = new self( $form_name, $fields, $options );
		self::$forms[$form_name] = $form;

	}

	/**
	 * Get the fields for a given form
	 * @param  string $form_name the name
	 * @return Archetype_Form            
	 */
	public static function get( $form_name ) {
		return self::$forms[$form_name];
	}

	/**
	 * Create a new form. The form will be processed by the process() callback in
	 * a class called Archetype_${form_name}_Form_Processor.
	 *
	 * Eg to create a form called 'Signup', hand this function a bunch of fields 
	 * and the word 'signup', and define a class called Archetype_Signup_Form_Processor
	 * (note the form_name is uppercased), with a process() method that does whatever
	 * you like with the validated data.
	 * 
	 * @param string $form_name 
	 * @param array $fields    array of Archetype_Form_Fields
	 * @param array $options
	 */
	function __construct( $form_name, $fields, $options = false ) {
		
		foreach( $fields as $field )
			$this->fields[$field->slug] = $field;

		$this->options = wp_parse_args( $options, array(
			'show_discrete_errors' => true,
			'hook_to_page' => false,
			'nonce' => AT_USER_NONCE ) );

		// convenient way to attach a hook to p_g_p on a given page
		if( $page = $this->options['hook_to_page'] && $nonce = $this->options['nonce'] ) {

			add_action( 'pre_get_posts', function() use ( $page, $form_name, $nonce ) {
				if( is_page( $page ) ) {
					at_form( $form_name, $nonce );
				}
			});

		}

		$process_class = "Archetype_" . ucwords( $form_name ) . "_Form_Processor";
		$this->processor = new $process_class;
	}

	/**
	 * Display the nonce field
	 * @return void
	 */
	function nonce_field() {
		wp_nonce_field( $this->options['nonce'] );
	}

	/**
	 * Process the form input
	 * @return mixed
	 */
	function process() {
		$succeeded = $this->processor->process( $this->fields );
		if( $succeeded ) {
			$this->success_callback( $succeeded );
		}
	}

	/**
	 * What to do if the form submission is all OK
	 * @param  mixed $arbitrary_data data to hand to the success function
	 * @return void            
	 */
	function success_callback( $arbitrary_data ) {
		$this->processor->succeed( $arbitrary_data );
	}

	/**
	 * Get a field for this form
	 * @param  string $name the field slug
	 * @return Archetype_User_Field       
	 */
	function get_field( $name ) {
		return $this->fields[$name];
	}

	/**
	 * Get the names of this form's fields
	 * @return array 
	 */
	function get_field_names() {
		return array_keys( $this->fields );
	}

	/**
	 * Perform valdiation on the form elements
	 *
	 * Called from the at_form() function, which you should hook before page output.
	 * 
	 * @param  string $nonce_action
	 * @return mixed true|WP_Error
	 */
	public function validate( $nonce_action ) {

		// only record generic errors once
		$generic_error = false;
		
		if( !wp_verify_nonce( $_POST['_wpnonce'], $nonce_action ) )
			$this->errors[] = new WP_Error( 'failed_nonce_check', 'Illegitimate form submission' );

		foreach( $this->fields as $field ) {

			if( $field->required() && ( !$field->is_valid() || is_wp_error( $field->is_valid() ) ) ) {

				if( is_wp_error( $field->is_valid() ) ) {
					
					$message = $field->is_valid()->get_error_message();
					$this->errors[] = new WP_Error( 'failed_field_validation', $message );

				} else {

					if( $generic_error )
						continue;

					$message = 'Please fill in the required information';
					$this->errors[] = new WP_Error( 'generic_validation_failure', $message );
					$generic_error = true;
				}
			}
		}

		if( !empty( $this->errors ) && $this->options['show_discrete_errors'] ) {
		
			return new WP_Error( 'at_form_errors', 'Errors found', $this->errors );

		} else if( !empty( $this->errors ) ) {
		
			$message = apply_filters( 'archetype_generic_form_error_message', 'Oops! There were errors' );
			return new WP_Error( 'at_form_errors', 'Errors found', array( new WP_Error( 'general_errors', $message ) ) );
		
		}

		return true;
	}
}

abstract class Archetype_Form_Processor {

	protected $errors = array();

	/**
	 * Add an error to the internal errors array
	 * @param WP_Error $error 
	 */
	function add_error( WP_Error $error ) {
		$this->errors[] = $error;
	}

	/**
	 * What happens when the form has been submitted successfully
	 * Will redirect to the same page by default.
	 * 
	 * @param  mixed $data 
	 * @return void       
	 */
	public function succeed( $data ) {
		wp_redirect( wp_get_referer() );
		die();
	}

	/**
	 * Process the valid, sanitized input
	 * @param  array $fields an array of Archetype_Form_Fields
	 * @return mixed         
	 */
	abstract function process( $fields );
}

/**
 * Template function - hook this before output on the page your form is being processed
 * @param  string $form_name the form's name
 * @param  string $nonce     the nonce to use when receiving data
 * @return mixed            
 */
function at_form( $form_name, $nonce ) {

	if( $_SERVER['REQUEST_METHOD'] != 'POST' )
		return;
	
	$form = Archetype_Form::get( $form_name );

	$valid = $form->validate( $nonce );

	if( is_wp_error( $valid ) )
		at_display_errors( $valid->get_error_data( 'at_form_errors' ) );

	$form->process();
}

/**
 * Register a form 
 * @param  string $form_name the form's name
 * @param  Archetype_Form_Fields[] $fields    array of form fields
 * @param array $options
 * @return void
 */
function at_register_form( $form_name, $fields, $options ) {
	
	$all_fields = Archetype_Form_Field::get_fields();

	$the_fields = array_map( function( $f ) use ( $fields ) {
		if( array_search( $f->slug, $fields ) !== false )
			return $f;
	}, $all_fields );

	Archetype_Form::add( $form_name, array_filter( $the_fields ), $options );

}
