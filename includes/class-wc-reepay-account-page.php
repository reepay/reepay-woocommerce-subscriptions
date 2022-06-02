<?php

/**
 * Class WC_Reepay_Checkout
 *
 * @since 1.0.0
 */
class WC_Reepay_Account_Page {

	/**
	 * Constructor
	 */
	public function __construct() {

        add_action('init', [$this, 'rewrite_endpoint']);
        add_action('woocommerce_account_subscriptions_endpoint', [$this, 'subscriptions_endpoint']);
        add_filter('woocommerce_account_menu_items', [$this, 'add_subscriptions_menu_item'] );
        add_filter('woocommerce_get_query_vars', [$this, 'subscriptions_query_vars'], 0);
        return add_filter( 'woocommerce_endpoint_subscriptions_title', [$this, 'get_title'] );
    }

    public function subscriptions_query_vars($endpoints) {
        $endpoints['subscriptions'] = 'subscriptions';
        return $endpoints;
    }

    public function get_title() {
	    return __("Subscriptions", reepay_s()->settings('domain'));
    }

	public function rewrite_endpoint() {
        add_rewrite_endpoint('subscriptions', EP_ROOT | EP_PAGES);
    }

    public function get_subscriptions() {

    }

	public function subscriptions_endpoint() {
	    $subscriptions = [];
        wc_get_template(
            'subscriptions.php',
            array(
            ),
            '',
            reepay_s()->settings('plugin_path').'templates/'
        );
    }

	public function add_subscriptions_menu_item($menu_items) {
        $menu_items["subscriptions"] = $this->get_title();
        return $menu_items;
    }
}

new WC_Reepay_Account_Page();
