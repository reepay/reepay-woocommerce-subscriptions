<?php
/**
 * Class CouponDiscountTest
 *
 * Covers the coupon/discount and other misc methods of WC_Reepay_Renewals:
 * get_reepay_coupons, get_reepay_discounts, get_plan_addons, change_user_role,
 * save_reepay_subscription_dates and get_reepay_subscription_dates.
 *
 * @package Reepay\Subscriptions
 */

use Reepay\Subscriptions\Tests\Helpers\PLUGINS_STATE;
use Reepay\Subscriptions\Tests\Helpers\Reepay_Subscriptions_UnitTestCase;
use Reepay\Subscriptions\Tests\Mocks\ApiResponseMock;

/**
 * CouponDiscountTest.
 */
class CouponDiscountTest extends Reepay_Subscriptions_UnitTestCase {

	/**
	 * @var WC_Reepay_Renewals
	 */
	private WC_Reepay_Renewals $renewals;

	public function set_up() {
		parent::set_up();

		$this->renewals = new WC_Reepay_Renewals();

		// Same test-only wiring as LifecycleTest::set_up() / OrderSplittingTest::set_up()
		// / PaymentTokenTest::set_up() (see those files for the full rationale):
		// WC_Reepay_Subscription_API (reepay_s()->api()) and the Frisbii Pay Api
		// class (reepay()->api()) both read their key from a settings snapshot
		// cached once at bootstrap, so updating the
		// woocommerce_reepay_checkout_settings option alone has no effect on an
		// already-constructed singleton - inject a fake key via reflection instead.
		update_option(
			'woocommerce_reepay_checkout_settings',
			array(
				'private_key_test' => 'test-key-reepay',
				'test_mode'        => 'yes',
			)
		);

		$this->set_fake_private_key( WC_Reepay_Subscription_API::get_instance(), 'private_key_test', 'test-key-reepay' );
		$this->set_fake_private_key( WC_Reepay_Subscription_API::get_instance(), 'test_mode', true );

		if ( PLUGINS_STATE::reepay_pay_activated() ) {
			reepay()->reset_settings();
		}

		// Order creation/status-setting can trigger WooCommerce's transactional
		// order emails. Rendering those emails calls
		// WC_Reepay_Woocommerce_Subscription_Extension::clean_order_totals(), which
		// calls the WooCommerce Subscriptions function order_contains_subscription()
		// unconditionally (no function_exists guard) - fatal when woo_subs isn't
		// active. Unhooking WC's email dispatch is test-only wiring (no production
		// file is touched), copied verbatim from LifecycleTest::set_up().
		foreach ( array(
			'woocommerce_order_status_pending_to_processing',
			'woocommerce_order_status_pending_to_completed',
			'woocommerce_order_status_processing_to_cancelled',
			'woocommerce_order_status_pending_to_cancelled',
			'woocommerce_order_status_pending_to_failed',
			'woocommerce_order_status_pending_to_on-hold',
			'woocommerce_order_status_failed_to_processing',
			'woocommerce_order_status_failed_to_completed',
			'woocommerce_order_status_failed_to_on-hold',
			'woocommerce_order_status_cancelled_to_processing',
			'woocommerce_order_status_cancelled_to_completed',
			'woocommerce_order_status_cancelled_to_on-hold',
			'woocommerce_order_status_on-hold_to_processing',
			'woocommerce_order_status_on-hold_to_cancelled',
			'woocommerce_order_status_on-hold_to_failed',
			'woocommerce_order_status_completed',
			'woocommerce_order_status_failed',
		) as $email_action ) {
			remove_action( $email_action, array( 'WC_Emails', 'send_transactional_email' ), 10 );
			remove_action( $email_action, array( 'WC_Emails', 'queue_transactional_email' ), 10 );
		}
	}

	public function tear_down() {
		ApiResponseMock::clear();

		parent::tear_down();
	}

