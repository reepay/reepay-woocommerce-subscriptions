<?php
/**
 * Class BaseTestCaseTest
 *
 * @package Reepay\Subscriptions
 */

use Reepay\Subscriptions\Tests\Helpers\Reepay_Subscriptions_UnitTestCase;

/**
 * BaseTestCaseTest.
 */
class BaseTestCaseTest extends Reepay_Subscriptions_UnitTestCase {

	/**
	 * Test the trait wires up fresh generators for every test method.
	 */
	public function test_generators_are_available_and_fresh() {
		$this->assertInstanceOf(
			'Reepay\Subscriptions\Tests\Helpers\OrderGenerator',
			$this->order_generator
		);
		$this->assertInstanceOf(
			'Reepay\Subscriptions\Tests\Helpers\ProductGenerator',
			$this->product_generator
		);
	}
}
