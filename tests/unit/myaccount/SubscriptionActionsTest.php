<?php
/**
 * Class SubscriptionActionsTest
 *
 * Covers WC_Reepay_My_Account_Subscription_Actions
 * (includes/my-account/WC_Reepay_My_Account_Subscription_Actions.php):
 * the four safe early-return/fallthrough branches of the public do_action()
 * wrapper, plus the five PRIVATE do_action_* business-logic methods invoked
 * directly via ReflectionMethod (same technique as Task 4.4's
 * get_min_max_price_variations(), per the brief).
 *
 * EXIT HAZARD on do_action(): confirmed by reading the full method. Every
 * branch past the four safe early returns below (invalid handle format, or
 * any of the five recognized actions actually executing, success or
 * exception) ends in wp_redirect() + exit()/exit; - do_action() is NEVER
 * called directly in this file for those paths. The five private
 * do_action_cancel_subscription/do_action_uncancel_subscription/
 * do_action_put_on_hold/do_action_reactivate/do_action_change_payment_method
 * methods are NOT exit-laden themselves (confirmed: the exit only happens in
 * the wrapping do_action() after the switch statement returns), so they are
 * invoked directly via ReflectionMethod below - this is fully exit-safe and
 * is the highest-value coverage in this file.
 *
 * NOT covered here: WC_Reepay_My_Account_Subscription_Page::customer_has_access_to_subscription()
 * is called inside the PUBLIC do_action() wrapper itself (not inside any of
 * the five private methods), so it is only reachable through the exit-laden
 * wrapper from this class's perspective - it is independently covered as a
 * static method in SubscriptionPageTest.php per the brief, and do_action()'s
 * own wrapper-level behavior around it is out of scope here.
 *
 * Phase 5 scope: baseline regression coverage per requirements.md §3.4, not
 * exhaustive scenario enumeration.
 *
 * Confirmed real behavior (read from source):
 * - do_action() safe branches: (1) $_GET['reepay_subscriptions_action'] unset
 *   -> return, no notice; (2) set but !is_user_logged_in() -> error notice
 *   "You must be logged in..." then return, no exit; (3) logged in, missing/
 *   invalid $_GET['_wpnonce'] -> error notice "Security check failed..." then
 *   return, no exit; (4) logged in + valid nonce, but none of the five action
 *   keys present in $_GET -> foreach loop finds nothing, falls off the end,
 *   no exit.
 * - do_action_cancel_subscription($handle) only calls the API
 *   (subscription/{handle}/cancel, POST) if
 *   reepay_s()->settings('_reepay_enable_cancel') is truthy.
 * - do_action_uncancel_subscription($handle) always calls the API
 *   (subscription/{handle}/uncancel, POST) unconditionally.
 * - do_action_put_on_hold($handle) is gated the same way as cancel, via
 *   reepay_s()->settings('_reepay_enable_on_hold'), and sends a
 *   'compensation_method' param sourced from
 *   reepay_s()->settings('_reepay_on_hold_compensation_method').
 * - do_action_reactivate($handle) always calls the API
 *   (subscription/{handle}/reactivate, POST) unconditionally.
 * - do_action_change_payment_method($handle) does nothing (no exception, no
 *   API call) if $_GET['token_id'] is empty. If set: validates the format via
 *   preg_match('/^[a-zA-Z0-9_-]{10,64}$/', $token_id) - throws Exception on
 *   mismatch; then verifies ownership against
 *   WC_Payment_Tokens::get_customer_tokens(get_current_user_id()) - throws
 *   Exception if no match; then calls the API (subscription/{handle}/pm,
 *   POST) with 'source' => $token_id if ownership passes.
 *
 * @package Reepay\Subscriptions
 */

use Reepay\Subscriptions\Tests\Helpers\Reepay_Subscriptions_Ajax_UnitTestCase;
use Reepay\Subscriptions\Tests\Helpers\PLUGINS_STATE;
use Reepay\Subscriptions\Tests\Mocks\ApiResponseMock;

/**
 * SubscriptionActionsTest.
 */
class SubscriptionActionsTest extends Reepay_Subscriptions_Ajax_UnitTestCase {

