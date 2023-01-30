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

				add_action( 'reepay_subscriptions_orders_created', [ $this, 'maybe_activate_membership' ] );

				add_filter( 'wc_memberships_access_granting_purchased_product_id', [ $this, 'disable_default_membership_activation_for_reepay_products' ], 100, 3 );
				add_filter( 'wc_memberships_grant_access_from_new_purchase', [ $this, 'disable_default_membership_activation_for_reepay_products_new_purchase' ], 100, 2 );
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
		 * @see WC_Memberships_Membership_Plans::grant_access_to_membership_from_order as example of memberships activation
		 *
		 * @param  WC_Order[]  $orders orders with one reepay subscription in each one
		 */
		public function maybe_activate_membership( $orders ) {
			$membership_plans = wc_memberships()->get_plans_instance()->get_membership_plans();
			__log(
				[
					'source' => 'maybe_activate_membership !1!',
					$membership_plans
				]
			);
			if ( empty( $membership_plans ) ) {
				return;
			}

			foreach ( $orders as $order ) {
				$order = wc_get_order($order);

				__log(
					[
						'source' => 'maybe_activate_membership !2!',
						$order
					]
				);

				if ( empty( $order ) || ! WC_Reepay_Renewals::is_order_contain_subscription( $order )) {
					continue;
				}

				$order_items = $order->get_items();
				$user_id     = $order->get_user_id();

				__log(
					[
						'source' => 'maybe_activate_membership !3!',
						$order_items,
						$user_id
					]
				);

				if ( empty( $order_items ) || empty( $user_id ) ) {
					continue;
				}

				foreach ( $membership_plans as $plan ) {
					__log(
						[
							'source' => 'maybe_activate_membership !4!',
							$plan
						]
					);
					if ( ! $plan->has_products() ) {
						continue;
					}

					remove_filter( 'wc_memberships_access_granting_purchased_product_id', [ $this, 'disable_default_membership_activation_for_reepay_products' ], 100 );
					$access_granting_product_ids = wc_memberships_get_order_access_granting_product_ids( $plan, $order, $order_items );
					__log(
						[
							'source' => 'maybe_activate_membership !5!',
							$access_granting_product_ids
						]
					);
					foreach ( $access_granting_product_ids as $product_id ) { //TODO remove this loop
						__log(
							[
								'source' => 'maybe_activate_membership !6!',
								$product_id
							]
						);
						if ( ! $plan->has_product( $product_id ) ) {
							continue;
						}
						__log(
							[
								'source' => 'maybe_activate_membership !7!',
								$product_id,
								WC_Reepay_Renewals::is_order_subscription_active( $order )
							]
						);
						if ( WC_Reepay_Renewals::is_order_subscription_active( $order ) ) {
							$res = $plan->grant_access_from_purchase( $user_id, $product_id, $order->get_id() );
							__log(
								[
									'source' => 'maybe_activate_membership !8!',
									$res ? 'true' : 'false'
								]
							);
						}
					}
				}
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
		 * Confirm grant access from new purchase to paid plan.
		 *
		 * @see WC_Memberships_Membership_Plans::grant_access_to_membership_from_order
		 *
		 * @param bool $grant_access by default true unless the order already granted access to the plan
		 * @param array $args {
		 *      @type int $user_id customer id for purchase order
		 *      @type int $product_id ID of product that grants access
		 *      @type int $order_id order ID containing the product
		 * }
		 */
		public function disable_default_membership_activation_for_reepay_products_new_purchase( $grant_access, $args ) {
			if ( WC_Reepay_Checkout::is_reepay_product( $args['product_id'] ) ) {
				$grant_access = false;
			}

			return $grant_access;
		}
	}
}

if ( is_plugin_active( 'woocommerce-memberships/woocommerce-memberships.php' )
     && ! is_plugin_active( 'woocommerce-subscriptions/woocommerce-subscriptions.php' )
     && ! class_exists( 'WC_Subscription' )
) {
	class WC_Subscription extends WC_Order {
		public $fake = true;
	}
}

if(!function_exists('__log')) {
	function __log($data, ...$more_data) {
		if($more_data) {
			$data = [
				'$data' => $data,
				'$more_data' => $more_data
			];
		}
		error_log( print_r( $data, true ) );
	}
}
