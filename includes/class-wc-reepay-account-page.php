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



	    if (!empty($_GET['cancel_subscription'])) {

            $handle = $_GET['cancel_subscription'];

            $params = [
            ];

            try {
                $result = reepay_s()->api()->request("subscription/{$handle}/cancel", 'POST', $params);
            } catch (Exception $exception) {
                wc_add_notice($exception->getMessage(), 'error');
            }
        }


	    if (!empty($_GET['uncancel_subscription'])) {

            $handle = $_GET['uncancel_subscription'];

            $params = [
            ];

            $result = reepay_s()->api()->request("subscription/{$handle}/uncancel", 'POST', $params);
        }

	    if (!empty($_GET['put_on_hold'])) {
	        $handle = $_GET['put_on_hold'];
            $compensation_method = WooCommerce_Reepay_Subscriptions::settings('compensation_method');

	        $params = [
                "compensation_method" => $compensation_method,
            ];

            try {
                $result = reepay_s()->api()->request("subscription/{$handle}/on_hold", 'POST', $params);
            } catch (Exception $e) {
                WC_Reepay_Subscription_Admin_Notice::add_notice( $e->getMessage() );
            }
        }

	    if (!empty($_GET['reactivate'])) {
	        $handle = $_GET['reactivate'];

	        $params = [
            ];

            $result = reepay_s()->api()->request("subscription/{$handle}/reactivate", 'POST', $params);
        }

	    if (!empty($_GET['change_payment_method'])) {
	        $handle = $_GET['change_payment_method'];
	        $token_id = $_GET['token_id'];
            $token = WC_Payment_Tokens::get($token_id);

	        $params = [
	            'source' => $token->get_token(),
            ];

            $result = reepay_s()->api()->request("subscription/{$handle}/pm", 'POST', $params);
        }

	    if (!empty($_GET['change_payment_method'])) {

        }




	    $params = [];
        $subsResult = reepay_s()->api()->request("subscription", 'GET', $params);
        $planResult = reepay_s()->api()->request("plan", 'GET', $params);
        $plans = [];
        foreach ($planResult as $item) {
            $plans[$item['handle']] = $item;
        }
	    $subscriptions = $subsResult['content'];
        wc_get_template(
            'my-account/subscriptions.php',
            array(
                'subscriptions' => $subscriptions,
                'plans' => $plans,
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
