<?php

/*
 * Plugin Name: Reepay Subscriptions for WooCommerce
 * Description: Get all the advanced subscription features from Reepay while still keeping your usual WooCommerce tools. The Reepay Subscription for WooCommerce plugins gives you the best prerequisites to succeed with your subscription business.
 * Author: reepay
 * Author URI: https://reepay.com/
 * Version: 1.0.0
 * Text Domain: reepay-woocommerce-subscriptions
 * Domain Path: /languages
 * WC requires at least: 3.0.0
 * WC tested up to: 4.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

define('REEPAY_PLUGIN_FILE', __FILE__);

class WooCommerce_Reepay_Subscriptions
{
    /**
     * @var WooCommerce_Reepay_Subscriptions
     */
    private static $instance;

    /**
     * @var WC_Reepay_Subscription_API
     */
    private $api;

    /**
     * @var WC_RS_Log
     */
    private $log;

    /**
     * @var WC_Reepay_Subscription_Plan_Simple
     */
    private $plan_simple;

    /**
     * @var array<string, mixed>
     */
    private static $settings;

    /**
     * @var string
     */
    public static $version = '1.0.1';

    /**
     * @var string
     */
    public static $rest_api_namespace = 'reepay_subscription';

    public static $compensation_methods = [
        'none' => 'None',
        'full_refund' => 'Full refund',
        'prorated_refund' => 'Prorated refund',
        'full_credit' => 'Full credit',
        'prorated_credit' => 'Prorated credit',
    ];

    /**
     * Constructor
     */
    private function __construct()
    {

        // Check if WooCommerce is active
        include_once(ABSPATH . 'wp-admin/includes/plugin.php');
        if (!is_plugin_active_for_network('woocommerce/woocommerce.php') && !is_plugin_active('woocommerce/woocommerce.php')) {
            return;
        }

        self::$settings = [
            'domain' => 'reepay-woocommerce-subscriptions',
            'plugin_url' => plugin_dir_url(__FILE__),
            'plugin_path' => plugin_dir_path(__FILE__),
            'version' => static::$version,
            'rest_api_namespace' => static::$rest_api_namespace,
            'debug' => get_option('_reepay_debug') === 'yes',
            'test_mode' => get_option('_reepay_test_mode') === 'yes',
            'api_private_key' => get_option('_reepay_api_private_key'),
            'api_private_key_test' => get_option('_reepay_api_private_key_test'),
            '_reepay_enable_downgrade' => get_option('_reepay_enable_downgrade') === 'yes',
            '_reepay_downgrade_compensation_method' => get_option('_reepay_downgrade_compensation_method'),
            '_reepay_enable_upgrade' => get_option('_reepay_enable_upgrade') === 'yes',
            '_reepay_upgrade_compensation_method' => get_option('_reepay_upgrade_compensation_method'),
            '_reepay_enable_on_hold' => get_option('_reepay_enable_on_hold') === 'yes',
            '_reepay_on_hold_compensation_method' => get_option('_reepay_on_hold_compensation_method'),
            '_reepay_enable_cancel' => get_option('_reepay_enable_cancel') === 'yes',
        ];


        $this->includes();
        add_action('admin_enqueue_scripts', [$this, 'admin_enqueue_scripts']);
        add_action('admin_enqueue_scripts', [$this, 'admin_customer_report']);
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), [$this, 'plugin_action_links']);
        add_filter('woocommerce_settings_tabs_array', [$this, 'add_settings_tab'], 50);
        add_action('woocommerce_settings_tabs_reepay_subscriptions', [$this, 'settings_tab']);
        add_action('woocommerce_update_options_reepay_subscriptions', [$this, 'update_settings']);
        add_filter('plugin_row_meta', array($this, 'plugin_row_meta'), 10, 2);
        register_activation_hook(REEPAY_PLUGIN_FILE, 'flush_rewrite_rules');
        add_action('admin_init', [$this, 'reepay_admin_notices']);

        $this->api = WC_Reepay_Subscription_API::get_instance();
        $this->log = WC_RS_Log::get_instance();
        $this->plan_simple = new WC_Reepay_Subscription_Plan_Simple;
        new WC_Reepay_Subscription_Plan_Variable();
    }

    public function reepay_admin_notices()
    {
        if (!class_exists('WC_ReepayCheckout', false)) {
            WC_Reepay_Subscription_Admin_Notice::add_activation_notice('The plugin Reepay Checkout for WooCommerce is required for Reepay Subscriptions for WooCommerce. <a target="_blank" href="https://wordpress.org/plugins/reepay-checkout-gateway/">Please install and activate the plugin.</a>');
        }

        $settings = get_option('woocommerce_reepay_checkout_settings');
        $test_subscriptions = get_option('_reepay_api_private_key_test');
        $test_gateway = $settings["private_key_test"];

        if (!empty($test_subscriptions) && !empty($test_gateway)) {
            if ($test_subscriptions != $test_gateway) {
                WC_Reepay_Subscription_Admin_Notice::add_activation_notice('Reepay checkout test key must match with Reepay subscriptions test key, please <a href="' . get_admin_url() . 'admin.php?page=wc-settings&tab=reepay_subscriptions">check settings</a>');
            }
        }

        $live_subscriptions = get_option('_reepay_api_private_key');
        $live_gateway = $settings["private_key"];

        if (!empty($live_subscriptions) && !empty($live_gateway)) {
            if ($live_subscriptions != $live_gateway) {
                WC_Reepay_Subscription_Admin_Notice::add_activation_notice('Reepay checkout live key must match with Reepay subscriptions live key, please <a href="' . get_admin_url() . 'admin.php?page=wc-settings&tab=reepay_subscriptions">check settings</a>');
            }
        }
    }

    /**
     * Show row meta on the plugin screen.
     *
     * @param mixed $links Plugin Row Meta.
     * @param mixed $file Plugin Base file.
     *
     * @return array
     */
    public function plugin_row_meta($links, $file)
    {

        if (plugin_basename(__FILE__) !== $file) {
            return $links;
        }

        $row_meta = array(
            'account' => '<a target="_blank" href="https://signup.reepay.com/?_gl=1*1iccm28*_gcl_aw*R0NMLjE2NTY1ODI3MTQuQ2p3S0NBandrX1dWQmhCWkVpd0FVSFFDbVJaNDJmVmVQWFc4LUlpVDRndE83bWRmaW5NNG5wZDhkaG12dVJFOEZkbDR4eXVMNlZpMTRSb0N1b2NRQXZEX0J3RQ..*_ga*MjA3MDA3MTk4LjE2NTM2MzgwNjY.*_ga_F82PFFEF3F*MTY2Mjk2NTEwNS4xOS4xLjE2NjI5NjUxODkuMC4wLjA.&_ga=2.98685660.319325710.1662963483-207007198.1653638066#/en">' . esc_html__('Get free test account', reepay_s()->settings('domain')) . '</a>',
            'pricing' => '<a target="_blank" href="https://reepay.com/pricing/">' . esc_html__('Pricing', reepay_s()->settings('domain')) . '</a>',
        );


        return array_merge($links, $row_meta);
    }

    public function admin_customer_report()
    {
        if (isset($_GET['path']) && $_GET['path'] == '/customers') {
            $script_path = 'assets/js/analytics/build/index.js';
            $script_asset_path = $this->settings('plugin_url') . 'assets/js/analytics/build/index.asset.php';
            $script_asset = file_exists($script_asset_path)
                ? require($script_asset_path)
                : ['dependencies' => [], 'version' => filemtime($this->settings('plugin_path') . $script_path)];
            $script_url = $this->settings('plugin_url') . $script_path;

            wp_register_script(
                'reepay-customer-extends',
                $script_url,
                $script_asset['dependencies'],
                $script_asset['version'],
                true
            );

            wp_register_style(
                'reepay-customer-extends',
                $this->settings('plugin_url') . 'assets/js/analytics/build/index.css',
                // Add any dependencies styles may have, such as wp-components.
                [],
                filemtime($this->settings('plugin_path') . 'assets/js/analytics/build/index.css')
            );

            wp_enqueue_script('reepay-customer-extends');
            wp_enqueue_style('reepay-customer-extends');
        }
    }

    /**
     * Add relevant links to plugins page
     *
     * @param array $links
     *
     * @return array
     */
    public function plugin_action_links($links)
    {
        $plugin_links = [
            '<a href="' . admin_url('admin.php?page=wc-settings&tab=reepay_subscriptions') . '">' . __('Settings', 'reepay-checkout-gateway') . '</a>'
        ];

        return array_merge($plugin_links, $links);
    }

    public function add_settings_tab($settings_tabs)
    {
        $settings_tabs['reepay_subscriptions'] = __('Reepay Subscriptions Settings', reepay_s()->settings('domain'));
        return $settings_tabs;
    }

    public function settings_tab()
    {
        woocommerce_admin_fields(static::get_settings());
    }

    public function update_settings()
    {
        if ($_POST['_reepay_api_private_key'] !== static::settings('api_private_key')) {
            WC_Reepay_Statistics::private_key_activated();
        }

        woocommerce_update_options(static::get_settings());
    }

    public function get_settings()
    {

        $settings = [
            'section_title' => [
                'name' => __('Reepay Subscription Settings', reepay_s()->settings('domain')),
                'type' => 'title',
                'desc' => '',
                'id' => 'reepay_section_title'
            ],
            'test_mode' => [
                'name' => __('Test mode', reepay_s()->settings('domain')),
                'type' => 'checkbox',
                'desc' => __('Enable test API mode', reepay_s()->settings('domain')),
                'id' => '_reepay_test_mode'
            ],
            'debug' => [
                'name' => __('Enable logging', reepay_s()->settings('domain')),
                'type' => 'checkbox',
                'desc' => __('Enable API logging. Logs can be seen in WooCommerce > Status > Logs', reepay_s()->settings('domain')),
                'id' => '_reepay_debug'
            ],
            'api_private_key' => [
                'name' => __('Private Key Live', reepay_s()->settings('domain')),
                'type' => 'text',
                'desc' => __('Private Key Live for API', reepay_s()->settings('domain')),
                'id' => '_reepay_api_private_key'
            ],
            'api_private_key_test' => [
                'name' => __('Private Key Test', reepay_s()->settings('domain')),
                'type' => 'text',
                'desc' => __('Private Key Test for test API', reepay_s()->settings('domain')),
                'id' => '_reepay_api_private_key_test'
            ],
            /*'_reepay_enable_downgrade' => [
                'name' => __('Enable Downgrade', reepay_s()->settings('domain')),
                'type' => 'checkbox',
                'desc' => __('Enable Downgrade', reepay_s()->settings('domain')),
                'id' => '_reepay_enable_downgrade'
            ],
            '_reepay_downgrade_compensation_method' => [
                'name' => __('Compensation method for downgrade', reepay_s()->settings('domain')),
                'type' => 'select',
                'options' => static::$compensation_methods,
                'desc' => __('Compensation method for downgrade', reepay_s()->settings('domain')),
                'id' => '_reepay_downgrade_compensation_method'
            ],
            '_reepay_enable_upgrade' => [
                'name' => __('Enable Upgrade', reepay_s()->settings('domain')),
                'type' => 'checkbox',
                'desc' => __('Enable Upgrade', reepay_s()->settings('domain')),
                'id' => '_reepay_enable_upgrade'
            ],
            '_reepay_upgrade_compensation_method' => [
                'name' => __('Compensation method for upgrade', reepay_s()->settings('domain')),
                'type' => 'select',
                'options' => static::$compensation_methods,
                'desc' => __('Compensation method for upgrade', reepay_s()->settings('domain')),
                'id' => '_reepay_upgrade_compensation_method'
            ],*/
            '_reepay_enable_on_hold' => [
                'name' => __('Enable On Hold', reepay_s()->settings('domain')),
                'type' => 'checkbox',
                'desc' => __('Enable On Hold', reepay_s()->settings('domain')),
                'id' => '_reepay_enable_on_hold'
            ],
            '_reepay_on_hold_compensation_method' => [
                'name' => __('Compensation method for On Hold', reepay_s()->settings('domain')),
                'type' => 'select',
                'options' => static::$compensation_methods,
                'desc' => __('Compensation method for on_hold', reepay_s()->settings('domain')),
                'id' => '_reepay_on_hold_compensation_method'
            ],
            '_reepay_enable_cancel' => [
                'name' => __('Enable Cancel', reepay_s()->settings('domain')),
                'type' => 'checkbox',
                'desc' => __('Enable Cancel', reepay_s()->settings('domain')),
                'id' => '_reepay_enable_cancel'
            ],
            '_reepay_cancel_compensation_method' => [
                'name' => __('Compensation method for Cancel', reepay_s()->settings('domain')),
                'type' => 'select',
                'options' => static::$compensation_methods,
                'desc' => __('Compensation method for cancel', reepay_s()->settings('domain')),
                'id' => '_reepay_cancel_compensation_method'
            ],
            'section_end' => [
                'type' => 'sectionend',
                'id' => 'reepay_section_end'
            ],
        ];

        return apply_filters('wc_settings_tab_reepay_subscriptions', $settings);
    }

    /**
     * @return WooCommerce_Reepay_Subscriptions
     */
    public static function get_instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * @return WC_Reepay_Subscription_API
     */
    public function api()
    {
        return $this->api;
    }

    /**
     * @return WC_RS_Log
     */
    public function log()
    {
        return $this->log;
    }

    /**
     * @return WC_Reepay_Subscription_Plan_Simple
     */
    public function plan()
    {
        return $this->plan_simple;
    }

    /**
     * Return plugin settings
     * @param string $property_name
     *
     * @return mixed
     */
    public static function settings($property_name = null)
    {
        return isset($property_name) ? (self::$settings[$property_name] ?? null) : self::$settings;
    }

    public function admin_enqueue_scripts()
    {
        wp_enqueue_script('admin-reepay-subscription', $this->settings('plugin_url') . 'assets/js/admin.js', ['jquery'], $this->settings('version'), true);
        wp_enqueue_style('admin-reepay-subscription', $this->settings('plugin_url') . 'assets/css/admin.css');
        wp_localize_script('admin-reepay-subscription', 'reepay', [
            'amountPercentageLabel' => __('Percentage', reepay_s()->settings('domain')),
            'rest_urls' => [
                'get_plan' => get_rest_url(0, reepay_s()->settings('rest_api_namespace') . "/plan_simple/") . '?product_id=' . ($_GET['post'] ?? 0),
                'get_coupon' => get_rest_url(0, reepay_s()->settings('rest_api_namespace') . "/coupon/"),
                'get_discount' => get_rest_url(0, reepay_s()->settings('rest_api_namespace') . "/discount/"),
                'get_addon' => get_rest_url(0, reepay_s()->settings('rest_api_namespace') . "/addon/"),
            ]
        ]);
    }

    public function includes()
    {
        include_once($this->settings('plugin_path') . '/includes/class-wc-reepay-api.php');
        include_once($this->settings('plugin_path') . '/includes/class-wc-reepay-log.php');
        include_once($this->settings('plugin_path') . '/includes/class-wc-reepay-admin-notice.php');
        include_once($this->settings('plugin_path') . '/includes/class-wc-reepay-checkout.php');
        include_once($this->settings('plugin_path') . '/includes/class-wc-reepay-plan-simple.php');
        include_once($this->settings('plugin_path') . '/includes/class-wc-reepay-plan-variable.php');
        include_once($this->settings('plugin_path') . '/includes/class-wc-reepay-addons.php');
        include_once($this->settings('plugin_path') . '/includes/class-wc-reepay-plan-simple-rest.php');
        include_once($this->settings('plugin_path') . '/includes/class-wc-reepay-addons-rest.php');
        include_once($this->settings('plugin_path') . '/includes/class-wc-reepay-coupons-rest.php');
        include_once($this->settings('plugin_path') . '/includes/class-wc-reepay-discounts-rest.php');
        include_once($this->settings('plugin_path') . '/includes/class-wc-reepay-renewals.php');
        include_once($this->settings('plugin_path') . '/includes/class-wc-reepay-discounts-and-coupons.php');
        include_once($this->settings('plugin_path') . '/includes/class-wc-reepay-account-page.php');
        include_once($this->settings('plugin_path') . '/includes/class-wc-reepay-statistics.php');
        include_once($this->settings('plugin_path') . '/includes/class-wc-reepay-addons-shipping.php');
        include_once($this->settings('plugin_path') . '/includes/class-wc-reepay-subscriptions-list.php');
        include_once($this->settings('plugin_path') . '/includes/class-wc-reepay-subscriptions-table.php');
        include_once($this->settings('plugin_path') . '/includes/class-wc-reepay-admin-frontend.php');
    }
}

/**
 * @return WooCommerce_Reepay_Subscriptions
 */
function reepay_s()
{
    return WooCommerce_Reepay_Subscriptions::get_instance();
}

reepay_s();

