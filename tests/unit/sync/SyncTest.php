<?php
/**
 * Class SyncTest
 *
 * Covers WC_Reepay_Sync (includes/sync/WC_Reepay_Sync.php) - a 16-line
 * constructor-only class that instantiates WC_Reepay_Sync_Customers and
 * WC_Reepay_Sync_Subscriptions. One "doesn't fatal" smoke test is sufficient
 * per this task's lighter coverage bar.
 *
 * @package Reepay\Subscriptions
 */

use Reepay\Subscriptions\Tests\Helpers\Reepay_Subscriptions_UnitTestCase;

/**
 * SyncTest.
 */
class SyncTest extends Reepay_Subscriptions_UnitTestCase {

	/**
	 * Test @see WC_Reepay_Sync::__construct does not fatal and transitively
	 * registers both WC_Reepay_Sync_Customers and WC_Reepay_Sync_Subscriptions
	 * hook callbacks.
	 */
	public function test_constructor_does_not_fatal_and_registers_hooks() {
		new WC_Reepay_Sync();

		$this->assertTrue( has_action( 'reepay_webhook_customer_created' ) !== false );
		$this->assertTrue( has_action( 'reepay_webhook_raw_event_subscription_created' ) !== false );
	}
}
