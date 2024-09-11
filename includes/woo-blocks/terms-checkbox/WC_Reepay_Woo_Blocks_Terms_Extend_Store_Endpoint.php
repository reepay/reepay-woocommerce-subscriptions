<?php
use Automattic\WooCommerce\StoreApi\Schemas\V1\CheckoutSchema;
use Automattic\WooCommerce\StoreApi\StoreApi;
use Automattic\WooCommerce\StoreApi\Schemas\ExtendSchema;

/**
 * Reepay subscription terms checkbox Extend Store API.
 */
class WC_Reepay_Woo_Blocks_Terms_Extend_Store_Endpoint {
	/**
	 * Stores Rest Extending instance.
	 *
	 * @var ExtendSchema
	 */
	private static $extend;

	/**
	 * Plugin Identifier, unique to each plugin.
	 *
	 * @var string
	 */
	const IDENTIFIER = 'wc-reepay-woo-block-terms';

	/**
	 * Bootstraps the class and hooks required data.
	 */
	public static function init() {
		self::$extend = StoreApi::container()->get( ExtendSchema::class );
		self::extend_store();
	}

	/**
	 * Registers the actual data into each endpoint.
	 */
	public static function extend_store() {
		if ( is_callable( array( self::$extend, 'register_endpoint_data' ) ) ) {
			self::$extend->register_endpoint_data(
				array(
					'endpoint'        => CheckoutSchema::IDENTIFIER,
					'namespace'       => self::IDENTIFIER,
					'schema_callback' => array( self::class, 'extend_checkout_schema' ),
					'schema_type'     => ARRAY_A,
				)
			);
		}
	}

	/**
	 * Register terms subscription checkbox schema into the Checkout endpoint.
	 *
	 * @return array Registered schema.
	 */
	public static function extend_checkout_schema() {
		return array(
			'reepay_subscription_terms' => array(
				'description' => __( 'Subscription terms checkbox.', 'reepay-subscriptions-for-woocommerce' ),
				'type'        => 'boolean',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => true,
				'arg_options' => array(
					'validate_callback' => fn( $value ) => is_bool( $value ),
				),
			),
		);
	}
}
?>
