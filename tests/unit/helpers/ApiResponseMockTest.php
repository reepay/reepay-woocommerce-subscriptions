<?php
/**
 * Class ApiResponseMockTest
 *
 * @package Reepay\Subscriptions
 */

use Reepay\Subscriptions\Tests\Mocks\ApiResponseMock;

/**
 * ApiResponseMockTest.
 */
class ApiResponseMockTest extends WP_UnitTestCase {

	/**
	 * Tear down: always clear the mock so it doesn't leak into other tests.
	 */
	public function tear_down() {
		ApiResponseMock::clear();

		parent::tear_down();
	}

	/**
	 * Test @see ApiResponseMock::respond_with intercepts the next wp_remote_request call.
	 */
	public function test_respond_with_intercepts_next_request() {
		ApiResponseMock::respond_with( array( 'id' => 'sub-123', 'state' => 'active' ), 200 );

		$response = wp_remote_request( 'https://api.reepay.com/v1/subscription/sub-123' );
		$body     = json_decode( wp_remote_retrieve_body( $response ), true );

		$this->assertSame( 200, wp_remote_retrieve_response_code( $response ) );
		$this->assertSame( 'sub-123', $body['id'] );
	}
}
