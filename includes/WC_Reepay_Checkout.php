<?php

/**
 * Class WC_Reepay_Checkout
 *
 * @since 1.0.0
 */
class WC_Reepay_Checkout {

	/**
	 * Single payment methods that must always be treated as subscription capable.
	 *
	 * Maps gateway id => subscription features. Used as a safety net for Frisbii Pay
	 * versions whose gateway class does not declare these features itself (BWSM-117).
	 *
	 * @var array<string, string[]>
	 */
	const FORCED_SUBSCRIPTION_SUPPORTS = [
		'reepay_klarna' => [
			'subscriptions',
			'subscription_cancellation',
			'subscription_suspension',
			'subscription_reactivation',
			'subscription_amount_changes',
			'subscription_date_changes',
			'subscription_payment_method_change',
			'subscription_payment_method_change_customer',
			'subscription_payment_method_change_admin',
			'multiple_subscriptions',
		],
	];

	/**
	 * Constructor
	 */
	public function __construct() {
		add_filter( 'woocommerce_payment_gateway_supports', [ $this, 'gateway_supports_subscription_features' ], 10, 3 );
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
	 * Report subscription features for gateways listed in FORCED_SUBSCRIPTION_SUPPORTS.
	 *
	 * Hooked on `woocommerce_payment_gateway_supports` so it is evaluated lazily on each
	 * `$gateway->supports()` call, without instantiating gateways early.
	 *
	 * @param bool               $supported Whether the gateway supports the feature.
	 * @param string             $feature   Feature being checked.
	 * @param WC_Payment_Gateway $gateway   Gateway instance.
	 *
	 * @return bool
	 */
	public function gateway_supports_subscription_features( $supported, $feature, $gateway ) {
		if ( $supported || ! is_object( $gateway ) || empty( $gateway->id ) ) {
			return $supported;
		}

		$forced = self::FORCED_SUBSCRIPTION_SUPPORTS[ $gateway->id ] ?? [];

		return in_array( $feature, $forced, true ) ? true : $supported;
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
