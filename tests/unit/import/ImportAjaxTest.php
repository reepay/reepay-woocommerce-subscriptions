<?php
/**
 * Class ImportAjaxTest
 *
 * Covers WC_Reepay_Import_AJAX (includes/import/WC_Reepay_Import_AJAX.php).
 *
 * SAFE wp_die() HANDLING (gotcha #3): get_objects()/save_objects()/
 * check_nonce() all terminate via wp_send_json_error()/wp_send_json_success(),
 * which call wp_die() internally. This is NOT the same hazard as the raw
 * exit() calls found in Task 5.2 - WP_Ajax_UnitTestCase (which
 * Reepay_Subscriptions_Ajax_UnitTestCase extends) installs a wp_die handler
 * that throws a catchable WPAjaxDieStopException/WPAjaxDieContinueException
 * instead of actually terminating the process (confirmed by reading
 * /tmp/wordpress-tests-lib/includes/testcase-ajax.php directly). dieHandler()
 * captures ob_get_clean() into $this->_last_response FIRST, then throws
 * WPAjaxDieStopException only if that captured buffer is empty, else
 * WPAjaxDieContinueException. CONFIRMED by actually running these tests:
 * since wp_send_json_error()/wp_send_json_success() always echo their JSON
 * payload via wp_die($json) (i.e. output always precedes the die call
 * itself), _handleAjax()'s captured buffer is NEVER empty for this class's
 * methods - every exception thrown is WPAjaxDieContinueException, never
 * WPAjaxDieStopException. (By contrast, calling check_nonce()/wp_send_json_error()
 * directly - NOT through _handleAjax()'s ob_start() wrapper - still throws
 * WPAjaxDieContinueException too, for the same reason: wp_send_json_error()
 * itself echoes before dying, regardless of whether an outer ob_start() is
 * active.) $this->_handleAjax($action) drives the real
 * do_action('wp_ajax_'.$action) hook exactly like admin-ajax.php would.
 *
 * CONFIRMED by reading the full source:
 * - get_objects()/save_objects() both gate on
 *   current_user_can('manage_woocommerce') FIRST, then check_nonce() - an
 *   unauthorized user never reaches the nonce check at all.
 * - get_objects()'s per-object switch statement only recognizes
 *   'plans'/'addons'/'coupons'/'discounts' (methods belonging to a sibling
 *   plugin's import flow, not this plugin's own 'customers'/'cards'/
 *   'subscriptions' object keys) - any of THIS plugin's own import object
 *   keys therefore always fall into the `default: wp_send_json_error('Invalid
 *   object type')` branch. This is a real mismatch confirmed by reading the
 *   switch statement body directly (not a guess) - the "happy path" for
 *   get_objects() as actually implemented is this default/invalid-object-type
 *   response for every one of WC_Reepay_Import::$import_objects's real keys.
 *   Covered below as the confirmed real behavior rather than assuming an
 *   unreachable success path.
 * - get_objects_to_import_from_get($data) is a pure array-normalization
 *   function with no WordPress dependency beyond optionally reading $_GET
 *   when $data is null - tested directly with an explicit $data argument.
 * - check_nonce() has two rejection branches (capability, then nonce) - one
 *   shared set of tests here covers both; not duplicated per caller.
 *
 * @package Reepay\Subscriptions
 */

use Reepay\Subscriptions\Tests\Helpers\Reepay_Subscriptions_Ajax_UnitTestCase;

/**
 * ImportAjaxTest.
 */
class ImportAjaxTest extends Reepay_Subscriptions_Ajax_UnitTestCase {

	/**
	 * @var WC_Reepay_Import_AJAX
	 */
	private WC_Reepay_Import_AJAX $import_ajax;

	public function set_up() {
		parent::set_up();

		// init() populates WC_Reepay_Import::$import_objects, read by
		// get_objects()/save_objects()/get_objects_to_import_from_get().
		( new WC_Reepay_Import() )->init();

		$this->import_ajax = new WC_Reepay_Import_AJAX();
	}

