<?php
/**
 * Class BootstrapSmokeTest
 *
 * @package Reepay\Subscriptions
 */

use Reepay\Subscriptions\Tests\Helpers\Reepay_Subscriptions_UnitTestCase;

/**
 * BootstrapSmokeTest.
 */
class BootstrapSmokeTest extends Reepay_Subscriptions_UnitTestCase {

	/**
	 * Test the plugin's main singleton boots without fatal errors.
	 */
	public function test_plugin_instance_exists() {
		$this->assertTrue( function_exists( 'reepay_s' ) );
		$this->assertInstanceOf( 'WooCommerce_Reepay_Subscriptions', reepay_s() );
	}
}
