<?php

class WC_Reepay_My_Account_Payment_Method {
	public function __construct() {
		add_filter( 'woocommerce_reepay_payment_accept_url', [ $this, 'add_subscription_arg' ] );
		add_filter( 'woocommerce_reepay_payment_cancel_url', [ $this, 'add_subscription_arg' ] );
		add_action( 'woocommerce_reepay_payment_method_added', [ $this, 'payment_method_added' ] );
	}

	// Frisbii handles may contain letters, digits, "_", "-", "." and "@" (up to 255 characters). A handle made only of dots is refused so it cannot act as a "." or ".." path segment.
	const HANDLE_PATTERN = '/^(?!\.+$)[A-Za-z0-9_.@-]{1,255}$/';

	public function add_subscription_arg( $url ) {
		$handle = isset( $_GET['reepay_subscription'] ) ? sanitize_text_field( wp_unslash( $_GET['reepay_subscription'] ) ) : '';

		if ( '' === $handle || ! preg_match( self::HANDLE_PATTERN, $handle ) ) {
			return $url;
		}

		$nonce_action = 'reepay_add_payment_method_' . $handle;

		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), $nonce_action ) ) {
			return $url;
		}

		return add_query_arg(
			[
				'reepay_subscription'       => $handle,
				'reepay_subscription_nonce' => wp_create_nonce( $nonce_action ),
			],
			$url
		);
	}

	public function payment_method_added( WC_Payment_Token $token ) {
		$handle = isset( $_GET['reepay_subscription'] ) ? sanitize_text_field( wp_unslash( $_GET['reepay_subscription'] ) ) : '';

		if ( '' === $handle ) {
			return;
		}

		try {
			$nonce = isset( $_GET['reepay_subscription_nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['reepay_subscription_nonce'] ) ) : '';

			if ( ! preg_match( self::HANDLE_PATTERN, $handle ) || ! wp_verify_nonce( $nonce, 'reepay_add_payment_method_' . $handle ) ) {
				throw new Exception( __( 'Permission denied', 'reepay-subscriptions-for-woocommerce' ) );
			}

			$subscription = reepay_s()->api()->request( 'subscription/' . rawurlencode( $handle ) );
			WC_Reepay_My_Account_Subscription_Page::customer_has_access_to_subscription( $subscription );

			reepay_s()->api()->request( 'subscription/' . rawurlencode( $handle ) . '/pm', 'POST', [
				'source' => $token->get_token(),
			] );
			wc_add_notice( __( 'Payment method successfully added.', 'reepay-subscriptions-for-woocommerce' ) );
		} catch ( Exception $exception ) {
			wc_add_notice( $exception->getMessage(), 'error' );
		}

		wp_redirect( wc_get_account_endpoint_url( WC_Reepay_My_Account_Subscriptions_Page::$menu_item_slug ) );

		exit;
	}
}