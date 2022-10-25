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
		add_filter( 'wcs_cart_have_subscription', [ $this, 'is_reepay_product_in_cart' ] );
		add_filter( 'wcs_cart_only_subscriptions', [ $this, 'only_reepay_product_in_cart' ] );
	}

	/**
	 * If in checkout we have at least one Reepay subscription product we will show only Reepay payment method
	 *
	 * @param $gateways
	 *
	 * @return mixed
	 */
	public function woocommerce_payment_gateways( $gateways ) {
		if ( ! is_checkout() || ! self::is_reepay_product_in_cart() ) {
			return $gateways;
		}

		foreach ( $gateways as $gateway_num => $gateway ) {
			if ( ! self::is_reepay_gateway( $gateway ) ) {
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
	public static function is_reepay_gateway( $gateway ) {
		return ( is_string( $gateway ) && str_contains( $gateway, 'reepay' ) ) ||
		       ( is_object( $gateway ) && str_contains( strtolower( get_class( $gateway ) ), 'reepay' ) );
	}

	/**
	 * @return bool
	 */
	public static function is_reepay_product_in_cart() {
		$is_reepay_product_in_cart = false;

		/**
		 * @var $cart_item array Item data
		 */

		if ( ! is_null( WC()->cart ) ) {
			foreach ( WC()->cart->get_cart() as $cart_item ) {
				if ( self::is_reepay_product( $cart_item['data'] ) ) {
					$is_reepay_product_in_cart = true;
					break;
				}
			}
		}


		return $is_reepay_product_in_cart;
	}

	/**
	 * @return bool
	 */
	public static function only_reepay_product_in_cart( $is_only ) {

		if ( $is_only ) {
			return $is_only;
		}

		$have_product = false;

		if ( self::is_reepay_product_in_cart() ) {
			/**
			 * @var $cart_item array Item data
			 */
			foreach ( WC()->cart->get_cart() as $cart_item ) {
				if ( ! self::is_reepay_product( $cart_item['data'] ) ) {
					$have_product = true;
					break;
				}
			}
		} else {
			$have_product = true;
		}


		return ! $have_product;
	}

	/**
	 * @param mixed $product
	 *
	 * @return bool
	 */
	public static function is_reepay_product( $product ) {
		$product = wc_get_product( $product );

		if ( $product->is_type( 'variation' ) ) {
			$product = wc_get_product( $product->get_parent_id() );
		}

		return str_contains( $product->get_type(), 'reepay' );
	}
}
