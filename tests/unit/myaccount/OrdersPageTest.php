<?php
/**
 * Class OrdersPageTest
 *
 * Covers WC_Reepay_My_Account_Orders_Page
 * (includes/my-account/WC_Reepay_My_Account_Orders_Page.php):
 * add_column_to_account_orders(), add_order_type_to_account_orders() and
 * show_zero_order_total_on_account_orders(). No exit hazards in this file.
 *
 * Phase 5 scope: baseline regression coverage per requirements.md §3.4, not
 * exhaustive scenario enumeration.
 *
 * Confirmed real behavior (read from source):
 * - add_column_to_account_orders($columns) is a pure array-mutation function:
 *   inserts an 'order_type' key and returns the array.
 * - add_order_type_to_account_orders($order) echoes '<span>{$type}</span>'
 *   (asserted via output buffering, not expectOutputString, since both
 *   branches are exercised in the same test run). Three-way branch:
 *     1. $order->get_meta('_reepay_subscription_handle') non-empty -> "Subscription".
 *     2. Else, only IF class_exists('WC_Subscriptions_Product') (i.e. only
 *        when woo_subs is active): inspects the first order item's product via
 *        WC_Subscriptions_Product::is_subscription($product) -> "Subscription";
 *        else if the order has no items -> "Order"; else if the order has
 *        '_reepay_order' meta AND (get_parent_id() != 0 OR '_reepay_is_renewal'
 *        meta is non-empty) -> "Renewal"; else -> "Order".
 *     3. Else (woo_subs not active) -> plain "Order", regardless of any
 *        reepay meta. This elseif-gated branch is correctly guarded with
 *        class_exists() - no fatal risk under woo-only, confirmed by reading
 *        the method body directly (unlike show_zero_order_total_on_account_orders()
 *        below).
 * - show_zero_order_total_on_account_orders($formatted_total, $order) returns
 *   $formatted_total unchanged if !is_account_page(). Otherwise, if the order
 *   has items and the first item's product is truthy, calls
 *   wcs_is_subscription_product($product) UNCONDITIONALLY - no function_exists()
 *   guard. Confirmed: this function is defined only by WooCommerce
 *   Subscriptions, so this genuinely fatals under woo-only whenever
 *   is_account_page() is true AND the order has a real product item. The
 *   "not on account page" branch is the default/easy case in a CLI test
 *   context (is_account_page() is false by default, confirmed by this test's
 *   own default-state test passing without any query manipulation). The
 *   "on account page" + has-items branch is only exercised here when woo_subs
 *   is active (guarded via PLUGINS_STATE::woo_subs_activated()); under
 *   woo-only it is a confirmed-but-not-directly-tested branch (would fatal),
 *   consistent with the "don't guess, note what you couldn't verify"
 *   principle - is_account_page() is a plain conditional tag (not filterable)
 *   based on the queried object, so simulating "on account page" requires
 *   pointing $GLOBALS['wp_query']->queried_object at the real myaccount page,
 *   which is only safe to then also exercise the wcs_ call when woo_subs
 *   is active.
 *
 * @package Reepay\Subscriptions
 */

use Reepay\Subscriptions\Tests\Helpers\PLUGINS_STATE;
use Reepay\Subscriptions\Tests\Helpers\Reepay_Subscriptions_UnitTestCase;

/**
 * OrdersPageTest.
 */
class OrdersPageTest extends Reepay_Subscriptions_UnitTestCase {

	/**
	 * @var WC_Reepay_My_Account_Orders_Page
	 */
	private WC_Reepay_My_Account_Orders_Page $orders_page;

	public function set_up() {
		parent::set_up();

		$this->orders_page = new WC_Reepay_My_Account_Orders_Page();
	}

	/**
	 * Point the main query at the My Account page so is_account_page() returns
	 * true, matching how WooCommerce itself determines the conditional tag
	 * (comparing the queried object's ID against wc_get_page_id('myaccount')).
	 * Test-only global manipulation, no production file touched.
	 */
	private function go_to_account_page(): void {
		// A fresh WP test bootstrap does not necessarily have WooCommerce's
		// default pages (myaccount, shop, cart, checkout) created yet -
		// confirmed by debugging: wc_get_page_id('myaccount') returns -1
		// without this call, which otherwise silently makes is_account_page()
		// always false and defeats this helper's purpose. Test-only setup,
		// no production file touched.
		WC_Install::create_pages();

		$account_page_id = wc_get_page_id( 'myaccount' );

		$this->go_to( get_permalink( $account_page_id ) );
	}

	/*
	 * ------------------------------------------------------------------
	 * add_column_to_account_orders()
	 * ------------------------------------------------------------------
	 */

