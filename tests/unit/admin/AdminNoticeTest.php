<?php
/**
 * Class AdminNoticeTest
 *
 * Covers WC_Reepay_Subscription_Admin_Notice (includes/WC_Reepay_Subscription_Admin_Notice.php):
 * add_notice, add_activation_notice, show_editor_message, show_activation_message
 * and show_thankyou_message.
 *
 * Phase 5 scope: baseline regression coverage per requirements.md §3.4.
 *
 * Confirmed real behavior (read from source):
 * - self::$notices / self::$activation_notices are STATIC properties,
 *   populated from the 'reepay_admin_notices' / 'reepay_admin_activation_notices'
 *   options in the CONSTRUCTOR. Because they're static, they persist across
 *   test methods within the same process unless explicitly reset - this
 *   suite resets both the options AND the static properties (via reflection)
 *   in set_up() so each test starts from a clean slate.
 * - add_notice()/add_activation_notice() are static, dedupe via
 *   array_unique(array_merge(...)) and persist immediately to the matching
 *   option.
 * - show_editor_message($messages) iterates self::$notices (the static
 *   property, read BEFORE the loop into a local $notices variable), calls
 *   add_settings_error()/settings_errors() for each, then clears the
 *   'reepay_admin_notices' OPTION to [] via update_option(). Confirmed by
 *   reading the method body carefully: it does NOT also reset
 *   self::$notices itself - only the option is cleared. This means a second
 *   call to show_editor_message() in the same request would still iterate
 *   the stale static property, even though the option is now empty. This
 *   test suite asserts that exact (confirmed) asymmetry rather than assuming
 *   both are cleared together. Contrary to the brief's assumption,
 *   settings_errors() DOES echo immediately in this CLI/unit-test context
 *   (confirmed by running this suite - it prints an inline
 *   "<div id='setting-error-' ...>" block per call); this suite does not
 *   assert on that incidental output, only on the option/property state,
 *   since the method's actual purpose is accumulating settings errors for
 *   WordPress's admin-notice rendering, not direct output.
 * - show_activation_message() iterates self::$activation_notices and
 *   DIRECTLY echoes "<div class='error'><p>...</p></div>" wrapped in
 *   wp_kses(..., ['a' => ['href' => true]]) - this is the one method here
 *   that is directly assertable via output buffering. It also clears the
 *   'reepay_admin_activation_notices' option afterward (same asymmetry as
 *   show_editor_message: the option is cleared, not necessarily the static
 *   property, though for this method it does not matter since nothing reads
 *   the static property again afterward in the same call).
 * - show_thankyou_message($ret, $order_id): (1) if order meta
 *   '_reepay_frontend_notices' is non-empty, returns
 *   ['state' => 'failed', 'message' => $notice] immediately; (2) else if
 *   '_reepay_thankyou_reloaded' meta is empty, checks
 *   WC_Reepay_Renewals::is_order_contain_subscription($order) (confirmed via
 *   WC_Reepay_Renewals.php:331 - true for reepay_simple_subscriptions /
 *   reepay_variable_subscriptions product types, i.e. the 'rp_sub' fixture
 *   type) - if true, returns ['state' => 'reload'] and sets
 *   '_reepay_thankyou_reloaded' meta to true; (3) else if a
 *   '_reepay_subscription_handle' meta exists (meaning reloaded was already
 *   truthy), makes a REAL outbound API call via
 *   reepay_s()->api()->request("subscription/{$sub_handle}") - mocked here
 *   with ApiResponseMock - and returns a 'paid' state when the mocked
 *   subscription's 'state' is 'active' (the clearest of the three ways to
 *   reach 'paid', per the brief), catching Exception on API failure and
 *   returning a 'failed' state with the exception message.
 *
 * @package Reepay\Subscriptions
 */

use Reepay\Subscriptions\Tests\Helpers\Reepay_Subscriptions_UnitTestCase;
use Reepay\Subscriptions\Tests\Mocks\ApiResponseMock;

/**
 * AdminNoticeTest.
 */
class AdminNoticeTest extends Reepay_Subscriptions_UnitTestCase {

