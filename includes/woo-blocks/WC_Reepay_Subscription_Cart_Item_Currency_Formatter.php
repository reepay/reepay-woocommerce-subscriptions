<?php

/**
 * Class WC_Reepay_Subscription_Cart_Item_Currency_Formatter
 *
 * Only ever require()'d from
 * WC_Reepay_Subscription_Currency::register_cart_item_currency_formatter(),
 * once the Store API classes it extends are guaranteed to be loaded.
 *
 * @since 1.0.0
 */
class WC_Reepay_Subscription_Cart_Item_Currency_Formatter extends \Automattic\WooCommerce\StoreApi\Formatters\CurrencyFormatter {
	/**
	 * Currency of the cart item the Store API is currently building a
	 * response for. Set by
	 * WC_Reepay_Subscription_Currency::track_current_cart_item_currency()
	 * and cleared by
	 * WC_Reepay_Subscription_Currency::untrack_current_cart_item_currency().
	 * Null means "use the shop's normal currency resolution".
	 *
	 * @var string|null
	 */
	public static $current_item_currency = null;

	/**
	 * Same as the parent CurrencyFormatter, except that when a per-item
	 * currency is being tracked, every field is stamped with that currency
	 * instead of get_woocommerce_currency(). This is what lets each Cart
	 * block line item show its own product's currency - core's formatter
	 * always uses a single shop-wide currency for every item in the
	 * response, with no per-item override.
	 *
	 * @param array $value   Value to format.
	 * @param array $options Options that influence the formatting.
	 *
	 * @return array
	 */
	public function format( $value, array $options = array() ) {
		if ( empty( self::$current_item_currency ) ) {
			return parent::format( $value, $options );
		}

		$currency = self::$current_item_currency;
		$position = get_option( 'woocommerce_currency_pos' );
		$symbol   = html_entity_decode( get_woocommerce_currency_symbol( $currency ) );
		$prefix   = '';
		$suffix   = '';

		switch ( $position ) {
			case 'left_space':
				$prefix = $symbol . ' ';
				break;
			case 'left':
				$prefix = $symbol;
				break;
			case 'right_space':
				$suffix = ' ' . $symbol;
				break;
			case 'right':
				$suffix = $symbol;
				break;
		}

		return array_merge(
			(array) $value,
			array(
				'currency_code'               => $currency,
				'currency_symbol'             => $symbol,
				'currency_minor_unit'         => wc_get_price_decimals(),
				'currency_decimal_separator'  => wc_get_price_decimal_separator(),
				'currency_thousand_separator' => wc_get_price_thousand_separator(),
				'currency_prefix'             => $prefix,
				'currency_suffix'             => $suffix,
			)
		);
	}
}
