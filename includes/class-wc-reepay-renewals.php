<?php

/**
 * Class WC_Reepay_Renewals
 *
 * @since 1.0.0
 */
class WC_Reepay_Renewals {
	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'reepay_webhook', [ $this, 'create_subscriptions' ] );

		add_action( 'reepay_webhook_raw_event_subscription_renewal', [ $this, 'renew_subscription' ] );
		add_action( 'reepay_webhook_raw_event_subscription_on_hold', [ $this, 'hold_subscription' ] );
		add_action( 'reepay_webhook_raw_event_subscription_cancelled', [ $this, 'cancel_subscription' ] );
		add_action( 'reepay_webhook_raw_event_subscription_uncancelled', [ $this, 'uncancel_subscription' ] );
	}

	/**
	 *
	 * @param  array[
	 *     'id' => string
	 *     'timestamp' => string
	 *     'signature' => string
	 *     'invoice' => string
	 *     'customer' => string
	 *     'transaction' => string
	 *     'event_type' => string
	 *     'event_id' => string
	 * ] $data
	 */
	public function create_subscriptions( $data ) {
		if( $data['event_type'] !== 'invoice_authorized' && $data['event_type'] !== 'invoice_settled' ) {
			return;
		}

		$main_order = rp_get_order_by_handle( $data['invoice'] );

		if ( empty($main_order)  || ! empty( $main_order->get_meta( '_reepay_subscription_handle' ) ) ) {
			return;
		}

		$data['order_id'] = $main_order->get_id();

		$token = self::get_payment_token_order( $main_order );

		if ( empty( $token ) ) {
			self::log( [
				'log'    => [
					'source' => 'WC_Reepay_Renewals::create_subscription',
					'error'  => 'Empty token',
					'data'   => $data
				],
				'notice' => "Subscription {$data['order_id']} has no payment token"
			] );

			return;
		}

		$token = $token->get_token();
		
		$orders = [ $main_order ];
		$order_items = $main_order->get_items();

		foreach ( $order_items as $order_item_key => $order_item ) {
			if ( count( $order_items ) <= 1 ) {
				break;
			}

			$main_order->remove_item( $order_item_key );
			unset( $order_items[ $order_item_key ] );

			$orders[] = self::create_order_copy( [
				'status'      => $main_order->get_status( '' ),
				'customer_id' => $main_order->get_customer_id(),
			], $main_order, [ $order_item ] );
		}

		$main_order->calculate_totals();

		foreach ( $orders as $order ) {
			$order_items = $order->get_items();
			$order_item  = reset( $order_items );

			$product = $order_item->get_product();

			$handle = 'subscription_handle_' . $order->get_id() . '_' . $product->get_id();

			$addons = array_merge( self::get_shipping_addons( $order ), $order_item->get_meta( 'addons' )?:[] );

			$new_subscription = null;
			try {
				/**
				 * @see https://reference.reepay.com/api/#create-subscription
				 */
                $sub_data = [
                    'customer'        => $data['customer'],
                    'plan'            => $product->get_meta( '_reepay_subscription_handle' ),
//					'amount' => null,
                    'quantity'        => $order_item->get_quantity(),
                    'test'            => WooCommerce_Reepay_Subscriptions::settings( 'test_mode' ),
                    'handle'          => $handle,
//					'metadata' => null,
                    'source'          => $token,
//					'create_customer' => null,
//					'plan_version'    => null,
                    'amount_incl_vat' => wc_prices_include_tax(),
//					'generate_handle' => null,
//					'start_date' => null,
//					'end_date' => null,
                    'grace_duration'  => 172800,
//					'no_trial' => null,
//					'no_setup_fee' => null,
//					'trial_period' => null,
//					'subscription_discounts' => null,
                    'coupon_codes'    => self::get_reepay_coupons( $order ),
//					'additional_costs' => null,
                    'signup_method'   => 'source',
                ];

                if(!empty($addons)){
                    $sub_data['add_ons'] = $addons;
                }

				$new_subscription = reepay_s()->api()->request( 'subscription', 'POST', $sub_data );
			}catch( Exception $e ) {
				self::log( [
					'notice' => $e->getMessage()
				] );
			}

			if ( empty( $new_subscription ) ) {
				self::log( [
					'log'    => [
						'source' => 'WC_Reepay_Renewals::create_subscription',
						'error'  => 'create-subscription',
						'data'   => $data,
						'plan'   => $product->get_meta( '_reepay_subscription_handle' )
					],
					'notice' => "Subscription {$data['order_id']} - unable to create subscription"
				] );

				continue;
			}

			try {
				/**
				 * @see https://reference.reepay.com/api/#set-payment-method
				 */
				$payment_method = reepay_s()->api()->request( "subscription/{$new_subscription['handle']}/pm", 'POST', [
					'handle' => $new_subscription['handle'],
					'source' => $token,
				] );
			}catch( Exception $e ) {
				self::log( [
					'notice' => $e->getMessage()
				] );
			}

			if ( empty( $payment_method ) ) {
				self::log( [
					'log'    => [
						'source' => 'WC_Reepay_Renewals::create_subscription',
						'error'  => 'set-payment-method',
						'data'   => $data
					],
					'notice' => "Subscription {$data['order_id']} - unable to assign payment method to subscription"
				] );

				continue;
			}

			$order->add_meta_data( '_reepay_subscription_handle', $handle );
			$order->save();
		}
	}

	/**
	 *
	 * @param  array[
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
	public function renew_subscription( $data ) {
		self::create_child_order( $data, 'wc-completed' );
	}

	/**
	 *
	 * @param  array[
	 *     'id' => string
	 *     'timestamp' => string
	 *     'signature' => string
	 *     'subscription' => string
	 *     'customer' => string
	 *     'event_type' => string
	 *     'event_id' => string
	 * ] $data
	 */
	public function hold_subscription( $data ) {
		self::create_child_order( $data, 'wc-on-hold' );
	}

	/**
	 *
	 * @param  array[
	 *     'id' => string
	 *     'timestamp' => string
	 *     'signature' => string
	 *     'subscription' => string
	 *     'customer' => string
	 *     'event_type' => string
	 *     'event_id' => string
	 * ] $data
	 */
	public function cancel_subscription( $data ) {
		self::create_child_order( $data, 'wc-cancelled' );
	}

	/**
	 *
	 * @param  array[
	 *     'id' => string
	 *     'timestamp' => string
	 *     'signature' => string
	 *     'subscription' => string
	 *     'customer' => string
	 *     'event_type' => string
	 *     'event_id' => string
	 * ] $data
	 */
	public function uncancel_subscription( $data ) {
		self::create_child_order( $data, 'wc-completed' );
	}

	/**
	 * Get payment token.
	 *
	 * @param  WC_Order  $order
	 *
	 * @return WC_Payment_Token_Reepay|false
	 */
	public static function get_payment_token_order( WC_Order $order ) {
		$token = $order->get_meta( '_reepay_token' );
		if ( empty( $token ) ) {
			return false;
		}

		return self::get_payment_token( $token );
	}

	/**
	 * Get Payment Token by Token string.
	 *
	 * @param  string  $token
	 *
	 * @return null|bool|WC_Payment_Token
	 */
	public static function get_payment_token( $token ) {
		global $wpdb;

		$query    = "SELECT token_id FROM {$wpdb->prefix}woocommerce_payment_tokens WHERE token = '%s';";
		$token_id = $wpdb->get_var( $wpdb->prepare( $query, $token ) );
		if ( ! $token_id ) {
			return false;
		}

		return WC_Payment_Tokens::get( $token_id );
	}

	/**
	 * @param  string  $handle
	 *
	 * @return bool|WC_Order|WC_Order_Refund
	 */
	public static function get_order_by_subscription_handle( $handle ) {
		// $handle - "subscription_handle_<order_id>_<product_id>"
		$parts = explode( '_', $handle );

		return wc_get_order( (int) $parts[2] );
	}

	/**
	 * @param  array<string, string>  $data
	 * @param  string  $status
	 *
	 * @return WC_Order|WP_Error
	 */
	public static function create_child_order( $data, $status ) {
		$parent_order = self::get_order_by_subscription_handle( $data['subscription'] );

		if ( empty( $parent_order ) ) {
			return new WP_Error( 'Undefined parent order' );
		}

		$query = new WP_Query( array(
			'post_parent'    => $parent_order->get_id(),
			'post_type'      => 'shop_order',
			'post_status'    => 'any',
			'orderby'        => 'ID',
			'posts_per_page' => 1,
			'offset'         => 0,
		) );

		if ( ! empty( $query->posts ) && $query->posts[0]->post_status === $status ) {
			self::log( [
				'log'    => [
					'source' => 'WC_Reepay_Renewals::create_child_order',
					'error'  => 'duplicate status - ' . $status,
				],
				'notice' => "Subscription {$data['subscription']} - duplication attempt"
			] );

			return new WP_Error( 'Duplicate order' );
		}

		self::log( [
			'log' => [
				'source' => 'WC_Reepay_Renewals::create_child_order',
				'data'   => $data,
			]
		] );

		return self::create_order_copy( [
			'status'      => $status,
			'parent'      => $parent_order->get_id(),
			'customer_id' => $parent_order->get_customer_id(),
		], $parent_order, $parent_order->get_items() );
	}

	/**
	 * @param  array  $order_args  Order arguments.
	 * @param  WC_Order  $main_order  Order arguments.
	 * @param  array<WC_Order_Item>  $items
	 *
	 * @return WC_Order|WP_Error
	 */
	public static function create_order_copy( $order_args, $main_order, $items = [] ) {
		$new_order = wc_create_order( $order_args );
		$new_order->save();

		$main_order = wc_get_order($main_order);

		$fields_to_copy = [
			'_order_shipping',
			'_order_discount',
			'_cart_discount',
			'_order_tax',
			'_order_shipping_tax',

			'_order_total',
			'_order_key',
			'_customer_user',
			'_order_currency',
			'_prices_include_tax',
			'_customer_ip_address',
			'_customer_user_agent',

			'_billing_city',
			'_billing_state',
			'_billing_postcode',
			'_billing_email',
			'_billing_phone',
			'_billing_address_1',
			'_billing_address_2',
			'_billing_country',
			'_billing_first_name',
			'_billing_last_name',
			'_billing_company',

			'_shipping_country',
			'_shipping_first_name',
			'_shipping_last_name',
			'_shipping_company',
			'_shipping_address_1',
			'_shipping_address_2',
			'_shipping_city',
			'_shipping_state',
			'_shipping_postcode',

			'_payment_method',
			'_payment_method_title',

			'_reepay_order',
			'_reepay_state_authorized',
			'_reepay_token_id',
			'_reepay_token',
		];

		foreach ( $fields_to_copy as $field_name ) {
			update_post_meta( $new_order->get_id(), $field_name, get_post_meta( $main_order->get_id(), $field_name, true ) );
		}

		foreach ( $items as $item ) {
			$new_order->add_product( wc_get_product( $item['product_id'] ), $item['qty'] );
		}

		$new_order->save();
		$new_order->calculate_totals();

		return $new_order;
	}

	/**
	 * @param  WC_Order  $order
	 *
	 *
	 * @return array<string>
	 */
	public static function get_reepay_coupons( $order ) {
		$coupons = [];

		foreach ( $order->get_coupon_codes() as $coupon_code ) {
			$c = new WC_Coupon( $coupon_code );

			if ( $c->is_type( 'reepay_fixed_product' ) || $c->is_type( 'reepay_percentage' ) ) {
				$coupons[] = $coupon_code;
			}
		}

		return $coupons;
	}

	/**
	 * @param  WC_Order  $order
	 *
	 * @return array
	 */
	public static function get_shipping_addons( $order ) {
		$methods = $order->get_shipping_methods();

		if ( empty( $methods ) ) {
			return [];
		}

		$shm      = array_shift( $methods );
		$shm_data = get_option( 'woocommerce_' . $shm->get_method_id() . '_' . $shm->get_instance_id() . '_settings' );
        self::log( [
            'log'    => [
                'source' => 'WC_Reepay_Renewals::addons-shipping',
                'data'  => $shm_data,
            ],
        ] );
		if ( empty( $shm_data ) || empty($shm_data['reepay_shipping_addon_name']) ) {
			return [];
		}

		return [
			[
				'name'          => $shm_data['reepay_shipping_addon_name'],
				'description'   => $shm_data['reepay_shipping_addon_description'],
				'type'          => 'on_off',
				'fixed_amount ' => true,
				'amount'        => $shm_data['reepay_shipping_addon_amount'],
				'vat'           => $shm_data['reepay_shipping_addon_vat'],
				'vat_type'      => $shm_data['reepay_shipping_addon_vat_type'],
				'handle'        => $shm_data['reepay_shipping_addon'],
				'exist'         => $shm_data['reepay_shipping_addon'],
				'add_on'        => $shm_data['reepay_shipping_addon'],
			]
		];
	}

	/**
	 * @param  array  $data
	 */
	public static function log( $data ) {
		if ( ! empty( $data['log'] ) ) {
			reepay_s()->log()->log( $data['log'], 'error' );
		}

		if ( ! empty( $data['notice'] ) ) {
			WC_Reepay_Subscription_Admin_Notice::add_notice( $data['notice'] );
		}
	}
}

new WC_Reepay_Renewals();