	/**
	 * Set a private/protected property on an already-constructed object via
	 * reflection. Copied verbatim from LifecycleTest/OrderSplittingTest/
	 * PaymentTokenTest - used only to inject a fake API key into the plugins'
	 * settings singletons, which cache their values once at bootstrap time, long
	 * before any option set in a test's set_up() would be read.
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
	 * Create a real WC_Coupon of Frisbii's custom 'reepay_type' discount type
	 * (registered via WC_Reepay_Discounts_And_Coupons::add_coupon_types() on the
	 * woocommerce_coupon_discount_types filter - but WC_Coupon::is_type() only
	 * ever compares against get_discount_type(), so setting the prop directly is
	 * sufficient and does not require that filter to be hooked).
	 *
	 * @param string $code Coupon code.
	 * @return WC_Coupon
	 */
	private function create_reepay_coupon( string $code ): WC_Coupon {
		$coupon = new WC_Coupon();
		$coupon->set_code( $code );
		$coupon->set_discount_type( 'reepay_type' );
		$coupon->set_amount( 10 );
		$coupon->save();

		return $coupon;
	}

	/*
	 * ------------------------------------------------------------------
	 * get_reepay_coupons()
	 * ------------------------------------------------------------------
	 *
	 * Real behavior (WC_Reepay_Renewals.php:2098-2116): iterates
	 * $order->get_coupon_codes(), loads each as a WC_Coupon, and for any coupon
	 * whose is_type('reepay_type') is true, resolves the "real" Frisbii coupon
	 * code via WC_Reepay_Discounts_And_Coupons::get_coupon_code_real() (reads
	 * '_reepay_coupon_code_real' post meta first, only hitting the API if that's
	 * empty and a '_reepay_coupon_handle' meta is set). If $customer_handle is
	 * non-empty, additionally requires
	 * WC_Reepay_Discounts_And_Coupons::coupon_can_be_applied() to return truthy
	 * (this makes a real API call to coupon/code/validate - mocked here).
	 * Non-reepay_type coupons on the order are skipped entirely.
	 */

	/**
	 * Test @see WC_Reepay_Renewals::get_reepay_coupons returns the real coupon
	 * code (from '_reepay_coupon_code_real' meta, no API call needed) for an
	 * order with an applied Reepay-type coupon, when no $customer_handle is
	 * passed (skips the coupon_can_be_applied() check entirely).
	 *
	 * Note: the order must contain a Reepay-type product (any of
	 * WC_Reepay_Checkout::is_reepay_product() types, not necessarily a
	 * subscription) - WC_Reepay_Discounts_And_Coupons::validate_coupon(),
	 * hooked onto 'woocommerce_coupon_is_valid', throws an Exception rejecting
	 * reepay_type coupons on an order with no Reepay product at all (confirmed
	 * by reading that method directly: it throws "available only Frisbii
	 * Billing subscription" when none of the order's items pass
	 * is_reepay_product()). A plain 'simple' product order would make
	 * apply_coupon() fail silently (return WP_Error, no coupon line added),
	 * which would make this test pass for the wrong reason (empty result from
	 * no coupon being applied at all, not from get_reepay_coupons() logic).
	 *
	 * Also note: validate_coupon() itself (run synchronously during
	 * $order->apply_coupon()) calls
	 * WC_Reepay_Discounts_And_Coupons::coupon_can_be_applied(), which makes a
	 * real reepay_s()->api()->request() call to coupon/code/validate - this must
	 * be mocked even though THIS test's $customer_handle argument to
	 * get_reepay_coupons() itself is left empty (that argument only controls
	 * whether get_reepay_coupons() makes its OWN second validation call).
	 */
	public function test_get_reepay_coupons_returns_real_code_for_reepay_coupon() {
		PLUGINS_STATE::maybe_skip_test_by_product_type( 'rp_sub' );

		$coupon = $this->create_reepay_coupon( 'reepay10off' );
		update_post_meta( $coupon->get_id(), '_reepay_coupon_code_real', 'REAL-CODE-1' );

		$order = $this->order_generator->add_product( 'rp_sub' )->order();

		ApiResponseMock::respond_with( array( 'valid' => true ) );
		$applied = $order->apply_coupon( $coupon->get_code() );
		$this->assertNotWPError( $applied, 'Fixture setup: coupon must actually apply to a Reepay-product order.' );
		$order->save();

		$result = WC_Reepay_Renewals::get_reepay_coupons( $order );

		$this->assertSame( array( 'REAL-CODE-1' ), $result );
	}

