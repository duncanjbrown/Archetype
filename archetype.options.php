<?php
/**
 * @package archetype
 * @subpackage options
 */

define( 'AT_OPTIONS_VERSION', 0.1 );

/**
 * A class to encapsulate the creation of simple WP options pages
 * @package archetype.options
 */
class Archetype_Options_Page {

	/**
	 * @var slug the slug for the page and menu
	 */
	protected $slug;

	/**
	 * @var the page title
	 */
	protected $page_title;

	/**
	 * @var the menu item title
	 */
	protected $menu_title;

	/**
	 * @var the necessary capability
	 */
	protected $capability;

	/**
	 * @var the sections
	 */
	protected $sections;

	/**
	 * @var the hook suffix for hooking into it
	 */
	protected $suffix;

	function __construct( $args ) {

		$this->sections = array();

		$this->slug = $args['slug'];
		$this->page_title = $args['page_title'];
		$this->menu_title = $args['menu_title'];
		$this->capability = $args['capability'];
		$icon_url = $args['icon_url'];
		$position = $args['position'];

		$this->suffix = add_options_page( $this->page_title, $this->menu_title, $this->capability, $this->slug, array( $this, 'display' ) );

		add_action( 'admin_print_styles-' . $this->suffix, array( $this, 'enqueue_styles' ) );
	}

	/**
	 * Return the hook suffix for this page
	 * @return string
	 */
	public function get_hook_suffix() {
		return $this->suffix;
	}

	/**
	 * Enqueue the standard stylesheet
	 */
	public function enqueue_styles() {
		wp_enqueue_style( 'at_options_styles', plugins_url( 'css/archetype.options.css', __FILE__ ) );
	}

	/**
	 * Get the slug for the settings to register_setting with
	 * @return string
	 */
	protected function get_settings_slug() {
		return $this->slug . '-settings';
	}

	/**
	 * Render the settings page
	 */
	function display() {
		include( 'views/main_options.php' );
	}

	/**
	 * Add a section to the page
	 * @param AT_Options_Page_Section $section
	 */
	public function add_section( $section ) {
		array_push( $this->sections, $section );
		add_settings_section( $section->slug, $section->name, $section->display_callback, $this->slug );
		$this->register_section_fields( $section->get_fields(), $section->slug );
	}

	/**
	 * Register the fields for a section that's been added
	 * @param array $fields the AT_Settings_Fields 
	 * @param string $section_slug the section slug
	 */
	private function register_section_fields( $fields, $section_slug ) {
		foreach( $fields as $field ) {
			$field->set_page_slug( $this->get_settings_slug() );
			register_setting( $this->get_settings_slug(), $field->slug, array( $field, 'validate' ) );
			add_settings_field( $field->slug, $field->name, array( $field, 'render' ), $this->slug, $section_slug );
		}
	}

}

class Archetype_Options_Page_Section {

	/**
	 * Array to hold the fields of the section
	 */
	protected $fields = array();

	/**
  	 * if you're using php > 5.3 you can pass in an anonymous function for the display callback.
  	 * otherwise you'll need to use a function outside the class
  	 *
	 * @param name $string the name of this section
	 * @param callable $display_callback the callback to display stuff under the section heading
	 */
	function __construct( $name, $display_callback = '__return_false' ) {
		$this->name = $name;
		$this->display_callback = $display_callback;
		$this->slug = sanitize_title( $name );
	}

	/**
	 * Add a field to the section
	 * @param AT_Options_Page_Field $field
	 */
	function add_field( $field ) {
		array_push( $this->fields, $field );
	}

	/**
	 * Get the fields of this section in an array
	 */
	function get_fields() {
		return $this->fields;
	}

}

/**
 * Instatiate subclasses eg Options_Page_Text_Field
 */
abstract class Archetype_Options_Page_Field {

	/**
	 * @param $slug the field's identifier and option_key
	 */
	var $slug;

	/**
	 * @param $name the nice name for the field
	 */
	var $name;

	function __construct( $args ) {

		$defaults = array( 
			'name' => false,
			'slug' => false,
			'validation' => false
		);

		$args = wp_parse_args( $args, $defaults );

		$this->slug 		= $args['key'];
		$this->name 		= $args['name'];
		$this->validation 	= $args['validation'];
	}

	/**
	 * Set the settings page slug for this field.
	 * This is called by the Options_Page class
	 * @param string $slug the page slug to associate it with
	 */
	function set_page_slug( $slug ) {
		$this->page_slug = $slug;
	} 

	/**
	 * Display the field
	 */
	abstract function render( $args );
	
	/**
	 * Run the validation callback on the submitted value
	 * @param mixed $value
	 * @return $value
	 */
	function validate( $value ) {
		if( $this->validation )
			return call_user_func( $this->validation, $value );

		return $value;
	}
}

class Archetype_Options_Page_Text_Field extends Archetype_Options_Page_Field {

	function render( $args ) {
		$opt = get_option( $this->slug );
		include( 'views/admin_fields/text_field.php' );
	}

	function validate( $value ) {
		$value = sanitize_text_field( $value );
		return parent::validate( $value );
	}
}

class Archtype_Options_Page_Checkbox_Field extends Archetype_Options_Page_Field {

	function render( $args ) {
		$opt = get_option( $this->slug );
		include( 'views/admin_fields/checkbox_field.php' );
	}

}
class Archtype_Options_Page_Checkbox_Array extends Archetype_Options_Page_Field {

	function __construct( $args ) {
		parent::__construct( $args );
		$this->choices = $args['choices'];
	}

	function render( $args ) {
		$opt = get_option( $this->slug );
		include( 'views/admin_fields/checkbox_field_array.php' );
	}

}

/**
 * Some convenient validation callbacks.
 */

function at_options_validate_int( $int ) {
	return intval( $int );
}