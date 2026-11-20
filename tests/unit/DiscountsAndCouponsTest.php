<?php
/**
 * Class DiscountsAndCouponsTest
 *
 * Covers WC_Reepay_Discounts_And_Coupons (includes/WC_Reepay_Discounts_And_Coupons.php),
 * the class responsible for mapping WooCommerce 'reepay_type' coupons onto
 * Frisbii "discount" and "coupon" API objects, and for gating/calculating
 * discounts at checkout for Reepay products.
 *
 * Confirmed real behavior (read directly from includes/WC_Reepay_Discounts_And_Coupons.php):
 *
 * - get_discount_default_params() (line 78): builds ['name' => ...] from
 *   '_reepay_discount_name' post meta, then ONLY if $coupon->get_amount() >= 1
 *   adds either 'percentage' (raw amount) when '_reepay_discount_type' meta is
 *   'reepay_percentage', or 'amount' (amount * 100, i.e. minor units) when the
 *   meta is 'reepay_fixed_product'. Appends 'description' only if
 *   $coupon->get_description() is non-empty. The $data param is accepted but
 *   NEVER read inside the method body (dead parameter).
 *
 * - get_coupon_default_params() (line 104): returns
 *   ['name' => ..., 'all_plans' => empty($apply_plans), 'eligible_plans' => $apply_plans]
 *   where $apply_plans comes from '_reepay_discount_eligible_plans' post meta
 *   (or [] if unset).
 *
 * - get_existing_discount($handle) (line 118): calls
 *   reepay_s()->api()->request('discount/'.$handle) directly, NO try/catch of
 *   its own - an Exception from an unknown handle propagates straight up.
 *   Maps 'amount'/100 or 'percentage' into '_reepay_discount_type', builds
 *   '_reepay_discount_apply_to' ('all' vs 'custom'), '_reepay_discount_duration'
 *   ('forever' / 'fixed_number' / 'limited_time'), and includes the raw
 *   'discount_handle' = $handle in the returned array.
 *
 * - get_existing_coupon($handle) (line 159): calls
 *   reepay_s()->api()->request('coupon/'.$handle), then chains into
 *   get_existing_discount($couponObj['discount']) - a SECOND API call, no
 *   try/catch either. Merges the discount data with coupon-specific keys
 *   ('_reepay_discount_all_plans', '_reepay_discount_eligible_plans',
 *   'coupon_handle', '_reepay_coupon_max_redemptions', '_reepay_coupon_valid_until').
 *
 * - get_coupon_code_real() (line 181): reads '_reepay_coupon_code_real' meta
 *   first; only calls the API (coupon/{handle}) if that's empty AND
 *   '_reepay_coupon_handle' meta is set, caching the result back into
 *   '_reepay_coupon_code_real'.
 *
 * - create_discount()/update_discount()/create_coupon()/update_coupon()
 *   (lines 206, 245, 271, 315): each wraps its API POST/PUT in try/catch;
 *   on success writes handle/code meta ('_reepay_discount_handle' for
 *   discounts; '_reepay_coupon_handle' + '_reepay_coupon_code_real' for
 *   coupons) and returns the decoded API response array. On Exception, adds
 *   an admin notice, and create_discount()/create_coupon() additionally set
 *   the coupon post status to 'draft'; both return false (no meta written).
 *
 * - is_coupon_applied_for_plans($coupon, $cart_item) (line 418): reads
 *   '_reepay_discount_eligible_plans' and '_reepay_discount_all_plans' meta.
 *   Returns true immediately if all_plans === '1'. If all_plans === '0' AND
 *   there are eligible plans, resolves the cart item's plan handle from
 *   '_reepay_subscription_handle' product (or variation, for
 *   'reepay_variable_subscriptions' type) meta and checks in_array() against
 *   the eligible plans list. Otherwise returns false.
 *
 * - validate_applied_for_plans(WC_Product $product, $apply_to_plans) (line 476):
 *   simpler sibling used by validate_coupon()/validate_coupon_for_product() -
 *   if $apply_to_plans is non-empty, resolves '_reepay_subscription_handle'
 *   product meta directly (no cart_item / variation branch) and checks
 *   in_array(); returns true unconditionally if $apply_to_plans is empty.
 *
 * - apply_discount() (line 444): the discount "types" in this codebase are
 *   NOT generic WC types (percent/fixed_cart/fixed_product) - they are the
 *   plugin's own '_reepay_discount_type' meta values 'reepay_percentage' and
 *   'reepay_fixed_product' (confirmed from get_discount_default_params() AND
 *   directly inside apply_discount()). For 'reepay_percentage':
 *   $discount = $coupon->get_amount() * ($discounting_amount / 100) (i.e.
 *   get_amount() is treated as a 0-100 percentage of the per-line discounting
 *   amount). For 'reepay_fixed_product':
 *   $discount = $coupon->get_amount() / $cart_item['quantity'] (a flat amount
 *   divided evenly across the line's quantity). Both branches additionally
 *   require is_coupon_applied_for_plans() to be true and the product to be a
 *   Reepay product, else $discount is left untouched (the original WC-computed
 *   discount passes through). For a NON-reepay_type coupon applied to a line
 *   that IS a Reepay product, $discount is forced to 0.00 (Reepay products
 *   never receive non-Frisbii coupon discounts).
 *
 * - validate_coupon($valid, $coupon, $discounts) (line 495): for a
 *   'reepay_type' coupon, throws an Exception (code 113, message "...available
 *   only Frisbii Billing subscription") if NONE of $discounts->get_items_to_validate()
 *   are Reepay products. If at least one item IS a Reepay product but the
 *   coupon restricts eligible plans (all_plans === '0' AND non-empty eligible
 *   plans list) and NONE of the items' products match
 *   validate_applied_for_plans(), throws a second Exception (also code 113,
 *   "...not applicable to the products"). Otherwise resolves the coupon's real
 *   Frisbii code via get_coupon_code_real() and calls coupon_can_be_applied();
 *   returns true if that succeeds, else throws using the WP_Error's message.
 *   For a NON-reepay_type coupon, throws (code 113) only if
 *   WC_Reepay_Checkout::only_reepay_products_in_cart('') is true (an all-Reepay
 *   cart may not use non-Frisbii coupons); otherwise returns $valid unchanged.
 *
 * - validate_coupon_for_product() (line 546): for a non-reepay_type coupon
 *   applied against a Reepay product, returns false outright. For a
 *   reepay_type coupon applied against a Reepay product, returns false only
 *   if plan-restricted and validate_applied_for_plans() fails, else true. For
 *   a reepay_type coupon applied against a NON-Reepay product, returns false
 *   unconditionally (reepay_type coupons only ever apply to Reepay products).
 *   Otherwise (non-reepay coupon on a non-Reepay product) returns $valid
 *   unchanged.
 *
 * - coupon_can_be_applied() (line 649, static): already-confirmed from prior
 *   tasks - true on success, WP_Error (NOT false) on any Exception.
 *
 * - add_billwerk_coupon_to_reepay_sub_orders($created_reepay_order_ids, $main_order)
 *   (line 679): for each id in $created_reepay_order_ids, loads it as a
 *   WC_Order and, for every 'reepay_type' coupon item on $main_order, calls
 *   $order->apply_coupon() + calculate_totals(true) + save() on that child
 *   order. No-op entirely if $created_reepay_order_ids is empty/falsy, or if
 *   $main_order has no coupon items at all. NOTE: this hook is commented out
 *   at construction time (line 56: `// add_action(...)`), so it is dead code
 *   in production right now, but the method itself is still directly
 *   callable and is tested as such here.
 *
 * - remove_billwerk_coupon_main_order_after_subscriptions_orders_created($created_reepay_order_ids, $main_order)
 *   (line 701): re-loads $main_order fresh via `new WC_Order($main_order->get_id())`,
 *   checks whether ANY of its line items (via get_product()) is a Reepay
 *   product. If NONE are, removes every 'reepay_type' coupon from the order
 *   (leaves non-reepay coupons alone) and saves. $created_reepay_order_ids is
 *   accepted but NEVER read in the method body (dead parameter - the method
 *   only acts on $main_order itself).
 *
 * - prevent_renewal_coupon_decrement($order_id, $order = null) (line 750):
 *   resolves $order via wc_get_order() if not already a WC_Order; no-ops if
 *   the order has no parent ($order->get_parent_id() falsy) OR the parent has
 *   no '_reepay_subscription_handle' meta. Otherwise calls
 *   $order->get_data_store()->set_recorded_coupon_usage_counts($order, false).
 *   This is an internal WC data-store flag with no public getter, so this
 *   test verifies the observable side effect via get_meta('_recorded_coupon_usage_counts')
 *   after invoking the method (HPOS/legacy store both persist this flag as
 *   order meta under that key).
 *
 * @package Reepay\Subscriptions
 */

