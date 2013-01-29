<?php
/**
 * @package archetype
 * @subpackage  archetype.users
 */

define( 'AT_USER_NONCE', '_at_update_user_profile' );

class Archetype_User_Profile {

	protected $fields = array();

	protected $context;

	protected static $_instance;

	public static function get_instance() {
		if( self::$_instance )
			return self::$_instance;

		return self::$_instance = new self;
	}

	protected function __construct() {}

	/**
	 * Add a field to this user profile
	 * @param  string $context the context
	 * @param  Archetype_User_Field $field   
	 * @return void          
	 */
	function register_field( $context, $field) {

		if( !is_array( $this->fields[$context] ) )
			$this->fields[$context] = array();

		$this->fields[$context][$field->name] = $field;
	}

	/**
	 * Return the fields for a given context, letting the field know which context it's in
	 * @param  string $context the context to get
	 * @return array          the fields
	 */
	function get_fields() {
		if( !$this->context )
			throw new Exception( __CLASS__ . ' needs a context to be set before retrieving fields' );
		
		$context = $this->context;
		
		return array_map( function( $field ) use ( $context ) {
			$field->set_context( $context );
			return $field;
		}, $this->fields[$context] );
	}

	/**
	 * Set the current context for this profile
	 * @param string $context 
	 */
	function set_context( $context ) {
		$this->context = $context;
	}

	/**
	 * Get a named field in the current context
	 * @param  string $name the field name it was registered with (its array key)
	 * @return Archetype_User_Field       
	 */
	function get_field( $name ) {
		if( !$this->context )
			throw new Exception( __CLASS__ . ' needs a context to be set before retrieving fields' );
		
		return $this->fields[$this->context][$name];
	}
}

/**
 * Add fields to the user profile this class
 *
 * @package Archetype_Users
 */
class Archetype_User_Field {

	/**
	 * @var $name the name of this field (used in the label)
	 */
	public $name;

	/**
	 * The title to show in the label
	 */
	public $title;

	/**
	 * The context we're seeing this field in
	 * @var string
	 */
	protected $context = 'admin';

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
	 * @var $desc the description to accompany the field
	 */
	public $desc;

	/**
	 * Instantiate a field from a set of array params
	 * @param  string $name
	 * @param  array $field 
	 * @return Archettype_User_Field        
	 */
	public static function build( $name, $field ) {

		switch( $field['type'] ) {
			case 'text' :
			default :
				return new Archetype_User_Field ( 
					$name,
					$field['title'],
					$field['type'],
					$field['description'],
					$field['meta_key'],
					$field['opts']
				);
		}
	}

	/**
	 * Add a new field to the user-admin page or the front end
	 * Hook this on admin_init.
	 *
	 * @param string  $name        the name of the profile field
	 * @param string  $type
	 * @param string  $description the field's description to display alongside it
	 * @param string  $meta_key    the usermeta key under which you'll store this data
	 */
	function __construct( $name, $title, $type, $description, $meta_key, $opts = array() ) {

		$this->name = $name;
		$this->type = $type;
		$this->title = $title;
		$this->slug = $meta_key;
		$this->meta_key = $meta_key;
		$this->desc = $description;

		$defaults = array( 
			'validation' 			=> '__return_true',
			'required_for_signup' 	=> false,
			'signup_only' 			=> false,
			'context' 				=> array( 'admin' ),
			'hidden' 				=> false
		);

		$opts = wp_parse_args( $opts, $defaults );
		$this->opts = $opts;

		foreach( $opts['context'] as $context ) {
			$this->register_field( $context );
			$this->attach_context_hooks( $context );
		}
	}

	/**
	 * Attach the context hooks according to which contexts we're interested in
	 * @param  string $context a context
	 * @return void          
	 */
	protected function attach_context_hooks( $context ) {
		$class = 'Archetype_' . ucfirst( $context ) . '_Profile_Hooks';
		call_user_func( $class . '::attach_hooks', $this );
	}

