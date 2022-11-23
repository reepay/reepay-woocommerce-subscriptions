<?php

/**
 * Class WC_Reepay_Sync_Customer
 *
 * @since 1.0.4
 */
class WC_Reepay_Sync_Customers {
	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'reepay_webhook_customer_created', [ $this, 'created' ] );
		add_action( 'reepay_webhook_raw_event_customer_changed', [ $this, 'changed' ] );
		add_action( 'reepay_webhook_raw_event_customer_deleted', [ $this, 'changed' ] );
	}

	/**
	 * @param  array[
	 *     'id' => string
	 *     'timestamp' => string
	 *     'signature' => string
	 *     'customer' => string
	 *     'event_type' => string
	 *     'event_id' => string
	 *     ] $data
	 */
	public function created( $data ) {
		$full_data = self::get_customer_data( $data['customer'] );

		if ( empty( $full_data ) ) {
			return;
		}

		WC_Reepay_Import_Helpers::create_woo_customer( ...$full_data );
	}

	/**
	 * @param  array[
	 *     'id' => string
	 *     'timestamp' => string
	 *     'signature' => string
	 *     'customer' => string
	 *     'event_type' => string
	 *     'event_id' => string
	 *     ] $data
	 */
	public function changed( $data ) {
		$full_data = self::get_customer_data( $data['customer'] );

		if ( empty( $full_data ) ) {
			return;
		}

		WC_Reepay_Import_Helpers::import_user_data( ...$full_data );
	}

	/**
	 * @param  array[
	 *     'id' => string
	 *     'timestamp' => string
	 *     'signature' => string
	 *     'customer' => string
	 *     'event_type' => string
	 *     'event_id' => string
	 *     ] $data
	 */
	public function deleted( $data ) {
		$user_id = rp_get_userid_by_handle( $data['customer'] );

		wp_delete_user( $user_id );
	}

	/**
	 * @param  array[
	 *     'id' => string
	 *     'timestamp' => string
	 *     'signature' => string
	 *     'customer' => string
	 *     'event_type' => string
	 *     'event_id' => string
	 *     'payment_method' => string
	 *     'payment_method_reference' => string
	 *     ] $data
	 */
	public function payment_method_added( $data ) {
		$user_id = rp_get_userid_by_handle( $data['customer'] );

		$payment_token = $data['payment_method'];
		$customer_tokens
		               = WC_Reepay_Import_Helpers::get_customer_tokens( $user_id );

		if ( in_array( $payment_token, $customer_tokens ) ) {
			return;
		}

		WC_Reepay_Import_Helpers::add_card_to_user( $user_id, $payment_token );
	}

	/**
	 * @param  string  $handle
	 *
	 * @return false|array
	 */
	public static function get_customer_data( $handle ) {
		$user_id = rp_get_userid_by_handle( $handle );

		try {
			/**
			 * @see https://reference.reepay.com/api/#get-customer
			 **/
			$customer_data = reepay_s()->api()->request( "customer/{$handle}" );
		} catch ( Exception $e ) {
			reepay_s()->log()->log( [
				'source'   => 'WC_Reepay_Sync_Customer::changed',
				'message'  => $e->getMessage(),
				'$data'    => $handle,
				'$user_id' => $user_id,
			] );

			return false;
		}

		return [ $user_id, $customer_data ];
	}
}