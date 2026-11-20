<?php
/**
 * Class DataProviderTest
 *
 * @package Reepay\Subscriptions
 */

use Reepay\Subscriptions\Tests\Helpers\DataProvider;

/**
 * DataProviderTest.
 */
class DataProviderTest extends WP_UnitTestCase {

	/**
	 * Test @see DataProvider::currencies returns a non-empty list of ISO codes.
	 */
	public function test_currencies_returns_iso_codes() {
		$currencies = DataProvider::currencies();

		$this->assertNotEmpty( $currencies );
		$this->assertContains( 'DKK', $currencies );
		$this->assertContains( 'EUR', $currencies );
	}

	/**
	 * Test @see DataProvider::subscription_statuses returns the expected status set.
	 */
	public function test_subscription_statuses_returns_expected_set() {
		$this->assertSame(
			array( 'active', 'on-hold', 'cancelled', 'expired', 'pending-cancel' ),
			DataProvider::subscription_statuses()
		);
	}
}
