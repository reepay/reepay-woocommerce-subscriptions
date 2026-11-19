<?php
/**
 * Class AddonsRestTest
 *
 * Covers WC_Reepay_Subscription_Addons_Rest (includes/WC_Reepay_Subscription_Addons_Rest.php),
 * a child of WC_Reepay_Subscription_Plan_Simple_Rest overriding init()
 * (rest_base = "/addon/"), get_list() and get_info().
 *
 * Confirmed real behavior:
 * - Route registered: GET /reepay_subscription/addon (WP_REST_Server trims the
 *   trailing slash from the '/addon/' rest_base).
 * - get_list() calls WC_Reepay_Subscription_Addons::get_reepay_addons_list(false, true)
 *   (always force-dropping the 'reepay_s_addons_list' option cache - the
 *   $drop_cache=true argument is hardcoded in get_list(), see
 *   WC_Reepay_Subscription_Addons_Rest.php:21) and builds an <option> list
 *   from $addons['content'].
 * - get_info() calls reepay_s()->api()->request("add_on/{handle}") directly
 *   (a REAL outbound HTTP call, unlike Plan_Simple_Rest::get_info() which
 *   goes through get_plan()'s internal try/catch). For an UNKNOWN handle the
 *   mocked 404 response makes WC_Reepay_Subscription_API::request() throw an
 *   Exception (see WC_Reepay_Subscription_API.php:115-160 - only HTTP 2xx/3xx
 *   return normally), which get_info()'s own try/catch converts into a 400
 *   WP_Error. This is a genuinely different "unknown handle" behavior from
 *   Plan_Simple_Rest (success) and matches what SubscriptionApiTest.php
 *   documents about request().
 * - If the response has a 'currency' and the request carries a 'product_id',
 *   get_info() compares it against wc_get_product($product_id)->get_currency()
 *   (or ->get_currency($product) for a reepay_variable_subscriptions product)
 *   and returns a 400 WP_Error ('You are cannot choose addon with another
 *   currency') on mismatch - no exception, a plain WP_Error return.
 *
 * @package Reepay\Subscriptions
 */

use Reepay\Subscriptions\Tests\Helpers\Reepay_Subscriptions_UnitTestCase;
use Reepay\Subscriptions\Tests\Helpers\ProductGenerator;
use Reepay\Subscriptions\Tests\Mocks\ApiResponseMock;

/**
 * AddonsRestTest.
 */
class AddonsRestTest extends Reepay_Subscriptions_UnitTestCase {

	/**
	 * @var WC_Reepay_Subscription_Addons_Rest
	 */
	private WC_Reepay_Subscription_Addons_Rest $controller;

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

		// get_reepay_addons_list() caches its result in this option and only
		// bypasses the cache when $drop_cache=true is passed - which get_list()
		// always does. Still, delete it up front so no state leaks between
		// tests/files that might call it with $drop_cache=false elsewhere.
		delete_option( 'reepay_s_addons_list' );

