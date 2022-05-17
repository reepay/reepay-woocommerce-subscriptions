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
		add_action( 'woocommerce_payment_complete', [ $this, 'add_renewal' ] );
	}

	public function add_renewal( $order_id ) {
		if ( ! WC_Reepay_Helpers::is_order_paid_via_reepay( $order_id ) ) {
			return;
		}


	}
}

new WC_Reepay_Renewals();
