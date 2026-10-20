<?php
/**
 * Class LogTest
 *
 * Covers WC_RS_Log (includes/WC_RS_Log.php).
 *
 * SINGLETON STATIC-STATE LEAK (gotcha #6): WC_RS_Log is a classic singleton
 * (private static $instance) whose test_mode/debug flags are read from
 * WooCommerce_Reepay_Subscriptions::settings() ONLY ONCE, inside the private
 * constructor, the first time get_instance() is ever called. Once
 * constructed, later get_instance() calls return the SAME instance
 * regardless of subsequent setting changes. To test both the "debug
 * enabled" and "debug disabled" branches, this file resets the private
 * static $instance to null via ReflectionProperty between tests (same
 * technique already used elsewhere in this suite, e.g.
 * WC_Reepay_Subscription_API::get_instance() resets).
 *
 * WooCommerce_Reepay_Subscriptions::$settings is itself a private static
 * array snapshotted once at plugin bootstrap from get_option() - updating
 * the 'debug' option alone has no effect on an already-bootstrapped
 * process, so this file overwrites that static array directly via
 * reflection as well (same pattern as
 * tests/unit/myaccount/SubscriptionActionsTest.php's set_static_setting()).
 *
 * @package Reepay\Subscriptions
 */

use Reepay\Subscriptions\Tests\Helpers\Reepay_Subscriptions_UnitTestCase;

/**
 * LogTest.
 */
class LogTest extends Reepay_Subscriptions_UnitTestCase {

	/**
	 * Snapshot of WooCommerce_Reepay_Subscriptions::$settings, restored in
	 * tear_down().
	 *
	 * @var array
	 */
	private array $settings_snapshot;

	public function set_up() {
		parent::set_up();

		$this->settings_snapshot = WooCommerce_Reepay_Subscriptions::settings();

		// Always start each test with a fresh (un-constructed) singleton, so
		// the 'debug' flag this test is about to set is actually the one
		// read by the private constructor.
		$this->reset_log_singleton();
	}

	public function tear_down() {
		$this->set_static_settings( $this->settings_snapshot );
		$this->reset_log_singleton();

		parent::tear_down();
	}

	/**
	 * Reset WC_RS_Log's private static $instance to null via reflection, so
	 * the next get_instance() call re-runs the private constructor (and thus
	 * re-reads the current debug/test_mode settings).
	 */
	private function reset_log_singleton(): void {
		$ref = new ReflectionProperty( WC_RS_Log::class, 'instance' );
		$ref->setAccessible( true );
		$ref->setValue( null, null );
	}

	/**
	 * Overwrite a single key of WooCommerce_Reepay_Subscriptions's private
	 * static $settings property via reflection. Pattern copied verbatim from
	 * tests/unit/myaccount/SubscriptionActionsTest.php.
	 *
	 * @param string $key settings array key.
	 * @param mixed  $value value to assign.
	 */
	private function set_static_setting( string $key, $value ): void {
		$ref              = new ReflectionProperty( WooCommerce_Reepay_Subscriptions::class, 'settings' );
		$ref->setAccessible( true );
		$settings         = $ref->getValue();
		$settings[ $key ] = $value;
		$ref->setValue( null, $settings );
	}

	/**
	 * Restore the full static $settings array. Pattern copied verbatim from
	 * tests/unit/myaccount/SubscriptionActionsTest.php.
	 *
	 * @param array $settings full settings array to restore.
	 */
	private function set_static_settings( array $settings ): void {
		$ref = new ReflectionProperty( WooCommerce_Reepay_Subscriptions::class, 'settings' );
		$ref->setAccessible( true );
		$ref->setValue( null, $settings );
	}

	/**
	 * Test @see WC_RS_Log::log with debug enabled: the message actually
	 * reaches wc_get_logger() - verified via the 'woocommerce_logger_log_message'
	 * filter, which WC_Logger::log() always applies (confirmed by reading
	 * includes/class-wc-logger.php directly) immediately before handing the
	 * message to whichever handler is currently registered. This assertion
	 * is deliberately handler-agnostic: WooCommerce's default log handler
	 * (LogHandlerFileV2, confirmed by reading
	 * src/Internal/Admin/Logging/Settings.php's DEFAULTS) uses a different
	 * file-naming scheme than the legacy WC_Log_Handler_File, so asserting
	 * on this filter rather than reverse-engineering the active handler's
	 * file path is the robust approach.
	 */
	public function test_log_with_debug_enabled_reaches_logger() {
		$this->set_static_setting( 'debug', true );
		$this->reset_log_singleton();

		$marker          = 'LogTest-debug-enabled-marker-' . wp_generate_password( 12, false );
		$captured_message = null;
		add_filter(
			'woocommerce_logger_log_message',
			function ( $message, $level, $context, $handler ) use ( &$captured_message ) {
				$captured_message = $message;

				return $message;
			},
			10,
			4
		);

		WC_RS_Log::get_instance()->log( $marker );

		$this->assertSame( $marker, $captured_message, 'Expected the message to reach WC_Logger::log() when debug is enabled.' );
	}

	/**
	 * Test @see WC_RS_Log::log with debug disabled is a complete no-op - no
	 * logger interaction at all. Verified by hooking
	 * 'woocommerce_logger_log_message' - a filter WC_Logger::log() always
	 * applies (confirmed by reading includes/class-wc-logger.php directly)
	 * whenever should_handle() lets the level through, regardless of which
	 * handler ultimately processes it - and asserting it never fires.
	 */
	public function test_log_with_debug_disabled_is_noop() {
		$this->set_static_setting( 'debug', false );
		$this->reset_log_singleton();

		$logger_was_called = false;
		add_filter(
			'woocommerce_logger_log_message',
			function ( $message, $level, $context, $handler ) use ( &$logger_was_called ) {
				$logger_was_called = true;

				return $message;
			},
			10,
			4
		);

		WC_RS_Log::get_instance()->log( 'This must never reach the logger.' );

		$this->assertFalse( $logger_was_called, 'wc_get_logger() must never be invoked when debug is disabled.' );
	}

	/**
	 * Test @see WC_RS_Log::log stringifies a non-string $message via
	 * var_export() before it would reach the logger - verified via the same
	 * 'woocommerce_logger_log_message' filter as the test above, confirming
	 * the var_export()-shaped representation (not a PHP Notice, not raw
	 * array-to-string conversion) is what actually reaches WC_Logger::log().
	 */
	public function test_log_non_string_message_uses_var_export_fallback() {
		$this->set_static_setting( 'debug', true );
		$this->reset_log_singleton();

		$data   = array( 'foo' => 'bar-marker-' . wp_generate_password( 8, false ) );
		$expect = var_export( $data, true );

		$captured_message = null;
		add_filter(
			'woocommerce_logger_log_message',
			function ( $message, $level, $context, $handler ) use ( &$captured_message ) {
				$captured_message = $message;

				return $message;
			},
			10,
			4
		);

		WC_RS_Log::get_instance()->log( $data );

		$this->assertSame( $expect, $captured_message );
	}
}
