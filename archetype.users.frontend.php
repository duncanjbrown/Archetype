<?php
/**
 * @package archetype
 * @subpackage users
 */


class Archetype_Form {

	public $fields;
	public $errors = array();
	private $processor;

	public static $forms = array();

	/**
	 * Add a new form
	 * @param string $form_name the name of the form
	 * @param array $fields    array of Archetype_Form_Fields
	 */
	public static function add( $form_name, $fields ) {
		$form = new self( $form_name, $fields );
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
	 */
	function __construct( $form_name, $fields ) {
		foreach( $fields as $field ) {
			$this->fields[$field->slug] = $field;
		}
		$process_class = "Archetype_" . ucfirst( $form_name ) . "_Form_Processor";
		$this->processor = new $process_class;
	}

	/**
	 * Process the form input
	 * @return mixed
	 */
	function process() {
		$this->processor->process( $this->fields );
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

		if( !empty( $this->errors ) )
			return new WP_Error( 'at_form_errors', 'Errors found', $this->errors );

		return true;
	}
}

abstract class Archetype_Form_Processor {

	protected $errors = array();

	function add_error( $error ) {
		$this->errors[] = $error;
	}

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
 * @return void
 */
function at_register_form( $form_name, $fields ) {
	
	$all_fields = Archetype_Form_Field::get_fields();

	$the_fields = array_map( function( $f ) use ( $fields ) {
		if( array_search( $f->slug, $fields ) !== false )
			return $f;
	}, $all_fields );

	if( in_array( null, $the_fields ) ) 
		wp_die( 'Missing field registration' );

	Archetype_Form::add( $form_name, $the_fields );
}
