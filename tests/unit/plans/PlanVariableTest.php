<?php
/**
 * Class PlanVariableTest
 *
 * Covers WC_Reepay_Subscription_Plan_Variable's request-driven methods:
 * save_subscription_meta( $post_id, $i ), get_subscription_handle_from_request()
 * (indexed by the public $loop property), and is_reepay_product_saving(). All
 * read directly from $_POST/$_REQUEST, so this extends
 * Reepay_Subscriptions_Ajax_UnitTestCase (resets those superglobals in
 * tear_down()).
 *
 * Important: WC_Reepay_Subscription_Plan_Variable extends
 * WC_Reepay_Subscription_Plan_Simple and OVERRIDES
 * is_reepay_product_saving() and get_subscription_handle_from_request().
 * Plan_Variable::save_subscription_meta() sets $this->loop = $i and then
 * calls parent::save_subscription_meta( $post_id ) - but because PHP method
 * calls via $this-> resolve against the object's actual (child) class, that
 * parent call still invokes the Plan_Variable overrides of
 * is_reepay_product_saving() and get_subscription_handle_from_request(), not
 * Plan_Simple's. So reaching the role-meta branches on a Plan_Variable
 * instance requires satisfying Plan_Variable's stricter
 * is_reepay_product_saving() (product-type AND non-empty handle), and the
 * handle itself must be present at $_REQUEST['_reepay_subscription_handle'][$loop].
 *
 * @package Reepay\Subscriptions
 */

use Reepay\Subscriptions\Tests\Helpers\Reepay_Subscriptions_Ajax_UnitTestCase;
use Reepay\Subscriptions\Tests\Mocks\ApiResponseMock;

/**
 * PlanVariableTest.
 */
class PlanVariableTest extends Reepay_Subscriptions_Ajax_UnitTestCase {

	/**
	 * @var WC_Reepay_Subscription_Plan_Variable
	 */
	private WC_Reepay_Subscription_Plan_Variable $plan;

