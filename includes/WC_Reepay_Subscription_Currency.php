<?php

/**
 * Class WC_Reepay_Subscription_Currency
 *
 * @since 1.0.0
 */
class WC_Reepay_Subscription_Currency {
	/**
	 * Constructor
	 */
	public function __construct() {
		add_filter( 'woocommerce_cart_subtotal', [ $this, 'maybe_change_currency' ], 10, 3 );
		add_action( 'woocommerce_check_cart_items', [ $this, 'restrict_cart_to_another_currency' ] );
	}

	public function restrict_cart_to_another_currency() {
		if ( self::cart_have_many_currencies() ) {
			wc_add_notice( __( 'You are not allowed to checkout few products with different currencies, please remove product with other currency.' ),
				'error' );
		}
	}

	public static function cart_have_many_currencies() {
		global $woocommerce;
		$cart_contents   = $woocommerce->cart->get_cart();
		$cart_item_keys  = array_keys( $cart_contents );
		$cart_item_count = count( $cart_item_keys );

		// Do nothing if the cart is empty
		// Do nothing if the cart only has one item
		if ( ! $cart_contents || $cart_item_count == 1 ) {
			return false;
		}

		$currencies = [];
		if ( ! empty( WC() ) && ! empty( WC()->cart ) ) {
			foreach ( WC()->cart->get_cart() as $cart_item ) {
				$product      = $cart_item['data'];
				$currencies[] = self::get_product_currency( $product, get_option( 'woocommerce_currency' ) );
			}
		}

		if ( ! empty( $currencies ) ) {
			$currencies = array_unique( $currencies );
			if ( count( $currencies ) > 1 ) {
				return true;
			}
		}

		return false;
	}

	public function maybe_change_currency( $cart_subtotal, $compound, $cart ) {
		$default_currency = get_woocommerce_currency();
		$real_currency    = self::get_real_currency( $default_currency );

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

	public static function get_real_currency( $currency ) {
		if ( ! empty( WC() ) && ! empty( WC()->cart ) ) {
			foreach ( WC()->cart->get_cart() as $cart_item ) {
				$product          = $cart_item['data'];
				$product_currency = self::get_product_currency( $product, $currency );
				if ( $product_currency !== $currency ) {
					return $product_currency;
				}
			}
		}

		return $currency;
	}

	public static function get_product_currency( $product, $currency ) {
		if ( WC_Reepay_Checkout::is_reepay_product( $product ) ) {
			if ( get_class( $product ) == 'WC_Product_Variation' ) {
				return WC_Product_Reepay_Variable_Subscription::get_currency( $product,
					$currency );
			} else {
				return $product->get_currency( $currency );
			}
		}

		return $currency;
	}
}