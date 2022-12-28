<?php

/**
 * Class WC_Reepay_Memberships_Integrations
 *
 * @since 1.0.9
 */
class WC_Reepay_Memberships_Integrations {
	public function __construct() {
		add_filter( 'woocommerce_is_subscription', [ $this, 'add_reepay_subscriptions_type' ], 100, 3 );
	}

	/**
	 * @param bool $is_subscription
	 * @param int $product_id
	 * @param WC_Product $product
	 */
	public function add_reepay_subscriptions_type($is_subscription, $product_id, $product) {
		return $is_subscription || WC_Reepay_Checkout::is_reepay_product( $product );
	}
}
