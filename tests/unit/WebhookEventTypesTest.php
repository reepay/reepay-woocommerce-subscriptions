<?php
/**
 * Class WebhookEventTypesTest
 *
 * Covers WooCommerce_Reepay_Subscriptions::resolve_webhook_event_types() and
 * enable_all_webhook_event_types() (reepay-subscriptions-for-woocommerce.php).
 *
 * @package Reepay\Subscriptions
 */

use Reepay\Subscriptions\Tests\Helpers\Reepay_Subscriptions_UnitTestCase;

/**
 * WebhookEventTypesTest.
 */
class WebhookEventTypesTest extends Reepay_Subscriptions_UnitTestCase {

	/**
	 * @var callable|null
	 */
	private $webhook_settings_filter = null;

	public function set_up() {
		parent::set_up();

		update_option(
			'woocommerce_reepay_checkout_settings',
			array(
				'private_key_test' => 'test-key-reepay',
				'test_mode'        => 'yes',
			)
		);

		$ref = new ReflectionProperty( WC_Reepay_Subscription_API::get_instance(), 'private_key_test' );
		$ref->setAccessible( true );
		$ref->setValue( WC_Reepay_Subscription_API::get_instance(), 'test-key-reepay' );

		$ref = new ReflectionProperty( WC_Reepay_Subscription_API::get_instance(), 'test_mode' );
		$ref->setAccessible( true );
		$ref->setValue( WC_Reepay_Subscription_API::get_instance(), true );
	}

	public function tear_down() {
		if ( null !== $this->webhook_settings_filter ) {
			remove_filter( 'pre_http_request', $this->webhook_settings_filter, 10 );
			$this->webhook_settings_filter = null;
		}

		parent::tear_down();
	}

	/**
	 * Registers a request-aware mock for GET/PUT account/webhook_settings,
	 * since this repo's ApiResponseMock helper is not request-method-aware.
	 * Mirrors WC_Reepay_Subscription_API::request()'s actual wp_remote_request()
	 * call shape (includes/WC_Reepay_Subscription_API.php:90-104): $args['method']
	 * is the HTTP method, $args['body'] is the JSON-encoded params (only present
	 * when params is non-empty).
	 *
	 * @param array         $get_response The array to return for the GET call.
	 * @param callable|null $on_put       Optional callback invoked with the decoded
	 *                                    PUT body, for assertions.
	 */
	private function mock_webhook_settings( array $get_response, ?callable $on_put = null ): void {
		$this->webhook_settings_filter = function ( $preempt, $parsed_args, $url ) use ( $get_response, $on_put ) {
			if ( strpos( $url, 'account/webhook_settings' ) === false ) {
				return $preempt;
			}

			if ( ( $parsed_args['method'] ?? 'GET' ) === 'PUT' ) {
				$body = json_decode( $parsed_args['body'] ?? '{}', true );

				if ( null !== $on_put ) {
					$on_put( $body );
				}

				return array(
					'headers'  => array(),
					'body'     => wp_json_encode( $body ),
					'response' => array( 'code' => 200, 'message' => 'OK' ),
					'cookies'  => array(),
					'filename' => null,
				);
			}

			return array(
				'headers'  => array(),
				'body'     => wp_json_encode( $get_response ),
				'response' => array( 'code' => 200, 'message' => 'OK' ),
				'cookies'  => array(),
				'filename' => null,
			);
		};

		add_filter( 'pre_http_request', $this->webhook_settings_filter, 10, 3 );
	}

	// -----------------------------------------------------------------------
	// resolve_webhook_event_types()
	// -----------------------------------------------------------------------

	/**
	 * Test @see WooCommerce_Reepay_Subscriptions::resolve_webhook_event_types
	 * activates all implemented event types when none of them are currently
	 * active on the account.
	 */
	public function test_resolve_webhook_event_types_activates_all_when_none_active() {
		$result = reepay_s()->resolve_webhook_event_types( array() );

		$this->assertTrue( $result['needs_update'] );
		$this->assertEqualsCanonicalizing(
			WooCommerce_Reepay_Subscriptions::IMPLEMENTED_WEBHOOK_EVENT_TYPES,
			$result['event_types']
		);
	}

