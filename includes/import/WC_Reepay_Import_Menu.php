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

	/**
	 * Register submenu in tools menu
	 */
	function create_submenu() {
		add_submenu_page(
			'tools.php',
			__( 'Reepay Import' ),
			__( 'Reepay Import' ),
			'manage_options',
			self::$menu_slug,
			[ $this, 'print_import_page' ],
			0
		);
	}

	/**
	 * Register settings
	 */
	function create_settings_fields() {
		register_setting( 'reepay_import_settings', WC_Reepay_Import::$option_name, [ $this, 'import_sanitize_checkbox' ] );

		foreach ( WC_Reepay_Import::$import_objects as $object_key => $object ) {
			add_settings_section(
				"import_section_$object_key",
				'',
				'',
				self::$menu_slug
			);

			add_settings_field(
				"import_$object_key",
				sprintf( __( 'Import %s' ), $object['label'] ),
				[ $this, 'print_checkbox' ],
				self::$menu_slug,
				"import_section_$object_key",
				[
					'option_name' => [ $object_key ],
					'class'       => 'reepay-import__row reepay-import__row--main',
				]
			);

			foreach ( $object['options'] as $option => $option_label ) {
				add_settings_field(
					"import_{$object_key}_{$option}",
					$option_label,
					[ $this, 'print_checkbox' ],
					self::$menu_slug,
					"import_section_$object_key",
					[
						'option_name' => [ $object_key, $option ],
						'class'       => "reepay-import__row reepay-import__row--sub reepay-import__row--$option",
					]
				);
			}
		}
	}

	/**
	 * Print settings page with tables
	 */
	function print_import_page() {
		wc_get_template(
			'import/page.php',
			array(),
			'',
			reepay_s()->settings( 'plugin_path' ) . 'templates/'
		);

		foreach ( array_keys( WC_Reepay_Import::$import_objects ) as $object_key ) {
			wc_get_template(
				"import/tables/$object_key.php",
				array(),
				'',
				reepay_s()->settings( 'plugin_path' ) . 'templates/'
			);
		}
	}

	/**
	 * Prepare name attribute and print setting checkbox
	 *
	 * @param array $args
	 */
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

	/**
	 * Check if current page is reepay import page
	 *
	 * @return bool
	 */
	public static function is_current_page() {
		return isset( $_GET['page'] ) && self::$menu_slug === $_GET['page'];
	}

}