	/**
	 * Test @see WC_Reepay_My_Account_Orders_Page::add_column_to_account_orders
	 * inserts the 'order_type' column and preserves existing columns.
	 */
	public function test_add_column_to_account_orders_inserts_order_type() {
		$columns = array( 'order-number' => 'Order' );

		$result = $this->orders_page->add_column_to_account_orders( $columns );

		$this->assertSame( 'Order', $result['order-number'] );
		$this->assertArrayHasKey( 'order_type', $result );
	}

	/*
	 * ------------------------------------------------------------------
	 * add_order_type_to_account_orders()
	 * ------------------------------------------------------------------
	 */

	/**
	 * Test @see WC_Reepay_My_Account_Orders_Page::add_order_type_to_account_orders
	 * echoes "Subscription" when the order carries a top-level subscription
	 * handle, regardless of woo_subs activation.
	 */
	public function test_add_order_type_subscription_handle_meta() {
		$order = $this->order_generator->add_product( 'simple' )->order();
		$order->update_meta_data( '_reepay_subscription_handle', 'sub-handle-1' );
		$order->save_meta_data();

		ob_start();
		$this->orders_page->add_order_type_to_account_orders( $order );
		$output = ob_get_clean();

		$this->assertSame( '<span>Subscription</span>', $output );
	}

	/**
	 * Test @see WC_Reepay_My_Account_Orders_Page::add_order_type_to_account_orders
	 * falls back to plain "Order" when woo_subs is NOT active, even if the
	 * order has reepay meta that would otherwise classify it as a Renewal -
	 * confirmed: branch 3 (plain else) ignores all reepay-specific meta.
	 */
	public function test_add_order_type_plain_order_without_woo_subs() {
		if ( PLUGINS_STATE::woo_subs_activated() ) {
			$this->markTestSkipped( 'This test exercises the woo_subs-absent branch only.' );
		}

		$order = $this->order_generator->add_product( 'simple' )->order();
		$order->update_meta_data( '_reepay_order', 'inv-1' );
		$order->save_meta_data();

		ob_start();
		$this->orders_page->add_order_type_to_account_orders( $order );
		$output = ob_get_clean();

		$this->assertSame( '<span>Order</span>', $output );
	}

	/**
	 * Test @see WC_Reepay_My_Account_Orders_Page::add_order_type_to_account_orders
	 * "Renewal" branch: requires woo_subs active (class_exists guard), a
	 * non-subscription product, non-empty '_reepay_order' meta, and either a
	 * non-zero parent id or '_reepay_is_renewal' meta.
	 */
	public function test_add_order_type_renewal_with_woo_subs() {
		if ( ! PLUGINS_STATE::woo_subs_activated() ) {
			$this->markTestSkipped( 'WooCommerce Subscriptions required for the Renewal branch.' );
		}

		$order = $this->order_generator->add_product( 'simple' )->order();
		$order->update_meta_data( '_reepay_order', 'inv-2' );
		$order->update_meta_data( '_reepay_is_renewal', 1 );
		$order->save_meta_data();

		ob_start();
		$this->orders_page->add_order_type_to_account_orders( $order );
		$output = ob_get_clean();

		$this->assertSame( '<span>Renewal</span>', $output );
	}

	/**
	 * Test @see WC_Reepay_My_Account_Orders_Page::add_order_type_to_account_orders
	 * "Order" fallback with woo_subs active, a non-subscription product and no
	 * reepay meta at all.
	 */
	public function test_add_order_type_plain_order_with_woo_subs_no_meta() {
		if ( ! PLUGINS_STATE::woo_subs_activated() ) {
			$this->markTestSkipped( 'WooCommerce Subscriptions required for this branch of the woo_subs-active path.' );
		}

		$order = $this->order_generator->add_product( 'simple' )->order();

		ob_start();
		$this->orders_page->add_order_type_to_account_orders( $order );
		$output = ob_get_clean();

		$this->assertSame( '<span>Order</span>', $output );
	}

	/**
	 * Test @see WC_Reepay_My_Account_Orders_Page::add_order_type_to_account_orders
	 * "Order" fallback with woo_subs active and an order with no line items at
	 * all (the nested "no order items" branch inside the class_exists() check).
	 */
	public function test_add_order_type_plain_order_with_woo_subs_no_items() {
		if ( ! PLUGINS_STATE::woo_subs_activated() ) {
			$this->markTestSkipped( 'WooCommerce Subscriptions required for this branch of the woo_subs-active path.' );
		}

		$order = wc_create_order();

		ob_start();
		$this->orders_page->add_order_type_to_account_orders( $order );
		$output = ob_get_clean();

		$this->assertSame( '<span>Order</span>', $output );
	}

	/*
	 * ------------------------------------------------------------------
	 * show_zero_order_total_on_account_orders()
	 * ------------------------------------------------------------------
	 */

