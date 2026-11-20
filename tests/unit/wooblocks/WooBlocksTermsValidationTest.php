<?php
/**
 * Class WooBlocksTermsValidationTest
 *
 * Covers WC_Reepay_Woo_Blocks_Terms_Extend_Woo_Core::validate_and_save_terms()
 * (includes/woo-blocks/terms-checkbox/WC_Reepay_Woo_Blocks_Terms_Extend_Woo_Core.php),
 * the Store API (block checkout) handler for the subscription terms checkbox.
 *
 * Confirmed real behavior (read from source):
 * - Does nothing unless the '_reepay_enable_subscription_terms' option is
 *   'yes' AND the order contains a Frisbii subscription product
 *   (WC_Reepay_Checkout::is_reepay_product()).
 * - Accepted means $request['extensions']['wc-reepay-woo-block-terms']
 *   ['reepay_subscription_terms'] is strictly boolean true: the order gets
 *   '_subscription_terms' = '1'.
 * - Otherwise '_subscription_terms' is removed; a POST (placing the order)
 *   then throws a RouteException ('reepay_subscription_terms_required', 400),
 *   while a PUT (draft update while the form is being filled in) does not.
 * - Meta is only set on the order object, not saved - the Store API saves
 *   the order afterwards - so assertions read the in-memory order.
 *
 * Only the method is exercised directly; the hook registration (init()) is
 * not, to avoid double-registering the Store API callback.
 *
 * @package Reepay\Subscriptions
 */

use Automattic\WooCommerce\StoreApi\Exceptions\RouteException;
use Reepay\Subscriptions\Tests\Helpers\PLUGINS_STATE;
use Reepay\Subscriptions\Tests\Helpers\Reepay_Subscriptions_UnitTestCase;

/**
 * WooBlocksTermsValidationTest.
 */
class WooBlocksTermsValidationTest extends Reepay_Subscriptions_UnitTestCase {

	/**
	 * @var WC_Reepay_Woo_Blocks_Terms_Extend_Woo_Core
	 */
	private $extend;

	public function set_up() {
		parent::set_up();

		if ( ! class_exists( RouteException::class ) ) {
			$this->markTestSkipped( 'The WooCommerce Store API is not available.' );
		}

		if ( ! class_exists( 'WC_Reepay_Woo_Blocks_Terms_Extend_Woo_Core' ) ) {
			require_once dirname( REEPAY_PLUGIN_FILE ) . '/includes/woo-blocks/terms-checkbox/WC_Reepay_Woo_Blocks_Terms_Extend_Woo_Core.php';
		}

		update_option( '_reepay_enable_subscription_terms', 'yes' );

		$this->extend = new WC_Reepay_Woo_Blocks_Terms_Extend_Woo_Core();
	}

	public function tear_down() {
		delete_option( '_reepay_enable_subscription_terms' );

		parent::tear_down();
	}

	/**
	 * A Store API checkout request.
	 *
	 * @param string     $method 'POST' (place order) or 'PUT' (draft update).
	 * @param mixed|null $terms value of reepay_subscription_terms; null = not sent.
	 *
	 * @return WP_REST_Request
	 */
	private function request( string $method, $terms = null ): WP_REST_Request {
		$request = new WP_REST_Request( $method, '/wc/store/v1/checkout' );

		if ( null !== $terms ) {
			$request->set_param(
				'extensions',
				array( 'wc-reepay-woo-block-terms' => array( 'reepay_subscription_terms' => $terms ) )
			);
		}

		return $request;
	}

	/**
	 * An order containing a Frisbii simple subscription product.
	 *
	 * @return WC_Order
	 */
	private function subscription_order(): WC_Order {
		PLUGINS_STATE::maybe_skip_test_by_product_type( 'rp_sub' );

		return $this->order_generator->add_product( 'rp_sub' )->order();
	}

	/**
	 * Test @see WC_Reepay_Woo_Blocks_Terms_Extend_Woo_Core::validate_and_save_terms
	 * stores '_subscription_terms' = '1' when the terms were accepted.
	 */
	public function test_accepted_terms_are_saved_on_order() {
		$order = $this->subscription_order();

		$this->extend->validate_and_save_terms( $order, $this->request( 'POST', true ) );

		$this->assertSame( '1', $order->get_meta( '_subscription_terms' ) );
	}

	/**
	 * Test @see WC_Reepay_Woo_Blocks_Terms_Extend_Woo_Core::validate_and_save_terms
	 * refuses to place (POST) an order without accepted terms.
	 */
	public function test_post_without_accepted_terms_throws_route_exception() {
		$order = $this->subscription_order();

		try {
			$this->extend->validate_and_save_terms( $order, $this->request( 'POST', false ) );
			$this->fail( 'Expected a RouteException.' );
		} catch ( RouteException $e ) {
			$this->assertSame( 'reepay_subscription_terms_required', $e->getErrorCode() );
			$this->assertSame( 400, $e->getCode() );
		}

		$this->assertSame( '', $order->get_meta( '_subscription_terms' ) );
	}

	/**
	 * Test @see WC_Reepay_Woo_Blocks_Terms_Extend_Woo_Core::validate_and_save_terms
	 * only accepts boolean true - truthy strings or a missing extensions
	 * payload are refused, and a previously saved acceptance is removed.
	 */
	public function test_non_boolean_or_missing_terms_are_refused() {
		foreach ( array( 'yes', '1', 1, null ) as $terms ) {
			$order = $this->subscription_order();
			$order->update_meta_data( '_subscription_terms', '1' );

			$thrown = false;
			try {
				$this->extend->validate_and_save_terms( $order, $this->request( 'POST', $terms ) );
			} catch ( RouteException $e ) {
				$thrown = true;
			}

			$label = var_export( $terms, true );
			$this->assertTrue( $thrown, "Terms value: $label" );
			$this->assertSame( '', $order->get_meta( '_subscription_terms' ), "Terms value: $label" );
		}
	}

	/**
	 * Test @see WC_Reepay_Woo_Blocks_Terms_Extend_Woo_Core::validate_and_save_terms
	 * does not throw for a PUT (draft update) without accepted terms, but
	 * still removes a stale acceptance.
	 */
	public function test_put_without_accepted_terms_does_not_throw() {
		$order = $this->subscription_order();
		$order->update_meta_data( '_subscription_terms', '1' );

		$this->extend->validate_and_save_terms( $order, $this->request( 'PUT', false ) );

		$this->assertSame( '', $order->get_meta( '_subscription_terms' ) );
	}

	/**
	 * Test @see WC_Reepay_Woo_Blocks_Terms_Extend_Woo_Core::validate_and_save_terms
	 * does nothing when the terms setting is disabled.
	 */
	public function test_noop_when_terms_setting_disabled() {
		update_option( '_reepay_enable_subscription_terms', 'no' );
		$order = $this->subscription_order();

		$this->extend->validate_and_save_terms( $order, $this->request( 'POST', false ) );

		$this->assertSame( '', $order->get_meta( '_subscription_terms' ) );
	}

	/**
	 * Test @see WC_Reepay_Woo_Blocks_Terms_Extend_Woo_Core::validate_and_save_terms
	 * does nothing for an order without Frisbii subscription products.
	 */
	public function test_noop_for_order_without_subscription_products() {
		$order = $this->order_generator->add_product( 'simple' )->order();

		$this->extend->validate_and_save_terms( $order, $this->request( 'POST', false ) );

		$this->assertSame( '', $order->get_meta( '_subscription_terms' ) );
	}
}