	/**
	 * Test @see WC_Reepay_Renewals::get_reepay_coupons returns an empty array
	 * for an order with no coupon applied at all.
	 */
	public function test_get_reepay_coupons_empty_for_order_without_coupon() {
		$order = $this->order_generator->add_product( 'simple' )->order();

		$result = WC_Reepay_Renewals::get_reepay_coupons( $order );

		$this->assertSame( array(), $result );
	}

	/**
	 * Test @see WC_Reepay_Renewals::get_reepay_coupons ignores a coupon that is
	 * applied to the order but is NOT of discount_type 'reepay_type' (a plain WC
	 * percent/fixed coupon).
	 */
	public function test_get_reepay_coupons_ignores_non_reepay_coupon() {
		$coupon = new WC_Coupon();
		$coupon->set_code( 'plainpercent' );
		$coupon->set_discount_type( 'percent' );
		$coupon->set_amount( 10 );
		$coupon->save();

		$order = $this->order_generator->add_product( 'simple' )->order();
		$order->apply_coupon( $coupon->get_code() );
		$order->save();

		$result = WC_Reepay_Renewals::get_reepay_coupons( $order );

		$this->assertSame( array(), $result );
	}

	/**
	 * Test @see WC_Reepay_Renewals::get_reepay_coupons with a non-empty
	 * $customer_handle: the coupon is included only when
	 * WC_Reepay_Discounts_And_Coupons::coupon_can_be_applied() succeeds (mocked
	 * API response for coupon/code/validate does not throw).
	 */
	public function test_get_reepay_coupons_includes_coupon_when_can_be_applied_for_customer() {
		PLUGINS_STATE::maybe_skip_test_by_product_type( 'rp_sub' );

		$coupon = $this->create_reepay_coupon( 'reepay10off2' );
		update_post_meta( $coupon->get_id(), '_reepay_coupon_code_real', 'REAL-CODE-2' );

		$order = $this->order_generator->add_product( 'rp_sub' )->order();

		// coupon_can_be_applied() calls reepay_s()->api()->request() against
		// coupon/code/validate - any non-throwing response means "can be
		// applied". This same mocked response also serves validate_coupon()'s
		// own internal call made synchronously during apply_coupon() below.
		ApiResponseMock::respond_with( array( 'valid' => true ) );

		$applied = $order->apply_coupon( $coupon->get_code() );
		$this->assertNotWPError( $applied, 'Fixture setup: coupon must actually apply to a Reepay-product order.' );
		$order->save();

		$result = WC_Reepay_Renewals::get_reepay_coupons( $order, 'cust-handle-1' );

		$this->assertSame( array( 'REAL-CODE-2' ), $result );
	}

