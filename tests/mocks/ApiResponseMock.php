<?php
/**
 * Class ApiResponseMock
 *
 * @package Reepay\Subscriptions
 */

namespace Reepay\Subscriptions\Tests\Mocks;

/**
 * Class ApiResponseMock
 *
 * Registers a `pre_http_request` filter so tests can control what
 * WC_Reepay_Subscription_API::request() (and reepay_s()->api()->request())
 * receive back, without touching either class. The registered response is
 * NOT one-shot: it serves every subsequent HTTP call until a following
 * `respond_with()` call replaces it or `clear()` removes it (e.g. in
 * `tear_down()`). If a test's code path makes multiple outbound calls and
 * each needs a different response, call `respond_with()` again between them.
 */
abstract class ApiResponseMock {
	/**
	 * @var callable|null
	 */
	private static $callback = null;

	/**
	 * Register the canned response served to every HTTP call until the next
	 * `respond_with()` call or `clear()` (see class docblock — not one-shot).
	 *
	 * @param array $body response body, JSON-encoded automatically.
	 * @param int   $status HTTP status code.
	 */
	public static function respond_with( array $body, int $status = 200 ): void {
		self::clear();

		self::$callback = function () use ( $body, $status ) {
			return array(
				'headers'  => array(),
				'body'     => wp_json_encode( $body ),
				'response' => array(
					'code'    => $status,
					'message' => get_status_header_desc( $status ),
				),
				'cookies'  => array(),
				'filename' => null,
			);
		};

		add_filter( 'pre_http_request', self::$callback, 10, 3 );
	}

	/**
	 * Remove any registered mock response.
	 */
	public static function clear(): void {
		if ( null !== self::$callback ) {
			remove_filter( 'pre_http_request', self::$callback, 10 );
			self::$callback = null;
		}
	}
}
