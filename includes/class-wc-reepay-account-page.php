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
	public function __construct()
    {
        add_action('init', [$this, 'init']);
        add_action('template_redirect', [$this, 'check_action']);
        add_action('woocommerce_account_subscriptions_endpoint', [$this, 'subscriptions_endpoint']);
        add_filter('woocommerce_account_menu_items', [$this, 'add_subscriptions_menu_item'] );
        add_filter('woocommerce_get_query_vars', [$this, 'subscriptions_query_vars'], 0);
        return add_filter( 'woocommerce_endpoint_subscriptions_title', [$this, 'get_title'] );
    }

    public function init() {
        $this->rewrite_endpoint();
    }

    public function rewrite_endpoint() {
        add_rewrite_endpoint('subscriptions', EP_ROOT | EP_PAGES);
    }

    public function check_action() {

        if (!empty($_GET['cancel_subscription'])) {

            $handle = $_GET['cancel_subscription'];

            try {
                $result = reepay_s()->api()->request("subscription/{$handle}/cancel", 'POST');
            } catch (Exception $exception) {
                wc_add_notice($exception->getMessage(), 'error');
            }
            wp_redirect(wc_get_endpoint_url('subscriptions'));
        }


        if (!empty($_GET['uncancel_subscription'])) {

            $handle = $_GET['uncancel_subscription'];

            $result = reepay_s()->api()->request("subscription/{$handle}/uncancel", 'POST');
            wp_redirect(wc_get_endpoint_url('subscriptions'));
        }

        if (!empty($_GET['put_on_hold'])) {
            $handle = $_GET['put_on_hold'];
            $plan_handle = $_GET['plan'];
            $plan = WC_Reepay_Subscription_Plans::wc_get_plan($handle);
            if (!empty($plan)) {
                $compensation_method = get_post_meta($plan->ID, '_reepay_subscription_compensation', true);

                $params = [
                    "compensation_method" => $compensation_method,
                ];

                try {
                    $result = reepay_s()->api()->request("subscription/{$handle}/on_hold", 'POST', $params);
                } catch (Exception $e) {
                    wc_add_notice( $e->getMessage() );
                }
            } else {
                wc_add_notice('Plan not found', 'error');
            }
            wp_redirect(wc_get_endpoint_url('subscriptions'));
            exit;
        }

        if (!empty($_GET['reactivate'])) {
            $handle = $_GET['reactivate'];

            $result = reepay_s()->api()->request("subscription/{$handle}/reactivate", 'POST');
            wp_redirect(wc_get_endpoint_url('subscriptions'));
        }

        if (!empty($_GET['change_payment_method'])) {
            $handle = $_GET['change_payment_method'];
            $token_id = $_GET['token_id'];
            $token = WC_Payment_Tokens::get($token_id);

            $params = [
                'source' => $token->get_token(),
            ];

            $result = reepay_s()->api()->request("subscription/{$handle}/pm", 'POST', $params);
            wp_redirect(wc_get_endpoint_url('subscriptions'));
        }

        if (!empty($_GET['change_payment_method'])) {

        }
    }

    public function subscriptions_query_vars($endpoints) {
        $endpoints['subscriptions'] = 'subscriptions';
        return $endpoints;
    }

    public function get_title() {
	    return __("Subscriptions", reepay_s()->settings('domain'));
    }

	public function subscriptions_endpoint() {
        $subsResult = reepay_s()->api()->request("subscription", 'GET');
        $planResult = reepay_s()->api()->request("plan", 'GET');
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
