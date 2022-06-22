<?php

/**
 * Class WC_Reepay_Helpers
 *
 * @since 1.0.0
 */
class WC_Reepay_Helpers {

	/**
	 * Constructor
	 */
	private function __construct() {
	}

	/**
	 * @param  mixed  $order  Post object or post ID of the order.
	 *
	 * @return bool
	 */
	public static function is_order_paid_via_reepay( $order = null ) {
		return self::is_reepay_gateway( $order->get_payment_method() );
	}
}
