<?php
/**
 * Class PaymentTokenTest
 *
 * Covers the payment-token-related methods of WC_Reepay_Renewals:
 * get_payment_token_order, get_payment_token, create_payment_method,
 * and get_order_by_subscription_handle.
 *
 * @package Reepay\Subscriptions
 */

use Reepay\Subscriptions\Tests\Helpers\Reepay_Subscriptions_UnitTestCase;
use Reepay\Subscriptions\Tests\Mocks\ApiResponseMock;

/**
 * PaymentTokenTest.
 */
class PaymentTokenTest extends Reepay_Subscriptions_UnitTestCase {

	/**
	 * @var WC_Reepay_Renewals
	 */
	private WC_Reepay_Renewals $renewals;

	public function set_up() {
		parent::set_up();

		$this->renewals = new WC_Reepay_Renewals();

		// Same test-only wiring as LifecycleTest::set_up() / OrderSplittingTest::set_up()
		// (see those files for the full rationale): WC_Reepay_Subscription_API
		// (reepay_s()->api()) and the Frisbii Pay Api class (reepay()->api()) both
		// read their key from a settings snapshot cached once at bootstrap, so
		// updating the woocommerce_reepay_checkout_settings option alone has no
		// effect on an already-constructed singleton - inject a fake key via
		// reflection instead.
		update_option(
			'woocommerce_reepay_checkout_settings',
			array(
				'private_key_test' => 'test-key-reepay',
				'test_mode'        => 'yes',
			)
		);

		$this->set_fake_private_key( WC_Reepay_Subscription_API::get_instance(), 'private_key_test', 'test-key-reepay' );
		$this->set_fake_private_key( WC_Reepay_Subscription_API::get_instance(), 'test_mode', true );

		if ( \Reepay\Subscriptions\Tests\Helpers\PLUGINS_STATE::reepay_pay_activated() ) {
			reepay()->reset_settings();
		}

		// Order creation/status-setting can trigger WooCommerce's transactional
		// order emails. Rendering those emails calls
		// WC_Reepay_Woocommerce_Subscription_Extension::clean_order_totals(), which
		// calls the WooCommerce Subscriptions function order_contains_subscription()
		// unconditionally (no function_exists guard) - fatal when woo_subs isn't
		// active. Unhooking WC's email dispatch is test-only wiring (no production
		// file is touched), copied verbatim from LifecycleTest::set_up().
		foreach ( array(
			'woocommerce_order_status_pending_to_processing',
			'woocommerce_order_status_pending_to_completed',
			'woocommerce_order_status_processing_to_cancelled',
			'woocommerce_order_status_pending_to_cancelled',
			'woocommerce_order_status_pending_to_failed',
			'woocommerce_order_status_pending_to_on-hold',
			'woocommerce_order_status_failed_to_processing',
			'woocommerce_order_status_failed_to_completed',
			'woocommerce_order_status_failed_to_on-hold',
			'woocommerce_order_status_cancelled_to_processing',
			'woocommerce_order_status_cancelled_to_completed',
			'woocommerce_order_status_cancelled_to_on-hold',
			'woocommerce_order_status_on-hold_to_processing',
			'woocommerce_order_status_on-hold_to_cancelled',
			'woocommerce_order_status_on-hold_to_failed',
			'woocommerce_order_status_completed',
			'woocommerce_order_status_failed',
		) as $email_action ) {
			remove_action( $email_action, array( 'WC_Emails', 'send_transactional_email' ), 10 );
			remove_action( $email_action, array( 'WC_Emails', 'queue_transactional_email' ), 10 );
		}
	}

	public function tear_down() {
		ApiResponseMock::clear();

		parent::tear_down();
	}

	/**
	 * Set a private/protected property on an already-constructed object via
	 * reflection. Copied verbatim from LifecycleTest/OrderSplittingTest - used
	 * only to inject a fake API key into the plugins' settings singletons, which
	 * cache their values once at bootstrap time, long before any option set in a
	 * test's set_up() would be read.
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
	 * get_payment_token_order()
	 * ------------------------------------------------------------------
	 *
	 * Real behavior (WC_Reepay_Renewals.php:1084-1099): this method does NOT
	 * query the woocommerce_payment_tokens table at all - it is a simple order
	 * meta lookup. It reads order meta 'reepay_token' first (sleeping 2 seconds
	 * if empty before retrying), then '_reepay_token' (sleeping 2 seconds again
	 * before a final retry of 'reepay_token'), returning false if all attempts
	 * are empty. Despite the brief's framing (and the method's own PHPDoc
	 * mentioning "Get payment token"), it is unrelated to WC_Payment_Tokens -
	 * flagged pre-existing technical debt per requirements.md. We avoid
	 * exercising the empty-token branch directly in a fast unit test because it
	 * performs two real sleep(2) calls (4s) - that path is still correct by
	 * inspection, so we test only the meta key lookups that don't trigger sleep.
	 */

