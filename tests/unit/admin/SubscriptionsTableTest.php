<?php
/**
 * Class SubscriptionsTableTest
 *
 * Covers WC_Reepay_Subscriptions_Table (includes/WC_Reepay_Subscriptions_Table.php),
 * a WP_List_Table subclass: get_subscriptions (via prepare_items),
 * format_status, prepare_items and the simple column_* renderers.
 *
 * Phase 5 scope: baseline regression coverage per requirements.md §3.4.
 *
 * Confirmed real behavior (read from source):
 * - get_subscriptions() (protected) reads $_GET['s']/['paged']/['orderby']/
 *   ['order'] via filter_input(INPUT_GET, ...), NOT via the $_GET
 *   superglobal directly, and makes a REAL outbound API call:
 *   reepay_s()->api()->request("subscription?" . http_build_query($params)) -
 *   mocked here with ApiResponseMock.
 *   IMPORTANT CONFIRMED GOTCHA (verified by a standalone CLI reproduction,
 *   not assumed): filter_input(INPUT_GET, ...) reliably returns NULL under
 *   the PHP CLI SAPI used by this test runner, REGARDLESS of what $_GET
 *   actually contains - this is a long-standing PHP CLI SAPI limitation
 *   (filter_input reads from the raw request input buffer, which the CLI
 *   SAPI never populates, rather than from the $_GET superglobal itself).
 *   This means setting $_GET['s']/['paged']/['orderby']/['order'] in a test
 *   has NO EFFECT on get_subscriptions() in this suite - $params always
 *   ends up as ['size' => 20, 'page' => 1] with no 'search'/'sort' key,
 *   regardless of the superglobal. This suite documents that behavior
 *   directly (test_get_subscriptions_ignores_get_params_due_to_cli_sapi_limitation)
 *   rather than silently asserting a false "respects $_GET" claim. This is
 *   unrelated to (and does not affect) WC_Reepay_Admin_Frontend's
 *   $_GET['author'] check, which reads the superglobal directly.
 * - prepare_items() catches Exception from get_subscriptions() (logs via
 *   reepay_s()->log()->log(...), does not rethrow) and leaves $this->items
 *   empty/unset if the API call throws OR the response is empty/missing a
 *   'content' key (confirmed: `if (empty($subscriptions) || empty($subscriptions['content'])) { return; }`).
 * - format_status($subscription) is a pure function (no WordPress/API
 *   dependency): returns 'Cancelled' markup if is_cancelled === true;
 *   'Expired' if state === 'expired'; 'On Hold' if state === 'on_hold';
 *   'Is cancelled' if state === 'is_cancelled'; 'Trial' if state === 'active'
 *   AND a 'trial_end' key exists with a future date, else 'Active' if
 *   state === 'active' with no future trial_end; else a generic
 *   ucfirst($state) fallback for any other string.
 * - column_plan/column_handle/column_customer_handle/column_date/
 *   column_next_period_start are simple string-building/date-formatting
 *   methods.
 *
 * @package Reepay\Subscriptions
 */

use Reepay\Subscriptions\Tests\Helpers\Reepay_Subscriptions_Ajax_UnitTestCase;
use Reepay\Subscriptions\Tests\Mocks\ApiResponseMock;

/**
 * SubscriptionsTableTest.
 */
class SubscriptionsTableTest extends Reepay_Subscriptions_Ajax_UnitTestCase {

	/**
	 * @var WC_Reepay_Subscriptions_Table
	 */
	private WC_Reepay_Subscriptions_Table $table;

	public function set_up() {
		parent::set_up();

		update_option(
			'woocommerce_reepay_checkout_settings',
			array(
				'private_key_test' => 'test-key-reepay',
				'test_mode'        => 'yes',
			)
		);

		$this->set_fake_private_key( WC_Reepay_Subscription_API::get_instance(), 'private_key_test', 'test-key-reepay' );
		$this->set_fake_private_key( WC_Reepay_Subscription_API::get_instance(), 'test_mode', true );

		$this->table = new WC_Reepay_Subscriptions_Table();
	}

	public function tear_down() {
		ApiResponseMock::clear();

		parent::tear_down();
	}

	/**
	 * Set a private/protected property on an already-constructed object via
	 * reflection. Pattern copied verbatim from tests/unit/renewals/LifecycleTest.php.
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
	 * Call the protected get_subscriptions() method via reflection.
	 *
	 * @return mixed
	 */
	private function call_get_subscriptions() {
		$ref = new ReflectionMethod( WC_Reepay_Subscriptions_Table::class, 'get_subscriptions' );
		$ref->setAccessible( true );

		return $ref->invoke( $this->table );
	}

	/*
	 * ------------------------------------------------------------------
	 * get_subscriptions()
	 * ------------------------------------------------------------------
	 */

