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
		var_dump('!1!');
		if(!empty($_GET['test_reepay'])) {
			var_dump(reepay_s()->api()->request('invoice'));
			die();
		}

		add_action( 'reepay_payment_finalize ', [ $this, 'add_renewal' ] );
	}

	/**
	 * @param array<string, mixed> $result
	 *
	 * @see https://reference.reepay.com/api/#get-invoice
	 */
	public function add_renewal( $result ) {
//		$this->api->set_params();
	}
}

new WC_Reepay_Renewals();
