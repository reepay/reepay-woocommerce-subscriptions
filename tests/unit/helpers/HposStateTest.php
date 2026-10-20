<?php
/**
 * Class HposStateTest
 *
 * @package Reepay\Subscriptions
 */

use Reepay\Subscriptions\Tests\Helpers\HPOS_STATE;

/**
 * HposStateTest.
 */
class HposStateTest extends WP_UnitTestCase {

	/**
	 * Test @see HPOS_STATE::is_active reflects the HPOS_ENABLED env var.
	 */
	public function test_is_active_reflects_env_default_no() {
		$this->assertFalse( HPOS_STATE::is_active() );
	}
}