	public function set_up() {
		parent::set_up();

		$this->plan = new WC_Reepay_Subscription_Plan_Variable();

		// Same rationale as PlanSimpleTest::set_up() / renewals/LifecycleTest.php:
		// save_subscription_meta() -> parent::save_subscription_meta() ->
		// save_remote_plan() -> get_remote_plan_meta() -> get_plan() ->
		// WC_Reepay_Subscription_API::request() needs a configured private key
		// before it will even call wp_remote_request() (which is what
		// ApiResponseMock intercepts). The singleton caches its key at bootstrap,
		// so inject it directly via reflection. Test-only wiring, no production
		// file touched.
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
	 * reflection. Used only to inject a fake API key into the plugin's
	 * settings singleton. Copied verbatim from tests/unit/renewals/LifecycleTest.php.
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
	 * Create a real 'reepay_variable_subscriptions'-flavoured product post
	 * with a non-AUTO-DRAFT title, so the get_the_title()/AUTO-DRAFT guard
	 * inherited from Plan_Simple::save_subscription_meta() (line 643-650)
	 * does not short-circuit. We reuse WC_Product_Reepay_Simple_Subscription
	 * only to obtain a real, saved post ID with a proper title - the Plan
	 * class under test does not itself branch on product class/type here.
	 *
	 * @return int product/post ID.
	 */
	private function create_real_product_post(): int {
		$product = ( new \Reepay\Subscriptions\Tests\Helpers\ProductGenerator( 'rp_sub' ) )->product();
		$product->set_name( 'Real Plan Variable Product' );
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
				'handle' => 'plan-handle-var-1',
				'amount' => 2000,
				'name'   => 'Variable Plan One',
			)
		);
	}

	/*
	 * ------------------------------------------------------------------
	 * save_subscription_meta( $post_id, $i ) - customer-role / expired
	 * ------------------------------------------------------------------
	 *
	 * Real behavior (WC_Reepay_Subscription_Plan_Variable::save_subscription_meta()):
	 *
	 *   public function save_subscription_meta( $post_id, $i = null ) {
	 *       $this->loop = $i;
	 *       parent::save_subscription_meta( $post_id );
	 *       $this->save_customer_role_meta( $post_id, '_reepay_subscription_customer_role', $_POST[...][ $i ] ?? '' );
	 *       // same for _expired
	 *   }
	 *
	 * The role values are read from the POST array AT INDEX $i (the explicit
	 * parameter), confirming per-variation-index handling. save_customer_role_meta()
	 * requires 'promote_users' (set_up() logs in as an administrator) and only
	 * saves roles from get_editable_roles(); 'without_changes' takes the
	 * delete branch, same as Plan_Simple.
	 */

	/**
	 * Test @see WC_Reepay_Subscription_Plan_Variable::save_subscription_meta
	 * updates the role meta using the value at POST array index $i when a
	 * real role is posted for that index - with the handle request array
	 * also indexed by the same loop value (since $this->loop = $i before the
	 * parent call resolves the handle/is_reepay_product_saving overrides).
	 */
	public function test_save_subscription_meta_updates_role_for_real_value_at_index() {
		$post_id = $this->create_real_product_post();
		$this->mock_valid_plan_response();

		$_REQUEST['product-type']                 = 'reepay_variable_subscriptions';
		$_REQUEST['_reepay_subscription_handle']   = array( 0 => 'plan-handle-var-1' );
		$_POST['_reepay_subscription_customer_role'] = array( 0 => 'subscriber' );

		$this->plan->save_subscription_meta( $post_id, 0 );

		$this->assertSame( 'subscriber', get_post_meta( $post_id, '_reepay_subscription_customer_role', true ) );
	}

	/**
	 * Proves INDEXING matters, not just presence: two different POST array
	 * values at two different indexes produce two different results when
	 * save_subscription_meta() is called with the matching $i each time -
	 * i.e. the method really reads $_POST[...][ $i ], not some other index
	 * or the whole array.
	 */
	public function test_save_subscription_meta_uses_explicit_index_not_just_presence() {
		$post_id_a = $this->create_real_product_post();
		$post_id_b = $this->create_real_product_post();
		$this->mock_valid_plan_response();

		$_REQUEST['product-type']               = 'reepay_variable_subscriptions';
		$_REQUEST['_reepay_subscription_handle'] = array(
			0 => 'plan-handle-var-1',
			1 => 'plan-handle-var-1',
		);
		// Two different real (editable) roles - only roles from
		// get_editable_roles() are saved.
		$_POST['_reepay_subscription_customer_role'] = array(
			0 => 'subscriber',
			1 => 'contributor',
		);

		// Save post A using index 0, post B using index 1 - different rows,
		// different indexes, different expected results.
		$this->plan->save_subscription_meta( $post_id_a, 0 );
		$this->plan->save_subscription_meta( $post_id_b, 1 );

		$this->assertSame( 'subscriber', get_post_meta( $post_id_a, '_reepay_subscription_customer_role', true ) );
		$this->assertSame( 'contributor', get_post_meta( $post_id_b, '_reepay_subscription_customer_role', true ) );
	}

	/**
	 * Test @see WC_Reepay_Subscription_Plan_Variable::save_subscription_meta
	 * deletes a previously-set role meta value when the posted value AT
	 * THAT INDEX is the literal string 'without_changes' - same delete
	 * branch behavior as Plan_Simple, but index-aware.
	 */
	public function test_save_subscription_meta_deletes_role_for_without_changes_at_index() {
		$post_id = $this->create_real_product_post();
		update_post_meta( $post_id, '_reepay_subscription_customer_role', 'previously-set-role' );

		$this->mock_valid_plan_response();

		$_REQUEST['product-type']                 = 'reepay_variable_subscriptions';
		$_REQUEST['_reepay_subscription_handle']   = array( 2 => 'plan-handle-var-1' );
		$_POST['_reepay_subscription_customer_role'] = array( 2 => 'without_changes' );

		$this->plan->save_subscription_meta( $post_id, 2 );

		$this->assertSame( '', get_post_meta( $post_id, '_reepay_subscription_customer_role', true ) );
	}

	/**
	 * Test @see WC_Reepay_Subscription_Plan_Variable::save_subscription_meta
	 * keeps a variation's existing role settings when the current user cannot
	 * 'promote_users' (shop manager). Regression test for the role keys being
	 * wiped by save_remote_plan() before the capability check.
	 */
	public function test_save_subscription_meta_without_promote_users_keeps_existing_roles_at_index() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'shop_manager' ) ) );

		$post_id = $this->create_real_product_post();
		update_post_meta( $post_id, '_reepay_subscription_customer_role', 'customer' );
		update_post_meta( $post_id, '_reepay_subscription_customer_role_expired', 'subscriber' );
		$this->mock_valid_plan_response();

		$_REQUEST['product-type']                            = 'reepay_variable_subscriptions';
		$_REQUEST['_reepay_subscription_handle']             = array( 0 => 'plan-handle-var-1' );
		$_POST['_reepay_subscription_customer_role']         = array( 0 => 'administrator' );
		$_POST['_reepay_subscription_customer_role_expired'] = array( 0 => '' );

		$this->plan->save_subscription_meta( $post_id, 0 );

		$this->assertSame( 'customer', get_post_meta( $post_id, '_reepay_subscription_customer_role', true ) );
		$this->assertSame( 'subscriber', get_post_meta( $post_id, '_reepay_subscription_customer_role_expired', true ) );
	}

	/**
	 * Same real-value update behavior but for the
	 * '_reepay_subscription_customer_role_expired' key, confirmed
	 * independently (separate if/else block in the source).
	 */
	public function test_save_subscription_meta_updates_role_expired_for_real_value_at_index() {
		$post_id = $this->create_real_product_post();
		$this->mock_valid_plan_response();

		$_REQUEST['product-type']               = 'reepay_variable_subscriptions';
		$_REQUEST['_reepay_subscription_handle'] = array( 1 => 'plan-handle-var-1' );
		$_POST['_reepay_subscription_customer_role_expired'] = array( 1 => 'customer' );

		$this->plan->save_subscription_meta( $post_id, 1 );

		$this->assertSame( 'customer', get_post_meta( $post_id, '_reepay_subscription_customer_role_expired', true ) );
	}

	/*
	 * ------------------------------------------------------------------
	 * get_subscription_handle_from_request() - public $loop property
	 * ------------------------------------------------------------------
	 *
	 * Real behavior (WC_Reepay_Subscription_Plan_Variable.php:117-123):
	 *   if ( empty( $_REQUEST['_reepay_subscription_handle'] ) ) {
	 *       return '';
	 *   }
	 *   return wc_clean( $_REQUEST['_reepay_subscription_handle'][ $this->loop ] ?? '' );
	 * Indexed by the PUBLIC $loop property (not a method parameter) - set
	 * directly, no reflection needed.
	 */

	/**
	 * Test @see WC_Reepay_Subscription_Plan_Variable::get_subscription_handle_from_request
	 * reads the handle at the array index matching the current public $loop
	 * property value - set to 0 here.
	 */
	public function test_get_subscription_handle_from_request_reads_loop_index_zero() {
		$_REQUEST['_reepay_subscription_handle'] = array(
			0 => 'handle-at-loop-0',
			1 => 'handle-at-loop-1',
		);

		$this->plan->loop = 0;

		$this->assertSame( 'handle-at-loop-0', $this->plan->get_subscription_handle_from_request() );
	}

	/**
	 * Same request array as above, but with $loop set to a different value
	 * (2) - proving the method re-reads $loop dynamically and indexes into
	 * the correct slot, not just whichever slot happens to be first/present.
	 */
	public function test_get_subscription_handle_from_request_reads_different_loop_index() {
		$_REQUEST['_reepay_subscription_handle'] = array(
			0 => 'handle-at-loop-0',
			1 => 'handle-at-loop-1',
			2 => 'handle-at-loop-2',
		);

		$this->plan->loop = 2;

		$this->assertSame( 'handle-at-loop-2', $this->plan->get_subscription_handle_from_request() );
	}

	/**
	 * Test @see WC_Reepay_Subscription_Plan_Variable::get_subscription_handle_from_request
	 * returns an empty string when '_reepay_subscription_handle' is absent
	 * from $_REQUEST entirely - exercising the early `empty()` guard that
	 * runs BEFORE any indexing by $loop is attempted (so an absent key never
	 * reaches the ?? '' fallback on a missing array index).
	 */
	public function test_get_subscription_handle_from_request_returns_empty_string_when_absent() {
		unset( $_REQUEST['_reepay_subscription_handle'] );
		$this->plan->loop = 0;

		$this->assertSame( '', $this->plan->get_subscription_handle_from_request() );
	}

	/**
	 * Test that even when the handle array IS present, an index with no
	 * corresponding entry (loop points past the end of the array) still
	 * returns an empty string via the `?? ''` fallback on the specific
	 * index, not a fatal/notice.
	 */
	public function test_get_subscription_handle_from_request_returns_empty_string_for_missing_index() {
		$_REQUEST['_reepay_subscription_handle'] = array( 0 => 'handle-at-loop-0' );
		$this->plan->loop = 5;

		$this->assertSame( '', $this->plan->get_subscription_handle_from_request() );
	}

	/*
	 * ------------------------------------------------------------------
	 * is_reepay_product_saving()
	 * ------------------------------------------------------------------
	 *
	 * Real behavior (WC_Reepay_Subscription_Plan_Variable.php:125-130):
	 *   return ! empty( $_REQUEST ) &&
	 *          ! empty( $_REQUEST['product-type'] ) &&
	 *          $_REQUEST['product-type'] === 'reepay_variable_subscriptions' &&
	 *          ! empty( $_REQUEST['_reepay_subscription_handle'] );
	 * This is the documented asymmetry with Plan_Simple: Plan_Variable
	 * ADDITIONALLY requires a non-empty subscription handle in the request.
	 */

	/**
	 * Test @see WC_Reepay_Subscription_Plan_Variable::is_reepay_product_saving
	 * returns true when BOTH the product-type matches AND a non-empty
	 * handle is present.
	 */
	public function test_is_reepay_product_saving_true_when_product_type_and_handle_present() {
		$_REQUEST['product-type']               = 'reepay_variable_subscriptions';
		$_REQUEST['_reepay_subscription_handle'] = array( 0 => 'some-handle' );

		$this->assertTrue( $this->plan->is_reepay_product_saving() );
	}

	/**
	 * Test the documented asymmetry with Plan_Simple directly: with a
	 * matching product-type but NO subscription handle in the request,
	 * Plan_Variable::is_reepay_product_saving() returns false - whereas
	 * Plan_Simple::is_reepay_product_saving() would return true for the
	 * equivalent request (see PlanSimpleTest::test_is_reepay_product_saving_true_for_matching_product_type_without_handle).
	 */
	public function test_is_reepay_product_saving_false_when_handle_missing_despite_matching_product_type() {
		$_REQUEST['product-type'] = 'reepay_variable_subscriptions';
		unset( $_REQUEST['_reepay_subscription_handle'] );

		$this->assertFalse( $this->plan->is_reepay_product_saving() );
	}

	/**
	 * Test @see WC_Reepay_Subscription_Plan_Variable::is_reepay_product_saving
	 * returns false when product-type does not match, even with a non-empty
	 * handle present.
	 */
	public function test_is_reepay_product_saving_false_for_different_product_type() {
		$_REQUEST['product-type']               = 'reepay_simple_subscriptions';
		$_REQUEST['_reepay_subscription_handle'] = array( 0 => 'some-handle' );

		$this->assertFalse( $this->plan->is_reepay_product_saving() );
	}

	/**
	 * Test @see WC_Reepay_Subscription_Plan_Variable::is_reepay_product_saving
	 * returns false when 'product-type' is absent from the request entirely.
	 */
	public function test_is_reepay_product_saving_false_when_product_type_absent() {
		unset( $_REQUEST['product-type'] );
		$_REQUEST['_reepay_subscription_handle'] = array( 0 => 'some-handle' );

		$this->assertFalse( $this->plan->is_reepay_product_saving() );
	}
}
