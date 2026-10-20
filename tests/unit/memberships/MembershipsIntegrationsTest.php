<?php
/**
 * Class MembershipsIntegrationsTest
 *
 * Covers WC_Reepay_Memberships_Integrations
 * (includes/memberships/WC_Reepay_Memberships_Integrations.php).
 *
 * CONFIRMED per plan.md's own explicit instruction for this file, and by
 * reading the constructor directly: it does nothing at all (no hooks
 * registered, every public method unreachable in production) unless
 * is_plugin_active('woocommerce-memberships/woocommerce-memberships.php')
 * is true - which it never is in this test environment, since WooCommerce
 * Memberships is not one of the plugins this suite's matrix
 * installs/activates (see PLUGINS_STATE::PLUGINS - only woo/woo_subs/
 * reepay_pay are ever activated).
 *
 * Per the brief: every test in this file must markTestSkipped() with a clear
 * reason rather than attempt real behavioral coverage. This file must never
 * fail the suite in this environment. The single "constructs without fatal"
 * test is NOT skipped (confirming the dependency-absent no-op is itself
 * exercised), but every other public method is skip-guarded rather than
 * invoked, per the brief's explicit scope instruction not to fake-activate
 * WooCommerce Memberships or exercise activate_memberships()/
 * renew_membership()/etc.'s real logic.
 *
 * @package Reepay\Subscriptions
 */

use Reepay\Subscriptions\Tests\Helpers\Reepay_Subscriptions_UnitTestCase;

/**
 * MembershipsIntegrationsTest.
 */
class MembershipsIntegrationsTest extends Reepay_Subscriptions_UnitTestCase {

	/**
	 * Confirms WooCommerce Memberships is NOT active in this test
	 * environment, per plan.md's explicit instruction for this file.
	 *
	 * @return bool
	 */
	private function memberships_plugin_active(): bool {
		return function_exists( 'is_plugin_active' )
			&& is_plugin_active( 'woocommerce-memberships/woocommerce-memberships.php' );
	}

	/**
	 * Test @see WC_Reepay_Memberships_Integrations::__construct can be
	 * instantiated without fatal when the WooCommerce Memberships dependency
	 * is absent - confirming it correctly does nothing when the dependency
	 * is missing. This is the one real (non-skipped) assertion this file
	 * makes, per the brief's instruction.
	 */
	public function test_constructor_does_not_fatal_when_memberships_not_installed() {
		if ( $this->memberships_plugin_active() ) {
			$this->markTestSkipped( 'WooCommerce Memberships plugin not installed in this test environment - expected it to be inactive, but it is active.' );
		}

		$integration = new WC_Reepay_Memberships_Integrations();

		$this->assertInstanceOf( WC_Reepay_Memberships_Integrations::class, $integration );
		$this->assertFalse(
			WC_Reepay_Memberships_Integrations::$woo_subscriptions_fake_activation,
			'fake_woo_subscription_activation() must never run when the Memberships dependency is absent.'
		);
	}

	/**
	 * Placeholder for add_reepay_subscriptions_type() - requires the
	 * Memberships plugin's filter wiring to be meaningfully exercised.
	 * WooCommerce Memberships isn't installed in this test environment.
	 */
	public function test_add_reepay_subscriptions_type_requires_memberships_plugin() {
		$this->markTestSkipped( 'WooCommerce Memberships plugin not installed in this test environment.' );
	}

	/**
	 * Placeholder for disable_default_membership_activation_for_reepay_products() -
	 * requires the Memberships plugin's filter wiring.
	 * WooCommerce Memberships isn't installed in this test environment.
	 */
	public function test_disable_default_membership_activation_for_reepay_products_requires_memberships_plugin() {
		$this->markTestSkipped( 'WooCommerce Memberships plugin not installed in this test environment.' );
	}

	/**
	 * Placeholder for activate_memberships() - calls wc_memberships() and
	 * wc_memberships_get_order_access_granting_product_ids(), both only
	 * defined by the Memberships plugin. WooCommerce Memberships isn't
	 * installed in this test environment.
	 */
	public function test_activate_memberships_requires_memberships_plugin() {
		$this->markTestSkipped( 'WooCommerce Memberships plugin not installed in this test environment.' );
	}

	/**
	 * Placeholder for renew_membership() - calls
	 * WC_Memberships_Integration_Subscriptions_Membership_Plan and
	 * wc_memberships_get_user_membership(), both only defined by the
	 * Memberships plugin. WooCommerce Memberships isn't installed in this
	 * test environment.
	 */
	public function test_renew_membership_requires_memberships_plugin() {
		$this->markTestSkipped( 'WooCommerce Memberships plugin not installed in this test environment.' );
	}

	/**
	 * Placeholder for hold_membership() - depends on get_membership_info()
	 * returning a real WC_Memberships_User_Membership, only possible with
	 * the Memberships plugin active. WooCommerce Memberships isn't installed
	 * in this test environment.
	 */
	public function test_hold_membership_requires_memberships_plugin() {
		$this->markTestSkipped( 'WooCommerce Memberships plugin not installed in this test environment.' );
	}

	/**
	 * Placeholder for cancel_membership() - same dependency as
	 * hold_membership(). WooCommerce Memberships isn't installed in this
	 * test environment.
	 */
	public function test_cancel_membership_requires_memberships_plugin() {
		$this->markTestSkipped( 'WooCommerce Memberships plugin not installed in this test environment.' );
	}

	/**
	 * Placeholder for get_membership_info() - calls
	 * wc_memberships_get_user_membership(), only defined by the Memberships
	 * plugin. WooCommerce Memberships isn't installed in this test
	 * environment.
	 */
	public function test_get_membership_info_requires_memberships_plugin() {
		$this->markTestSkipped( 'WooCommerce Memberships plugin not installed in this test environment.' );
	}
}
