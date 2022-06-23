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
		add_action( 'reepay_webhook_invoice_authorized', [ $this, 'create_subscription' ] );
		add_action( 'reepay_webhook_invoice_settled', [ $this, 'create_subscription' ] );

		add_action( 'reepay_webhook_raw_event_subscription_renewal', [ $this, 'renew_subscription' ] );
		add_action( 'reepay_webhook_raw_event_subscription_on_hold', [ $this, 'hold_subscription' ] );
		add_action( 'reepay_webhook_raw_event_subscription_cancelled', [ $this, 'cancel_subscription' ] );
		add_action( 'reepay_webhook_raw_event_subscription_uncancelled', [ $this, 'uncancel_subscription' ] );
        add_action( 'manage_shop_order_posts_custom_column', [ $this, 'shop_order_custom_columns' ], 11 );
        add_filter( 'manage_edit-shop_order_columns', [ $this, 'admin_shop_order_edit_columns' ], 11 );
        add_filter( 'post_class', [ $this, 'admin_shop_order_row_classes' ], 10, 2 );
	}

    /**
     * Adds css classes on admin shop order table
     *
     * @global WP_Post $post
     *
     * @param array $classes
     * @param int $post_id
     *
     * @return array
     */
    public function admin_shop_order_row_classes( $classes, $post_id ) {
        global $post;

        if ( is_search() || ! current_user_can( 'manage_woocommerce' ) ) {
            return $classes;
        }

        if ( $post->post_type == 'shop_order' && $post->post_parent != 0 ) {
            $classes[] = 'sub-order parent-' . $post->post_parent;
        }

        return $classes;
    }

    /**
     * Adds custom column on admin shop order table
     *
     * @param string $col
     *
     * @return void
     */
    public function shop_order_custom_columns($col){
        /**
         * @global \WP_Post $post
         * @global \WC_Order $the_order
         */
        global $post, $the_order;

        if ( empty( $the_order ) || $the_order->get_id() !== $post->ID ) {
            $the_order = new \WC_Order( $post->ID );
        }

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        if ( ! in_array( $col, [ 'order_number', 'suborder', 'reepay_sub' ], true ) ) {
            return;
        }

        $output = '';
        switch ( $col ) {
            case 'order_number':
                if ( $post->post_parent !== 0 ) {
                    $output = '<strong>';
                    $output .= esc_html__( '&nbsp;Sub Order of', reepay_s()->settings( 'domain' ) );
                    $output .= sprintf( ' <a href="%s">#%s</a>', esc_url( admin_url( 'post.php?action=edit&post=' . $post->post_parent ) ), esc_html( $post->post_parent ) );
                    $output .= '</strong>';
                }
                break;

            case 'suborder':
                $handle = $the_order->get_meta( '_reepay_subscription_handle', true );
                if ( !empty($handle) && $post->post_parent == 0 ) {
                    $output = sprintf( '<a href="#" class="show-sub-orders" data-class="parent-%1$d" data-show="%2$s" data-hide="%3$s">%2$s</a>', esc_attr( $post->ID ), esc_attr__( 'Show history', reepay_s()->settings( 'domain' ) ), esc_attr__( 'Hide history', reepay_s()->settings( 'domain' ) ) );
                }
                break;

            case 'reepay_sub':
                $handle = $the_order->get_meta( '_reepay_subscription_handle', true );
                if(!empty($handle)){
                    $admin_page = 'https://admin.reepay.com/#/misha-rudrastyh-team/misha-rudrastyh-team/';

                    $link = $admin_page . 'subscriptions/' . $handle;

                    $output = sprintf( '<a target="_blank" href="%s">%s</a>', $link, 'Reepay subscription - '.$the_order->get_id() );
                }

                break;
        }

        if ( ! empty( $output ) ) {
            echo $output;
        }
    }

    /**
     * Change the columns shown in admin.
     *
     * @param array $existing_columns
     *
     * @return array
     */
    public function admin_shop_order_edit_columns( $existing_columns ) {
        if ( WC_VERSION > '3.2.6' ) {
            unset( $existing_columns['wc_actions'] );

            $columns = array_slice( $existing_columns, 0, count( $existing_columns ), true ) +
                array(
                    'reepay_sub'     => __( 'Subscription', reepay_s()->settings( 'domain' ) ),
                    'suborder'   => __( 'Sub Order', reepay_s()->settings( 'domain' ) ),
                )
                + array_slice( $existing_columns, count( $existing_columns ), count( $existing_columns ) - 1, true );
        } else {
            $existing_columns['reepay_sub']    = __( 'Vendor', reepay_s()->settings( 'domain' ) );
            $existing_columns['suborder']  = __( 'Sub Order', reepay_s()->settings( 'domain' ) );
        }

        if ( WC_VERSION > '3.2.6' ) {
            // Remove seller, suborder column if seller is viewing his own product
            if ( ! current_user_can( 'manage_woocommerce' ) || ( isset( $_GET['author'] ) && ! empty( $_GET['author'] ) ) ) {
                unset( $columns['suborder'] );
                unset( $columns['reepay_sub'] );
            }

            return $columns;
        }

        // Remove seller, suborder column if seller is viewing his own product
        if ( ! current_user_can( 'manage_woocommerce' ) || ( isset( $_GET['author'] ) && ! empty( $_GET['author'] ) ) ) {
            unset( $existing_columns['suborder'] );
            unset( $existing_columns['reepay_sub'] );
        }

        return $existing_columns;
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
	 *     'order_id' => int
	 * ] $data
	 */
	public function create_subscription( $data ) {
		$order = wc_get_order( $data['order_id'] );

		if ( ! empty( $order->get_meta( '_reepay_subscription_handle' ) ) ) {
			self::log( [
				'log'    => [
					'source' => 'WC_Reepay_Renewals::create_subscription',
					'error'  => 'Subscription already exists',
					'data'   => $data
				],
				'notice' => "Subscription {$data['order_id']} already exists, an attempt was made to re-create"
			] );

			return;
		}

		$token = self::get_payment_token_order( $order );

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

		foreach ( $order->get_items() as $item_id => $order_item ) {
			$product = $order_item->get_product();

			$handle = 'subscription_handle_' . $order->get_id() . '_' . $product->get_id() . '_' . time();

			$addons = array_merge( self::get_shipping_addons( $order ), $order_item->get_meta( 'addons' ) ?? [] );

			$new_subscription = null;
			try {
				/**
				 * @see https://reference.reepay.com/api/#create-subscription
				 */
				$new_subscription = reepay_s()->api()->request( 'subscription', 'POST', [
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
					'add_ons'         => $addons,
//					'additional_costs' => null,
					'signup_method'   => 'source',
				] );
			} catch ( Exception $e ) {
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

				return;
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
					'notice' => "Subscription {$data['order_id']} - unable to assign payment method to subscription"
				] );

				return;
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
		$parent_order = self::get_order_by_subscription_handle( $data['subscription'] );

		if ( empty( $parent_order ) ) {
			self::log( [
				'log'    => [
					'source' => 'WC_Reepay_Renewals::renew_subscription',
					'error'  => 'undefined parent order',
					'data'   => $data
				],
				'notice' => "Subscription {$data['subscription']} - undefined order"
			] );

			return;
		}

		self::create_child_order( $parent_order, 'wc-completed' );
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
		$parent_order = self::get_order_by_subscription_handle( $data['subscription'] );

		if ( empty( $parent_order ) ) {
			self::log( [
				'log'    => [
					'source' => 'WC_Reepay_Renewals::hold_subscription',
					'error'  => 'undefined parent order',
					'data'   => $data
				],
				'notice' => "Subscription {$data['subscription']} - undefined order"
			] );

			return;
		}

		self::create_child_order( $parent_order, 'wc-on-hold' );
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
        $parent_order = self::get_order_by_subscription_handle( $data['subscription'] );

        if ( empty( $parent_order ) ) {
            self::log( [
                'log'    => [
                    'source' => 'WC_Reepay_Renewals::cancel_subscription',
                    'error'  => 'undefined parent order',
                    'data'   => $data
                ],
                'notice' => "Subscription {$data['subscription']} - undefined order"
            ] );

            return;
        }

        self::create_child_order( $parent_order, 'wc-cancelled' );
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
		$parent_order = self::get_order_by_subscription_handle( $data['subscription'] );

		if ( empty( $parent_order ) ) {
			self::log( [
				'log'    => [
					'source' => 'WC_Reepay_Renewals::uncancel_subscription',
					'error'  => 'undefined parent order',
					'data'   => $data
				],
				'notice' => "Subscription {$data['subscription']} - undefined order"
			] );

			return;
		}

		self::create_child_order( $parent_order, 'wc-completed' );
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
		// $handle - "subscription_handle_<order_id>_<product_id>_<timestamp>"
		$parts = explode( '_', $handle );

		return wc_get_order( (int) $parts[2] );
	}

	/**
	 * @param  WC_Order  $parent_order
	 * @param  string  $status
	 *
	 * @return WC_Order|WP_Error
	 */
	public static function create_child_order( $parent_order, $status ) {
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
					'source' => 'WC_Reepay_Renewals::cancel_subscription',
					'error'  => 'duplicate status - ' . $status,
				],
				'notice' => "Subscription {$data['subscription']} - duplication attempt"
			] );
			return new WP_Error('Duplicate order');
		}

		$order = wc_create_order( [
			'status'      => $status,
			'parent'      => $parent_order->get_id(),
			'customer_id' => $parent_order->get_customer_id(),
		] );

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

		$order->save();

		foreach ( $fields_to_copy as $field_name ) {
			update_post_meta( $order->get_id(), $field_name, get_post_meta( $parent_order->get_id(), $field_name, true ) );
		}

		foreach ( $parent_order->get_items() as $item ) {
			$order->add_product( wc_get_product( $item['product_id'] ), $item['qty'] );
		}

		$order->save();
		$order->calculate_totals();

		return $order;
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

		if ( empty( $shm_data ) ) {
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
