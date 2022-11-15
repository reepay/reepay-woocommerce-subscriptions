<?php

/**
 * Class WC_Reepay_Woocommerce_Subscription_Extension
 *
 * @since 1.0.0
 */
class WC_Reepay_Woocommerce_Subscription_Extension {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_filter( 'woocommerce_get_order_item_totals', [ $this, 'clean_order_totals' ], 100, 3 );
	}

	/**
	 * Remove total and subtotal rows from email
	 *
	 * @param  array  $total_rows  Total rows to display
	 * @param  WC_Order  $order  Current order
	 * @param  bool  $tax_display  Report or not taxes
	 */
	public function clean_order_totals( $total_rows, $order, $tax_display ) {
		if ( did_action( 'woocommerce_email_before_order_table' ) && wcs_order_contains_subscription( $order, 'any' ) ) {
			unset( $total_rows['cart_subtotal'] );
			unset( $total_rows['order_total'] );
		}

		return $total_rows;
	}
}