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

class WC_Reepay_Subscriptions{
    public static $plugin_url;
    public static $domain;
    public static $plugin_path;
    public static $version;

    /**
     * Constructor
     */
    public function __construct() {
        self::$domain = 'reepay-woocommerce-subscriptions';
        self::$plugin_url = plugin_dir_url(__FILE__);
        self::$plugin_path = plugin_dir_path(__FILE__);
        self::$version = time();

        // Check if WooCommerce is active
        include_once(ABSPATH . 'wp-admin/includes/plugin.php');
        if(is_plugin_active_for_network('woocommerce/woocommerce.php') or is_plugin_active('woocommerce/woocommerce.php')) {
            $this->includes();
            add_action('admin_enqueue_scripts', [$this, 'admin_enqueue_scripts']);
        }
    }

    public function admin_enqueue_scripts(){
        wp_enqueue_script('admin-reepay-subscription', self::$plugin_url . 'assets/js/admin.js', ['jquery'], self::$version, true);
        wp_enqueue_style('admin-reepay-subscription', self::$plugin_url . 'assets/css/admin.css');
        /*wp_localize_script('admin-reepay-subscriptiony', 'reepay', [
            'ajaxUrl' => admin_url('admin-ajax.php')
        ]);*/
    }

    public function includes(){
	    include_once( self::$plugin_path . '/includes/class-wc-reepay-checkout.php' );
        include_once( self::$plugin_path . '/includes/class-wc-reepay-helpers.php' );
        include_once( self::$plugin_path . '/includes/class-wc-reepay-plans.php' );
        include_once( self::$plugin_path . '/includes/class-wc-reepay-renewals.php' );
    }
}

new WC_Reepay_Subscriptions();