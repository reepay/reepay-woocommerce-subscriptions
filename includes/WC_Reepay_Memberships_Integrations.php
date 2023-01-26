<?php

/**
 * Class WC_Reepay_Memberships_Integrations
 *
 * @since 1.0.9
 */
class WC_Reepay_Memberships_Integrations {
	/**
	 * true if "woo memberships" is active but "woo subscriptions" is disabled
	 *
	 * @var bool
	 */
	public static $woo_subscriptions_fake_activation = false;

	public function __construct() {
		if ( is_plugin_active( 'woocommerce-memberships/woocommerce-memberships.php' ) ) {
			$this->fake_woo_subscription_activation();
		}
	}

	/**
	 * Add "woocommerce-subscriptions" to list of active plugins
	 */
	public function fake_woo_subscription_activation() {
		if ( ! is_plugin_active( 'woocommerce-subscriptions/woocommerce-subscriptions.php' ) ) {
			$plugins   = get_option( 'active_plugins', array() );
			$plugins[] = 'woocommerce-subscriptions/woocommerce-subscriptions.php';
			update_option( 'active_plugins', $plugins );

			self::$woo_subscriptions_fake_activation = true;
		}
	}

	/**
	 * @param  WC_Memberships_Membership_Plan  $plan
	 *
	 * @return bool
	 */
	public function has_membership_plan_reepay_subscription( $plan ) {
		foreach ( $plan->get_product_ids() as $product_id ) {
			if ( WC_Reepay_Checkout::is_reepay_product( $product_id ) ) {
				return true;
			}
		}

		return false;
	}
}

if ( is_plugin_active( 'woocommerce-memberships/woocommerce-memberships.php' ) &&
     ! class_exists( 'WC_Subscription' ) ) {
	class WC_Subscription {
		public $fake = true;
		public function __construct($x) {
		}
	}
}
