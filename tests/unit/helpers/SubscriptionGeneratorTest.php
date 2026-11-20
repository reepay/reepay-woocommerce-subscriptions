<?php
/**
 * Class SubscriptionGeneratorTest
 *
 * @package Reepay\Subscriptions
 */

use Reepay\Subscriptions\Tests\Helpers\OrderGenerator;
use Reepay\Subscriptions\Tests\Helpers\PLUGINS_STATE;
use Reepay\Subscriptions\Tests\Helpers\SubscriptionGenerator;

/**
 * SubscriptionGeneratorTest.
 */
class SubscriptionGeneratorTest extends WP_UnitTestCase {

	/**
	 * Test @see SubscriptionGenerator::subscription builds a WC_Subscription with the requested status.
	 */
	public function test_subscription_has_requested_status() {
		if ( ! PLUGINS_STATE::woo_subs_activated() ) {
			$this->markTestSkipped( 'WooCommerce Subscriptions not active.' );
		}

		$customer_id = self::factory()->user->create();

		$order_generator = new OrderGenerator();
		$order_generator->add_product( 'woo_sub' )->set_prop( 'customer_id', $customer_id );

		$generator    = new SubscriptionGenerator( $order_generator->order() );
		$subscription = $generator->with_status( 'on-hold' )->subscription();

		$this->assertSame( 'on-hold', $subscription->get_status() );
	}
}
