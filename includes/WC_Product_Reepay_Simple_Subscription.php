<?php
class WC_Product_Reepay_Simple_Subscription extends WC_Product_Simple {
    public function get_type() {
        return 'reepay_simple_subscriptions';
    }

	/**
	 * Returns the price in html format.
	 *
	 * @param string $deprecated Deprecated param.
	 *
	 * @return string
	 */
	public function get_price_html( $deprecated = '' ) {
		return self::format_price(parent::get_price_html(), $this);
	}

	/**
	 * @param string $price_html
	 * @param WC_Product $product
	 * @return string
	 */
	public static function format_price($price_html, $product) {
		$schedule_type = WC_Reepay_Subscription_Plan_Simple::get_billing_plan( $product, true );

		if ( empty( $schedule_type ) || empty( $price_html ) ) {
			return $price_html;
		}

		return $price_html  . ' / ' . $schedule_type;
	}
}