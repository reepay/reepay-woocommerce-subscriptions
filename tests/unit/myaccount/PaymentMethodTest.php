<?php
/**
 * Class PaymentMethodTest
 *
 * Covers WC_Reepay_My_Account_Payment_Method
 * (includes/my-account/WC_Reepay_My_Account_Payment_Method.php):
 * add_subscription_arg(). Uses the Ajax testcase base since the method under
 * test reads $_GET directly.
 *
 * EXIT HAZARD - payment_method_added(WC_Payment_Token $token) returns early
 * only when $_GET['reepay_subscription'] is empty. Every other path (try and
 * catch branches alike) falls through to wp_redirect() + exit; at the bottom
 * of the method. Those paths are tested by hooking the 'wp_redirect' filter
 * (applied inside wp_redirect(), before the exit) to throw
 * PaymentMethodTest_Redirect, which aborts the method before it can exit.
 * The method's own try/catch does not wrap the redirect, so the exception
 * reaches the test.
 *
 * Phase 5 scope: baseline regression coverage per requirements.md §3.4, not
 * exhaustive scenario enumeration.
 *
 * Confirmed real behavior (read from source):
 * - add_subscription_arg($url) reads $_GET['reepay_subscription'] and returns
 *   $url unchanged unless the handle matches HANDLE_PATTERN AND
 *   $_GET['_wpnonce'] is a valid nonce for 'reepay_add_payment_method_{handle}'.
 *   When both hold, it appends 'reepay_subscription' and a fresh
 *   'reepay_subscription_nonce' (same action) via add_query_arg(). Pure given
 *   the superglobals, no API/exit involvement.
 *
 * @package Reepay\Subscriptions
 */

use Reepay\Subscriptions\Tests\Helpers\PLUGINS_STATE;
use Reepay\Subscriptions\Tests\Helpers\Reepay_Subscriptions_Ajax_UnitTestCase;
use Reepay\Subscriptions\Tests\Mocks\ApiResponseMock;

/**
 * Thrown from the 'wp_redirect' filter so payment_method_added() never
 * reaches its exit.
 */
class PaymentMethodTest_Redirect extends Exception {
}

/**
 * PaymentMethodTest.
 */
class PaymentMethodTest extends Reepay_Subscriptions_Ajax_UnitTestCase {

	/**
	 * @var WC_Reepay_My_Account_Payment_Method
	 */
	private WC_Reepay_My_Account_Payment_Method $payment_method;

	/**
	 * Outbound requests seen during a test: [ method, url, decoded body ].
	 *
	 * @var array
	 */
	private array $requests = array();

	/**
	 * @var callable|null
	 */
	private $request_recorder = null;

	public function set_up() {
		parent::set_up();

		// Same API-key wiring as renewals/LifecycleTest.php: the API client
		// bails out before wp_remote_request() without a key, and caches its
		// key at bootstrap. Test-only wiring.
		update_option(
			'woocommerce_reepay_checkout_settings',
			array(
				'private_key_test' => 'test-key-reepay',
				'test_mode'        => 'yes',
			)
		);
		$api = WC_Reepay_Subscription_API::get_instance();
		foreach ( array( 'private_key_test' => 'test-key-reepay', 'test_mode' => true ) as $property => $value ) {
			$ref = new ReflectionProperty( $api, $property );
			$ref->setAccessible( true );
			$ref->setValue( $api, $value );
		}

		// Only Frisbii subscription endpoints are recorded: in the full suite
		// the Frisbii Pay plugin also syncs saved cards (list/payment_method)
		// when a token is created, which is unrelated to the code under test.
		$this->requests         = array();
		$this->request_recorder = function ( $preempt, $args, $url ) {
			if ( false !== strpos( $url, '/subscription/' ) ) {
				$this->requests[] = array( $args['method'] ?? 'GET', $url, json_decode( $args['body'] ?? '', true ) );
			}

			return $preempt;
		};
		add_filter( 'pre_http_request', $this->request_recorder, 5, 3 );

		wc_clear_notices();

		$this->payment_method = new WC_Reepay_My_Account_Payment_Method();
	}

