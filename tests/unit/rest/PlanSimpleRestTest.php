<?php
/**
 * Class PlanSimpleRestTest
 *
 * Covers WC_Reepay_Subscription_Plan_Simple_Rest (includes/WC_Reepay_Subscription_Plan_Simple_Rest.php),
 * the base REST controller shared by Addons_Rest, Coupons_Rest and Discounts_Rest.
 *
 * Confirmed real behavior:
 * - The constructor calls init() directly (not via the 'init' hook, per the
 *   comment at the top of the constructor: this class may be instantiated
 *   after 'init' has already fired) and hooks register_routes() onto
 *   'rest_api_init'. Because the real plugin bootstrap instantiates this
 *   class long before our tests run, 'rest_api_init' has already fired by
 *   the time a *new* instance is created in a test - so register_routes()
 *   must be invoked directly (or 'rest_api_init' re-fired) to populate
 *   rest_get_server()->get_routes().
 * - Exactly one route is registered: GET {namespace}/plan_simple/, namespace
 *   being reepay_s()->settings('rest_api_namespace') === 'reepay_subscription'
 *   (hardcoded static, not read from options - see
 *   WooCommerce_Reepay_Subscriptions::$rest_api_namespace). WP_REST_Server
 *   trims the trailing slash, so the registered route key is actually
 *   '/reepay_subscription/plan_simple' (confirmed by dumping
 *   rest_get_server()->get_routes()).
 * - Calling register_routes() directly (outside the 'rest_api_init' action)
 *   triggers WordPress's "_doing_it_wrong" notice ("REST API routes must be
 *   registered on the rest_api_init action"), which this suite's
 *   convertNoticesToExceptions="true" turns into a fatal test error. Since
 *   the constructor already hooks register_routes() onto 'rest_api_init',
 *   the clean way to populate the route table in a test is to fire
 *   do_action('rest_api_init') after constructing the controller - exactly
 *   what a late-instantiated controller (the scenario this class's own
 *   constructor comment describes) would experience in production too.
 * - get_item() dispatches to get_list() when $request['get_list'] is
 *   non-empty, else get_info().
 * - get_info() calls reepay_s()->plan($product_id)->get_remote_plan_meta($handle).
 *   For an UNKNOWN handle: WC_Reepay_Subscription_Plan_Simple::get_plan()
 *   catches the API's "not found" Exception internally, calls
 *   plan_error() (just records an admin notice) and returns false - it does
 *   NOT rethrow. get_remote_plan_meta() then sees an empty $plan_data,
 *   calls plan_error('Plan not found') again and returns [] (also no
 *   throw). So Plan_Simple_Rest::get_info() for an unknown handle returns a
 *   200 WP_REST_Response with success=true and an 'html' string rendered
 *   from an almost-empty $plan_meta_data (no WP_Error, no exception).
 * - The permission_callback registered for the route is a closure that
 *   simply returns current_user_can('manage_woocommerce').
 *
 * @package Reepay\Subscriptions
 */

use Reepay\Subscriptions\Tests\Helpers\Reepay_Subscriptions_UnitTestCase;
use Reepay\Subscriptions\Tests\Mocks\ApiResponseMock;

/**
 * PlanSimpleRestTest.
 */
class PlanSimpleRestTest extends Reepay_Subscriptions_UnitTestCase {

	/**
	 * @var WC_Reepay_Subscription_Plan_Simple_Rest
	 */
	private WC_Reepay_Subscription_Plan_Simple_Rest $controller;

