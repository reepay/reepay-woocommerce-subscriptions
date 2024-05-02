<?php

class WC_Reepay_Woo_Blocks {

	public function __construct() {
		add_action( 'woocommerce_blocks_loaded', [ $this, 'add_gateways_filter' ] );
		add_action( 'reepay_blocks_payment_method_data', [ $this, 'set_token_saving' ] );
	}

	public function add_gateways_filter() {
		if ( function_exists( 'woocommerce_store_api_register_payment_requirements' ) ) {
			woocommerce_store_api_register_payment_requirements(
				array(
					'data_callback' => [ $this, 'filter_gateways' ],
				)
			);
		}
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

	/**
	 * Set tokens' saving always on true
	 *
	 * @param  array  $data
	 *
	 * @return array
	 */
	public function set_token_saving( $data ) {
		if ( wcs_cart_have_subscription() || wcs_is_payment_change() ) {
			$data['always_save_token'] = true;
		}

		return $data;
	}
}