	/**
	 * @var WC_Reepay_My_Account_Subscription_Actions
	 */
	private WC_Reepay_My_Account_Subscription_Actions $subscription_actions;

	/**
	 * Snapshot of the WooCommerce_Reepay_Subscriptions::$settings static
	 * property, restored in tear_down() so one test's settings override can't
	 * leak into another test (that static array is populated once at plugin
	 * bootstrap and otherwise persists for the whole PHP process).
	 *
	 * @var array
	 */
	private array $settings_snapshot;

	public function set_up() {
		parent::set_up();

		$this->subscription_actions = new WC_Reepay_My_Account_Subscription_Actions();

		// WC notices persist across tests via the WC session unless cleared -
		// confirmed necessary here (a prior test's "You must be logged in"
		// notice otherwise leaks into a later test's wc_get_notices('error')
		// assertion). Pattern copied from tests/unit/CheckoutTest.php.
		wc_clear_notices();

		update_option(
			'woocommerce_reepay_checkout_settings',
			array(
				'private_key_test' => 'test-key-reepay',
				'test_mode'        => 'yes',
			)
		);

		$this->set_fake_private_key( WC_Reepay_Subscription_API::get_instance(), 'private_key_test', 'test-key-reepay' );
		$this->set_fake_private_key( WC_Reepay_Subscription_API::get_instance(), 'test_mode', true );

		if ( PLUGINS_STATE::reepay_pay_activated() ) {
			reepay()->reset_settings();
		}

		$this->settings_snapshot = reepay_s()->settings();
	}