	/**
	 * Test @see WC_Reepay_Renewals::get_payment_token_order returns the value of
	 * the 'reepay_token' order meta (first key tried) without needing a real
	 * WC_Payment_Tokens entry at all.
	 */
	public function test_get_payment_token_order_reads_reepay_token_meta() {
		$order = $this->order_generator->add_product( 'simple' )->order();
		$order->update_meta_data( 'reepay_token', 'token-abc-123' );
		$order->save_meta_data();

		$result = WC_Reepay_Renewals::get_payment_token_order( $order );

		$this->assertSame( 'token-abc-123', $result );
	}

	/**
	 * Test @see WC_Reepay_Renewals::get_payment_token_order falls back to the
	 * '_reepay_token' order meta key when 'reepay_token' is empty.
	 */
	public function test_get_payment_token_order_falls_back_to_underscored_meta() {
		$order = $this->order_generator->add_product( 'simple' )->order();
		$order->update_meta_data( '_reepay_token', 'token-underscored-456' );
		$order->save_meta_data();

		$result = WC_Reepay_Renewals::get_payment_token_order( $order );

		$this->assertSame( 'token-underscored-456', $result );
	}

	/**
	 * Test @see WC_Reepay_Renewals::get_payment_token_order returns false (not
	 * null, not empty string) when the order has neither 'reepay_token' nor
	 * '_reepay_token' meta set - i.e. a token that was never associated with any
	 * order. This exercises the full empty path (including both sleep(2) calls),
	 * so it is slower (~4s) by design - confirming the exact "not found" return
	 * value is the point of the test.
	 */
	public function test_get_payment_token_order_returns_false_when_no_token_meta() {
		$order = $this->order_generator->add_product( 'simple' )->order();

		$result = WC_Reepay_Renewals::get_payment_token_order( $order );

		$this->assertFalse( $result );
		$this->assertNotNull( $result );
	}

	/*
	 * ------------------------------------------------------------------
	 * get_payment_token()
	 * ------------------------------------------------------------------
	 *
	 * Real behavior (WC_Reepay_Renewals.php:1108-1118): raw $wpdb query
	 * `SELECT token_id FROM {$wpdb->prefix}woocommerce_payment_tokens WHERE
	 * token = '%s'` (confirmed against the real table/column names via
	 * DESCRIBE wp_woocommerce_payment_tokens: token_id, token). If a token_id is
	 * found it delegates to WC_Payment_Tokens::get( $token_id ), which returns a
	 * real WC_Payment_Token object (or null if the ID is somehow stale). If no
	 * row matches, returns false. No outbound API call is made by this method,
	 * so ApiResponseMock is not needed here.
	 */

	/**
	 * Test @see WC_Reepay_Renewals::get_payment_token finds a payment token that
	 * was saved through WooCommerce's normal token-saving flow
	 * (WC_Payment_Token_CC::save()) and returns the same token string/user/id.
	 */
	public function test_get_payment_token_finds_real_saved_token() {
		$user_id = self::factory()->user->create();

		$token = new WC_Payment_Token_CC();
		$token->set_token( 'tok-real-789' );
		$token->set_gateway_id( 'reepay_checkout' );
		$token->set_user_id( $user_id );
		$token->set_card_type( 'visa' );
		$token->set_last4( '4242' );
		$token->set_expiry_month( '01' );
		$token->set_expiry_year( '2030' );
		$token->save();

		$result = WC_Reepay_Renewals::get_payment_token( 'tok-real-789' );

		$this->assertInstanceOf( 'WC_Payment_Token', $result );
		$this->assertSame( 'tok-real-789', $result->get_token() );
		$this->assertSame( (string) $user_id, (string) $result->get_user_id() );
		$this->assertSame( $token->get_id(), $result->get_id() );
	}

	/**
	 * Test @see WC_Reepay_Renewals::get_payment_token returns false (the exact
	 * "not found" sentinel - not null) for a token string that was never saved.
	 */
	public function test_get_payment_token_returns_false_for_unknown_token() {
		$result = WC_Reepay_Renewals::get_payment_token( 'tok-does-not-exist' );

		$this->assertFalse( $result );
	}

	/*
	 * ------------------------------------------------------------------
	 * create_payment_method()
	 * ------------------------------------------------------------------
	 *
	 * Real behavior (WC_Reepay_Renewals.php:713-736): POSTs to
	 * subscription/{$handle}/pm with body {handle, source: $token} via
	 * reepay_s()->api()->request(). On success returns the decoded API response
	 * (array, per ApiResponseMock) unmodified. On any Exception, logs and
	 * returns null (the initial value of $payment_method, never reassigned on
	 * the catch path).
	 */

