<?php

class WC_Reepay_My_Account_Add_Payment_Method_Page {
	public function __construct() {
		add_filter( 'woocommerce_available_payment_gateways', array( $this, 'add_payment_methods' ) );
	}

	public function add_payment_methods( $available_gateways ) {
		if ( ! is_add_payment_method_page() || empty( $_GET['reepay_subscription'] ) ) {
			return $available_gateways;
		}

		$subscription_handle = wc_clean( $_GET['reepay_subscription'] );

		try {
			$subscription        = reepay_s()->api()->request( "subscription/{$subscription_handle}" );
			WC_Reepay_My_Account_Subscription_Page::customer_has_access_to_subscription( $subscription );
		} catch ( Exception $e ) {
			return $available_gateways;
		}

		$reepay_checkout_gateway = reepay()->gateways()->checkout();

		if( 'yes' === $reepay_checkout_gateway->enabled ) {
			return array( $reepay_checkout_gateway );
		}

		return array();
	}
}