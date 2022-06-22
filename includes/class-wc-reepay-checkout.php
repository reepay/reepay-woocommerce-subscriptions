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
        foreach ( WC()->cart->get_cart() as $cart_item ) {
            /**
             * @var $product WC_Product
             */
            $product = $cart_item['data'];

            if ( $product->is_type( 'variation' ) ) {
                $product = wc_get_product( $product->get_parent_id() );
            }

            if ( str_contains( $product->get_type(), 'reepay' ) ) {
                $is_reepay_product_in_cart = true;
                break;
            }
        }

        return $is_reepay_product_in_cart;
    }
}

new WC_Reepay_Checkout();
