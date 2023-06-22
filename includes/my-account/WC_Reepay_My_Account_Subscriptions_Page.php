<?php

class WC_Reepay_My_Account_Subscriptions_Page {

	public static $menu_item_slug = 'r-subscriptions';

	public function __construct() {
		add_action( 'init', [ $this, 'rewrite_endpoint' ] );
		add_filter( 'woocommerce_account_menu_items', [ $this, 'add_subscriptions_menu_item' ] );
		add_action( 'woocommerce_account_' . self::$menu_item_slug . '_endpoint', [ $this, 'subscriptions_endpoint' ] );
		add_filter( 'woocommerce_endpoint_' . self::$menu_item_slug . '_title', function () {
			return __( 'Subscriptions', 'reepay-subscriptions-for-woocommerce' );
		} );
	}

	public function rewrite_endpoint() {
		add_rewrite_endpoint( self::$menu_item_slug, EP_ROOT | EP_PAGES );

		if ( get_transient( 'woocommerce_reepay_subscriptions_activated' ) ) {
			flush_rewrite_rules();
			delete_transient( 'woocommerce_reepay_subscriptions_activated' );
		}
	}

	public function add_subscriptions_menu_item( $menu_items ) {
		if ( ! empty( $menu_items[ self::$menu_item_slug ] ) ) {
			return $menu_items;
		}

		$menu_items_updated = [];

		foreach ( $menu_items as $key => $menu_item ) {
			$menu_items_updated[ $key ] = $menu_item;

			if ( 'orders' === $key ) {
				$menu_items_updated[ self::$menu_item_slug ] = __( 'Subscriptions', 'reepay-subscriptions-for-woocommerce' );
			}
		}

		return $menu_items_updated;
	}

	public function subscriptions_endpoint() {
		$reepay_customer_handle = rp_get_customer_handle( get_current_user_id() );

		if ( empty( $reepay_customer_handle ) ) {
			reepay()->get_template( 'myaccount/my-subscriptions-error.php', array(
				'error' => esc_html__( 'You have no active subscriptions.', 'reepay-subscriptions-for-woocommerce' )
			) );

			return;
		}

		$subscriptions = reepay_s()->api()->request( "list/subscription?customer=$reepay_customer_handle" );

		if ( empty( $subscriptions ) ) {
			reepay()->get_template( 'myaccount/my-subscriptions-error.php', array(
				'error' => esc_html__( 'You have no active subscriptions.', 'reepay-subscriptions-for-woocommerce' )
			) );

			return;
		}

		reepay()->get_template( 'myaccount/my-subscriptions.php', array(
			'subscriptions' => $subscriptions
		) );
	}
}