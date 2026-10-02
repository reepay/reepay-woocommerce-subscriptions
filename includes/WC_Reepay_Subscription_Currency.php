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
		add_filter( 'woocommerce_cart_product_price', [ $this, 'maybe_change_item_price_currency' ], 10, 2 );
		add_filter( 'woocommerce_cart_product_subtotal', [ $this, 'maybe_change_item_subtotal_currency' ], 10, 4 );
		add_action( 'woocommerce_check_cart_items', [ $this, 'restrict_cart_to_another_currency' ] );

		// Run after WCML's currency filter so this can override it for cart/checkout.
		// Other contexts keep the currency already decided by WCML/WooCommerce.
		// Only applies when the cart contains products with a single currency.
		add_filter( 'woocommerce_currency', [ $this, 'maybe_override_cart_checkout_currency' ], 20 );

		// Block-based Cart/Checkout uses the Store API, so classic cart price
		// filters do not apply. Register a custom formatter so each cart item
		// can use its product's currency.
		// Register it directly because 'woocommerce_blocks_loaded' has already
		// fired before this plugin is initialized.
		$this->register_cart_item_currency_formatter();
	}

	/**
	 * Replaces the Store API's registered 'currency' formatter with one that
	 * respects a per-item currency override (see
	 * WC_Reepay_Subscription_Cart_Item_Currency_Formatter), and registers our
	 * own cart-item endpoint data so we have a reliable "this item is done"
	 * signal to clear that override again (see
	 * untrack_current_cart_item_currency()).
	 *
	 * @return void
	 */
	public function register_cart_item_currency_formatter() {
		if ( ! class_exists( '\Automattic\WooCommerce\StoreApi\StoreApi' ) ) {
			return;
		}

		if ( ! class_exists( 'WC_Reepay_Subscription_Cart_Item_Currency_Formatter' ) ) {
			require_once __DIR__ . '/woo-blocks/WC_Reepay_Subscription_Cart_Item_Currency_Formatter.php';
		}

		try {
			\Automattic\WooCommerce\StoreApi\StoreApi::container()
				->get( \Automattic\WooCommerce\StoreApi\Formatters::class )
				->register( 'currency', WC_Reepay_Subscription_Cart_Item_Currency_Formatter::class );
		} catch ( \Exception $e ) {
			return;
		}

		add_filter( 'woocommerce_cart_item_permalink', [ $this, 'track_current_cart_item_currency' ], 1, 2 );

		woocommerce_store_api_register_endpoint_data(
			array(
				'endpoint'      => \Automattic\WooCommerce\StoreApi\Schemas\V1\CartItemSchema::IDENTIFIER,
				'namespace'     => 'reepay_subscription_currency',
				'data_callback' => [ $this, 'untrack_current_cart_item_currency' ],
				'schema_type'   => ARRAY_A,
			)
		);
	}

	/**
	 * Fires early in CartItemSchema::get_item_response(), before its "prices"
	 * and "totals" fields are built for the item being serialized right now -
	 * the permalink filter is the earliest per-item hook core exposes there.
	 * Used purely as a "this item is starting" signal; the permalink itself
	 * is passed through unchanged.
	 *
	 * @param string $permalink Product permalink.
	 * @param array  $cart_item Cart item array.
	 *
	 * @return string
	 */
	public function track_current_cart_item_currency( $permalink, $cart_item ) {
		$product = $cart_item['data'] ?? null;

		if ( $product instanceof WC_Product ) {
			$default_currency = get_option( 'woocommerce_currency' );
			$product_currency = self::get_product_currency( $product, $default_currency );

			WC_Reepay_Subscription_Cart_Item_Currency_Formatter::$current_item_currency =
				$product_currency !== $default_currency ? $product_currency : null;
		}

		return $permalink;
	}

	/**
	 * Fires last in CartItemSchema::get_item_response() (our own registered
	 * endpoint data is always the final field built for an item), i.e. after
	 * its "prices"/"totals" already used the tracked currency above. Clears
	 * the override so the cart-level aggregate totals built right after the
	 * item loop finishes fall back to the normal, mixed-cart-aware currency
	 * instead of leaking the last item's currency onto the whole cart.
	 *
	 * @return array
	 */
	public function untrack_current_cart_item_currency() {
		WC_Reepay_Subscription_Cart_Item_Currency_Formatter::$current_item_currency = null;

		return array();
	}

	/**
	 * Override get_woocommerce_currency() with the cart's real (product-fixed)
	 * currency, but only within cart/checkout contexts - classic pages or the
	 * Store API's cart/checkout/batch REST routes. Deliberately scoped this
	 * narrowly so it doesn't leak onto unrelated pages (e.g. the shop/PLP
	 * product grid, which also goes through the Store API) for any visitor
	 * who happens to have one of these products in their cart.
	 *
	 * Skipped entirely when the cart mixes multiple currencies: checkout is
	 * already blocked in that state (see restrict_cart_to_another_currency()),
	 * and forcing one currency onto the aggregate total/order would just
	 * mislabel a sum of different currencies as if it were a single one.
	 * Per-item price/subtotal columns stay correct regardless (see
	 * maybe_change_item_price_currency()/maybe_change_item_subtotal_currency()),
	 * since those use each product's own currency, not this global override.
	 *
	 * @param string $currency Currency code WooCommerce/WCML would otherwise use.
	 *
	 * @return string
	 */
	public function maybe_override_cart_checkout_currency( $currency ) {
		if ( empty( WC() ) || empty( WC()->cart ) || ! $this->is_cart_or_checkout_context() ) {
			return $currency;
		}

		if ( self::cart_have_many_currencies() ) {
			return $currency;
		}

		return self::get_real_currency( $currency );
	}

	/**
	 * Fix the currency shown in the cart page's per-item "Price" column,
	 * which uses WC_Cart::get_product_price(). Always uses this specific
	 * product's own currency, independent of maybe_override_cart_checkout_currency()'s
	 * aggregate override, so a mixed cart still shows each item correctly
	 * (e.g. one product in EUR, another in the shop's native DKK,
	 * simultaneously) rather than relabeling everything to one currency.
	 *
	 * @param string     $price_html Price column HTML.
	 * @param WC_Product $product    Cart line item product.
	 *
	 * @return string
	 */
	public function maybe_change_item_price_currency( $price_html, $product ) {
		$default_currency = get_option( 'woocommerce_currency' );
		$product_currency = self::get_product_currency( $product, $default_currency );

		if ( $product_currency === $default_currency ) {
			return $price_html;
		}

		$cart          = WC()->cart;
		$product_price = $cart->display_prices_including_tax()
			? wc_get_price_including_tax( $product )
			: wc_get_price_excluding_tax( $product );

		return wc_price( $product_price, array( 'currency' => $product_currency ) );
	}

	/**
	 * Fix the currency shown in the cart page's per-item "Total" column,
	 * which uses WC_Cart::get_product_subtotal() - same gap as above.
	 *
	 * @param string     $subtotal_html Line total HTML.
	 * @param WC_Product $product       Cart line item product.
	 * @param int        $quantity      Line item quantity.
	 * @param WC_Cart    $cart          Current cart.
	 *
	 * @return string
	 */
	public function maybe_change_item_subtotal_currency( $subtotal_html, $product, $quantity, $cart ) {
		$default_currency = get_option( 'woocommerce_currency' );
		$product_currency = self::get_product_currency( $product, $default_currency );

		if ( $product_currency === $default_currency ) {
			return $subtotal_html;
		}

		if ( $product->is_taxable() ) {
			$row_price = $cart->display_prices_including_tax()
				? wc_get_price_including_tax( $product, array( 'qty' => $quantity ) )
				: wc_get_price_excluding_tax( $product, array( 'qty' => $quantity ) );
		} else {
			$row_price = (float) $product->get_price() * (float) $quantity;
		}

		return wc_price( $row_price, array( 'currency' => $product_currency ) );
	}

	/**
	 * Deliberately avoids is_cart()/is_checkout() - those conditional tags are
	 * unsafe here because this filter also fires during requests where the
	 * main WP query hasn't run yet (e.g. the classic wc-ajax=update_order_review
	 * request that refreshes the checkout review table). Calling them there
	 * triggers a PHP notice ("Conditional query tags do not work before the
	 * query is run") that gets echoed into what must be a pure AJAX/JSON
	 * response, corrupting it and leaving the checkout stuck mid-refresh.
	 * Everything below is based only on the current request itself, so it's
	 * safe at any point in the request lifecycle.
	 *
	 * @return bool
	 */
	protected function is_cart_or_checkout_context() {
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST && ! empty( $_SERVER['REQUEST_URI'] ) ) {
			return (bool) preg_match( '#/wc/store/v1/(cart|checkout|batch)#', wp_unslash( $_SERVER['REQUEST_URI'] ) );
		}

		if ( isset( $_REQUEST['wc-ajax'] ) ) {
			return in_array(
				wp_unslash( $_REQUEST['wc-ajax'] ),
				array( 'update_order_review', 'checkout', 'get_refreshed_fragments', 'apply_coupon', 'remove_coupon', 'update_shipping_method', 'get_cart_totals' ),
				true
			);
		}

		if ( is_admin() || empty( $_SERVER['REQUEST_URI'] ) ) {
			return false;
		}

		$path          = wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH );
		$cart_page     = get_permalink( wc_get_page_id( 'cart' ) );
		$checkout_page = get_permalink( wc_get_page_id( 'checkout' ) );

		foreach ( array( $cart_page, $checkout_page ) as $page_url ) {
			if ( $page_url && $path && trailingslashit( $path ) === trailingslashit( wp_parse_url( $page_url, PHP_URL_PATH ) ) ) {
				return true;
			}
		}

		return false;
	}

	public function restrict_cart_to_another_currency() {
		if ( self::cart_have_many_currencies() ) {
			wc_add_notice( __( 'You are not allowed to checkout products with different currencies, please remove product with other currency.' ),
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

		if ( $real_currency !== $default_currency && ! self::cart_have_many_currencies() ) {
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
				return WC_Product_Reepay_Variable_Subscription::get_currency( $product, $currency );
			}

			return $product->get_currency( $currency );
		}

		return $currency;
	}
}
