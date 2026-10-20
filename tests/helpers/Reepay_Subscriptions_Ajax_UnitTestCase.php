<?php
/**
 * Class Reepay_Subscriptions_Ajax_UnitTestCase
 *
 * @package Reepay\Subscriptions
 */

namespace Reepay\Subscriptions\Tests\Helpers;

use WP_Ajax_UnitTestCase;

/**
 * Class Reepay_Subscriptions_Ajax_UnitTestCase
 *
 * Base case for classes driven by $_GET/$_POST/$_REQUEST (import, my-account
 * actions, admin-save handlers) rather than being invoked directly.
 */
class Reepay_Subscriptions_Ajax_UnitTestCase extends WP_Ajax_UnitTestCase {
	use Reepay_Subscriptions_UnitTestCase_Trait;

	/**
	 * Runs before each test is executed.
	 */
	public function set_up() {
		parent::set_up();

		$this->set_up_data();
	}

	/**
	 * Reset superglobals after each test so one test's $_GET/$_POST can't leak into the next.
	 */
	public function tear_down() {
		$_GET     = array();
		$_POST    = array();
		$_REQUEST = array();

		parent::tear_down();
	}
}
