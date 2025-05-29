<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_Reepay_Subscriptions_Update {

	/**
	 * @var string[]
	 */
	private static $db_updates = [
		'1.0.2' => 'update-1.0.2.php',
	];

	public function __construct( $db_version ) {
		// Show Upgrade notification
		if ( version_compare(
			get_option( 'woocommerce_reepay_subscriptions_version', $db_version ),
			$db_version,
			'<' )
		) {
			add_action( 'admin_notices', [ $this, 'upgrade_notice' ] );
		}

		add_action( 'admin_menu', [ $this, 'admin_menu' ], 99 );
	}

	/**
	 * Update DB version.
	 *
	 * @param  string  $version
	 */
	private static function update_db_version( $version ) {
		delete_option( 'woocommerce_reepay_subscriptions_version' );
		add_option( 'woocommerce_reepay_subscriptions_version', $version );
	}

	/**
	 * Upgrade Notice
	 */
	public static function upgrade_notice() {
		if ( current_user_can( 'update_plugins' ) ) {
			?>
            <div id="message" class="error">
                <p>
					<?php
					echo esc_html__( 'Warning! WooCommerce Frisbii Billing plugin requires to update the database structure.',
						'reepay-checkout-gateway' );
					echo ' ' . sprintf( esc_html__( 'Please click %s here %s to start upgrade.',
							'reepay-checkout-gateway' ),
							'<a href="' . esc_url( admin_url( 'admin.php?page=wc-reepay-subscriptions-upgrade' ) ) . '">',
							'</a>' );
					?>
                </p>
            </div>
			<?php
		}
	}

	/**
	 * Add Upgrade Page
	 */
	public static function admin_menu() {
		global $_registered_pages;

		$hookname = get_plugin_page_hookname( 'wc-reepay-subscriptions-upgrade', '' );
		if ( ! empty( $hookname ) ) {
			add_action( $hookname, __CLASS__ . '::upgrade_page' );
		}

		$_registered_pages[ $hookname ] = true;
	}

	/**
	 * Upgrade Page
	 */
	public static function upgrade_page() {
		if ( ! current_user_can( 'update_plugins' ) ) {
			return;
		}

		// Run Database Update
		self::update();

		echo esc_html__( 'Upgrade finished.', 'reepay-checkout-gateway' );
	}

	/**
	 * Handle updates
	 */
	public static function update() {
		$current_version = get_option( 'woocommerce_reepay_subscriptions_version' ) ?: '1.0.0';
		foreach ( self::$db_updates as $version => $updater ) {
			if ( version_compare( $current_version, $version, '<' ) ) {
				include dirname( __FILE__ ) . '/' . $updater;
				self::update_db_version( $version );
			}
		}
	}
}