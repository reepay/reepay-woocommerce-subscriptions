<?php

class WC_Reepay_Import_Menu {
	/**
	 * @var string
	 */
	public $option_name = 'reepay_import';

	/**
	 * @var string
	 */
	public $menu_slug = 'reepay_import';

	/**
	 * @var array<string>
	 */
	public $import_objects = [ 'users', 'cards', 'subscriptions' ];

	/**
	 * WC_Reepay_Import_Menu constructor.
	 *
	 * @param string $option_name
	 * @param string $menu_slug
	 * @param array<string> $import_objects
	 */
	public function __construct( $option_name, $menu_slug, $import_objects ) {
		$this->option_name    = $option_name;
		$this->menu_slug      = $menu_slug;
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
			[ $this, 'import_page_callback' ],
			0
		);
	}

	function create_settings_fields() {
		add_settings_section(
			'import_section',
			'',
			'',
			$this->menu_slug
		);

		register_setting( 'reepay_import_settings', $this->option_name, [ $this, 'import_sanitize_checkbox' ] );

		foreach ( $this->import_objects as $object ) {
			add_settings_field(
				"import_$object",
				"Import $object",
				[ $this, 'print_checkbox' ],
				$this->menu_slug,
				'import_section',
				[
					'option_name' => $object
				]
			);
		}
	}

	function print_checkbox( $args ) {
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

	function import_sanitize_checkbox( $args ) {
		foreach ( $args as &$arg ) {
			$arg = 'on' === $arg ? 'yes' : 'no';
		}

		return $args;
	}

	function import_page_callback() {
		wc_get_template(
			'import/page.php',
			array(),
			'',
			reepay_s()->settings( 'plugin_path' ) . 'templates/'
		);
	}
}