	/**
	 * @var WC_Reepay_Subscription_Admin_Notice
	 */
	private WC_Reepay_Subscription_Admin_Notice $notice;

	public function set_up() {
		parent::set_up();

		// self::$notices / self::$activation_notices are static and populated
		// from options in the constructor - reset both the options and the
		// static properties directly via reflection so no earlier test's
		// state leaks into this one.
		update_option( 'reepay_admin_notices', array() );
		update_option( 'reepay_admin_activation_notices', array() );
		$this->reset_static_property( 'notices', array() );
		$this->reset_static_property( 'activation_notices', array() );

		// Both WC_Reepay_Subscription_API (reepay_s()->api()) and the Frisbii
		// Pay Api class read their key from a settings snapshot cached once
		// at plugin bootstrap, long before any option set here would be
		// read - inject a fake test key directly via reflection instead.
		// Pattern copied verbatim from tests/unit/renewals/LifecycleTest.php.
		update_option(
			'woocommerce_reepay_checkout_settings',
			array(
				'private_key_test' => 'test-key-reepay',
				'test_mode'        => 'yes',
			)
		);

		$this->set_fake_private_key( WC_Reepay_Subscription_API::get_instance(), 'private_key_test', 'test-key-reepay' );
		$this->set_fake_private_key( WC_Reepay_Subscription_API::get_instance(), 'test_mode', true );

		$this->notice = new WC_Reepay_Subscription_Admin_Notice();
	}

	public function tear_down() {
		ApiResponseMock::clear();

		update_option( 'reepay_admin_notices', array() );
		update_option( 'reepay_admin_activation_notices', array() );
		$this->reset_static_property( 'notices', array() );
		$this->reset_static_property( 'activation_notices', array() );

		parent::tear_down();
	}

	/**
	 * Reset a private static property on WC_Reepay_Subscription_Admin_Notice
	 * via reflection.
	 *
	 * @param string $property property name.
	 * @param mixed  $value value to assign.
	 */
	private function reset_static_property( string $property, $value ): void {
		$ref = new ReflectionProperty( WC_Reepay_Subscription_Admin_Notice::class, $property );
		$ref->setAccessible( true );
		$ref->setValue( null, $value );
	}

	/**
	 * Set a private/protected property on an already-constructed object via
	 * reflection. Pattern copied verbatim from tests/unit/renewals/LifecycleTest.php.
	 *
	 * @param object $object object instance to modify.
	 * @param string $property property name.
	 * @param mixed  $value value to assign.
	 */
	private function set_fake_private_key( object $object, string $property, $value ): void {
		$ref = new ReflectionProperty( $object, $property );
		$ref->setAccessible( true );
		$ref->setValue( $object, $value );
	}

	/*
	 * ------------------------------------------------------------------
	 * add_notice() / add_activation_notice()
	 * ------------------------------------------------------------------
	 */

	/**
	 * Test @see WC_Reepay_Subscription_Admin_Notice::add_notice dedupes
	 * identical notices (adding the same notice twice results in exactly one
	 * entry) and persists to the 'reepay_admin_notices' option.
	 */
	public function test_add_notice_dedupes_and_persists() {
		WC_Reepay_Subscription_Admin_Notice::add_notice( 'Something went wrong' );
		WC_Reepay_Subscription_Admin_Notice::add_notice( 'Something went wrong' );

		$stored = get_option( 'reepay_admin_notices' );

		$this->assertCount( 1, $stored );
		$this->assertContains( 'Something went wrong', $stored );
	}

	/**
	 * Test @see WC_Reepay_Subscription_Admin_Notice::add_activation_notice
	 * dedupes identical notices and persists to the
	 * 'reepay_admin_activation_notices' option.
	 */
	public function test_add_activation_notice_dedupes_and_persists() {
		WC_Reepay_Subscription_Admin_Notice::add_activation_notice( 'Please activate your key' );
		WC_Reepay_Subscription_Admin_Notice::add_activation_notice( 'Please activate your key' );

		$stored = get_option( 'reepay_admin_activation_notices' );

		$this->assertCount( 1, $stored );
		$this->assertContains( 'Please activate your key', $stored );
	}

	/*
	 * ------------------------------------------------------------------
	 * show_editor_message()
	 * ------------------------------------------------------------------
	 */

