<?php

/*
 * Plugin Name: Reepay Subscriptions for WooCommerce
 * Description: Get all the advanced subscription features from Reepay while still keeping your usual WooCommerce tools. The Reepay Subscription for WooCommerce plugins gives you the best prerequisites to succeed with your subscription business.
 * Author: reepay
 * Author URI: https://reepay.com/
 * Version: 1.0.4
 * Text Domain: reepay-subscriptions-for-woocommerce
 * Domain Path: /languages
 * WC requires at least: 3.0.0
 * WC tested up to: 4.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const REEPAY_PLUGIN_FILE = __FILE__;

class WooCommerce_Reepay_Subscriptions {
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
	 * @var WC_Reepay_Subscription_Plan_Variable
	 */
	private $plan_variable;

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

	/**
	 * @var <string, string>
	 */
	public static $compensation_methods = [
		'none'            => 'None',
		'full_refund'     => 'Full refund',
		'prorated_refund' => 'Prorated refund',
		'full_credit'     => 'Full credit',
		'prorated_credit' => 'Prorated credit',
	];

	/**
	 * @var <string>
	 */
	public static $webhook_event_types = [
		"subscription_reactivated",
		"invoice_cancelled",
		"subscription_on_hold_dunning",
		"subscription_created",
		"invoice_failed",
		"subscription_renewal",
		"invoice_dunning",
		"subscription_on_hold",
		"invoice_credited",
		"subscription_changed",
		"invoice_adjustment",
		"invoice_created",
		"subscription_cancelled",
		"subscription_payment_method_changed",
		"invoice_changed",
		"invoice_dunning_cancelled",
		"subscription_payment_method_added",
		"subscription_trial_end",
		"subscription_uncancelled",
		"subscription_trial_end_reminder",
		"customer_created",
		"customer_payment_method_added",
		"invoice_dunning_notification",
		"invoice_reactivate",
		"subscription_renewal_reminder",
		"customer_changed",
		"subscription_expired",
		"subscription_expired_dunning",
		"invoice_authorized",
		"customer_deleted",
		"invoice_settled",
		"invoice_refund"
	];

	public static $db_version = '1.0.2';

	/**
	 * Constructor
	 */
	private function __construct() {

		// Check if WooCommerce is active
		include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		if ( ! is_plugin_active_for_network( 'woocommerce/woocommerce.php' ) && ! is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
			return;
		}

		$settings = get_option( 'woocommerce_reepay_checkout_settings' );

		self::$settings = [
			'domain'                                     => 'reepay-subscriptions-for-woocommerce',
			'plugin_url'                                 => plugin_dir_url( __FILE__ ),
			'plugin_path'                                => plugin_dir_path( __FILE__ ),
			'version'                                    => static::$version,
			'rest_api_namespace'                         => static::$rest_api_namespace,
			'debug'                                      => get_option( '_reepay_debug' ) === 'yes',
			'test_mode'                                  => $settings['test_mode'] === 'yes',
			'api_private_key'                            => ! empty( $settings['private_key'] ) ? $settings['private_key'] : '',
			'api_private_key_test'                       => ! empty( $settings['private_key_test'] ) ? $settings['private_key_test'] : '',
			'_reepay_enable_downgrade'                   => get_option( '_reepay_enable_downgrade' ) === 'yes',
			'_reepay_downgrade_compensation_method'      => get_option( '_reepay_downgrade_compensation_method' ),
			'_reepay_enable_upgrade'                     => get_option( '_reepay_enable_upgrade' ) === 'yes',
			'_reepay_upgrade_compensation_method'        => get_option( '_reepay_upgrade_compensation_method' ),
			'_reepay_enable_on_hold'                     => get_option( '_reepay_enable_on_hold' ) === 'yes',
			'_reepay_on_hold_compensation_method'        => get_option( '_reepay_on_hold_compensation_method' ),
			'_reepay_enable_cancel'                      => get_option( '_reepay_enable_cancel' ) === 'yes',
			'_reepay_suborders_default_renew_status'     => get_option( '_reepay_suborders_default_renew_status' ) ?: 'wc-completed',
			'_reepay_orders_default_subscription_status' => get_option( '_reepay_orders_default_subscription_status' ) ?: 'wc-processing',
			'_reepay_manual_start_date'                  => get_option( '_reepay_manual_start_date' ) === 'yes',
			'_reepay_manual_start_date_status'           => get_option( '_reepay_manual_start_date_status' ) ?: 'wc-completed',
		];


		$this->includes();
		$this->init_classes();

		register_activation_hook( REEPAY_PLUGIN_FILE, __CLASS__ . '::install' );

		add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_customer_report' ] );
		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), [ $this, 'plugin_action_links' ] );
		add_filter( 'woocommerce_settings_tabs_array', [ $this, 'add_settings_tab' ], 50 );
		add_action( 'woocommerce_settings_tabs_reepay_subscriptions', [ $this, 'settings_tab' ] );
		add_action( 'woocommerce_update_options_reepay_subscriptions', [ $this, 'update_settings' ] );
		add_filter( 'plugin_row_meta', [ $this, 'plugin_row_meta' ], 10, 2 );
		add_action( 'admin_init', [ $this, 'reepay_admin_notices' ] );
		add_action( 'init', [ $this, 'init' ] );

		if (!has_action('woocommerce_admin_field_hr')) {
            add_action('woocommerce_admin_field_hr', [$this, 'hr_field']);
        }

	}

	public function hr_field() {
	    ?>
        <tr valign="top" class="" style="border-top: 1px solid #c3c4c7">
        </tr>
        <?php
    }

	public static function install() {
		flush_rewrite_rules();

		if ( ! get_option( 'woocommerce_reepay_subscriptions_version' ) ) {
			add_option( 'woocommerce_reepay_subscriptions_version', self::$db_version );
		}
	}

	public function init() {
		load_plugin_textdomain( self::settings( 'domain' ), false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

		new WC_Reepay_Subscriptions_Update( self::$db_version );
	}

	public function reepay_admin_notices() {
		if ( ! class_exists( 'WC_ReepayCheckout', false ) ) {

			WC_Reepay_Subscription_Admin_Notice::add_activation_notice(
				sprintf(
					wp_kses(
						__( 'The plugin Reepay Checkout for WooCommerce is required for Reepay Subscriptions for WooCommerce. <a target="_blank" href="%s">Please install and activate the plugin.</a>',
							'reepay-subscriptions-for-woocommerce'
						), [
							'a' => [
								'href'   => true,
								'target' => true
							]
						]
					),
					'https://wordpress.org/plugins/reepay-checkout-gateway/'
				)
			);
		}

		$settings           = get_option( 'woocommerce_reepay_checkout_settings' );
		$test_subscriptions = get_option( '_reepay_api_private_key_test' );
		$test_gateway       = $settings["private_key_test"] ?? '';

		if ( ! empty( $test_subscriptions ) && ! empty( $test_gateway ) ) {
			if ( $test_subscriptions != $test_gateway ) {
				WC_Reepay_Subscription_Admin_Notice::add_activation_notice(
					sprintf(
						wp_kses(
							__( 'Reepay checkout test key must match with Reepay subscriptions test key, please <a href="%s">check settings</a>',
								'reepay-subscriptions-for-woocommerce'
							), [
								'a' => [
									'href' => true
								]
							]
						),
						get_admin_url() . 'admin.php?page=wc-settings&tab=reepay_subscriptions'
					)
				);
			}
		}

		$live_subscriptions = get_option( '_reepay_api_private_key' );
		$live_gateway       = $settings["private_key"] ?? '';

		if ( ! empty( $live_subscriptions ) && ! empty( $live_gateway ) ) {
			if ( $live_subscriptions != $live_gateway ) {
				WC_Reepay_Subscription_Admin_Notice::add_activation_notice(
					sprintf(
						wp_kses(
							__( 'Reepay checkout live key must match with Reepay subscriptions live key, please <a href="%s">check settings</a>',
								'reepay-subscriptions-for-woocommerce'
							),
							[
								'a' => [
									'href' => true
								]
							]
						),
						get_admin_url() . 'admin.php?page=wc-settings&tab=reepay_subscriptions'
					)
				);
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
	public function plugin_row_meta( $links, $file ) {

		if ( plugin_basename( __FILE__ ) !== $file ) {
			return $links;
		}

		$row_meta = [
			'account' => '<a target="_blank" href="https://signup.reepay.com/?_gl=1*1iccm28*_gcl_aw*R0NMLjE2NTY1ODI3MTQuQ2p3S0NBandrX1dWQmhCWkVpd0FVSFFDbVJaNDJmVmVQWFc4LUlpVDRndE83bWRmaW5NNG5wZDhkaG12dVJFOEZkbDR4eXVMNlZpMTRSb0N1b2NRQXZEX0J3RQ..*_ga*MjA3MDA3MTk4LjE2NTM2MzgwNjY.*_ga_F82PFFEF3F*MTY2Mjk2NTEwNS4xOS4xLjE2NjI5NjUxODkuMC4wLjA.&_ga=2.98685660.319325710.1662963483-207007198.1653638066#/en">' . __( 'Get free test account', reepay_s()->settings( 'domain' ) ) . '</a>',
			'pricing' => '<a target="_blank" href="https://reepay.com/pricing/">' . __( 'Pricing', 'reepay-subscriptions-for-woocommerce' ) . '</a>',
		];


		return array_merge( $links, $row_meta );
	}

	public function admin_customer_report() {
		if ( isset( $_GET['path'] ) && $_GET['path'] == '/customers' ) {
			$script_path       = 'assets/js/analytics/build/index.js';
			$script_asset_path = $this->settings( 'plugin_url' ) . 'assets/js/analytics/build/index.asset.php';
			$script_asset      = file_exists( $script_asset_path )
				? require( $script_asset_path )
				: [ 'dependencies' => [], 'version' => filemtime( $this->settings( 'plugin_path' ) . $script_path ) ];
			$script_url        = $this->settings( 'plugin_url' ) . $script_path;

			wp_register_script(
				'reepay-customer-extends',
				$script_url,
				$script_asset['dependencies'],
				$script_asset['version'],
				true
			);

			wp_register_style(
				'reepay-customer-extends',
				$this->settings( 'plugin_url' ) . 'assets/js/analytics/build/index.css',
				// Add any dependencies styles may have, such as wp-components.
				[],
				filemtime( $this->settings( 'plugin_path' ) . 'assets/js/analytics/build/index.css' )
			);

			wp_enqueue_script( 'reepay-customer-extends' );
			wp_enqueue_style( 'reepay-customer-extends' );
		}
	}

	/**
	 * Add relevant links to plugins page
	 *
	 * @param array $links
	 *
	 * @return array
	 */
	public function plugin_action_links( $links ) {
		$plugin_links = [
			'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=reepay_subscriptions' ) . '">' . __( 'Settings', 'reepay-subscriptions-for-woocommerce' ) . '</a>'
		];

		return array_merge( $plugin_links, $links );
	}

	public function add_settings_tab( $settings_tabs ) {
		$settings_tabs['reepay_subscriptions'] = __( 'Reepay Subscriptions', 'reepay-subscriptions-for-woocommerce' );

		return $settings_tabs;
	}

	public function settings_tab() {
		woocommerce_admin_fields( static::get_settings() );
	}

	public function update_settings() {
		woocommerce_update_options( static::get_settings() );

		$this->enable_all_webhook_event_types();
	}

	public function enable_all_webhook_event_types() {
		$webhook_settings = reepay_s()->api()->request( 'account/webhook_settings' );

		if ( count( $webhook_settings['event_types'] ) < count( static::$webhook_event_types ) ) {
			$webhook_settings['event_types'] = static::$webhook_event_types;

			try {
				reepay_s()->api()->request( 'account/webhook_settings', 'PUT', $webhook_settings );
			} catch ( Exception $e ) {
				reepay_s()->log()->log( [
					'source'  => 'WooCommerce_Reepay_Subscriptions::update_settings',
					'message' => 'Updating webhook settings',
					'request' => $webhook_settings,
					'error'   => $e
				], 'error' );
			}
		}
	}

	public function get_settings() {

		$settings = [
			'section_title'                              => [
				'name' => __( 'Reepay Subscription Settings', 'reepay-subscriptions-for-woocommerce' ),
				'type' => 'title',
				'desc' => '',
				'id'   => 'reepay_section_title'
			],
			/*'test_mode'                              => [
				'name' => __( 'Test mode', 'reepay-subscriptions-for-woocommerce' ),
				'type' => 'checkbox',
				'desc' => __( 'Enable test API mode', 'reepay-subscriptions-for-woocommerce' ),
				'id'   => '_reepay_test_mode'
			],*/
			'debug'                                      => [
				'name' => __( 'Enable logging', 'reepay-subscriptions-for-woocommerce' ),
				'type' => 'checkbox',
				'desc' => __( 'Enable API logging. Logs can be seen in WooCommerce > Status > Logs', reepay_s()->settings( 'domain' ) ),
				'id'   => '_reepay_debug'
			],
            'hr_subscriptions' => [
              'type' => 'hr',
                'id' => 'hr_subscriptions',
            ],
			/*'api_private_key'                        => [
				'name' => __( 'Private Key Live', 'reepay-subscriptions-for-woocommerce' ),
				'type' => 'text',
				'desc' => __( 'Private Key Live for API', 'reepay-subscriptions-for-woocommerce' ),
				'id'   => '_reepay_api_private_key'
			],
			'api_private_key_test'                   => [
				'name' => __( 'Private Key Test', 'reepay-subscriptions-for-woocommerce' ),
				'type' => 'text',
				'desc' => __( 'Private Key Test for test API', 'reepay-subscriptions-for-woocommerce' ),
				'id'   => '_reepay_api_private_key_test'
			],*/
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
			'_reepay_enable_on_hold'                     => [
				'name' => __( 'Enable On Hold', 'reepay-subscriptions-for-woocommerce' ),
				'type' => 'checkbox',
				'desc' => __( 'Enable On Hold', 'reepay-subscriptions-for-woocommerce' ),
				'id'   => '_reepay_enable_on_hold'
			],
			'_reepay_on_hold_compensation_method'        => [
				'name'    => __( 'Compensation method for On Hold', 'reepay-subscriptions-for-woocommerce' ),
				'type'    => 'select',
				'options' => static::$compensation_methods,
				'desc'    => __( 'Compensation method for on_hold', 'reepay-subscriptions-for-woocommerce' ),
				'id'      => '_reepay_on_hold_compensation_method'
			],
			'_reepay_enable_cancel'                      => [
				'name' => __( 'Enable Cancel', 'reepay-subscriptions-for-woocommerce' ),
				'type' => 'checkbox',
				'desc' => __( 'Enable Cancel', 'reepay-subscriptions-for-woocommerce' ),
				'id'   => '_reepay_enable_cancel'
			],
			'_reepay_cancel_compensation_method'         => [
				'name'    => __( 'Compensation method for Cancel', 'reepay-subscriptions-for-woocommerce' ),
				'type'    => 'select',
				'options' => static::$compensation_methods,
				'desc'    => __( 'Compensation method for cancel', 'reepay-subscriptions-for-woocommerce' ),
				'id'      => '_reepay_cancel_compensation_method'
			],
			'_reepay_orders_default_subscription_status' => [
				'name'    => __( 'Subscription order default status after create', 'reepay-subscriptions-for-woocommerce' ),
				'type'    => 'select',
				'options' => wc_get_order_statuses(),
				'desc'    => __( 'Setting to control witch status the woocommerce order gets, when it is created based on a Reepay invoice', 'reepay-subscriptions-for-woocommerce' ),
				'id'      => '_reepay_orders_default_subscription_status'
			],
            'hr_suborders' => [
                'type' => 'hr',
                'id' => 'hr_suborders',
            ],
			'_reepay_suborders_default_renew_status'     => [
				'name'    => __( 'Suborders default status after renew', 'reepay-subscriptions-for-woocommerce' ),
				'type'    => 'select',
				'options' => wc_get_order_statuses(),
				'desc'    => __( 'Setting to control witch status the woocommerce order gets, when it is created based on a Reepay invoice', 'reepay-subscriptions-for-woocommerce' ),
				'id'      => '_reepay_suborders_default_renew_status'
			],
            'hr_date' => [
                'type' => 'hr',
                'id' => 'hr_date',
            ],
			'_reepay_manual_start_date'                  => [
				'name' => __( 'Enable manual subscription start date', 'reepay-subscriptions-for-woocommerce' ),
				'type' => 'checkbox',
				'desc' => __( 'Enable manual subscription start date. <p class="description">This will set a temporary start date for the subscription that is far in the future. We recommend removing the start date tag from your sign up emails in Reepay.</p>', 'reepay-subscriptions-for-woocommerce' ),
				'id'   => '_reepay_manual_start_date'
			],
			'_reepay_manual_start_date_status'           => [
				'name'    => __( 'Manual start date order status', 'reepay-subscriptions-for-woocommerce' ),
				'type'    => 'select',
				'options' => wc_get_order_statuses(),
				'desc'    => __( 'Subscription will start when parent order get changed to this order status ', 'reepay-subscriptions-for-woocommerce' ),
				'id'      => '_reepay_manual_start_date_status'
			],
			'section_end'                                => [
				'type' => 'sectionend',
				'id'   => 'reepay_section_end'
			],
		];

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
	 * @param mixed|null $product Current product
	 *
	 * @return WC_Reepay_Subscription_Plan_Simple
	 */
	public function plan( $product = null ) {
		if ( is_null( $product ) ) {
			return $this->plan_simple;
		}

		$product = wc_get_product( $product );

		if ( $product->is_type( 'variation' ) ) {
			$product = wc_get_product( $product->get_parent_id() );
		}

		if ( $product->is_type( 'reepay_variable_subscriptions' ) ) {
			return $this->plan_variable;
		}

		return $this->plan_simple;
	}

	/**
	 * Return plugin settings
	 *
	 * @param string $property_name
	 *
	 * @return mixed
	 */
	public static function settings( $property_name = null ) {
		return isset( $property_name ) ? ( self::$settings[ $property_name ] ?? null ) : self::$settings;
	}

	public function admin_enqueue_scripts() {
		$product = wc_get_product();

		wp_enqueue_script( 'admin-reepay-subscription', $this->settings( 'plugin_url' ) . 'assets/js/admin.js', [ 'jquery' ], $this->settings( 'version' ), true );
		wp_enqueue_style( 'admin-reepay-subscription', $this->settings( 'plugin_url' ) . 'assets/css/admin.css' );
		wp_localize_script( 'admin-reepay-subscription', 'reepay', [
			'amountPercentageLabel' => __( 'Percentage', reepay_s()->settings( 'domain' ) ),
			'product'               => [
				'id'          => empty( $product ) ? 0 : $product->get_id(),
				'is_variable' => empty( $product ) ? false : $product->is_type( 'reepay_variable_subscriptions' ),
				'status'      => empty( $product ) ? '' : $product->get_status( '' )
			],
			'rest_urls'             => [
				'get_plan'     => get_rest_url( 0, reepay_s()->settings( 'rest_api_namespace' ) . "/plan_simple/" ),
				'get_coupon'   => get_rest_url( 0, reepay_s()->settings( 'rest_api_namespace' ) . "/coupon/" ),
				'get_discount' => get_rest_url( 0, reepay_s()->settings( 'rest_api_namespace' ) . "/discount/" ),
				'get_addon'    => get_rest_url( 0, reepay_s()->settings( 'rest_api_namespace' ) . "/addon/" ),
			]
		] );
	}

	public function includes() {
		include_once( $this->settings( 'plugin_path' ) . '/vendor/autoload.php' );

	}

	public function init_classes() {
		$this->api = WC_Reepay_Subscription_API::get_instance();
		$this->log = WC_RS_Log::get_instance();

		$this->plan_simple   = new WC_Reepay_Subscription_Plan_Simple;
		$this->plan_variable = new WC_Reepay_Subscription_Plan_Variable();

		new WC_Reepay_Subscription_Addons();
		new WC_Reepay_Account_Page();
		new WC_Reepay_Admin_Frontend();
		new WC_Reepay_Checkout();
		new WC_Reepay_Discounts_And_Coupons();
		new WC_Reepay_Renewals();
		new WC_Reepay_Statistics();
		new WC_Reepay_Subscription_Addons_Rest();
		new WC_Reepay_Subscription_Addons_Shipping();
		new WC_Reepay_Subscription_Admin_Notice();
		new WC_Reepay_Subscription_Coupons_Rest();
		new WC_Reepay_Subscription_Discounts_Rest();
		new WC_Reepay_Subscription_Plan_Simple_Rest();
		new WC_Reepay_Subscriptions_List();
		new WC_Reepay_Import();
		new WC_Reepay_Woocommerce_Subscription_Extension();
		new WC_Reepay_Sync();
	}
}

/**
 * @return WooCommerce_Reepay_Subscriptions
 */
function reepay_s() {
	return WooCommerce_Reepay_Subscriptions::get_instance();
}

reepay_s();

