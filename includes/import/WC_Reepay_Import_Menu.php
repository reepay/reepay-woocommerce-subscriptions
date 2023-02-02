<?php

class WC_Reepay_Import_Menu {
	/**
	 * @var string
	 */
	public $option_name;

	/**
	 * @var array
	 */
	public $import_objects;

	/**
	 * @var string
	 */
	public $menu_slug = 'reepay_import';

	/**
	 * WC_Reepay_Import_Menu constructor.
	 *
	 * @param string $option_name
	 * @param array $import_objects
	 */
	public function __construct( $option_name, $import_objects ) {
		$this->option_name    = $option_name;
		$this->import_objects = $import_objects;

		add_action( 'admin_menu', [ $this, 'create_submenu' ] );
		add_action( 'admin_init', [ $this, 'create_settings_fields' ] );
	}

	function create_submenu() {
		add_submenu_page(
			'tools.php',
			'Reepay Import',
			'Reepay Import',
			'manage_options',
			$this->menu_slug,
			[ $this, 'print_import_page' ],
			0
		);
	}

	function create_settings_fields() {
		register_setting( 'reepay_import_settings', $this->option_name, [ $this, 'import_sanitize_checkbox' ] );

		foreach ( $this->import_objects as $object => ['input_type' => $options_input_type, 'options' => $options] ) {
			add_settings_section(
				"import_section_$object",
				'',
				'',
				$this->menu_slug
			);

			add_settings_field(
				"import_$object",
				"Import $object",
				[ $this, 'print_checkbox' ],
				$this->menu_slug,
				"import_section_$object",
				[
					'option_name' => [ $object ],
					'class' => 'reepay-import__row reepay-import__row--main'
				]
			);

			foreach ( $options as $option => $option_label ) {
				add_settings_field(
					"import_{$object}_{$option}",
					$option_label,
					[ $this, 'print_checkbox' ],
					$this->menu_slug,
					"import_section_$object",
					[
						'option_name' => [ $object, $option ],
						'class' => "reepay-import__row reepay-import__row--sub reepay-import__row--$option"
					]
				);
			}
		}
	}

	function import_sanitize_checkbox( $args ) {
		foreach ( $args as &$arg ) {
			if ( is_array( $arg ) ) {
				$arg = array_keys( $arg );

				if ( in_array( 'all', $arg ) ) {
					$arg = [ 'all' ];
				}
			} else {
				$arg = [ 'all' ];
			}
		}

		return $args;
	}

	function print_import_page() {
		wc_get_template(
			'import/page.php',
			array(),
			'',
			reepay_s()->settings( 'plugin_path' ) . 'templates/'
		);
	}

	function print_checkbox( $args ) {
		$args['option_name'] = '[' . implode( '][', $args['option_name'] ) . ']';

		wc_get_template(
			'import/checkbox.php',
			array(
				'args' => $args,
				'value' => get_option( 'reepay_import' )[ $args['option_name'] ] ?? ''
			),
			'',
			reepay_s()->settings( 'plugin_path' ) . 'templates/'
		);
	}

}
