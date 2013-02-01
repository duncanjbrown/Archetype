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

	public static function add( $form_name, $fields ) {
		$form = new self( $form_name, $fields );
		self::$forms[$form_name] = $form;
	}

	public static function get( $form_name ) {
		return self::$forms[$form_name];
	}

	function __construct( $form_name, $fields ) {
		foreach( $fields as $field ) {
			$this->fields[$field->slug] = $field;
		}
		$process_class = "Archetype_" . ucfirst( $form_name ) . "_Form_Processor";
		$this->processor = new $process_class;
	}

	function process() {
		$this->processor->process( $this->fields );
		$this->processor->errors();
	}

	function get_field_names() {
		return array_keys( $this->fields );
	}

	/**
	 * Perform valdiation on the form elements
	 * @param  string $nonce_action
	 * @return mixed true|WP_Error
	 */
	public function validate( $nonce_action ) {
		
		if( !wp_verify_nonce( $_POST['_wpnonce'], $nonce_action ) )
			$this->errors[] = new WP_Error( 'failed_nonce_check', 'Illegitimate form submission' );

		foreach( $this->fields as $field ) {
			if( $field->required() && ( !$field->is_valid() || is_wp_error( $field->is_valid() ) ) ) {

				if( is_wp_error( $field->is_valid() ) ) {
					$message = $field->is_valid()->get_error_message();
				} else {
					$message = 'Invalid input';
				}
				$this->errors[] = new WP_Error( 'failed_field_validation', $message );
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

	function errors() {
		at_display_errors( $this->errors );
	}

	abstract function process( $fields );
}

/**
 * Template function - put this on the page where your form is being processed
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
