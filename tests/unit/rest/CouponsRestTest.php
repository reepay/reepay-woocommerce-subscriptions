<?php
/**
 * Class CouponsRestTest
 *
 * Covers WC_Reepay_Subscription_Coupons_Rest (includes/WC_Reepay_Subscription_Coupons_Rest.php),
 * a child of WC_Reepay_Subscription_Plan_Simple_Rest overriding init()
 * (rest_base = "/coupon/"), get_list() and get_info().
 *
 * Confirmed real behavior:
 * - Route registered: GET /reepay_subscription/coupon.
 * - get_list() calls WC_Reepay_Discounts_And_Coupons::get_coupons(), which
 *   itself calls reepay_s()->api()->request('coupon')['content'] ?? [] -
 *   this DOES make a real outbound API call (contrary to a naive reading of
 *   the class name - "Coupons" doesn't mean "no API calls"). An HTML
 *   <option> list is built directly from the returned rows, with no
 *   wc_get_template() call (unlike get_info()).
 * - get_info() calls WC_Reepay_Discounts_And_Coupons::get_existing_coupon($handle),
 *   which calls reepay_s()->api()->request('coupon/'.$handle) and then
 *   static::get_existing_discount($couponObj['discount']) (a SECOND outbound
 *   API call to discount/{discount_handle}). Both calls must be satisfied for
 *   the happy path.
 * - For an UNKNOWN coupon handle: get_coupons()/get_existing_coupon() have NO
 *   try/catch of their own, so the Exception thrown by
 *   WC_Reepay_Subscription_API::request() on a 404 propagates straight up
 *   into get_list()/get_info()'s own try/catch, which converts it into a 400
 *   WP_Error - the SAME "real API exception -> 400 WP_Error" shape as
 *   Addons_Rest::get_info(), not Plan_Simple_Rest's swallow-and-succeed
 *   behavior.
 *
 * @package Reepay\Subscriptions
 */

use Reepay\Subscriptions\Tests\Helpers\Reepay_Subscriptions_UnitTestCase;
use Reepay\Subscriptions\Tests\Mocks\ApiResponseMock;

/**
 * CouponsRestTest.
 */
class CouponsRestTest extends Reepay_Subscriptions_UnitTestCase {

	/**
	 * @var WC_Reepay_Subscription_Coupons_Rest
	 */
	private WC_Reepay_Subscription_Coupons_Rest $controller;

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

		$this->controller = new WC_Reepay_Subscription_Coupons_Rest();
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
	 * Test @see WC_Reepay_Subscription_Coupons_Rest::init overrides rest_base to
	 * "/coupon/" and register_routes() registers GET /reepay_subscription/coupon.
	 */
	public function test_register_routes_registers_coupon_route() {
		do_action( 'rest_api_init' );

		$routes = rest_get_server()->get_routes();
		$route  = '/reepay_subscription/coupon';

		$this->assertArrayHasKey( $route, $routes, 'Expected route ' . $route . ' to be registered.' );
		$this->assertSame( array( 'GET' => true ), $routes[ $route ][0]['methods'] );
	}

	/*
	 * ------------------------------------------------------------------
	 * get_list()
	 * ------------------------------------------------------------------
	 */

	/**
	 * Test @see WC_Reepay_Subscription_Coupons_Rest::get_list returns a
	 * WP_REST_Response with success=true and an <option> list built directly
	 * from WC_Reepay_Discounts_And_Coupons::get_coupons() - a real (mocked)
	 * API-backed coupon list, not a WC_Coupon lookup.
	 */
	public function test_get_list_returns_success_response_with_html_option_list() {
		ApiResponseMock::respond_with(
			array(
				'content' => array(
					array( 'handle' => 'coupon-1', 'name' => 'Coupon One' ),
				),
			)
		);

		$request = new WP_REST_Request( 'GET', '/reepay_subscription/coupon' );
		$request->set_param( 'get_list', '1' );
		$request->set_param( 'handle', '' );

		$response = $this->controller->get_item( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$data = $response->get_data();
		$this->assertTrue( $data['success'] );
		$this->assertStringContainsString( 'Coupon One', $data['html'] );
		$this->assertStringContainsString( '<option value="coupon-1">', $data['html'] );
	}

	/**
	 * Test @see WC_Reepay_Subscription_Coupons_Rest::get_list catches the real
	 * API Exception from get_coupons() (which has no internal try/catch of its
	 * own, unlike WC_Reepay_Subscription_Addons::get_reepay_addons_list()) and
	 * converts it to a 400 WP_Error.
	 */
	public function test_get_list_returns_wp_error_on_api_exception() {
		ApiResponseMock::respond_with( array( 'error' => 'invalid_request', 'message' => 'boom' ), 400 );

		$request = new WP_REST_Request( 'GET', '/reepay_subscription/coupon' );
		$request->set_param( 'get_list', '1' );
		$request->set_param( 'handle', '' );

		$response = $this->controller->get_item( $request );

		$this->assertInstanceOf( WP_Error::class, $response );
		$this->assertSame( 400, $response->get_error_code() );
	}

	/*
	 * ------------------------------------------------------------------
	 * get_info() / unknown handle behavior
	 * ------------------------------------------------------------------
	 */

	/**
	 * Test @see WC_Reepay_Subscription_Coupons_Rest::get_info for a KNOWN
	 * handle: get_existing_coupon() makes two chained API calls
	 * (coupon/{handle}, then discount/{discount_handle}) - both are served by
	 * the same mocked response here since ApiResponseMock is not one-shot.
	 * Returns success=true with rendered html containing the coupon/discount
	 * name.
	 */
	public function test_get_info_known_handle_returns_success_with_html() {
		ApiResponseMock::respond_with(
			array(
				// Shape serves both the coupon/{handle} and discount/{handle}
				// calls - 'discount' key used by the coupon call, 'name'/
				// 'percentage' etc. used by the discount call.
				'discount'        => 'discount-known',
				'name'            => 'Known Coupon',
				'all_plans'       => true,
				'eligible_plans'  => array(),
				'percentage'      => 10,
				'apply_to'        => array( 'all' ),
			)
		);

		$request = new WP_REST_Request( 'GET', '/reepay_subscription/coupon' );
		$request->set_param( 'handle', 'coupon-known' );

		$response = $this->controller->get_item( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$data = $response->get_data();
		$this->assertTrue( $data['success'] );
		$this->assertStringContainsString( 'Known Coupon', $data['html'] );
	}

	/**
	 * Test @see WC_Reepay_Subscription_Coupons_Rest::get_info for an UNKNOWN
	 * handle. Real behavior (confirmed): get_existing_coupon() has no
	 * try/catch of its own around reepay_s()->api()->request('coupon/'.$handle),
	 * so a mocked 404 response's Exception propagates straight into
	 * get_info()'s own try/catch and is converted into a 400 WP_Error - unlike
	 * Plan_Simple_Rest's swallow-and-succeed behavior.
	 */
	public function test_get_info_unknown_handle_returns_wp_error() {
		ApiResponseMock::respond_with( array( 'error' => 'invalid_request', 'message' => 'Coupon not found' ), 404 );

		$request = new WP_REST_Request( 'GET', '/reepay_subscription/coupon' );
		$request->set_param( 'handle', 'unknown-coupon-handle' );

		$response = $this->controller->get_item( $request );

		$this->assertInstanceOf(
			WP_Error::class,
			$response,
			'get_existing_coupon() has no internal exception-swallowing around its API calls, so an unknown handle must surface as a WP_Error.'
		);
		$this->assertSame( 400, $response->get_error_code() );
	}
}
