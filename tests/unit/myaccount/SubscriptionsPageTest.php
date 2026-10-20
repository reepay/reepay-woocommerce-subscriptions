<?php
/**
 * Class SubscriptionsPageTest
 *
 * Covers WC_Reepay_My_Account_Subscriptions_Page
 * (includes/my-account/WC_Reepay_My_Account_Subscriptions_Page.php) - plural,
 * a DIFFERENT class from WC_Reepay_My_Account_Subscription_Page (singular).
 * NO exit hazards in this class.
 *
 * Phase 5 scope: baseline regression coverage per requirements.md §3.4, not
 * exhaustive scenario enumeration.
 *
 * CRITICAL caching gotcha, confirmed by reading the method bodies: both
 * private helpers get_plans_from_subscriptions($subscriptions) and
 * get_billing_period_for_plan($plan_data) use a PHP `static $variable = []`
 * INSIDE the method body (a function-local static, NOT a class-static
 * property) as a cache, keyed by plan handle. Function-local statics persist
 * for the lifetime of the PHP process - since PHPUnit runs all tests in a
 * single process by default, this cache persists across EVERY test in the
 * entire run, not just within this file. Every test below generates a unique
 * plan handle (via __FUNCTION__ and/or uniqid()) to guarantee no collision
 * with any other test anywhere in the suite, rather than reusing a fixed
 * literal handle.
 *
 * Confirmed real behavior (read from source):
 * - add_subscriptions_menu_item($menu_items) is a pure array-mutation
 *   function: inserts a 'Subscriptions' menu item immediately after the
 *   'orders' key, or does nothing if the subscriptions key already exists.
 * - subscriptions_endpoint() calls rp_get_customer_handle(get_current_user_id())
 *   DIRECTLY with no parameter to override it (unlike the methods on the
 *   singular Subscription_Page class) - this genuinely fatals under any combo
 *   without the Frisbii Pay payment plugin active (woo-only, woo,woo_subs-only).
 *   Guarded here with PLUGINS_STATE::reepay_pay_activated() + markTestSkipped().
 *   Makes a real outbound API call (list/subscription?customer=...&size=100)
 *   and renders templates/myaccount/my-subscriptions.php (success, non-empty
 *   content array) or templates/myaccount/my-subscriptions-error.php (both
 *   the "no customer handle" and "empty subscriptions list" exception paths
 *   land here with different messages).
 * - prepare_subscriptions_template_data() additionally calls
 *   rp_make_initial_amount() (also only defined by the Frisbii Pay payment
 *   plugin, confirmed in reepay-woocommerce-payment/includes/Functions/currency.php:33)
 *   whenever a matching plan was found - a second, previously-unflagged
 *   reepay_pay dependency in this same success path, on top of
 *   rp_get_customer_handle().
 *
 * @package Reepay\Subscriptions
 */

use Reepay\Subscriptions\Tests\Helpers\PLUGINS_STATE;
use Reepay\Subscriptions\Tests\Helpers\Reepay_Subscriptions_UnitTestCase;
use Reepay\Subscriptions\Tests\Mocks\ApiResponseMock;

/**
 * SubscriptionsPageTest.
 */
class SubscriptionsPageTest extends Reepay_Subscriptions_UnitTestCase {

	/**
	 * @var WC_Reepay_My_Account_Subscriptions_Page
	 */
	private WC_Reepay_My_Account_Subscriptions_Page $subscriptions_page;

