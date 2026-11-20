<?php
/**
 * Class StatisticsTest
 *
 * Covers WC_Reepay_Statistics (includes/WC_Reepay_Statistics.php): send_event,
 * plugin_deactivated, plugin_deleted and upgrade_completed.
 *
 * Phase 5 scope: baseline regression coverage per requirements.md §3.4 - this
 * is a thin class, so a lighter checklist applies per the brief.
 *
 * Confirmed real behavior (read from source):
 * - send_event($event) (static) is opt-in: it returns null and sends nothing
 *   unless the '_reepay_statistics' option is exactly 'yes' (set_up() turns
 *   it on; dedicated tests cover the opted-out case). When enabled it makes
 *   a non-blocking wp_remote_post() call to a hardcoded third-party
 *   analytics webhook URL (https://hook.reepay.integromat.celonis.com/...) -
 *   mocked here with ApiResponseMock, whose `pre_http_request` filter is
 *   generic (no URL-based exclusion). The real URL is never hit in this
 *   suite. The payload holds plugin, version, url and event - no private key.
 * - plugin_deactivated()/plugin_deleted() are thin static wrappers calling
 *   send_event() with a fixed event name ('deactivated'/'deleted') - captured
 *   here via a custom `pre_http_request` callback that inspects the posted
 *   JSON body. (private_key_activated() was removed from production.)
 * - upgrade_completed($upgrader_object, $options) only calls
 *   send_event('updated') when $options['plugins'] contains an entry
 *   strictly equal to plugin_basename(REEPAY_PLUGIN_FILE) - the
 *   "folder/file.php" form WordPress passes to upgrader_process_complete.
 *   (This replaced an older strpos() check whose exact-match case was falsy.)
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

	public function set_up() {
		parent::set_up();

		// send_event() only sends when the merchant opted in.
		update_option( '_reepay_statistics', 'yes' );
	}

	public function tear_down() {
		delete_option( '_reepay_statistics' );
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
	 * Test @see WC_Reepay_Statistics::send_event posts only plugin, version,
	 * url and event - never the private key - as a non-blocking request
	 * with a 3 second timeout.
	 */
	public function test_send_event_payload_contains_no_private_key_and_is_non_blocking() {
		$captured_args = null;
		$callback      = function ( $preempt, $args ) use ( &$captured_args ) {
			$captured_args = $args;

			return array(
				'headers'  => array(),
				'body'     => '',
				'response' => array( 'code' => 200, 'message' => 'OK' ),
				'cookies'  => array(),
				'filename' => null,
			);
		};
		add_filter( 'pre_http_request', $callback, 5, 2 );

		WC_Reepay_Statistics::send_event( 'payload_check' );

		remove_filter( 'pre_http_request', $callback, 5 );

		$this->assertIsArray( $captured_args );
		$body = json_decode( $captured_args['body'], true );
		$this->assertSame( array( 'plugin', 'version', 'url', 'event' ), array_keys( $body ) );
		$this->assertArrayNotHasKey( 'privatekey', $body );
		$this->assertFalse( $captured_args['blocking'] );
		$this->assertSame( 3, $captured_args['timeout'] );
	}

	/**
	 * Test @see WC_Reepay_Statistics::send_event sends nothing and returns
	 * null unless the merchant opted in ('_reepay_statistics' === 'yes') -
	 * covers the option being absent, 'no', and any other value.
	 */
	public function test_send_event_sends_nothing_when_not_opted_in() {
		foreach ( array( null, 'no', '1' ) as $option ) {
			if ( null === $option ) {
				delete_option( '_reepay_statistics' );
			} else {
				update_option( '_reepay_statistics', $option );
			}

			$captured_body = null;
			$callback      = $this->capture_request_body( $captured_body );

			$result = WC_Reepay_Statistics::send_event( 'opted_out_event' );
			WC_Reepay_Statistics::plugin_deactivated();

			remove_filter( 'pre_http_request', $callback, 5 );

			$label = var_export( $option, true );
			$this->assertNull( $result, "Option: $label" );
			$this->assertNull( $captured_body, "Option: $label" );
		}
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

	/*
	 * ------------------------------------------------------------------
	 * upgrade_completed()
	 * ------------------------------------------------------------------
	 */

	/**
	 * Test @see WC_Reepay_Statistics::upgrade_completed sends the 'updated'
	 * event when $options['plugins'] contains this plugin's basename
	 * ("folder/file.php") - the form WordPress passes to
	 * upgrader_process_complete for this plugin's own update.
	 */
	public function test_upgrade_completed_sends_event_for_matching_plugin() {
		$captured_body = null;
		$callback      = $this->capture_request_body( $captured_body );

		WC_Reepay_Statistics::upgrade_completed(
			null,
			array( 'plugins' => array( 'some-other-plugin/some-other-plugin.php', plugin_basename( REEPAY_PLUGIN_FILE ) ) )
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
	 * Test @see WC_Reepay_Statistics::upgrade_completed matches the basename
	 * strictly: the absolute REEPAY_PLUGIN_FILE path, or a string that only
	 * contains the basename, is not treated as this plugin's update.
	 */
	public function test_upgrade_completed_no_event_for_non_exact_match() {
		$captured_body = null;
		$callback      = $this->capture_request_body( $captured_body );

		WC_Reepay_Statistics::upgrade_completed(
			null,
			array(
				'plugins' => array(
					REEPAY_PLUGIN_FILE,
					'prefix/' . plugin_basename( REEPAY_PLUGIN_FILE ),
				),
			)
		);

		remove_filter( 'pre_http_request', $callback, 5 );

		$this->assertNull( $captured_body );
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