	/**
	 * Register the field as a context of the whole user profile
	 * @return void 
	 */
	protected function register_field( $context ) {
		$profile = Archetype_User_Profile::get_instance();
		$profile->register_field( $context, $this );
	}

	/**
	 * Get the posted value for this field if there is one
	 * @return mixed field data, or false
	 */
	public function get_posted_value() {
		if( isset( $_POST[$this->slug] ) ) 
			return $_POST[$this->slug];

		return false;
	}

	/**
	 * Set the context we're currently seeing the field in
	 * @param string $context 
	 */
	public function set_context( $context ) {
		$this->context = $context;
	}

	/**
	 * Get the template path for this field
	 * @return string 
	 */
	protected function get_template() {

		if( $this->context == 'admin' )
			return 'views/fields/admin/' . $this->type . '.php';
		else 
			return 'views/fields/frontend/' . $this->type . '.php';

	}

	/**
	 * Is this valid input for this field?
	 * @param mixed $input
	 * @return boolean [description]
	 */
	function is_valid( $input ) {
		return call_user_func( $this->opts['validation'], $input );
	}

	/**
	 * Update the usermeta on the profile page save action
	 * Won't save if the validation doesn't return true
	 *
	 * @param int     $user_id the ID of the user to update (supplied by WP)
	 */
	public function save( $user_id ) {

		if ( !current_user_can( 'edit_user', $user_id ) )
			return false;

		$user = User::get( $user_id );

		$value = sanitize_text_field( $_POST[$this->meta_key] );

		$valid = call_user_func( $this->opts['validation'],  $value );

		if( !$valid )
			return false;

		$user->update_meta( $this->meta_key, $value );
	}

	/**
	 * Show the field using an include
	 *
	 * @return void
	 */
	public function show_field( $user = false ) {
		include $this->get_template();
	}

}

interface User_Profile_Hook_Context {
	public static function attach_hooks( $object );
}

class Archetype_Frontend_Profile_Hooks implements User_Profile_Hook_Context {
	public static function attach_hooks( $object ) {
		add_action( 'at_show_frontend_fields', array( $object, 'show_field' ) );
		add_action( 'at_save_frontend_fields', array( $object, 'save' ) );
	}
}

class Archetype_Admin_Profile_Hooks implements User_Profile_Hook_Context {
	public static function attach_hooks( $object ) {
		add_action( 'show_user_profile', array( $object, 'show_field' ) );
		add_action( 'edit_user_profile', array( $object, 'show_field' ) );
		add_action( 'personal_options_update', array( $object, 'save' ) );
		add_action( 'edit_user_profile_update', array( $object, 'save' ) );
	}
}

class Archetype_Signup_Profile_Hooks implements User_Profile_Hook_Context {
	public static function attach_hooks( $object ) {}
}

/**
 * Implement checkboxes for the User Admin page
 *
 */
class Archetype_User_Admin_Checkbox_Field extends Archetype_User_Field {

	public function save( $user_id ) {

		if ( !current_user_can( 'edit_user', $user_id ) || !current_user_can( 'administrator' ) )
			return false;

		$user = User::get( $user_id );

		if ( !isset( $_POST[$this->meta_key] ) ) {
			$user->update_meta( $this->meta_key, '0' );
			return;
		}

		$value = sanitize_text_field( $_POST[$this->meta_key] );
		$user->update_meta( $this->meta_key, $value );
	}

}

add_action( 'init', function() {
	$fields = apply_filters( 'at_user_fields', array() );
	if( !empty( $fields ) )
		at_register_fields( $fields );
} );

function at_register_fields( $fields ) {
	foreach( $fields as $name => $data )
		Archetype_User_Field::build( $name, $data );
}

function at_get_user_fields_for( $context ) {
	$profile = Archetype_User_Profile::get_instance();
	$profile->set_context( $context );
	$fields = $profile->get_fields();
	return $fields;
}
