<?php
/**
 * Class AdminFrontendTest
 *
 * Covers WC_Reepay_Admin_Frontend (includes/WC_Reepay_Admin_Frontend.php):
 * modify_order_id, admin_shop_order_row_classes, shop_order_custom_columns,
 * admin_shop_order_edit_columns and modify_search_results_fields.
 *
 * Phase 5 scope: baseline regression coverage per requirements.md §3.4, not
 * exhaustive scenario enumeration. The constructor's HPOS-branching hook
 * registration itself is not tested directly - only the public methods it
 * wires up.
 *
 * Confirmed real behavior (read from source):
 * - modify_order_id($id, $order) reads `global $post` and
 *   $order->get_meta('_reepay_order'); only returns the reepay handle instead
 *   of $id when $post is non-empty AND the reepay order meta is non-empty AND
 *   ($post->post_parent is a non-zero truthy value OR the order/post carries
 *   a '_reepay_subscription_handle_parent' meta/postmeta value).
 * - admin_shop_order_row_classes($classes, $post_id) returns $classes
 *   unchanged if is_search() is true or !current_user_can('manage_woocommerce').
 *   Otherwise reads `global $post` and OrderUtil::get_order_type($post_id);
 *   appends a 'sub-order parent-{id}' class only when the order type is
 *   'shop_order' AND $post->post_parent != 0.
 * - shop_order_custom_columns($column_id, $order) normalizes $order via
 *   wc_get_order($order) (so it accepts a WC_Order, an int ID, or null) and
 *   returns early (no output at all) if !current_user_can('manage_woocommerce')
 *   or $column_id isn't one of order_number/order_type/reepay_sub. Echoes
 *   wp_kses_post($output) - asserted here via expectOutputString()/output
 *   buffering.
 * - admin_shop_order_edit_columns($existing_columns) inserts reepay_sub and
 *   order_type columns just before the LAST existing column, then strips both
 *   back out if !current_user_can('manage_woocommerce') OR a non-empty
 *   $_GET['author'] is present - the author-filter branch is read directly
 *   from the superglobal (not via filter_input), so the plain $_GET
 *   assignment in the Ajax testcase works as expected.
 * - modify_search_results_fields($orderby, $query) only rewrites $orderby
 *   (a raw $wpdb column-list string) when is_admin() && $query->is_main_query()
 *   && $query->get('post_type') === 'shop_order'; otherwise returns $orderby
 *   unchanged. Tested with a real WP_Query instance rather than a full mock
 *   of the query stack, per the brief's guidance.
 *
 * @package Reepay\Subscriptions
 */

use Reepay\Subscriptions\Tests\Helpers\Reepay_Subscriptions_Ajax_UnitTestCase;

/**
 * AdminFrontendTest.
 */
class AdminFrontendTest extends Reepay_Subscriptions_Ajax_UnitTestCase {

	/**
	 * @var WC_Reepay_Admin_Frontend
	 */
	private WC_Reepay_Admin_Frontend $frontend;

	/**
	 * @var int Administrator user ID, set current user for manage_woocommerce checks.
	 */
	private int $admin_id;

