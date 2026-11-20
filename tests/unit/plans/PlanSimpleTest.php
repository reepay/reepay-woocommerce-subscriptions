<?php
/**
 * Class PlanSimpleTest
 *
 * Covers WC_Reepay_Subscription_Plan_Simple's request-driven methods:
 * save_subscription_meta(), get_subscription_handle_from_request(), and
 * is_reepay_product_saving(). All three read directly from $_POST/$_REQUEST,
 * so this extends Reepay_Subscriptions_Ajax_UnitTestCase (resets those
 * superglobals in tear_down()).
 *
 * @package Reepay\Subscriptions
 */

use Reepay\Subscriptions\Tests\Helpers\Reepay_Subscriptions_Ajax_UnitTestCase;
use Reepay\Subscriptions\Tests\Mocks\ApiResponseMock;

/**
 * PlanSimpleTest.
 */
class PlanSimpleTest extends Reepay_Subscriptions_Ajax_UnitTestCase {

	/**
	 * @var WC_Reepay_Subscription_Plan_Simple
	 */
	private WC_Reepay_Subscription_Plan_Simple $plan;

	public function set_up() {
		parent::set_up();

		$this->plan = new WC_Reepay_Subscription_Plan_Simple();

		// save_subscription_meta() only reaches the customer-role branches
		// (the part under test) after get_plan() -> WC_Reepay_Subscription_API::request()
		// succeeds (see save_remote_plan() -> get_remote_plan_meta() -> get_plan(),
		// WC_Reepay_Subscription_Plan_Simple.php:470-321). That request bails out
		// with an Exception *before* calling wp_remote_request() unless a private
		// key is configured, which would make ApiResponseMock unreachable.
		// WC_Reepay_Subscription_API reads its key from a settings snapshot cached
		// once at plugin bootstrap, long before any option set here would be read,
		// so the option alone has no effect on the already-constructed singleton -
		// inject a fake key directly via reflection instead. Copied verbatim from
		// tests/unit/renewals/LifecycleTest.php::set_up(). Test-only wiring, no
		// production file touched.
		update_option(
			'woocommerce_reepay_checkout_settings',
			array(
				'private_key_test' => 'test-key-reepay',
				'test_mode'        => 'yes',
			)
		);

		$this->set_fake_private_key( WC_Reepay_Subscription_API::get_instance(), 'private_key_test', 'test-key-reepay' );
		$this->set_fake_private_key( WC_Reepay_Subscription_API::get_instance(), 'test_mode', true );

		// save_customer_role_meta() only saves when the current user can
		// 'promote_users' and the role is in get_editable_roles() - act as an
		// administrator, and load get_editable_roles() (wp-admin only).
		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	public function tear_down() {
		ApiResponseMock::clear();

		parent::tear_down();
	}

	/**
	 * Set a private/protected property on an already-constructed object via
	 * reflection. Used only to inject a fake API key into the plugin's settings
	 * singleton, which caches its values once at bootstrap time - long before
	 * any option set in set_up() would be read. Copied verbatim from
	 * tests/unit/renewals/LifecycleTest.php.
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
	 * Create a real 'reepay_simple_subscriptions' product (via
	 * WC_Product_Reepay_Simple_Subscription) with a non-AUTO-DRAFT title, so
	 * save_subscription_meta()'s get_the_title()/strpos('AUTO-DRAFT') guard
	 * (WC_Reepay_Subscription_Plan_Simple.php:643-650) does not short-circuit.
	 *
	 * @return int product/post ID.
	 */
	private function create_real_product_post(): int {
		$product = ( new \Reepay\Subscriptions\Tests\Helpers\ProductGenerator( 'rp_sub' ) )->product();
		$product->set_name( 'Real Plan Simple Product' );
		$product->save();

		return $product->get_id();
	}

	/**
	 * Canned API response used to satisfy get_plan()'s round trip through
	 * save_remote_plan() so save_subscription_meta() can reach the
	 * customer-role branches under test.
	 */
	private function mock_valid_plan_response(): void {
		ApiResponseMock::respond_with(
			array(
				'handle' => 'plan-handle-1',
				'amount' => 1000,
				'name'   => 'Plan One',
			)
		);
	}

	/*
	 * ------------------------------------------------------------------
	 * save_subscription_meta() - customer-role / customer-role-expired
	 * ------------------------------------------------------------------
	 *
	 * Real behavior (WC_Reepay_Subscription_Plan_Simple::save_customer_role_meta()):
	 *
	 *   if ( ! current_user_can( 'promote_users' ) ) return;
	 *   if ( '' === $role || 'without_changes' === $role ) {
	 *       delete_post_meta( $post_id, $meta_key );
	 *   } elseif ( array_key_exists( $role, get_editable_roles() ) ) {
	 *       update_post_meta( $post_id, $meta_key, $role );
	 *   }
	 *
	 * Used for both '_reepay_subscription_customer_role' and
	 * '_reepay_subscription_customer_role_expired'. The literal string
	 * 'without_changes' actively deletes the meta key; a real, editable role
	 * calls update_post_meta. set_up() logs in as an administrator so the
	 * capability check passes.
	 */

	/**
	 * Test @see WC_Reepay_Subscription_Plan_Simple::save_subscription_meta
	 * calls update_post_meta with the sanitized role when a real (non
	 * 'without_changes') role value is posted.
	 */
	public function test_save_subscription_meta_updates_role_for_real_value() {
		$post_id = $this->create_real_product_post();
		$this->mock_valid_plan_response();

		$_REQUEST['product-type']            = 'reepay_simple_subscriptions';
		$_REQUEST['_reepay_subscription_handle'] = 'plan-handle-1';
		$_POST['_reepay_subscription_customer_role'] = 'subscriber';

		$this->plan->save_subscription_meta( $post_id );

		$this->assertSame( 'subscriber', get_post_meta( $post_id, '_reepay_subscription_customer_role', true ) );
	}

	/**
	 * Test @see WC_Reepay_Subscription_Plan_Simple::save_subscription_meta
	 * actively DELETES a previously-set '_reepay_subscription_customer_role'
	 * meta value when the posted value is the literal string
	 * 'without_changes' - confirming this is not a no-op but an explicit
	 * delete_post_meta() branch.
	 */
	public function test_save_subscription_meta_deletes_role_for_without_changes() {
		$post_id = $this->create_real_product_post();
		update_post_meta( $post_id, '_reepay_subscription_customer_role', 'previously-set-role' );

		$this->mock_valid_plan_response();

		$_REQUEST['product-type']                = 'reepay_simple_subscriptions';
		$_REQUEST['_reepay_subscription_handle']  = 'plan-handle-1';
		$_POST['_reepay_subscription_customer_role'] = 'without_changes';

		$this->plan->save_subscription_meta( $post_id );

		$this->assertSame( '', get_post_meta( $post_id, '_reepay_subscription_customer_role', true ) );
	}

	/**
	 * Same as above but for the '_reepay_subscription_customer_role_expired'
	 * key, confirming the real-value branch independently of the main role
	 * key (both are handled by separate if/else blocks in the source).
	 */
	public function test_save_subscription_meta_updates_role_expired_for_real_value() {
		$post_id = $this->create_real_product_post();
		$this->mock_valid_plan_response();

		$_REQUEST['product-type']               = 'reepay_simple_subscriptions';
		$_REQUEST['_reepay_subscription_handle'] = 'plan-handle-1';
		$_POST['_reepay_subscription_customer_role_expired'] = 'customer';

		$this->plan->save_subscription_meta( $post_id );

		$this->assertSame( 'customer', get_post_meta( $post_id, '_reepay_subscription_customer_role_expired', true ) );
	}

	/**
	 * Same as the delete test above but for the
	 * '_reepay_subscription_customer_role_expired' key.
	 */
	public function test_save_subscription_meta_deletes_role_expired_for_without_changes() {
		$post_id = $this->create_real_product_post();
		update_post_meta( $post_id, '_reepay_subscription_customer_role_expired', 'previously-set-role' );

		$this->mock_valid_plan_response();

		$_REQUEST['product-type']               = 'reepay_simple_subscriptions';
		$_REQUEST['_reepay_subscription_handle'] = 'plan-handle-1';
		$_POST['_reepay_subscription_customer_role_expired'] = 'without_changes';

		$this->plan->save_subscription_meta( $post_id );

		$this->assertSame( '', get_post_meta( $post_id, '_reepay_subscription_customer_role_expired', true ) );
	}

	/**
	 * Confirms save_subscription_meta() never reaches the role branches at
	 * all when is_reepay_product_saving() is false (wrong product-type) -
	 * i.e. the early return at WC_Reepay_Subscription_Plan_Simple.php:645-650
	 * is real and guards the whole method, not just a later step.
	 */
	public function test_save_subscription_meta_no_op_when_not_reepay_product_saving() {
		$post_id = $this->create_real_product_post();
		update_post_meta( $post_id, '_reepay_subscription_customer_role', 'existing-role' );

		// Deliberately NOT the reepay_simple_subscriptions product-type.
		$_REQUEST['product-type'] = 'simple';
		$_POST['_reepay_subscription_customer_role'] = 'subscriber';

		$this->plan->save_subscription_meta( $post_id );

		// Untouched - neither updated to 'subscriber' nor deleted.
		$this->assertSame( 'existing-role', get_post_meta( $post_id, '_reepay_subscription_customer_role', true ) );
	}

	/*
	 * ------------------------------------------------------------------
	 * get_subscription_handle_from_request()
	 * ------------------------------------------------------------------
	 *
	 * Real behavior (WC_Reepay_Subscription_Plan_Simple.php:684-686):
	 *   return wc_clean( $_REQUEST['_reepay_subscription_handle'] ?? '' );
	 * A plain scalar key, no array index.
	 */

	/**
	 * Test @see WC_Reepay_Subscription_Plan_Simple::get_subscription_handle_from_request
	 * returns the posted handle value when present in $_REQUEST.
	 */
	public function test_get_subscription_handle_from_request_returns_posted_handle() {
		$_REQUEST['_reepay_subscription_handle'] = 'handle-from-request-1';

		$this->assertSame( 'handle-from-request-1', $this->plan->get_subscription_handle_from_request() );
	}

	/**
	 * Test @see WC_Reepay_Subscription_Plan_Simple::get_subscription_handle_from_request
	 * returns an empty string when the '_reepay_subscription_handle' key is
	 * absent entirely from $_REQUEST (exercises the ?? '' null-coalesce
	 * fallback, not just an empty-string value).
	 */
	public function test_get_subscription_handle_from_request_returns_empty_string_when_absent() {
		unset( $_REQUEST['_reepay_subscription_handle'] );

		$this->assertSame( '', $this->plan->get_subscription_handle_from_request() );
	}

	/*
	 * ------------------------------------------------------------------
	 * is_reepay_product_saving()
	 * ------------------------------------------------------------------
	 *
	 * Real behavior (WC_Reepay_Subscription_Plan_Simple.php:688-690):
	 *   return ! empty( $_REQUEST ) && ! empty( $_REQUEST['product-type'] )
	 *       && $_REQUEST['product-type'] === 'reepay_simple_subscriptions';
	 * Unlike Plan_Variable, there is no additional handle-presence
	 * requirement here.
	 */

	/**
	 * Test @see WC_Reepay_Subscription_Plan_Simple::is_reepay_product_saving
	 * returns true when product-type matches, even with NO subscription
	 * handle present in the request at all - this is the documented
	 * asymmetry with Plan_Variable (which additionally requires a non-empty
	 * handle).
	 */
	public function test_is_reepay_product_saving_true_for_matching_product_type_without_handle() {
		$_REQUEST['product-type'] = 'reepay_simple_subscriptions';
		unset( $_REQUEST['_reepay_subscription_handle'] );

		$this->assertTrue( $this->plan->is_reepay_product_saving() );
	}

	/**
	 * Test @see WC_Reepay_Subscription_Plan_Simple::is_reepay_product_saving
	 * returns false when product-type does not match.
	 */
	public function test_is_reepay_product_saving_false_for_different_product_type() {
		$_REQUEST['product-type'] = 'reepay_variable_subscriptions';

		$this->assertFalse( $this->plan->is_reepay_product_saving() );
	}

	/**
	 * Test @see WC_Reepay_Subscription_Plan_Simple::is_reepay_product_saving
	 * returns false when 'product-type' is absent/empty from the request
	 * entirely.
	 */
	public function test_is_reepay_product_saving_false_when_product_type_absent() {
		unset( $_REQUEST['product-type'] );

		$this->assertFalse( $this->plan->is_reepay_product_saving() );
	}

	/*
	 * ------------------------------------------------------------------
	 * save_subscription_meta() - role validation guards
	 * ------------------------------------------------------------------
	 */

	/**
	 * Test @see WC_Reepay_Subscription_Plan_Simple::save_subscription_meta
	 * keeps the existing role settings when the current user cannot
	 * 'promote_users' (a shop manager can edit products but cannot promote
	 * users): the posted role is ignored and the admin-configured roles are
	 * not wiped. Regression test - save_remote_plan() used to reset the role
	 * keys together with the plan fields, so saving the product as a shop
	 * manager silently removed both roles.
	 */
	public function test_save_subscription_meta_without_promote_users_keeps_existing_roles() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'shop_manager' ) ) );
		$this->assertFalse( current_user_can( 'promote_users' ), 'Fixture user must not be able to promote users.' );

		$post_id = $this->create_real_product_post();
		update_post_meta( $post_id, '_reepay_subscription_customer_role', 'customer' );
		update_post_meta( $post_id, '_reepay_subscription_customer_role_expired', 'subscriber' );
		$this->mock_valid_plan_response();

		$_REQUEST['product-type']                            = 'reepay_simple_subscriptions';
		$_REQUEST['_reepay_subscription_handle']             = 'plan-handle-1';
		$_POST['_reepay_subscription_customer_role']         = 'administrator';
		$_POST['_reepay_subscription_customer_role_expired'] = '';

		$this->plan->save_subscription_meta( $post_id );

		$this->assertSame( 'customer', get_post_meta( $post_id, '_reepay_subscription_customer_role', true ) );
		$this->assertSame( 'subscriber', get_post_meta( $post_id, '_reepay_subscription_customer_role_expired', true ) );
	}

	/**
	 * Test @see WC_Reepay_Subscription_Plan_Simple::save_subscription_meta
	 * still refreshes the plan fields from Frisbii when the current user
	 * cannot 'promote_users' - only the role keys are left alone.
	 */
	public function test_save_subscription_meta_without_promote_users_still_saves_plan_fields() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'shop_manager' ) ) );

		$post_id = $this->create_real_product_post();
		update_post_meta( $post_id, '_reepay_subscription_name', 'Stale name' );
		$this->mock_valid_plan_response();

		$_REQUEST['product-type']                = 'reepay_simple_subscriptions';
		$_REQUEST['_reepay_subscription_handle'] = 'plan-handle-1';

		$this->plan->save_subscription_meta( $post_id );

		$this->assertSame( 'Plan One', get_post_meta( $post_id, '_reepay_subscription_name', true ) );
		$this->assertSame( 'plan-handle-1', get_post_meta( $post_id, '_reepay_subscription_handle', true ) );
	}

	/**
	 * Test @see WC_Reepay_Subscription_Plan_Simple::save_subscription_meta
	 * does not save a role that is not in get_editable_roles() (unknown
	 * role), keeping the previous value.
	 */
	public function test_save_subscription_meta_rejects_unknown_role() {
		$post_id = $this->create_real_product_post();
		update_post_meta( $post_id, '_reepay_subscription_customer_role', 'subscriber' );
		$this->mock_valid_plan_response();

		$_REQUEST['product-type']                    = 'reepay_simple_subscriptions';
		$_REQUEST['_reepay_subscription_handle']     = 'plan-handle-1';
		$_POST['_reepay_subscription_customer_role'] = 'no-such-role';

		$this->plan->save_subscription_meta( $post_id );

		$this->assertSame( 'subscriber', get_post_meta( $post_id, '_reepay_subscription_customer_role', true ) );
	}

	/**
	 * Test @see WC_Reepay_Subscription_Plan_Simple::save_remote_plan does not
	 * touch the role keys (they are not part of the Frisbii plan).
	 */
	public function test_save_remote_plan_leaves_role_keys_alone() {
		$post_id = $this->create_real_product_post();
		update_post_meta( $post_id, '_reepay_subscription_customer_role', 'customer' );
		update_post_meta( $post_id, '_reepay_subscription_customer_role_expired', 'subscriber' );
		$this->mock_valid_plan_response();

		$this->plan->save_remote_plan( $post_id, 'plan-handle-1' );

		$this->assertSame( 'customer', get_post_meta( $post_id, '_reepay_subscription_customer_role', true ) );
		$this->assertSame( 'subscriber', get_post_meta( $post_id, '_reepay_subscription_customer_role_expired', true ) );
	}

	/**
	 * Test @see WC_Reepay_Subscription_Plan_Simple::save_subscription_meta
	 * does not save a role the current user may not grant (removed from
	 * get_editable_roles() via the 'editable_roles' filter).
	 */
	public function test_save_subscription_meta_rejects_role_not_editable_by_user() {
		$hide_editor = function ( $roles ) {
			unset( $roles['editor'] );

			return $roles;
		};
		add_filter( 'editable_roles', $hide_editor );

		$post_id = $this->create_real_product_post();
		$this->mock_valid_plan_response();

		$_REQUEST['product-type']                    = 'reepay_simple_subscriptions';
		$_REQUEST['_reepay_subscription_handle']     = 'plan-handle-1';
		$_POST['_reepay_subscription_customer_role'] = 'editor';

		$this->plan->save_subscription_meta( $post_id );

		remove_filter( 'editable_roles', $hide_editor );

		$this->assertSame( '', get_post_meta( $post_id, '_reepay_subscription_customer_role', true ) );
	}

	/*
	 * ------------------------------------------------------------------
	 * get_month_fixed_day_text() / get_billing_plan() for fixed-day plans
	 * ------------------------------------------------------------------
	 *
	 * The test site runs in English (untranslated strings), so the ordinal
	 * is always English ("1st", "2nd", ...), via NumberFormatter when the
	 * intl extension is available and the built-in fallback otherwise.
	 */

	/**
	 * Test @see WC_Reepay_Subscription_Plan_Simple::get_month_fixed_day_text
	 * uses the singular sentence for a 1-month interval, with English
	 * ordinals including the 11th-13th exceptions.
	 */
	public function test_get_month_fixed_day_text_singular_with_english_ordinals() {
		$expected = array(
			1  => '1st',
			2  => '2nd',
			3  => '3rd',
			4  => '4th',
			11 => '11th',
			12 => '12th',
			13 => '13th',
			21 => '21st',
			22 => '22nd',
			23 => '23rd',
			31 => '31st',
		);

		foreach ( $expected as $day => $ordinal ) {
			$this->assertSame(
				"Billed on the $ordinal day of every month",
				WC_Reepay_Subscription_Plan_Simple::get_month_fixed_day_text( 1, $day )
			);
		}
	}

	/**
	 * Test @see WC_Reepay_Subscription_Plan_Simple::get_month_fixed_day_text
	 * uses the plural sentence for intervals of more than one month.
	 */
	public function test_get_month_fixed_day_text_plural() {
		$this->assertSame(
			'Billed every 3 months on the 15th day of the month',
			WC_Reepay_Subscription_Plan_Simple::get_month_fixed_day_text( 3, 15 )
		);
	}

	/**
	 * Test @see WC_Reepay_Subscription_Plan_Simple::get_billing_plan returns
	 * the fixed-day sentence for a month_fixed_day plan (long form only).
	 */
	public function test_get_billing_plan_month_fixed_day_long_form() {
		$plan = array(
			'type'      => WC_Reepay_Subscription_Plan_Simple::TYPE_MONTH_FIXED_DAY,
			'type_data' => array(
				'month' => 1,
				'day'   => 1,
			),
			'interval'  => 1,
		);

		$this->assertSame(
			'Billed on the 1st day of every month',
			WC_Reepay_Subscription_Plan_Simple::get_billing_plan( $plan )
		);
		$this->assertNotSame(
			'Billed on the 1st day of every month',
			WC_Reepay_Subscription_Plan_Simple::get_billing_plan( $plan, true ),
			'The short form must not use the fixed-day sentence.'
		);
	}
}
