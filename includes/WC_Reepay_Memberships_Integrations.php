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

//			add_action( 'wc_memberships_grant_membership_access_from_purchase', array( $this, 'save_reepay_subscription_data' ), 10, 2 );
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
	 * Saves related subscription data when a membership access is granted via a purchase.
	 *
	 * Sets the start date if it has an installment plan.
	 * Sets the end date to match subscription end date.
	 *
	 * @see WC_Memberships_Membership_Plan::grant_access_from_purchase - action fires at the end of this function
	 * @see WC_Memberships_Integration_Subscriptions_Membership_Plans::save_subscription_data - action in Woo Mems
	 *
	 * @param WC_Memberships_Membership_Plan $plan
	 * @param array $args [
	 * 			'user_id'            => int
	 * 		    'product_id'         => int,
	 * 		    'order_id'           => int,
	 * 		    'user_membership_id' => int,
	 * ]
	 */
//	public function save_reepay_subscription_data( $plan, $args ) {
//		$product     = wc_get_product( $args['product_id'] );
//		$integration = wc_memberships()->get_integrations_instance()->get_subscriptions_instance();
//
//		__log(['!r1!', $plan, $args]);
//		__log(['!r11!', !empty($product), !empty($integration), WC_Reepay_Checkout::is_reepay_product( $product ),
//			      $this->has_membership_plan_reepay_subscription( $plan )]);
//
//		if (    $product
//		        && $integration
//		        && WC_Reepay_Checkout::is_reepay_product( $product )
//		        && $this->has_membership_plan_reepay_subscription( $plan ) ) {
//
//			$order = wc_get_order($args['order_id']);
//			$reepay_subscription_id = get_post_meta( $args['product_id'], '_reepay_subscription_handle', true );
//			__log(['!r2!', $reepay_subscription_id]);
//			$reepay_subscription = reepay_s()->api()->request("subscription/$reepay_subscription_id");
//			__log(['!r22!', $reepay_subscription]);
//			$subscription_membership = new WC_Memberships_Integration_Subscriptions_User_Membership( $args['user_membership_id'] );
//
//			$subscription_membership->set_subscription_id( $order->get_id() );
//
//			$subscription_plan = new WC_Memberships_Integration_Subscriptions_Membership_Plan( $subscription_membership->get_plan_id() );
//			__log(['!r3!', $subscription_membership->has_installment_plan(), $subscription_plan->get_access_start_date( 'mysql' )]);
//			// adjust the start date for installment plans (might not be now for fixed date plans)
//			if ( $subscription_membership->has_installment_plan() ) {
//				$subscription_membership->set_start_date( $subscription_plan->get_access_start_date( 'mysql' ) );
//			}
//
//			// end date: subscription length (unlimited or fixed by the subscription product)
//			if ( 'subscription' === $subscription_plan->get_access_length_type() &&
//			     apply_filters( 'wc_memberships_plan_grants_access_while_subscription_active', true, $plan->get_id() ) ) {
//				//ToDo get from reepay_subscription
////				$membership_end_date = $integration->get_subscription_event_date( $subscription, 'end' );
//
//				// end date: likely an installment plan, so it could be relative to the start date or be on a fixed date
//			} else {
//				$membership_end_date = $subscription_plan->get_expiration_date( current_time( 'mysql', true ), $args );
//			}
//
//			__log('!r4!', $subscription_plan->get_access_length_type(), $membership_end_date );
//
//			// set the determined end date for the subscription membership
//			$subscription_membership->set_end_date( $membership_end_date );
//
//			// maybe update the trial end date
////				if ( $trial_end_date = $integration->get_subscription_event_date( $subscription, 'trial_end' ) ) {
////					$subscription_membership->set_free_trial_end_date( $trial_end_date );
////				}
//
//			__log('!r5!');
//		}
//	}

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
