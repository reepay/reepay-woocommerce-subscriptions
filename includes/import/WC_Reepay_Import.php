<?php

class WC_Reepay_Import {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'admin_menu', [ $this, 'import_submenu' ] );
		add_action( 'admin_init', [ $this, 'import_settings_fields' ] );
		add_action( 'admin_init', [ $this, 'process_import' ] );
	}

	public function process_import() {
		if ( isset( $_POST['import_tipple'] ) ) {
			try {
				if ( isset( $_POST['import_checkbox_users'] ) && $_POST['import_checkbox_users'] == 'on' ) {
					$this->process_import_users();
				}
			} catch ( Exception $e ) {
				WC_Reepay_Subscription_Admin_Notice::add_notice( $e->getMessage() );
			}

		}
	}

	public function process_import_users( $token = '' ) {
		$params = [
			'from' => '1970-01-01',
			'size' => 100,
		];

		if ( ! empty( $token ) ) {
			$params['next_page_token'] = $token;
		}

		try {
			$users_data = reepay_s()->api()->request( "list/customer?" . http_build_query( $params ) );
		} catch ( Exception $e ) {
			return new WP_Error( 400, $e->getMessage() );
		}


		if ( ! empty( $users_data ) && ! empty( $users_data['content'] ) ) {
			$users = $users_data['content'];

			foreach ( $users as $user ) {
				if ( $wp_user = get_user_by( 'email', $user['email'] ) ) {
					$wp_user_data = $wp_user->data;
					if ( ! empty( get_user_meta( $wp_user_data->ID, 'reepay_customer_id', true ) ) ) {
						// Что если handel не совпадает?
						continue;
					} else {
						update_user_meta( $wp_user_data->ID, 'reepay_customer_id', $user['handle'] );
					}
				} else {
					//Создать юзера
				}
			}

			if ( ! empty( $users_data['next_page_token'] ) ) {
				$this->process_import_users();
			}
		}
	}


	function import_submenu() {

		add_submenu_page(
			'tools.php', // parent page slug
			'Reepay Import',
			'Reepay Import',
			'manage_options',
			'reepay_import',
			[ $this, 'import_page_callback' ],
			0 // menu position
		);
	}

	function import_settings_fields() {
		// I created variables to make the things clearer
		$page_slug    = 'reepay_import';
		$option_group = 'reepay_import_settings';

		// 1. create section
		add_settings_section(
			'import_section', // section ID
			'', // title (optional)
			'', // callback function to display the section (optional)
			$page_slug
		);

		// 2. register fields
		register_setting( $option_group, 'slider_on', [ $this, 'import_sanitize_checkbox' ] );

		// 3. add fields
		add_settings_field(
			'import_users',
			'Import users',
			[ $this, 'import_checkbox_users' ], // function to print the field
			$page_slug,
			'import_section' // section ID
		);
		add_settings_field(
			'import_cards',
			'Import cards',
			[ $this, 'import_checkbox_cards' ], // function to print the field
			$page_slug,
			'import_section' // section ID
		);
		add_settings_field(
			'import_subscriptions',
			'Import subscriptions',
			[ $this, 'import_checkbox_subscriptions' ], // function to print the field
			$page_slug,
			'import_section' // section ID
		);

	}

	// custom callback function to print checkbox field HTML
	function import_checkbox_users( $args ) {
		$value = get_option( 'import_checkbox_users' );
		?>
        <label>
            <input type="checkbox" name="import_checkbox_users" <?php checked( $value, 'yes' ) ?> />
        </label>
		<?php
	}

	// custom callback function to print checkbox field HTML
	function import_checkbox_cards( $args ) {
		$value = get_option( 'import_checkbox_cards' );
		?>
        <label>
            <input type="checkbox" name="import_checkbox_cards" <?php checked( $value, 'yes' ) ?> />
        </label>
		<?php
	}

	// custom callback function to print checkbox field HTML
	function import_checkbox_subscriptions( $args ) {
		$value = get_option( 'import_checkbox_subscriptions' );
		?>
        <label>
            <input type="checkbox" name="import_checkbox_subscriptions" <?php checked( $value, 'yes' ) ?> />
        </label>
		<?php
	}

// custom sanitization function for a checkbox field
	function import_sanitize_checkbox( $value ) {
		return 'on' === $value ? 'yes' : 'no';
	}

	function import_page_callback() {
		?>
        <div class="wrap">
            <h1><?php echo get_admin_page_title() ?></h1>
            <form method="post" action="options.php">
				<?php
				settings_fields( 'reepay_import_settings' ); // settings group name
				do_settings_sections( 'reepay_import' ); // just a page slug
				?>

                <input type="submit" name="import_tipple" id="submit" class="button button-primary" value="Import">
            </form>
        </div>
		<?php
	}

}
