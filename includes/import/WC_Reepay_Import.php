<?php

class WC_Reepay_Import {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'admin_menu', [ $this, 'import_submenu' ] );
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

	function import_page_callback() {
		?>
        <div class="wrap">
            <h1><?php echo get_admin_page_title() ?></h1>
            <form method="post" action="options.php">
				<?php
				settings_fields( 'rudr_slider_settings' ); // settings group name
				do_settings_sections( 'rudr_slider' ); // just a page slug
				submit_button(); // "Save Changes" button
				?>
            </form>
        </div>
		<?php
	}

}