	/**
	 * Test @see WC_Reepay_Subscription_Admin_Notice::show_editor_message
	 * clears the 'reepay_admin_notices' OPTION after running, and returns the
	 * $messages argument unchanged (pass-through filter).
	 */
	public function test_show_editor_message_clears_option_and_returns_messages_unchanged() {
		WC_Reepay_Subscription_Admin_Notice::add_notice( 'Editor notice' );

		$messages = array( 'post' => array( 1 => 'Post updated.' ) );
		// settings_errors() (called internally) really does echo in this test
		// environment (confirmed in Task 5.1) - buffer and discard it here
		// since this test isn't asserting on that incidental HTML output,
		// only on the method's return value and the option side effect.
		ob_start();
		$result = $this->notice->show_editor_message( $messages );
		ob_end_clean();

		$this->assertSame( $messages, $result );
		$this->assertSame( array(), get_option( 'reepay_admin_notices' ) );
	}

	/**
	 * Test @see WC_Reepay_Subscription_Admin_Notice::show_editor_message
	 * confirms the asymmetry flagged in the brief: the method clears the
	 * 'reepay_admin_notices' OPTION but does NOT reset the static
	 * self::$notices property itself. A second, freshly-constructed instance
	 * (whose constructor re-reads the now-empty option) sees an empty list,
	 * but the static property as read by a direct reflection probe right
	 * after show_editor_message() still holds the original notice - proving
	 * the option and the static property are cleared independently, not
	 * together.
	 */
	public function test_show_editor_message_does_not_reset_static_notices_property() {
		WC_Reepay_Subscription_Admin_Notice::add_notice( 'Editor notice' );

		// See note in test_show_editor_message_clears_option_and_returns_messages_unchanged()
		// above re: buffering settings_errors()'s real echoed output.
		ob_start();
		$this->notice->show_editor_message( array() );
		ob_end_clean();

		$ref = new ReflectionProperty( WC_Reepay_Subscription_Admin_Notice::class, 'notices' );
		$ref->setAccessible( true );
		$static_value = $ref->getValue();

		$this->assertSame( array(), get_option( 'reepay_admin_notices' ), 'The option must be cleared.' );
		$this->assertContains(
			'Editor notice',
			$static_value,
			'The static self::$notices property is confirmed NOT to be reset by show_editor_message() - only the option is cleared.'
		);
	}

	/*
	 * ------------------------------------------------------------------
	 * show_activation_message()
	 * ------------------------------------------------------------------
	 */

	/**
	 * Test @see WC_Reepay_Subscription_Admin_Notice::show_activation_message
	 * echoes the notice wrapped in a "<div class='error'>" block and clears
	 * the 'reepay_admin_activation_notices' option afterward.
	 */
	public function test_show_activation_message_echoes_notice_and_clears_option() {
		WC_Reepay_Subscription_Admin_Notice::add_activation_notice( 'Please activate your key' );

		ob_start();
		$this->notice->show_activation_message();
		$output = ob_get_clean();

		$this->assertStringContainsString( "<div class='error'>", $output );
		$this->assertStringContainsString( 'Please activate your key', $output );
		$this->assertSame( array(), get_option( 'reepay_admin_activation_notices' ) );
	}

	/**
	 * Test @see WC_Reepay_Subscription_Admin_Notice::show_activation_message
	 * outputs nothing when there are no activation notices.
	 */
	public function test_show_activation_message_no_output_without_notices() {
		ob_start();
		$this->notice->show_activation_message();
		$output = ob_get_clean();

		$this->assertSame( '', $output );
	}

	/*
	 * ------------------------------------------------------------------
	 * show_thankyou_message()
	 * ------------------------------------------------------------------
	 */

	/**
	 * Test @see WC_Reepay_Subscription_Admin_Notice::show_thankyou_message
	 * returns a 'failed' state immediately from the order's
	 * '_reepay_frontend_notices' meta, without touching the reload/API logic
	 * at all.
	 */
	public function test_show_thankyou_message_frontend_notice_present_returns_failed() {
		$order = $this->order_generator->add_product( 'simple' )->order();
		$order->update_meta_data( '_reepay_frontend_notices', 'Card declined' );
		$order->save_meta_data();

		$result = $this->notice->show_thankyou_message( array(), $order->get_id() );

		$this->assertSame(
			array(
				'state'   => 'failed',
				'message' => 'Card declined',
			),
			$result
		);
	}

