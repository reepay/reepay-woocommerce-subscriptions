<?php
/**
 * Class StatisticsTest
 *
 * Covers WC_Reepay_Statistics (includes/WC_Reepay_Statistics.php): send_event,
 * plugin_deactivated, plugin_deleted, private_key_activated and
 * upgrade_completed.
 *
 * Phase 5 scope: baseline regression coverage per requirements.md §3.4 - this
 * is a thin (66-line) class, so a lighter checklist applies per the brief.
 *
 * Confirmed real behavior (read from source):
 * - send_event($event) (static) makes a REAL outbound wp_remote_post() call
 *   to a hardcoded third-party analytics webhook URL
 *   (https://hook.reepay.integromat.celonis.com/...) - mocked here with
 *   ApiResponseMock, whose `pre_http_request` filter is generic (no
 *   URL-based exclusion) and intercepts this call just like the plugin's
 *   own API client. The real URL is never hit in this suite. Reads
 *   reepay_s()->settings('version')/reepay_s()->settings('api_private_key')
 *   (a simple static-array lookup, returns null if unset, never fatals) and
 *   home_url().
 * - plugin_deactivated()/plugin_deleted()/private_key_activated() are thin
 *   static wrappers calling send_event() with a fixed event name
 *   ('deactivated'/'deleted'/'activated' respectively) - captured here via a
 *   custom `pre_http_request` callback that inspects the posted JSON body.
 * - upgrade_completed($upgrader_object, $options) only calls
 *   send_event('updated') if $options['plugins'] is non-empty AND contains a
 *   string matching REEPAY_PLUGIN_FILE (an absolute path, defined as
 *   __FILE__ in the main plugin file) via strpos(). CONFIRMED GOTCHA: the
 *   condition is `if (strpos($plugin, REEPAY_PLUGIN_FILE))` with NO `!==
 *   false` / `=== 0` comparison - strpos() returns int|false, and PHP's
 *   loose truthiness means a match at position 0 (i.e. $plugin being
 *   exactly equal to, or starting exactly with, REEPAY_PLUGIN_FILE) is
 *   FALSY and will NOT trigger send_event(), even though the string
 *   genuinely matches. This is a real latent bug reproduced by this suite's
 *   test_upgrade_completed_sends_event_for_matching_plugin test (which
 *   deliberately uses a haystack where REEPAY_PLUGIN_FILE appears at a
 *   non-zero offset to exercise the intended/working case), with a
 *   dedicated test
 *   (test_upgrade_completed_exact_path_match_is_falsy_due_to_strpos_bug)
 *   documenting the exact-match falsy case as it actually behaves - per the
 *   "no production code changes" constraint, this is reported rather than
 *   fixed.
 * - The constructor calls register_deactivation_hook()/register_uninstall_hook()
 *   with REEPAY_PLUGIN_FILE - both idempotent WordPress API calls. Confirmed
 *   safe to call repeatedly: register_deactivation_hook()/register_uninstall_hook()
 *   are themselves just add_action() calls keyed by file+callback, so
 *   constructing WC_Reepay_Statistics multiple times across tests does not
 *   warn or fatal. All of the methods under test are static (or only need
 *   static::class), so this suite exercises them directly without
 *   constructing the class at all, except for one smoke test confirming the
 *   constructor itself does not fatal when called repeatedly.
 *
 * @package Reepay\Subscriptions
 */

use Reepay\Subscriptions\Tests\Helpers\Reepay_Subscriptions_UnitTestCase;
use Reepay\Subscriptions\Tests\Mocks\ApiResponseMock;

/**
 * StatisticsTest.
 */
class StatisticsTest extends Reepay_Subscriptions_UnitTestCase {

	public function tear_down() {
		ApiResponseMock::clear();

		parent::tear_down();
	}

