<?php
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
	 * Saves the terms subscription to the order's metadata.
	 *
	 * @return void
	 */
	private function save_terms_instructions()
	{
		add_action(
			'woocommerce_store_api_checkout_update_order_from_request',
			function (\WC_Order $order, \WP_REST_Request $request) {
				if ( ! empty ($request['extensions']) ) {
					$subscription_terms_request_data = $request['extensions'][$this->name];
					$subscription_terms = $subscription_terms_request_data['reepay_subscription_terms'];
					$order->update_meta_data('_subscription_terms', $subscription_terms);
					$order->save();
				}
			},
			10,
			2
		);
	}
}
?>