	public function tear_down() {
		remove_filter( 'pre_http_request', $this->request_recorder, 5 );
		remove_all_filters( 'wp_redirect' );
		ApiResponseMock::clear();
		wc_clear_notices();
		unset( $_GET['reepay_subscription'], $_GET['_wpnonce'], $_GET['reepay_subscription_nonce'] );

		parent::tear_down();
	}

	/*
	 * ------------------------------------------------------------------
	 * add_subscription_arg()
	 * ------------------------------------------------------------------
	 */

	/**
	 * Test @see WC_Reepay_My_Account_Payment_Method::add_subscription_arg
	 * appends the 'reepay_subscription' query arg plus a
	 * 'reepay_subscription_nonce' when $_GET['reepay_subscription'] is a
	 * valid handle and $_GET['_wpnonce'] is valid for that handle.
	 */
	public function test_add_subscription_arg_appends_when_get_param_present() {
		$_GET['reepay_subscription'] = 'sub-handle-123';
		$_GET['_wpnonce']            = wp_create_nonce( 'reepay_add_payment_method_sub-handle-123' );

		$result = $this->payment_method->add_subscription_arg( 'https://example.com/account/' );

		$this->assertStringContainsString( 'reepay_subscription=sub-handle-123', $result );

		parse_str( (string) wp_parse_url( $result, PHP_URL_QUERY ), $query );
		$this->assertArrayHasKey( 'reepay_subscription_nonce', $query );
		$this->assertSame( 1, wp_verify_nonce( $query['reepay_subscription_nonce'], 'reepay_add_payment_method_sub-handle-123' ) );

		unset( $_GET['reepay_subscription'], $_GET['_wpnonce'] );
	}

	/**
	 * Test @see WC_Reepay_My_Account_Payment_Method::add_subscription_arg
	 * returns the URL unchanged when $_GET['reepay_subscription'] is absent.
	 */
	public function test_add_subscription_arg_unchanged_without_get_param() {
		unset( $_GET['reepay_subscription'] );

		$url    = 'https://example.com/account/';
		$result = $this->payment_method->add_subscription_arg( $url );

		$this->assertSame( $url, $result );
	}

	/**
	 * Test @see WC_Reepay_My_Account_Payment_Method::add_subscription_arg
	 * returns the URL unchanged when $_GET['reepay_subscription'] is present
	 * but empty - !empty() guard treats '' the same as absent.
	 */
	public function test_add_subscription_arg_unchanged_when_get_param_empty() {
		$_GET['reepay_subscription'] = '';

		$url    = 'https://example.com/account/';
		$result = $this->payment_method->add_subscription_arg( $url );

		$this->assertSame( $url, $result );
	}

	/**
	 * Test @see WC_Reepay_My_Account_Payment_Method::add_subscription_arg
	 * accepts every character Frisbii allows in a handle (letters, digits,
	 * '_', '-', '.', '@').
	 */
	public function test_add_subscription_arg_accepts_all_allowed_handle_characters() {
		$handle                      = 'Sub_1-a.b@c';
		$_GET['reepay_subscription'] = $handle;
		$_GET['_wpnonce']            = wp_create_nonce( 'reepay_add_payment_method_' . $handle );

		$result = $this->payment_method->add_subscription_arg( 'https://example.com/account/' );

		parse_str( (string) wp_parse_url( $result, PHP_URL_QUERY ), $query );
		$this->assertSame( $handle, $query['reepay_subscription'] ?? null );
	}

	/**
	 * Test @see WC_Reepay_My_Account_Payment_Method::add_subscription_arg
	 * refuses handles that do not match HANDLE_PATTERN - even with a valid
	 * nonce for that exact handle.
	 */
	public function test_add_subscription_arg_rejects_invalid_handles() {
		$url = 'https://example.com/account/';

		foreach ( array( '.', '..', 'sub/../x', 'sub handle', 'sub#1', str_repeat( 'a', 256 ) ) as $handle ) {
			$_GET['reepay_subscription'] = $handle;
			$_GET['_wpnonce']            = wp_create_nonce( 'reepay_add_payment_method_' . $handle );

			$this->assertSame( $url, $this->payment_method->add_subscription_arg( $url ), "Handle: $handle" );
		}
	}