	/**
	 * Call at the top of any test that triggers wp_send_json_error()/
	 * wp_send_json_success(). CONFIRMED real cross-file interaction: WP core's
	 * wp_send_json() (wp-includes/functions.php:4588) calls _doing_it_wrong()
	 * whenever wp_is_serving_rest_request() is true, which is defined as
	 * `defined('REST_REQUEST') && REST_REQUEST` (wp-includes/functions.php:4862).
	 * REST_REQUEST is a PHP constant - once Task 3.5's CurrencyTest.php
	 * deliberately defines it true (an already-approved, documented
	 * trade-off), it can never be undefined for the rest of the PHP process.
	 * So this class's tests pass in isolation but fail when run after
	 * CurrencyTest.php in the full suite, purely because of execution order.
	 * Pre-declaring the expected incorrect-usage notice ONLY when the
	 * constant is already truthy keeps this test correct in both contexts:
	 * isolated (notice never fires, no expectation registered) and full-suite
	 * (notice fires, explicitly expected, doesn't fail the test).
	 */
	private function maybe_expect_rest_request_incorrect_usage(): void {
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			$this->setExpectedIncorrectUsage( 'wp_send_json' );
		}
	}

	/*
	 * ------------------------------------------------------------------
	 * get_objects_to_import_from_get() - pure function, no Ajax needed
	 * ------------------------------------------------------------------
	 */

	/**
	 * Test @see WC_Reepay_Import_AJAX::get_objects_to_import_from_get strips
	 * unrecognized keys and normalizes a single ['all' => 'on']-shaped value
	 * down to ['all'].
	 */
	public function test_get_objects_to_import_from_get_normalizes_all_option() {
		$result = $this->import_ajax->get_objects_to_import_from_get(
			array(
				'customers'        => array( 'all' => 'on' ),
				'not_a_real_object' => array( 'all' => 'on' ),
			)
		);

		$this->assertSame( array( 'all' ), $result['customers'] );
		$this->assertArrayNotHasKey( 'not_a_real_object', $result );
	}

	/**
	 * Test @see WC_Reepay_Import_AJAX::get_objects_to_import_from_get
	 * normalizes a non-array scalar value (e.g. a bare 'debug' => 'on') to
	 * ['all'].
	 */
	public function test_get_objects_to_import_from_get_normalizes_scalar_value() {
		$result = $this->import_ajax->get_objects_to_import_from_get(
			array( 'cards' => 'on' )
		);

		$this->assertSame( array( 'all' ), $result['cards'] );
	}

	/*
	 * ------------------------------------------------------------------
	 * check_nonce() - shared by get_objects()/save_objects()
	 * ------------------------------------------------------------------
	 */

	/**
	 * Test @see WC_Reepay_Import_AJAX::check_nonce rejects a user without
	 * manage_woocommerce via wp_send_json_error() -> wp_die().
	 *
	 * Wrapped in ob_start()/ob_end_clean() - confirmed necessary by actually
	 * running this test: the die handler's own ob_get_clean() call (see
	 * class docblock) otherwise closes a buffer this test didn't open
	 * itself, which PHPUnit flags as "risky" (test did not only close its
	 * own output buffers). _handleAjax() avoids this by opening its own
	 * ob_start() first; this direct (non-_handleAjax) call must do the same.
	 */
	public function test_check_nonce_rejects_unauthorized_user() {
		$this->maybe_expect_rest_request_incorrect_usage();

		$subscriber_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber_id );

		// WP_Ajax_UnitTestCase::dieHandler() (testcase-ajax.php:217) already
		// does `$this->_last_response .= ob_get_clean();` itself as part of
		// throwing the exception - it consumes the buffer we open below. Do
		// NOT also call ob_end_clean()/ob_get_clean() here: that would
		// over-close one level that no longer belongs to us (this was the
		// actual cause of the original "did not close its own output
		// buffers" risky warning, not expectException() itself).
		ob_start();
		try {
			$this->import_ajax->check_nonce();
			$this->fail( 'Expected WPAjaxDieContinueException was not thrown.' );
		} catch ( WPAjaxDieContinueException $e ) {
			$this->assertTrue( true );
		}
	}

	/**
	 * Test @see WC_Reepay_Import_AJAX::check_nonce rejects a valid-capability
	 * user with a missing/invalid nonce. See ob_start() note above.
	 */
	public function test_check_nonce_rejects_invalid_nonce() {
		$this->maybe_expect_rest_request_incorrect_usage();

		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );
		$_REQUEST['nonce'] = 'not-a-real-nonce';

		// See note in test_check_nonce_rejects_unauthorized_user() above -
		// dieHandler() consumes this buffer itself, don't double-close it.
		ob_start();
		try {
			$this->import_ajax->check_nonce();
			$this->fail( 'Expected WPAjaxDieContinueException was not thrown.' );
		} catch ( WPAjaxDieContinueException $e ) {
			$this->assertTrue( true );
		}
	}

	/**
	 * Test @see WC_Reepay_Import_AJAX::check_nonce returns true for an
	 * authorized user with a valid nonce (no exception, no die).
	 */
	public function test_check_nonce_passes_for_authorized_user_with_valid_nonce() {
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );
		$_REQUEST['nonce'] = wp_create_nonce( WC_Reepay_Import_AJAX::$ajax_nonce );

		$this->assertTrue( $this->import_ajax->check_nonce() );
	}

	/*
	 * ------------------------------------------------------------------
	 * get_objects() - via _handleAjax()
	 * ------------------------------------------------------------------
	 */

	/**
	 * Test @see WC_Reepay_Import_AJAX::get_objects rejects an unauthorized
	 * user (cheapest branch, no nonce/object processing reached).
	 */
	public function test_get_objects_rejects_unauthorized_user() {
		$this->maybe_expect_rest_request_incorrect_usage();

		$subscriber_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber_id );

		$this->expectException( WPAjaxDieContinueException::class );

		$this->_handleAjax( WC_Reepay_Import_AJAX::$ajax_prefix . '_get_objects' );
	}

	/**
	 * Test @see WC_Reepay_Import_AJAX::get_objects for an authorized user
	 * with a valid nonce and one of this plugin's real import object keys
	 * ('customers') selected. Confirmed real behavior: the method's internal
	 * switch statement does not recognize 'customers' (only
	 * plans/addons/coupons/discounts, which belong to a different import
	 * flow), so it falls into the default branch and responds with
	 * wp_send_json_error('Invalid object type') - this IS the real, correct
	 * current behavior of this method for this plugin's own object types,
	 * not a workaround.
	 */
	public function test_get_objects_authorized_request_for_customers_hits_invalid_object_type() {
		$this->maybe_expect_rest_request_incorrect_usage();

		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		$_GET['nonce']                                 = wp_create_nonce( WC_Reepay_Import_AJAX::$ajax_nonce );
		$_GET[ WC_Reepay_Import::$option_name ]['customers'] = array( 'all' => 'on' );

		$this->expectException( WPAjaxDieContinueException::class );

		try {
			$this->_handleAjax( WC_Reepay_Import_AJAX::$ajax_prefix . '_get_objects' );
		} finally {
			$this->assertStringContainsString( 'Invalid object type', $this->_last_response );
		}
	}

	/*
	 * ------------------------------------------------------------------
	 * save_objects() - via _handleAjax()
	 * ------------------------------------------------------------------
	 */

	/**
	 * Test @see WC_Reepay_Import_AJAX::save_objects rejects an unauthorized
	 * user.
	 */
	public function test_save_objects_rejects_unauthorized_user() {
		$this->maybe_expect_rest_request_incorrect_usage();

		$subscriber_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber_id );

		$this->expectException( WPAjaxDieContinueException::class );

		$this->_handleAjax( WC_Reepay_Import_AJAX::$ajax_prefix . '_save_objects' );
	}

	/**
	 * Test @see WC_Reepay_Import_AJAX::save_objects for an authorized user
	 * with a valid nonce and no $_POST['selected'] entries at all - every
	 * object key is skipped via the `empty($_POST['selected'][$object])`
	 * continue, so the method reaches wp_send_json_success([]) with an empty
	 * result array (success path - confirmed no production object key can
	 * reach the inner switch without a non-empty 'selected' entry).
	 */
	public function test_save_objects_authorized_request_without_selection_returns_empty_success() {
		$this->maybe_expect_rest_request_incorrect_usage();

		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		$_POST['nonce'] = wp_create_nonce( WC_Reepay_Import_AJAX::$ajax_nonce );

		$this->expectException( WPAjaxDieContinueException::class );

		try {
			$this->_handleAjax( WC_Reepay_Import_AJAX::$ajax_prefix . '_save_objects' );
		} finally {
			$decoded = json_decode( $this->_last_response, true );
			$this->assertTrue( $decoded['success'] );
			$this->assertSame( array(), $decoded['data'] );
		}
	}
}