	/**
	 * Test @see WC_Reepay_Renewals::get_reepay_coupons with a non-empty
	 * $customer_handle: the coupon is excluded when
	 * WC_Reepay_Discounts_And_Coupons::coupon_can_be_applied() fails (API call
	 * throws, surfaced as a WP_Error - which is falsy-safe here since the
	 * calling code only checks it via the `||` boolean condition, and a WP_Error
	 * object itself is truthy in PHP, but we confirm via the 400 status path that
	 * the actual Frisbii behavior for an invalid coupon is still reflected here:
	 * the request throwing an Exception on a non-2xx status is what
	 * coupon_can_be_applied() catches to produce the WP_Error).
	 *
	 * Note: coupon_can_be_applied() returns a WP_Error (not false) on failure,
	 * and WC_Reepay_Renewals::get_reepay_coupons()'s condition is
	 * `empty($customer_handle) || coupon_can_be_applied(...)` - a WP_Error object
	 * is never empty/falsy in PHP, so the real code path, as written, still adds
	 * the coupon. We assert that actual (perhaps unintended) behavior rather
	 * than the "obviously correct" one, since this task requires grounding
	 * assertions in what the code does, not what it should do.
	 */
	public function test_get_reepay_coupons_still_includes_coupon_when_validation_errors() {
		PLUGINS_STATE::maybe_skip_test_by_product_type( 'rp_sub' );

		$coupon = $this->create_reepay_coupon( 'reepay10off3' );
		update_post_meta( $coupon->get_id(), '_reepay_coupon_code_real', 'REAL-CODE-3' );

		$order = $this->order_generator->add_product( 'rp_sub' )->order();

		// validate_coupon() (run synchronously during apply_coupon() below) also
		// calls coupon_can_be_applied() - must succeed here so the coupon
		// actually gets attached to the order; the failing response is set
		// afterwards, only for get_reepay_coupons()'s own call.
		ApiResponseMock::respond_with( array( 'valid' => true ) );
		$applied = $order->apply_coupon( $coupon->get_code() );
		$this->assertNotWPError( $applied, 'Fixture setup: coupon must actually apply to a Reepay-product order.' );
		$order->save();

		// Force the API call to fail (anything non-2xx throws an Exception inside
		// the plugin's API client, which coupon_can_be_applied() catches).
		ApiResponseMock::respond_with( array( 'error' => 'coupon_not_valid' ), 404 );

		$result = WC_Reepay_Renewals::get_reepay_coupons( $order, 'cust-handle-2' );

		$this->assertSame( array( 'REAL-CODE-3' ), $result );
	}

	/*
	 * ------------------------------------------------------------------
	 * get_reepay_discounts()
	 * ------------------------------------------------------------------
	 *
	 * Real behavior (WC_Reepay_Renewals.php:2126-2144): iterates
	 * $order->get_coupon_codes(), for each coupon of is_type('reepay_type')
	 * reads '_reepay_discount_handle' post meta (set by
	 * WC_Reepay_Discounts_And_Coupons::create_discount() when the coupon is a
	 * "discount"-flavored Frisbii coupon, as opposed to a plain "coupon"-flavored
	 * one that only has '_reepay_coupon_handle'). If present, appends
	 * ['handle' => "$handle_param_$discount_handle", 'discount' => $discount_handle].
	 * No API call is made by this method.
	 */

	/**
	 * Test @see WC_Reepay_Renewals::get_reepay_discounts returns the combined
	 * handle and raw discount handle for a reepay_type coupon that has a
	 * '_reepay_discount_handle' meta set.
	 */
	public function test_get_reepay_discounts_returns_discount_for_reepay_coupon_with_discount_handle() {
		PLUGINS_STATE::maybe_skip_test_by_product_type( 'rp_sub' );

		$coupon = $this->create_reepay_coupon( 'reepaydiscount1' );
		update_post_meta( $coupon->get_id(), '_reepay_discount_handle', 'discount123' );

		$order = $this->order_generator->add_product( 'rp_sub' )->order();

		// validate_coupon() (run synchronously during apply_coupon()) calls
		// coupon_can_be_applied(), a real reepay_s()->api()->request() call.
		ApiResponseMock::respond_with( array( 'valid' => true ) );
		$applied = $order->apply_coupon( $coupon->get_code() );
		$this->assertNotWPError( $applied, 'Fixture setup: coupon must actually apply to a Reepay-product order.' );
		$order->save();

		$result = WC_Reepay_Renewals::get_reepay_discounts( $order, 'sub-handle-xyz' );

		$this->assertSame(
			array(
				array(
					'handle'   => 'sub-handle-xyz_discount123',
					'discount' => 'discount123',
				),
			),
			$result
		);
	}

