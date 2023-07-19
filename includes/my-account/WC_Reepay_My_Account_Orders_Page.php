<?php

class WC_Reepay_My_Account_Orders_Page {
	public function __construct() {
		add_filter( 'woocommerce_account_orders_columns', [ $this, 'add_column_to_account_orders' ], 2, 10 );
		add_filter( 'woocommerce_my_account_my_orders_column_order_type', [ $this, 'add_order_type_to_account_orders' ],
			2, 10 );
		add_filter( 'woocommerce_get_formatted_order_total', [ $this, 'show_zero_order_total_on_account_orders' ], 10,
			2 );
	}

	/**
	 * @param  array  $columns
	 */
	public function add_column_to_account_orders( $columns ) {
		$columns['order_type'] = __( 'Order type', 'reepay-subscriptions-for-woocommerce' );

		return $columns;
	}

	/**
	 * @param  WC_Order  $order
	 */
	public function add_order_type_to_account_orders( $order ) {
		$type = '';

		if ( $order->get_meta( '_reepay_subscription_handle' ) ) {
			$type = __( 'Subscription', 'reepay-subscriptions-for-woocommerce' );
		} elseif ( class_exists( 'WC_Subscriptions_Product' ) ) {
			$order_items = $order->get_items();

			if ( ! empty( $order_items ) ) {
				$product = current( $order_items )->get_product();
				if ( WC_Subscriptions_Product::is_subscription( $product ) ) {
					$type = __( 'Subscription', 'reepay-subscriptions-for-woocommerce' );
				} elseif ( ! empty( $order->get_meta( '_reepay_order' ) ) && ( $order->get_parent_id() != 0 || ! empty( $order->get_meta( '_reepay_renewal' ) ) ) ) {
					$type = __( 'Renewal', 'reepay-subscriptions-for-woocommerce' );
				} else {
					$type = __( 'Order', 'reepay-subscriptions-for-woocommerce' );
				}
			} else {
				$type = __( 'Order', 'reepay-subscriptions-for-woocommerce' );
			}
		} else {
			$type = __( 'Order', 'reepay-subscriptions-for-woocommerce' );
		}

		echo '<span>' . $type . '</span>';
	}

	public function show_zero_order_total_on_account_orders( $formatted_total, $order ) {
		$order_items = $order->get_items();
		if ( ! empty( $order_items ) && ! empty( current( $order_items ) ) ) {
			$product = current( $order_items )->get_product();
			if ( WC_Subscriptions_Product::is_subscription( $product ) ) {
				return wc_price( 0 );
			}
		}

		return $formatted_total;
	}
}