	public function set_up() {
		parent::set_up();

		$this->subscriptions_page = new WC_Reepay_My_Account_Subscriptions_Page();

		// Same reflection-based API-key injection pattern as
		// tests/unit/renewals/LifecycleTest.php (see that file for the full
		// rationale).
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
		remove_all_filters( 'pre_http_request' );
		// Re-attach ApiResponseMock's own management is unaffected since it
		// manages its own filter removal via clear() above; remove_all_filters()
		// here only guards against a leftover custom per-test URL-branching
		// filter (see test_subscriptions_endpoint_renders_success_template()).

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
	 * add_subscriptions_menu_item()
	 * ------------------------------------------------------------------
	 */

	/**
	 * Test @see WC_Reepay_My_Account_Subscriptions_Page::add_subscriptions_menu_item
	 * inserts the 'Subscriptions' entry immediately after the 'orders' key.
	 */
	public function test_add_subscriptions_menu_item_inserts_after_orders() {
		$menu_items = array(
			'dashboard' => 'Dashboard',
			'orders'    => 'Orders',
			'edit-address' => 'Addresses',
		);

		$result = $this->subscriptions_page->add_subscriptions_menu_item( $menu_items );

		$keys = array_keys( $result );
		$this->assertSame( 'orders', $keys[1] );
		$this->assertSame( WC_Reepay_My_Account_Subscriptions_Page::$menu_item_slug, $keys[2] );
	}

	/**
	 * Test @see WC_Reepay_My_Account_Subscriptions_Page::add_subscriptions_menu_item
	 * returns the menu items unchanged when the subscriptions key is already
	 * present.
	 */
	public function test_add_subscriptions_menu_item_noop_when_already_present() {
		$menu_items = array(
			'orders' => 'Orders',
			WC_Reepay_My_Account_Subscriptions_Page::$menu_item_slug => 'Existing Subscriptions',
		);

		$result = $this->subscriptions_page->add_subscriptions_menu_item( $menu_items );

		$this->assertSame( $menu_items, $result );
	}

	/**
	 * Test @see WC_Reepay_My_Account_Subscriptions_Page::add_subscriptions_menu_item
	 * does nothing (no 'orders' key to anchor on) when the menu has no orders
	 * entry at all.
	 */
	public function test_add_subscriptions_menu_item_noop_without_orders_key() {
		$menu_items = array( 'dashboard' => 'Dashboard' );

		$result = $this->subscriptions_page->add_subscriptions_menu_item( $menu_items );

		$this->assertArrayNotHasKey( WC_Reepay_My_Account_Subscriptions_Page::$menu_item_slug, $result );
	}

	/*
	 * ------------------------------------------------------------------
	 * subscriptions_endpoint()
	 * ------------------------------------------------------------------
	 */

	/**
	 * Test @see WC_Reepay_My_Account_Subscriptions_Page::subscriptions_endpoint
	 * "no customer handle" early-return branch (empty($reepay_customer_handle)
	 * inside subscriptions_endpoint() itself).
	 *
	 * IMPORTANT FINDING, not assumed by the brief: this branch is DEAD CODE in
	 * practice. Confirmed by reading rp_get_customer_handle() in full
	 * (reepay-woocommerce-payment/includes/Functions/customer.php:13-36): for
	 * ANY $user_id, if the 'reepay_customer_id' user meta is empty AND
	 * ReepayCustomer::set_reepay_handle() also returns empty (e.g. no API
	 * match), the function falls back to 'customer-' . $user_id (if the user
	 * exists) or 'cust-' . TimeKeeper::get() (if it doesn't) - BOTH fallbacks
	 * are always non-empty strings. There is no path through this function
	 * that returns ''. So empty($reepay_customer_handle) can never be true in
	 * subscriptions_endpoint(), and this specific exception message
	 * ("You have no active subscriptions." reached via the empty-handle path)
	 * is unreachable from real code, as opposed to the structurally identical
	 * message reached via the "empty subscriptions list" path (tested below),
	 * which IS reachable. This test instead documents the real observed
	 * behavior for a user with no 'reepay_customer_id' meta set: the fallback
	 * 'customer-{$user_id}' handle is used, and subscriptions_endpoint()
	 * proceeds to call the list/subscription API with that fallback handle
	 * (confirmed via a custom pre_http_request filter asserting on the
	 * requested URL) rather than short-circuiting.
	 */
	public function test_subscriptions_endpoint_falls_back_to_generated_handle_without_customer_meta() {
		if ( ! PLUGINS_STATE::reepay_pay_activated() ) {
			$this->markTestSkipped( 'Frisbii Pay (reepay_pay) plugin required: subscriptions_endpoint() calls rp_get_customer_handle() unconditionally with no override.' );
		}

		$user_id = self::factory()->user->create();
		wp_set_current_user( $user_id );
		// Deliberately no 'reepay_customer_id' user meta set.

		$captured_url = null;
		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) use ( &$captured_url ) {
				$captured_url = $url;

				return array(
					'headers'  => array(),
					'body'     => wp_json_encode( array( 'content' => array() ) ),
					'response' => array( 'code' => 200, 'message' => 'OK' ),
					'cookies'  => array(),
					'filename' => null,
				);
			},
			10,
			3
		);

		ob_start();
		$this->subscriptions_page->subscriptions_endpoint();
		$output = ob_get_clean();

