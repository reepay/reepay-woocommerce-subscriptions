<?php

/**
 * Class WC_Reepay_Checkout
 *
 * @since 1.0.0
 */
class WC_Reepay_Checkout {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_filter( 'woocommerce_payment_gateways', [ $this, 'woocommerce_payment_gateways' ], PHP_INT_MAX );
	}

	/**
	 * If in checkout we have at least one Reepay subscription product we will show only Reepay payment method
	 *
	 * @param $gateways
	 *
	 * @return mixed
	 */
	public function woocommerce_payment_gateways( $gateways ) {
		if ( ! is_checkout() || ! WC_Reepay_Helpers::is_reepay_product_in_cart() ) {
			return $gateways;
		}

		foreach ( $gateways as $gateway_num => $gateway ) {
			if ( WC_Reepay_Helpers::is_reepay_gateway( $gateway ) ) {
				unset( $gateways[ $gateway_num ] );
			}
		}

		return $gateways;
	}
}

new WC_Reepay_Checkout();
