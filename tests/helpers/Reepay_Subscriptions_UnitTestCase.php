<?php
/**
 * Class Reepay_Subscriptions_UnitTestCase
 *
 * @package Reepay\Subscriptions
 */

namespace Reepay\Subscriptions\Tests\Helpers;

use WP_UnitTestCase;

/**
 * Class Reepay_Subscriptions_UnitTestCase
 */
class Reepay_Subscriptions_UnitTestCase extends WP_UnitTestCase {
	use Reepay_Subscriptions_UnitTestCase_Trait;

	/**
	 * Runs before each test is executed.
	 */
	public function set_up() {
		parent::set_up();

		$this->set_up_data();
	}
}