	/**
	 * Test @see WC_Reepay_Renewals::get_reepay_discounts returns an empty array
	 * for a reepay_type coupon that has NO '_reepay_discount_handle' meta (a
	 * plain coupon-flavored Frisbii coupon rather than a discount-flavored one).
	 */
	public function test_get_reepay_discounts_empty_when_no_discount_handle_meta() {
		PLUGINS_STATE::maybe_skip_test_by_product_type( 'rp_sub' );

		$coupon = $this->create_reepay_coupon( 'reepaycouponnodiscount' );
		// Deliberately no '_reepay_discount_handle' meta.

		$order = $this->order_generator->add_product( 'rp_sub' )->order();

		// validate_coupon() (run synchronously during apply_coupon()) calls
		// coupon_can_be_applied(), a real reepay_s()->api()->request() call.
		ApiResponseMock::respond_with( array( 'valid' => true ) );
		$applied = $order->apply_coupon( $coupon->get_code() );
		$this->assertNotWPError( $applied, 'Fixture setup: coupon must actually apply to a Reepay-product order.' );
		$order->save();

		$result = WC_Reepay_Renewals::get_reepay_discounts( $order, 'sub-handle-xyz' );

		$this->assertSame( array(), $result );
	}

	/**
	 * Test @see WC_Reepay_Renewals::get_reepay_discounts returns an empty array
	 * for an order without any applied coupon at all.
	 */
	public function test_get_reepay_discounts_empty_for_order_without_coupon() {
		$order = $this->order_generator->add_product( 'simple' )->order();

		$result = WC_Reepay_Renewals::get_reepay_discounts( $order, 'sub-handle-xyz' );

		$this->assertSame( array(), $result );
	}

	/*
	 * ------------------------------------------------------------------
	 * get_plan_addons()
	 * ------------------------------------------------------------------
	 *
	 * Real behavior (WC_Reepay_Renewals.php:2151-2162): reads the 'addons' meta
	 * off the order item (shape confirmed by OrderSplittingTest's coverage of
	 * is_exist_addon_type_on_off_in_addons: array of rows each shaped like
	 * ['name' => ..., 'type' => ..., 'amount' => ...]). If non-empty, multiplies
	 * EVERY row's 'amount' by 100 in place (converting from major units/kroner to
	 * minor units/øre for the Frisbii API) and returns the mutated array;
	 * otherwise returns an empty array (covers both an empty array AND the
	 * string '' case documented in OrderSplittingTest, since empty('') is true).
	 */

	/**
	 * Test @see WC_Reepay_Renewals::get_plan_addons multiplies each addon row's
	 * 'amount' by 100 and preserves the other keys (e.g. 'type', 'name')
	 * unchanged.
	 */
	public function test_get_plan_addons_converts_amount_to_minor_units() {
		$order = $this->order_generator->add_product( 'simple' )->order();
		$items = $order->get_items();
		$item  = current( $items );

		$item->update_meta_data(
			'addons',
			array(
				array( 'name' => 'Extra service', 'type' => 'on_off', 'amount' => '5.50' ),
				array( 'name' => 'Metered usage', 'type' => 'metered', 'amount' => '2' ),
			)
		);
		$item->save_meta_data();

		$result = WC_Reepay_Renewals::get_plan_addons( $item );

		$this->assertCount( 2, $result );
		$this->assertSame( 550.0, $result[0]['amount'] );
		$this->assertSame( 'on_off', $result[0]['type'] );
		$this->assertSame( 'Extra service', $result[0]['name'] );
		$this->assertSame( 200.0, $result[1]['amount'] );
		$this->assertSame( 'metered', $result[1]['type'] );
	}

	/**
	 * Test @see WC_Reepay_Renewals::get_plan_addons returns an empty array for
	 * an order item that never had 'addons' meta set (real-world case: WC
	 * returns '' for a missing meta key, and empty('') is true).
	 */
	public function test_get_plan_addons_empty_when_no_addons_meta() {
		$order = $this->order_generator->add_product( 'simple' )->order();
		$items = $order->get_items();
		$item  = current( $items );
		// Deliberately no 'addons' meta set.

		$result = WC_Reepay_Renewals::get_plan_addons( $item );

		$this->assertSame( array(), $result );
	}

