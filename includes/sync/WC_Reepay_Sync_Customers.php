<?php

/**
 * Class WC_Reepay_Sync_Customer
 *
 * @since 1.0.4
 */
class WC_Reepay_Sync_Customers {
	public static $events = array(
		'reepay_webhook_customer_created'              => 'created',
		'reepay_webhook_raw_event_customer_changed'    => 'changed',
		'reepay_webhook_raw_event_customer_deleted'    => 'deleted',
		'reepay_webhook_customer_payment_method_added' => 'payment_method_added',
	);

	/**
	 * Constructor
	 */
	public function __construct() {
		foreach ( self::$events as $hook => $method ) {
			add_action( $hook, [ $this, $method ] );
		}
	}

	/**
	 * @param array[
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

		if ( empty( $full_data['customer_data'] ) ) {
			return;
		}

		WC_Reepay_Import_Helpers::import_reepay_customer( $full_data['customer_data'] );
	}

	/**
	 * @param array[
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

		if ( empty( $full_data['user_id'] ) || empty( $full_data['customer_data'] ) ) {
			return;
		}

		WC_Reepay_Import_Helpers::import_user_data( $full_data['user_id'], $full_data['customer_data'] );
	}

	/**
	 * @param array[
	 *     'id' => string
	 *     'timestamp' => string
	 *     'signature' => string
	 *     'customer' => string
	 *     'event_type' => string
	 *     'event_id' => string
	 *     ] $data
	 */
	public function deleted( $data ) {
		$full_data = self::get_customer_data( $data['customer'] );

		if ( empty( $full_data['user_id'] ) ) {
			return;
		}

		require_once( ABSPATH . 'wp-admin/includes/user.php' );

		wp_delete_user( $full_data['user_id'] );
	}

	/**
	 * @param array[
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
		//If the payment method added on current site, then we wait for the end of its creation
		//Prevent payment methods duplication
		sleep( 5 );

		$user_id = rp_get_userid_by_handle( $data['customer'] );

		if( empty( $user_id ) ) {
			return;
		}
		
		$payment_token   = $data['payment_method'];
		$customer_tokens = WC_Reepay_Import_Helpers::get_customer_tokens( $user_id );

		if ( in_array( $payment_token, $customer_tokens ) ) {
			return;
		}

		try {
			$result = reepay_s()->api()->request(
				'customer/' . $data['customer'] . '/payment_method'
			);
		} catch ( Exception $e ) {

		}

		if ( empty( $result ) || empty( $result['cards'] ) ) {
			return;
		}

		foreach ( $result['cards'] as $card ) {
			if ( $card['id'] === $payment_token && 'active' === $card['state'] ) {
				WC_Reepay_Import_Helpers::add_card_to_user( $user_id, $card );

				return;
			}
		}
	}

	/**
	 * @param string $handle
	 *
	 * @return false|array
	 */
	public static function get_customer_data( $handle ) {
		$user_id       = rp_get_userid_by_handle( $handle );
		$customer_data = [];

		try {
			/**
			 * @see https://reference.reepay.com/api/#get-customer
			 **/
			$customer_data = reepay_s()->api()->request( "customer/{$handle}" );
		} catch ( Exception $e ) {
			reepay_s()->log()->log( [
				'source'   => 'WC_Reepay_Sync_Customer::get_customer_data',
				'message'  => $e->getMessage(),
				'$handle'  => $handle,
				'$user_id' => $user_id,
			] );
		}

		return [
			'user_id'       => $user_id,
			'customer_data' => $customer_data,
		];
	}
}