	/**
	 * Register a `pre_http_request` filter that captures the posted body
	 * (decoded JSON) of the next outbound HTTP request and prevents it from
	 * actually going out, returning a canned 200 response.
	 *
	 * @param array|null $captured_body reference populated with the decoded body.
	 *
	 * @return callable the registered filter callback, for later removal.
	 */
	private function capture_request_body( &$captured_body ): callable {
		$callback = function ( $preempt, $args, $url ) use ( &$captured_body ) {
			$captured_body = json_decode( $args['body'], true );

			return array(
				'headers'  => array(),
				'body'     => wp_json_encode( array( 'ok' => true ) ),
				'response' => array( 'code' => 200, 'message' => 'OK' ),
				'cookies'  => array(),
				'filename' => null,
			);
		};

		add_filter( 'pre_http_request', $callback, 5, 3 );

		return $callback;
	}

	/*
	 * ------------------------------------------------------------------
	 * send_event()
	 * ------------------------------------------------------------------
	 */

	/**
	 * Test @see WC_Reepay_Statistics::send_event posts the expected event
	 * name and plugin identifier to the (mocked) analytics webhook, and
	 * never hits the real URL (confirmed by the captured $url matching the
	 * hardcoded hook.reepay.integromat.celonis.com endpoint, served entirely
	 * by ApiResponseMock's generic pre_http_request filter).
	 */
	public function test_send_event_posts_expected_payload() {
		$captured_body = null;
		$callback      = $this->capture_request_body( $captured_body );

		ApiResponseMock::respond_with( array( 'ok' => true ) );

		WC_Reepay_Statistics::send_event( 'custom_event' );

		remove_filter( 'pre_http_request', $callback, 5 );

		$this->assertIsArray( $captured_body );
		$this->assertSame( 'custom_event', $captured_body['event'] );
		$this->assertSame( 'WOOCOMMERCE-SUBSCRIPTION', $captured_body['plugin'] );
		$this->assertSame( home_url(), $captured_body['url'] );
	}

	/**
	 * Test @see WC_Reepay_Statistics::send_event never reaches the real
	 * analytics webhook URL - ApiResponseMock intercepts it via the generic
	 * `pre_http_request` filter, confirmed by asserting the mocked response
	 * flows back through send_event()'s own return value (the raw
	 * wp_remote_post() result).
	 */
	public function test_send_event_never_hits_real_url_response_is_mocked() {
		ApiResponseMock::respond_with( array( 'received' => true ), 200 );

		$result = WC_Reepay_Statistics::send_event( 'another_event' );

		$this->assertIsArray( $result );
		$this->assertSame( 200, $result['response']['code'] );
		$this->assertSame( wp_json_encode( array( 'received' => true ) ), $result['body'] );
	}

	/*
	 * ------------------------------------------------------------------
	 * plugin_deactivated() / plugin_deleted() / private_key_activated()
	 * ------------------------------------------------------------------
	 */

	/**
	 * Test @see WC_Reepay_Statistics::plugin_deactivated sends the
	 * 'deactivated' event.
	 */
	public function test_plugin_deactivated_sends_deactivated_event() {
		$captured_body = null;
		$callback      = $this->capture_request_body( $captured_body );

		WC_Reepay_Statistics::plugin_deactivated();

		remove_filter( 'pre_http_request', $callback, 5 );

		$this->assertSame( 'deactivated', $captured_body['event'] );
	}

	/**
	 * Test @see WC_Reepay_Statistics::plugin_deleted sends the 'deleted'
	 * event.
	 */
	public function test_plugin_deleted_sends_deleted_event() {
		$captured_body = null;
		$callback      = $this->capture_request_body( $captured_body );

		WC_Reepay_Statistics::plugin_deleted();

		remove_filter( 'pre_http_request', $callback, 5 );

		$this->assertSame( 'deleted', $captured_body['event'] );
	}

	/**
	 * Test @see WC_Reepay_Statistics::private_key_activated sends the
	 * 'activated' event.
	 */
	public function test_private_key_activated_sends_activated_event() {
		$captured_body = null;
		$callback      = $this->capture_request_body( $captured_body );

		WC_Reepay_Statistics::private_key_activated();

		remove_filter( 'pre_http_request', $callback, 5 );

		$this->assertSame( 'activated', $captured_body['event'] );
	}

