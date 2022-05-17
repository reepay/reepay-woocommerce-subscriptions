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
	 * @param $gateway string|WC_Payment_Gateway
	 *
	 * @return bool
	 */
	public static function is_reepay_gateway( $gateway ) {
		return ( is_string( $gateway ) && ! str_contains( $gateway, 'reepay' ) ) ||
		       ( is_object( $gateway ) && ! str_contains( strtolower( get_class( $gateway ) ), 'reepay' ) );
	}

	/**
	 * @return bool
	 */
	public static function is_reepay_product_in_cart() {
		$is_reepay_product_in_cart = false;

		/**
		 * @var $cart_item array Item data
		 */
		foreach ( WC()->cart->get_cart() as $cart_item ) {
			/**
			 * @var $product WC_Product
			 */
			$product = $cart_item['data'];

			if ( str_contains( $product->get_type(), 'reepay' ) ) {
				$is_reepay_product_in_cart = true;
				break;
			}
		}

		return $is_reepay_product_in_cart;
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
