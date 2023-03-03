<?php

class WC_Reepay_Woo_Blocks {

	public function __construct() {
		add_action( 'woocommerce_blocks_loaded', [ $this, 'add_gateways_filter' ] );
	}

	public function add_gateways_filter() {
		woocommerce_store_api_register_payment_requirements(
			array(
				'data_callback' => [ $this, 'filter_gateways' ],
			)
		);
	}

	/**
	 * Check the content of the cart and add required payment methods.
	 *
	 * @return array list of features required by cart items.
	 */
	public function filter_gateways() {
		if ( WC_Reepay_Checkout::is_reepay_product_in_cart() ) {
			return array( 'woo_blocks_only_subscriptions_in_cart' );
		}

		return array();
	}
}