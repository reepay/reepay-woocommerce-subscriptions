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
		add_filter( 'woocommerce_checkout_registration_required', [ $this, 'require_registration_during_checkout' ] );
	}

	/**
	 * Enables the 'registeration required' (guest checkout) setting when purchasing subscriptions.
	 *
	 * @param  bool  $account_required  Whether an account is required to checkout.
	 *
	 * @return bool
	 * @since 3.1.0
	 *
	 */
	public static function require_registration_during_checkout( $account_required ): bool {
		if ( self::is_reepay_product_in_cart() && ! is_user_logged_in() ) {
			$account_required = true;
		}

		return $account_required;
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
	public static function only_reepay_products_in_cart( $is_only ): bool {
		if ( $is_only ) {
			return $is_only;
		}

		/**
		 * @var array $cart_item item data.
		 */
		foreach ( WC()->cart->get_cart() as $cart_item ) {
			// skip WPC Product Bundles
			if ( ! self::is_reepay_product( $cart_item['data'], [ 'woosb' ] ) ) {
				return false;
			}
		}

		return true;
	}
	
	/**
	 * Check product is Reepay product
	 *
	 * @param mixed $product
	 * @param string[] $skip_types
	 *
	 * @return bool
	 */
	public static function is_reepay_product( $product = false, array $skip_types = [] ): bool {
		$product = wc_get_product( $product );
		
		if ( empty( $product ) ) {
			return false;
		}
		
		$type = $product->get_type();
		if ( in_array( $type, $skip_types ) ) {
			return true;
		}
		
		if ( $product->is_type( 'variation' ) ) {
			$product = wc_get_product( $product->get_parent_id() );
			$type = $product->get_type();
		}
		
		return str_contains( $type, 'reepay' );
	}
}