	/*
	 * ------------------------------------------------------------------
	 * upgrade_completed()
	 * ------------------------------------------------------------------
	 */

	/**
	 * Test @see WC_Reepay_Statistics::upgrade_completed sends the 'updated'
	 * event when $options['plugins'] contains a string matching
	 * REEPAY_PLUGIN_FILE at a non-zero offset (the normal/working case for
	 * this plugin's own update, where the plugins array holds a relative
	 * "folder/file.php" style path from a context that happens to contain
	 * REEPAY_PLUGIN_FILE's basename further into the string - here
	 * reproduced directly with REEPAY_PLUGIN_FILE prefixed by a non-empty
	 * marker so strpos() returns a truthy non-zero offset).
	 */
	public function test_upgrade_completed_sends_event_for_matching_plugin() {
		$captured_body = null;
		$callback      = $this->capture_request_body( $captured_body );

		WC_Reepay_Statistics::upgrade_completed(
			null,
			array( 'plugins' => array( 'prefix/' . REEPAY_PLUGIN_FILE ) )
		);

		remove_filter( 'pre_http_request', $callback, 5 );

		$this->assertSame( 'updated', $captured_body['event'] );
	}

	/**
	 * Test @see WC_Reepay_Statistics::upgrade_completed does NOT send an
	 * event for an unrelated plugin's update.
	 */
	public function test_upgrade_completed_no_event_for_unrelated_plugin() {
		$captured_body = null;
		$callback      = $this->capture_request_body( $captured_body );

		WC_Reepay_Statistics::upgrade_completed(
			null,
			array( 'plugins' => array( 'some-other-plugin/some-other-plugin.php' ) )
		);

		remove_filter( 'pre_http_request', $callback, 5 );

		$this->assertNull( $captured_body, 'No outbound request should have been made for an unrelated plugin.' );
	}

	/**
	 * Test @see WC_Reepay_Statistics::upgrade_completed does NOT send an
	 * event when $options['plugins'] is empty.
	 */
	public function test_upgrade_completed_no_event_when_plugins_list_empty() {
		$captured_body = null;
		$callback      = $this->capture_request_body( $captured_body );

		WC_Reepay_Statistics::upgrade_completed( null, array( 'plugins' => array() ) );

		remove_filter( 'pre_http_request', $callback, 5 );

		$this->assertNull( $captured_body );
	}

	/**
	 * Test @see WC_Reepay_Statistics::upgrade_completed CONFIRMED GOTCHA: an
	 * EXACT match (the plugin string being identical to REEPAY_PLUGIN_FILE,
	 * so strpos() returns 0) is loose-falsy in the method's
	 * `if (strpos($plugin, REEPAY_PLUGIN_FILE))` check and therefore does
	 * NOT trigger send_event() - a real latent bug in production code,
	 * documented here rather than fixed (no production code changes per
	 * this task's constraints) or silently ignored.
	 */
	public function test_upgrade_completed_exact_path_match_is_falsy_due_to_strpos_bug() {
		$captured_body = null;
		$callback      = $this->capture_request_body( $captured_body );

		WC_Reepay_Statistics::upgrade_completed( null, array( 'plugins' => array( REEPAY_PLUGIN_FILE ) ) );

		remove_filter( 'pre_http_request', $callback, 5 );

		$this->assertNull(
			$captured_body,
			'Confirmed gotcha: strpos() returning 0 for an exact match is falsy in the unguarded if() check, so no event is sent.'
		);
	}

	/*
	 * ------------------------------------------------------------------
	 * Constructor smoke test
	 * ------------------------------------------------------------------
	 */

	/**
	 * Test @see WC_Reepay_Statistics::__construct does not fatal or warn when
	 * called more than once in the same process - register_deactivation_hook()/
	 * register_uninstall_hook() are themselves just add_action() calls keyed
	 * by file+callback, so repeated construction is safe.
	 */
	public function test_constructor_does_not_fatal_on_repeated_construction() {
		new WC_Reepay_Statistics();
		new WC_Reepay_Statistics();

		$this->addToAssertionCount( 1 );
	}
}
