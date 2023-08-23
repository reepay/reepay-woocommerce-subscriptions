<?php

use Reepay\Checkout\Tokens\ReepayTokens;
use Reepay\Checkout\Gateways\ReepayGateway;

class WC_Reepay_My_Account_Payment_Methods_Actions {
	public function __construct() {
		add_action( 'wp', array( __CLASS__, 'delete_payment_method_action' ), 10 ); //Before WooCommerce action
	}

	/**
	 * Process the delete payment method form.
	 */
	public static function delete_payment_method_action() {
		$token_id = absint( get_query_var( 'delete-payment-method', 0 ) );

		if ( empty( $token_id ) ) {
			return;
		}

		$token = WC_Payment_Tokens::get( $token_id );

		if ( ( class_exists( ReepayTokens::class ) && ! ReepayTokens::is_reepay_token( $token ) ) ||
		     ( method_exists( ReepayGateway::class, 'is_reepay_token' ) && ! ReepayGateway::is_reepay_token( $token ) ) ) {
			return;
		}

		wc_nocache_headers();

		if ( get_current_user_id() !== $token->get_user_id() || ! isset( $_REQUEST['_wpnonce'] ) || false === wp_verify_nonce( wp_unslash( $_REQUEST['_wpnonce'] ), 'delete-payment-method-' . $token_id ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			wc_add_notice( __( 'Invalid payment method.', 'reepay-checkout-gateway' ), 'error' );
		} else {
			$deleted = false;

			if ( class_exists( ReepayTokens::class ) ) {
				$deleted = ReepayTokens::delete_card( $token );
			} else if ( method_exists( ReepayGateway::class, 'is_reepay_token' ) ) {
				$deleted = ReepayGateway::delete_card( $token );
			}

			if ( $deleted ) {
				wc_add_notice( __( 'Payment method deleted.', 'reepay-checkout-gateway' ) );
			} else {
				wc_add_notice( __( 'Payment method cannot be deleted.', 'reepay-checkout-gateway' ) );
			}
		}

		wp_safe_redirect( wc_get_account_endpoint_url( 'payment-methods' ) );
		exit();
	}
}