	/**
	 * Test @see WC_Reepay_My_Account_Payment_Method::add_subscription_arg
	 * accepts a handle of exactly 255 characters (the maximum).
	 */
	public function test_add_subscription_arg_accepts_255_character_handle() {
		$handle                      = str_repeat( 'a', 255 );
		$_GET['reepay_subscription'] = $handle;
		$_GET['_wpnonce']            = wp_create_nonce( 'reepay_add_payment_method_' . $handle );

		$result = $this->payment_method->add_subscription_arg( 'https://example.com/account/' );

		$this->assertStringContainsString( 'reepay_subscription=' . $handle, $result );
	}

	/**
	 * Test @see WC_Reepay_My_Account_Payment_Method::add_subscription_arg
	 * returns the URL unchanged without a nonce, with a garbage nonce, or with
	 * a nonce created for a different handle.
	 */
	public function test_add_subscription_arg_unchanged_without_valid_nonce() {
		$url                         = 'https://example.com/account/';
		$_GET['reepay_subscription'] = 'sub-nonce-1';

		unset( $_GET['_wpnonce'] );
		$this->assertSame( $url, $this->payment_method->add_subscription_arg( $url ), 'No nonce' );

		$_GET['_wpnonce'] = 'not-a-nonce';
		$this->assertSame( $url, $this->payment_method->add_subscription_arg( $url ), 'Garbage nonce' );

		$_GET['_wpnonce'] = wp_create_nonce( 'reepay_add_payment_method_sub-other' );
		$this->assertSame( $url, $this->payment_method->add_subscription_arg( $url ), 'Nonce for another handle' );
	}

	/*
	 * ------------------------------------------------------------------
	 * payment_method_added()
	 * ------------------------------------------------------------------
	 */

	/**
	 * A saved card token.
	 *
	 * @return WC_Payment_Token_CC
	 */
	private function create_token(): WC_Payment_Token_CC {
		$token = new WC_Payment_Token_CC();
		$token->set_token( 'ca_test_token_1' );
		$token->set_gateway_id( 'reepay_checkout' );
		$token->set_card_type( 'visa' );
		$token->set_last4( '4242' );
		$token->set_expiry_month( '12' );
		$token->set_expiry_year( '2030' );
		$token->set_user_id( get_current_user_id() );
		$token->save();

		return $token;
	}

	/**
	 * Log in a customer linked to $customer_handle and set up the GET args
	 * for $subscription_handle (with a valid nonce unless told otherwise).
	 *
	 * @param string      $customer_handle Frisbii customer handle of the user.
	 * @param string      $subscription_handle subscription handle in the URL.
	 * @param string|null $nonce nonce to use; null = a valid one.
	 */
	private function prepare_request( string $customer_handle, string $subscription_handle, ?string $nonce = null ): void {
		$user_id = self::factory()->user->create( array( 'role' => 'customer' ) );
		update_user_meta( $user_id, 'reepay_customer_id', $customer_handle );
		wp_set_current_user( $user_id );

		$_GET['reepay_subscription']       = $subscription_handle;
		$_GET['reepay_subscription_nonce'] = $nonce ?? wp_create_nonce( 'reepay_add_payment_method_' . $subscription_handle );
	}

	/**
	 * Run payment_method_added(), returning the redirect location.
	 *
	 * @param WC_Payment_Token $token token.
	 *
	 * @return string|null redirect location, or null if the method returned.
	 */
	private function run_payment_method_added( WC_Payment_Token $token ): ?string {
		add_filter(
			'wp_redirect',
			function ( $location ) {
				throw new PaymentMethodTest_Redirect( $location );
			}
		);

		try {
			$this->payment_method->payment_method_added( $token );
		} catch ( PaymentMethodTest_Redirect $redirect ) {
			return $redirect->getMessage();
		}

		return null;
	}

