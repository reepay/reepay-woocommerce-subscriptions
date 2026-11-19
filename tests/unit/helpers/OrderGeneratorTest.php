<?php
/**
 * Class OrderGeneratorTest
 *
 * @package Reepay\Subscriptions
 */

use Reepay\Subscriptions\Tests\Helpers\OrderGenerator;

/**
 * OrderGeneratorTest.
 */
class OrderGeneratorTest extends WP_UnitTestCase {

	/**
	 * Test @see OrderGenerator::add_product followed by @see OrderGenerator::order.
	 */
	public function test_add_product_creates_order_with_line_item() {
		$generator = new OrderGenerator();
		$generator->add_product( 'simple' );

		$order = $generator->order();

		$this->assertInstanceOf( 'WC_Order', $order );
		$this->assertCount( 1, $order->get_items() );
	}

	/**
	 * Test @see OrderGenerator::set_prop forwards to the underlying WC_Order.
	 */
	public function test_set_prop_sets_payment_method() {
		$generator = new OrderGenerator();
		$generator->add_product( 'simple' )->set_prop( 'payment_method', 'bacs' );

		$this->assertSame( 'bacs', $generator->order()->get_payment_method() );
	}
}