	public function tear_down() {
		ApiResponseMock::clear();
		$this->set_static_settings( $this->settings_snapshot );

		parent::tear_down();
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

	/**
	 * Overwrite a single key of WooCommerce_Reepay_Subscriptions's private
	 * static $settings property via reflection. Needed because
	 * reepay_s()->settings('_reepay_enable_cancel') etc. are snapshotted once
	 * from get_option() at plugin bootstrap - long before any option set in a
	 * test's set_up() would be read - so updating the option alone has no
	 * effect on an already-bootstrapped process. Test-only wiring, no
	 * production file touched.
	 *
	 * @param string $key settings array key.
	 * @param mixed  $value value to assign.
	 */
	private function set_static_setting( string $key, $value ): void {
		$ref = new ReflectionProperty( WooCommerce_Reepay_Subscriptions::class, 'settings' );
		$ref->setAccessible( true );
		$settings         = $ref->getValue();
		$settings[ $key ] = $value;
		$ref->setValue( null, $settings );
	}

	/**
	 * Restore the full static $settings array (used in tear_down() to undo
	 * set_static_setting() calls made during a test).
	 *
	 * @param array $settings full settings array to restore.
	 */
	private function set_static_settings( array $settings ): void {
		$ref = new ReflectionProperty( WooCommerce_Reepay_Subscriptions::class, 'settings' );
		$ref->setAccessible( true );
		$ref->setValue( null, $settings );
	}

	/**
	 * Invoke a private do_action_* method via reflection - these are not
	 * exit-laden themselves (confirmed: the exit only happens in the public
	 * do_action() wrapper, after the switch statement), so this is a fully
	 * exit-safe way to exercise the real business logic. Same technique as
	 * Task 4.4's invoke_get_min_max_price_variations().
	 *
	 * @param string $method_name private method name.
	 * @param mixed  ...$args arguments to pass through.
	 *
	 * @return mixed
	 */
	private function invoke_private_action( string $method_name, ...$args ) {
		$method = new ReflectionMethod( $this->subscription_actions, $method_name );
		$method->setAccessible( true );

		return $method->invoke( $this->subscription_actions, ...$args );
	}

	/*
	 * ------------------------------------------------------------------
	 * do_action() - the four safe early-return/fallthrough branches
	 * ------------------------------------------------------------------
	 */

	/**
	 * Test @see WC_Reepay_My_Account_Subscription_Actions::do_action returns
	 * immediately with no notice when $_GET['reepay_subscriptions_action'] is
	 * not set at all.
	 */
	public function test_do_action_noop_without_action_param() {
		unset( $_GET['reepay_subscriptions_action'] );

		$this->subscription_actions->do_action();

		$this->assertEmpty( wc_get_notices( 'error' ) );
	}

	/**
	 * Test @see WC_Reepay_My_Account_Subscription_Actions::do_action adds an
	 * error notice and returns (no exit) when the user is not logged in.
	 */
	public function test_do_action_not_logged_in_adds_error_notice() {
		wp_set_current_user( 0 );
		$_GET['reepay_subscriptions_action'] = '1';

		$this->subscription_actions->do_action();

		$notices = wc_get_notices( 'error' );
		$this->assertNotEmpty( $notices );
		$this->assertStringContainsString( 'You must be logged in', $notices[0]['notice'] );
	}

	/**
	 * Test @see WC_Reepay_My_Account_Subscription_Actions::do_action adds an
	 * error notice and returns (no exit) when logged in but the nonce is
	 * missing/invalid.
	 */
	public function test_do_action_invalid_nonce_adds_error_notice() {
		$user_id = self::factory()->user->create();
		wp_set_current_user( $user_id );

		$_GET['reepay_subscriptions_action'] = '1';
		$_GET['_wpnonce']                    = 'not-a-real-nonce';

		$this->subscription_actions->do_action();

		$notices = wc_get_notices( 'error' );
		$this->assertNotEmpty( $notices );
		$this->assertStringContainsString( 'Security check failed', $notices[0]['notice'] );
	}

	/**
	 * Test @see WC_Reepay_My_Account_Subscription_Actions::do_action falls off
	 * the end with no exit and no notice when logged in with a valid nonce but
	 * none of the five recognized action keys are present in $_GET.
	 */
	public function test_do_action_valid_nonce_no_recognized_action_is_noop() {
		$user_id = self::factory()->user->create();
		wp_set_current_user( $user_id );

		$_GET['reepay_subscriptions_action'] = '1';
		$_GET['_wpnonce']                    = wp_create_nonce( 'reepay_subscription_action' );
		// Deliberately no cancel_subscription/uncancel_subscription/put_on_hold/
		// reactivate/change_payment_method key set.

		$this->subscription_actions->do_action();

		$this->assertEmpty( wc_get_notices( 'error' ) );
	}

	/*
	 * ------------------------------------------------------------------
	 * do_action_cancel_subscription() [private, via reflection]
	 * ------------------------------------------------------------------
	 */

	/**
	 * Test @see WC_Reepay_My_Account_Subscription_Actions::do_action_cancel_subscription
	 * calls the cancel API endpoint when the feature is enabled.
	 */
	public function test_do_action_cancel_subscription_calls_api_when_enabled() {
		$this->set_static_setting( '_reepay_enable_cancel', true );

		$captured_url = null;
		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) use ( &$captured_url ) {
				$captured_url = $url;

				return array(
					'headers'  => array(),
					'body'     => wp_json_encode( array( 'state' => 'cancelled' ) ),
					'response' => array( 'code' => 200, 'message' => 'OK' ),
					'cookies'  => array(),
					'filename' => null,
				);
			},
			10,
			3
		);

		$this->invoke_private_action( 'do_action_cancel_subscription', 'sub-cancel-handle' );

