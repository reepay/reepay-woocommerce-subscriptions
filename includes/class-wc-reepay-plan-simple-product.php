<?php
class WC_Product_Reepay_Simple_Subscription extends WC_Product_Simple {

    public function get_type() {
        return 'reepay_simple_subscriptions';
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
     * Get subscription's price HTML.
     *
     * @return string containing the formatted price
     */
    public function get_price_html( $price = '' ) {

        return parent::get_price_html( $price );
    }

}