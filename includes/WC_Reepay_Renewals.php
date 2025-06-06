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

//		add_action( 'reepay_webhook_invoice_created', [ $this, 'renew_subscription' ] );
        add_action( 'reepay_webhook_raw_event_subscription_renewal', [ $this, 'renew_subscription' ] );
        add_action( 'reepay_webhook_raw_event_subscription_on_hold', [ $this, 'hold_subscription' ] );
        add_action( 'reepay_webhook_raw_event_subscription_cancelled', [ $this, 'cancel_subscription' ] );
        add_action( 'reepay_webhook_raw_event_subscription_uncancelled', [ $this, 'uncancel_subscription' ] );

        /**
         * Change user role expired when hook action
         */
        add_action( 'reepay_webhook_raw_event_subscription_expired', [ $this, 'expired_subscription' ] );

        add_action( 'woocommerce_order_status_changed', array( $this, 'status_manual_start_date' ), 10, 4 );

        add_filter( 'woocommerce_available_payment_gateways', array( $this, 'get_available_payment_gateways' ) );

        add_filter( 'woocommerce_get_formatted_order_total', array( $this, 'display_real_total' ), 10, 4 );

        add_filter( 'reepay_settled_order_status', array( $this, 'reepay_subscriptions_order_status' ), 11, 2 );

        add_filter( 'show_reepay_metabox', array( $this, 'disable_for_sub' ), 10, 2 );

        add_filter( 'order_contains_reepay_subscription', function ( $contains, $order ) {
            return $this->reepay_order_contains_subscription( $order ) || $contains;
        }, 10, 2 );

        add_filter( 'woocommerce_cart_needs_payment', array( $this, 'check_need_payment' ), 10, 2 );
    }

    public function check_need_payment( $need_payment, $cart ) {
        if ( WC_Reepay_Checkout::is_reepay_product_in_cart() ) {
            return true;
        }

        return $need_payment;
    }

    /**
     * @param  WC_Order|integer  $order
     *
     * @return bool
     */
    function reepay_order_contains_subscription( $order ) {
        if ( ! empty( $order ) ) {
            $order = wc_get_order( $order );

            if ( ! empty( $order ) ) {
                foreach ( $order->get_items() as $item ) {
                    if ( WC_Reepay_Checkout::is_reepay_product( $item->get_product() ) ) {
                        return true;
                    }
                }
            }
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
                if ( WC_Reepay_Checkout::is_reepay_product( $product ) ) {
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

    public function status_manual_start_date(
        $order_id,
        $this_status_transition_from,
        $this_status_transition_to,
        $instance
    ) {
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
                $params['next_period_start'] = date( 'Y-m-d\TH:i:s',
                    strtotime( current_time( 'Y-m-d\TH:i:s' ) . "+60 seconds" ) );

                try {
                    reepay_s()->api()->request( "subscription/{$sub_meta}/change_next_period_start", 'POST', $params );

                    $order->update_meta_data( '_reepay_subscription_period_started', true );
                    $order->save_meta_data();
                } catch ( Exception $e ) {
                    self::log( [
                        'log' => [
                            'source'    => 'WC_Reepay_Renewals::status_manual_start_date',
                            'error'     => $e->getMessage(),
                            '$order_id' => $order_id,
                        ]
                    ] );

                    $notice = sprintf(
                        __( 'Unable to change subscription period to %s. Error from acquire: %s',
                            'reepay-subscriptions-for-woocommerce' ),
                        $params['next_period_start'],
                        $e->getMessage()
                    );

                    $order->add_order_note( $notice );
                    WC_Reepay_Subscription_Admin_Notice::add_frontend_notice( $notice, $order->get_id() );
                }
            }
        }

        if ( floatval( $order->get_total() ) != 0 && self::is_order_contain_subscription( $order ) ) {
            $order->update_meta_data( '_real_total', $order->get_total() );
            $order->save_meta_data();
            $order->set_total( 0 );
            $order->save();
        }
    }

    public function display_real_total( $formatted_total, $order, $tax_display, $display_refunded ) {
        $real_total = $order->get_meta( '_real_total' );

        if ( empty( $real_total ) ) {
            return $formatted_total;
        }

        if ( is_wc_endpoint_url( 'order-received' ) ) {
            return wc_price( $real_total, array( 'currency' => $order->get_currency() ) );
        }

        if ( is_admin() ) {
            return wc_price( 0, array( 'currency' => $order->get_currency() ) );
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
     *
     * @throws WC_Data_Exception
     */
    public function create_subscriptions_handle( $data ) {
        if ( $data['event_type'] == 'invoice_authorized' || $data['event_type'] == 'invoice_settled' ) {
            $order = rp_get_order_by_handle( $data['invoice'] );
        } elseif ( $data['event_type'] == 'customer_payment_method_added' ) {
            $order = rp_get_order_by_session( $data['payment_method_reference'], $data['customer'] );
        } else {
            return;
        }

        self::log( [
            'log' => [
                'source'   => 'WC_Reepay_Renewals::create_subscriptions_handle',
                'event'    => 'Subscription create request',
                'data'     => $data,
                'order_id' => empty( $order ) ? 'false' : $order->get_id()
            ],
        ] );


        if ( empty( $order ) ) {
            self::log( [
                'log' => [
                    'source' => 'WC_Reepay_Renewals::create_subscriptions_handle',
                    'error'  => 'Order not found',
                    'data'   => $data
                ],
            ] );

            return;
        }

        if ( ! empty( $data['invoice'] ) ) {
            $child_order = $this->get_child_order( $order, $data['invoice'] );
        }

        self::log( [
            'log' => [
                'source'  => 'WC_Reepay_Renewals::child_order',
                'error'   => '',
                'data'    => $child_order ?? '-',
                'invoice' => $data['invoice'] ?? 'empty'
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

    /**
     * @param WC_Order $order
     *
     * @return bool|WC_Order_Item
     */
    public static function is_order_contain_subscription( $order ) {
        foreach ( $order->get_items() as $item ) {
            $product = $item->get_product();

            //Imported subscriptions are empty
            if ( empty( $product ) ) {
                continue;
            }

            if ( $product->is_type( 'reepay_variable_subscriptions' ) || $product->is_type( 'reepay_simple_subscriptions' ) ) {
                return $item;
            }

            if ( $product->is_type( 'variation' ) && ! empty( $product->get_parent_id() ) ) {
                $_product_main = wc_get_product( $product->get_parent_id() );
                if ( $_product_main->is_type( 'reepay_variable_subscriptions' ) ) {
                    return $item;
                }
            }
        }

        return false;
    }

    /**
     *
     * @param array $data [
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
     *
     * @throws WC_Data_Exception
     */
    public function create_subscriptions( array $data, WC_Order $main_order ) {
//		$main_order->add_meta_data( '_reepay_is_subscription', 1 );
        self::log( [
            'log' => [
                'source'     => 'WC_Reepay_Renewals::create_subscriptions',
                'main_order' => $main_order->get_id(),
                'data'       => $data,
            ]
        ] );

        $data['order_id'] = $main_order->get_id();

        if ( ! empty( $data['payment_method'] ) ) {
            $token = $data['payment_method'];
        } else {
            $token = self::get_payment_token_order( $main_order );
        }

        if ( empty( $token ) ) {
            self::log( [
                'log'    => [
                    'source' => 'WC_Reepay_Renewals::create_subscriptions',
                    'error'  => 'Empty token',
                    'data'   => $data
                ],
                'notice' => sprintf(
                    __( 'Subscription %d has no payment token', 'reepay-subscriptions-for-woocommerce' ),
                    (string) $main_order->get_id()
                )
            ] );

            $main_order->add_order_note( __( "Unable to create subscription. Empty token",
                'reepay-subscriptions-for-woocommerce' ) );

            return;
        }

        $user = get_user_by( 'email', $main_order->get_billing_email() );
        if ( $user ) {
            $main_order->set_customer_id( $user->ID );
            $main_order->save();
        }

        self::log( [
            'log' => [
                'source' => 'WC_Reepay_Renewals::customer_id_connect',
                '$data'  => $main_order->get_customer_id(),
            ]
        ] );
        $orders      = [ $main_order ];
        $order_items = $main_order->get_items();
        [ $new_orders, $created_order_ids ] = $this->get_division_of_products_into_orders( $order_items, $main_order );
        $orders = array_merge( $orders, $new_orders );

        $main_order->update_meta_data( '_reepay_another_orders', $created_order_ids );
        if (empty($created_order_ids)) {
            $main_order->update_meta_data( '_reepay_is_subscription', 1 );
        }
        $main_order->save_meta_data();

        $main_order->calculate_totals();

        // create sub-orders renewals
        $created_reepay_order_ids = [];
        foreach ( $orders as $order_key => $order ) {
            $order_item = self::is_order_contain_subscription( $order );
            if ( ! $order_item ) {
                continue;
            }

            if ( floatval( $order->get_total() ) != 0 ) {
                $order->update_meta_data( '_real_total', $order->get_total() );
                $order->save_meta_data();
                $order->set_total( 0 );
            } elseif ( floatval( $order->get_subtotal() ) != 0 ){
                $order->update_meta_data( '_real_total', $order->get_subtotal() );
                $order->save_meta_data();
                $order->set_total( 0 );
            }

            $product = $order_item->get_product();

            $handle = "{$order->get_id()}_{$product->get_id()}_$order_key";

            $addons = array_merge( self::get_shipping_addons( $order ), self::get_plan_addons( $order_item ) ?: [] );
            self::log( [
                'log' => [
                    'source'  => 'WC_Reepay_Renewals::create_subscriptions',
                    '$addons' => $addons,
                ]
            ] );

            $new_subscription = $this->create_subscription_from_order_item(
                $main_order,
                $order,
                $order_item,
                [
                    'customer' => $data['customer'],
                    'handle'   => $handle,
                    'source'   => $token,
                    'addons'   => $addons,
                ]
            );

            if ( empty( $new_subscription ) ) {
                continue;
            }

            $payment_method = $this->create_payment_method($handle, $token);
            if ( empty( $payment_method ) ) {
                self::log( [
                    'log'    => [
                        'source' => 'WC_Reepay_Renewals::create_subscriptions',
                        'error'  => 'set-payment-method',
                        'data'   => $data
                    ],
                    'notice' => sprintf(
                        __( "Subscription %s - unable to assign payment method to subscription",
                            'reepay-subscriptions-for-woocommerce' ),
                        $data['order_id']
                    )
                ] );

                continue;
            }

            $status_main = reepay_s()->settings( '_reepay_orders_default_subscription_status' );
            if ( $order->get_status() != $status_main ) {
                $order->update_status( $status_main );
            }

            $order->add_meta_data( '_reepay_subscription_handle', $handle );

            $order->save();

            $created_reepay_order_ids[] = $order->get_id();
        }

        do_action( 'reepay_subscriptions_orders_created', $created_reepay_order_ids, $main_order );
    }

    /**
     * Get split orders
     *
     * @param WC_Order_Item[] $order_items
     * @param WC_Order $main_order
     *
     * @return array
     *
     * @throws WC_Data_Exception
     */
    public function get_division_of_products_into_orders( array $order_items, WC_Order $main_order ): array {
        $orders            = [];
        $created_order_ids = [];
        foreach ( $order_items as $order_item_key => $order_item ) {
            /**
             * @var WC_Order_Item_Product $order_item
             */
            $product                    = $order_item->get_product();
            $order_item_quantity        = $order_item->get_quantity();
            $addons                     = $order_item->get_meta( 'addons' );
            $is_exist_addon_type_on_off = self::is_exist_addon_type_on_off_in_addons( $addons );
            $order_items_count          = count( $order_items );

            if ( $product->is_type('woosb') ) {
                unset( $order_items[ $order_item_key ] );
            }
            if ( ! WC_Reepay_Checkout::is_reepay_product( $product ) ) {
                continue;
            }

            $items_to_create = [];

            $fee = $product->get_meta( '_reepay_subscription_fee' );
            if ( ! empty( $fee ) && ! empty( $fee['enabled'] ) && $fee['enabled'] == 'yes' ) {
                foreach ( $main_order->get_items( 'fee' ) as $item_id => $item ) {
                    if ( $product->get_name() . ' - ' . $fee["text"] === $item['name'] ) {
                        $items_to_create[] = $item;
                        $main_order->remove_item( $item_id );
                    }
                }
            }

            // Add coupon to order
            $discount = null;
            $coupons = $main_order->get_items( 'coupon' );
            if ( $coupons ){
                foreach ( $coupons as $item_id => $item ){
                    $coupon = new WC_Coupon($item->get_code());
                    if ( $coupon->is_type('reepay_type')) {
                        $items_to_create[] = $item;
                        $main_order->add_meta_data( '_reepay_coupon_code', $item->get_code() );
                        $main_order->save();
                        $real_total = $main_order->get_meta( '_real_total' );
                        if( ! empty( $real_total ) ){
                            $discount = $main_order->get_total() - $real_total;
                        }
                    }
                }
            }

            $order_direct_quantity = $order_item_quantity;
            if ( $order_item_quantity > 1 && $is_exist_addon_type_on_off ) {
                $addons_amount = 0;
                foreach ( $addons as $addon ) {
                    $addons_amount += (float) $addon['amount'];
                }
                for ( $i = 1; $i < $order_item_quantity; $i ++ ) {
                    $new_product_item = new WC_Order_Item_Product();
                    $new_product_item->set_name( $order_item->get_name() );
                    $new_product_item->set_quantity( 1 );
                    $new_product_item->set_product_id( $order_item->get_product_id() );
                    $product = wc_get_product( $order_item->get_product_id() );
                    $total   = (string) ( (float) $product->get_price() + $addons_amount );
                    $new_product_item->set_variation_id( $order_item->get_variation_id() );
                    $new_product_item->set_subtotal( $total );
                    if (WC_Reepay_Checkout::is_reepay_product($product)) {
                    if ( $discount !== null ) {
                        $new_product_item->set_total( $total - $discount );
                    } else {
                            $new_product_item->set_total( $total );
                        }
                    }else{
                        $new_product_item->set_total( $total );
                    }
                    $order_direct_quantity --;

                    foreach ( $order_item->get_formatted_meta_data() as $meta_data ) {
                        $new_product_item->add_meta_data( $meta_data->key, $meta_data->value );
                    }
                    $new_product_item->add_meta_data( 'addons', $addons );

                    $created_order = self::create_order_copy( [
                        'status' => $main_order->get_status( '' ),
                        'customer_id' => $main_order->get_customer_id(),
                    ], $main_order, $items_to_create );
                    $created_order->set_customer_id( $main_order->get_customer_id() );
                    $created_order->update_meta_data( '_reepay_is_subscription', 1 );
                    $created_order->update_meta_data( '_reepay_order', '');
                    $created_order->save();

                    $new_product_item->set_order_id( $created_order->get_id() );
                    $new_product_item->save();

                    $order_item->set_quantity( $order_direct_quantity );
                    $order_item->set_total( $order_item->get_total() - $total );
                    $order_item->set_subtotal( $order_item->get_subtotal() - $total );
                    $order_item->save();

                    $orders[]            = wc_get_order( $created_order ); // otherwise cached
                    $created_order_ids[] = $created_order->get_id();
                }
            } else {
                // if last order item
                if ( $order_items_count <= 1 ) {
                    break;
                }
                $main_order->remove_item( $order_item_key );
                unset( $order_items[ $order_item_key ] );

                $items_to_create[] = $order_item;
                $created_order     = self::create_order_copy( [
                    'status' => $main_order->get_status( '' ),
                    'customer_id' => $main_order->get_customer_id(),
                ], $main_order, $items_to_create );
                $created_order->set_customer_id( $main_order->get_customer_id() );
                $created_order->update_meta_data( '_reepay_is_subscription', 1 );
                $created_order->update_meta_data( '_reepay_order', '');
                $created_order->save();
                $orders[]            = $created_order;
                $created_order_ids[] = $created_order->get_id();
            }
        }

        return array( $orders, $created_order_ids );
    }

    /**
     * @param array|string $addons
     *
     * @return bool
     */
    public static function is_exist_addon_type_on_off_in_addons($addons): bool {
        return ! is_string( $addons ) && in_array( 'on_off', array_column( $addons, 'type' ) );
    }

    /**
     * @param string $handle
     * @param string $token
     *
     * @return null|object|array
     */
    public function create_payment_method( string $handle, string $token ) {
        $payment_method = null;
        try {
            /**
             * @see https://reference.reepay.com/api/#set-payment-method
             */
            $payment_method = reepay_s()->api()->request( "subscription/$handle/pm", 'POST', [
                'handle' => $handle,
                'source' => $token,
            ] );
        } catch ( Exception $e ) {
            self::log( [
                'notice' => $e->getMessage()
            ] );
        }
        return $payment_method;
    }

    /**
     * Get POST data for create subscription request
     *
     * @param WC_Order $main_order
     * @param WC_Order $split_order
     * @param WC_Order_Item $order_item
     * @param array{customer: string, handle: string, source: string, addons: array} $data
     *
     * @return array
     *
     * @throws Exception
     */
    public function get_create_subscription_data(
        WC_Order $main_order,
        WC_Order $split_order,
        WC_Order_Item $order_item,
        array $data
    ): array {
        /**
         * @see https://reference.reepay.com/api/#create-subscription
         */
        $product  = $order_item->get_product();
        $order_item_quantity = $order_item->get_quantity();
        $sub_data = [
            'customer'        => $data['customer'],
            'plan'            => $product->get_meta( '_reepay_subscription_handle' ),
            //					'amount' => null,
            'quantity'        => $order_item_quantity,
            'test'            => WooCommerce_Reepay_Subscriptions::settings( 'test_mode' ),
            'handle'          => $data['handle'],
            //					'metadata' => null,
            'source'          => $data['source'],
            //					'create_customer' => null,
            //					'plan_version'    => null,
            'amount_incl_vat' => wc_prices_include_tax(),
            //					'generate_handle' => null,
            'grace_duration'  => 172800,
            //					'no_trial' => null,
            //					'no_setup_fee' => null,
            //					'trial_period' => null,
            //					'subscription_discounts' => null,
            'coupon_codes'    => self::get_reepay_coupons( $split_order, $data['customer'] ),
            //					'additional_costs' => null,
            'signup_method'   => 'source',
        ];

        if ( WooCommerce_Reepay_Subscriptions::settings( '_reepay_manual_start_date' ) ) {
            $sub_data['start_date'] = date( 'Y-m-d\TH:i:s', strtotime( "+100 years" ) );
        }

        if ( ! empty( $data['addons'] ) ) {
            $sub_data['add_ons'] = $data['addons'];
        }

        // Disable add coupon discount double time in secound order.
        /*
        if ( $main_order->get_id() !== $split_order->get_id() ) {
            $sub_data['subscription_discounts'] = self::get_reepay_discounts( $main_order, $data['handle'] );
        }
        */

        // override amount if WPC Product Bundles for WooCommerce
        if ( ! empty( $order_item->get_meta( '_woosb_parent_id' ) ) && function_exists('rp_prepare_amount') ) {
            $order_item_data = $order_item->get_data();
            $total = (float) $order_item_data['total'] + (float) ($order_item_data['total_tax'] ?? 0);
            $subtotal = $total / $order_item_quantity;
            $sub_data['amount'] = rp_prepare_amount( $subtotal, $main_order->get_currency() );
        }

        return $sub_data;
    }

    /**
     * @param WC_Order $main_order
     * @param WC_Order $split_order
     * @param WC_Order_Item $order_item
     * @param array{customer: string, handle: string, source: string, addons: array} $data
     *
     * @return null|object|array
     */
    public function create_subscription_from_order_item(
        WC_Order $main_order,
        WC_Order $split_order,
        WC_Order_Item $order_item,
        array $data
    ) {
        $product          = $order_item->get_product();
        $new_subscription = null;
        try {
            /**
             * @see https://reference.reepay.com/api/#create-subscription
             */
            $sub_data         = $this->get_create_subscription_data(
                $main_order,
                $split_order,
                $order_item,
                $data
            );
            $new_subscription = reepay_s()->api()->request( 'subscription', 'POST', $sub_data );
        } catch ( Exception $e ) {
            $notice = sprintf(
                __( 'Unable to create subscription. Error from acquire: %s',
                    'reepay-subscriptions-for-woocommerce' ),
                $e->getMessage()
            );
            self::log( [
                'notice' => $notice
            ] );
            $split_order->add_order_note( $notice );
        }
        if ( empty( $new_subscription ) ) {
            self::log( [
                'log'    => [
                    'source' => 'WC_Reepay_Renewals::create_subscriptions',
                    'error'  => 'create-subscription',
                    'data'   => $sub_data ?? 'empty',
                    'plan'   => $product->get_meta( '_reepay_subscription_handle' )
                ],
                'notice' => sprintf(
                    __( "Subscription %s - unable to create subscription", 'reepay-subscriptions-for-woocommerce' ),
                    $data['order_id']
                )
            ] );
        }

        return $new_subscription;
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
        $status_main = reepay_s()->settings( '_reepay_orders_default_subscription_status' );
        //self::update_subscription_status( $data, $status_main, false );
        self::create_child_order( $data, reepay_s()->settings( '_reepay_suborders_default_renew_status' ) );
        self::change_user_role( $data );
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
        self::update_subscription_status( $data, 'wc-on-hold' );
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
        self::update_subscription_status( $data, 'wc-cancelled' );
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
        // self::update_subscription_status( $data, 'wc-completed' );
        self::update_subscription_status( $data, reepay_s()->settings( '_reepay_suborders_default_renew_status' ) );
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
    public function expired_subscription( $data ) {
        self::update_subscription_status( $data, 'wc-cancelled' );
        self::change_user_role( $data );
    }

    /**
     * Get payment token.
     *
     * @param  WC_Order  $order
     *
     * @return string|false
     * @todo refactor with while cycle
     *
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

        if ( empty( $orders ) ) {
            $orders = wc_get_orders(
                [
                    'limit'      => 1,
                    'meta_key'   => '_reepay_subscription_handle',
                    'meta_value' => $handle,
                ]
            );
        }

        return ! empty( $orders ) ? current( $orders ) : false;
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
        } catch ( Exception $e ) {
            return false;
        }

        $is_active = $subscription['state'] === 'active';

        set_transient( $transient_name, $is_active ? '1' : '0', HOUR_IN_SECONDS );

        return $is_active;
    }

    /**
     * @param  WC_Order  $parent_order
     * @param  string  $invoice
     *
     * @return WC_Order|false
     */
    public function get_child_order( $parent_order, $invoice ) {
        if ( rp_hpos_enabled() ) {
            $args = [
                'meta_query' => [
                    [
                        'key'   => '_reepay_order',
                        'value' => $invoice,
                    ]
                ]
            ];
        } else {
            $args = [
                'meta_key'  => '_reepay_order',
                'meta_value' => $invoice,
                'meta_compare' => '=',
            ];
        }

        $args['parent'] = $parent_order->get_id();

        $query = wc_get_orders( $args );

        return ! empty( $query ) ? $query[0] : false;
    }

    /**
     * @param  array<string, string>  $data
     * @param  string  $status
     */
    public static function create_child_order( $data, $status ) {
        self::log( [
            'log' => [
                'source'  => 'WC_Reepay_Renewals::create_child_order',
                '$data'   => $data,
                '$status' => $status
            ]
        ] );

        $subscription_id = $data['order_id'] ?? 0;

        if ( empty( $subscription_id ) ) {
            $subscription_id = $data['subscription'] ?? 0;
            if ( empty( $subscription_id ) ) {
                self::log( [
                    'log' => [
                        'source' => 'WC_Reepay_Renewals::create_child_order',
                        'error'  => 'Empty subscription id'
                    ]
                ] );
            }
        }

        $parent_order = wc_get_order( $subscription_id );

        if ( empty( $parent_order ) ) {
            $parent_order = self::get_order_by_subscription_handle( $subscription_id );
            if ( empty( $parent_order ) ) {
                self::log( [
                    'log' => [
                        'source' => 'WC_Reepay_Renewals::create_child_order',
                        'info'   => 'Undefined parent order'
                    ]
                ] );
            }
        }

        if ( rp_hpos_enabled() ) {
            $args = [
                'meta_query' => [
                    [
                        'key'   => '_reepay_order',
                        'value' => $data['invoice'],
                    ]
                ]
            ];
        } else {
            $args = [
                'meta_key'  => '_reepay_order',
                'meta_value' => $data['invoice'],
                'meta_compare' => '=',
            ];
        }

        if ( ! empty( $parent_order ) ) {
            $args['parent'] = $parent_order->get_id();
        }

        $query = wc_get_orders( $args );

        if ( ! empty( $query ) ) {
            self::log( [
                'log' => [
                    'source' => 'WC_Reepay_Renewals::create_child_order',
                    'error'  => 'duplicate status - ' . $status
                ]
            ] );

            return;
        }

        self::log( [
            'log' => [
                'source' => 'WC_Reepay_Renewals::create_child_order',
                'data'   => $data,
            ]
        ] );

        if ( ! empty( $parent_order ) ) {
            $parent_order->update_meta_data( '_reepay_order', $data['invoice'] );
            $parent_order->save_meta_data();
            $gateway = rp_get_payment_method( $parent_order );
        }

        $items = array();

        $gateway = ! empty( $gateway ) ? $gateway : 'reepay_checkout';

        if ( function_exists( 'reepay' ) ) {
            $invoice_data = reepay()->api( $gateway )->get_invoice_by_handle( $data['invoice'] );
        }

        if ( empty( $invoice_data ) ) {
            $invoice_data = $gateway->api->get_invoice_by_handle( $data['invoice'] );
        }

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

            // Get discount from order_lines
            $discount_amount = null;
            foreach ($invoice_data['order_lines'] as $discount_line) {
                if ($discount_line['origin'] === 'discount') {
                    $discount_amount = abs(floatval( $discount_line['amount'] ) / 100);
                    break;
                }
            }

            foreach ( $invoice_data['order_lines'] as $invoice_lines ) {
                /*if ($invoice_lines['origin'] == 'discount') {
                    continue;
                }*/

                if ( $invoice_lines['origin'] == 'surcharge_fee' ) {
                    $fees_item = new WC_Order_Item_Fee();
                    $fees_item->set_name( $invoice_lines['ordertext'] );
                    $fees_item->set_amount( floatval( $invoice_lines['unit_amount'] ) / 100 );
                    $fees_item->set_total( floatval( $invoice_lines['amount'] ) / 100 );
                    $fees_item->add_meta_data( '_is_card_fee', true );
                    $new_items[] = $fees_item;
                } elseif( $invoice_lines['origin'] == 'discount' ) {
                    $discount_item = new WC_Order_Item_Coupon();
                    // $discount_item->set_code( $invoice_lines['ordertext'] );
                    $discount_item->set_code( $invoice_lines['origin_handle'] );
                    $discount_item->set_discount( abs(floatval( $invoice_lines['unit_amount'] ) / 100) );
                    $new_items[] = $discount_item;
                } else {
                    $product_item = new WC_Order_Item_Product();
                    $product_item->set_name( $invoice_lines['ordertext'] );
                    $product_item->set_quantity( $invoice_lines['quantity'] );
                    $product_item->set_subtotal( floatval( $invoice_lines['unit_amount'] ) / 100 );
                    if($invoice_lines['origin'] == 'plan'){
                    if ( $discount_amount !== null ){
                        $total = floatval( $invoice_lines['amount'] ) / 100;
                        $total_with_discount = $total - $discount_amount;
                        $product_item->set_total( $total_with_discount );
                        } else {
                            $product_item->set_total( floatval( $invoice_lines['amount'] ) / 100 );
                        }
                    } else {
                        $product_item->set_total( floatval( $invoice_lines['amount'] ) / 100 );
                    }
                    $new_items[] = $product_item;
                }
            }

            $items = $new_items;
        }

        self::log(
            [
                'log' => [
                    'source'        => 'WC_Reepay_Renewals::create_child_order_items',
                    'items_count'   => count( $items ),
                    '$parent_order' => ! empty( $parent_order ) ? $parent_order->get_id() : null,
                    '$data'         => $data,
                    '$invoice_data' => $invoice_data,
                ],
            ]
        );

        if ( ! empty( $parent_order ) ) {
            $customer = $parent_order->get_customer_id();
        } elseif ( $invoice_data['customer'] ) {
            $customer = rp_get_user_id_by_handle( $invoice_data['customer'] );
        } else {
            $customer = $parent_order->get_customer_id();
        }

        self::create_order_copy( [
            'status'       => $status,
            'parent'       => ! empty( $parent_order ) ? $parent_order->get_id() : null,
            'customer_id'  => $customer,
            'subscription' => ! empty( $data['subscription'] ) ? $data['subscription'] : null,
        ], ! empty( $parent_order ) ? $parent_order : false, $items, false, $invoice_data );
    }

    /**
     * @param  array[
     *     'id' => string
     *     'timestamp' => string
     *     'signature' => string
     *     'subscription' => string
     *     'customer' => string
     *     'event_type' => string
     *     'event_id' => string
     * ] $data
     * @param  string  $status
     *
     * @return bool|WP_Error
     */
    public static function update_subscription_status( $data, $status, $update_status = true ) {
        self::log( [
            'log' => [
                'source'  => 'WC_Reepay_Renewals::update_subscription_status',
                '$data'   => $data,
                '$status' => $status
            ]
        ] );

        if ( empty( $data['subscription'] ) ) {
            return new WP_Error( __( 'Undefined subscription handle', 'reepay-subscriptions-for-woocommerce' ) );
        }

        $order = self::get_order_by_subscription_handle( $data['subscription'] );

        if ( ! empty( $order ) ) {
            self::log( [
                'log' => [
                    'source'   => 'WC_Reepay_Renewals::update_subscription_status::order',
                    'order_id' => empty( $order ) ? 0 : $order->get_id(),
                ]
            ] );

            if ( empty( $order ) ) {
                return new WP_Error( __( 'Undefined parent order', 'reepay-subscriptions-for-woocommerce' ) );
            }

            if ( $update_status ) {
                if ( $order->get_status() === $status ) {
                    return new WP_Error( __( 'Duplication of order status', 'reepay-subscriptions-for-woocommerce' ) );
                }

                $order->set_status( $status );
                $order->save();
            }

            self::save_reepay_subscription_dates( $order );
        }

        return true;
    }

    /**
     * @param array $order_args Order arguments.
     * @param WC_Order $main_order Order arguments.
     * @param array<WC_Order_Item> $items
     *
     * @return WC_Order|WP_Error
     * @throws WC_Data_Exception
     */
    public static function create_order_copy(
        $order_args,
        $main_order = false,
        $items = [],
        $calc_taxes = true,
        $invoice_data = false
    ) {
        // clean status to avoid creating order with status 'null' to fix send email new order with empty order items.
        $status_to_set = $order_args['status'];
        $order_args['status'] = 'null';

        $new_order = wc_create_order( $order_args );
        $new_order->save();

        if ( $main_order ) {
            $main_order = wc_get_order( $main_order );
        }

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
            '_reepay_another_orders',
        ];

        $additional_fields_to_copy = [
            'is_vat_exempt',
            'reepay_card_type',
            'reepay_masked_card',
            'reepay_session_id',
            'reepay_token',
            '_reepay_coupon_code',
        ];

        $product_item_fields_to_copy = [
            'addons',
            '_woosb_parent_id',
        ];

        if ( $main_order ) {
            foreach ( $fields_to_copy as $field_name ) {
                if ( $field_name == '_order_key' ) {
                    $main_order_key = $main_order->get_order_key();
                    $new_order->set_order_key( $main_order_key );
                } elseif ( $field_name == '_order_currency' ) {
                    $main_order_currency = $main_order->get_currency();
                    $new_order->set_currency( $main_order_currency );
                } elseif ( $field_name == '_prices_include_tax' ) {
                    $main_order_tax_status = $main_order->get_prices_include_tax();
                    $new_order->set_prices_include_tax( $main_order_tax_status );
                } elseif ( $field_name == '_customer_ip_address' ) {
                    $customer_ip_address = $main_order->get_customer_ip_address();
                    $new_order->set_customer_ip_address( $customer_ip_address );
                } elseif ( $field_name == '_customer_user_agent' ) {
                    $customer_user_agent = $main_order->get_customer_user_agent();
                    $new_order->set_customer_user_agent( $customer_user_agent );
                } elseif ( $field_name == '_billing_city' ) {
                    $customer_billing_city = $main_order->get_billing_city();
                    $new_order->set_billing_city( $customer_billing_city );
                } elseif ( $field_name == '_billing_state' ) {
                    $customer_billing_state = $main_order->get_billing_state();
                    $new_order->set_billing_state( $customer_billing_state );
                } elseif ( $field_name == '_billing_postcode' ) {
                    $customer_billing_postcode = $main_order->get_billing_postcode();
                    $new_order->set_billing_postcode( $customer_billing_postcode );
                } elseif ( $field_name == '_billing_email' ) {
                    $customer_billing_email = $main_order->get_billing_email();
                    $new_order->set_billing_email( $customer_billing_email );
                } elseif ( $field_name == '_billing_phone' ) {
                    $customer_billing_phone = $main_order->get_billing_phone();
                    $new_order->set_billing_phone( $customer_billing_phone );
                } elseif ( $field_name == '_billing_address_1' ) {
                    $customer_billing_address_1 = $main_order->get_billing_address_1();
                    $new_order->set_billing_address_1( $customer_billing_address_1 );
                } elseif ( $field_name == '_billing_address_2' ) {
                    $customer_billing_address_2 = $main_order->get_billing_address_2();
                    $new_order->set_billing_address_2( $customer_billing_address_2 );
                } elseif ( $field_name == '_billing_country' ) {
                    $customer_billing_country = $main_order->get_billing_country();
                    $new_order->set_billing_country( $customer_billing_country );
                } elseif ( $field_name == '_billing_first_name' ) {
                    $customer_billing_first_name = $main_order->get_billing_first_name();
                    $new_order->set_billing_first_name( $customer_billing_first_name );
                } elseif ( $field_name == '_billing_last_name' ) {
                    $customer_billing_last_name = $main_order->get_billing_last_name();
                    $new_order->set_billing_last_name( $customer_billing_last_name );
                } elseif ( $field_name == '_billing_company' ) {
                    $customer_billing_company = $main_order->get_billing_company();
                    $new_order->set_billing_company( $customer_billing_company );
                } elseif ( $field_name == '_billing_first_name' ) {
                    $customer_billing_first_name = $main_order->get_billing_first_name();
                    $new_order->set_billing_first_name( $customer_billing_first_name );
                } elseif ( $field_name == '_billing_last_name' ) {
                    $customer_billing_last_name = $main_order->get_billing_last_name();
                    $new_order->set_billing_last_name( $customer_billing_last_name );
                } elseif ( $field_name == '_shipping_country' ) {
                    $customer_shipping_country = $main_order->get_shipping_country();
                    $new_order->set_shipping_country( $customer_shipping_country );
                } elseif ( $field_name == '_shipping_first_name' ) {
                    $customer_shipping_first_name = $main_order->get_shipping_first_name();
                    $new_order->set_shipping_first_name( $customer_shipping_first_name );
                } elseif ( $field_name == '_shipping_last_name' ) {
                    $customer_shipping_last_name = $main_order->get_shipping_last_name();
                    $new_order->set_shipping_last_name( $customer_shipping_last_name );
                } elseif ( $field_name == '_shipping_company' ) {
                    $customer_shipping_company = $main_order->get_shipping_company();
                    $new_order->set_shipping_company( $customer_shipping_company );
                } elseif ( $field_name == '_shipping_address_1' ) {
                    $customer_shipping_address_1 = $main_order->get_shipping_address_1();
                    $new_order->set_shipping_address_1( $customer_shipping_address_1 );
                } elseif ( $field_name == '_shipping_address_2' ) {
                    $customer_shipping_address_2 = $main_order->get_shipping_address_2();
                    $new_order->set_shipping_address_2( $customer_shipping_address_2 );
                } elseif ( $field_name == '_shipping_city' ) {
                    $customer_shipping_city = $main_order->get_shipping_city();
                    $new_order->set_shipping_city( $customer_shipping_city );
                } elseif ( $field_name == '_shipping_state' ) {
                    $customer_shipping_state = $main_order->get_shipping_state();
                    $new_order->set_shipping_state( $customer_shipping_state );
                } elseif ( $field_name == '_shipping_postcode' ) {
                    $customer_shipping_postcode = $main_order->get_shipping_postcode();
                    $new_order->set_shipping_postcode( $customer_shipping_postcode );
                } elseif ( $field_name == '_payment_method' ) {
                    $customer_payment_method = $main_order->get_payment_method();
                    $new_order->set_payment_method( $customer_payment_method );
                } elseif ( $field_name == '_payment_method_title' ) {
                    $customer_payment_method_title = $main_order->get_payment_method_title();
                    $new_order->set_payment_method_title( $customer_payment_method_title );
                } else {
                    $new_order->update_meta_data( $field_name, $main_order->get_meta( $field_name ) );
                }
            }

            foreach ( $additional_fields_to_copy as $field_name ) {
                $field_value = $main_order->get_meta( $field_name );

                if ( ! empty( $field_value ) ) {
                    $new_order->update_meta_data( $field_name, $field_value );
                }
            }
            $new_order->save_meta_data();
            $new_order->set_currency( $main_order->get_currency() ?? '' );
        } elseif ( ! empty( $invoice_data ) && ! empty( $invoice_data['customer'] ) ) {
            try {
                $customer = $invoice_data['customer'];
                $customer = reepay_s()->api()->request( "customer/$customer" );
            } catch ( Exception $e ) {
                return new WP_Error( __( 'Plan request error' ) );
            }

            //import logic
            $new_order->set_billing_city( $customer['city'] ?? '' );
            $new_order->set_billing_postcode( $customer['postal_code'] ?? '' );
            $new_order->set_billing_email( $customer['email'] ?? '' );
            $new_order->set_billing_phone( $customer['phone'] ?? '' );
            $new_order->set_billing_address_1( $customer['address'] ?? '' );
            $new_order->set_billing_address_2( $customer['address2'] ?? '' );
            $new_order->set_billing_country( $customer['country'] ?? '' );
            $new_order->set_billing_first_name( $customer['first_name'] ?? '' );
            $new_order->set_billing_last_name( $customer['last_name'] ?? '' );
            $new_order->set_billing_company( $customer['company'] ?? '' );

            $new_order->set_payment_method( 'reepay_checkout' );
            $new_order->set_payment_method_title( 'Reepay Checkout' );
            $new_order->add_meta_data( '_reepay_state_authorized', 1 );
            $new_order->set_currency( $invoice_data['currency'] );


            $new_order->update_meta_data( '_order_currency', $invoice_data['currency'] );

            if ( ! empty( $order_args['subscription'] ) ) {
                $subscription = reepay_s()->api()->request( "subscription/{$order_args['subscription']}" );
                if ( ! empty( $invoice_data['handle'] ) ) {
                    $new_order->add_meta_data( '_reepay_order', $invoice_data['handle'] );
                } else {
                    $new_order->add_meta_data( '_reepay_order', $subscription['handle'] );
                }

                $new_order->add_meta_data( '_reepay_subscription_handle_parent', $subscription['handle'] );
                $new_order->add_meta_data('_reepay_is_renewal', 1);
                $new_order->add_meta_data( '_reepay_imported', 1 );

                $plan_data     = reepay_s()->plan()->get_remote_plan_meta( $subscription['plan'] );
                $schedule_type = $plan_data['_reepay_subscription_schedule_type'];
                $schedule_data = $plan_data[ $schedule_type ];

                $new_order->add_meta_data(
                    '_reepay_billing_string',
                    WC_Reepay_Subscription_Plan_Simple::get_billing_plan(
                        array(
                            'type'      => $schedule_type,
                            'type_data' => $schedule_data,
                            'interval'  => ''
                        ),
                        true
                    )
                );
            }
            $new_order->save_meta_data();
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

                foreach ( $item->get_formatted_meta_data('_', true) as $value ) {
                    $product_item->add_meta_data( $value->key, $value->value );
                }

                foreach ( $product_item_fields_to_copy as $field_name ) {
                    $field_value = $item->get_meta( $field_name );

                    if ( ! empty( $field_value ) ) {
                        $product_item->update_meta_data( $field_name, $field_value );
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

            //coupon
            if ( $item->is_type( 'coupon' ) ) {
                $reepay_coupon_code = $main_order->get_meta( '_reepay_coupon_code' );
                $coupon = new WC_Coupon($reepay_coupon_code);
                $coupon_item = new WC_Order_Item_Coupon();
                if ( $coupon->is_type('reepay_type')) {
                    // WC version < 8.7
                    if (defined('WC_VERSION') && version_compare(WC_VERSION, '8.7', '<')) {
                        $coupon_data = $coupon->get_data();
                        $coupon_item->add_meta_data( 'coupon_data', $coupon_data );
                    } else {
                        $coupon_info = $coupon->get_short_info();
                        $coupon_item->add_meta_data( 'coupon_info', $coupon_info );
                    }
                    $coupon_item->set_code( $reepay_coupon_code );
                $coupon_item->set_discount( $item->get_discount() );
                // $coupon_item->set_discount_tax( 0 ); // If there is no tax

                $coupon_item->save();
                $new_order->add_item( $coupon_item );
                }
            }

            //shipping
            if ( $item->is_type( 'shipping' ) ) {
                $shipping_item = new WC_Order_Item_Shipping();
                $shipping_item->set_method_title( $item->get_method_title() );
                $shipping_item->set_method_id( $item->get_method_id() );
                $shipping_item->set_total( $item->get_total() );
                $new_order->add_item( $shipping_item );
            }


            $new_order->calculate_totals( $calc_taxes );
        }

        if ( $main_order ) {
            $main_order->save();
        }

        $new_order->set_status( $status_to_set );
        $new_order->save();
        $new_order->calculate_totals( $calc_taxes );

        return $new_order;
    }

    /**
     * @param  array  $data  @see self::renew_subscription
     *
     * @return true|WP_Error
     */
    public static function change_user_role( $data ) {
        $order = ! empty( $data['subscription'] ) ? self::get_order_by_subscription_handle( $data['subscription'] ) : '';

        if ( empty( $order ) ) {
            return new WP_Error( __( 'Order not found', 'reepay-subscriptions-for-woocommerce' ) );
        }

        $order_item = self::is_order_contain_subscription( $order );
        if ( ! $order_item ) {
            return;
        }
        
        if ( 'subscription_expired' === $data['event_type'] ) {
            /**
             * Set expired subscription user role
             */
            $new_role = get_post_meta( $order_item->get_variation_id() ?: $order_item->get_product_id(),
                '_reepay_subscription_customer_role_expired', true );
        } else {
            /**
             * Set subscription user role
             */
            $new_role = get_post_meta( $order_item->get_variation_id() ?: $order_item->get_product_id(),
                '_reepay_subscription_customer_role', true );
        }

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

        self::log( [
            'log' => [
                'source' => 'WC_Reepay_Renewals::change_user_role',
                'subscription' => $data['subscription'],
                'event_type' => $data['event_type'],
                'order_id' => $order->get_id(),
                'customer_id'   => $customer_id,
                'new_role' => $new_role,
            ]
        ] );

        return true;
    }

    /**
     * @param  mixed  $order
     * @param  array[]|null  $data  - @see https://reference.reepay.com/api/#the-subscription-object
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
     * @param  mixed  $order
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
     * @param  WC_Order  $order
     * @param  string  $customer_handle
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
     * @param  WC_Order  $order
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
     * @param  WC_Order_Item  $order_item
     *
     * @return array
     */
    public static function get_plan_addons( WC_Order_Item $order_item ): array {
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

        if ( empty( $shm_data ) || empty( $shm_data['reepay_shipping_addon_name'] ) ) {
            return [];
        }

        return [
            [
                'name'          => $shm_data['reepay_shipping_addon_name'],
                'description'   => $shm_data['reepay_shipping_addon_description'],
                'type'          => 'on_off',
                'fixed_amount ' => true,
                'amount'        => $shm_data['cost'] ? ($shm_data['cost'] * 100) :  0,
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
     * @param  mixed  $order_id
     *
     * @return void
     */
    private static function lock_order( $order_id ) {
        $order = wc_get_order( $order_id );
        $order->update_meta_data( '_reepay_subscriptions_locked', '1' );
        $order->save_meta_data();
    }

    /**
     * Unlock the order.
     *
     * @param  mixed  $order_id
     *
     * @return void
     */
    private static function unlock_order( $order_id ) {
        $order = wc_get_order( $order_id );
        $order->delete_meta_data( '_reepay_subscriptions_locked' );
    }

    /**
     * Check is order order locked.
     *
     * @param $order_id
     *
     * @return bool
     */
    private static function is_locked( $order_id ) {
        $order = wc_get_order( $order_id );

        return (bool) $order->get_meta( '_reepay_subscriptions_locked' );
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
