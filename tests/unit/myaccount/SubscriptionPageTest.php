<?php
/**
 * Class SubscriptionPageTest
 *
 * Covers WC_Reepay_My_Account_Subscription_Page
 * (includes/my-account/WC_Reepay_My_Account_Subscription_Page.php): NO exit
 * hazards in this class - every public/static method is safe to call
 * directly.
 *
 * Phase 5 scope: baseline regression coverage per requirements.md §3.4, not
 * exhaustive scenario enumeration.
 *
 * Confirmed real behavior (read from source):
 * - subscription_endpoint($subscription_handle) makes two real outbound API
 *   calls (subscription/{handle}, then plan/{plan}/current) and renders
 *   templates/myaccount/my-subscription.php on success, or
 *   templates/myaccount/my-subscriptions-error.php on any Exception (both
 *   confirmed to exist on disk). It ALSO calls
 *   get_customer_payment_methods($subscription_handle) with NO
 *   $customer_handle override (two more outbound calls) and
 *   get_dates_to_display($subscription) on the success path - meaning the
 *   success path transitively depends on rp_get_customer_handle(), which is
 *   only defined by the Frisbii Pay payment plugin (confirmed in
 *   reepay-woocommerce-payment/includes/Functions/customer.php:21, no
 *   function_exists() guard anywhere in this call chain). This is a SECOND,
 *   previously-unflagged place (beyond get_customer_payment_methods() and
 *   customer_has_access_to_subscription() called with no override) where this
 *   class's success path requires reepay_pay active - the success-path test
 *   below is guarded with PLUGINS_STATE::reepay_pay_activated() accordingly.
 *   ApiResponseMock::respond_with() serves the SAME response body to every
 *   outbound HTTP call for as long as it's registered (confirmed - it doesn't
 *   branch by URL), so a single merged fixture (containing both subscription-
 *   shaped and plan-shaped keys) is used for the success path test, since
 *   PHP's duck-typed array access tolerates the extra unused keys at each
 *   call site.
 * - get_dates_to_display($subscription) is a pure array-mapping function.
 * - get_customer_payment_methods($subscription_handle, $customer_handle = '')
 *   makes two real outbound API calls. rp_get_customer_handle() is called
 *   UNCONDITIONALLY only if $customer_handle is empty - always passing a
 *   non-empty $customer_handle explicitly sidesteps that dependency entirely.
 * - get_status($subscription) is a pure function: is_cancelled===true ->
 *   'Cancelled'; state==='expired' -> 'Expired'; state==='on_hold' ->
 *   'On hold'; state==='is_cancelled' -> 'Cancelled' (a second, differently
 *   reached, Cancelled case); state==='active' with a future trial_end ->
 *   'Trial'; state==='active' otherwise -> 'Active'; any other state ->
 *   returned as-is (raw fallback). Uses plain __() strings (no <mark> HTML),
 *   unlike WC_Reepay_Subscriptions_Table::format_status() from Task 5.1 -
 *   confirmed these two methods do NOT return identically-shaped strings.
 * - customer_has_access_to_subscription($subscription, $customer_handle = '')
 *   (static): same rp_get_customer_handle() dependency note - always pass
 *   $customer_handle explicitly here too. Throws Exception('Permission
 *   denied') on mismatch, returns null silently on match.
 *
 * @package Reepay\Subscriptions
 */

use Reepay\Subscriptions\Tests\Helpers\PLUGINS_STATE;
use Reepay\Subscriptions\Tests\Helpers\Reepay_Subscriptions_UnitTestCase;
use Reepay\Subscriptions\Tests\Mocks\ApiResponseMock;

/**
 * SubscriptionPageTest.
 */
class SubscriptionPageTest extends Reepay_Subscriptions_UnitTestCase {

	/**
	 * @var WC_Reepay_My_Account_Subscription_Page
	 */
	private WC_Reepay_My_Account_Subscription_Page $subscription_page;

	public function set_up() {
		parent::set_up();

		$this->subscription_page = new WC_Reepay_My_Account_Subscription_Page();

		// Same reflection-based API-key injection pattern as
		// tests/unit/renewals/LifecycleTest.php (see that file for the full
		// rationale) - WC_Reepay_Subscription_API caches its key from a
		// settings snapshot at bootstrap time.
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
	}

