<?php

/**
 * Class WC_Reepay_Renewals
 *
 * @since 1.0.0
 */
class WC_Reepay_Renewals {
	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'reepay_webhook_invoice_authorized', [ $this, 'create_subscription' ] );
	}

	/**
	 * @param  array[
	 *     'id' => string
	 *     'timestamp' => string
	 *     'signature' => string
	 *     'invoice' => string
	 *     'customer' => string
	 *     'transaction' => string
	 *     'event_type' => string
	 *     'event_id' => string
	 *     'order_id' => int
	 * ] $data
	 */
	public function create_subscription( $data ) {
		$order = wc_get_order( $data['order_id'] );

		if ( empty( $order ) || ! WC_Reepay_Helpers::is_order_paid_via_reepay( $order ) ) {
			return;
		}

		$token = self::get_payment_token_order( $order );

		if ( empty( $token ) ) {
			reepay_s()->log()->log([
				'source' => 'WC_Reepay_Renewals::create_subscription',
				'error' => 'Empty token',
				'data' => $data
			], 'error');
			return;
		}

		$token = $token->get_token();

		$order_item = $order->get_items()[0];
		$product = $order_item->get_product();

		$handle  = 'subscription_handle_' . $order->get_id() . '_' . $product->get_id();

		$new_subscription     = null;
		try {
			/**
			 * @see https://reference.reepay.com/api/#create-subscription
			 */
			$new_subscription = reepay_s()->api()->request( 'subscription', 'POST', [
				'customer'       => $data['customer'],
				'plan'           => $product->get_meta( '_reepay_subscription_handle' ),
//					'amount' => null,
				'quantity'       => $order_item->get_quantity(),
//					'test' => null,
				'handle'         => $handle,
//					'metadata' => null,
				'source'         => $token,
//					'create_customer' => null,
//					'plan_version' => null,
//					'amount_incl_vat' => null,
//					'generate_handle' => null,
//					'start_date' => null,
//					'end_date' => null,
				'grace_duration' => 172800,
//					'no_trial' => null,
//					'no_setup_fee' => null,
//					'trial_period' => null,
//					'subscription_discounts' => null,
//					'coupon_codes' => null,
//					'add_ons' => null,
//					'additional_costs' => null,
				'signup_method'  => 'source',
			] );
		} catch ( Exception $e ) {
		}

		if ( empty( $new_subscription ) ) {
			reepay_s()->log()->log([
				'source' => 'WC_Reepay_Renewals::create_subscription',
				'error' => 'create-subscription',
				'data' => $data
			], 'error');
			return;
		}

		try {
			/**
			 * @see https://reference.reepay.com/api/#set-payment-method
			 */
			$payment_method = reepay_s()->api()->request( "subscription/{$new_subscription['handle']}/pm", 'POST', [
				'handle' => $new_subscription['handle'],
				'source' => $token,
			] );
		} catch ( Exception $e ) {
		}

		if ( empty( $payment_method ) ) {
			reepay_s()->log()->log([
				'source' => 'WC_Reepay_Renewals::create_subscription',
				'error' => 'set-payment-method',
				'data' => $data
			], 'error');
			return;
		}

		$order->add_meta_data( 'subscription_handle', $payment_method['handle'] );
	}

	/**
	 * Get payment token.
	 *
	 * @param  WC_Order  $order
	 *
	 * @return WC_Payment_Token_Reepay|false
	 */
	public static function get_payment_token_order( WC_Order $order ) {
		$token = $order->get_meta( '_reepay_token' );
		if ( empty( $token ) ) {
			return false;
		}

		return self::get_payment_token( $token );
	}

	/**
	 * Get Payment Token by Token string.
	 *
	 * @param  string  $token
	 *
	 * @return null|bool|WC_Payment_Token
	 */
	public static function get_payment_token( $token ) {
		global $wpdb;

		$query    = "SELECT token_id FROM {$wpdb->prefix}woocommerce_payment_tokens WHERE token = '%s';";
		$token_id = $wpdb->get_var( $wpdb->prepare( $query, $token ) );
		if ( ! $token_id ) {
			return false;
		}

		return WC_Payment_Tokens::get( $token_id );
	}
}

new WC_Reepay_Renewals();