	/**
	 * Test @see WC_Reepay_My_Account_Payment_Method::payment_method_added
	 * returns without a redirect or API call when no subscription handle is
	 * in the URL (the normal "add payment method" flow).
	 */
	public function test_payment_method_added_noop_without_handle() {
		wp_set_current_user( self::factory()->user->create() );
		unset( $_GET['reepay_subscription'] );

		$this->assertNull( $this->run_payment_method_added( $this->create_token() ) );
		$this->assertSame( array(), $this->requests );
	}

	/**
	 * Test @see WC_Reepay_My_Account_Payment_Method::payment_method_added
	 * refuses an invalid nonce: error notice, redirect, no API call.
	 */
	public function test_payment_method_added_rejects_invalid_nonce() {
		$this->prepare_request( 'cust-pm-1', 'sub-pm-1', 'not-a-nonce' );

		$redirect = $this->run_payment_method_added( $this->create_token() );

		$this->assertNotNull( $redirect );
		$this->assertSame( array(), $this->requests );
		$this->assertCount( 1, wc_get_notices( 'error' ) );
		$this->assertEmpty( wc_get_notices( 'success' ) );
	}

	/**
	 * Test @see WC_Reepay_My_Account_Payment_Method::payment_method_added
	 * refuses a handle that does not match HANDLE_PATTERN, even with a nonce
	 * for that handle.
	 */
	public function test_payment_method_added_rejects_invalid_handle() {
		$this->prepare_request( 'cust-pm-2', '..' );

		$this->assertNotNull( $this->run_payment_method_added( $this->create_token() ) );
		$this->assertSame( array(), $this->requests );
		$this->assertCount( 1, wc_get_notices( 'error' ) );
	}

	/**
	 * Test @see WC_Reepay_My_Account_Payment_Method::payment_method_added
	 * refuses a subscription that belongs to another Frisbii customer: only
	 * the ownership lookup is made, the payment method is NOT attached.
	 */
	public function test_payment_method_added_rejects_subscription_of_other_customer() {
		if ( ! PLUGINS_STATE::reepay_pay_activated() ) {
			$this->markTestSkipped( 'rp_get_customer_handle() requires the Frisbii Pay (reepay_pay) plugin.' );
		}

		$this->prepare_request( 'cust-pm-mine', 'sub-pm-3' );
		ApiResponseMock::respond_with( array( 'handle' => 'sub-pm-3', 'customer' => 'cust-pm-someone-else' ) );

		$this->assertNotNull( $this->run_payment_method_added( $this->create_token() ) );

		$this->assertCount( 1, $this->requests );
		$this->assertSame( 'GET', $this->requests[0][0] );
		$this->assertCount( 1, wc_get_notices( 'error' ) );
		$this->assertEmpty( wc_get_notices( 'success' ) );
	}

	/**
	 * Test @see WC_Reepay_My_Account_Payment_Method::payment_method_added
	 * attaches the token to the customer's own subscription (handle
	 * URL-encoded in the request path) and adds a success notice.
	 */
	public function test_payment_method_added_attaches_token_to_own_subscription() {
		if ( ! PLUGINS_STATE::reepay_pay_activated() ) {
			$this->markTestSkipped( 'rp_get_customer_handle() requires the Frisbii Pay (reepay_pay) plugin.' );
		}

		$this->prepare_request( 'cust-pm-owner', 'sub-pm@x' );
		ApiResponseMock::respond_with( array( 'handle' => 'sub-pm@x', 'customer' => 'cust-pm-owner' ) );

		$this->assertNotNull( $this->run_payment_method_added( $this->create_token() ) );

		$this->assertCount( 2, $this->requests );
		$this->assertStringEndsWith( 'subscription/sub-pm%40x', $this->requests[0][1] );
		[ $method, $url, $body ] = $this->requests[1];
		$this->assertSame( 'POST', $method );
		$this->assertStringEndsWith( 'subscription/sub-pm%40x/pm', $url );
		$this->assertSame( 'ca_test_token_1', $body['source'] );
		$this->assertCount( 1, wc_get_notices( 'success' ) );
		$this->assertEmpty( wc_get_notices( 'error' ) );
	}
}