	/**
	 * Test @see WC_Reepay_Subscription_Admin_Notice::show_thankyou_message
	 * returns a 'reload' state and sets '_reepay_thankyou_reloaded' when the
	 * order contains a Reepay subscription product and has not yet been
	 * reloaded.
	 */
	public function test_show_thankyou_message_reload_path_for_subscription_order() {
		\Reepay\Subscriptions\Tests\Helpers\PLUGINS_STATE::maybe_skip_test_by_product_type( 'rp_sub' );

		$order = $this->order_generator->add_product( 'rp_sub' )->order();

		$result = $this->notice->show_thankyou_message( array(), $order->get_id() );

		$this->assertSame( array( 'state' => 'reload' ), $result );

		$order = wc_get_order( $order->get_id() );
		$this->assertEquals( 1, $order->get_meta( '_reepay_thankyou_reloaded' ) );
	}

	/**
	 * Test @see WC_Reepay_Subscription_Admin_Notice::show_thankyou_message
	 * returns a 'paid' state when the already-reloaded order's subscription
	 * handle resolves to an 'active' subscription via the (mocked) API call.
	 * This is the clearest of the three ways to reach the 'paid' state, per
	 * the brief's guidance to pick one.
	 */
	public function test_show_thankyou_message_paid_path_when_subscription_active() {
		$order = $this->order_generator->add_product( 'simple' )->order();
		$order->update_meta_data( '_reepay_thankyou_reloaded', true );
		$order->update_meta_data( '_reepay_subscription_handle', 'sub-handle-paid' );
		$order->save_meta_data();

		// in_trial and next_period_start are read unconditionally (no isset
		// guard) before the 'active' state check - omitting them trips
		// "Undefined array key" warnings which, under this suite's
		// convertNoticesToExceptions, are caught by the method's own
		// try/catch and surface as a 'failed' result instead. Confirmed by
		// running this exact test without these keys first.
		ApiResponseMock::respond_with(
			array(
				'handle'             => 'sub-handle-paid',
				'state'              => 'active',
				'in_trial'           => false,
				'next_period_start'  => '2030-01-01T00:00:00.000Z',
			)
		);

		$result = $this->notice->show_thankyou_message( array(), $order->get_id() );

		$this->assertSame( 'paid', $result['state'] );
		$this->assertSame( 'Order has been paid', $result['message'] );
	}

	/**
	 * Test @see WC_Reepay_Subscription_Admin_Notice::show_thankyou_message
	 * returns a 'failed' state with the exception's message when the
	 * (mocked) API call fails.
	 */
	public function test_show_thankyou_message_failed_path_on_api_exception() {
		$order = $this->order_generator->add_product( 'simple' )->order();
		$order->update_meta_data( '_reepay_thankyou_reloaded', true );
		$order->update_meta_data( '_reepay_subscription_handle', 'sub-handle-failed' );
		$order->save_meta_data();

		ApiResponseMock::respond_with( array( 'error' => 'invalid_request', 'message' => 'boom' ), 400 );

		$result = $this->notice->show_thankyou_message( array(), $order->get_id() );

		$this->assertSame( 'failed', $result['state'] );
		$this->assertNotEmpty( $result['message'] );
	}

	/**
	 * Test @see WC_Reepay_Subscription_Admin_Notice::show_thankyou_message
	 * returns the original $ret argument unchanged when the order has
	 * already been reloaded but carries no subscription handle at all (none
	 * of the three branches apply).
	 */
	public function test_show_thankyou_message_passthrough_without_subscription_handle() {
		$order = $this->order_generator->add_product( 'simple' )->order();
		$order->update_meta_data( '_reepay_thankyou_reloaded', true );
		$order->save_meta_data();

		$original = array( 'state' => 'unchanged' );
		$result   = $this->notice->show_thankyou_message( $original, $order->get_id() );

		$this->assertSame( $original, $result );
	}
}