		$this->controller = new WC_Reepay_Subscription_Addons_Rest();
	}

	public function tear_down() {
		ApiResponseMock::clear();
		delete_option( 'reepay_s_addons_list' );

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

	/*
	 * ------------------------------------------------------------------
	 * Route registration
	 * ------------------------------------------------------------------
	 */

	/**
	 * Test @see WC_Reepay_Subscription_Addons_Rest::init overrides rest_base to
	 * "/addon/" and register_routes() (inherited, hooked onto 'rest_api_init' by
	 * the base constructor) registers GET /reepay_subscription/addon.
	 */
	public function test_register_routes_registers_addon_route() {
		do_action( 'rest_api_init' );

		$routes = rest_get_server()->get_routes();
		$route  = '/reepay_subscription/addon';

		$this->assertArrayHasKey( $route, $routes, 'Expected route ' . $route . ' to be registered.' );
		$this->assertSame( array( 'GET' => true ), $routes[ $route ][0]['methods'] );
	}

	/*
	 * ------------------------------------------------------------------
	 * get_list()
	 * ------------------------------------------------------------------
	 */

	/**
	 * Test @see WC_Reepay_Subscription_Addons_Rest::get_list returns a
	 * WP_REST_Response with success=true and an <option> list built from the
	 * 'content' key of WC_Reepay_Subscription_Addons::get_reepay_addons_list()'s
	 * API response, for a real (mocked) addon record.
	 */
	public function test_get_list_returns_success_response_with_html_option_list() {
		ApiResponseMock::respond_with(
			array(
				'content' => array(
					array(
						'handle'         => 'addon-1',
						'name'           => 'Addon One',
						'all_plans'      => true,
						'eligible_plans' => array(),
						'state'          => 'active',
					),
				),
			)
		);

		$request = new WP_REST_Request( 'GET', '/reepay_subscription/addon' );
		$request->set_param( 'get_list', '1' );
		$request->set_param( 'handle', '' );

		$response = $this->controller->get_item( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$data = $response->get_data();
		$this->assertTrue( $data['success'] );
		$this->assertStringContainsString( 'Addon One', $data['html'] );
		$this->assertStringContainsString( '<option value="addon-1"', $data['html'] );
	}

	/**
	 * Test @see WC_Reepay_Subscription_Addons_Rest::get_list filters out addons
	 * whose state isn't 'active' (get_reepay_addons_list() unsets them before
	 * returning), so an inactive addon never appears in the rendered <option>
	 * list.
	 */
	public function test_get_list_excludes_inactive_addons() {
		ApiResponseMock::respond_with(
			array(
				'content' => array(
					array(
						'handle'         => 'addon-active',
						'name'           => 'Active Addon',
						'all_plans'      => true,
						'eligible_plans' => array(),
						'state'          => 'active',
					),
					array(
						'handle'         => 'addon-inactive',
						'name'           => 'Inactive Addon',
						'all_plans'      => true,
						'eligible_plans' => array(),
						'state'          => 'inactive',
					),
				),
			)
		);

		$request = new WP_REST_Request( 'GET', '/reepay_subscription/addon' );
		$request->set_param( 'get_list', '1' );
		$request->set_param( 'handle', '' );

		$response = $this->controller->get_item( $request );

		$data = $response->get_data();
		$this->assertStringContainsString( 'Active Addon', $data['html'] );
		$this->assertStringNotContainsString( 'Inactive Addon', $data['html'] );
	}

	/**
	 * Test @see WC_Reepay_Subscription_Addons_Rest::get_list catches an API
	 * Exception and converts it to a 400 WP_Error.
	 *
	 * Note: get_reepay_addons_list() has its OWN try/catch that swallows the
	 * API Exception (records an admin notice and returns false), so
	 * get_list() never sees an exception from a failed add_on list request -
	 * $addons becomes false and `$addons['content'] ?? []` safely yields an
	 * empty list, resulting in a 200 success response with just the "Select
	 * add-on" placeholder option, not a WP_Error. This is the real, confirmed
	 * behavior (different from Plan_Simple_Rest::get_list(), which lets the
	 * list-fetch exception propagate).
	 */
	public function test_get_list_with_failing_api_returns_success_with_empty_list() {
		ApiResponseMock::respond_with( array( 'error' => 'invalid_request', 'message' => 'boom' ), 400 );

		$request = new WP_REST_Request( 'GET', '/reepay_subscription/addon' );
		$request->set_param( 'get_list', '1' );
		$request->set_param( 'handle', '' );

		$response = $this->controller->get_item( $request );

		$this->assertInstanceOf(
			WP_REST_Response::class,
			$response,
			'get_reepay_addons_list() swallows its own API exception internally - get_list() must not surface a WP_Error here.'
		);
		$data = $response->get_data();
		$this->assertTrue( $data['success'] );
		$this->assertStringContainsString( 'Select add-on', $data['html'] );
	}

	/*
	 * ------------------------------------------------------------------
	 * get_info() / unknown handle behavior
	 * ------------------------------------------------------------------
	 */

	/**
	 * Test @see WC_Reepay_Subscription_Addons_Rest::get_info for a KNOWN handle
	 * with no product_id (no currency check path): returns success=true with
	 * rendered html containing the addon name.
	 */
	public function test_get_info_known_handle_returns_success_with_html() {
		ApiResponseMock::respond_with(
			array(
				'handle'      => 'addon-known',
				'name'        => 'Known Addon',
				'description' => 'desc',
				'type'        => 'on_off',
				'amount'      => 1000,
				'vat'         => 0,
				'all_plans'   => true,
			)
		);

		$request = new WP_REST_Request( 'GET', '/reepay_subscription/addon' );
		$request->set_param( 'handle', 'addon-known' );

		$response = $this->controller->get_item( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$data = $response->get_data();
		$this->assertTrue( $data['success'] );
		$this->assertStringContainsString( 'Known Addon', $data['html'] );
	}

	/**
	 * Test @see WC_Reepay_Subscription_Addons_Rest::get_info for an UNKNOWN
	 * handle. Real behavior (confirmed): get_info() calls
	 * reepay_s()->api()->request("add_on/{handle}") directly with no internal
	 * try/catch of its own around that call (unlike Plan_Simple_Rest's
	 * get_plan(), which swallows the Exception). A mocked 404 response makes
	 * WC_Reepay_Subscription_API::request() throw, which IS caught by
	 * get_info()'s own try/catch and converted into a 400 WP_Error.
	 */
	public function test_get_info_unknown_handle_returns_wp_error() {
		ApiResponseMock::respond_with( array( 'error' => 'invalid_request', 'message' => 'Add-on not found' ), 404 );

		$request = new WP_REST_Request( 'GET', '/reepay_subscription/addon' );
		$request->set_param( 'handle', 'unknown-addon-handle' );

		$response = $this->controller->get_item( $request );

		$this->assertInstanceOf(
			WP_Error::class,
			$response,
			'Unlike Plan_Simple_Rest, Addons_Rest::get_info() has no internal exception-swallowing around the API call, so an unknown handle must surface as a WP_Error.'
		);
		$this->assertSame( 400, $response->get_error_code() );
	}

	/*
	 * ------------------------------------------------------------------
	 * get_info() currency check
	 * ------------------------------------------------------------------
	 */

	/**
	 * Test @see WC_Reepay_Subscription_Addons_Rest::get_info returns a 400
	 * WP_Error when the addon's currency does not match the given product's
	 * currency.
	 *
	 * Note: get_info()'s currency branch calls $product->get_currency(), a
	 * method core WC_Product/WC_Product_Simple do NOT define (confirmed by
	 * running this test against a 'simple' product: fatal "Call to
	 * undefined method"). Only this plugin's own
	 * WC_Product_Reepay_Simple_Subscription (type 'rp_sub') and
	 * WC_Product_Reepay_Variable_Subscription add get_currency(), so a real
	 * rp_sub product (driven by its _reepay_subscription_currency meta) is
	 * required to exercise this branch at all.
	 */
	public function test_get_info_returns_error_on_currency_mismatch() {
		$product = ( new ProductGenerator( 'rp_sub' ) )->product();
		$product->update_meta_data( '_reepay_subscription_currency', 'DKK' );
		$product->save();

		ApiResponseMock::respond_with(
			array(
				'handle'   => 'addon-currency',
				'name'     => 'Currency Addon',
				'amount'   => 1000,
				'currency' => 'USD',
			)
		);

		$request = new WP_REST_Request( 'GET', '/reepay_subscription/addon' );
		$request->set_param( 'handle', 'addon-currency' );
		$request->set_param( 'product_id', (string) $product->get_id() );

		$response = $this->controller->get_item( $request );

		$this->assertInstanceOf( WP_Error::class, $response );
		$this->assertSame( 400, $response->get_error_code() );
		$this->assertStringContainsString( 'currency', $response->get_error_message() );
	}

	/**
	 * Test @see WC_Reepay_Subscription_Addons_Rest::get_info with matching
	 * currencies and an 'amount' param set: returns success=true with just the
	 * converted amount (divided by 100), skipping the html rendering branch.
	 */
	public function test_get_info_returns_amount_only_when_amount_param_set() {
		$product = ( new ProductGenerator( 'rp_sub' ) )->product();
		$product->update_meta_data( '_reepay_subscription_currency', 'DKK' );
		$product->save();

		ApiResponseMock::respond_with(
			array(
				'handle'   => 'addon-amount',
				'name'     => 'Amount Addon',
				'amount'   => 2500,
				'currency' => 'DKK',
			)
		);

		$request = new WP_REST_Request( 'GET', '/reepay_subscription/addon' );
		$request->set_param( 'handle', 'addon-amount' );
		$request->set_param( 'product_id', (string) $product->get_id() );
		$request->set_param( 'amount', '1' );

		$response = $this->controller->get_item( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$data = $response->get_data();
		$this->assertTrue( $data['success'] );
		$this->assertEquals( 25, $data['amount'] );
		$this->assertArrayNotHasKey( 'html', $data );
	}
}
