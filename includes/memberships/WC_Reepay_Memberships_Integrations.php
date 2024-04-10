<?php

if ( ! class_exists( 'WC_Reepay_Memberships_Integrations' ) ) {
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

				add_filter( 'wc_memberships_access_granting_purchased_product_id', [ $this, 'disable_default_membership_activation_for_reepay_products' ], 100, 3 );

				add_action( 'reepay_subscriptions_orders_created', [ $this, 'activate_memberships' ] );

				add_action( 'reepay_webhook_invoice_created', [ $this, 'renew_membership' ] );
				add_action( 'reepay_webhook_raw_event_subscription_renewal', [ $this, 'renew_membership' ] );
				add_action( 'reepay_webhook_raw_event_subscription_on_hold', [ $this, 'hold_membership' ] );
				add_action( 'reepay_webhook_raw_event_subscription_cancelled', [ $this, 'cancel_membership' ] );
				add_action( 'reepay_webhook_raw_event_subscription_expired', [ $this, 'cancel_membership' ] );
				add_action( 'reepay_webhook_raw_event_subscription_uncancelled', [ $this, 'renew_membership' ] );
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
			if ( is_admin() ) {
				$is_subscription = $is_subscription || WC_Reepay_Checkout::is_reepay_product( $product );
			}

			return $is_subscription;
		}

		/**
		 * @param  int|int[]                       $product_ids                  Product id or array of product ids that grant access (may be non unique)
		 * @param  int[]                           $access_granting_product_ids  Array of product ids that can grant access to this plan
		 * @param  WC_Memberships_Membership_Plan  $plan                         Membership plan access will be granted to
		 */
		public function disable_default_membership_activation_for_reepay_products( $product_ids, $access_granting_product_ids, $plan ) {
			if ( ! is_array( $product_ids ) ) {
				$product_ids = [ $product_ids ];
			}

			$filtered_product_ids = [];

			foreach ( $product_ids as $product_id ) {
				if ( ! WC_Reepay_Checkout::is_reepay_product( $product_id ) ) {
					$filtered_product_ids[] = $product_id;
				}
			}

			return $filtered_product_ids;
		}

		/**
		 * @see WC_Memberships_Membership_Plans::grant_access_to_membership_from_order as example of memberships activation
		 *
		 * @param  WC_Order[]  $orders orders with one reepay subscription in each one
		 */
		public function activate_memberships( $orders ) {
			remove_filter( 'wc_memberships_access_granting_purchased_product_id', [ $this, 'disable_default_membership_activation_for_reepay_products' ], 100 );

			$membership_plans = wc_memberships()->get_plans_instance()->get_membership_plans();

			if ( empty( $membership_plans ) ) {
				return;
			}

			foreach ( $orders as $order ) {
				$order = wc_get_order($order);

				if ( empty( $order ) ||
				     ! WC_Reepay_Renewals::is_order_contain_subscription_in_products( $order ) ||
				     ! WC_Reepay_Renewals::is_order_subscription_active( $order ) ) {
					continue;
				}

				$order_items = $order->get_items();
				$user_id     = $order->get_user_id();

				if ( empty( $order_items ) || empty( $user_id ) ) {
					continue;
				}

				foreach ( $membership_plans as $plan ) {
					if ( ! $plan->has_products() ) {
						continue;
					}

					$access_granting_product_ids = wc_memberships_get_order_access_granting_product_ids( $plan, $order, $order_items );

					foreach ( $access_granting_product_ids as $product_id ) {
						if ( ! $plan->has_product( $product_id ) ) {
							continue;
						}

						$created_membership_id = $plan->grant_access_from_purchase( $user_id, $product_id, $order->get_id() );

						if ( is_null( $created_membership_id ) ) {
							continue;
						}

						$order->add_meta_data( '_reepay_membership_id', $created_membership_id, true );
						$order->save();
					}
				}
			}
		}

		/**
		 *
		 * @param array[
		 *     'id' => string
		 *     'timestamp' => string
		 *     'signature' => string
		 *     'invoice' => string
		 *     'subscription' => string
		 *     'customer' => string
		 *     'event_type' => string
		 *     'event_id' => string
		 * ] $data
		 */
		public function renew_membership( $data ) {
			/**
			 * @var WC_Memberships_User_Membership $membership
			 * @var array                          $subscription
			 */
			[ 'membership' => $membership, 'subscription' => $subscription ] = self::get_membership_info( $data['subscription'] );

			if ( is_null( $membership ) ) {
				return;
			}

			$membership->activate_membership();

			$plan = new WC_Memberships_Integration_Subscriptions_Membership_Plan( $membership->get_plan()->get_id() );

			$access_start_date = $plan->get_access_start_date( 'timestamp' );

			if ( $plan->is_access_length_type( 'subscription' ) ) {
				$membership->set_end_date(
					strtotime( $subscription['end_date'] ?? $subscription['next_period_start'] ) ?: ''
				);
			} else {
				$access_length_in_seconds = $plan->get_access_length_in_seconds();

				if ( ! empty( $access_length_in_seconds ) ) {
					$membership->set_end_date( $access_start_date + $access_length_in_seconds );
				} else {
					$membership->set_end_date( $plan->get_access_end_date( 'timestamp' ) );
				}
			}

//			ToDo fix - $membership->get_subscription() - returns null for reepay subscriptions, because they are just orders
//			$membership = new WC_Memberships_Integration_Subscriptions_User_Membership( $membership->get_id() );
//			$membership->get_subscription()->update_dates(
//				[
//					'next_payment' => strtotime( $subscription['next_period_start'] ),
//				]
//			);
		}

		/**
		 *
		 * @param array[
		 *     'id' => string
		 *     'timestamp' => string
		 *     'signature' => string
		 *     'invoice' => string
		 *     'subscription' => string
		 *     'customer' => string
		 *     'event_type' => string
		 *     'event_id' => string
		 * ] $data
		 */
		public function hold_membership( $data ) {
			[ 'membership' => $membership, 'subscription' => $subscription  ] = self::get_membership_info( $data['subscription'] );

			if ( is_null( $membership ) ) {
				return;
			}

			$membership->pause_membership();
		}

		/**
		 *
		 * @param array[
		 *     'id' => string
		 *     'timestamp' => string
		 *     'signature' => string
		 *     'invoice' => string
		 *     'subscription' => string
		 *     'customer' => string
		 *     'event_type' => string
		 *     'event_id' => string
		 * ] $data
		 */
		public function cancel_membership( $data ) {
			[ 'membership' => $membership, 'subscription' => $subscription ] = self::get_membership_info( $data['subscription'] );

			if ( is_null( $membership ) ) {
				return;
			}

			$membership->cancel_membership();
		}

		/**
		 * @param  string  $handle
		 *
		 * @return array[
		 *     'order' => WC_Order|null
		 *     'membership' => WC_Memberships_User_Membership|null
		 *     'subscription' => array|null @see https://reference.reepay.com/api/#the-subscription-object
		 * ]
		 */
		public static function get_membership_info( $handle ) {
			$data = [
				'order'        => null,
				'membership'   => null,
				'subscription' => null,
			];

			$data['order'] = WC_Reepay_Renewals::get_order_by_subscription_handle( $handle );

			if ( empty( $data['order'] ) ) {
				return $data;
			}

			$membership_id = $data['order']->get_meta( '_reepay_membership_id' );

			if ( empty( $membership_id ) ) {
				return null;
			}

			$data['membership'] =  wc_memberships_get_user_membership( $membership_id );

			if ( is_null( $data['membership'] ) ) {
				return $data;
			}

			$handle = $data['order']->get_meta( '_reepay_subscription_handle' );

			if ( empty( $handle ) ) {
				return $data;
			}

			try {
				$data['subscription'] = reepay_s()->api()->request( "subscription/$handle" );
			} catch (Exception $e) {
				return $data;
			}

			return $data;
		}
	}
}

add_action( 'plugins_loaded', function () {
	if ( is_plugin_active( 'woocommerce-memberships/woocommerce-memberships.php' )
	     && ! is_plugin_active( 'woocommerce-subscriptions/woocommerce-subscriptions.php' )
	     && ! class_exists( 'WC_Subscription' )
//		     && class_exists( 'WC_Order' )
	) {
		class WC_Subscription extends WC_Order {
			public $fake = true;
		}
	}
}, 5
);