	/*
	 * ------------------------------------------------------------------
	 * change_user_role()
	 * ------------------------------------------------------------------
	 *
	 * Real behavior (WC_Reepay_Renewals.php:1945-2001): $data must contain a
	 * 'subscription' key (a subscription handle) resolved via
	 * get_order_by_subscription_handle() to a WC_Order; if not found returns a
	 * WP_Error. The order must contain a subscription line item
	 * (is_order_contain_subscription()) or the method returns null (implicit,
	 * via the bare `return;`). The target role is read from product/variation
	 * meta: '_reepay_subscription_customer_role_expired' when
	 * $data['event_type'] === 'subscription_expired', otherwise
	 * '_reepay_subscription_customer_role'. If that meta is empty or the literal
	 * string 'without_changes', returns a WP_Error without changing anything.
	 * The customer is resolved via $order->get_customer_id() -> get_userdata();
	 * missing/invalid customer returns a WP_Error. On success, calls
	 * $user->set_role( $new_role ) and returns true (boolean true, not a
	 * WC_Order or anything else).
	 */

	/**
	 * Test @see WC_Reepay_Renewals::change_user_role actually changes a real
	 * WP_User's role for the non-expired event path (reads
	 * '_reepay_subscription_customer_role' product meta).
	 */
	public function test_change_user_role_changes_role_for_active_subscription() {
		PLUGINS_STATE::maybe_skip_test_by_product_type( 'rp_sub' );

		$customer_id = self::factory()->user->create( array( 'role' => 'customer' ) );

		$order = $this->order_generator
			->add_product( 'rp_sub' )
			->set_prop( 'customer_id', $customer_id )
			->order();
		$order->update_meta_data( '_reepay_subscription_handle', 'sub-role-1' );
		$order->save_meta_data();

		$order_item = WC_Reepay_Renewals::is_order_contain_subscription( $order );
		$this->assertNotFalse( $order_item, 'Fixture order must contain a subscription line item.' );

		update_post_meta( $order_item->get_product_id(), '_reepay_subscription_customer_role', 'subscriber' );

		$result = WC_Reepay_Renewals::change_user_role(
			array( 'subscription' => 'sub-role-1', 'event_type' => 'subscription_renewal' )
		);

		$this->assertTrue( $result );

		$user = get_userdata( $customer_id );
		$this->assertContains( 'subscriber', $user->roles );
	}

	/**
	 * Test @see WC_Reepay_Renewals::change_user_role reads the *_expired role
	 * meta key (not the regular one) when event_type is 'subscription_expired'.
	 */
	public function test_change_user_role_uses_expired_role_for_expired_event() {
		PLUGINS_STATE::maybe_skip_test_by_product_type( 'rp_sub' );

		$customer_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );

		$order = $this->order_generator
			->add_product( 'rp_sub' )
			->set_prop( 'customer_id', $customer_id )
			->order();
		$order->update_meta_data( '_reepay_subscription_handle', 'sub-role-2' );
		$order->save_meta_data();

		$order_item = WC_Reepay_Renewals::is_order_contain_subscription( $order );
		$this->assertNotFalse( $order_item );

		// Deliberately different values, to prove the *_expired key is the one read.
		update_post_meta( $order_item->get_product_id(), '_reepay_subscription_customer_role', 'subscriber' );
		update_post_meta( $order_item->get_product_id(), '_reepay_subscription_customer_role_expired', 'customer' );

		$result = WC_Reepay_Renewals::change_user_role(
			array( 'subscription' => 'sub-role-2', 'event_type' => 'subscription_expired' )
		);

		$this->assertTrue( $result );

