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

        add_filter('woocommerce_reepay_payment_accept_url', [$this, 'add_subscription_arg']);
        add_filter('woocommerce_reepay_payment_cancel_url', [$this, 'add_subscription_arg']);
        add_action('woocommerce_reepay_payment_method_added', [$this, 'payment_method_added']);
        return add_filter( 'woocommerce_endpoint_subscriptions_title', [$this, 'get_title'] );
    }

    public function add_subscription_arg($url) {
        if ($_GET['reepay_subscription']) {
            return add_query_arg('reepay_subscription', $_GET['reepay_subscription'], $url);
        }
        return $url;
    }

    public function payment_method_added(WC_Payment_Token $token) {
        $handle = $_GET['reepay_subscription'] ?? '';
        if (!empty($handle)) {
            try {
                reepay_s()->api()->request('subscription/' . $handle . '/pm', 'POST', [
                    'source' => $token->get_token(),
                ]);
            } catch (Exception $exception) {
                wc_add_notice($exception->getMessage());
            }
        }
        wc_add_notice( __( 'Payment method successfully added.', 'reepay-checkout-gateway' ) );
        wp_redirect( wc_get_account_endpoint_url( 'subscriptions' ) );
        exit;
    }

    public function init() {
        $this->rewrite_endpoint();
    }
    public function rewrite_endpoint() {
        add_rewrite_endpoint('subscriptions', EP_ROOT | EP_PAGES);
    }

    /**
     * Verify subscription belongs to customer
     * @param $handle
     * @return mixed|WC_Order|null
     */
    public function get_customer_subscription_by_handle($handle) {
        $order = wc_get_orders([
                'meta_key' => '_reepay_subscription_handle',
                'meta_value' => $handle,
            ])[0] ?? null;
        if ($order && $order->get_customer_id() === get_current_user_id()) {
            return $order;
        }
        return null;
    }

    public function check_action() {

        if (!empty($_GET['cancel_subscription'])) {

            $handle = $_GET['cancel_subscription'];
            $handle = urlencode($handle);

            $order = wc_get_orders([
                    'meta_key' => '_reepay_subscription_handle',
                    'meta_value' => $handle,
                ])[0] ?? null;

            if ($order && $order->get_customer_id() === get_current_user_id()) {
                try {
                    $result = reepay_s()->api()->request("subscription/{$handle}/cancel", 'POST');
                } catch (Exception $exception) {
                    wc_add_notice($exception->getMessage(), 'error');
                }
            } else {
                wc_add_notice('Permission denied', 'error');
            }

            wp_redirect(wc_get_endpoint_url('subscriptions'));
        }


        if (!empty($_GET['uncancel_subscription'])) {

            $handle = $_GET['uncancel_subscription'];
            $handle = urlencode($handle);

            $order = wc_get_orders([
                    'meta_key' => '_reepay_subscription_handle',
                    'meta_value' => $handle,
                ])[0] ?? null;


            if ($order && $order->get_customer_id() === get_current_user_id()) {
                try {
                    $result = reepay_s()->api()->request("subscription/{$handle}/uncancel", 'POST');
                } catch (Exception $exception) {
                    wc_add_notice($exception->getMessage(), 'error');
                }
            } else {
                wc_add_notice('Permission denied', 'error');
            }

            wp_redirect(wc_get_endpoint_url('subscriptions'));
        }

        if (!empty($_GET['put_on_hold'])) {
            $handle = $_GET['put_on_hold'];
            $plan_handle = $_GET['plan'];
            $handle = urlencode($handle);
            $handle = urlencode($handle);

            $order = wc_get_orders([
                    'meta_key' => '_reepay_subscription_handle',
                    'meta_value' => $handle,
                ])[0] ?? null;


            if ($order && $order->get_customer_id() === get_current_user_id()) {
                $plan = WC_Reepay_Subscription_Plan_Simple::wc_get_plan($handle);
                if (!empty($plan)) {
                    $compensation_method = get_post_meta($plan->ID, '_reepay_subscription_compensation', true);

                    $params = [
                        "compensation_method" => $compensation_method,
                    ];

                    try {
                        $result = reepay_s()->api()->request("subscription/{$handle}/on_hold", 'POST', $params);
                    } catch (Exception $e) {
                        wc_add_notice( $e->getMessage(), 'error' );
                    }
                } else {
                    wc_add_notice('Plan not found', 'error');
                }
            } else {
                wc_add_notice('Permission denied', 'error');
            }


            wp_redirect(wc_get_endpoint_url('subscriptions'));
            exit;
        }

        if (!empty($_GET['reactivate'])) {
            $handle = $_GET['reactivate'];
            $handle = urlencode($handle);

            $order = wc_get_orders([
                    'meta_key' => '_reepay_subscription_handle',
                    'meta_value' => $handle,
                ])[0] ?? null;

            if ($order && $order->get_customer_id() === get_current_user_id()) {
                try {
                    $result = reepay_s()->api()->request("subscription/{$handle}/reactivate", 'POST');
                } catch (Exception $e) {
                    wc_add_notice( $e->getMessage() );
                }
            } else {
                wc_add_notice('Permission denied', 'error');
            }
            wp_redirect(wc_get_endpoint_url('subscriptions'));
        }

        if (!empty($_GET['change_payment_method'])) {
            $handle = $_GET['change_payment_method'];
            $token_id = $_GET['token_id'];
            $token = WC_Payment_Tokens::get($token_id);
            $handle = urlencode($handle);
            $handle = urlencode($handle);

            $order = wc_get_orders([
                    'meta_key' => '_reepay_subscription_handle',
                    'meta_value' => $handle,
                ])[0] ?? null;

            $params = [
                'source' => $token->get_token(),
            ];

            if ($order && $order->get_customer_id() === get_current_user_id()) {
                try {
                    $result = reepay_s()->api()->request("subscription/{$handle}/pm", 'POST', $params);
                } catch (Exception $e) {
                    wc_add_notice( $e->getMessage() );
                }
            } else {
                wc_add_notice('Permission denied', 'error');
            }
            wp_redirect(wc_get_endpoint_url('subscriptions'));
        }
    }

    public function subscriptions_query_vars($endpoints) {
        $endpoints['subscriptions'] = 'subscriptions';
        return $endpoints;
    }

    public function get_title() {
        return __("Subscriptions", reepay_s()->settings('domain'));
    }

    public function subscriptions_endpoint($page = 1) {
        $cur_page = urlencode($page);

        $subscriptionsParams = [
            'page' => urlencode($page),
            'size' => 3,
        ];

        $subsResult = reepay_s()->api()->request("subscription?" . http_build_query($subscriptionsParams));
        $planResult = reepay_s()->api()->request("plan");
        $plans = [];

        foreach ($planResult as $item) {
            $plans[$item['handle']] = $item;
        }

        $subscriptions = $subsResult['content'];

        $subscriptionsArr = [];

        foreach ($subscriptions as $subscription) {
            $payment_methods = reepay_s()->api()->request("subscription/".$subscription['handle']."/pm");
            $subscriptionsArr[] = [
                'state' => $subscription['state'],
                'handle' => $subscription['handle'],
                'is_cancelled' => $subscription['is_cancelled'],
                'renewing' => $subscription['renewing'],
                'first_period_start' => $subscription['first_period_start'],
                'formatted_first_period_start' => $this->format_date($subscription['first_period_start']),
                'current_period_start' => $subscription['current_period_start'] ?? null,
                'formatted_current_period_start' => $this->format_date($subscription['current_period_start'] ?? null),
                'next_period_start' => $subscription['next_period_start'] ?? null,
                'formatted_next_period_start' => $this->format_date($subscription['next_period_start'] ?? null),
                'expired_date' => $subscription['expired_date'] ?? null,
                'formatted_expired_date' => $this->format_date($subscription['expired_date'] ?? null),
                'formatted_status' => $this->get_status($subscription),
                'payment_methods' => $payment_methods,
                'plan' => $subscription['plan']
            ];
        }

        wc_get_template(
            'my-account/subscriptions.php',
            array(
                'subscriptions' => $subscriptionsArr,
                'plans' => $plans,
                'current' => $cur_page,
                'total' => $subsResult['total_pages']
            ),
            '',
            reepay_s()->settings('plugin_path').'templates/'
        );
    }

    public function add_subscriptions_menu_item($menu_items) {
        $returnArr = [];
        foreach ($menu_items as $key => $menu_item) {
            $returnArr[$key] = $menu_item;
            if ($key === 'orders') {
                $returnArr["subscriptions"] = $this->get_title();
            }
        }
        return $returnArr;
    }

    function get_status($subscription) {
        if ($subscription['is_cancelled'] === true) {
            return 'cancelled';
        }
        if ($subscription['state'] === 'expired') {
            return 'expired';
        }

        if ($subscription['state'] === 'on_hold') {
            return 'on_hold';
        }

        if ($subscription['state'] === 'is_cancelled') {
            return 'is_cancelled';
        }

        if ($subscription['state'] === 'active') {
            if (isset($subscription['trial_end'])) {
                $now = new DateTime();
                $trial_end = new DateTime($subscription['trial_end']);
                if ($trial_end > $now) {
                    return 'trial';
                }
            }
            return 'active';
        }

        return $subscription['state'];
    }

    function format_date($dateStr) {
        if (!empty($dateStr)) {
            return (new DateTime($dateStr))->format('d M Y');
        }
    }
}

new WC_Reepay_Account_Page();
