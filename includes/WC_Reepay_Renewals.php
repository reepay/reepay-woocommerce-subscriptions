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
		add_action( 'reepay_webhook', [ $this, 'create_subscriptions_handle' ] );
		add_action( 'reepay_create_subscription', [ $this, 'create_subscriptions' ], 10, 2 );

		add_action( 'reepay_webhook_invoice_created', [ $this, 'renew_subscription' ], );
		add_action( 'reepay_webhook_raw_event_subscription_renewal', [ $this, 'renew_subscription' ] );
		add_action( 'reepay_webhook_raw_event_subscription_on_hold', [ $this, 'hold_subscription' ] );
		add_action( 'reepay_webhook_raw_event_subscription_cancelled', [ $this, 'cancel_subscription' ] );
		add_action( 'reepay_webhook_raw_event_subscription_uncancelled', [ $this, 'uncancel_subscription' ] );

		add_action( 'woocommerce_order_status_changed', array( $this, 'status_manual_start_date' ), 10, 4 );

		add_filter( 'woocommerce_available_payment_gateways', array( $this, 'get_available_payment_gateways' ) );

		add_filter( 'woocommerce_get_formatted_order_total', array( $this, 'display_real_total' ), 10, 4 );

		add_filter( 'reepay_settled_order_status', array( $this, 'reepay_subscriptions_order_status' ), 11, 2 );

		add_filter( 'show_reepay_metabox', array( $this, 'disable_for_sub' ), 10, 2 );

		add_filter( 'order_contains_reepay_subscription', function ( $contains, $order ) {
			if ( $this->reepay_order_contains_subscription( $order ) ) {
				return true;
			}

			return $contains;
		}, 10, 2 );
	}

	/**
	 * @param WC_Order|integer $order
	 *
	 * @return bool
	 */
	function reepay_order_contains_subscription( $order ) {
		if ( is_int( $order ) ) {
			$order = wc_get_order( $order );
		}
		foreach ( $order->get_items() as $item ) {
			$product = $item->get_product();
			if ( $product->is_type( [ 'reepay_simple_subscriptions', 'reepay_variable_subscriptions' ] ) ) {
				return true;
			};
		}

		return false;
	}

	function reepay_cart_subscription_count() {
		$count = 0;
		if ( ! empty( WC()->cart->cart_contents ) ) {
			foreach ( WC()->cart->cart_contents as $cart_item ) {
				/**
				 * @var WC_Product $product
				 */
				$product = $cart_item['data'];
				if ( $product->is_type( 'reepay_simple_subscriptions', 'reepay_variable_subscriptions' ) ) {
					$count += $cart_item['quantity'];
				}
			}
		}

		return $count;
	}

	/**
	 * Display the gateways which support subscriptions if manual payments are not allowed.
	 *
	 * @since 1.0
	 */
	public function get_available_payment_gateways( $available_gateways ) {
		if ( is_wc_endpoint_url( 'order-pay' ) ) {
			return $available_gateways;
		}

		$subscriptions_in_cart = $this->reepay_cart_subscription_count();

		//filter reepay type subscriptions
		if ( empty( $subscriptions_in_cart ) && ( ! isset( $_GET['order_id'] ) || ! $this->reepay_order_contains_subscription( $_GET['order_id'] ) ) ) {
			return $available_gateways;
		}

		foreach ( $available_gateways as $gateway_id => $gateway ) {

			$supports_subscriptions = $gateway->supports( 'subscriptions' );

			// Remove the payment gateway if there are multiple subscriptions in the cart and this gateway either doesn't support multiple subscriptions or isn't manual (all manual gateways support multiple subscriptions)
			if ( $subscriptions_in_cart > 1 && $gateway->supports( 'multiple_subscriptions' ) !== true && $supports_subscriptions ) {
				unset( $available_gateways[ $gateway_id ] );

				// If there is just the one subscription the cart, remove the payment gateway if manual renewals are disabled and this gateway doesn't support automatic payments
			} elseif ( ! $supports_subscriptions ) {
				unset( $available_gateways[ $gateway_id ] );
			}
		}

		return $available_gateways;
	}

	public function disable_for_sub( $is_able, $order ) {

		if ( ! empty( $order->get_meta( '_reepay_subscription_handle' ) ) ) {
			return false;
		}

		return $is_able;
	}

	public function reepay_subscriptions_order_status( $status, $order ) {
		if ( reepay_s()->settings( '_reepay_manual_start_date' ) && 'wc-' . $order->get_status() == reepay_s()->settings( '_reepay_manual_start_date_status' ) ) {
			$status = reepay_s()->settings( '_reepay_manual_start_date_status' );
		} elseif ( self::is_order_contain_subscription( $order ) ) {
			$status = reepay_s()->settings( '_reepay_orders_default_subscription_status' );
		}


		return $status;
	}

	public function status_manual_start_date( $order_id, $this_status_transition_from, $this_status_transition_to, $instance ) {
		$order          = wc_get_order( $order_id );
		$payment_method = $order->get_payment_method();

		if ( strpos( $payment_method, 'reepay' ) === false ) {
			return;
		}

		$is_started = $order->get_meta( '_reepay_subscription_period_started' );

		if ( 'wc-' . $this_status_transition_to == reepay_s()->settings( '_reepay_manual_start_date_status' ) &&
		     reepay_s()->settings( '_reepay_manual_start_date' ) &&
		     self::is_order_contain_subscription( $order ) && empty( $is_started ) ) {

			$sub_meta = $order->get_meta( '_reepay_subscription_handle' );

			if ( ! empty( $sub_meta ) ) {
				$params['next_period_start'] = date( 'Y-m-d\TH:i:s', strtotime( current_time( 'Y-m-d\TH:i:s' ) . "+60 seconds" ) );

				try {
					reepay_s()->api()->request( "subscription/{$sub_meta}/change_next_period_start", 'POST', $params );
					update_post_meta( $order_id, '_reepay_subscription_period_started', true );
				} catch ( Exception $e ) {
					self::log( [
						'log' => [
							'source'    => 'WC_Reepay_Renewals::status_manual_start_date',
							'error'     => $e->getMessage(),
							'$order_id' => $order_id,
						]
					] );

					$notice = sprintf(
						__( 'Unable to change subscription period to %s. Error from acquire: %s', 'reepay-subscriptions-for-woocommerce' ),
						$params['next_period_start'],
						$e->getMessage()
					);

					$order->add_order_note( $notice );
					WC_Reepay_Subscription_Admin_Notice::add_frontend_notice( $notice , $order->get_id() );
				}

			}
		}

		if ( floatval( $order->get_total() ) != 0 && self::is_order_contain_subscription( $order ) ) {
			$new_total = 0;
			$order->set_total( $new_total );
			$order->save();
		}
	}

	public function display_real_total( $formatted_total, $order, $tax_display, $display_refunded ) {
		if ( self::is_order_contain_subscription( $order ) && floatval( $order->get_total() ) == 0 && ! is_admin() ) {
			$real_total = get_post_meta( $order->get_id(), '_real_total', true );
			if ( ! empty( $real_total ) ) {
				return wc_price( $real_total );
			}

		}

		return $formatted_total;
	}

	/**
	 *
	 * @param array[
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
	public function create_subscriptions_handle( $data ) {
		if ( $data['event_type'] == 'invoice_authorized' || $data['event_type'] == 'invoice_settled' ) {
			$order = rp_get_order_by_handle( $data['invoice'] );
		} elseif ( $data['event_type'] == 'customer_payment_method_added' ) {
			$order = rp_get_order_by_session( $data['payment_method_reference'] );
		} else {
			return;
		}

		self::log( [
			'log' => [
				'source'   => 'WC_Reepay_Renewals::create_subscription',
				'error'    => 'Subscription create request',
				'data'     => $data,
				'order_id' => empty( $order ) ? 'false' : $order->get_id()
			],
		] );


		if ( empty( $order ) ) {
			self::log( [
				'log' => [
					'source' => 'WC_Reepay_Renewals::create_subscription',
					'error'  => 'Order not found',
					'data'   => $data
				],
			] );

			return;
		}

		$child_order = $this->get_child_order( $order, $data['invoice'] );

		self::log( [
			'log' => [
				'source' => 'WC_Reepay_Renewals::child_order',
				'error'  => '',
				'data'   => $child_order
			],
		] );

		if ( ! empty( $child_order ) && $child_order->get_status() == 'wc-failed' && $data['event_type'] == 'invoice_settled' ) {
			$child_order->set_status( reepay_s()->settings( '_reepay_suborders_default_renew_status' ) );
			$child_order->save();
		}

		if ( ! empty( $order->get_meta( '_reepay_subscription_handle' ) ) ) {
			self::log( [
				'log' => [
					'source' => 'WC_Reepay_Renewals::create_subscription',
					'error'  => 'Subscription allready exist',
					'data'   => $data
				],
			] );

			return;
		}

		if ( ! self::is_order_contain_subscription( $order ) ) {
			self::log( [
				'log' => [
					'source' => 'WC_Reepay_Renewals::create_subscription',
					'error'  => 'Order not contain subscription',
					'data'   => $data
				],
			] );

			return;
		}

		if ( self::is_locked( $order->get_id() ) ) {
			return;
		}

		self::lock_order( $order->get_id() );

		$this->create_subscriptions( $data, $order );

		self::unlock_order( $order->get_id() );
	}

	public static function is_order_contain_subscription( $order ) {
		foreach ( $order->get_items() as $item_values ) {
			$product = $item_values->get_product();

			//Imported subscriptions are empty
			if ( empty( $product ) ) {
				continue;
			}

			if ( $product->is_type( 'reepay_variable_subscriptions' ) || $product->is_type( 'reepay_simple_subscriptions' ) ) {
				return true;
			}

			if ( $product->is_type( 'variation' ) && ! empty( $product->get_parent_id() ) ) {
				$_product_main = wc_get_product( $product->get_parent_id() );
				if ( $_product_main->is_type( 'reepay_variable_subscriptions' ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 *
	 * @param array[
	 *     'id' => string
	 *     'timestamp' => string
	 *     'signature' => string
	 *     'invoice' => string
	 *     'customer' => string
	 *     'transaction' => string
	 *     'event_type' => string
	 *     'event_id' => string
	 * ] $data
	 *
	 * @param WC_Order $main_order
	 */
	public function create_subscriptions( $data, $main_order ) {
		$data['order_id'] = $main_order->get_id();

		if ( ! empty( $data['payment_method'] ) ) {
			$token = $data['payment_method'];
		} else {
			$token = self::get_payment_token_order( $main_order );
		}

		if ( empty( $token ) ) {
			self::log( [
				'log'    => [
					'source' => 'WC_Reepay_Renewals::create_subscription',
					'error'  => 'Empty token',
					'data'   => $data
				],
				'notice' => sprintf(
					__( 'Subscription %d has no payment token', 'reepay-subscriptions-for-woocommerce' ),
					(string) $main_order->get_id()
				)
			] );

			$main_order->add_order_note( __( "Unable to create subscription. Empty token", 'reepay-subscriptions-for-woocommerce' ) );

			return;
		}


		$orders         = [ $main_order ];
		$order_items    = $main_order->get_items();
		$created_orders = [ $main_order->get_id() ];
		foreach ( $order_items as $order_item_key => $order_item ) {
			if ( count( $order_items ) <= 1 ) {
				break;
			}

			//Get the WC_Product object
			$product = $order_item->get_product();

			if ( ! WC_Reepay_Checkout::is_reepay_product( $product ) ) {
				continue;
			}

			$items_to_create = [ $order_item ];

			$fee = $product->get_meta( '_reepay_subscription_fee' );
			if ( ! empty( $fee ) && ! empty( $fee['enabled'] ) && $fee['enabled'] == 'yes' ) {
				foreach ( $main_order->get_items( 'fee' ) as $item_id => $item ) {
					if ( $product->get_name() . ' - ' . $fee["text"] === $item['name'] ) {
						$items_to_create[] = $item;
						$main_order->remove_item( $item_id );
					}
				}
			}

			$main_order->remove_item( $order_item_key );
			unset( $order_items[ $order_item_key ] );

			$created_order = self::create_order_copy( [
				'status'      => $main_order->get_status( '' ),
				'customer_id' => $main_order->get_customer_id(),
			], $main_order, $items_to_create );

			$orders[]         = $created_order;
			$created_orders[] = $created_order->get_id();
		}

		update_post_meta( $main_order->get_id(), '_reepay_another_orders', $created_orders );

		$main_order->calculate_totals();

		$created_reepay_orders = [];
		foreach ( $orders as $order ) {
			if ( ! self::is_order_contain_subscription( $order ) ) {
				continue;
			}

			if ( floatval( $order->get_total() ) != 0 ) {
				update_post_meta( $order->get_id(), '_real_total', $order->get_total() );
				$new_total = 0;
				$order->set_total( $new_total );
			}

			$order_items = $order->get_items();
			$order_item  = reset( $order_items );

			$product = $order_item->get_product();

			$handle = $order->get_id() . '_' . $product->get_id();

			$addons = array_merge( self::get_shipping_addons( $order ), self::get_plan_addons( $order_item ) ?: [] );

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
					'grace_duration'  => 172800,
//					'no_trial' => null,
//					'no_setup_fee' => null,
//					'trial_period' => null,
//					'subscription_discounts' => null,
					'coupon_codes'    => self::get_reepay_coupons( $order, $data['customer'] ),
//					'additional_costs' => null,
					'signup_method'   => 'source',
				];

				if ( WooCommerce_Reepay_Subscriptions::settings( '_reepay_manual_start_date' ) ) {
					$sub_data['start_date'] = date( 'Y-m-d\TH:i:s', strtotime( "+100 years" ) );
				}


				if ( ! empty( $addons ) ) {
					$sub_data['add_ons'] = array_unique( $addons );
				}

				if ( $main_order->get_id() !== $order->get_id() ) {
					$sub_data['subscription_discounts'] = self::get_reepay_discounts( $main_order, $handle );
				}

				$new_subscription = reepay_s()->api()->request( 'subscription', 'POST', $sub_data );
			} catch ( Exception $e ) {
				$notice = sprintf(
					__( 'Unable to create subscription. Error from acquire: %s', 'reepay-subscriptions-for-woocommerce' ),
					$e->getMessage()
				);

				self::log( [
					'notice' => $notice
				] );

				$order->add_order_note( $notice );
			}


			if ( empty( $new_subscription ) ) {
				self::log( [
					'log'    => [
						'source' => 'WC_Reepay_Renewals::create_subscription',
						'error'  => 'create-subscription',
						'data'   => $sub_data ?? 'empty',
						'plan'   => $product->get_meta( '_reepay_subscription_handle' )
					],
					'notice' => sprintf(
						__( "Subscription %s - unable to create subscription", 'reepay-subscriptions-for-woocommerce' ),
						$data['order_id']
					)
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
			} catch ( Exception $e ) {
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
					'notice' => sprintf(
						__( "Subscription %s - unable to assign payment method to subscription", 'reepay-subscriptions-for-woocommerce' ),
						$data['order_id']
					)
				] );

				continue;
			}

			if ( $order->get_status() != 'processing' ) {
				$order->update_status( 'processing' );
			}

			$order->add_meta_data( '_reepay_subscription_handle', $handle );
			$order->save();

			$created_reepay_orders[] = $order->get_id();
		}

		do_action('reepay_subscriptions_orders_created', $created_reepay_orders);
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
	public function renew_subscription( $data ) {
		$status_main = reepay_s()->settings( '_reepay_manual_start_date' ) ? reepay_s()->settings( '_reepay_manual_start_date_status' ) : reepay_s()->settings( '_reepay_orders_default_subscription_status' );

		self::update_subscription_status( $data, $status_main );
		self::create_child_order( $data, reepay_s()->settings( '_reepay_suborders_default_renew_status' ) );
		self::change_user_role( $data );
	}

	/**
	 *
	 * @param array[
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
		self::update_subscription_status( $data, 'wc-on-hold' );
	}

	/**
	 *
	 * @param array[
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
		self::update_subscription_status( $data, 'wc-cancelled' );
	}

	/**
	 *
	 * @param array[
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
		self::update_subscription_status( $data, 'wc-completed' );
	}

	/**
	 * Get payment token.
	 *
	 * @todo refactor with while cycle
	 *
	 * @param WC_Order $order
	 *
	 * @return WC_Payment_Token_Reepay|false
	 */
	public static function get_payment_token_order( WC_Order $order ) {
		$token = $order->get_meta( 'reepay_token' );
		if ( empty( $token ) ) {
			sleep( 2 );
			$token = $order->get_meta( '_reepay_token' );
			if ( empty( $token ) ) {
				sleep( 2 );
				$token = $order->get_meta( 'reepay_token' );
				if ( empty( $token ) ) {
					return false;
				}
			}

		}

		return $token;
	}

	/**
	 * Get Payment Token by Token string.
	 *
	 * @param string $token
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
	 * @param string $handle
	 *
	 * @return WC_Order|false
	 */
	public static function get_order_by_subscription_handle( $handle ) {
		$orders = wc_get_orders(
			[
				'limit'      => 1,
				'meta_key'   => '_reepay_order',
				'meta_value' => $handle,
			]
		);

		if ( ! empty( $orders ) ) {
			return current( $orders );
		}

		$orders = wc_get_orders(
			[
				'limit'      => 1,
				'meta_key'   => '_reepay_subscription_handle',
				'meta_value' => $handle,
			]
		);

		return current( $orders );
	}

	/**
	 * @param  mixed  $order
	 */
	public static function is_order_subscription_active( $order ) {
		$order = wc_get_order( $order );

		if ( empty( $order ) ) {
			return false;
		}

		$transient_name = 'reepay_subscription_status_' . $order->get_id();

		$maybe_is_actibe = get_transient( $transient_name );

		if ( ! empty( $maybe_is_actibe ) ) {
			return $maybe_is_actibe === '1';
		}

		$handle = $order->get_meta( '_reepay_subscription_handle' );

		if ( empty( $handle ) ) {
			return false;
		}

		try {
			$subscription = reepay_s()->api()->request( "subscription/$handle" );
		} catch (Exception $e) {
			return false;
		}

		$is_active = $subscription['state'] === 'active';

		set_transient($transient_name, $is_active ? '1' : '0', HOUR_IN_SECONDS);

		return $is_active;
	}

	/**
	 * @param WC_Order $parent_order
	 * @param string $invoice
	 *
	 * @return WC_Order|false
	 */
	public function get_child_order( $parent_order, $invoice ) {
		$query = new WP_Query( [
			'post_parent'    => $parent_order->get_id(),
			'post_type'      => 'shop_order',
			'post_status'    => 'any',
			'posts_per_page' => - 1,
			'meta_query'     => [
				[
					'key'   => '_reepay_order',
					'value' => $invoice,
				]
			]
		] );

		return ! empty( $query->posts ) ? wc_get_order( $query->posts[0] ) : false;
	}

	/**
	 * @param array<string, string> $data
	 * @param string $status
	 *
	 * @return WC_Order|WP_Error
	 */
	public static function create_child_order( $data, $status ) {
		self::log( [
			'log' => [
				'source'  => 'WC_Reepay_Renewals::create_child_order',
				'$data'   => $data,
				'$status' => $status
			]
		] );

		if ( empty( $data['subscription'] ) ) {
			return new WP_Error(  __( 'Undefined subscription handle', 'reepay-subscriptions-for-woocommerce' ) );
		}

		$parent_order = self::get_order_by_subscription_handle( $data['subscription'] );

		if ( empty( $parent_order ) ) {
			return new WP_Error(  __( 'Undefined parent order', 'reepay-subscriptions-for-woocommerce' ) );
		}

		$query = new WP_Query( [
			'post_parent'    => $parent_order->get_id(),
			'post_type'      => 'shop_order',
			'post_status'    => 'any',
			'posts_per_page' => - 1,
			'meta_query'     => [
				[
					'key'   => '_reepay_order',
					'value' => $data['invoice'],
				]
			]
		] );

		if ( ! empty( $query->posts ) ) {
			self::log( [
				'log' => [
					'source' => 'WC_Reepay_Renewals::create_child_order',
					'error'  => 'duplicate status - ' . $status,
					'data'   => $data
				]
			] );

			return new WP_Error(  __( 'Duplicate order', 'reepay-subscriptions-for-woocommerce' ) );
		}

		self::log( [
			'log' => [
				'source' => 'WC_Reepay_Renewals::create_child_order',
				'data'   => $data,
			]
		] );

		update_post_meta( $parent_order->get_id(), '_reepay_order', $data['invoice'] );

		$items = $parent_order->get_items();

		if ( ! empty( $parent_order->get_items( 'fee' ) ) ) {
			foreach ( $parent_order->get_items( 'fee' ) as $fee ) {
				$items[] = $fee;
			}
		}

		if ( ! empty( $parent_order->get_items( 'shipping' ) ) ) {
			foreach ( $parent_order->get_items( 'shipping' ) as $shipping ) {
				$items[] = $shipping;
			}
		}

		$gateway      = rp_get_payment_method( $parent_order );
		$invoice_data = $gateway->api->get_invoice_by_handle( $data['invoice'] );
		self::log( [
			'log' => [
				'source' => 'WC_Reepay_Renewals::create_child_invoice_data',
				'data'   => $invoice_data,
			]
		] );

		if ( $invoice_data['state'] == 'failed' || $invoice_data['state'] == 'dunning' ) {
			$status = 'wc-failed';
		}

		if ( ! empty( $invoice_data ) && ! empty( $invoice_data['order_lines'] ) ) {
			$new_items = [];
			foreach ( $invoice_data['order_lines'] as $invoice_lines ) {
				$is_exist = false;
				foreach ( $items as $item ) {
					if ( $item->is_type( 'line_item' ) ) {
						$product = $item->get_product();
						if ( $product && ( $product->is_type( 'reepay_variable_subscriptions' ) || $product->is_type( 'reepay_simple_subscriptions' ) ) ) {
							if ( $product->get_meta( '_reepay_subscription_name' ) == $invoice_lines['ordertext'] ) {
								$is_exist    = true;
								$new_items[] = $item;
							}
						} else {
							if ( $item['name'] == $invoice_lines['ordertext'] ) {
								$is_exist    = true;
								$new_items[] = $item;
							}
						}
					} else {
						if ( $item['name'] == $invoice_lines['ordertext'] ) {
							$is_exist    = true;
							$new_items[] = $item;
						}
					}
				}

				if ( ! $is_exist ) {
					if ( $invoice_lines['origin'] == 'surcharge_fee' ) {
						$fees_item = new WC_Order_Item_Fee();
						$fees_item->set_name( $invoice_lines['ordertext'] );
						$fees_item->set_amount( floatval( $invoice_lines['unit_amount'] ) / 100 );
						$fees_item->set_total( floatval( $invoice_lines['amount'] ) / 100 );
						$new_items[] = $fees_item;
					} else {
						$product_item = new WC_Order_Item_Product();
						$product_item->set_name( $invoice_lines['ordertext'] );
						$product_item->set_quantity( $invoice_lines['quantity'] );
						$product_item->set_subtotal( floatval( $invoice_lines['unit_amount'] ) / 100 );
						$product_item->set_total( floatval( $invoice_lines['amount_ex_vat'] ) / 100 );
						$new_items[] = $product_item;
					}
				}
			}

			$items = $new_items;
		}


		self::log( [
			'log' => [
				'source' => 'WC_Reepay_Renewals::create_child_order_items',
				'data'   => $items,
			]
		] );

		return self::create_order_copy( [
			'status'      => $status,
			'parent'      => $parent_order->get_id(),
			'customer_id' => $parent_order->get_customer_id(),
		], $parent_order, $items );
	}

	/**
	 * @param array[
	 *     'id' => string
	 *     'timestamp' => string
	 *     'signature' => string
	 *     'subscription' => string
	 *     'customer' => string
	 *     'event_type' => string
	 *     'event_id' => string
	 * ] $data
	 * @param string $status
	 *
	 * @return bool|WP_Error
	 */
	public static function update_subscription_status( $data, $status ) {
		self::log( [
			'log' => [
				'source'  => 'WC_Reepay_Renewals::update_subscription_status',
				'$data'   => $data,
				'$status' => $status
			]
		] );

		if ( empty( $data['subscription'] ) ) {
			return new WP_Error(  __( 'Undefined subscription handle', 'reepay-subscriptions-for-woocommerce' ) );
		}

		$order = self::get_order_by_subscription_handle( $data['subscription'] );

		self::log( [
			'log' => [
				'source'   => 'WC_Reepay_Renewals::update_subscription_status::order',
				'order_id' => empty( $order ) ? 0 : $order->get_id(),
			]
		] );

		if ( empty( $order ) ) {
			return new WP_Error( __( 'Undefined parent order', 'reepay-subscriptions-for-woocommerce' ) );
		}

		if ( $order->get_status() === $status ) {
			return new WP_Error( __( 'Duplication of order status', 'reepay-subscriptions-for-woocommerce' ) );
		}

		$order->set_status( $status );
		$order->save();

		self::save_reepay_subscription_dates( $order );

		return true;
	}

	/**
	 * @param array $order_args Order arguments.
	 * @param WC_Order $main_order Order arguments.
	 * @param array<WC_Order_Item> $items
	 *
	 * @return WC_Order|WP_Error
	 */
	public static function create_order_copy( $order_args, $main_order, $items = [] ) {
		$new_order = wc_create_order( $order_args );
		$new_order->save();

		$main_order = wc_get_order( $main_order );


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
			'_reepay_customer',
		];

		foreach ( $fields_to_copy as $field_name ) {
			update_post_meta( $new_order->get_id(), $field_name, get_post_meta( $main_order->get_id(), $field_name, true ) );
		}

		foreach ( $items as $item ) {
			if ( $item->is_type( 'line_item' ) ) {
				$product_item = new WC_Order_Item_Product();
				$product_item->set_name( $item->get_name() );
				$product_item->set_quantity( $item->get_quantity() );
				$product_item->set_product_id( $item->get_product_id() );
				$product_item->set_variation_id( $item->get_variation_id() );
				$product_item->set_subtotal( $item->get_subtotal() );
				$product_item->set_total( $item->get_total() );

				$meta_item = $item->get_formatted_meta_data();
				if ( ! empty( $meta_item ) ) {
					foreach ( $meta_item as $value ) {
						$product_item->add_meta_data( $value->key, $value->value );

						if( '_reepay_subscription_customer_role' === $value->key ) {
							$new_order->add_meta_data( '_reepay_subscription_customer_role', $value->value );
						}
					}
				}
				self::log( [
					'log' => [
						'source' => 'WC_Reepay_Renewals::create_subscription_item_data',
						'data'   => $item->get_formatted_meta_data()
					],
				] );

				$new_order->add_item( $product_item );
			}

			//fees
			if ( $item->is_type( 'fee' ) ) {
				$fees_item = new WC_Order_Item_Fee();
				$fees_item->set_name( $item->get_name() );
				$fees_item->set_amount( $item->get_amount() );
				$fees_item->set_total( $item->get_total() );
				$new_order->add_item( $fees_item );
			}

			//shipping
			if ( $item->is_type( 'shipping' ) ) {
				$shipping_item = new WC_Order_Item_Shipping();
				$shipping_item->set_method_title( $item->get_method_title() );
				$shipping_item->set_method_id( $item->get_method_id() );
				$shipping_item->set_total( $item->get_total() );
				$new_order->add_item( $shipping_item );
			}


			$new_order->calculate_totals();
		}

		$new_order->save();
		$new_order->calculate_totals();

		return $new_order;
	}

	/**
	 * @param  array  $data  @see self::renew_subscription
	 *
	 * @return true|WP_Error
	 */
	public static function change_user_role( $data ) {
		$order = self::get_order_by_subscription_handle( $data['subscription'] );

		if ( empty( $order ) ) {
			return new WP_Error( __( 'Order not found', 'reepay-subscriptions-for-woocommerce' ) );
		}

		$new_role = $order->get_meta( '_reepay_subscription_customer_role' );

		if ( empty( $new_role ) || 'without_changes' === $new_role ) {
			return new WP_Error( __( 'Role change not required', 'reepay-subscriptions-for-woocommerce' ) );
		}

		$customer_id = $order->get_customer_id();

		if ( empty( $customer_id ) ) {
			return new WP_Error( __( 'No customer in order', 'reepay-subscriptions-for-woocommerce' ) );
		}

		$user = get_userdata( $customer_id );

		if ( empty( $user ) ) {
			return new WP_Error( __( 'Wrong customer id', 'reepay-subscriptions-for-woocommerce' ) );
		}

		$user->set_role( $new_role );

		return true;
	}

	/**
	 * @param  mixed  $order
	 * @param  array[]|null $data - @see https://reference.reepay.com/api/#the-subscription-object
	 *
	 * @return array|WP_Error - array of saved data or error
	 */
	public static function save_reepay_subscription_dates( $order, $data = null ) {
		$order = wc_get_order( $order );

		if ( empty( $order ) ) {
			return new WP_Error( __( 'Undefined order', 'reepay-subscriptions-for-woocommerce' ) );
		}

		if ( is_null( $data ) ) {
			$handle = $order->get_meta( '_reepay_subscription_handle' );

			if ( empty( $handle ) ) {
				return new WP_Error( __( 'Undefined subscription handle', 'reepay-subscriptions-for-woocommerce' ) );
			}

			try {
				$data = reepay_s()->api()->request( "subscription/$handle" );
			} catch ( Exception $e ) {
				return new WP_Error( $e->getMessage() );
			}
		}

		$time_data = [
			'expires'              => strtotime( $data['expires'] ?? false ),
			'reactivated'          => strtotime( $data['reactivated'] ?? false ),
			'created'              => strtotime( $data['created'] ?? false ),
			'activated'            => strtotime( $data['activated'] ?? false ),
			'start_date'           => strtotime( $data['start_date'] ?? false ),
			'end_date'             => strtotime( $data['end_date'] ?? false ),
			'current_period_start' => strtotime( $data['current_period_start'] ?? false ),
			'next_period_start'    => strtotime( $data['next_period_start'] ?? false ),
			'first_period_start'   => strtotime( $data['first_period_start'] ?? false ),
			'last_period_start'    => strtotime( $data['last_period_start'] ?? false ),
			'trial_start'          => strtotime( $data['trial_start'] ?? false ),
			'trial_end'            => strtotime( $data['trial_end'] ?? false ),
			'cancelled_date'       => strtotime( $data['cancelled_date'] ?? false ),
			'expired_date'         => strtotime( $data['expired_date'] ?? false ),
			'on_hold_date'         => strtotime( $data['on_hold_date'] ?? false ),
			'reminder_email_sent'  => strtotime( $data['reminder_email_sent'] ?? false ),
		];

		$order->update_meta_data( '_reepay_subscription_dates', $time_data );
		$order->save();

		return $time_data;
	}

	/**
	 * @param  mixed   $order
	 * @param  string  $date_key
	 * @param  string  $date_format
	 *
	 * @return string
	 */
	public static function get_reepay_subscription_dates( $order, $date_key, $date_format = 'wordpress' ) {
		$order = wc_get_order( $order );

		if ( empty( $order ) ) {
			return '';
		}

		$dates = $order->get_meta( '_reepay_subscription_dates' );

		if ( empty( $dates ) ) {
			$dates = self::save_reepay_subscription_dates( $order );

			if ( is_wp_error( $dates ) ) {
				return '';
			}
		}

		if ( empty( $dates[ $date_key ] ) ) {
			return '';
		}

		if ( 'wordpress' === $date_format ) {
			return wp_date( get_option( 'date_format' ), $dates[ $date_key ] );
		}

		return $dates[ $date_key ];
	}

	/**
	 * @param WC_Order $order
	 * @param string $customer_handle
	 *
	 *
	 * @return array<string>
	 * @throws Exception
	 */
	public static function get_reepay_coupons( $order, $customer_handle = null ) {
		$coupons = [];

		foreach ( $order->get_coupon_codes() as $coupon_code ) {
			$c = new WC_Coupon( $coupon_code );


			if ( $c->is_type( 'reepay_type' ) ) {
				$coupon_code_real = WC_Reepay_Discounts_And_Coupons::get_coupon_code_real( $c );
				if (
					empty( $customer_handle ) ||
					WC_Reepay_Discounts_And_Coupons::coupon_can_be_applied( $coupon_code_real, $customer_handle )
				) {
					$coupons[] = $coupon_code_real;
				}
			}
		}

		return $coupons;
	}

	/**
	 * @param WC_Order $order
	 *
	 *
	 * @param $handle
	 *
	 * @return array<string>
	 */
	public static function get_reepay_discounts( $order, $handle ) {
		$discounts = [];

		foreach ( $order->get_coupon_codes() as $coupon_code ) {
			$c = new WC_Coupon( $coupon_code );

			if ( $c->is_type( 'reepay_type' ) ) {
				$discount_handle = get_post_meta( $c->get_id(), '_reepay_discount_handle', true );
				if ( $discount_handle ) {
					$discounts[] = [
						'handle'   => $handle . '_' . $discount_handle,
						'discount' => $discount_handle
					];
				}
			}
		}

		return $discounts;
	}

	/**
	 * @param WC_Order_Item $order_item
	 *
	 * @return array
	 */
	public static function get_plan_addons( $order_item ) {
		$plan_addons = $order_item->get_meta( 'addons' );
		if ( ! empty( $plan_addons ) ) {
			foreach ( $plan_addons as &$addon ) {
				$addon['amount'] = floatval( $addon['amount'] ) * 100;
			}

			return $plan_addons;
		}

		return [];
	}

	/**
	 * @param WC_Order $order
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

		if ( empty( $shm_data ) || empty( $shm_data['reepay_shipping_addon_name'] ) ) {
			return [];
		}

		return [
			[
				'name'          => $shm_data['reepay_shipping_addon_name'],
				'description'   => $shm_data['reepay_shipping_addon_description'],
				'type'          => 'on_off',
				'fixed_amount ' => true,
				'amount'        => $shm_data['reepay_shipping_addon_amount'],
				'vat'           => WC_Reepay_Subscription_Plan_Simple::get_vat_shipping(),
				'vat_type'      => wc_prices_include_tax(),
				'handle'        => $shm_data['reepay_shipping_addon'],
				'exist'         => $shm_data['reepay_shipping_addon'],
				'add_on'        => $shm_data['reepay_shipping_addon'],
			]
		];
	}

	/**
	 * Lock the order.
	 *
	 * @param mixed $order_id
	 *
	 * @return void
	 */
	private static function lock_order( $order_id ) {
		update_post_meta( $order_id, '_reepay_subscriptions_locked', '1' );
	}

	/**
	 * Unlock the order.
	 *
	 * @param mixed $order_id
	 *
	 * @return void
	 */
	private static function unlock_order( $order_id ) {
		delete_post_meta( $order_id, '_reepay_subscriptions_locked' );
	}

	/**
	 * Check is order order locked.
	 *
	 * @param $order_id
	 *
	 * @return bool
	 */
	private static function is_locked( $order_id ) {
		return (bool) get_post_meta( $order_id, '_reepay_subscriptions_locked', true );
	}

	/**
	 * @param array $data
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
