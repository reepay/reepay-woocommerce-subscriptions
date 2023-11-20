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
		add_filter( 'wcs_cart_only_subscriptions', [ $this, 'only_subscriptions_in_cart' ] );
		add_filter( 'wcr_cart_only_reepay_subscriptions', [ $this, 'only_reepay_products_in_cart' ] );
		add_filter( 'woocommerce_cart_subtotal', [ $this, 'maybe_change_currency' ], 10, 3 );
	}

	public function maybe_change_currency( $cart_subtotal, $compound, $cart ) {
		$default_currency = get_woocommerce_currency();
		$real_currency    = WC_Reepay_Subscription_Plan_Simple::get_real_currency( $default_currency );

		if ( $real_currency !== $default_currency ) {
			if ( $compound ) {
				$cart_subtotal = wc_price( $cart->get_cart_contents_total() + $cart->get_shipping_total() + $cart->get_taxes_total( false,
						false ), array( 'currency' => $real_currency ) );
			} elseif ( $cart->display_prices_including_tax() ) {
				$cart_subtotal = wc_price( $cart->get_subtotal() + $cart->get_subtotal_tax(),
					array( 'currency' => $real_currency ) );

				if ( $cart->get_subtotal_tax() > 0 && ! wc_prices_include_tax() ) {
					$cart_subtotal .= ' <small class="tax_label">' . WC()->countries->inc_tax_or_vat() . '</small>';
				}
			} else {
				$cart_subtotal = wc_price( $cart->get_subtotal(), array( 'currency' => $real_currency ) );

				if ( $cart->get_subtotal_tax() > 0 && wc_prices_include_tax() ) {
					$cart_subtotal .= ' <small class="tax_label">' . WC()->countries->ex_tax_or_vat() . '</small>';
				}
			}
		}


		return $cart_subtotal;
	}

	/**
	 * If in checkout we have at least one Reepay subscription product we will show only Reepay payment method
	 *
	 * @param $gateways
	 *
	 * @return mixed
	 */
	public function woocommerce_payment_gateways( $gateways ) {
		if ( isset( $GLOBALS['wp_query'] ) && is_checkout() && self::is_reepay_product_in_cart() ) {
			foreach ( $gateways as $gateway_num => $gateway ) {
				if ( ! self::is_reepay_gateway( $gateway ) ) {
					unset( $gateways[ $gateway_num ] );
				}
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
	public static function only_subscriptions_in_cart( $is_only ) {
		if ( $is_only ) {
			return $is_only;
		}

		/**
		 * @var $cart_item array Item data
		 */
		foreach ( WC()->cart->get_cart() as $cart_item ) {
			if ( ! self::is_reepay_product( $cart_item['data'] ) && ! wcs_is_subscription_product( $cart_item['data'] ) ) {
				return false;
			}
		}


		return true;
	}

	/**
	 * @return bool
	 */
	public static function only_reepay_products_in_cart( $is_only ) {
		if ( $is_only ) {
			return $is_only;
		}

		/**
		 * @var $cart_item array Item data
		 */
		foreach ( WC()->cart->get_cart() as $cart_item ) {
			if ( ! self::is_reepay_product( $cart_item['data'] ) ) {
				return false;
			}
		}


		return true;
	}

	/**
	 * @param  mixed  $product
	 *
	 * @return bool
	 */
	public static function is_reepay_product( $product = false ) {
		$product = wc_get_product( $product );

		if ( empty( $product ) ) {
			return false;
		}

		if ( $product->is_type( 'variation' ) ) {
			$product = wc_get_product( $product->get_parent_id() );
		}

		return str_contains( $product->get_type(), 'reepay' );
	}
}