	public function set_up() {
		parent::set_up();

		$this->frontend = new WC_Reepay_Admin_Frontend();

		$this->admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $this->admin_id );
	}

	public function tear_down() {
		global $post;
		$post = null;

		parent::tear_down();
	}

	/*
	 * ------------------------------------------------------------------
	 * modify_order_id()
	 * ------------------------------------------------------------------
	 */

	/**
	 * Test @see WC_Reepay_Admin_Frontend::modify_order_id returns the reepay
	 * order handle instead of $id when `global $post` has a non-zero
	 * post_parent AND the order carries a non-empty '_reepay_order' meta.
	 */
	public function test_modify_order_id_returns_reepay_handle_for_sub_order_with_parent() {
		$order = $this->order_generator->add_product( 'simple' )->order();
		$order->update_meta_data( '_reepay_order', 'inv-handle-123' );
		$order->save_meta_data();

		global $post;
		$post              = new WP_Post( new stdClass() );
		$post->post_parent = 999;
		$post->ID          = $order->get_id();

		$result = $this->frontend->modify_order_id( 'fallback-id', $order );

		$this->assertSame( 'inv-handle-123', $result );
	}

	/**
	 * Test @see WC_Reepay_Admin_Frontend::modify_order_id falls back to the
	 * passed-in $id unchanged when `global $post` is empty.
	 */
	public function test_modify_order_id_returns_fallback_id_without_global_post() {
		$order = $this->order_generator->add_product( 'simple' )->order();
		$order->update_meta_data( '_reepay_order', 'inv-handle-456' );
		$order->save_meta_data();

		global $post;
		$post = null;

		$result = $this->frontend->modify_order_id( 'fallback-id', $order );

		$this->assertSame( 'fallback-id', $result );
	}

	/**
	 * Test @see WC_Reepay_Admin_Frontend::modify_order_id falls back to the
	 * passed-in $id unchanged when the order has no '_reepay_order' meta,
	 * even with a non-zero post_parent.
	 */
	public function test_modify_order_id_returns_fallback_id_without_reepay_order_meta() {
		$order = $this->order_generator->add_product( 'simple' )->order();

		global $post;
		$post              = new WP_Post( new stdClass() );
		$post->post_parent = 999;
		$post->ID          = $order->get_id();

		$result = $this->frontend->modify_order_id( 'fallback-id', $order );

		$this->assertSame( 'fallback-id', $result );
	}

	/*
	 * ------------------------------------------------------------------
	 * admin_shop_order_row_classes()
	 * ------------------------------------------------------------------
	 */

	/**
	 * Test @see WC_Reepay_Admin_Frontend::admin_shop_order_row_classes returns
	 * $classes unchanged when is_search() is true, regardless of capability.
	 */
	public function test_admin_shop_order_row_classes_unchanged_when_is_search() {
		global $wp_query;
		$wp_query->is_search = true;

		$order   = $this->order_generator->add_product( 'simple' )->order();
		$classes = array( 'existing-class' );

		$result = $this->frontend->admin_shop_order_row_classes( $classes, $order->get_id() );

		$wp_query->is_search = false;

		$this->assertSame( $classes, $result );
	}

	/**
	 * Test @see WC_Reepay_Admin_Frontend::admin_shop_order_row_classes returns
	 * $classes unchanged for a user without manage_woocommerce capability.
	 */
	public function test_admin_shop_order_row_classes_unchanged_without_capability() {
		$subscriber_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber_id );

		$order   = $this->order_generator->add_product( 'simple' )->order();
		$classes = array( 'existing-class' );

		$result = $this->frontend->admin_shop_order_row_classes( $classes, $order->get_id() );

		$this->assertSame( $classes, $result );
	}

	/**
	 * Test @see WC_Reepay_Admin_Frontend::admin_shop_order_row_classes appends
	 * the 'sub-order parent-{id}' class for a shop_order post with a non-zero
	 * post_parent, for an admin user outside of search.
	 */
	public function test_admin_shop_order_row_classes_appends_sub_order_class() {
		global $wp_query, $post;
		$wp_query->is_search = false;

		$order = $this->order_generator->add_product( 'simple' )->order();

		$post              = new WP_Post( new stdClass() );
		$post->post_parent = 42;
		$post->ID          = $order->get_id();

		$result = $this->frontend->admin_shop_order_row_classes( array(), $order->get_id() );

		$this->assertContains( 'sub-order parent-42', $result );
	}

	/*
	 * ------------------------------------------------------------------
	 * shop_order_custom_columns()
	 * ------------------------------------------------------------------
	 */

	/**
	 * Test @see WC_Reepay_Admin_Frontend::shop_order_custom_columns outputs
	 * nothing for a user without manage_woocommerce capability.
	 */
	public function test_shop_order_custom_columns_no_output_without_capability() {
		$subscriber_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber_id );

		$order = $this->order_generator->add_product( 'simple' )->order();

		ob_start();
		$this->frontend->shop_order_custom_columns( 'order_type', $order );
		$output = ob_get_clean();

		$this->assertSame( '', $output );
	}

	/**
	 * Test @see WC_Reepay_Admin_Frontend::shop_order_custom_columns outputs
	 * nothing for a column id outside order_number/order_type/reepay_sub.
	 */
	public function test_shop_order_custom_columns_no_output_for_unknown_column() {
		$order = $this->order_generator->add_product( 'simple' )->order();

		ob_start();
		$this->frontend->shop_order_custom_columns( 'some_unrelated_column', $order );
		$output = ob_get_clean();

		$this->assertSame( '', $output );
	}

	/**
	 * Test @see WC_Reepay_Admin_Frontend::shop_order_custom_columns accepts a
	 * raw order ID (int) rather than a WC_Order instance, per the HPOS/legacy
	 * compatibility note in the method docblock, and outputs the "Subscription"
	 * label for the 'order_type' column when the order has a top-level
	 * subscription handle.
	 */
	public function test_shop_order_custom_columns_order_type_subscription_accepts_order_id() {
		$order = $this->order_generator->add_product( 'simple' )->order();
		$order->update_meta_data( '_reepay_subscription_handle', 'sub-handle-1' );
		$order->save_meta_data();

		ob_start();
		$this->frontend->shop_order_custom_columns( 'order_type', $order->get_id() );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Subscription', $output );
	}

	/**
	 * Test @see WC_Reepay_Admin_Frontend::shop_order_custom_columns renders a
	 * link to the Billwerk admin for the 'reepay_sub' column when the order
	 * carries a subscription handle.
	 */
	public function test_shop_order_custom_columns_reepay_sub_renders_link() {
		$order = $this->order_generator->add_product( 'simple' )->order();
		$order->update_meta_data( '_reepay_subscription_handle', 'sub-handle-2' );
		$order->save_meta_data();

		ob_start();
		$this->frontend->shop_order_custom_columns( 'reepay_sub', $order );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'sub-handle-2', $output );
		$this->assertStringContainsString( '<a', $output );
	}

	/**
	 * Test @see WC_Reepay_Admin_Frontend::shop_order_custom_columns with a
	 * null $order. Confirmed by running this exact test: wc_get_order(null)
	 * resolves to `false` (not an empty order), and the method does not
	 * guard against that before calling ->get_meta() on it inside the
	 * 'order_type'/'order_number'/'reepay_sub' switch branches - so this is a
	 * real latent TypeError for any of the three valid column ids when no
	 * order context is available, not a defect in this test. Documenting the
	 * actual behavior here rather than asserting a false "handles gracefully"
	 * claim, per this phase's "no production code changes" constraint.
	 */
	public function test_shop_order_custom_columns_null_order_throws_on_valid_column() {
		$this->expectException( \Error::class );

		$this->frontend->shop_order_custom_columns( 'order_type', null );
	}

	/*
	 * ------------------------------------------------------------------
	 * admin_shop_order_edit_columns()
	 * ------------------------------------------------------------------
	 */

	/**
	 * Test @see WC_Reepay_Admin_Frontend::admin_shop_order_edit_columns inserts
	 * reepay_sub/order_type columns just before the last existing column for a
	 * user with manage_woocommerce capability and no author filter.
	 */
	public function test_admin_shop_order_edit_columns_inserts_columns() {
		$existing = array(
			'cb'     => '<input type="checkbox" />',
			'number' => 'Order',
			'date'   => 'Date',
		);

		$result = $this->frontend->admin_shop_order_edit_columns( $existing );

		$keys = array_keys( $result );

		$this->assertArrayHasKey( 'reepay_sub', $result );
		$this->assertArrayHasKey( 'order_type', $result );
		$this->assertSame( 'date', end( $keys ), 'The originally-last column must remain last.' );
		$this->assertSame( array( 'cb', 'number', 'reepay_sub', 'order_type', 'date' ), $keys );
	}

	/**
	 * Test @see WC_Reepay_Admin_Frontend::admin_shop_order_edit_columns strips
	 * the reepay_sub/order_type columns back out when a non-empty
	 * $_GET['author'] is present (seller-viewing-own-product case). This reads
	 * $_GET directly (not via filter_input), so a plain assignment under the
	 * Ajax testcase base is sufficient.
	 */
	public function test_admin_shop_order_edit_columns_strips_columns_with_author_filter() {
		$_GET['author'] = '5';

		$existing = array(
			'cb'   => '<input type="checkbox" />',
			'date' => 'Date',
		);

		$result = $this->frontend->admin_shop_order_edit_columns( $existing );

		$this->assertArrayNotHasKey( 'reepay_sub', $result );
		$this->assertArrayNotHasKey( 'order_type', $result );
	}

	/**
	 * Test @see WC_Reepay_Admin_Frontend::admin_shop_order_edit_columns strips
	 * the reepay_sub/order_type columns back out for a user without
	 * manage_woocommerce capability.
	 */
	public function test_admin_shop_order_edit_columns_strips_columns_without_capability() {
		$subscriber_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber_id );

		$existing = array(
			'cb'   => '<input type="checkbox" />',
			'date' => 'Date',
		);

		$result = $this->frontend->admin_shop_order_edit_columns( $existing );

		$this->assertArrayNotHasKey( 'reepay_sub', $result );
		$this->assertArrayNotHasKey( 'order_type', $result );
	}

	/*
	 * ------------------------------------------------------------------
	 * modify_search_results_fields()
	 * ------------------------------------------------------------------
	 */

	/**
	 * Test @see WC_Reepay_Admin_Frontend::modify_search_results_fields rewrites
	 * $orderby when is_admin() is true, the query is the main query, and the
	 * query's post_type is 'shop_order'. WP_Query::is_main_query() compares
	 * itself against the global $wp_the_query (confirmed by reading WP core's
	 * class-wp-query.php) rather than exposing a settable flag, so the global
	 * is pointed at our query instance for the duration of this assertion.
	 */
	public function test_modify_search_results_fields_rewrites_orderby_for_shop_order_main_query() {
		set_current_screen( 'edit-post' );
		$this->assertTrue( is_admin(), 'Precondition: is_admin() must be true for this test to be meaningful.' );

		$query = new WP_Query( array( 'post_type' => 'shop_order' ) );

		global $wp_the_query, $wpdb;
		$previous_main_query = $wp_the_query;
		$wp_the_query        = $query;

		$result = $this->frontend->modify_search_results_fields( 'original_orderby', $query );

		$wp_the_query = $previous_main_query;
		set_current_screen( 'front' );

		$this->assertSame( "$wpdb->posts.*, $wpdb->posts.post_title", $result );
	}

	/**
	 * Test @see WC_Reepay_Admin_Frontend::modify_search_results_fields returns
	 * $orderby unchanged when the query's post_type is not 'shop_order'.
	 */
	public function test_modify_search_results_fields_unchanged_for_non_shop_order_query() {
		$query = new WP_Query( array( 'post_type' => 'post' ) );

		$result = $this->frontend->modify_search_results_fields( 'original_orderby', $query );

		$this->assertSame( 'original_orderby', $result );
	}
}
