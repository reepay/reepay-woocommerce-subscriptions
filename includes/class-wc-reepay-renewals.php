<?php

/**
 * Class WC_Reepay_Renewals
 *
 * @since 1.0.0
 */
class WC_Reepay_Renewals {
	/**
	 * @var WC_Reepay_Subscription_API
	 */
	private $api;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->api = new WC_Reepay_Subscription_API();

		if(!empty($_GET['test_reepay'])) {
			$this->api->request();
		}

		add_action( 'reepay_payment_finalize ', [ $this, 'add_renewal' ] );
	}

	/**
	 * @param array<string, mixed> $result
	 *
	 * @see https://reference.reepay.com/api/#get-invoice
	 */
	public function add_renewal( $result ) {
		$this->api->set_params();
	}
}

new WC_Reepay_Renewals();
