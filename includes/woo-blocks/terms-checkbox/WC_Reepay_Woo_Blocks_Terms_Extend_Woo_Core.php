<?php

use Automattic\WooCommerce\StoreApi\Exceptions\RouteException;

/**
 * Reepay terms subscription checkbox Extend WC Core.
 */
class WC_Reepay_Woo_Blocks_Terms_Extend_Woo_Core {

	/**
	 * Plugin Identifier, unique to each plugin.
	 *
	 * @var string
	 */
	private $name = 'wc-reepay-woo-block-terms';

	/**
	 * Bootstraps the class and hooks required data.
	 */
	public function init()
	{
		$this->save_terms_instructions();
	}

	/**
	 * Validates and saves the terms subscription to the order's metadata.
	 *
	 * @return void
	 */
	private function save_terms_instructions()
	{
		add_action(
			'woocommerce_store_api_checkout_update_order_from_request',
			array( $this, 'validate_and_save_terms' ),
			10,
			2
		);
	}

	/**
	 * Refuses Store API orders with Frisbii subscription products unless the terms were accepted.
	 *
	 * @param \WC_Order        $order
	 * @param \WP_REST_Request $request
	 *
	 * @throws RouteException When the order is placed without accepted terms.
	 */
	public function validate_and_save_terms( \WC_Order $order, \WP_REST_Request $request )
	{
		if ( get_option( '_reepay_enable_subscription_terms' ) !== 'yes' || ! $this->order_has_reepay_product( $order ) ) {
			return;
		}

		$extensions = $request->get_param( 'extensions' );
		$accepted   = is_array( $extensions )
			&& isset( $extensions[ $this->name ]['reepay_subscription_terms'] )
			&& true === $extensions[ $this->name ]['reepay_subscription_terms'];

		if ( $accepted ) {
			$order->update_meta_data( '_subscription_terms', '1' );

			return;
		}

		$order->delete_meta_data( '_subscription_terms' );

		// PUT requests only update the draft order while the customer is still filling in the form.
		if ( 'POST' === $request->get_method() ) {
			throw new RouteException(
				'reepay_subscription_terms_required',
				__( 'Please read and accept the subscription terms to proceed', 'reepay-subscriptions-for-woocommerce' ),
				400
			);
		}
	}

	/**
	 * @param \WC_Order $order
	 *
	 * @return bool
	 */
	private function order_has_reepay_product( \WC_Order $order )
	{
		foreach ( $order->get_items() as $item ) {
			$product = $item->get_product();

			if ( $product && WC_Reepay_Checkout::is_reepay_product( $product ) ) {
				return true;
			}
		}

		return false;
	}
}
?>