	/**
	 * Test @see WooCommerce_Reepay_Subscriptions::resolve_webhook_event_types
	 * preserves pre-existing, unrelated event types (including the payment
	 * plugin's own 7) while still activating all 8 implemented ones, when
	 * none of the implemented ones are active yet.
	 */
	public function test_resolve_webhook_event_types_preserves_unrelated_when_none_of_ours_active() {
		$existing = array( 'invoice_authorized', 'customer_created', 'some_third_party_event' );

		$result = reepay_s()->resolve_webhook_event_types( $existing );

		$this->assertTrue( $result['needs_update'] );
		foreach ( $existing as $event_type ) {
			$this->assertContains( $event_type, $result['event_types'] );
		}
		foreach ( WooCommerce_Reepay_Subscriptions::IMPLEMENTED_WEBHOOK_EVENT_TYPES as $event_type ) {
			$this->assertContains( $event_type, $result['event_types'] );
		}
	}

	/**
	 * Test @see WooCommerce_Reepay_Subscriptions::resolve_webhook_event_types
	 * leaves event_types completely unchanged when at least one implemented
	 * event type is already active — missing ones from the 8 must NOT be
	 * filled in individually.
	 */
	public function test_resolve_webhook_event_types_leaves_unchanged_when_one_already_active() {
		$existing = array( 'subscription_renewal', 'invoice_authorized' );

		$result = reepay_s()->resolve_webhook_event_types( $existing );

		$this->assertFalse( $result['needs_update'] );
		$this->assertEqualsCanonicalizing( $existing, $result['event_types'] );
	}

	/**
	 * Test @see WooCommerce_Reepay_Subscriptions::resolve_webhook_event_types
	 * never activates based on the payment plugin's own events alone — only
	 * this plugin's 8 implemented events count toward "already active".
	 */
	public function test_resolve_webhook_event_types_payment_plugin_events_alone_do_not_prevent_activation() {
		// Only payment-plugin-owned events active; none of our 8.
		$existing = array( 'invoice_authorized', 'invoice_settled', 'customer_created', 'customer_payment_method_added' );

		$result = reepay_s()->resolve_webhook_event_types( $existing );

		$this->assertTrue( $result['needs_update'], 'Payment-plugin events must not count as "ours already active".' );
		foreach ( WooCommerce_Reepay_Subscriptions::IMPLEMENTED_WEBHOOK_EVENT_TYPES as $event_type ) {
			$this->assertContains( $event_type, $result['event_types'] );
		}
	}

	/**
	 * Test @see WooCommerce_Reepay_Subscriptions::resolve_webhook_event_types
	 * never produces duplicates or flip-flops when fed its own previous
	 * output (simulates two consecutive settings saves with no external
	 * change in between).
	 */
	public function test_resolve_webhook_event_types_idempotent_across_consecutive_calls() {
		$first  = reepay_s()->resolve_webhook_event_types( array() );
		$second = reepay_s()->resolve_webhook_event_types( $first['event_types'] );

		$this->assertFalse( $second['needs_update'] );
		$this->assertEqualsCanonicalizing( $first['event_types'], $second['event_types'] );
		$this->assertCount( count( array_unique( $second['event_types'] ) ), $second['event_types'] );
	}

	// -----------------------------------------------------------------------
	// enable_all_webhook_event_types() — integration
	// -----------------------------------------------------------------------

