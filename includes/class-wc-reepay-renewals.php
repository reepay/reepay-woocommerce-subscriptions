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
		add_action( 'reepay_webhook_invoice_authorized', [ $this, 'add_renewal' ] );
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
	public function add_renewal( $data ) {
		$res = null;

		try {
			/**
			 * @see https://reference.reepay.com/api/#create-subscription
			 */
			$res = reepay_s()->api()->request( 'subscription', 'POST', [
				'customer'       => $data['customer'],
				'plan'           => $data['plan'],
//					'amount' => null,
//					'quantity' => null,
//					'test' => null,
//					'handle' => null,
//					'metadata' => null,
//					'source' => null,
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

		if ( ! empty( $res ) ) {
			try {
				/**
				 * @see https://reference.reepay.com/api/#set-payment-method
				 */
				$res = reepay_s()->api()->request( "subscription/{$res['handle']}/pm", 'POST', [
					'handle' => $res['handle'],
//						'source'          => null,
//						'payment_method_reference' => null,
				] );
			} catch ( Exception $e ) {
			}
		}

		die();
	}
}

new WC_Reepay_Renewals();