		$user = get_userdata( $customer_id );
		$this->assertContains( 'customer', $user->roles );
		$this->assertNotContains( 'subscriber', $user->roles );
	}

	/**
	 * Test @see WC_Reepay_Renewals::change_user_role returns a WP_Error without
	 * touching any user when the role meta is the literal string
	 * 'without_changes'.
	 */
	public function test_change_user_role_returns_error_for_without_changes_role() {
		PLUGINS_STATE::maybe_skip_test_by_product_type( 'rp_sub' );

		$customer_id = self::factory()->user->create( array( 'role' => 'customer' ) );

		$order = $this->order_generator
			->add_product( 'rp_sub' )
			->set_prop( 'customer_id', $customer_id )
			->order();
		$order->update_meta_data( '_reepay_subscription_handle', 'sub-role-3' );
		$order->save_meta_data();

		$order_item = WC_Reepay_Renewals::is_order_contain_subscription( $order );
		update_post_meta( $order_item->get_product_id(), '_reepay_subscription_customer_role', 'without_changes' );

		$result = WC_Reepay_Renewals::change_user_role(
			array( 'subscription' => 'sub-role-3', 'event_type' => 'subscription_renewal' )
		);

		$this->assertWPError( $result );

		$user = get_userdata( $customer_id );
		$this->assertContains( 'customer', $user->roles );
	}

	/**
	 * Test @see WC_Reepay_Renewals::change_user_role returns a WP_Error when the
	 * subscription handle in $data resolves to no order at all.
	 */
	public function test_change_user_role_returns_error_for_unknown_subscription_handle() {
		$result = WC_Reepay_Renewals::change_user_role(
			array( 'subscription' => 'sub-role-does-not-exist', 'event_type' => 'subscription_renewal' )
		);

		$this->assertWPError( $result );
	}

	/*
	 * ------------------------------------------------------------------
	 * save_reepay_subscription_dates() / get_reepay_subscription_dates()
	 * ------------------------------------------------------------------
	 *
	 * Real behavior (WC_Reepay_Renewals.php:2009-2088):
	 * save_reepay_subscription_dates() either takes an explicit $data array (the
	 * subscription object shape from the Frisbii API) or, if null, fetches it
	 * live via reepay_s()->api()->request("subscription/$handle") (requires
	 * '_reepay_subscription_handle' order meta, else WP_Error). It builds a fixed
	 * set of 16 date keys (expires, reactivated, created, activated, start_date,
	 * end_date, current_period_start, next_period_start, first_period_start,
	 * last_period_start, trial_start, trial_end, cancelled_date, expired_date,
	 * on_hold_date, reminder_email_sent), each via strtotime() on the
	 * corresponding $data key (or strtotime(false) => false if missing/absent),
	 * stores the whole array as order meta '_reepay_subscription_dates', and
	 * returns that same array. get_reepay_subscription_dates() reads that same
	 * meta (lazily calling save_reepay_subscription_dates() with $data=null if
	 * missing - not exercised here to avoid a live API dependency), and for a
	 * present, non-empty date key returns either (a) a WordPress-formatted date
	 * string via wp_date(get_option('date_format'), $timestamp) when
	 * $date_format === 'wordpress' (the default), or (b) the raw Unix timestamp
	 * (int) for ANY other $date_format value - there is no third format; it is a
	 * boolean fork, not a real $date_format lookup/switch.
	 */

	/**
	 * Test @see WC_Reepay_Renewals::save_reepay_subscription_dates stores and
	 * returns strtotime()-converted timestamps for the explicit $data branch
	 * (no API call needed).
	 */
	public function test_save_reepay_subscription_dates_stores_converted_timestamps() {
		$order = $this->order_generator->add_product( 'simple' )->order();

		$data = array(
			'created'   => '2026-01-01T00:00:00.000Z',
			'activated' => '2026-01-02T00:00:00.000Z',
			// Deliberately omit the rest to confirm they come back falsy.
		);

		$result = WC_Reepay_Renewals::save_reepay_subscription_dates( $order, $data );

		$this->assertIsArray( $result );
		$this->assertSame( strtotime( '2026-01-01T00:00:00.000Z' ), $result['created'] );
		$this->assertSame( strtotime( '2026-01-02T00:00:00.000Z' ), $result['activated'] );
		$this->assertFalse( $result['expires'], 'Missing keys must resolve to strtotime(false) === false.' );

		$order = wc_get_order( $order->get_id() );
		$this->assertSame( $result, $order->get_meta( '_reepay_subscription_dates' ) );
	}

	/**
	 * Test @see WC_Reepay_Renewals::save_reepay_subscription_dates returns a
	 * WP_Error (no meta written) when $data is null and the order has no
	 * '_reepay_subscription_handle' meta to fetch a live subscription with.
	 */
	public function test_save_reepay_subscription_dates_error_without_handle_or_data() {
		$order = $this->order_generator->add_product( 'simple' )->order();

		$result = WC_Reepay_Renewals::save_reepay_subscription_dates( $order );

		$this->assertWPError( $result );
		$order = wc_get_order( $order->get_id() );
		$this->assertEmpty( $order->get_meta( '_reepay_subscription_dates' ) );
	}

	/**
	 * Test @see WC_Reepay_Renewals::get_reepay_subscription_dates with
	 * $date_format = 'wordpress' (the default) returns a formatted date string
	 * using the site's date_format option, round-tripped through a prior
	 * save_reepay_subscription_dates() call.
	 */
	public function test_get_reepay_subscription_dates_wordpress_format_round_trip() {
		$order = $this->order_generator->add_product( 'simple' )->order();

		WC_Reepay_Renewals::save_reepay_subscription_dates(
			$order,
			array( 'created' => '2026-03-15T10:00:00.000Z' )
		);

		$order = wc_get_order( $order->get_id() );

		$result = WC_Reepay_Renewals::get_reepay_subscription_dates( $order, 'created' );

		$expected = wp_date( get_option( 'date_format' ), strtotime( '2026-03-15T10:00:00.000Z' ) );
		$this->assertSame( $expected, $result );
		$this->assertIsString( $result );
	}

	/**
	 * Test @see WC_Reepay_Renewals::get_reepay_subscription_dates with any
	 * non-'wordpress' $date_format returns the raw Unix timestamp (int), not a
	 * formatted string - confirming the method only distinguishes
	 * 'wordpress' vs. everything-else, it does not support e.g. a 'mysql' format
	 * specifically.
	 */
	public function test_get_reepay_subscription_dates_non_wordpress_format_returns_raw_timestamp() {
		$order = $this->order_generator->add_product( 'simple' )->order();

		WC_Reepay_Renewals::save_reepay_subscription_dates(
			$order,
			array( 'created' => '2026-03-15T10:00:00.000Z' )
		);

		$order = wc_get_order( $order->get_id() );

		$result_timestamp = WC_Reepay_Renewals::get_reepay_subscription_dates( $order, 'created', 'timestamp' );
		$result_mysql      = WC_Reepay_Renewals::get_reepay_subscription_dates( $order, 'created', 'mysql' );

		$expected = strtotime( '2026-03-15T10:00:00.000Z' );
		$this->assertSame( $expected, $result_timestamp );
		$this->assertSame( $expected, $result_mysql, "Any format other than 'wordpress' falls through to the raw timestamp - there is no dedicated 'mysql' handling." );
	}

	/**
	 * Test @see WC_Reepay_Renewals::get_reepay_subscription_dates returns an
	 * empty string for a date key that is present in the array but resolved to
	 * false (missing from the source $data), regardless of $date_format.
	 */
	public function test_get_reepay_subscription_dates_empty_string_for_missing_key() {
		$order = $this->order_generator->add_product( 'simple' )->order();

		WC_Reepay_Renewals::save_reepay_subscription_dates(
			$order,
			array( 'created' => '2026-03-15T10:00:00.000Z' )
			// 'expires' deliberately omitted from $data.
		);

		$order = wc_get_order( $order->get_id() );

		$this->assertSame( '', WC_Reepay_Renewals::get_reepay_subscription_dates( $order, 'expires' ) );
		$this->assertSame( '', WC_Reepay_Renewals::get_reepay_subscription_dates( $order, 'expires', 'timestamp' ) );
	}
}