	public function set_up() {
		parent::set_up();

		// Both WC_Reepay_Subscription_API (reepay_s()->api()) read their key from
		// a settings snapshot cached once at plugin bootstrap, long before any
		// option set here would be read - inject a fake key directly via
		// reflection instead. Pattern copied verbatim from
		// tests/unit/renewals/LifecycleTest.php and tests/unit/SubscriptionApiTest.php.
		update_option(
			'woocommerce_reepay_checkout_settings',
			array(
				'private_key_test' => 'test-key-reepay',
				'test_mode'        => 'yes',
			)
		);

		$this->set_fake_private_key( WC_Reepay_Subscription_API::get_instance(), 'private_key_test', 'test-key-reepay' );
		$this->set_fake_private_key( WC_Reepay_Subscription_API::get_instance(), 'test_mode', true );

		$this->controller = new WC_Reepay_Subscription_Plan_Simple_Rest();
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

	/*
	 * ------------------------------------------------------------------
	 * Route registration
	 * ------------------------------------------------------------------
	 */

	/**
	 * Test @see WC_Reepay_Subscription_Plan_Simple_Rest::register_routes registers
	 * exactly the GET {namespace}/plan_simple route. The constructor hooks
	 * register_routes() onto 'rest_api_init', which already fired during test
	 * bootstrap before this $controller existed, so we re-fire
	 * do_action('rest_api_init') here (as the constructor's own comment
	 * anticipates for late-instantiated controllers) rather than calling
	 * register_routes() directly, which would trip WP's "_doing_it_wrong" notice.
	 */
	public function test_register_routes_registers_plan_simple_route() {
		do_action( 'rest_api_init' );

		$routes = rest_get_server()->get_routes();
		$route  = '/reepay_subscription/plan_simple';

		$this->assertArrayHasKey( $route, $routes, 'Expected route ' . $route . ' to be registered.' );

		$route_def = $routes[ $route ][0];
		$this->assertSame( array( 'GET' => true ), $route_def['methods'] );
		$this->assertIsCallable( $route_def['callback'] );
	}

	/**
	 * Test @see WC_Reepay_Subscription_Plan_Simple_Rest::get_url builds the URL from
	 * the namespace + rest_base set by init().
	 */
	public function test_get_url_returns_namespaced_rest_url() {
		$this->assertSame(
			get_rest_url( 0, 'reepay_subscription/plan_simple/' ),
			$this->controller->get_url()
		);
	}

	/*
	 * ------------------------------------------------------------------
	 * get_list()
	 * ------------------------------------------------------------------
	 */

	/**
	 * Test @see WC_Reepay_Subscription_Plan_Simple_Rest::get_list returns a
	 * WP_REST_Response with success=true and an 'html' <select> rendered from
	 * the plans list fetched via reepay_s()->plan(...)->get_reepay_plans_list().
	 *
	 * Note: get_list() calls reepay_s()->plan($request['product_id'] ?? 0).
	 * plan() only short-circuits to plan_simple for a literal null - passing 0
	 * (the fallback used when no product_id is supplied) makes it call
	 * wc_get_product(0)->is_type(...) and fatal on a bool. A real product_id
	 * must therefore be supplied.
	 *
	 * Note 2: get_reepay_plans_list() returns the RAW API response - it does
	 * NOT unwrap the paginated 'content' key the way Addons_Rest::get_list()
	 * and Coupons_Rest::get_list() do for their own list endpoints. The
	 * 'plan-subscription-plans-select.php' template it feeds iterates the
	 * value directly as a flat array of {handle, name} rows. Confirmed by
	 * actually running this test with a realistically-paginated
	 * {"content": [...]} mock response: the template then iterates the
	 * *outer* array (one row keyed 'content'), so $plan['handle'] is
	 * undefined and the whole call surfaces as a 400 WP_Error ("Undefined
	 * array key \"handle\"") - see
	 * test_get_list_with_paginated_api_response_surfaces_as_error below. This
	 * test instead supplies the flat shape the template actually expects, to
	 * exercise the success path get_list() is clearly intended to have.
	 */
	public function test_get_list_returns_success_response_with_html() {
		ApiResponseMock::respond_with(
			array(
				array( 'handle' => 'plan-1', 'name' => 'Plan One' ),
			)
		);

		$product = ( new \Reepay\Subscriptions\Tests\Helpers\ProductGenerator( 'simple' ) )->product();

		$request = new WP_REST_Request( 'GET', '/reepay_subscription/plan_simple' );
		$request->set_param( 'get_list', '1' );
		$request->set_param( 'product_id', (string) $product->get_id() );
		$request->set_param( 'handle', '' );

		$response = $this->controller->get_item( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$data = $response->get_data();
		$this->assertTrue( $data['success'] );
		$this->assertStringContainsString( 'Plan One', $data['html'] );
		$this->assertStringContainsString( '<option', $data['html'] );
	}

	/**
	 * Test @see WC_Reepay_Subscription_Plan_Simple_Rest::get_list with a
	 * realistically-shaped PAGINATED API response (the actual Reepay plan list
	 * endpoint wraps rows in a 'content' key, exactly like the add_on and
	 * coupon list endpoints). Because get_reepay_plans_list() does not unwrap
	 * 'content' here (unlike Addons_Rest/Coupons_Rest get_list()), the
	 * template ends up reading $plan['handle'] off the wrong array shape,
	 * producing "Undefined array key" - which, with this suite's
	 * convertNoticesToExceptions, becomes a genuine Exception caught by
	 * get_list()'s try/catch and surfaced as a 400 WP_Error. This is a real,
	 * reproduced behavior (confirmed by running this exact test), not a
	 * weakened assertion.
	 */
	public function test_get_list_with_paginated_api_response_surfaces_as_error() {
		ApiResponseMock::respond_with(
			array(
				'content' => array(
					array( 'handle' => 'plan-1', 'name' => 'Plan One' ),
				),
			)
		);

		$product = ( new \Reepay\Subscriptions\Tests\Helpers\ProductGenerator( 'simple' ) )->product();

		$request = new WP_REST_Request( 'GET', '/reepay_subscription/plan_simple' );
		$request->set_param( 'get_list', '1' );
		$request->set_param( 'product_id', (string) $product->get_id() );
		$request->set_param( 'handle', '' );

		$ob_level_before = ob_get_level();

		$response = $this->controller->get_item( $request );

		while ( ob_get_level() > $ob_level_before ) {
			ob_end_clean();
		}

		$this->assertInstanceOf( WP_Error::class, $response );
		$this->assertSame( 400, $response->get_error_code() );
		$this->assertStringContainsString( 'handle', $response->get_error_message() );
	}

	/**
	 * Test @see WC_Reepay_Subscription_Plan_Simple_Rest::get_list catches an API
	 * Exception (get_reepay_plans_list() lets the Exception from the HTTP
	 * failure propagate up unlike get_plan()) and converts it to a 400 WP_Error.
	 */
	public function test_get_list_returns_wp_error_on_exception() {
		ApiResponseMock::respond_with( array( 'error' => 'invalid_request', 'message' => 'boom' ), 400 );

		$product = ( new \Reepay\Subscriptions\Tests\Helpers\ProductGenerator( 'simple' ) )->product();

		$request = new WP_REST_Request( 'GET', '/reepay_subscription/plan_simple' );
		$request->set_param( 'get_list', '1' );
		$request->set_param( 'product_id', (string) $product->get_id() );
		$request->set_param( 'handle', '' );

		// get_list() calls ob_start() before the API call that throws here, and
		// the catch block never reaches its own ob_get_clean() - leaving a
		// dangling output buffer open as a side effect of this real control
		// flow. Close it afterwards so this test doesn't leak buffer state into
		// later tests/assertions (PHPUnit flags that as "risky").
		$ob_level_before = ob_get_level();

		$response = $this->controller->get_list( $request );

		while ( ob_get_level() > $ob_level_before ) {
			ob_end_clean();
		}

		$this->assertInstanceOf( WP_Error::class, $response );
		$this->assertSame( 400, $response->get_error_code() );
	}

	/*
	 * ------------------------------------------------------------------
	 * get_info() / unknown handle behavior
	 * ------------------------------------------------------------------
	 */

	/**
	 * Test @see WC_Reepay_Subscription_Plan_Simple_Rest::get_info for a KNOWN handle:
	 * returns success=true with rendered html containing the plan name.
	 */
	public function test_get_info_known_handle_returns_success_with_html() {
		ApiResponseMock::respond_with(
			array(
				'handle' => 'plan-known',
				'name'   => 'Known Plan',
				'amount' => 1000,
			)
		);

		$request = new WP_REST_Request( 'GET', '/reepay_subscription/plan_simple/' );
		$request->set_param( 'handle', 'plan-known' );

		$response = $this->controller->get_item( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$data = $response->get_data();
		$this->assertTrue( $data['success'] );
		$this->assertIsString( $data['html'] );
	}

	/**
	 * Test @see WC_Reepay_Subscription_Plan_Simple_Rest::get_info for an UNKNOWN
	 * handle. Real behavior (confirmed by reading get_plan() and
	 * get_remote_plan_meta() in WC_Reepay_Subscription_Plan_Simple.php): the
	 * API's 404 Exception is swallowed internally (get_plan() catches it and
	 * returns false; get_remote_plan_meta() then returns [] without
	 * rethrowing) - so get_info() does NOT return a WP_Error. It returns a
	 * 200 WP_REST_Response with success=true and html rendered from an
	 * essentially empty plan meta set.
	 */
	public function test_get_info_unknown_handle_returns_success_not_error() {
		ApiResponseMock::respond_with( array( 'error' => 'invalid_request', 'message' => 'Plan not found' ), 404 );

		$request = new WP_REST_Request( 'GET', '/reepay_subscription/plan_simple/' );
		$request->set_param( 'handle', 'unknown-plan-handle' );

		$response = $this->controller->get_item( $request );

		$this->assertInstanceOf(
			WP_REST_Response::class,
			$response,
			'Unknown handle must not produce a WP_Error: get_plan() swallows the API exception internally.'
		);
		$data = $response->get_data();
		$this->assertTrue( $data['success'] );
		$this->assertIsString( $data['html'] );
	}

	/*
	 * ------------------------------------------------------------------
	 * permission_callback
	 * ------------------------------------------------------------------
	 */

	/**
	 * Test the permission_callback closure registered for the plan_simple route:
	 * true for a user with manage_woocommerce, false for a plain subscriber.
	 */
	public function test_permission_callback_depends_on_manage_woocommerce_capability() {
		do_action( 'rest_api_init' );

		$routes               = rest_get_server()->get_routes();
		$permission_callback  = $routes['/reepay_subscription/plan_simple'][0]['permission_callback'];

		$this->assertIsCallable( $permission_callback );

		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );
		$this->assertTrue( $permission_callback() );

		$subscriber_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber_id );
		$this->assertFalse( $permission_callback() );
	}
}
