<?php
/**
 * Trait Reepay_Subscriptions_UnitTestCase_Trait
 *
 * @package Reepay\Subscriptions
 */

namespace Reepay\Subscriptions\Tests\Helpers;

/**
 * Trait Reepay_Subscriptions_UnitTestCase_Trait
 */
trait Reepay_Subscriptions_UnitTestCase_Trait {
	/**
	 * @var OrderGenerator
	 */
	protected OrderGenerator $order_generator;

	/**
	 * @var ProductGenerator
	 */
	protected ProductGenerator $product_generator;

	/**
	 * Fixture setup shared by every test method.
	 */
	protected function set_up_data() {
		$this->order_generator   = new OrderGenerator();
		$this->product_generator = new ProductGenerator();
	}
}