		$this->assertNotNull( $captured_url, 'Expected an outbound API call when _reepay_enable_cancel is true.' );
		$this->assertStringContainsString( 'subscription/sub-cancel-handle/cancel', $captured_url );
	}

	/**
	 * Test @see WC_Reepay_My_Account_Subscription_Actions::do_action_cancel_subscription
	 * makes no API call when the feature is disabled - no ApiResponseMock
	 * response is registered, so any attempted call would throw/fatal; the
	 * assertion is that nothing breaks.
	 */
	public function test_do_action_cancel_subscription_skips_api_when_disabled() {
		$this->set_static_setting( '_reepay_enable_cancel', false );

		$this->invoke_private_action( 'do_action_cancel_subscription', 'sub-cancel-handle-2' );

		$this->addToAssertionCount( 1 ); // Reaching here without an exception/API call is the assertion.
	}

	/*
	 * ------------------------------------------------------------------
	 * do_action_uncancel_subscription() [private, via reflection]
	 * ------------------------------------------------------------------
	 */

	/**
	 * Test @see WC_Reepay_My_Account_Subscription_Actions::do_action_uncancel_subscription
	 * always calls the uncancel API endpoint unconditionally.
	 */
	public function test_do_action_uncancel_subscription_always_calls_api() {
		ApiResponseMock::respond_with( array( 'state' => 'active' ) );

		$captured_url = null;
		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) use ( &$captured_url ) {
				$captured_url = $url;

				return $preempt;
			},
			5,
			3
		);

		$this->invoke_private_action( 'do_action_uncancel_subscription', 'sub-uncancel-handle' );

		$this->assertNotNull( $captured_url );
		$this->assertStringContainsString( 'subscription/sub-uncancel-handle/uncancel', $captured_url );
	}

	/*
	 * ------------------------------------------------------------------
	 * do_action_put_on_hold() [private, via reflection]
	 * ------------------------------------------------------------------
	 */

	/**
	 * Test @see WC_Reepay_My_Account_Subscription_Actions::do_action_put_on_hold
	 * calls the on_hold API endpoint with the configured compensation_method
	 * param when the feature is enabled.
	 */
	public function test_do_action_put_on_hold_calls_api_with_compensation_method_when_enabled() {
		$this->set_static_setting( '_reepay_enable_on_hold', true );
		$this->set_static_setting( '_reepay_on_hold_compensation_method', 'full' );

		$captured_url  = null;
		$captured_body = null;
		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) use ( &$captured_url, &$captured_body ) {
				$captured_url  = $url;
				$captured_body = $args['body'] ?? null;

				return array(
					'headers'  => array(),
					'body'     => wp_json_encode( array( 'state' => 'on_hold' ) ),
					'response' => array( 'code' => 200, 'message' => 'OK' ),
					'cookies'  => array(),
					'filename' => null,
				);
			},
			10,
			3
		);

		$this->invoke_private_action( 'do_action_put_on_hold', 'sub-hold-handle' );

		$this->assertNotNull( $captured_url );
		$this->assertStringContainsString( 'subscription/sub-hold-handle/on_hold', $captured_url );
		$this->assertStringContainsString( 'full', (string) $captured_body );
	}

	/**
	 * Test @see WC_Reepay_My_Account_Subscription_Actions::do_action_put_on_hold
	 * makes no API call when the feature is disabled.
	 */
	public function test_do_action_put_on_hold_skips_api_when_disabled() {
		$this->set_static_setting( '_reepay_enable_on_hold', false );

		$this->invoke_private_action( 'do_action_put_on_hold', 'sub-hold-handle-2' );

		$this->addToAssertionCount( 1 ); // Reaching here without an exception/API call is the assertion.
	}

	/*
	 * ------------------------------------------------------------------
	 * do_action_reactivate() [private, via reflection]
	 * ------------------------------------------------------------------
	 */

	/**
	 * Test @see WC_Reepay_My_Account_Subscription_Actions::do_action_reactivate
	 * always calls the reactivate API endpoint unconditionally.
	 */
	public function test_do_action_reactivate_always_calls_api() {
		ApiResponseMock::respond_with( array( 'state' => 'active' ) );

		$captured_url = null;
		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) use ( &$captured_url ) {
				$captured_url = $url;

				return $preempt;
			},
			5,
			3
		);

		$this->invoke_private_action( 'do_action_reactivate', 'sub-reactivate-handle' );

		$this->assertNotNull( $captured_url );
		$this->assertStringContainsString( 'subscription/sub-reactivate-handle/reactivate', $captured_url );
	}

	/*
	 * ------------------------------------------------------------------
	 * do_action_change_payment_method() [private, via reflection]
	 * ------------------------------------------------------------------
	 */

	/**
	 * Test @see WC_Reepay_My_Account_Subscription_Actions::do_action_change_payment_method
	 * is a complete no-op (no exception, no API call) when $_GET['token_id']
	 * is empty.
	 */
	public function test_do_action_change_payment_method_noop_without_token_id() {
		unset( $_GET['token_id'] );

		$this->invoke_private_action( 'do_action_change_payment_method', 'sub-pm-handle-1' );

		$this->addToAssertionCount( 1 ); // Reaching here without an exception/API call is the assertion.
	}

	/**
	 * Test @see WC_Reepay_My_Account_Subscription_Actions::do_action_change_payment_method
	 * throws an Exception for a token_id that doesn't match the required
	 * format (too short - must be 10-64 chars of [a-zA-Z0-9_-]).
	 */
	public function test_do_action_change_payment_method_invalid_format_throws() {
		$_GET['token_id'] = 'short';

		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Invalid token format.' );

		$this->invoke_private_action( 'do_action_change_payment_method', 'sub-pm-handle-2' );
	}

	/**
	 * Test @see WC_Reepay_My_Account_Subscription_Actions::do_action_change_payment_method
	 * throws an Exception for a validly-shaped token_id that does not belong
	 * to the current user (no matching WC_Payment_Token found).
	 */
	public function test_do_action_change_payment_method_not_owned_token_throws() {
		$user_id = self::factory()->user->create();
		wp_set_current_user( $user_id );

		$_GET['token_id'] = 'ca_not_owned_12345';

		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Invalid payment method.' );

		$this->invoke_private_action( 'do_action_change_payment_method', 'sub-pm-handle-3' );
	}

	/**
	 * Test @see WC_Reepay_My_Account_Subscription_Actions::do_action_change_payment_method
	 * succeeds (calls the API) for a validly-shaped token_id that DOES belong
	 * to the current user, via a real WC_Payment_Token_CC saved for that user.
	 *
	 * Uses the core 'bacs' gateway id (not 'reepay_checkout') for the saved
	 * token. Confirmed by debugging: WC_Payment_Tokens::get_customer_tokens()
	 * with no $gateway_id override (exactly how do_action_change_payment_method()
	 * calls it) restricts its data-store query to
	 * WC_Payment_Gateways::instance()->get_payment_gateway_ids() - i.e. only
	 * CURRENTLY REGISTERED gateway ids (plus an empty gateway_id) - confirmed
	 * in WC_Payment_Token_Data_Store::get_tokens(). 'reepay_checkout' is only
	 * registered when the Frisbii Pay plugin is active, so under woo-only a
	 * token saved with that gateway_id is silently excluded and ownership
	 * verification would spuriously fail - a test-fixture artifact, not a
	 * production code issue (the production method itself never inspects the
	 * token's gateway_id). 'bacs' is always registered by WooCommerce core,
	 * making this test plugin-combo-independent.
	 */
	public function test_do_action_change_payment_method_owned_token_calls_api() {
		$user_id = self::factory()->user->create();
		wp_set_current_user( $user_id );

		$token = new WC_Payment_Token_CC();
		$token->set_token( 'ca_owned_token_123456' );
		$token->set_gateway_id( 'bacs' );
		$token->set_user_id( $user_id );
		$token->set_card_type( 'visa' );
		$token->set_last4( '4242' );
		$token->set_expiry_month( '01' );
		$token->set_expiry_year( '2030' );
		$token->save();

		$_GET['token_id'] = 'ca_owned_token_123456';

		$captured_url  = null;
		$captured_body = null;
		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) use ( &$captured_url, &$captured_body ) {
				$captured_url  = $url;
				$captured_body = $args['body'] ?? null;

				return array(
					'headers'  => array(),
					'body'     => wp_json_encode( array( 'state' => 'active' ) ),
					'response' => array( 'code' => 200, 'message' => 'OK' ),
					'cookies'  => array(),
					'filename' => null,
				);
			},
			10,
			3
		);

		$this->invoke_private_action( 'do_action_change_payment_method', 'sub-pm-handle-4' );

		$this->assertNotNull( $captured_url );
		$this->assertStringContainsString( 'subscription/sub-pm-handle-4/pm', $captured_url );
		$this->assertStringContainsString( 'ca_owned_token_123456', (string) $captured_body );
	}
}
