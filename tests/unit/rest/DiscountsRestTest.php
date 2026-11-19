<?php
/**
 * Class DiscountsRestTest
 *
 * Covers WC_Reepay_Subscription_Discounts_Rest (includes/WC_Reepay_Subscription_Discounts_Rest.php),
 * a child of WC_Reepay_Subscription_Plan_Simple_Rest overriding init()
 * (rest_base = "/discount/") AND overriding get_item() directly - it has
 * neither get_list() nor get_info() of its own (the get_list()/get_info()
 * split from the base class is bypassed entirely for this controller).
 *
 * Confirmed real behavior:
 * - Route registered: GET /reepay_subscription/discount.
 * - get_item() always calls WC_Reepay_Discounts_And_Coupons::get_existing_discount($request['handle']),
 *   regardless of $request['get_list'] - there is no separate "list" mode for
 *   discounts at all (the $_GET['get_list'] param that the base class and
 *   Addons_Rest/Coupons_Rest check is simply never read here).
 * - get_existing_discount() calls reepay_s()->api()->request('discount/'.$handle)
 *   directly - a REAL outbound API call - with NO try/catch of its own. On
 *   success it returns array(['success'=>true, 'discount'=>$discount_data]).
 * - For an UNKNOWN handle: the mocked 404 response's Exception from
 *   WC_Reepay_Subscription_API::request() propagates straight up (no
 *   internal swallow, same shape as Coupons_Rest/Addons_Rest) into
 *   get_item()'s own try/catch here, converted into a 400 WP_Error.
 *
 * @package Reepay\Subscriptions
 */

use Reepay\Subscriptions\Tests\Helpers\Reepay_Subscriptions_UnitTestCase;
use Reepay\Subscriptions\Tests\Mocks\ApiResponseMock;

/**
 * DiscountsRestTest.
 */
class DiscountsRestTest extends Reepay_Subscriptions_UnitTestCase {

	/**
	 * @var WC_Reepay_Subscription_Discounts_Rest
	 */
	private WC_Reepay_Subscription_Discounts_Rest $controller;

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

		$this->controller = new WC_Reepay_Subscription_Discounts_Rest();
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
	 * Test @see WC_Reepay_Subscription_Discounts_Rest::init overrides rest_base
	 * to "/discount/" and register_routes() (inherited from the base class)
	 * registers GET /reepay_subscription/discount, pointing at this class's
	 * own overridden get_item() callback.
	 */
	public function test_register_routes_registers_discount_route() {
		do_action( 'rest_api_init' );

		$routes = rest_get_server()->get_routes();
		$route  = '/reepay_subscription/discount';

		$this->assertArrayHasKey( $route, $routes, 'Expected route ' . $route . ' to be registered.' );
		$this->assertSame( array( 'GET' => true ), $routes[ $route ][0]['methods'] );

		$callback = $routes[ $route ][0]['callback'];
		$this->assertIsArray( $callback );
		$this->assertInstanceOf( WC_Reepay_Subscription_Discounts_Rest::class, $callback[0] );
		$this->assertSame( 'get_item', $callback[1] );
	}

	/*
	 * ------------------------------------------------------------------
	 * get_item() - no separate get_list(): confirmed by reading the class,
	 * exercised here by setting get_list=1 and observing it has no effect.
	 * ------------------------------------------------------------------
	 */

	/**
	 * Test @see WC_Reepay_Subscription_Discounts_Rest::get_item ignores any
	 * 'get_list' request param (unlike the base class and
	 * Addons_Rest/Coupons_Rest) - it always resolves a single discount by
	 * handle via WC_Reepay_Discounts_And_Coupons::get_existing_discount().
	 */
	public function test_get_item_ignores_get_list_param_and_resolves_single_discount() {
		ApiResponseMock::respond_with(
			array(
				'name'       => 'Discount With Get List Param',
				'percentage' => 15,
				'apply_to'   => array( 'all' ),
			)
		);

		$request = new WP_REST_Request( 'GET', '/reepay_subscription/discount' );
		$request->set_param( 'get_list', '1' );
		$request->set_param( 'handle', 'discount-ignore-list' );

		$response = $this->controller->get_item( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$data = $response->get_data();
		$this->assertTrue( $data['success'] );
		$this->assertArrayHasKey( 'discount', $data );
		$this->assertArrayNotHasKey( 'html', $data, 'Discounts_Rest has no html-rendering get_list() branch at all.' );
		$this->assertSame( 'Discount With Get List Param', $data['discount']['_reepay_discount_name'] );
	}

	/**
	 * Test @see WC_Reepay_Subscription_Discounts_Rest::get_item for a KNOWN
	 * handle: returns success=true with the full discount data array (not
	 * rendered html) under the 'discount' key, exactly as
	 * get_existing_discount() shapes it.
	 */
	public function test_get_item_known_handle_returns_success_with_discount_data() {
		ApiResponseMock::respond_with(
			array(
				'name'       => 'Known Discount',
				'percentage' => 20,
				'apply_to'   => array( 'all' ),
			)
		);

		$request = new WP_REST_Request( 'GET', '/reepay_subscription/discount' );
		$request->set_param( 'handle', 'discount-known' );

		$response = $this->controller->get_item( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$data = $response->get_data();
		$this->assertTrue( $data['success'] );
		$this->assertSame( 'reepay_percentage', $data['discount']['_reepay_discount_type'] );
		$this->assertSame( 'Known Discount', $data['discount']['_reepay_discount_name'] );
		$this->assertSame( 'all', $data['discount']['_reepay_discount_apply_to'] );
		$this->assertEquals( 20, $data['discount']['_reepay_discount_amount'] );
		$this->assertSame( 'discount-known', $data['discount']['discount_handle'] );
	}

	/**
	 * Test @see WC_Reepay_Subscription_Discounts_Rest::get_item for an UNKNOWN
	 * handle. Real behavior (confirmed): get_existing_discount() has no
	 * try/catch of its own around reepay_s()->api()->request('discount/'.$handle),
	 * so a mocked 404 response's Exception propagates straight up into
	 * get_item()'s own try/catch here and is converted into a 400 WP_Error.
	 */
	public function test_get_item_unknown_handle_returns_wp_error() {
		ApiResponseMock::respond_with( array( 'error' => 'invalid_request', 'message' => 'Discount not found' ), 404 );

		$request = new WP_REST_Request( 'GET', '/reepay_subscription/discount' );
		$request->set_param( 'handle', 'unknown-discount-handle' );

		$response = $this->controller->get_item( $request );

		$this->assertInstanceOf(
			WP_Error::class,
			$response,
			'get_existing_discount() has no internal exception-swallowing around its API call, so an unknown handle must surface as a WP_Error.'
		);
		$this->assertSame( 400, $response->get_error_code() );
	}
}
