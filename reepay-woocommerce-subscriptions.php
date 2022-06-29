<?php

/*
 * Plugin Name: WooCommerce Reepay Subscriptions
 * Description: Provides a subscriptions through Reepay for WooCommerce.
 * Author: OnePix
 * Author URI: https://onepix.net
 * Version: 1.0.0
 * Text Domain: reepay-woocommerce-subscriptions
 * Domain Path: /languages
 * WC requires at least: 3.0.0
 * WC tested up to: 4.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define('REEPAY_PLUGIN_FILE', __FILE__);

class WooCommerce_Reepay_Subscriptions{
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
	 * @var array<string, mixed>
	 */
	private static $settings;

    /**
     * @var string
     */
    public static $version = '1.0.0';

    /**
     * Constructor
     */
    private function __construct() {

        // Check if WooCommerce is active
        include_once(ABSPATH . 'wp-admin/includes/plugin.php');
        if(!is_plugin_active_for_network('woocommerce/woocommerce.php') && !is_plugin_active('woocommerce/woocommerce.php')) {
            return;
        }

    	self::$settings = [
    		'domain' => 'reepay-woocommerce-subscriptions',
    		'plugin_url' => plugin_dir_url(__FILE__),
    		'plugin_path' => plugin_dir_path(__FILE__),
    		'version' => static::$version,
		    'debug' => get_option('_reepay_debug') === 'yes',
		    'test_mode' => get_option('_reepay_test_mode') === 'yes',
		    'api_private_key' => get_option('_reepay_api_private_key'),
		    'api_private_key_test' => get_option('_reepay_api_private_key_test'),
	    ];



        $this->includes();
        add_action('admin_enqueue_scripts', [$this, 'admin_enqueue_scripts']);
        add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), [$this, 'plugin_action_links'] );
        add_filter( 'woocommerce_settings_tabs_array', [$this, 'add_settings_tab'], 50 );
        add_action( 'woocommerce_settings_tabs_reepay_subscriptions', [$this, 'settings_tab'] );
        add_action( 'woocommerce_update_options_reepay_subscriptions', [$this, 'update_settings'] );
        register_activation_hook( REEPAY_PLUGIN_FILE, 'flush_rewrite_rules' );

        $this->api = WC_Reepay_Subscription_API::get_instance();
        $this->log = WC_RS_Log::get_instance();
    }

    /**
     * Add relevant links to plugins page
     *
     * @param  array $links
     *
     * @return array
     */
    public function plugin_action_links( $links ) {
        $plugin_links = array(
            '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=reepay_subscriptions' ) . '">' . __( 'Settings', 'reepay-checkout-gateway' ) . '</a>'
        );

        return array_merge( $plugin_links, $links );
    }

    public function add_settings_tab( $settings_tabs ) {
        $settings_tabs['reepay_subscriptions'] = __( 'Reepay Subscriptions Settings', reepay_s()->settings('domain') );
        return $settings_tabs;
    }

    public function settings_tab() {
        woocommerce_admin_fields( static::get_settings() );
    }

    public function update_settings() {
        if ($_POST['_reepay_api_private_key'] !== static::settings('api_private_key')) {
            WC_Reepay_Statistics::private_key_activated();
        }

        woocommerce_update_options( static::get_settings() );
    }

    public function get_settings() {

        $settings = array(
            'section_title' => array(
                'name'     => __( 'Reepay Subscription Settings', reepay_s()->settings('domain') ),
                'type'     => 'title',
                'desc'     => '',
                'id'       => 'reepay_section_title'
            ),
            '_reepay_enable_downgrade' => array(
                'name' => __('Enable Downgrade', reepay_s()->settings('domain')),
                'type' => 'checkbox',
                'desc' => __('Enable Downgrade', reepay_s()->settings('domain')),
                'id' => '_reepay_enable_downgrade'
            ),
            '_reepay_downgrade_compensation_method' => array(
                'name' => __('Compensation method for downgrade', reepay_s()->settings('domain')),
                'type' => 'select',
                'options' =>  [
                    'none' => 'None',
                    'full_refund' => 'Full refund',
                    'prorated_refund' => 'Prorated refund',
                    'full_credit' => 'Full credit',
                    'prorated_credit' => 'Prorated credit',
                ],
                'desc' => __('Compensation method for downgrade', reepay_s()->settings('domain')),
                'id' => '_reepay_downgrade_compensation_method'
            ),
            '_reepay_enable_upgrade' => array(
                'name' => __('Enable Upgrade', reepay_s()->settings('domain')),
                'type' => 'checkbox',
                'desc' => __('Enable Upgrade', reepay_s()->settings('domain')),
                'id' => '_reepay_enable_upgrade'
            ),
            '_reepay_upgrade_compensation_method' => array(
                'name' => __('Compensation method for upgrade', reepay_s()->settings('domain')),
                'type' => 'select',
                'options' =>  [
                    'none' => 'None',
                    'full_refund' => 'Full refund',
                    'prorated_refund' => 'Prorated refund',
                    'full_credit' => 'Full credit',
                    'prorated_credit' => 'Prorated credit',
                ],
                'desc' => __('Compensation method for upgrade', reepay_s()->settings('domain')),
                'id' => '_reepay_upgrade_compensation_method'
            ),
            '_reepay_enable_on_hold' => array(
                'name' => __('Enable On Hold', reepay_s()->settings('domain')),
                'type' => 'checkbox',
                'desc' => __('Enable On Hold', reepay_s()->settings('domain')),
                'id' => '_reepay_enable_on_hold'
            ),
            '_reepay_on_hold_compensation_method' => array(
                'name' => __('Compensation method for On Hold', reepay_s()->settings('domain')),
                'type' => 'select',
                'options' => [
                    'none' => 'None',
                    'full_refund' => 'Full refund',
                    'prorated_refund' => 'Prorated refund',
                    'full_credit' => 'Full credit',
                    'prorated_credit' => 'Prorated credit',
                ],
                'desc' => __('Compensation method for on_hold', reepay_s()->settings('domain')),
                'id' => '_reepay_on_hold_compensation_method'
            ),
            '_reepay_enable_cancel' => array(
                'name' => __('Enable Cancel', reepay_s()->settings('domain')),
                'type' => 'checkbox',
                'desc' => __('Enable Cancel', reepay_s()->settings('domain')),
                'id' => '_reepay_enable_cancel'
            ),
            '_reepay_cancel_compensation_method' => array(
                'name' => __('Compensation method for Cancel', reepay_s()->settings('domain')),
                'type' => 'select',
                'options' =>  [
                    'none' => 'None',
                    'full_refund' => 'Full refund',
                    'prorated_refund' => 'Prorated refund',
                    'full_credit' => 'Full credit',
                    'prorated_credit' => 'Prorated credit',
                ],
                'desc' => __('Compensation method for cancel', reepay_s()->settings('domain')),
                'id' => '_reepay_cancel_compensation_method'
            ),
            'test_mode' => array(
                'name' => __( 'Test mode', reepay_s()->settings('domain') ),
                'type' => 'checkbox',
                'desc' => __( 'Enable test api mode', reepay_s()->settings('domain') ),
                'id'   => '_reepay_test_mode'
            ),
            'debug' => array(
                'name' => __( 'Enable logging', reepay_s()->settings('domain') ),
                'type' => 'checkbox',
                'desc' => __( 'Enable api logging. Logs can be seen in WooCommerce > Status > Logs', reepay_s()->settings('domain') ),
                'id'   => '_reepay_debug'
            ),
            'api_private_key' => array(
                'name' => __( 'Private key', reepay_s()->settings('domain') ),
                'type' => 'text',
                'desc' => __( 'Private key for api', reepay_s()->settings('domain') ),
                'id'   => '_reepay_api_private_key'
            ),
            'api_private_key_test' => array(
                'name' => __( 'Private key (Test)', reepay_s()->settings('domain') ),
                'type' => 'text',
                'desc' => __( 'Private key for test api', reepay_s()->settings('domain') ),
                'id'   => '_reepay_api_private_key_test'
            ),
            'section_end' => array(
                'type' => 'sectionend',
                'id' => 'reepay_section_end'
            ),
        );

        return apply_filters( 'wc_settings_tab_reepay_subscriptions', $settings );
    }
	/**
	 * @return WooCommerce_Reepay_Subscriptions
	 */
	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * @return WC_Reepay_Subscription_API
	 */
	public function api() {
		return $this->api;
	}

	/**
	 * @return WC_RS_Log
	 */
	public function log() {
		return $this->log;
	}

	/**
	 * Return plugin settings
	 * @param  string  $property_name
	 *
	 * @return mixed
	 */
	public static function settings($property_name = null) {
		return isset($property_name) ? (self::$settings[$property_name] ?? null) : self::$settings;
	}

    public function admin_enqueue_scripts(){
        wp_enqueue_script('admin-reepay-subscription', $this->settings('plugin_url') . 'assets/js/admin.js', ['jquery'], $this->settings('version'), true);
        wp_enqueue_style('admin-reepay-subscription', $this->settings('plugin_url') . 'assets/css/admin.css');
        /*wp_localize_script('admin-reepay-subscriptiony', 'reepay', [
            'ajaxUrl' => admin_url('admin-ajax.php')
        ]);*/
    }

    public function includes(){
	    include_once( $this->settings('plugin_path') . '/includes/class-wc-reepay-api.php' );
	    include_once( $this->settings('plugin_path') . '/includes/class-wc-reepay-log.php' );
	    include_once( $this->settings('plugin_path') . '/includes/class-wc-reepay-admin-notice.php' );
	    include_once( $this->settings('plugin_path') . '/includes/class-wc-reepay-checkout.php' );
        include_once( $this->settings('plugin_path') . '/includes/class-wc-reepay-plan-simple.php' );
        include_once( $this->settings('plugin_path') . '/includes/class-wc-reepay-plan-variable.php' );
        include_once( $this->settings('plugin_path') . '/includes/class-wc-reepay-addons.php' );
	    include_once( $this->settings('plugin_path') . '/includes/class-wc-reepay-renewals.php' );
        include_once( $this->settings('plugin_path') . '/includes/class-wc-reepay-discounts-and-coupons.php' );
        include_once( $this->settings('plugin_path') . '/includes/class-wc-reepay-account-page.php' );
        include_once( $this->settings('plugin_path') . '/includes/class-wc-reepay-statistics.php' );
        include_once( $this->settings('plugin_path') . '/includes/class-wc-reepay-addons-shipping.php' );
        include_once( $this->settings('plugin_path') . '/includes/class-wc-reepay-subscriptions-list.php' );
        include_once( $this->settings('plugin_path') . '/includes/class-wc-reepay-subscriptions-table.php' );
        include_once( $this->settings('plugin_path') . '/includes/class-wc-reepay-admin-frontend.php' );
    }
}

/**
 * @return WooCommerce_Reepay_Subscriptions
 */
function reepay_s() {
	return WooCommerce_Reepay_Subscriptions::get_instance();
}

reepay_s();