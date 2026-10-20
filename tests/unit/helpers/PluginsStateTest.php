<?php
/**
 * Class PluginsStateTest
 *
 * @package Reepay\Subscriptions
 */

use Reepay\Subscriptions\Tests\Helpers\PLUGINS_STATE;

/**
 * PluginsStateTest.
 */
class PluginsStateTest extends WP_UnitTestCase {

	/**
	 * Test @see PLUGINS_STATE::PLUGINS maps the three expected keys.
	 */
	public function test_plugins_constant_has_expected_keys() {
		$this->assertSame(
			array( 'woo', 'woo_subs', 'reepay_pay' ),
			array_keys( PLUGINS_STATE::PLUGINS )
		);
	}

	/**
	 * Test @see PLUGINS_STATE::woo_subs_activated returns false when the class isn't loaded.
	 */
	public function test_woo_subs_activated_false_by_default_in_this_test() {
		if ( class_exists( 'WC_Subscriptions', false ) ) {
			$this->markTestSkipped( 'WC_Subscriptions already loaded in this run.' );
		}

		$this->assertFalse( PLUGINS_STATE::woo_subs_activated() );
	}
}