		$this->assertNotNull( $captured_url, 'Expected subscriptions_endpoint() to proceed to the list/subscription API call using a generated fallback handle.' );
		$this->assertStringContainsString( 'customer=customer-' . $user_id, $captured_url );
		$this->assertStringContainsString( 'You have no active subscriptions.', $output, 'Reached via the empty-subscriptions-list path, not the empty-handle path.' );
	}

	/**
	 * Test @see WC_Reepay_My_Account_Subscriptions_Page::subscriptions_endpoint
	 * renders the error template with "no active subscriptions" when the API
	 * returns an empty subscriptions list for a valid customer handle (the
	 * SECOND, differently-reached, same-message exception path).
	 */
	public function test_subscriptions_endpoint_renders_error_with_empty_subscriptions_list() {
		if ( ! PLUGINS_STATE::reepay_pay_activated() ) {
			$this->markTestSkipped( 'Frisbii Pay (reepay_pay) plugin required: subscriptions_endpoint() calls rp_get_customer_handle() unconditionally with no override.' );
		}

		$user_id = self::factory()->user->create();
		wp_set_current_user( $user_id );

		// Set the 'reepay_customer_id' user meta directly - this is exactly
		// what rp_get_customer_handle() reads first, before falling back to
		// ReepayCustomer::set_reepay_handle() (which would make its own
		// outbound API call and could interfere with this test's mocked
		// response). No production file touched.
		update_user_meta( $user_id, 'reepay_customer_id', 'cust-' . __FUNCTION__ );

		ApiResponseMock::respond_with( array( 'content' => array() ) );

		ob_start();
		$this->subscriptions_page->subscriptions_endpoint();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'You have no active subscriptions.', $output );
	}

	/**
	 * Test @see WC_Reepay_My_Account_Subscriptions_Page::subscriptions_endpoint
	 * success path: renders my-subscriptions.php with the subscription's
	 * billing period/amount threaded through from a mocked plan response.
	 * Requires a custom pre_http_request filter branching by URL (rather than
	 * ApiResponseMock, which serves one body to every call) since this method
	 * makes two STRUCTURALLY DIFFERENT calls - list/subscription (needs a
	 * {content: [...]} wrapper) and plan/{handle}/current (needs flat plan
	 * fields) - that cannot share one mocked body shape. A unique plan handle
	 * (uniqid() + __FUNCTION__) sidesteps the function-local static cache
	 * gotcha documented in the class docblock above.
	 */
	public function test_subscriptions_endpoint_renders_success_with_plan_data() {
		if ( ! PLUGINS_STATE::reepay_pay_activated() ) {
			$this->markTestSkipped( 'Frisbii Pay (reepay_pay) plugin required: subscriptions_endpoint() and prepare_subscriptions_template_data() both call reepay_pay-only functions unconditionally.' );
		}

		$user_id = self::factory()->user->create();
		wp_set_current_user( $user_id );

		// Same direct user-meta approach as the test above - sidesteps
		// ReepayCustomer::set_reepay_handle()'s own outbound API call.
		update_user_meta( $user_id, 'reepay_customer_id', 'cust-' . __FUNCTION__ );

		$plan_handle = 'plan-' . __FUNCTION__ . '-' . uniqid();

		$subscription_list_response = array(
			'content' => array(
				array(
					'handle'            => 'sub-' . uniqid(),
					'plan'              => $plan_handle,
					'state'             => 'active',
					'is_cancelled'      => false,
					'trial_end'         => null,
					'next_period_start' => null,
				),
			),
		);

		$plan_response = array(
			'handle'                  => $plan_handle,
			'name'                    => 'Silver Plan',
			'amount'                  => 2500,
			'currency'                => 'DKK',
			'schedule_type'           => 'daily',
			'interval_length'         => 1,
			'partial_proration_days'  => false,
		);

		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) use ( $subscription_list_response, $plan_response ) {
				$body = ( false !== strpos( $url, '/plan/' ) ) ? $plan_response : $subscription_list_response;

				return array(
					'headers'  => array(),
					'body'     => wp_json_encode( $body ),
					'response' => array( 'code' => 200, 'message' => 'OK' ),
					'cookies'  => array(),
					'filename' => null,
				);
			},
			10,
			3
		);

		ob_start();
		$this->subscriptions_page->subscriptions_endpoint();
		$output = ob_get_clean();

		$this->assertStringNotContainsString( 'no active subscriptions', $output );
		$this->assertStringContainsString( 'Day', $output, 'Billing period from the mocked daily/interval=1 plan should read "Day" (short form).' );
	}
}