	/**
	 * Test @see WC_Reepay_My_Account_Orders_Page::show_zero_order_total_on_account_orders
	 * returns $formatted_total unchanged when not on the account page - the
	 * default state for a CLI/test context (is_account_page() is false), so
	 * this is always safe regardless of plugin combo.
	 */
	public function test_show_zero_order_total_unchanged_when_not_on_account_page() {
		$this->assertFalse( is_account_page(), 'Precondition: default test context should not be on the account page.' );

		$order     = $this->order_generator->add_product( 'simple' )->order();
		$formatted = wc_price( $order->get_total() );

		$result = $this->orders_page->show_zero_order_total_on_account_orders( $formatted, $order );

		$this->assertSame( $formatted, $result );
	}

	/**
	 * Test @see WC_Reepay_My_Account_Orders_Page::show_zero_order_total_on_account_orders
	 * returns $formatted_total unchanged when on the account page but the order
	 * has no line items at all - safe under any plugin combo since the
	 * unconditional wcs_is_subscription_product() call site is only reached
	 * when $order_items is non-empty.
	 */
	public function test_show_zero_order_total_unchanged_on_account_page_without_items() {
		$this->go_to_account_page();
		$this->assertTrue( is_account_page(), 'Precondition: queried object must resolve to the My Account page.' );

		$order     = wc_create_order();
		$formatted = wc_price( $order->get_total() );

		$result = $this->orders_page->show_zero_order_total_on_account_orders( $formatted, $order );

		$this->assertSame( $formatted, $result );
	}

	/**
	 * Test @see WC_Reepay_My_Account_Orders_Page::show_zero_order_total_on_account_orders
	 * zeroes the formatted total for a real subscription-product order on the
	 * account page. Requires woo_subs active (wcs_is_subscription_product() is
	 * only defined by WooCommerce Subscriptions, no guard in production code -
	 * confirmed fatal under woo-only, so this branch is only run here when
	 * woo_subs is active).
	 */
	public function test_show_zero_order_total_zeroed_for_subscription_product_on_account_page() {
		// CONFIRMED by running the full 5-combination matrix, in THREE
		// stages (neither check alone is sufficient - both are required):
		// (1) PHPUNIT_PLUGINS=woo,woo_subs (no reepay_pay): class_exists
		// ('WC_Subscriptions_Product', false) is TRUE (that class loads
		// independently) but function_exists('wcs_is_subscription_product')
		// is FALSE - the real woo_subs global functions file doesn't load in
		// this specific combo.
		// (2) PHPUNIT_PLUGINS=woo,reepay_pay (no woo_subs): the inverse -
		// function_exists('wcs_is_subscription_product') is TRUE (satisfied
		// by reepay-woocommerce-payment's own function_exists()-guarded shim
		// in includes/Functions/subscriptions.php) but class_exists
		// ('WC_Subscriptions_Product', false) is FALSE (woo_subs was never
		// included at all), so a real 'woo_sub' product fixture (below)
		// can't be built.
		// Only requiring BOTH checks together correctly skips in both of
		// those combos and correctly runs under the full combo (where both
		// are true).
		if ( ! function_exists( 'wcs_is_subscription_product' ) || ! class_exists( 'WC_Subscriptions_Product', false ) ) {
			$this->markTestSkipped( 'WooCommerce Subscriptions required: this test needs both a callable wcs_is_subscription_product() AND a real woo_sub product fixture, which reepay-woocommerce-payment\'s shim alone cannot provide.' );
		}

		$this->go_to_account_page();
		$this->assertTrue( is_account_page(), 'Precondition: queried object must resolve to the My Account page.' );

		$order     = $this->order_generator->add_product( 'woo_sub' )->order();
		$formatted = wc_price( $order->get_total() );

		$result = $this->orders_page->show_zero_order_total_on_account_orders( $formatted, $order );

		$this->assertSame( wc_price( 0 ), $result );
	}

	/**
	 * Test @see WC_Reepay_My_Account_Orders_Page::show_zero_order_total_on_account_orders
	 * leaves the formatted total unchanged on the account page for a
	 * non-subscription product, when woo_subs is active (so the unconditional
	 * wcs_is_subscription_product() call is safe to reach but returns false).
	 */
	public function test_show_zero_order_total_unchanged_for_simple_product_on_account_page_with_woo_subs() {
		// Unlike the test above, this one only builds a 'simple' product, so
		// it doesn't need the real WC_Subscriptions_Product class - it only
		// needs wcs_is_subscription_product() to be safely callable (real or
		// the reepay-woocommerce-payment shim both work fine here, since
		// either way it correctly returns false for a non-subscription
		// product). function_exists() is the correct, less restrictive
		// guard for this specific test.
		if ( ! function_exists( 'wcs_is_subscription_product' ) ) {
			$this->markTestSkipped( 'WooCommerce Subscriptions required to safely reach wcs_is_subscription_product().' );
		}

		$this->go_to_account_page();

		$order     = $this->order_generator->add_product( 'simple' )->order();
		$formatted = wc_price( $order->get_total() );

		$result = $this->orders_page->show_zero_order_total_on_account_orders( $formatted, $order );

		$this->assertSame( $formatted, $result );
	}
}
