<?php
/**
 * @package archetype
 * @subpackage forms
 */

define( 'AT_USER_NONCE', '_at_update_user_profile' );

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
	 * @var $slug the id for this field
	 */
	public $slug;

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

		$class = 'Archetype_Form_Field';

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
		$this->slug = at_underscore_slug( $name );
		$this->type = $type;
		$this->title = $title;
		$this->meta_key = $meta_key;
		$this->desc = $description;

		$defaults = array( 
			'admin' 				=> true,
			'validation' 			=> '__return_true',
			'required'			 	=> false,
			'signup_only' 			=> false,
			'hidden' 				=> false
		);

		$opts = wp_parse_args( $opts, $defaults );
		$this->opts = $opts;

		if( method_exists( $this, 'init' ) ) {
			$this->init();
		}
	}

	/**
	 * Get the sanitized, posted value for this field if there is one
	 * @return mixed field data, or false
	 */
	public function get_posted_value() {
		if( isset( $_POST[$this->slug] ) ) 
			$val = $_POST[$this->slug];

		return sanitize_text_field( $val );

		return false;
	}

	/**
	 * Get the posted value of this field if there is one, or put the existing value if not
	 * @param  mixed $default 
	 * @return mixed          
	 */
	public function get_value( $default = null ) {
		if( $value = $this->get_posted_value() )
			return $value;

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

		if( $this->required() && !( $this->get_posted_value() ) )
			$this->valid = false;
		else 
			$this->valid = call_user_func( $this->opts['validation'], $this->get_posted_value() );
	
		return $this->valid;
	}

	/**
	 * Show the field
	 *
	 * @return void
	 */
	public function show_field( $user = false ) {
		$admin = is_admin() ? true : false;
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
 * Add a submission field 
 * @param  string $field the field name
 */
function at_register_field( $field ) {
	Archetype_Form_Field::build( $field );
}
