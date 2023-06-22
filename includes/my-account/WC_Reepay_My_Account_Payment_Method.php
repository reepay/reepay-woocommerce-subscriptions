<?php

class WC_Reepay_My_Account_Payment_Method {
	public function __construct() {
		add_filter( 'woocommerce_reepay_payment_accept_url', [ $this, 'add_subscription_arg' ] );
		add_filter( 'woocommerce_reepay_payment_cancel_url', [ $this, 'add_subscription_arg' ] );
		add_action( 'woocommerce_reepay_payment_method_added', [ $this, 'payment_method_added' ] );
	}

	public function add_subscription_arg( $url ) {
		if ( $_GET['reepay_subscription'] ) {
			$url = sanitize_url( add_query_arg( 'reepay_subscription', $_GET['reepay_subscription'], $url ) );
		}

		return $url;
	}

	public function payment_method_added( WC_Payment_Token $token ) {
		$handle = sanitize_text_field( $_GET['reepay_subscription'] ) ?? '';

		if ( ! empty( $handle ) ) {
			try {
				reepay_s()->api()->request( 'subscription/' . $handle . '/pm', 'POST', [
					'source' => $token->get_token(),
				] );
				wc_add_notice( __( 'Payment method successfully added.', 'reepay-subscriptions-for-woocommerce' ) );
			} catch ( Exception $exception ) {
				wc_add_notice( $exception->getMessage() );
			}
		}

		wp_redirect( wc_get_account_endpoint_url( WC_Reepay_My_Account_Subscriptions_Page::$menu_item_slug ) );

		exit;
	}
}