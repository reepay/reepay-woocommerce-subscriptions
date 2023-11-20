<?php

class WC_Product_Reepay_Simple_Subscription extends WC_Product_Simple {
	public function get_type() {
		return 'reepay_simple_subscriptions';
	}

	/**
	 * Returns the price in html format.
	 *
	 * @param  string  $deprecated  Deprecated param.
	 *
	 * @return string
	 */
	public function get_price_html( $deprecated = '' ) {
		$args['currency'] = $this->get_currency();

		$price = wc_price( wc_get_price_to_display( $this ),
				$args ) . $this->get_price_suffix();

		$price = apply_filters( 'woocommerce_get_price_html', $price, $this );

		return self::format_price( $price, $this );
	}

	public function get_currency( $currency = '' ) {
		if ( empty( $currency ) ) {
			$currency = get_woocommerce_currency();
		}

		if ( ! empty( $this->get_meta( '_reepay_subscription_currency' ) ) ) {
			$currency = $this->get_meta( '_reepay_subscription_currency' );
		}

		return $currency;
	}

	/**
	 * @param  string  $price_html
	 * @param  WC_Product  $product
	 *
	 * @return string
	 */
	public static function format_price( $price_html, $product ) {
		$schedule_type = WC_Reepay_Subscription_Plan_Simple::get_billing_plan( $product, true );

		if ( empty( $schedule_type ) || empty( $price_html ) ) {
			return $price_html;
		}

		/*$fee = $product->get_meta('_reepay_subscription_fee');
		if ( ! empty($fee) && ! empty($fee['enabled']) && $fee['enabled'] == 'yes') {
			$schedule_type .= sprintf(
				__(' and a %s %s',
					'reepay-subscriptions-for-woocommerce'),
				wc_price($fee["amount"]), $fee["text"]
			);
		}*/

		return $price_html . ' / ' . $schedule_type;
	}
}