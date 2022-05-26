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
     * Constructor
     */
    private function __construct() {
    	self::$settings = [
    		'domain' => 'reepay-woocommerce-subscriptions',
    		'plugin_url' => plugin_dir_url(__FILE__),
    		'plugin_path' => plugin_dir_path(__FILE__),
    		'version' => time(),
		    'debug' => true,
		    'test_mode' => true
	    ];

        // Check if WooCommerce is active
        include_once(ABSPATH . 'wp-admin/includes/plugin.php');
        if(is_plugin_active_for_network('woocommerce/woocommerce.php') or is_plugin_active('woocommerce/woocommerce.php')) {
            $this->includes();
            add_action('admin_enqueue_scripts', [$this, 'admin_enqueue_scripts']);
        }

        $this->api = WC_Reepay_Subscription_API::get_instance();
        $this->log = WC_RS_Log::get_instance();
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
	public static function s($property_name = null) {
		return isset($property_name) ? (self::$settings[$property_name] ?? null) : self::$settings;
	}

    public function admin_enqueue_scripts(){
        wp_enqueue_script('admin-reepay-subscription', $this->s('plugin_url') . 'assets/js/admin.js', ['jquery'], $this->s('version'), true);
        wp_enqueue_style('admin-reepay-subscription', $this->s('plugin_url') . 'assets/css/admin.css');
        /*wp_localize_script('admin-reepay-subscriptiony', 'reepay', [
            'ajaxUrl' => admin_url('admin-ajax.php')
        ]);*/
    }

    public function includes(){
	    include_once( $this->s('plugin_path') . '/includes/class-wc-reepay-api.php' );
	    include_once( $this->s('plugin_path') . '/includes/class-wc-reepay-log.php' );
	    include_once( $this->s('plugin_path') . '/includes/class-wc-reepay-admin-notice.php' );
	    include_once( $this->s('plugin_path') . '/includes/class-wc-reepay-helpers.php' );
	    include_once( $this->s('plugin_path') . '/includes/class-wc-reepay-checkout.php' );
        include_once( $this->s('plugin_path') . '/includes/class-wc-reepay-plans.php' );
        include_once( $this->s('plugin_path') . '/includes/class-wc-reepay-plans-variable.php' );
	    include_once( $this->s('plugin_path') . '/includes/class-wc-reepay-renewals.php' );
    }
}

/**
 * @return WooCommerce_Reepay_Subscriptions
 */
function reepay_s() {
	return WooCommerce_Reepay_Subscriptions::get_instance();
}

reepay_s();