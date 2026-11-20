<?php
/**
 * Class SubscriptionsUpdateTest
 *
 * Covers WC_Reepay_Subscriptions_Update
 * (includes/update/WC_Reepay_Subscriptions_Update.php). update-1.0.2.php
 * itself (trivial set_time_limit/ini_set, no business logic) is explicitly
 * out of scope per the brief.
 *
 * CONFIRMED by reading the full source:
 * - update() is version-gated via the 'woocommerce_reepay_subscriptions_version'
 *   option compared against the hardcoded '1.0.2' key in self::$db_updates.
 *   No API mocking needed, no exit hazard.
 * - upgrade_notice()/upgrade_page() both gate on
 *   current_user_can('update_plugins').
 *
 * @package Reepay\Subscriptions
 */

use Reepay\Subscriptions\Tests\Helpers\Reepay_Subscriptions_UnitTestCase;

/**
 * SubscriptionsUpdateTest.
 */
class SubscriptionsUpdateTest extends Reepay_Subscriptions_UnitTestCase {

	/**
	 * Snapshot of the option before each test, restored afterwards.
	 *
	 * @var string|false
	 */
	private $version_option_before;

	public function set_up() {
		parent::set_up();

		$this->version_option_before = get_option( 'woocommerce_reepay_subscriptions_version' );
	}

	public function tear_down() {
		delete_option( 'woocommerce_reepay_subscriptions_version' );
		if ( false !== $this->version_option_before ) {
			add_option( 'woocommerce_reepay_subscriptions_version', $this->version_option_before );
		}

		parent::tear_down();
	}

	/*
	 * ------------------------------------------------------------------
	 * update()
	 * ------------------------------------------------------------------
	 */

	/**
	 * Test @see WC_Reepay_Subscriptions_Update::update bumps the stored
	 * option to '1.0.2' when the current version is below it.
	 */
	public function test_update_bumps_version_when_below_1_0_2() {
		delete_option( 'woocommerce_reepay_subscriptions_version' );
		add_option( 'woocommerce_reepay_subscriptions_version', '1.0.0' );

		WC_Reepay_Subscriptions_Update::update();

		$this->assertSame( '1.0.2', get_option( 'woocommerce_reepay_subscriptions_version' ) );
	}

	/**
	 * Test @see WC_Reepay_Subscriptions_Update::update is a no-op (option
	 * left untouched) when the current version is already at/above '1.0.2'.
	 */
	public function test_update_is_noop_when_already_at_or_above_1_0_2() {
		delete_option( 'woocommerce_reepay_subscriptions_version' );
		add_option( 'woocommerce_reepay_subscriptions_version', '1.0.2' );

		WC_Reepay_Subscriptions_Update::update();

		$this->assertSame( '1.0.2', get_option( 'woocommerce_reepay_subscriptions_version' ) );
	}

	/*
	 * ------------------------------------------------------------------
	 * upgrade_notice()
	 * ------------------------------------------------------------------
	 */

	/**
	 * Test @see WC_Reepay_Subscriptions_Update::upgrade_notice echoes the
	 * upgrade warning for a user who can update_plugins.
	 */
	public function test_upgrade_notice_outputs_for_admin_user() {
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		ob_start();
		WC_Reepay_Subscriptions_Update::upgrade_notice();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'requires to update the database structure', $output );
	}

	/**
	 * Test @see WC_Reepay_Subscriptions_Update::upgrade_notice outputs
	 * nothing for a non-admin user.
	 */
	public function test_upgrade_notice_silent_for_non_admin_user() {
		$subscriber_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber_id );

		ob_start();
		WC_Reepay_Subscriptions_Update::upgrade_notice();
		$output = ob_get_clean();

		$this->assertSame( '', $output );
	}

	/*
	 * ------------------------------------------------------------------
	 * upgrade_page()
	 * ------------------------------------------------------------------
	 */

	/**
	 * Test @see WC_Reepay_Subscriptions_Update::upgrade_page runs the update
	 * and echoes the finished message for an admin user.
	 */
	public function test_upgrade_page_runs_update_and_outputs_for_admin_user() {
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		delete_option( 'woocommerce_reepay_subscriptions_version' );
		add_option( 'woocommerce_reepay_subscriptions_version', '1.0.0' );

		ob_start();
		WC_Reepay_Subscriptions_Update::upgrade_page();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Upgrade finished', $output );
		$this->assertSame( '1.0.2', get_option( 'woocommerce_reepay_subscriptions_version' ) );
	}

	/**
	 * Test @see WC_Reepay_Subscriptions_Update::upgrade_page does nothing
	 * (no output, no update run) for a non-admin user.
	 */
	public function test_upgrade_page_noop_for_non_admin_user() {
		$subscriber_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber_id );

		delete_option( 'woocommerce_reepay_subscriptions_version' );
		add_option( 'woocommerce_reepay_subscriptions_version', '1.0.0' );

		ob_start();
		WC_Reepay_Subscriptions_Update::upgrade_page();
		$output = ob_get_clean();

		$this->assertSame( '', $output );
		$this->assertSame( '1.0.0', get_option( 'woocommerce_reepay_subscriptions_version' ) );
	}
}
