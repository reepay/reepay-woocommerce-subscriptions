<?php

class WC_Reepay_My_Account {
	public function __construct() {
		new WC_Reepay_My_Account_Add_Payment_Method_Page();
		new WC_Reepay_My_Account_Orders_Page();
		new WC_Reepay_My_Account_Payment_Method();
		new WC_Reepay_My_Account_Subscription_Page();
		new WC_Reepay_My_Account_Subscription_Actions();
		new WC_Reepay_My_Account_Subscriptions_Page();

		add_action( 'init', [ $this, 'rewrite_endpoint' ] );
		add_action( 'woocommerce_get_query_vars', [ $this, 'add_pages_to_woo_query_vars' ] );
	}

	public function rewrite_endpoint() {
		add_rewrite_endpoint( WC_Reepay_My_Account_Subscriptions_Page::$menu_item_slug, EP_ROOT | EP_PAGES );
		add_rewrite_endpoint( WC_Reepay_My_Account_Subscription_Page::$menu_item_slug, EP_ROOT | EP_PAGES );

		if ( get_transient( 'woocommerce_reepay_subscriptions_activated' ) ) {
			flush_rewrite_rules();
			delete_transient( 'woocommerce_reepay_subscriptions_activated' );
		}
	}

	public function add_pages_to_woo_query_vars( $query_vars ) {
		$query_vars[ WC_Reepay_My_Account_Subscriptions_Page::$menu_item_slug ] = WC_Reepay_My_Account_Subscriptions_Page::$menu_item_slug;
		$query_vars[ WC_Reepay_My_Account_Subscription_Page::$menu_item_slug ]  = WC_Reepay_My_Account_Subscription_Page::$menu_item_slug;

		return $query_vars;
	}
}