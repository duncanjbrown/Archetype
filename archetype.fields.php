<?php
/**
 * @package archetype
 * @subpackage forms
 */

define( 'AT_USER_NONCE', '_at_update_user_profile' );

/**
 * @package archetype.forms
 */
class Archetype_Form {

	public $fields;
	public $errors = array();
	private $processor;
	private $options;
	private $name;

	public static $forms = array();

	/**
	 * Add a new form
	 * @param string $form_name the name of the form
	 * @param array $form_fields    Archetype_Form_Field[]
	 * @param array $options
	 */
	public static function add( $form_name, $form_fields, $options = false ) {
		$form = new self( $form_name, $form_fields, $options );
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
	 * Eg to create a form called 'Signup', hand this constructor a bunch of fields 
	 * and the word 'signup', and define a class called Archetype_Signup_Form_Processor
	 * (note the form_name is uppercased), with a process() method that does whatever
	 * you like with the validated data.
	 * 
	 * @param string $form_name 
	 * @param array $fields    Archetype_Form_Field[]
	 * @param array $options
	 */
	function __construct( $form_name, $form_fields, $options = false ) {

		foreach( $form_fields as $field ) {
			$field->form = $this;
			$this->fields[$field->name] = $field;
		}		

		$this->name = $form_name;

		$this->options = wp_parse_args( $options, array(
			'show_discrete_errors' => true,
			'hook_to_page' => false,
			'nonce' => AT_USER_NONCE ) );

		// convenient way to attach a hook to p_g_p on a given page
		if( $this->options['hook_to_page'] && $this->options['nonce'] ) {

			$page = $this->options['hook_to_page'];
			$nonce = $this->options['nonce'];
			
			add_action( 'pre_get_posts', function( $query ) use ( $page, $form_name, $nonce ) {
				
				if( is_page() && $query->query_vars['pagename'] == $page ) {
					at_form( $form_name, $nonce );
				}
			});

		}

		// @todo lazy-load this
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
	 * Get a specified field for this form
	 * @param  string $name the field name
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
	 * Show all the fields
	 * @return [type] [description]
	 */
	function show_fields() {
		foreach( $this->fields as $field ) {
			$field->show_field();
		}
	}

	/**
	 * Retrieve the nonce to check for posted form
	 * @todo use a hidden param to specify which form is being posted
	 * @return string 
	 */
	function get_nonce() {
		return $this->options['nonce'];
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
		
			$message = apply_filters( 'archetype_generic_form_error_message', 'Please correct the errors below' );
			return new WP_Error( 'at_form_errors', 'Errors found', array( new WP_Error( 'general_errors', $message ) ) );
		
		}

		return true;
	}

	public function messages() {
		$errors = apply_filters( 'at_form_errors', null );
		if( !$errors )
			return;

		foreach( $errors as $error ) { ?>
			<div class="tn_message error"><?php echo $error->get_error_message(); ?></div>	
		<?php }
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
 * Add fields to the user profile
 *
 * @package archetype.forms
 */
class Archetype_Form_Field {

	/**
	 * @var $name the name of this field (used in the label)
	 */
	public $name;

	/**
	 * The title to show in the label
	 */
	public $title;

	/**
	 * Other options - validation etc
	 * @var array
	 */
	public $opts;

	/**
	 * The template path to include to display the field
	 */
	public $template;

	/**
	 * @var $meta_key the usermeta key this field will work on
	 */
	public $meta_key;

	/**
	 * Is the field valid as posted
	 * @var bool
	 */
	public $valid;

	/**
	 * @var $desc the description to accompany the field
	 */
	public $desc;

	/**
	 * the fields registered
	 * @var array
	 */
	public static $fields = array();

	/**
	 * Instantiate a field from a set of array params
	 * @param  array $field 
	 * @return Archetype_User_Field        
	 */
	public static function build( $field ) {

		if( isset( $field['opts']['admin'] ) && $field['opts']['admin'] == true && is_admin() ) 
			$class = 'Archetype_Admin_Form_Field';
		else 
			$class = __CLASS__;

		$field = new $class ( 
			$field['name'],
			$field['title'],
			$field['type'],
			isset( $field['description'] ) ?  $field['description'] : '',
			isset( $field['meta_key'] ) ?  $field['meta_key'] : '',
			isset( $field['opts'] ) ?  $field['opts'] : ''
		);

		self::$fields[] = $field;

		return $field;
	}

	/**
	 * Get the registered fields
	 * @return array 
	 */
	public static function get_fields() {
		return self::$fields;
	}

	/**
	 * Make a new field
	 * @param string $name        the name of the field
	 * @param string $title       how to label the field
	 * @param string $type        eg text, checkbox
	 * @param string $description to decorate it in the admin
	 * @param string $meta_key    the meta_key to update (if required)
	 * @param array  $opts        
	 */
	function __construct( $name, $title, $type, $description, $meta_key, $opts = array() ) {

		$this->name = $name;
		$this->type = $type;
		$this->title = $title;
		$this->meta_key = $meta_key;
		$this->desc = $description;

		$this->update_options( (array) $opts );

		if( method_exists( $this, 'init' ) ) {
			$this->init();
		}
	}

	/**
	 * Update the fields options with a hash
	 *
	 * Will apply defaults if no options are already set
	 *
	 * @param  array $opts options to update
	 * @return void       
	 */
	public function update_options( $opts = array() ) {

		// options already set - just update
		if( $opts && isset( $this->opts ) ) {
			
			$this->opts = array_merge( $this->opts, $opts );
		
		} else {

			$defaults = array( 
				'admin' 				=> true,
				'validation' 			=> '__return_true',
				'signup_only' 			=> false,
				'choices'				=> '__return_false',
				'hidden' 				=> false,
				'placeholder'			=> false,
				'required'				=> false,
				'value' 				=> false,
				'disabled'				=> false,
				'readonly'				=> false
			);

			$opts = wp_parse_args( $opts, $defaults );

			$this->opts = $opts;
		}
	}

	/**
	 * Get the sanitized, posted value for this field if there is one
	 * @return mixed field data, or false
	 */
	public function get_posted_value() {
		$val = at_get_post_value( $this->name );

		if( is_string( $val ) )
			return sanitize_text_field( $val );
		if( is_array( $val ) )
			return array_map( 'sanitize_text_field', $val );

		return false;
	}

	/**
	 * Get the alternatives for this field, eg if it's a select.
	 * @return array
	 */
	public function get_choices() {
		return call_user_func( $this->opts['choices'] );
	}

	/**
	 * Get the posted value of this field if there is one, or put the existing value if not
	 * @param  mixed $default 
	 * @return mixed          
	 */
	public function get_value( $default = null ) {
		if( $value = $this->get_posted_value() ) {
			return $value;
		}

		if( $this->opts['value'] ) {
			return $this->opts['value'];
		}

		return $default;
	}

	/**
	 * Is this field marked as required?
	 * @return bool 
	 */
	public function required() {
		return $this->opts['required'];
	}

	/**
	 * Is this field marked as disabled
	 * @return bool 
	 */
	public function disabled() {
		return $this->opts['disabled'];
	}

	/**
	 * Is the field marked read-only
	 * @return bool 
	 */
	public function readonly() {
		return $this->opts['readonly'];
	}

	/**
	 * Set the value of the field
	 * @param mixed $value 
	 */
	public function set_value( $value ) {
		$this->opts['value'] = $value;
	}

	/**
	 * Get the template path for this field
	 * @return string 
	 */
	protected function get_template( $admin = false) {

		if( $admin )
			return 'views/fields/admin/' . $this->type . '.php';
		else 
			return 'views/fields/frontend/' . $this->type . '.php';

	}

	/**
	 * Is this valid input for this field?
	 * First checks it's been provided if required, then validates input
	 * 
	 * @return boolean
	 */
	function is_valid() {

		error_log( print_r( $this->opts, true ) );

		if( $this->required() && !( $this->get_posted_value() ) )
			$this->valid = false;
		else 
			$this->valid = call_user_func( $this->opts['validation'], $this->get_posted_value(), $this->form );
	
		return $this->valid;
	}

	/**
	 * Show the field
	 *
	 * @return void
	 */
	public function show_field( $user = false ) {
		$admin = is_admin() && !defined( 'DOING_AJAX' ) ? true : false;
		include $this->get_template( $admin );
	}

	/**
	 * Get classes to add to this field depending on what's been input
	 * @return string  space-separated class names
	 */
	protected function get_classes() {

		$classes = array();

		if ( $this->valid === false || is_wp_error( $this->valid ) ) { 
			$classes = array_merge( $classes, apply_filters( 'archetype_invalid_field_class', array( 'invalid' ) ) );
		}

		return implode( ' ', $classes);
	}

	/**
	 * Get error data to attach to this field
	 * @return string  an error string
	 */
	protected function get_error() {

		if( $this->valid === false && $this->required() ) {
			return 'You need to fill this box in';
		} else if( is_wp_error( $this->valid ) ) {
			return $this->valid->get_error_message();
		}

		return false;
	}

}

class Archetype_Admin_Form_Field extends Archetype_Form_Field {

	/**
	 * @var Archetype_Save_Field_Strategy
	 */
	private $save_strategy;

	protected function init() {
		$this->attach_hooks();
		$type = ucfirst( $this->type );
		$strategy_class = "Archetype_" . $type. "_Field_Save_Strategy";
		$this->save_strategy = new $strategy_class;
	}

	/**
	 * Update the usermeta on the profile page save action
	 * Won't save if the validation doesn't return true
	 *
	 * @param int     $user_id the ID of the user to update (supplied by WP)
	 */
	public function save( $user_id ) {
		$this->save_strategy->save( $user_id, $this );
	}

	/**
	 * Attach the necessary admin hooks to update this field
	 * @return void 
	 */
	public function attach_hooks( ) {
		add_action( 'show_user_profile', array( $this, 'show_field' ) );
		add_action( 'edit_user_profile', array( $this, 'show_field' ) );
		add_action( 'personal_options_update', array( $this, 'save' ) );
		add_action( 'edit_user_profile_update', array( $this, 'save' ) );
	}

}

interface Archetype_Save_Field_Strategy {
	public function save( $user_id, $field );
}

class Archetype_Text_Field_Save_Strategy implements Archetype_Save_Field_Strategy {

	public function save( $user_id, $field ) {

		if ( !current_user_can( 'edit_user', $user_id ) )
			return false;

		$user = User::get( $user_id );

		$value = sanitize_text_field( $field->get_posted_value() );

		$valid = call_user_func( $field->opts['validation'],  $value );

		if( !$valid )
			return false;

		$user->update_meta( $field->meta_key, $value );
	}
}

class Archetype_Checkbox_Field_Save_Strategy implements Archetype_Save_Field_Strategy {

	public function save( $user_id, $field ) {

		if ( !current_user_can( 'edit_user', $user_id ) )
			return false;

		$user = User::get( $user_id );

		if ( !isset( $_POST[$field->meta_key] ) ) {
			$user->update_meta( $field->meta_key, '0' );
			return;
		}

		$value = sanitize_text_field( $field->get_posted_value() );
		$user->update_meta( $field->meta_key, $value );
	}

}

/**
 * Register a form 
 *
 * pulls all the registered fields from archetype_form_field
 * and checks them for presence in the passed array of field names
 * if a field matches, it makes it into the form. Any options
 * passed when making the form field are added at this point
 * 
 * @param  string $form_name the form's name
 * @param  Archetype_Form_Fields[] $fields    array of form field names
 * @param array $options
 * @return void
 */
function at_register_form( $form_name, $fields, $options = array() ) {
	
	$all_fields = Archetype_Form_Field::get_fields();

	$form_fields = array();

	foreach( $all_fields as $f ) {
		if( isset( $fields[$f->name] ) ) {
			$new_field = clone $f; // override options on a per-form basis
			if( $fields[$f->name] ) { // there are options to override
				$new_field->update_options( $fields[$f->name] ); // apply any options passed with the form
			}
			$form_fields[] = $new_field;
		}
	}

	Archetype_Form::add( $form_name, $form_fields, $options );
}

/**
 * Add a submission field 
 * @param  string $field the field name
 */
function at_register_field( $field ) {
	// the first time it's constructed
	Archetype_Form_Field::build( $field );
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

	if( !$form )
		return false;

	// don't process the wrong form
	// atm we use nonces to distinguish, this could be much better
	if( !wp_verify_nonce( $_POST['_wpnonce'], $form->get_nonce() ) )
		return;
	

	$valid = $form->validate( $nonce );

	if( is_wp_error( $valid ) ) {
		$errors = $valid->get_error_data( 'at_form_errors' );
		at_display_errors( $errors ); //to show in page-level notices
		add_filter( 'at_form_errors', function() use ( $errors ) {
			return $errors;
		} ); // to show in form function call
	}

	$form->process();
}