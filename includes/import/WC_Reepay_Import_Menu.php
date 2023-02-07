<?php

class WC_Reepay_Import_Menu {
	/**
	 * @var string
	 */
	public static $menu_slug = 'reepay_import';

	/**
	 * WC_Reepay_Import_Menu constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', [ $this, 'create_submenu' ] );
		add_action( 'admin_init', [ $this, 'create_settings_fields' ] );
	}

	function create_submenu() {
		add_submenu_page(
			'tools.php',
			'Reepay Import',
			'Reepay Import',
			'manage_options',
			self::$menu_slug,
			[ $this, 'print_import_page' ],
			0
		);
	}

	function create_settings_fields() {
		register_setting( 'reepay_import_settings', WC_Reepay_Import::$option_name, [ $this, 'import_sanitize_checkbox' ] );

		foreach ( WC_Reepay_Import::$import_objects as $object => ['options' => $options] ) {
			add_settings_section(
				"import_section_$object",
				'',
				'',
				self::$menu_slug
			);

			add_settings_field(
				"import_$object",
				"Import $object",
				[ $this, 'print_checkbox' ],
				self::$menu_slug,
				"import_section_$object",
				[
					'option_name' => [ $object ],
					'class'       => 'reepay-import__row reepay-import__row--main',
				]
			);

			foreach ( $options as $option => $option_label ) {
				add_settings_field(
					"import_{$object}_{$option}",
					$option_label,
					[ $this, 'print_checkbox' ],
					self::$menu_slug,
					"import_section_$object",
					[
						'option_name' => [ $object, $option ],
						'class'       => "reepay-import__row reepay-import__row--sub reepay-import__row--$option",
					]
				);
			}
		}
	}

	function print_import_page() {
		wc_get_template(
			'import/page.php',
			array(),
			'',
			reepay_s()->settings( 'plugin_path' ) . 'templates/'
		);

		foreach ( array_keys( WC_Reepay_Import::$import_objects ) as $object ) {
			wc_get_template(
				"import/tables/$object.php",
				array(),
				'',
				reepay_s()->settings( 'plugin_path' ) . 'templates/'
			);
		}
	}

	function print_checkbox( $args ) {
		$args['option_name'] = '[' . implode( '][', $args['option_name'] ) . ']';

		wc_get_template(
			'import/checkbox.php',
			array(
				'args'  => $args,
				'value' => get_option( 'reepay_import' )[ $args['option_name'] ] ?? '',
			),
			'',
			reepay_s()->settings( 'plugin_path' ) . 'templates/'
		);
	}

	public static function is_current_page() {
		return isset( $_GET['page'] ) && self::$menu_slug === $_GET['page'];
	}

}
