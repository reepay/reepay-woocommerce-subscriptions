<?php
/**
 * Class PaymentMethodTest
 *
 * Covers WC_Reepay_My_Account_Payment_Method
 * (includes/my-account/WC_Reepay_My_Account_Payment_Method.php):
 * add_subscription_arg(). Uses the Ajax testcase base since the method under
 * test reads $_GET directly.
 *
 * EXIT HAZARD - INTENTIONALLY SKIPPED: payment_method_added(WC_Payment_Token $token)
 * has NO safe branch at all. Confirmed by reading the full method body: both
 * the empty-handle and non-empty-handle paths fall through to the same single
 * wp_redirect() + exit; at the bottom of the method - including both the try
 * and catch branches of its internal API call when the handle IS present.
 * There is no early return anywhere in this method. Calling it directly in a
 * test would kill the entire PHPUnit process. It is NOT reachable via
 * ReflectionMethod either, since the exit is in the body of this exact public
 * method, not factored into a private sub-method. This method is therefore
 * entirely untested in this suite without @runInSeparateProcess, which this
 * task's brief says not to rely on without confirming it round-trips cleanly
 * in this environment first - not attempted, per the brief's guidance to skip
 * and document rather than risk killing the suite.
 *
 * Phase 5 scope: baseline regression coverage per requirements.md §3.4, not
 * exhaustive scenario enumeration.
 *
 * Confirmed real behavior (read from source):
 * - add_subscription_arg($url) reads $_GET['reepay_subscription']; if
 *   non-empty, appends it as a 'reepay_subscription' query arg (sanitized) to
 *   the given $url via add_query_arg() + sanitize_url(). Otherwise returns
 *   $url unchanged. Pure given the superglobal, no API/exit involvement.
 *
 * @package Reepay\Subscriptions
 */

use Reepay\Subscriptions\Tests\Helpers\Reepay_Subscriptions_Ajax_UnitTestCase;

/**
 * PaymentMethodTest.
 */
class PaymentMethodTest extends Reepay_Subscriptions_Ajax_UnitTestCase {

	/**
	 * @var WC_Reepay_My_Account_Payment_Method
	 */
	private WC_Reepay_My_Account_Payment_Method $payment_method;

	public function set_up() {
		parent::set_up();

		$this->payment_method = new WC_Reepay_My_Account_Payment_Method();
	}

	/*
	 * ------------------------------------------------------------------
	 * add_subscription_arg()
	 * ------------------------------------------------------------------
	 */

	/**
	 * Test @see WC_Reepay_My_Account_Payment_Method::add_subscription_arg
	 * appends the sanitized 'reepay_subscription' query arg when
	 * $_GET['reepay_subscription'] is present.
	 */
	public function test_add_subscription_arg_appends_when_get_param_present() {
		$_GET['reepay_subscription'] = 'sub-handle-123';

		$result = $this->payment_method->add_subscription_arg( 'https://example.com/account/' );

		$this->assertStringContainsString( 'reepay_subscription=sub-handle-123', $result );
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
}