	/**
	 * Test @see WooCommerce_Reepay_Subscriptions::enable_all_webhook_event_types
	 * activates all 8 implemented event types when none are active yet,
	 * preserving everything else already on the account (urls, alert_emails,
	 * disabled, and unrelated/payment-plugin event types).
	 */
	public function test_enable_all_webhook_event_types_activates_all_when_none_active() {
		$captured_put_body = null;

		$this->mock_webhook_settings(
			array(
				'urls'         => array( 'https://example.com/wc-api/WC_Gateway_Reepay/' ),
				'disabled'     => false,
				'secret'       => 'secret_key',
				'alert_emails' => array( 'merchant@example.com' ),
				'event_types'  => array( 'invoice_authorized', 'some_third_party_event' ),
			),
			function ( $body ) use ( &$captured_put_body ) {
				$captured_put_body = $body;
			}
		);

		reepay_s()->enable_all_webhook_event_types();

		$this->assertNotNull( $captured_put_body, 'A PUT must be issued to activate the missing implemented event types.' );
		$this->assertSame( array( 'https://example.com/wc-api/WC_Gateway_Reepay/' ), $captured_put_body['urls'], 'urls must be preserved unchanged.' );
		$this->assertSame( array( 'merchant@example.com' ), $captured_put_body['alert_emails'], 'alert_emails must be preserved unchanged.' );
		$this->assertContains( 'invoice_authorized', $captured_put_body['event_types'], 'Pre-existing payment-plugin event must be preserved.' );
		$this->assertContains( 'some_third_party_event', $captured_put_body['event_types'], 'Pre-existing third-party event must be preserved.' );
		foreach ( WooCommerce_Reepay_Subscriptions::IMPLEMENTED_WEBHOOK_EVENT_TYPES as $event_type ) {
			$this->assertContains( $event_type, $captured_put_body['event_types'] );
		}
	}

	/**
	 * Test @see WooCommerce_Reepay_Subscriptions::enable_all_webhook_event_types
	 * issues NO PUT at all when at least one implemented event type is
	 * already active — the admin's configuration must be assumed deliberate.
	 */
	public function test_enable_all_webhook_event_types_no_put_when_one_already_active() {
		$put_was_called = false;

		$this->mock_webhook_settings(
			array(
				'urls'         => array( 'https://example.com/wc-api/WC_Gateway_Reepay/' ),
				'disabled'     => false,
				'alert_emails' => array(),
				'event_types'  => array( 'subscription_renewal' ),
			),
			function () use ( &$put_was_called ) {
				$put_was_called = true;
			}
		);

		reepay_s()->enable_all_webhook_event_types();

		$this->assertFalse( $put_was_called, 'No PUT should be issued when an implemented event type is already active.' );
	}

	/**
	 * Test @see WooCommerce_Reepay_Subscriptions::enable_all_webhook_event_types
	 * treats a missing event_types key in the GET response (e.g. brand new
	 * account) the same as an empty list — activates all 8.
	 */
	public function test_enable_all_webhook_event_types_missing_event_types_key_treated_as_none_active() {
		$captured_put_body = null;

		$this->mock_webhook_settings(
			array(
				'urls'         => array(),
				'disabled'     => false,
				'alert_emails' => array(),
				// Deliberately no 'event_types' key.
			),
			function ( $body ) use ( &$captured_put_body ) {
				$captured_put_body = $body;
			}
		);

		reepay_s()->enable_all_webhook_event_types();

		$this->assertNotNull( $captured_put_body );
		$this->assertEqualsCanonicalizing( WooCommerce_Reepay_Subscriptions::IMPLEMENTED_WEBHOOK_EVENT_TYPES, $captured_put_body['event_types'] );
	}

	/**
	 * Test @see WooCommerce_Reepay_Subscriptions::enable_all_webhook_event_types
	 * logs and returns gracefully (does not throw) when the initial GET fails
	 * — confirms the fix for the previously-unguarded GET call.
	 */
	public function test_enable_all_webhook_event_types_handles_failed_get_gracefully() {
		$this->webhook_settings_filter = function ( $preempt, $parsed_args, $url ) {
			if ( strpos( $url, 'account/webhook_settings' ) === false ) {
				return $preempt;
			}

			return array(
				'headers'  => array(),
				'body'     => wp_json_encode( array( 'error' => 'unauthorized' ) ),
				'response' => array( 'code' => 401, 'message' => 'Unauthorized' ),
				'cookies'  => array(),
				'filename' => null,
			);
		};
		add_filter( 'pre_http_request', $this->webhook_settings_filter, 10, 3 );

		// Must not throw.
		reepay_s()->enable_all_webhook_event_types();

		$this->assertTrue( true, 'enable_all_webhook_event_types() must not propagate an exception when the GET fails.' );
	}
}