	public function tear_down() {
		ApiResponseMock::clear();

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

	/*
	 * ------------------------------------------------------------------
	 * subscription_endpoint()
	 * ------------------------------------------------------------------
	 */

	/**
	 * Test @see WC_Reepay_My_Account_Subscription_Page::subscription_endpoint
	 * success path: renders my-subscription.php with the subscription status
	 * visible in the output. Requires reepay_pay active, since the success
	 * path transitively calls rp_get_customer_handle() via
	 * get_customer_payment_methods($subscription_handle) (no override passed)
	 * - confirmed by reading the method body, see class docblock.
	 */
	public function test_subscription_endpoint_renders_success_template() {
		if ( ! PLUGINS_STATE::reepay_pay_activated() ) {
			$this->markTestSkipped( 'Frisbii Pay (reepay_pay) plugin required: subscription_endpoint() success path calls rp_get_customer_handle() with no override.' );
		}

		$user_id = self::factory()->user->create();
		wp_set_current_user( $user_id );

		$customer_handle = 'cust-' . __FUNCTION__;

		// Set the 'reepay_customer_id' user meta directly - this is exactly
		// what rp_get_customer_handle() reads first. Skipping this would make
		// it fall through to ReepayCustomer::set_reepay_handle(), which makes
		// its OWN outbound API call (via the Frisbii Pay plugin's reepay()->api(),
		// a different client than reepay_s()->api()) using the factory user's
		// real generated email - unpredictable and unrelated to this test's
		// mocked response. No production file touched.
		update_user_meta( $user_id, 'reepay_customer_id', $customer_handle );

		// Merged fixture: both subscription-shaped and plan-shaped keys present
		// at once, since ApiResponseMock serves the same body to every call.
		ApiResponseMock::respond_with(
			array(
				// Subscription-shaped keys.
				'handle'              => 'sub-handle-success',
				'plan'                => 'plan-handle-' . __FUNCTION__,
				'state'               => 'active',
				'is_cancelled'        => false,
				'customer'            => $customer_handle,
				'first_period_start'  => '2025-01-01T00:00:00.000Z',
				// Plan-shaped keys.
				'name'                => 'Gold Plan',
				'amount'              => 1000,
				'currency'            => 'DKK',
				'interval_length'     => 1,
				'partial_proration_days' => false,
			)
		);

		ob_start();
		$this->subscription_page->subscription_endpoint( 'sub-handle-success' );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Active', $output );
	}

	/**
	 * Test @see WC_Reepay_My_Account_Subscription_Page::subscription_endpoint
	 * exception path: renders my-subscriptions-error.php when the returned
	 * subscription's 'customer' does not match the current user's handle -
	 * customer_has_access_to_subscription() throws before the second
	 * (plan/current) API call is ever reached, sidestepping the dual-API-call
	 * response-shape issue entirely.
	 */
	public function test_subscription_endpoint_renders_error_template_on_access_denied() {
		if ( ! PLUGINS_STATE::reepay_pay_activated() ) {
			$this->markTestSkipped( 'Frisbii Pay (reepay_pay) plugin required: customer_has_access_to_subscription() with no override calls rp_get_customer_handle().' );
		}

		$user_id = self::factory()->user->create();
		wp_set_current_user( $user_id );

		ApiResponseMock::respond_with(
			array(
				'handle'   => 'sub-handle-denied',
				'plan'     => 'plan-handle-denied',
				'state'    => 'active',
				'customer' => 'someone-elses-customer-handle',
			)
		);

		ob_start();
		$this->subscription_page->subscription_endpoint( 'sub-handle-denied' );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Subscription not found', $output );
	}

	/**
	 * Test @see WC_Reepay_My_Account_Subscription_Page::subscription_endpoint
	 * exception path for a plain API failure (e.g. invalid handle causing a
	 * non-2xx/3xx response) - also renders the error template. Does not
	 * depend on reepay_pay since the exception is thrown before
	 * customer_has_access_to_subscription() is ever reached.
	 */
	public function test_subscription_endpoint_renders_error_template_on_api_failure() {
		ApiResponseMock::respond_with( array( 'error' => 'not_found' ), 404 );

		ob_start();
		$this->subscription_page->subscription_endpoint( 'sub-handle-missing' );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Subscription not found', $output );
	}

	/*
	 * ------------------------------------------------------------------
	 * get_dates_to_display()
	 * ------------------------------------------------------------------
	 */

	/**
	 * Test @see WC_Reepay_My_Account_Subscription_Page::get_dates_to_display
	 * maps all six known subscription date fields into labeled entries.
	 */
	public function test_get_dates_to_display_maps_known_fields() {
		$subscription = array(
			'first_period_start'   => '2025-01-01',
			'current_period_start' => '2025-02-01',
			'next_period_start'    => '2025-03-01',
			'expires'              => '2025-12-31',
			'trial_start'          => '2025-01-01',
			'trial_end'            => '2025-01-15',
		);

		$result = $this->subscription_page->get_dates_to_display( $subscription );

		$this->assertSame( '2025-01-01', $result['start_date']['value'] );
		$this->assertSame( '2025-02-01', $result['last_order_date_created']['value'] );
		$this->assertSame( '2025-03-01', $result['next_payment']['value'] );
		$this->assertSame( '2025-12-31', $result['end']['value'] );
		$this->assertSame( '2025-01-01', $result['start_end']['value'] );
		$this->assertSame( '2025-01-15', $result['trial_end']['value'] );
	}

	/**
	 * Test @see WC_Reepay_My_Account_Subscription_Page::get_dates_to_display
	 * falls back to empty strings for missing keys (null-coalescing, no
	 * notice/fatal).
	 */
	public function test_get_dates_to_display_defaults_missing_fields_to_empty_string() {
		$result = $this->subscription_page->get_dates_to_display( array() );

		foreach ( $result as $entry ) {
			$this->assertSame( '', $entry['value'] );
		}
	}

	/*
	 * ------------------------------------------------------------------
	 * get_customer_payment_methods()
	 * ------------------------------------------------------------------
	 */

	/**
	 * Test @see WC_Reepay_My_Account_Subscription_Page::get_customer_payment_methods
	 * merges the current payment method under the 'current' key with the
	 * customer's full payment method list, when a non-empty $customer_handle
	 * is passed explicitly (sidesteps the rp_get_customer_handle() dependency
	 * entirely).
	 */
	public function test_get_customer_payment_methods_merges_current_and_list() {
		// Same body served to both calls (subscription/{handle}/pm and
		// customer/{handle}/payment_method) - array_merge(['current' => body[0] ?? []], body)
		// still demonstrates the merge shape even though both calls share a body.
		ApiResponseMock::respond_with(
			array(
				array( 'id' => 'pm-1', 'type' => 'card' ),
				array( 'id' => 'pm-2', 'type' => 'card' ),
			)
		);

		$result = $this->subscription_page->get_customer_payment_methods( 'sub-handle-pm', 'cust-handle-explicit' );

		$this->assertArrayHasKey( 'current', $result );
		$this->assertSame( array( 'id' => 'pm-1', 'type' => 'card' ), $result['current'] );
	}

	/*
	 * ------------------------------------------------------------------
	 * get_status()
	 * ------------------------------------------------------------------
	 */

	/**
	 * Test @see WC_Reepay_My_Account_Subscription_Page::get_status returns
	 * 'Cancelled' when is_cancelled is strictly true, regardless of state.
	 */
	public function test_get_status_cancelled_via_is_cancelled_flag() {
		$result = WC_Reepay_My_Account_Subscription_Page::get_status( array( 'is_cancelled' => true, 'state' => 'active' ) );

		$this->assertSame( 'Cancelled', $result );
	}

	/**
	 * Test @see WC_Reepay_My_Account_Subscription_Page::get_status returns
	 * 'Expired' for state === 'expired'.
	 */
	public function test_get_status_expired() {
		$result = WC_Reepay_My_Account_Subscription_Page::get_status( array( 'is_cancelled' => false, 'state' => 'expired' ) );

		$this->assertSame( 'Expired', $result );
	}

	/**
	 * Test @see WC_Reepay_My_Account_Subscription_Page::get_status returns
	 * 'On hold' for state === 'on_hold'.
	 */
	public function test_get_status_on_hold() {
		$result = WC_Reepay_My_Account_Subscription_Page::get_status( array( 'is_cancelled' => false, 'state' => 'on_hold' ) );

		$this->assertSame( 'On hold', $result );
	}

	/**
	 * Test @see WC_Reepay_My_Account_Subscription_Page::get_status returns
	 * 'Cancelled' for the literal state === 'is_cancelled' (a second,
	 * differently-reached Cancelled branch - confirmed distinct from the
	 * is_cancelled-flag branch above).
	 */
	public function test_get_status_cancelled_via_literal_state_value() {
		$result = WC_Reepay_My_Account_Subscription_Page::get_status( array( 'is_cancelled' => false, 'state' => 'is_cancelled' ) );

		$this->assertSame( 'Cancelled', $result );
	}

	/**
	 * Test @see WC_Reepay_My_Account_Subscription_Page::get_status returns
	 * 'Trial' for state === 'active' with a trial_end in the future.
	 */
	public function test_get_status_active_with_future_trial_end_is_trial() {
		$result = WC_Reepay_My_Account_Subscription_Page::get_status(
			array(
				'is_cancelled' => false,
				'state'        => 'active',
				'trial_end'    => gmdate( 'c', strtotime( '+10 days' ) ),
			)
		);

		$this->assertSame( 'Trial', $result );
	}

	/**
	 * Test @see WC_Reepay_My_Account_Subscription_Page::get_status returns
	 * 'Active' for state === 'active' with a trial_end already in the past.
	 */
	public function test_get_status_active_with_past_trial_end_is_active() {
		$result = WC_Reepay_My_Account_Subscription_Page::get_status(
			array(
				'is_cancelled' => false,
				'state'        => 'active',
				'trial_end'    => gmdate( 'c', strtotime( '-10 days' ) ),
			)
		);

		$this->assertSame( 'Active', $result );
	}

	/**
	 * Test @see WC_Reepay_My_Account_Subscription_Page::get_status returns
	 * 'Active' for state === 'active' with no trial_end key at all.
	 */
	public function test_get_status_active_without_trial_end_key() {
		$result = WC_Reepay_My_Account_Subscription_Page::get_status( array( 'is_cancelled' => false, 'state' => 'active' ) );

		$this->assertSame( 'Active', $result );
	}

	/**
	 * Test @see WC_Reepay_My_Account_Subscription_Page::get_status falls back
	 * to the raw state string for any unrecognized state.
	 */
	public function test_get_status_unrecognized_state_returns_raw_value() {
		$result = WC_Reepay_My_Account_Subscription_Page::get_status( array( 'is_cancelled' => false, 'state' => 'some_future_state' ) );

		$this->assertSame( 'some_future_state', $result );
	}

	/*
	 * ------------------------------------------------------------------
	 * customer_has_access_to_subscription()
	 * ------------------------------------------------------------------
	 */

	/**
	 * Test @see WC_Reepay_My_Account_Subscription_Page::customer_has_access_to_subscription
	 * returns silently (no exception) when the subscription's 'customer' value
	 * matches the explicitly-passed $customer_handle.
	 */
	public function test_customer_has_access_to_subscription_passes_on_match() {
		$result = WC_Reepay_My_Account_Subscription_Page::customer_has_access_to_subscription(
			array( 'customer' => 'cust-match-1' ),
			'cust-match-1'
		);

		$this->assertNull( $result );
	}

	/**
	 * Test @see WC_Reepay_My_Account_Subscription_Page::customer_has_access_to_subscription
	 * throws an Exception('Permission denied') when the subscription's
	 * 'customer' value does not match the explicitly-passed $customer_handle.
	 */
	public function test_customer_has_access_to_subscription_throws_on_mismatch() {
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Permission denied' );

		WC_Reepay_My_Account_Subscription_Page::customer_has_access_to_subscription(
			array( 'customer' => 'cust-real' ),
			'cust-different'
		);
	}
}
