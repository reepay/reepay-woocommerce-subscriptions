<?php

/**
 * Class WC_Reepay_Sync_Subscriptions
 *
 * @since 1.0.4
 */
class WC_Reepay_Sync_Subscriptions {
	public static $events = array(
		'created',
	);

	/**
	 * Constructor
	 */
	public function __construct() {
		foreach ( self::$events as $event ) {
			add_action( "reepay_webhook_raw_event_subscription_$event", [ $this, $event ], 1, 10 );
		}
	}

	/**
	 * @param  array[
	 *     'id' => string
	 *     'timestamp' => string
	 *     'signature' => string
	 *     'subscription' => string
	 *     'customer' => string
	 *     'event_type' => string
	 *     'event_id' => string
	 *     ] $data
	 */
	public function created( $data ) {
		if ( ! WC_Reepay_Import_Helpers::woo_reepay_subscription_exists( $data['subscription'] ) ) {
			WC_Reepay_Import_Helpers::import_reepay_subscription( $data['subscription'] );
		}
	}
}