	/**
	 * Test @see WC_Reepay_Renewals::create_payment_method returns the API
	 * response array unmodified (including the handle) on success.
	 */
	public function test_create_payment_method_returns_api_response_on_success() {
		ApiResponseMock::respond_with(
			array(
				'handle'  => 'sub-handle-1',
				'source'  => 'test-token-123',
				'id'      => 'pm-1',
				'created' => '2026-01-01T00:00:00.000Z',
			)
		);

		$result = $this->renewals->create_payment_method( 'sub-handle-1', 'test-token-123' );

		$this->assertIsArray( $result );
		$this->assertSame( 'sub-handle-1', $result['handle'] );
		$this->assertSame( 'test-token-123', $result['source'] );
		$this->assertSame( 'pm-1', $result['id'] );
	}

	/**
	 * Test @see WC_Reepay_Renewals::create_payment_method returns null (not
	 * false, not WP_Error) when the underlying API call throws (e.g. a 4xx/5xx
	 * from Frisbii, surfaced by WC_Reepay_Subscription_API as an Exception).
	 */
	public function test_create_payment_method_returns_null_on_api_error() {
		ApiResponseMock::respond_with( array( 'error' => 'invalid_request', 'message' => 'Bad token' ), 400 );

		$result = $this->renewals->create_payment_method( 'sub-handle-err', 'bad-token' );

		$this->assertNull( $result );
	}

	/*
	 * ------------------------------------------------------------------
	 * get_order_by_subscription_handle()
	 * ------------------------------------------------------------------
	 *
	 * Real behavior (WC_Reepay_Renewals.php:1125-1145): first looks up a
	 * wc_get_orders() query for meta_key '_reepay_order' === $handle (limit 1);
	 * if none found, falls back to meta_key '_reepay_subscription_handle' ===
	 * $handle. Returns false if neither query matches, otherwise the first (only)
	 * matching WC_Order. This is genuinely different from the global helper
	 * rp_get_order_by_subscription_handle() (defined in includes/Functions/ in
	 * the main Frisbii Pay plugin, not in this plugin) - this plugin does not
	 * call that global helper; WC_Reepay_Renewals::get_order_by_subscription_handle()
	 * is self-contained and only consults the two meta keys above.
	 */

	/**
	 * Test @see WC_Reepay_Renewals::get_order_by_subscription_handle finds the
	 * order via the '_reepay_subscription_handle' meta key (the renewal /
	 * webhook-facing identifier set by create_subscriptions()).
	 */
	public function test_get_order_by_subscription_handle_finds_via_subscription_handle_meta() {
		$order = $this->order_generator->add_product( 'simple' )->order();
		$order->update_meta_data( '_reepay_subscription_handle', 'sub-handle-find-1' );
		$order->save_meta_data();

		$result = WC_Reepay_Renewals::get_order_by_subscription_handle( 'sub-handle-find-1' );

		$this->assertInstanceOf( 'WC_Order', $result );
		$this->assertSame( $order->get_id(), $result->get_id() );
	}

	/**
	 * Test @see WC_Reepay_Renewals::get_order_by_subscription_handle prefers the
	 * '_reepay_order' meta key over '_reepay_subscription_handle' when both exist
	 * (on different orders) with the same handle value - confirms the first
	 * wc_get_orders() query, not the fallback, is what actually matched.
	 */
	public function test_get_order_by_subscription_handle_prefers_reepay_order_meta() {
		$order_via_invoice = $this->order_generator->add_product( 'simple' )->order();
		$order_via_invoice->update_meta_data( '_reepay_order', 'shared-handle-1' );
		$order_via_invoice->save_meta_data();

		$order_via_sub_handle = $this->order_generator->add_product( 'simple' )->order();
		$order_via_sub_handle->update_meta_data( '_reepay_subscription_handle', 'shared-handle-1' );
		$order_via_sub_handle->save_meta_data();

		$result = WC_Reepay_Renewals::get_order_by_subscription_handle( 'shared-handle-1' );

		$this->assertInstanceOf( 'WC_Order', $result );
		$this->assertSame( $order_via_invoice->get_id(), $result->get_id() );
	}

	/**
	 * Test @see WC_Reepay_Renewals::get_order_by_subscription_handle returns
	 * false (the exact "not found" sentinel) for a handle that is tagged on no
	 * order at all.
	 */
	public function test_get_order_by_subscription_handle_returns_false_for_unknown_handle() {
		$result = WC_Reepay_Renewals::get_order_by_subscription_handle( 'handle-does-not-exist-anywhere' );

		$this->assertFalse( $result );
	}
}
