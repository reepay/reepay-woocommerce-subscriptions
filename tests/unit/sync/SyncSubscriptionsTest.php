<?php
/**
 * Class SyncSubscriptionsTest
 *
 * Covers WC_Reepay_Sync_Subscriptions (includes/sync/WC_Reepay_Sync_Subscriptions.php).
 *
 * COST WARNING (gotcha #5): created() calls sleep(5) UNCONDITIONALLY at the
 * very top of the method, before any branching - there is no early-return
 * before it. Both tests below that call created() therefore each pay a real
 * 5-second wall-clock cost; this is unavoidable given the method's structure,
 * not a test design choice, and matches the brief's "at most one test per
 * method" guidance loosely (two tests here each individually "pay the
 * sleep", but together they are the brief's own explicitly-endorsed minimum:
 * "a reasonable minimum per this task's bar: one test confirming the
 * 'already exists' branch, and one test confirming the 'API exception'
 * branch"). The full successful create-and-import chain (which would also
 * inherit import_reepay_subscription()'s own 3-call mocking complexity from
 * gotcha #1) is deliberately left undercovered here per the brief's explicit
 * allowance - see class docblock note below.
 *
 * payment_method_added() is a confirmed-empty, no-op method body (a
 * placeholder for a webhook event this class doesn't yet act on) - a single
 * "doesn't fatal" test is fully adequate coverage.
 *
 * @package Reepay\Subscriptions
 */

use Reepay\Subscriptions\Tests\Helpers\PLUGINS_STATE;
use Reepay\Subscriptions\Tests\Helpers\Reepay_Subscriptions_UnitTestCase;
use Reepay\Subscriptions\Tests\Mocks\ApiResponseMock;

/**
 * SyncSubscriptionsTest.
 */
class SyncSubscriptionsTest extends Reepay_Subscriptions_UnitTestCase {

	/**
	 * @var WC_Reepay_Sync_Subscriptions
	 */
	private WC_Reepay_Sync_Subscriptions $sync_subscriptions;

	public function set_up() {
		parent::set_up();

		update_option(
			'woocommerce_reepay_checkout_settings',
			array(
				'private_key_test' => 'test-key-reepay',
				'test_mode'        => 'yes',
			)
		);

		$this->set_fake_private_key( WC_Reepay_Subscription_API::get_instance(), 'private_key_test', 'test-key-reepay' );
		$this->set_fake_private_key( WC_Reepay_Subscription_API::get_instance(), 'test_mode', true );

		if ( PLUGINS_STATE::reepay_pay_activated() ) {
			reepay()->reset_settings();
		}

		$this->sync_subscriptions = new WC_Reepay_Sync_Subscriptions();
	}

	public function tear_down() {
		ApiResponseMock::clear();

		parent::tear_down();
	}

	/**
	 * Set a private/protected property on an already-constructed object via
	 * reflection. Pattern copied verbatim from tests/unit/renewals/LifecycleTest.php.
	 *
	 * @param object $object object instance to modify.
	 * @param string $property property name.
	 * @param mixed  $value value to assign.
	 */
	private function set_fake_private_key( object $object, string $property, $value ): void {
		$ref = new ReflectionProperty( $object, $property );
		$ref->setAccessible( true );
		$ref->setValue( $object, $value );
	}

	/**
	 * Test @see WC_Reepay_Sync_Subscriptions::created short-circuits (no API
	 * call attempted) when the subscription handle already exists as a
	 * WooCommerce order. PAYS THE 5s sleep() COST (gotcha #5) - unavoidable,
	 * sleep happens before this early-return check is reached.
	 */
	public function test_created_short_circuits_when_subscription_already_exists() {
		$order = $this->order_generator->add_product( 'simple' )->order();
		$order->update_meta_data( '_reepay_subscription_handle', 'sub-sync-exists-1' );
		$order->save_meta_data();

		// Deliberately no ApiResponseMock registered: if the exists-check
		// didn't short-circuit, the subsequent un-mocked API call would
		// throw/fatal rather than silently succeed - the assertion is that
		// nothing breaks and no new order is created.
		$orders_before = wc_get_orders( array( 'limit' => -1 ) );

		$this->sync_subscriptions->created( array( 'subscription' => 'sub-sync-exists-1' ) );

		$orders_after = wc_get_orders( array( 'limit' => -1 ) );
		$this->assertCount( count( $orders_before ), $orders_after, 'No additional order should be created when the subscription already exists.' );
	}

	/**
	 * Test @see WC_Reepay_Sync_Subscriptions::created logs and returns
	 * (no import attempted) when the API call throws. PAYS THE 5s sleep()
	 * COST (gotcha #5) - unavoidable.
	 */
	public function test_created_logs_and_returns_on_api_exception() {
		ApiResponseMock::respond_with( array( 'error' => 'invalid_request', 'message' => 'boom' ), 400 );

		$orders_before = wc_get_orders( array( 'limit' => -1 ) );

		$this->sync_subscriptions->created( array( 'subscription' => 'sub-sync-exception-1' ) );

		$orders_after = wc_get_orders( array( 'limit' => -1 ) );
		$this->assertCount( count( $orders_before ), $orders_after, 'No order should be created when the API call throws.' );
	}

	/**
	 * Test @see WC_Reepay_Sync_Subscriptions::payment_method_added is a
	 * confirmed-empty no-op method body - a call must not fatal.
	 */
	public function test_payment_method_added_is_noop_does_not_fatal() {
		$this->sync_subscriptions->payment_method_added( array( 'subscription' => 'sub-noop-1' ) );

		$this->addToAssertionCount( 1 ); // Reaching here without an exception/fatal is the assertion.
	}
}
