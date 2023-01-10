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
			
			add_filter( 'woocommerce_is_subscription', [ $this, 'add_reepay_subscriptions_type' ], 100, 3 );
//		    add_filter( 'wc_memberships_plan_grants_access_while_subscription_active', [ $this, 'grant_access_to_memberships' ], 100, 3 );
			add_filter( 'wc_memberships_access_granting_purchased_product_id', [ $this, 'access_granting_purchased_product_id' ] );
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
	 * @param  bool        $is_subscription
	 * @param  int         $product_id
	 * @param  WC_Product  $product
	 */
	public function add_reepay_subscriptions_type( $is_subscription, $product_id, $product ) {
		return $is_subscription || WC_Reepay_Checkout::is_reepay_product( $product );
	}

	/**
	 *
	 */
	public function get_active_reepay_subscription_order_products() {

	}

	/**
	 * @see wc_memberships_get_order_access_granting_product_ids
	 *
	 * @param int|int[] $product_ids Product id or array of product ids that grant access (may be non unique)
	 * @param int[] $access_granting_product_ids Array of product ids that can grant access to this plan
	 * @param \WC_Memberships_Membership_Plan $plan Membership plan access will be granted to
	 */
	public function access_granting_purchased_product_id( $product_ids, $access_granting_product_ids, $plan ) {
		if ( ! is_array( $product_ids ) ) {
			$product_ids = [ $product_ids ];
		}



		return $product_ids;
	}

	/**
	 * Filter whether a plan grants access to a membership while subscription is active.
	 *
	 * @param  bool  $grants_access  Default: true.
	 * @param  int   $plan_id        Membership Plan ID.
	 *
	 * @return bool
	 */
	public function grant_access_to_memberships( $grants_access, $plan_id ) {
		if ( $grants_access ) {
			return $grants_access;
		}

		/** @var WC_Memberships_Membership_Plan $membership_plan */
		$membership_plan = wc_memberships_get_membership_plan( $plan_id );

		if ( 'purchase' !== $membership_plan->get_access_method() ) {
			return $grants_access;
		}

		$plan_products = $membership_plan->get_products();
		$user_memberships = wc_memberships_get_user_membership( $user_id, $this->id );

		foreach ( $plan_products as $product ) {
			if ( ! WC_Reepay_Checkout::is_reepay_product( $product ) ) {
				continue;
			}

			/**
			 * TODO Get orders with $product and check their status via
			 * @see WC_Reepay_Subscription_Plan_Simple::is_subscription_active
			 *
			 * Who is current user?
			 */

			wc_get_orders(

			);
		}

		return $grants_access;
	}
}

if ( is_plugin_active( 'woocommerce-memberships/woocommerce-memberships.php' ) &&
     ! class_exists( 'WC_Subscription' ) ) {
	class WC_Subscription {

	}
}