use Reepay\Subscriptions\Tests\Helpers\PLUGINS_STATE;
use Reepay\Subscriptions\Tests\Helpers\Reepay_Subscriptions_Ajax_UnitTestCase;
use Reepay\Subscriptions\Tests\Mocks\ApiResponseMock;

/**
 * DiscountsAndCouponsTest.
 */
class DiscountsAndCouponsTest extends Reepay_Subscriptions_Ajax_UnitTestCase {

	/**
	 * @var WC_Reepay_Discounts_And_Coupons
	 */
	private WC_Reepay_Discounts_And_Coupons $subject;

	public function set_up() {
		parent::set_up();

		$this->subject = new WC_Reepay_Discounts_And_Coupons();

		// Same test-only wiring as CouponDiscountTest::set_up() (see that file
		// for the full rationale): inject a fake API key via reflection since
		// the settings singleton caches its value once at bootstrap.
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
		// order emails, which can fatal when woo_subs isn't active. Test-only
		// wiring, copied verbatim from CouponDiscountTest::set_up().
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
	 * reflection. Copied verbatim from CouponDiscountTest/CouponsRestTest.
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
	 * Create a real WC_Coupon of Frisbii's custom 'reepay_type' discount type.
	 * Copied pattern from CouponDiscountTest::create_reepay_coupon().
	 *
	 * @param string $code Coupon code.
	 * @param float  $amount Coupon amount.
	 * @return WC_Coupon
	 */
	private function create_reepay_coupon( string $code, float $amount = 10 ): WC_Coupon {
		$coupon = new WC_Coupon();
		$coupon->set_code( $code );
		$coupon->set_discount_type( 'reepay_type' );
		$coupon->set_amount( $amount );
		$coupon->save();

		return $coupon;
	}

	/*
	 * ------------------------------------------------------------------
	 * 1. get_discount_default_params() / get_coupon_default_params()
	 * ------------------------------------------------------------------
	 */

	/**
	 * Test @see WC_Reepay_Discounts_And_Coupons::get_discount_default_params
	 * returns only ['name' => ''] for a freshly created coupon with no
	 * '_reepay_discount_name'/'_reepay_discount_type' meta and amount 0 - the
	 * amount/percentage/description keys are only ever added conditionally.
	 */
	public function test_get_discount_default_params_minimal_shape_for_fresh_coupon() {
		$coupon = new WC_Coupon();
		$coupon->set_code( 'freshcoupon1' );
		$coupon->set_amount( 0 );
		$coupon->save();

		$result = $this->subject->get_discount_default_params( $coupon );

		$this->assertSame( array( 'name' => '' ), $result );
	}

	/**
	 * Test @see WC_Reepay_Discounts_And_Coupons::get_discount_default_params
	 * adds 'percentage' (the raw coupon amount, NOT divided/multiplied) when
	 * '_reepay_discount_type' meta is 'reepay_percentage' and amount >= 1.
	 */
	public function test_get_discount_default_params_percentage_type() {
		$coupon = new WC_Coupon();
		$coupon->set_code( 'percentcoupon1' );
		$coupon->set_amount( 15 );
		$coupon->save();

		update_post_meta( $coupon->get_id(), '_reepay_discount_name', 'My Percent Discount' );
		update_post_meta( $coupon->get_id(), '_reepay_discount_type', 'reepay_percentage' );

		$result = $this->subject->get_discount_default_params( $coupon );

		// $coupon->get_amount() returns a wc_format_decimal() STRING (e.g.
		// '15'), and get_discount_default_params() assigns it through
		// untouched for the percentage branch - confirmed by reading the
		// method body (no floatval()/(float) cast on that assignment).
		$this->assertSame(
			array(
				'name'       => 'My Percent Discount',
				'percentage' => '15',
			),
			$result
		);
	}

	/**
	 * Test @see WC_Reepay_Discounts_And_Coupons::get_discount_default_params
	 * adds 'amount' = coupon amount * 100 (minor units) when
	 * '_reepay_discount_type' meta is 'reepay_fixed_product' and amount >= 1,
	 * and includes 'description' when the coupon has one.
	 */
	public function test_get_discount_default_params_fixed_product_type_with_description() {
		$coupon = new WC_Coupon();
		$coupon->set_code( 'fixedcoupon1' );
		$coupon->set_amount( 5 );
		$coupon->set_description( 'Five off' );
		$coupon->save();

		update_post_meta( $coupon->get_id(), '_reepay_discount_name', 'My Fixed Discount' );
		update_post_meta( $coupon->get_id(), '_reepay_discount_type', 'reepay_fixed_product' );

		$result = $this->subject->get_discount_default_params( $coupon );

		// $amount * 100 where $amount = $coupon->get_amount() (a numeric
		// string '5') - PHP's string*int arithmetic yields an int (500) here,
		// not a float, confirmed empirically.
		$this->assertSame(
			array(
				'name'        => 'My Fixed Discount',
				'amount'      => 500,
				'description' => 'Five off',
			),
			$result
		);
	}

	/**
	 * Test @see WC_Reepay_Discounts_And_Coupons::get_coupon_default_params
	 * returns all_plans=true and empty eligible_plans for a coupon with no
	 * '_reepay_discount_eligible_plans' meta set.
	 */
	public function test_get_coupon_default_params_all_plans_when_no_eligible_plans_meta() {
		$coupon = new WC_Coupon();
		$coupon->set_code( 'allplanscoupon1' );
		$coupon->save();

		update_post_meta( $coupon->get_id(), '_reepay_discount_name', 'All Plans Coupon' );

		$result = $this->subject->get_coupon_default_params( $coupon );

		$this->assertSame(
			array(
				'name'           => 'All Plans Coupon',
				'all_plans'      => true,
				'eligible_plans' => array(),
			),
			$result
		);
	}

	/**
	 * Test @see WC_Reepay_Discounts_And_Coupons::get_coupon_default_params
	 * returns all_plans=false and the raw eligible_plans list when
	 * '_reepay_discount_eligible_plans' meta is a non-empty array.
	 */
	public function test_get_coupon_default_params_restricted_plans() {
		$coupon = new WC_Coupon();
		$coupon->set_code( 'restrictedplanscoupon1' );
		$coupon->save();

		update_post_meta( $coupon->get_id(), '_reepay_discount_name', 'Restricted Coupon' );
		update_post_meta( $coupon->get_id(), '_reepay_discount_eligible_plans', array( 'plan-a', 'plan-b' ) );

		$result = $this->subject->get_coupon_default_params( $coupon );

		$this->assertSame(
			array(
				'name'           => 'Restricted Coupon',
				'all_plans'      => false,
				'eligible_plans' => array( 'plan-a', 'plan-b' ),
			),
			$result
		);
	}

	/*
	 * ------------------------------------------------------------------
	 * 2. get_existing_discount() / get_existing_coupon() / get_coupon_code_real()
	 * ------------------------------------------------------------------
	 */

	/**
	 * Test @see WC_Reepay_Discounts_And_Coupons::get_existing_discount maps a
	 * percentage-type Frisbii discount response into the expected WC meta
	 * shape, including the raw 'discount_handle'.
	 */
	public function test_get_existing_discount_maps_percentage_discount() {
		ApiResponseMock::respond_with(
			array(
				'name'         => 'Known Discount',
				'percentage'   => 20,
				'apply_to'     => array( 'plan' ),
				'fixed_count'  => 3,
			)
		);

		$result = WC_Reepay_Discounts_And_Coupons::get_existing_discount( 'discount-known-1' );

		$this->assertSame( 'reepay_percentage', $result['_reepay_discount_type'] );
		$this->assertSame( 'Known Discount', $result['_reepay_discount_name'] );
		$this->assertSame( 'custom', $result['_reepay_discount_apply_to'] );
		$this->assertSame( array( 'plan' ), $result['_reepay_discount_apply_to_items'] );
		$this->assertSame( 20, $result['_reepay_discount_amount'] );
		$this->assertSame( 'fixed_number', $result['_reepay_discount_duration'] );
		$this->assertSame( 3, $result['_reepay_discount_fixed_count'] );
		$this->assertSame( 'discount-known-1', $result['discount_handle'] );
	}

	/**
	 * Test @see WC_Reepay_Discounts_And_Coupons::get_existing_discount throws
	 * (propagates) the real Exception from a failed/unknown handle lookup -
	 * it has no try/catch of its own, per the already-confirmed fact.
	 */
	public function test_get_existing_discount_throws_for_unknown_handle() {
		ApiResponseMock::respond_with( array( 'error' => 'invalid_request', 'message' => 'Discount not found' ), 404 );

		$this->expectException( Exception::class );

		WC_Reepay_Discounts_And_Coupons::get_existing_discount( 'unknown-discount-handle' );
	}

	/**
	 * Test @see WC_Reepay_Discounts_And_Coupons::get_existing_coupon chains
	 * into get_existing_discount() (a second API call, same mocked response
	 * serves both since ApiResponseMock is not one-shot) and merges
	 * coupon-specific keys on top.
	 */
	public function test_get_existing_coupon_merges_discount_and_coupon_data() {
		ApiResponseMock::respond_with(
			array(
				// Shape serves both the coupon/{handle} call ('discount',
				// 'name', 'all_plans', 'eligible_plans', 'max_redemptions',
				// 'valid_until') and the chained discount/{handle} call
				// ('name', 'percentage', 'apply_to').
				'discount'        => 'discount-xyz',
				'name'            => 'Known Coupon',
				'all_plans'       => true,
				'eligible_plans'  => array(),
				'percentage'      => 10,
				'apply_to'        => array( 'all' ),
				'max_redemptions' => 50,
				'valid_until'     => '2026-12-31T00:00:00.000Z',
			)
		);

		$result = WC_Reepay_Discounts_And_Coupons::get_existing_coupon( 'coupon-known-1' );

		$this->assertSame( 'Known Coupon', $result['_reepay_discount_name'] );
		$this->assertSame( '1', $result['_reepay_discount_all_plans'] );
		$this->assertSame( array(), $result['_reepay_discount_eligible_plans'] );
		$this->assertSame( 'coupon-known-1', $result['coupon_handle'] );
		$this->assertSame( 50, $result['_reepay_coupon_max_redemptions'] );
		$this->assertSame( '2026-12-31T00:00:00.000Z', $result['_reepay_coupon_valid_until'] );
		// Merged-in discount data, confirming the chained API call's result is present too.
		$this->assertSame( 'reepay_percentage', $result['_reepay_discount_type'] );
		$this->assertSame( 'all', $result['_reepay_discount_apply_to'] );
	}

	/**
	 * Test @see WC_Reepay_Discounts_And_Coupons::get_existing_coupon
	 * propagates the Exception from an unknown handle (no try/catch of its
	 * own, same as get_existing_discount()).
	 */
	public function test_get_existing_coupon_throws_for_unknown_handle() {
		ApiResponseMock::respond_with( array( 'error' => 'invalid_request', 'message' => 'Coupon not found' ), 404 );

		$this->expectException( Exception::class );

		WC_Reepay_Discounts_And_Coupons::get_existing_coupon( 'unknown-coupon-handle' );
	}

	/**
	 * Test @see WC_Reepay_Discounts_And_Coupons::get_coupon_code_real returns
	 * the cached '_reepay_coupon_code_real' meta directly, making NO API call
	 * at all (confirmed by never priming ApiResponseMock - a real outbound
	 * call with no mock registered would fail/throw, so a passing assertion
	 * here proves the meta-first path was taken).
	 */
	public function test_get_coupon_code_real_returns_cached_meta_without_api_call() {
		$coupon = $this->create_reepay_coupon( 'cachedcodecoupon1' );
		update_post_meta( $coupon->get_id(), '_reepay_coupon_code_real', 'CACHED-REAL-CODE' );

		$result = WC_Reepay_Discounts_And_Coupons::get_coupon_code_real( $coupon );

		$this->assertSame( 'CACHED-REAL-CODE', $result );
	}

	/**
	 * Test @see WC_Reepay_Discounts_And_Coupons::get_coupon_code_real falls
	 * back to the API (coupon/{handle}) and caches the result into
	 * '_reepay_coupon_code_real' meta, when the cached meta is empty but
	 * '_reepay_coupon_handle' is set.
	 */
	public function test_get_coupon_code_real_falls_back_to_api_and_caches_result() {
		$coupon = $this->create_reepay_coupon( 'fallbackcodecoupon1' );
		update_post_meta( $coupon->get_id(), '_reepay_coupon_handle', 'coupon-handle-1' );
		// Deliberately no '_reepay_coupon_code_real' meta set.

		ApiResponseMock::respond_with( array( 'code' => 'API-RESOLVED-CODE' ) );

		$result = WC_Reepay_Discounts_And_Coupons::get_coupon_code_real( $coupon );

		$this->assertSame( 'API-RESOLVED-CODE', $result );
		$this->assertSame( 'API-RESOLVED-CODE', get_post_meta( $coupon->get_id(), '_reepay_coupon_code_real', true ) );
	}

	/*
	 * ------------------------------------------------------------------
	 * 3. create_discount() / update_discount() / create_coupon() / update_coupon()
	 * ------------------------------------------------------------------
	 */

	/**
	 * Test @see WC_Reepay_Discounts_And_Coupons::create_discount on success
	 * writes '_reepay_discount_handle' = "discount{$post_id}" meta and returns
	 * the decoded API response.
	 */
	public function test_create_discount_success_writes_handle_meta() {
		$coupon = new WC_Coupon();
		$coupon->set_code( 'creatediscount1' );
		$coupon->set_amount( 10 );
		$coupon->save();

		ApiResponseMock::respond_with( array( 'handle' => 'discount' . $coupon->get_id(), 'name' => 'Created' ) );

		$result = $this->subject->create_discount( $coupon, array() );

		$this->assertIsArray( $result );
		$this->assertSame( 'discount' . $coupon->get_id(), get_post_meta( $coupon->get_id(), '_reepay_discount_handle', true ) );
	}

	/**
	 * Test @see WC_Reepay_Discounts_And_Coupons::create_discount on API
	 * failure returns false and writes NO '_reepay_discount_handle' meta (the
	 * exception path sets the coupon post to 'draft' instead).
	 */
	public function test_create_discount_failure_returns_false_without_meta() {
		$coupon = new WC_Coupon();
		$coupon->set_code( 'creatediscountfail1' );
		$coupon->set_amount( 10 );
		$coupon->save();

		ApiResponseMock::respond_with( array( 'error' => 'invalid_request', 'message' => 'boom' ), 400 );

		$result = $this->subject->create_discount( $coupon, array() );

		$this->assertFalse( $result );
		$this->assertEmpty( get_post_meta( $coupon->get_id(), '_reepay_discount_handle', true ) );
	}

	/**
	 * Test @see WC_Reepay_Discounts_And_Coupons::update_discount on success
	 * returns the decoded API response (it does not write any new meta on
	 * success, only reads the existing '_reepay_discount_handle').
	 */
	public function test_update_discount_success_returns_api_response() {
		$coupon = new WC_Coupon();
		$coupon->set_code( 'updatediscount1' );
		$coupon->set_amount( 10 );
		$coupon->save();
		update_post_meta( $coupon->get_id(), '_reepay_discount_handle', 'discount' . $coupon->get_id() );

		ApiResponseMock::respond_with( array( 'handle' => 'discount' . $coupon->get_id(), 'updated' => true ) );

		$result = $this->subject->update_discount( $coupon );

		$this->assertIsArray( $result );
		$this->assertTrue( $result['updated'] );
	}

	/**
	 * Test @see WC_Reepay_Discounts_And_Coupons::create_coupon on success
	 * writes both '_reepay_coupon_handle' and '_reepay_coupon_code_real' meta
	 * and returns the decoded API response.
	 */
	public function test_create_coupon_success_writes_handle_and_code_meta() {
		$coupon = new WC_Coupon();
		$coupon->set_code( 'createcoupon1' );
		$coupon->set_amount( 10 );
		$coupon->save();

		ApiResponseMock::respond_with( array( 'handle' => 'coupon' . $coupon->get_id(), 'code' => 'createcoupon1' ) );

		$result = $this->subject->create_coupon( $coupon, 'discount' . $coupon->get_id(), array() );

		$this->assertIsArray( $result );
		$this->assertSame( 'coupon' . $coupon->get_id(), get_post_meta( $coupon->get_id(), '_reepay_coupon_handle', true ) );
		$this->assertSame( 'createcoupon1', get_post_meta( $coupon->get_id(), '_reepay_coupon_code_real', true ) );
	}

	/**
	 * Test @see WC_Reepay_Discounts_And_Coupons::create_coupon on API failure
	 * returns false and writes neither handle nor code meta.
	 */
	public function test_create_coupon_failure_returns_false_without_meta() {
		$coupon = new WC_Coupon();
		$coupon->set_code( 'createcouponfail1' );
		$coupon->set_amount( 10 );
		$coupon->save();

		ApiResponseMock::respond_with( array( 'error' => 'invalid_request', 'message' => 'boom' ), 400 );

		$result = $this->subject->create_coupon( $coupon, 'discount' . $coupon->get_id(), array() );

		$this->assertFalse( $result );
		$this->assertEmpty( get_post_meta( $coupon->get_id(), '_reepay_coupon_handle', true ) );
		$this->assertEmpty( get_post_meta( $coupon->get_id(), '_reepay_coupon_code_real', true ) );
	}

	/**
	 * Test @see WC_Reepay_Discounts_And_Coupons::update_coupon on success
	 * returns the decoded API response (reads existing '_reepay_coupon_handle'
	 * meta, writes nothing new on success).
	 */
	public function test_update_coupon_success_returns_api_response() {
		$coupon = new WC_Coupon();
		$coupon->set_code( 'updatecoupon1' );
		$coupon->set_amount( 10 );
		$coupon->save();
		update_post_meta( $coupon->get_id(), '_reepay_coupon_handle', 'coupon' . $coupon->get_id() );

		ApiResponseMock::respond_with( array( 'handle' => 'coupon' . $coupon->get_id(), 'updated' => true ) );

		$result = $this->subject->update_coupon( $coupon );

		$this->assertIsArray( $result );
		$this->assertTrue( $result['updated'] );
	}

	/*
	 * ------------------------------------------------------------------
	 * 4. is_coupon_applied_for_plans() / validate_applied_for_plans()
	 * ------------------------------------------------------------------
	 */

	/**
	 * Test @see WC_Reepay_Discounts_And_Coupons::is_coupon_applied_for_plans
	 * returns true immediately when '_reepay_discount_all_plans' meta is '1',
	 * regardless of the cart item's plan.
	 */
	public function test_is_coupon_applied_for_plans_true_when_all_plans() {
		PLUGINS_STATE::maybe_skip_test_by_product_type( 'rp_sub' );

		$coupon = $this->create_reepay_coupon( 'allplansapply1' );
		update_post_meta( $coupon->get_id(), '_reepay_discount_all_plans', '1' );
		update_post_meta( $coupon->get_id(), '_reepay_discount_eligible_plans', array( 'some-other-plan' ) );

		$product = ( new \Reepay\Subscriptions\Tests\Helpers\ProductGenerator( 'rp_sub' ) )->product();
		update_post_meta( $product->get_id(), '_reepay_subscription_handle', 'plan-not-in-list' );

		$cart_item = array( 'product_id' => $product->get_id(), 'variation_id' => 0 );

		$this->assertTrue( $this->subject->is_coupon_applied_for_plans( $coupon, $cart_item ) );
	}

	/**
	 * Test @see WC_Reepay_Discounts_And_Coupons::is_coupon_applied_for_plans
	 * returns true for a cart item whose product's '_reepay_subscription_handle'
	 * matches one of the coupon's eligible plans (restricted-plans path).
	 */
	public function test_is_coupon_applied_for_plans_true_for_matching_plan() {
		PLUGINS_STATE::maybe_skip_test_by_product_type( 'rp_sub' );

		$coupon = $this->create_reepay_coupon( 'matchingplanapply1' );
		update_post_meta( $coupon->get_id(), '_reepay_discount_all_plans', '0' );
		update_post_meta( $coupon->get_id(), '_reepay_discount_eligible_plans', array( 'plan-match' ) );

		$product = ( new \Reepay\Subscriptions\Tests\Helpers\ProductGenerator( 'rp_sub' ) )->product();
		update_post_meta( $product->get_id(), '_reepay_subscription_handle', 'plan-match' );

		$cart_item = array( 'product_id' => $product->get_id(), 'variation_id' => 0 );

		$this->assertTrue( $this->subject->is_coupon_applied_for_plans( $coupon, $cart_item ) );
	}

	/**
	 * Test @see WC_Reepay_Discounts_And_Coupons::is_coupon_applied_for_plans
	 * returns false for a cart item whose product's plan does NOT match any
	 * of the coupon's restricted eligible plans.
	 */
	public function test_is_coupon_applied_for_plans_false_for_non_matching_plan() {
		PLUGINS_STATE::maybe_skip_test_by_product_type( 'rp_sub' );

		$coupon = $this->create_reepay_coupon( 'nonmatchingplanapply1' );
		update_post_meta( $coupon->get_id(), '_reepay_discount_all_plans', '0' );
		update_post_meta( $coupon->get_id(), '_reepay_discount_eligible_plans', array( 'plan-match' ) );

		$product = ( new \Reepay\Subscriptions\Tests\Helpers\ProductGenerator( 'rp_sub' ) )->product();
		update_post_meta( $product->get_id(), '_reepay_subscription_handle', 'plan-does-not-match' );

		$cart_item = array( 'product_id' => $product->get_id(), 'variation_id' => 0 );

		$this->assertFalse( $this->subject->is_coupon_applied_for_plans( $coupon, $cart_item ) );
	}

	/**
	 * Test @see WC_Reepay_Discounts_And_Coupons::validate_applied_for_plans
	 * returns true unconditionally when $apply_to_plans is empty - no meta
	 * lookup needed for this branch.
	 */
	public function test_validate_applied_for_plans_true_when_no_restriction() {
		PLUGINS_STATE::maybe_skip_test_by_product_type( 'rp_sub' );

		$product = ( new \Reepay\Subscriptions\Tests\Helpers\ProductGenerator( 'rp_sub' ) )->product();

		$this->assertTrue( $this->subject->validate_applied_for_plans( $product, array() ) );
	}

	/**
	 * Test @see WC_Reepay_Discounts_And_Coupons::validate_applied_for_plans
	 * matches directly on the product's own '_reepay_subscription_handle'
	 * meta (no cart_item / variation involved, unlike
	 * is_coupon_applied_for_plans()) - true for a matching plan, false for a
	 * non-matching one.
	 */
	public function test_validate_applied_for_plans_matches_and_rejects_by_plan_handle() {
		PLUGINS_STATE::maybe_skip_test_by_product_type( 'rp_sub' );

		$matching_product = ( new \Reepay\Subscriptions\Tests\Helpers\ProductGenerator( 'rp_sub' ) )->product();
		update_post_meta( $matching_product->get_id(), '_reepay_subscription_handle', 'plan-x' );

		$non_matching_product = ( new \Reepay\Subscriptions\Tests\Helpers\ProductGenerator( 'rp_sub' ) )->product();
		update_post_meta( $non_matching_product->get_id(), '_reepay_subscription_handle', 'plan-y' );

		$this->assertTrue( $this->subject->validate_applied_for_plans( $matching_product, array( 'plan-x' ) ) );
		$this->assertFalse( $this->subject->validate_applied_for_plans( $non_matching_product, array( 'plan-x' ) ) );
	}

	/*
	 * ------------------------------------------------------------------
	 * 5. apply_discount()
	 * ------------------------------------------------------------------
	 *
	 * Confirmed real discount "types" (NOT generic WC coupon types): the
	 * plugin's own '_reepay_discount_type' meta values 'reepay_percentage'
	 * and 'reepay_fixed_product' - read directly inside apply_discount()
	 * (line 456) and matching the exact same two values produced by
	 * get_discount_default_params().
	 */

	/**
	 * Test @see WC_Reepay_Discounts_And_Coupons::apply_discount for a
	 * 'reepay_percentage' type coupon applied to a Reepay product: discount =
	 * coupon amount * (discounting_amount / 100).
	 */
	public function test_apply_discount_percentage_type_calculates_percentage_of_amount() {
		PLUGINS_STATE::maybe_skip_test_by_product_type( 'rp_sub' );

		$coupon = $this->create_reepay_coupon( 'applydiscpercent1', 20 );
		update_post_meta( $coupon->get_id(), '_reepay_discount_type', 'reepay_percentage' );
		update_post_meta( $coupon->get_id(), '_reepay_discount_all_plans', '1' );

		$product = ( new \Reepay\Subscriptions\Tests\Helpers\ProductGenerator( 'rp_sub' ) )->product();

		$cart_item = array( 'product_id' => $product->get_id(), 'variation_id' => 0, 'quantity' => 1 );

		$result = $this->subject->apply_discount( 0.0, 100.0, $cart_item, false, $coupon );

		// 20 * (100 / 100) = 20.
		$this->assertSame( 20.0, $result );
	}

	/**
	 * Test @see WC_Reepay_Discounts_And_Coupons::apply_discount for a
	 * 'reepay_fixed_product' type coupon applied to a Reepay product:
	 * discount = coupon amount / cart item quantity.
	 */
	public function test_apply_discount_fixed_product_type_divides_by_quantity() {
		PLUGINS_STATE::maybe_skip_test_by_product_type( 'rp_sub' );

		$coupon = $this->create_reepay_coupon( 'applydiscfixed1', 30 );
		update_post_meta( $coupon->get_id(), '_reepay_discount_type', 'reepay_fixed_product' );
		update_post_meta( $coupon->get_id(), '_reepay_discount_all_plans', '1' );

		$product = ( new \Reepay\Subscriptions\Tests\Helpers\ProductGenerator( 'rp_sub' ) )->product();

		$cart_item = array( 'product_id' => $product->get_id(), 'variation_id' => 0, 'quantity' => 3 );

		$result = $this->subject->apply_discount( 0.0, 100.0, $cart_item, false, $coupon );

		// 30 / 3 = 10 - $coupon->get_amount() is a numeric string ('30'),
		// and string/int division in PHP yields an int when evenly
		// divisible, confirmed empirically.
		$this->assertSame( 10, $result );
	}

	/**
	 * Test @see WC_Reepay_Discounts_And_Coupons::apply_discount forces the
	 * discount to 0.00 for a NON-reepay_type coupon applied to a Reepay
	 * product (Reepay products never receive non-Frisbii coupon discounts).
	 */
	public function test_apply_discount_forces_zero_for_non_reepay_coupon_on_reepay_product() {
		PLUGINS_STATE::maybe_skip_test_by_product_type( 'rp_sub' );

		$coupon = new WC_Coupon();
		$coupon->set_code( 'plainpercentapply1' );
		$coupon->set_discount_type( 'percent' );
		$coupon->set_amount( 10 );
		$coupon->save();

		$product = ( new \Reepay\Subscriptions\Tests\Helpers\ProductGenerator( 'rp_sub' ) )->product();

		$cart_item = array( 'product_id' => $product->get_id(), 'variation_id' => 0, 'quantity' => 1 );

		$result = $this->subject->apply_discount( 5.0, 100.0, $cart_item, false, $coupon );

		$this->assertSame( 0.00, $result );
	}

	/**
	 * Test @see WC_Reepay_Discounts_And_Coupons::apply_discount leaves the
	 * original WC-computed $discount untouched for a reepay_type coupon when
	 * is_coupon_applied_for_plans() is false (plan restriction not satisfied).
	 */
	public function test_apply_discount_unchanged_when_plan_restriction_not_satisfied() {
		PLUGINS_STATE::maybe_skip_test_by_product_type( 'rp_sub' );

		$coupon = $this->create_reepay_coupon( 'applydiscrestricted1', 20 );
		update_post_meta( $coupon->get_id(), '_reepay_discount_type', 'reepay_percentage' );
		update_post_meta( $coupon->get_id(), '_reepay_discount_all_plans', '0' );
		update_post_meta( $coupon->get_id(), '_reepay_discount_eligible_plans', array( 'plan-required' ) );

		$product = ( new \Reepay\Subscriptions\Tests\Helpers\ProductGenerator( 'rp_sub' ) )->product();
		update_post_meta( $product->get_id(), '_reepay_subscription_handle', 'plan-different' );

		$cart_item = array( 'product_id' => $product->get_id(), 'variation_id' => 0, 'quantity' => 1 );

		$result = $this->subject->apply_discount( 7.5, 100.0, $cart_item, false, $coupon );

		$this->assertSame( 7.5, $result );
	}

	/*
	 * ------------------------------------------------------------------
	 * 6. validate_coupon() / validate_coupon_for_product()
	 * ------------------------------------------------------------------
	 */

	/**
	 * Test @see WC_Reepay_Discounts_And_Coupons::validate_coupon throws an
	 * Exception with code 113 and the "available only Frisbii Billing
	 * subscription" message when a reepay_type coupon is validated against a
	 * WC_Discounts built from an order with NO Reepay product at all.
	 */
	public function test_validate_coupon_throws_for_reepay_coupon_without_reepay_product() {
		$coupon = $this->create_reepay_coupon( 'validatecouponnoproduct1' );

		$order     = $this->order_generator->add_product( 'simple' )->order();
		$discounts = new WC_Discounts( $order );

		try {
			$this->subject->validate_coupon( true, $coupon, $discounts );
			$this->fail( 'Expected an Exception to be thrown.' );
		} catch ( Exception $e ) {
			$this->assertSame( 113, $e->getCode() );
			$this->assertStringContainsString( 'available only Frisbii Billing subscription', $e->getMessage() );
		}
	}

	/**
	 * Test @see WC_Reepay_Discounts_And_Coupons::validate_coupon returns true
	 * for a reepay_type coupon (no plan restriction) validated against a
	 * WC_Discounts built from an order that DOES contain a Reepay product,
	 * once coupon_can_be_applied() succeeds (mocked).
	 */
	public function test_validate_coupon_returns_true_for_valid_reepay_coupon_with_reepay_product() {
		PLUGINS_STATE::maybe_skip_test_by_product_type( 'rp_sub' );

		$coupon = $this->create_reepay_coupon( 'validatecouponvalid1' );
		update_post_meta( $coupon->get_id(), '_reepay_coupon_code_real', 'REAL-VALIDATE-1' );
		update_post_meta( $coupon->get_id(), '_reepay_discount_all_plans', '1' );

		$order     = $this->order_generator->add_product( 'rp_sub' )->order();
		$discounts = new WC_Discounts( $order );

		ApiResponseMock::respond_with( array( 'valid' => true ) );

		$result = $this->subject->validate_coupon( true, $coupon, $discounts );

		$this->assertTrue( $result );
	}

	/**
	 * Test @see WC_Reepay_Discounts_And_Coupons::validate_coupon throws
	 * (code 113, "not applicable to the products") for a reepay_type coupon
	 * restricted to eligible plans when the order's Reepay product's plan
	 * handle does not match any eligible plan.
	 */
	public function test_validate_coupon_throws_for_plan_restricted_coupon_without_matching_plan() {
		PLUGINS_STATE::maybe_skip_test_by_product_type( 'rp_sub' );

		$coupon = $this->create_reepay_coupon( 'validatecouponplanmismatch1' );
		update_post_meta( $coupon->get_id(), '_reepay_discount_all_plans', '0' );
		update_post_meta( $coupon->get_id(), '_reepay_discount_eligible_plans', array( 'plan-required' ) );

		$order = $this->order_generator->add_product( 'rp_sub' )->order();
		foreach ( $order->get_items() as $order_item ) {
			update_post_meta( $order_item->get_product_id(), '_reepay_subscription_handle', 'plan-different' );
		}

		$discounts = new WC_Discounts( $order );

		try {
			$this->subject->validate_coupon( true, $coupon, $discounts );
			$this->fail( 'Expected an Exception to be thrown.' );
		} catch ( Exception $e ) {
			$this->assertSame( 113, $e->getCode() );
			$this->assertStringContainsString( 'not applicable to the products', $e->getMessage() );
		}
	}

	/**
	 * Test @see WC_Reepay_Discounts_And_Coupons::validate_coupon for a
	 * NON-reepay_type coupon: throws (code 113) when the order/cart is
	 * exclusively Reepay products (WC_Reepay_Checkout::only_reepay_products_in_cart('')
	 * is true via an all-Reepay-product WC()->cart).
	 */
	public function test_validate_coupon_throws_for_non_reepay_coupon_when_cart_is_all_reepay() {
		PLUGINS_STATE::maybe_skip_test_by_product_type( 'rp_sub' );

		$coupon = new WC_Coupon();
		$coupon->set_code( 'plainnonreepay1' );
		$coupon->set_discount_type( 'percent' );
		$coupon->set_amount( 10 );
		$coupon->save();

		$product = ( new \Reepay\Subscriptions\Tests\Helpers\ProductGenerator( 'rp_sub' ) )->product();

		WC()->cart->empty_cart();
		WC()->cart->add_to_cart( $product->get_id() );

		$order     = $this->order_generator->add_product( 'rp_sub' )->order();
		$discounts = new WC_Discounts( $order );

		try {
			$this->subject->validate_coupon( true, $coupon, $discounts );
			$this->fail( 'Expected an Exception to be thrown.' );
		} catch ( Exception $e ) {
			$this->assertSame( 113, $e->getCode() );
		} finally {
			WC()->cart->empty_cart();
		}
	}

	/**
	 * Test @see WC_Reepay_Discounts_And_Coupons::validate_coupon for a
	 * NON-reepay_type coupon returns $valid UNCHANGED when the cart is NOT
	 * exclusively Reepay products (a mixed/simple-only cart).
	 */
	public function test_validate_coupon_returns_valid_unchanged_for_non_reepay_coupon_on_mixed_cart() {
		$coupon = new WC_Coupon();
		$coupon->set_code( 'plainnonreepaymixed1' );
		$coupon->set_discount_type( 'percent' );
		$coupon->set_amount( 10 );
		$coupon->save();

		$simple = ( new \Reepay\Subscriptions\Tests\Helpers\ProductGenerator( 'simple' ) )->product();

		WC()->cart->empty_cart();
		WC()->cart->add_to_cart( $simple->get_id() );

		$order     = $this->order_generator->add_product( 'simple' )->order();
		$discounts = new WC_Discounts( $order );

		try {
			$result = $this->subject->validate_coupon( true, $coupon, $discounts );
			$this->assertTrue( $result );

			$result_false = $this->subject->validate_coupon( false, $coupon, $discounts );
			$this->assertFalse( $result_false );
		} finally {
			WC()->cart->empty_cart();
		}
	}

	/**
	 * Test @see WC_Reepay_Discounts_And_Coupons::validate_coupon_for_product
	 * returns false for a NON-reepay_type coupon applied against a Reepay
	 * product.
	 */
	public function test_validate_coupon_for_product_false_for_non_reepay_coupon_on_reepay_product() {
		PLUGINS_STATE::maybe_skip_test_by_product_type( 'rp_sub' );

		$coupon = new WC_Coupon();
		$coupon->set_code( 'forproductplain1' );
		$coupon->set_discount_type( 'percent' );
		$coupon->set_amount( 10 );
		$coupon->save();

		$product = ( new \Reepay\Subscriptions\Tests\Helpers\ProductGenerator( 'rp_sub' ) )->product();

		$result = $this->subject->validate_coupon_for_product( true, $product, $coupon, array() );

		$this->assertFalse( $result );
	}

	/**
	 * Test @see WC_Reepay_Discounts_And_Coupons::validate_coupon_for_product
	 * returns true for a reepay_type coupon (no plan restriction) applied
	 * against a Reepay product.
	 */
	public function test_validate_coupon_for_product_true_for_reepay_coupon_on_reepay_product() {
		PLUGINS_STATE::maybe_skip_test_by_product_type( 'rp_sub' );

		$coupon = $this->create_reepay_coupon( 'forproductreepay1' );
		update_post_meta( $coupon->get_id(), '_reepay_discount_all_plans', '1' );

		$product = ( new \Reepay\Subscriptions\Tests\Helpers\ProductGenerator( 'rp_sub' ) )->product();

		$result = $this->subject->validate_coupon_for_product( false, $product, $coupon, array() );

		$this->assertTrue( $result );
	}

	/**
	 * Test @see WC_Reepay_Discounts_And_Coupons::validate_coupon_for_product
	 * returns false unconditionally for a reepay_type coupon applied against
	 * a NON-Reepay product (reepay_type coupons only ever apply to Reepay
	 * products).
	 */
	public function test_validate_coupon_for_product_false_for_reepay_coupon_on_non_reepay_product() {
		$coupon = $this->create_reepay_coupon( 'forproductreepaynonrp1' );

		$simple = ( new \Reepay\Subscriptions\Tests\Helpers\ProductGenerator( 'simple' ) )->product();

		$result = $this->subject->validate_coupon_for_product( true, $simple, $coupon, array() );

		$this->assertFalse( $result );
	}

	/*
	 * ------------------------------------------------------------------
	 * 7. coupon_can_be_applied()
	 * ------------------------------------------------------------------
	 */

	/**
	 * Test @see WC_Reepay_Discounts_And_Coupons::coupon_can_be_applied returns
	 * boolean true on a successful (mocked) API response.
	 */
	public function test_coupon_can_be_applied_returns_true_on_success() {
		ApiResponseMock::respond_with( array( 'valid' => true ) );

		$result = WC_Reepay_Discounts_And_Coupons::coupon_can_be_applied( 'SOME-CODE' );

		$this->assertTrue( $result );
	}

	/**
	 * Test @see WC_Reepay_Discounts_And_Coupons::coupon_can_be_applied returns
	 * a WP_Error (NOT false) when the underlying API call throws (mocked
	 * 404), per the already-confirmed fact from prior tasks.
	 */
	public function test_coupon_can_be_applied_returns_wp_error_on_failure() {
		ApiResponseMock::respond_with( array( 'error' => 'invalid_request', 'message' => 'Coupon not valid' ), 404 );

		$result = WC_Reepay_Discounts_And_Coupons::coupon_can_be_applied( 'BAD-CODE' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertNotFalse( $result );
	}

	/*
	 * ------------------------------------------------------------------
	 * 8. add_billwerk_coupon_to_reepay_sub_orders() /
	 *    remove_billwerk_coupon_main_order_after_subscriptions_orders_created() /
	 *    prevent_renewal_coupon_decrement()
	 * ------------------------------------------------------------------
	 */

	/**
	 * Test @see WC_Reepay_Discounts_And_Coupons::add_billwerk_coupon_to_reepay_sub_orders
	 * applies every reepay_type coupon found on $main_order onto each child
	 * order id supplied in $created_reepay_order_ids.
	 */
	public function test_add_billwerk_coupon_to_reepay_sub_orders_applies_coupon_to_child_orders() {
		PLUGINS_STATE::maybe_skip_test_by_product_type( 'rp_sub' );

		$coupon = $this->create_reepay_coupon( 'subordercoupon1' );
		update_post_meta( $coupon->get_id(), '_reepay_discount_all_plans', '1' );
		update_post_meta( $coupon->get_id(), '_reepay_coupon_code_real', 'REAL-SUBORDER-1' );

		$main_order = $this->order_generator->add_product( 'rp_sub' )->order();

		ApiResponseMock::respond_with( array( 'valid' => true ) );
		$applied = $main_order->apply_coupon( $coupon->get_code() );
		$this->assertNotWPError( $applied, 'Fixture setup: coupon must actually apply to the main order.' );
		$main_order->save();

		// The child order must itself contain a Reepay product: apply_coupon()
		// internally re-runs validate_coupon() (hooked onto
		// woocommerce_coupon_is_valid), which throws for a reepay_type coupon
		// on an order with no Reepay product at all - that Exception is
		// caught by WC_Abstract_Order::apply_coupon() and surfaces as a
		// (silently ignored, in production) WP_Error, which would make this
		// test fail for the wrong reason (apply_coupon() rejecting the
		// coupon, not add_billwerk_coupon_to_reepay_sub_orders() failing to
		// call it).
		$child_order = $this->order_generator->add_product( 'rp_sub' )->order();

		ApiResponseMock::respond_with( array( 'valid' => true ) );
		$this->subject->add_billwerk_coupon_to_reepay_sub_orders( array( $child_order->get_id() ), $main_order );

		$child_order = wc_get_order( $child_order->get_id() );
		$this->assertContains( $coupon->get_code(), $child_order->get_coupon_codes() );
	}

	/**
	 * Test @see WC_Reepay_Discounts_And_Coupons::add_billwerk_coupon_to_reepay_sub_orders
	 * is a no-op (no exception, no coupon applied anywhere) when
	 * $created_reepay_order_ids is empty.
	 */
	public function test_add_billwerk_coupon_to_reepay_sub_orders_noop_for_empty_ids() {
		PLUGINS_STATE::maybe_skip_test_by_product_type( 'rp_sub' );

		$coupon = $this->create_reepay_coupon( 'subordercoupon2' );
		update_post_meta( $coupon->get_id(), '_reepay_discount_all_plans', '1' );
		update_post_meta( $coupon->get_id(), '_reepay_coupon_code_real', 'REAL-SUBORDER-2' );

		$main_order = $this->order_generator->add_product( 'rp_sub' )->order();

		ApiResponseMock::respond_with( array( 'valid' => true ) );
		$main_order->apply_coupon( $coupon->get_code() );
		$main_order->save();

		// Should not throw and should simply do nothing.
		$this->subject->add_billwerk_coupon_to_reepay_sub_orders( array(), $main_order );

		$this->assertTrue( true, 'No exception thrown for empty $created_reepay_order_ids.' );
	}

	/**
	 * Test @see WC_Reepay_Discounts_And_Coupons::remove_billwerk_coupon_main_order_after_subscriptions_orders_created
	 * removes a reepay_type coupon from the main order when NONE of its line
	 * items are Reepay products (a pure non-Reepay order that nonetheless
	 * picked up a Frisbii coupon, e.g. via a mixed cart split).
	 */
	public function test_remove_billwerk_coupon_removes_reepay_coupon_when_no_reepay_product() {
		$coupon = new WC_Coupon();
		$coupon->set_code( 'removecoupon1' );
		$coupon->set_discount_type( 'reepay_type' );
		$coupon->set_amount( 10 );
		$coupon->save();

		$order = $this->order_generator->add_product( 'simple' )->order();

		// Bypass validate_coupon() entirely (which would reject a reepay_type
		// coupon on a non-Reepay order) by adding the coupon as a raw order
		// item, mirroring how a coupon might land on a post-split order
		// without going through the cart/checkout validation filters again.
		$item = new WC_Order_Item_Coupon();
		$item->set_code( $coupon->get_code() );
		$item->set_discount( 1 );
		$order->add_item( $item );
		$order->save();

		$this->assertContains( $coupon->get_code(), $order->get_coupon_codes() );

		$this->subject->remove_billwerk_coupon_main_order_after_subscriptions_orders_created( array(), $order );

		$order = wc_get_order( $order->get_id() );
		$this->assertNotContains( $coupon->get_code(), $order->get_coupon_codes() );
	}

	/**
	 * Test @see WC_Reepay_Discounts_And_Coupons::remove_billwerk_coupon_main_order_after_subscriptions_orders_created
	 * leaves the coupon in place when the main order DOES contain a Reepay
	 * product.
	 */
	public function test_remove_billwerk_coupon_keeps_coupon_when_has_reepay_product() {
		PLUGINS_STATE::maybe_skip_test_by_product_type( 'rp_sub' );

		$coupon = $this->create_reepay_coupon( 'keepcoupon1' );
		update_post_meta( $coupon->get_id(), '_reepay_discount_all_plans', '1' );
		update_post_meta( $coupon->get_id(), '_reepay_coupon_code_real', 'REAL-KEEP-1' );

		$order = $this->order_generator->add_product( 'rp_sub' )->order();

		ApiResponseMock::respond_with( array( 'valid' => true ) );
		$applied = $order->apply_coupon( $coupon->get_code() );
		$this->assertNotWPError( $applied, 'Fixture setup: coupon must actually apply to a Reepay-product order.' );
		$order->save();

		$this->subject->remove_billwerk_coupon_main_order_after_subscriptions_orders_created( array(), $order );

		$order = wc_get_order( $order->get_id() );
		$this->assertContains( $coupon->get_code(), $order->get_coupon_codes() );
	}

	/**
	 * Test @see WC_Reepay_Discounts_And_Coupons::prevent_renewal_coupon_decrement
	 * sets the order's recorded-coupon-usage-counts flag to false (observable
	 * via WC_Order::get_recorded_coupon_usage_counts(), the CRUD prop backed
	 * by '_recorded_coupon_usage_counts' meta - NOT readable via the generic
	 * get_meta() accessor, since it is a dedicated order prop rather than
	 * custom/ad-hoc meta) for a renewal (child) order whose parent carries a
	 * '_reepay_subscription_handle'.
	 */
	public function test_prevent_renewal_coupon_decrement_resets_flag_for_subscription_renewal() {
		$parent = $this->order_generator->add_product( 'simple' )->order();
		$parent->update_meta_data( '_reepay_subscription_handle', 'sub-decrement-1' );
		$parent->save();

		$child = wc_create_order();
		$child->set_parent_id( $parent->get_id() );
		$child->set_recorded_coupon_usage_counts( true );
		$child->save();

		$this->assertTrue( wc_get_order( $child->get_id() )->get_recorded_coupon_usage_counts(), 'Fixture setup: flag must start as true.' );

		$this->subject->prevent_renewal_coupon_decrement( $child->get_id(), $child );

		$child = wc_get_order( $child->get_id() );
		$this->assertFalse( $child->get_recorded_coupon_usage_counts() );
	}

	/**
	 * Test @see WC_Reepay_Discounts_And_Coupons::prevent_renewal_coupon_decrement
	 * is a no-op (flag left untouched) for an order with no parent at all.
	 */
	public function test_prevent_renewal_coupon_decrement_noop_for_order_without_parent() {
		$order = $this->order_generator->add_product( 'simple' )->order();
		$order->set_recorded_coupon_usage_counts( true );
		$order->save();

		$this->subject->prevent_renewal_coupon_decrement( $order->get_id(), $order );

		$order = wc_get_order( $order->get_id() );
		$this->assertTrue( $order->get_recorded_coupon_usage_counts(), 'Flag must remain untouched for an order without a parent.' );
	}

	/**
	 * Test @see WC_Reepay_Discounts_And_Coupons::prevent_renewal_coupon_decrement
	 * is a no-op when the parent order has NO '_reepay_subscription_handle'
	 * meta (a plain, non-Frisbii-subscription parent/child relationship).
	 */
	public function test_prevent_renewal_coupon_decrement_noop_when_parent_has_no_subscription_handle() {
		$parent = $this->order_generator->add_product( 'simple' )->order();
		// Deliberately no '_reepay_subscription_handle' meta on the parent.

		$child = wc_create_order();
		$child->set_parent_id( $parent->get_id() );
		$child->set_recorded_coupon_usage_counts( true );
		$child->save();

		$this->subject->prevent_renewal_coupon_decrement( $child->get_id(), $child );

		$child = wc_get_order( $child->get_id() );
		$this->assertTrue( $child->get_recorded_coupon_usage_counts(), 'Flag must remain untouched when parent has no subscription handle.' );
	}

	/*
	 * ------------------------------------------------------------------
	 * add_coupon_text_field() - unserialize() without objects
	 * ------------------------------------------------------------------
	 */

	/**
	 * Test @see WC_Reepay_Discounts_And_Coupons::add_coupon_text_field
	 * unserializes the '_reepay_discount_apply_to_items' and
	 * '_reepay_discount_eligible_plans' coupon meta with
	 * allowed_classes => false: plain values survive, but a serialized object
	 * is never instantiated (no __wakeup()), it becomes __PHP_Incomplete_Class.
	 *
	 * get_post_meta( $id ) without a key returns the RAW (still serialized)
	 * values, which is why the method calls unserialize() itself. The
	 * template is swapped for an empty file via the 'wc_get_template' filter
	 * so only the data passed to it is inspected.
	 */
	public function test_add_coupon_text_field_does_not_instantiate_serialized_objects() {
		DiscountsAndCouponsTest_Wakeup_Probe::$woken = false;

		$coupon_id = self::factory()->post->create( array( 'post_type' => 'shop_coupon' ) );
		update_post_meta( $coupon_id, '_reepay_discount_apply_to_items', array( 'plan-a', new DiscountsAndCouponsTest_Wakeup_Probe() ) );
		update_post_meta( $coupon_id, '_reepay_discount_eligible_plans', array( 'plan-b', new DiscountsAndCouponsTest_Wakeup_Probe() ) );

		$GLOBALS['post'] = get_post( $coupon_id );

		ApiResponseMock::respond_with( array( 'content' => array() ) );

		$empty_template = wp_tempnam( 'reepay-empty-template' );
		$template_args  = null;
		$swap_template  = function ( $template, $template_name, $args ) use ( &$template_args, $empty_template ) {
			if ( 'discounts-and-coupons-fields.php' !== $template_name ) {
				return $template;
			}

			$template_args = $args;

			return $empty_template;
		};
		add_filter( 'wc_get_template', $swap_template, 10, 3 );

		ob_start();
		$this->subject->add_coupon_text_field();
		ob_end_clean();

		remove_filter( 'wc_get_template', $swap_template, 10 );
		unlink( $empty_template );
		unset( $GLOBALS['post'] );

		$this->assertFalse( DiscountsAndCouponsTest_Wakeup_Probe::$woken, 'A serialized object must not be instantiated.' );
		$this->assertIsArray( $template_args );

		foreach ( array(
			'_reepay_discount_apply_to_items' => 'plan-a',
			'_reepay_discount_eligible_plans' => 'plan-b',
		) as $meta_key => $plain_value ) {
			$value = $template_args['meta'][ $meta_key ][0];
			$this->assertSame( $plain_value, $value[0], $meta_key );
			$this->assertInstanceOf( '__PHP_Incomplete_Class', $value[1], $meta_key );
		}
	}
}

/**
 * Records whether unserialize() instantiated it (via __wakeup()).
 */
class DiscountsAndCouponsTest_Wakeup_Probe {
	/**
	 * @var bool
	 */
	public static bool $woken = false;

	public function __wakeup() {
		self::$woken = true;
	}
}
