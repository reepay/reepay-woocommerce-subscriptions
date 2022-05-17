<?php

class WC_Reepay_Checkout {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_filter( 'woocommerce_payment_gateways', array( $this, 'woocommerce_payment_gateways' ), PHP_INT_MAX );
	}

	/**
	 * If in checkout we have at least one Reepay subscription product we will show only Reepay payment method
	 *
	 * @param $gateways
	 *
	 * @return mixed
	 */
	public function woocommerce_payment_gateways( $gateways ) {
		if ( ! $this->is_reepay_product_in_cart() ) {
			return $gateways;
		}

		foreach ( $gateways as $gateway_num => $gateway ) {
			if ( $this->is_reepay_gateway( $gateway ) ) {
				unset( $gateways[ $gateway_num ] );
			}
		}

		return $gateways;
	}

	/**
	 * @param $gateway string|WC_Payment_Gateway
	 *
	 * @return bool
	 */
	private function is_reepay_gateway( $gateway ) {
		return ( is_string( $gateway ) && ! str_contains( $gateway, 'reepay' ) ) ||
		       ( is_object( $gateway ) && ! str_contains( strtolower( get_class( $gateway ) ), 'reepay' ) );
	}

	/**
	 * @return bool
	 */
	private function is_reepay_product_in_cart() {
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
}

new WC_Reepay_Checkout();
