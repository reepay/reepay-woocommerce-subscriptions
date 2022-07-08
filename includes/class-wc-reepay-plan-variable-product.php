<?php
class WC_Product_Reepay_Variable_Subscription extends WC_Product_Variable {
    public function __construct( $product ) {
        parent::__construct( $product );

        $this->data_store = WC_Data_Store::load( 'product-variable' );
    }

    public function get_type() {
        return 'reepay_variable_subscriptions';
    }

    /**
     * Auto-load in-accessible properties on demand.
     *
     * @param mixed $key
     * @return mixed
     */
    public function __get( $key ) {

        $value = wcs_product_deprecated_property_handler( $key, $this );

        // No matching property found in wcs_product_deprecated_property_handler()
        if ( is_null( $value ) ) {
            $value = parent::__get( $key );
        }

        return $value;
    }

	/**
	 * Returns the price in html format.
	 *
	 * @param string $price Price (default: '').
	 *
	 * @return string
	 */
	public function get_price_html( $price = '' ) {
		$prices = $this->get_min_max_price_variations();

		if ( is_null( $prices['min'] ) ) {
			$price = apply_filters( 'woocommerce_variable_empty_price_html', '', $this );
		} else {
			if ( $prices['min'] !== $prices['max'] ) {
				$price = wc_format_price_range(
					wc_price( $prices['min'] ) . ' / ' . $prices['min_schedule_type'],
					wc_price( $prices['max'] ) . ' / ' . $prices['max_schedule_type']
				);
			} else {
				$price = wc_price( $prices['min'] ) . ' / ' . $prices['min_schedule_type'];
			}

			$price = apply_filters( 'woocommerce_variable_price_html', $price . $this->get_price_suffix(), $this );
		}

		return apply_filters( 'woocommerce_get_price_html', $price, $this );
	}

	protected function get_min_max_price_variations() {
		$result = [
			'min' => null,
			'max' => null,
			'min_schedule_type' => '',
			'max_schedule_type' => '',
		];

		foreach ( $this->get_visible_children() as $product_id ) {
			$variation = wc_get_product( $product_id );

			if ( empty( $variation ) ) {
				continue;
			}

			if ( is_null( $result['min'] ) || (float)$variation->get_price() < $result['min'] ) {
				$result['min'] = (float) $variation->get_price();
				$result['min_schedule_type'] = WC_Reepay_Subscription_Plan_Simple::get_billing_plan( $this, true );
			}

			if ( is_null( $result['max'] ) || (float)$variation->get_price() > $result['max'] ) {
				$result['max'] = (float) $variation->get_price();
				$result['max_schedule_type'] = WC_Reepay_Subscription_Plan_Simple::get_billing_plan( $this, true );
			}
		}

		return $result;
	}
}