	/**
	 * Test @see WC_Reepay_Subscriptions_Table::get_subscriptions makes the
	 * expected API call and returns the (mocked) raw response.
	 */
	public function test_get_subscriptions_returns_mocked_api_response() {
		ApiResponseMock::respond_with(
			array(
				'content'        => array(
					array(
						'handle'             => 'sub-1',
						'created'            => '2025-01-01T00:00:00.000Z',
						'next_period_start'  => '2025-02-01T00:00:00.000Z',
						'customer'           => 'cust-1',
						'plan'               => 'plan-1',
						'state'              => 'active',
					),
				),
				'total_elements' => 1,
				'size'           => 20,
				'total_pages'    => 1,
			)
		);

		$result = $this->call_get_subscriptions();

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'content', $result );
		$this->assertCount( 1, $result['content'] );
	}

	/**
	 * Test @see WC_Reepay_Subscriptions_Table::get_subscriptions - confirmed
	 * gotcha: filter_input(INPUT_GET, ...) always returns NULL under the PHP
	 * CLI SAPI used by this test runner, regardless of $_GET's actual
	 * contents (verified via a standalone CLI reproduction outside
	 * WordPress). Setting $_GET['s']/['paged']/['orderby']/['order'] here has
	 * no effect on the params sent to the API - $params is always
	 * ['size' => 20, 'page' => 1] with no 'search'/'sort' key. This is a
	 * genuine environment limitation of this method as written, not a test
	 * authoring mistake - documented here rather than silently asserting a
	 * false "respects $_GET" claim.
	 */
	public function test_get_subscriptions_ignores_get_params_due_to_cli_sapi_limitation() {
		$_GET['s']       = 'customer-search-term';
		$_GET['paged']   = '3';
		$_GET['orderby'] = 'date';
		$_GET['order']   = 'asc';

		$captured_url = null;
		$capture      = function ( $preempt, $args, $url ) use ( &$captured_url ) {
			$captured_url = $url;

			return $preempt;
		};
		add_filter( 'pre_http_request', $capture, 5, 3 );

		ApiResponseMock::respond_with(
			array(
				'content'        => array(),
				'total_elements' => 0,
				'size'           => 20,
				'total_pages'    => 0,
			)
		);

		$this->call_get_subscriptions();

		remove_filter( 'pre_http_request', $capture, 5 );

		$this->assertNotNull( $captured_url );
		$this->assertStringContainsString( 'size=20', $captured_url );
		$this->assertStringContainsString( 'page=1', $captured_url );
		$this->assertStringNotContainsString( 'search=', $captured_url );
		$this->assertStringNotContainsString( 'sort=', $captured_url );
	}

	/*
	 * ------------------------------------------------------------------
	 * prepare_items()
	 * ------------------------------------------------------------------
	 */

	/**
	 * Test @see WC_Reepay_Subscriptions_Table::prepare_items populates
	 * $this->items from a realistic paginated API response with a
	 * non-empty 'content' array, and sets pagination args from
	 * total_elements/size/total_pages.
	 */
	public function test_prepare_items_happy_path_populates_items() {
		ApiResponseMock::respond_with(
			array(
				'content'        => array(
					array(
						'handle'            => 'sub-happy-1',
						'created'           => '2025-01-01T00:00:00.000Z',
						'next_period_start' => '2025-02-01T00:00:00.000Z',
						'customer'          => 'cust-happy-1',
						'plan'              => 'plan-happy-1',
						'state'             => 'active',
					),
				),
				'total_elements' => 1,
				'size'           => 20,
				'total_pages'    => 1,
			)
		);

		$this->table->prepare_items();

		$this->assertArrayHasKey( 'sub-happy-1', $this->table->items );
		$this->assertSame( 'sub-happy-1', $this->table->items['sub-happy-1']['handle'] );
		$this->assertSame( 'cust-happy-1', $this->table->items['sub-happy-1']['customer_handle'] );
		$this->assertSame( 'plan-happy-1', $this->table->items['sub-happy-1']['plan'] );
	}

	/**
	 * Test @see WC_Reepay_Subscriptions_Table::prepare_items leaves
	 * $this->items empty when the (mocked) API response has no 'content' key
	 * at all.
	 */
	public function test_prepare_items_empty_response_leaves_items_unset() {
		ApiResponseMock::respond_with(
			array(
				'total_elements' => 0,
				'size'           => 20,
				'total_pages'    => 0,
			)
		);

		$this->table->prepare_items();

		$this->assertEmpty( $this->table->items );
	}

	/**
	 * Test @see WC_Reepay_Subscriptions_Table::prepare_items leaves
	 * $this->items empty (no fatal) when the underlying API call throws -
	 * the Exception is caught and logged internally by prepare_items(),
	 * never propagated to the caller.
	 */
	public function test_prepare_items_api_exception_leaves_items_unset_without_fatal() {
		ApiResponseMock::respond_with( array( 'error' => 'invalid_request', 'message' => 'boom' ), 400 );

		$this->table->prepare_items();

		$this->assertEmpty( $this->table->items );
	}

	/*
	 * ------------------------------------------------------------------
	 * format_status() - pure function, every branch
	 * ------------------------------------------------------------------
	 */

	/**
	 * Test @see WC_Reepay_Subscriptions_Table::format_status returns the
	 * 'Cancelled' branch when is_cancelled === true, regardless of 'state'.
	 */
	public function test_format_status_cancelled_when_is_cancelled_true() {
		$result = $this->table->format_status( array( 'is_cancelled' => true, 'state' => 'active' ) );

		$this->assertStringContainsString( 'Cancelled', $result );
		$this->assertStringContainsString( 'canceled', $result );
	}

	/**
	 * Test @see WC_Reepay_Subscriptions_Table::format_status returns the
	 * 'Expired' branch for state === 'expired'.
	 */
	public function test_format_status_expired() {
		$result = $this->table->format_status( array( 'is_cancelled' => false, 'state' => 'expired' ) );

		$this->assertStringContainsString( 'Expired', $result );
	}

	/**
	 * Test @see WC_Reepay_Subscriptions_Table::format_status returns the
	 * 'On Hold' branch for state === 'on_hold'.
	 */
	public function test_format_status_on_hold() {
		$result = $this->table->format_status( array( 'is_cancelled' => false, 'state' => 'on_hold' ) );

		$this->assertStringContainsString( 'On Hold', $result );
	}

	/**
	 * Test @see WC_Reepay_Subscriptions_Table::format_status returns the
	 * 'Is cancelled' branch for state === 'is_cancelled'.
	 */
	public function test_format_status_is_cancelled_state() {
		$result = $this->table->format_status( array( 'is_cancelled' => false, 'state' => 'is_cancelled' ) );

		$this->assertStringContainsString( 'Is cancelled', $result );
	}

	/**
	 * Test @see WC_Reepay_Subscriptions_Table::format_status returns the
	 * 'Trial' branch for state === 'active' with a future 'trial_end' date.
	 */
	public function test_format_status_trial_when_trial_end_in_future() {
		$result = $this->table->format_status(
			array(
				'is_cancelled' => false,
				'state'        => 'active',
				'trial_end'    => ( new DateTime( '+10 days' ) )->format( DateTime::ATOM ),
			)
		);

		$this->assertStringContainsString( 'Trial', $result );
	}

	/**
	 * Test @see WC_Reepay_Subscriptions_Table::format_status returns the
	 * 'Active' branch for state === 'active' with no 'trial_end' key at all.
	 */
	public function test_format_status_active_without_trial_end() {
		$result = $this->table->format_status( array( 'is_cancelled' => false, 'state' => 'active' ) );

		$this->assertStringContainsString( 'Active', $result );
	}

	/**
	 * Test @see WC_Reepay_Subscriptions_Table::format_status falls back to a
	 * generic ucfirst($state) label for any other state string.
	 */
	public function test_format_status_generic_fallback_for_unknown_state() {
		$result = $this->table->format_status( array( 'is_cancelled' => false, 'state' => 'created' ) );

		$this->assertStringContainsString( 'Created', $result );
	}

	/*
	 * ------------------------------------------------------------------
	 * column_*() renderers
	 * ------------------------------------------------------------------
	 */

	/**
	 * Test @see WC_Reepay_Subscriptions_Table::column_plan builds a link to
	 * the Billwerk dashboard containing the plan handle.
	 */
	public function test_column_plan_renders_link_with_handle() {
		$result = $this->table->column_plan( array( 'plan' => 'plan-abc' ) );

		$this->assertStringContainsString( 'plan-abc', $result );
		$this->assertStringContainsString( 'plans/plans/plan/plan-abc', $result );
	}

	/**
	 * Test @see WC_Reepay_Subscriptions_Table::column_handle builds a link to
	 * the Billwerk dashboard for the subscription.
	 */
	public function test_column_handle_renders_link() {
		$result = $this->table->column_handle( array( 'id' => 'sub-abc', 'handle' => 'sub-abc' ) );

		$this->assertStringContainsString( 'subscriptions/subscription/sub-abc', $result );
		$this->assertStringContainsString( 'sub-abc', $result );
	}

	/**
	 * Test @see WC_Reepay_Subscriptions_Table::column_customer_handle builds
	 * a link to the Billwerk dashboard for the customer.
	 */
	public function test_column_customer_handle_renders_link() {
		$result = $this->table->column_customer_handle( array( 'customer_handle' => 'cust-abc' ) );

		$this->assertStringContainsString( 'customers/customers/customer/cust-abc', $result );
		$this->assertStringContainsString( 'cust-abc', $result );
	}

	/**
	 * Test @see WC_Reepay_Subscriptions_Table::column_date formats the item's
	 * 'date' value as "d M Y".
	 */
	public function test_column_date_formats_date() {
		$result = $this->table->column_date( array( 'date' => '2025-03-15T00:00:00.000Z' ) );

		$this->assertSame( '15 Mar 2025', $result );
	}

	/**
	 * Test @see WC_Reepay_Subscriptions_Table::column_next_period_start
	 * formats the item's 'next_period_start' value as "d M Y".
	 */
	public function test_column_next_period_start_formats_date() {
		$result = $this->table->column_next_period_start( array( 'next_period_start' => '2025-04-20T00:00:00.000Z' ) );

		$this->assertSame( '20 Apr 2025', $result );
	}
}
