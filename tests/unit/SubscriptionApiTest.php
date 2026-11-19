<?php
/**
 * Class SubscriptionApiTest
 *
 * Covers WC_Reepay_Subscription_API::request() (includes/WC_Reepay_Subscription_API.php:68-161).
 *
 * Confirmed real behavior: request() is a non-static instance method on the
 * WC_Reepay_Subscription_API::get_instance() singleton. It does NOT return a
 * WP_Error on failure - every non-2xx/3xx path (including network-level
 * failures and any HTTP 4xx/5xx response) throws a PHP Exception (see the
 * `switch ($code)` block, lines 115-160: cases 2 and 3 are the only ones that
 * `return json_decode($body, true)`; every other case throws).
 *
 * @package Reepay\Subscriptions
 */

use Reepay\Subscriptions\Tests\Helpers\Reepay_Subscriptions_UnitTestCase;
use Reepay\Subscriptions\Tests\Mocks\ApiResponseMock;

/**
 * SubscriptionApiTest.
 */
class SubscriptionApiTest extends Reepay_Subscriptions_UnitTestCase {

	public function set_up() {
		parent::set_up();

		// request() throws immediately (before ever calling wp_remote_request(),
		// which is what ApiResponseMock intercepts via the pre_http_request
		// filter) when no private key is configured (lines 76-86). The
		// singleton reads its key from a settings snapshot cached once at
		// plugin bootstrap, long before any option set here would be read, so
		// updating the option alone has no effect on the already-constructed
		// instance - inject a fake key directly via reflection instead.
		// Pattern copied verbatim from tests/unit/renewals/LifecycleTest.php
		// and tests/unit/plans/PlanSimpleTest.php. Test-only wiring, no
		// production file touched.
		update_option(
			'woocommerce_reepay_checkout_settings',
			array(
				'private_key_test' => 'test-key-reepay',
				'test_mode'        => 'yes',
			)
		);

		$this->set_fake_private_key( WC_Reepay_Subscription_API::get_instance(), 'private_key_test', 'test-key-reepay' );
		$this->set_fake_private_key( WC_Reepay_Subscription_API::get_instance(), 'test_mode', true );
	}

	public function tear_down() {
		ApiResponseMock::clear();

		parent::tear_down();
	}

	/**
	 * Set a private/protected property on an already-constructed object via
	 * reflection. Copied verbatim from tests/unit/renewals/LifecycleTest.php.
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
	 * Test @see WC_Reepay_Subscription_API::request correctly parses a 200 JSON
	 * response and returns the decoded array (code 2 branch - `return
	 * json_decode($body, true)`).
	 */
	public function test_request_returns_decoded_array_for_200_response() {
		ApiResponseMock::respond_with(
			array(
				'handle' => 'sub-handle-200',
				'state'  => 'active',
				'amount' => 1000,
			),
			200
		);

		$result = WC_Reepay_Subscription_API::get_instance()->request( 'subscription/sub-handle-200' );

		$this->assertIsArray( $result );
		$this->assertSame( 'sub-handle-200', $result['handle'] );
		$this->assertSame( 'active', $result['state'] );
		$this->assertSame( 1000, $result['amount'] );
	}

	/**
	 * Test @see WC_Reepay_Subscription_API::request also correctly parses a
	 * 3xx-class response (code 3 branch shares the same `return
	 * json_decode($body, true)` as code 2).
	 */
	public function test_request_returns_decoded_array_for_3xx_response() {
		ApiResponseMock::respond_with(
			array( 'handle' => 'sub-handle-300' ),
			300
		);

		$result = WC_Reepay_Subscription_API::get_instance()->request( 'subscription/sub-handle-300' );

		$this->assertIsArray( $result );
		$this->assertSame( 'sub-handle-300', $result['handle'] );
	}

	/**
	 * Test @see WC_Reepay_Subscription_API::request throws a PHP Exception
	 * (NOT a WP_Error) for a 400 response - confirms the exception is
	 * actually thrown via expectException(), not silently swallowed or
	 * returned as some other sentinel value.
	 */
	public function test_request_throws_exception_for_400_response() {
		ApiResponseMock::respond_with(
			array(
				'error'   => 'invalid_request',
				'message' => 'Something was wrong with the request',
			),
			400
		);

		$this->expectException( Exception::class );

		WC_Reepay_Subscription_API::get_instance()->request( 'subscription/does-not-matter' );
	}

	/**
	 * Test @see WC_Reepay_Subscription_API::request throws a PHP Exception
	 * for a 500 response too (code 5 branch, same switch arm as 4xx).
	 */
	public function test_request_throws_exception_for_500_response() {
		ApiResponseMock::respond_with(
			array(
				'error'   => 'server_error',
				'message' => 'Internal error',
			),
			500
		);

		$this->expectException( Exception::class );

		WC_Reepay_Subscription_API::get_instance()->request( 'subscription/does-not-matter' );
	}

	/**
	 * Test @see WC_Reepay_Subscription_API::request includes the API's error
	 * (and message) text in the thrown Exception's message for a non-2xx
	 * response, confirming the exception carries real diagnostic content
	 * rather than being a generic/empty throw.
	 */
	public function test_request_exception_message_contains_api_error_details() {
		ApiResponseMock::respond_with(
			array(
				'error'   => 'invalid_request',
				'message' => 'Something was wrong with the request',
			),
			400
		);

		try {
			WC_Reepay_Subscription_API::get_instance()->request( 'subscription/does-not-matter' );
			$this->fail( 'Expected an Exception to be thrown for a 400 response.' );
		} catch ( Exception $e ) {
			$this->assertStringContainsString( 'invalid_request', $e->getMessage() );
			$this->assertStringContainsString( 'Something was wrong with the request', $e->getMessage() );
		}
	}

	/**
	 * Test @see WC_Reepay_Subscription_API::request throws immediately (before
	 * any HTTP call) when no API key is configured at all - the guard at
	 * lines 78-86. Uses a second, separate instance-like state: since
	 * get_instance() is a true singleton we temporarily blank out the
	 * reflected key fields rather than constructing a second instance.
	 */
	public function test_request_throws_exception_when_no_api_key_configured() {
		$instance = WC_Reepay_Subscription_API::get_instance();

		// Blank out both possible key fields and force test_mode so the
		// "$key = $this->test_mode ? $this->private_key_test : $this->private_key"
		// line resolves to an empty string either way.
		$this->set_fake_private_key( $instance, 'private_key_test', '' );
		$this->set_fake_private_key( $instance, 'private_key', '' );
		$this->set_fake_private_key( $instance, 'test_mode', true );

		// Deliberately do NOT call ApiResponseMock::respond_with() - if the
		// guard did not short-circuit, this test would attempt a real outbound
		// HTTP request instead of throwing, which would fail/hang instead of
		// asserting the exception.
		$this->expectException( Exception::class );

		$instance->request( 'subscription/does-not-matter' );
	}
}
