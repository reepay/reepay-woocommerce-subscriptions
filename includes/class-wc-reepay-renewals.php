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
		add_action( 'reepay_payment_finalize ', [ $this, 'add_renewal' ] );
	}

	/**
	 * @param array<string, mixed> $result
	 *
	 * @see https://reference.reepay.com/api/#get-invoice
	 */
	public function add_renewal( $result ) {

	}
}

new WC_Reepay_Renewals();
