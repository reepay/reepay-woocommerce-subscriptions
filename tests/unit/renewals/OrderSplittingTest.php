<?php
/**
 * Class OrderSplittingTest
 *
 * Covers the order-splitting and order-copying methods of WC_Reepay_Renewals:
 * get_division_of_products_into_orders, create_child_order, get_child_order,
 * create_order_copy and is_exist_addon_type_on_off_in_addons.
 *
 * @package Reepay\Subscriptions
 */

use Reepay\Subscriptions\Tests\Helpers\OrderItemsGenerator;
use Reepay\Subscriptions\Tests\Helpers\PLUGINS_STATE;
use Reepay\Subscriptions\Tests\Helpers\Reepay_Subscriptions_UnitTestCase;
use Reepay\Subscriptions\Tests\Mocks\ApiResponseMock;

/**
 * OrderSplittingTest.
 */
class OrderSplittingTest extends Reepay_Subscriptions_UnitTestCase {

	/**
	 * @var WC_Reepay_Renewals
	 */
	private WC_Reepay_Renewals $renewals;

	public function set_up() {
		parent::set_up();

		$this->renewals = new WC_Reepay_Renewals();

		// Same test-only wiring as LifecycleTest::set_up() (see that file for the
		// full rationale): WC_Reepay_Subscription_API (reepay_s()->api()) and the
		// Frisbii Pay Api class (reepay()->api()) both read their key from a
		// settings snapshot cached once at bootstrap, so updating the
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

		// Order creation/status-setting in create_order_copy()/create_child_order()
		// can trigger WooCommerce's transactional order emails (even when the test
		// doesn't explicitly expect a status *change* - e.g. wc_create_order()'s
		// default status followed by set_status() still counts as a transition).
		// Rendering those emails calls
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
	 * reflection. Copied verbatim from LifecycleTest - used only to inject a fake
	 * API key into the plugins' settings singletons, which cache their values
	 * once at bootstrap time, long before any option set in a test's set_up()
	 * would be read.
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
	 * is_exist_addon_type_on_off_in_addons()
	 * ------------------------------------------------------------------
	 *
	 * Real behavior (WC_Reepay_Renewals.php:703-705):
	 *   return ! is_string( $addons ) && in_array( 'on_off', array_column( $addons, 'type' ) );
	 * i.e. it is a table: a string value (WC "addons" meta sometimes serializes
	 * to an empty string '' when absent) always yields false regardless of
	 * content; otherwise it looks for an element whose 'type' key is 'on_off'.
	 */

	/**
	 * Test @see WC_Reepay_Renewals::is_exist_addon_type_on_off_in_addons true when
	 * one of the addon rows has type === 'on_off' (shape taken from
	 * WC_Reepay_Subscription_Addons_Shipping::save_options(), which builds
	 * ['name' => ..., 'type' => 'on_off', ...] for the shipping addon).
	 */
	public function test_is_exist_addon_type_on_off_true_when_present() {
		$addons = array(
			array( 'name' => 'Recurring addon', 'type' => 'recurring' ),
			array( 'name' => 'Shipping addon', 'type' => 'on_off' ),
		);

		$this->assertTrue( WC_Reepay_Renewals::is_exist_addon_type_on_off_in_addons( $addons ) );
	}

	/**
	 * Test @see WC_Reepay_Renewals::is_exist_addon_type_on_off_in_addons false when
	 * addons is a non-empty array but none of the rows are of type 'on_off'.
	 */
	public function test_is_exist_addon_type_on_off_false_when_absent() {
		$addons = array(
			array( 'name' => 'Recurring addon', 'type' => 'recurring' ),
			array( 'name' => 'Metered addon', 'type' => 'metered' ),
		);

		$this->assertFalse( WC_Reepay_Renewals::is_exist_addon_type_on_off_in_addons( $addons ) );
	}

	/**
	 * Test @see WC_Reepay_Renewals::is_exist_addon_type_on_off_in_addons false for
	 * an empty array (no addons at all).
	 */
	public function test_is_exist_addon_type_on_off_false_for_empty_array() {
		$this->assertFalse( WC_Reepay_Renewals::is_exist_addon_type_on_off_in_addons( array() ) );
	}

	/**
	 * Test @see WC_Reepay_Renewals::is_exist_addon_type_on_off_in_addons false when
	 * $addons is a string - this is the real-world case where WC_Order_Item::get_meta('addons')
	 * returns '' for an order item that never had the 'addons' meta set at all.
	 * The is_string() guard short-circuits before array_column() would otherwise
	 * emit a warning/TypeError on a string argument.
	 */
	public function test_is_exist_addon_type_on_off_false_when_addons_is_string() {
		$this->assertFalse( WC_Reepay_Renewals::is_exist_addon_type_on_off_in_addons( '' ) );
		$this->assertFalse( WC_Reepay_Renewals::is_exist_addon_type_on_off_in_addons( 'on_off' ) );
	}

	/*
	 * ------------------------------------------------------------------
	 * get_division_of_products_into_orders()
	 * ------------------------------------------------------------------
	 *
	 * Real behavior (WC_Reepay_Renewals.php:613-696), confirmed by tracing the
	 * loop logic directly: it walks $order_items, skipping any item whose
	 * product is missing or is not "reepay" per WC_Reepay_Checkout::is_reepay_product()
	 * (i.e. get_type() containing the substring "reepay" - simple/woo_sub
	 * products are NOT reepay products and are left untouched on $order_items
	 * AND on $main_order). For each reepay item, it re-checks
	 * count($order_items) (the *live*, mutating array - items already split out
	 * are unset() from it, but skipped non-reepay items never are). If that
	 * count is <= 1 the loop `break`s immediately - the last remaining reepay
	 * item is deliberately NOT split out and stays on $main_order. Otherwise
	 * the item is removed from $main_order and copied into a brand-new child
	 * order via create_order_copy(), tagged _reepay_is_subscription=1 and
	 * _reepay_order=''.
	 *
	 * Concretely, for 3 reepay-only items [A, B, C]: A and B are split into
	 * their own orders, C is left on the main order (verified via a standalone
	 * PHP trace of the exact unset()/count() sequence before writing this test).
	 */

	/**
	 * Test @see WC_Reepay_Renewals::get_division_of_products_into_orders with 3
	 * reepay-type line items: exactly 2 child orders are created (the first two
	 * items encountered), each with exactly 1 product line matching the
	 * original item, while the main order keeps the 3rd (last) item.
	 */
	public function test_get_division_splits_all_but_last_reepay_item() {
		PLUGINS_STATE::maybe_skip_test_by_product_type( 'rp_sub' );

		$order = $this->order_generator
			->add_product( 'rp_sub', array( 'name' => 'Sub A' ) )
			->add_product( 'rp_sub', array( 'name' => 'Sub B' ) )
			->add_product( 'rp_sub', array( 'name' => 'Sub C' ) )
			->order();

		$order_items = OrderItemsGenerator::for_order( $order );
		$this->assertCount( 3, $order_items, 'Fixture sanity check.' );

		list( $created_orders, $created_order_ids ) = $this->renewals->get_division_of_products_into_orders( $order_items, $order );

		$this->assertCount( 2, $created_orders, 'The first 2 of 3 reepay items should be split into their own orders.' );
		$this->assertCount( 2, $created_order_ids );

		foreach ( $created_orders as $created_order ) {
			$this->assertEquals( 1, $created_order->get_meta( '_reepay_is_subscription' ) );
			$this->assertSame( '', $created_order->get_meta( '_reepay_order' ) );
			$items = $created_order->get_items();
			$this->assertCount( 1, $items, 'Each split order should carry exactly the one item moved into it.' );
		}

		// NOTE on what is NOT asserted here: the method calls
		// $main_order->remove_item( $order_item_key ) in-memory for A and B, but
		// never calls $main_order->save() on that same object before
		// create_order_copy() internally does `$main_order = wc_get_order( $main_order )`
		// (WC_Reepay_Renewals.php:1572) - a fresh read from the DB that still has
		// all 3 original items, and create_order_copy() then calls save() on THAT
		// fresh copy. Net effect: the in-memory removals are lost and the
		// persisted main order keeps all 3 original items. Confirmed by direct
		// inspection (re-fetching the order after the call returns all 3 items),
		// not guessed - this looks like a latent copy/save-order bug in production
		// code, but per the task's constraints this test only documents real
		// behavior and must not modify includes/WC_Reepay_Renewals.php.
		$main_order = wc_get_order( $order->get_id() );
		$this->assertCount(
			3,
			$main_order->get_items(),
			'Known behavior: create_order_copy() re-fetches $main_order from the DB internally, so the in-memory remove_item() calls made by get_division_of_products_into_orders() are never persisted - all original items remain on the main order row.'
		);
	}

	/**
	 * Test @see WC_Reepay_Renewals::get_division_of_products_into_orders with a
	 * single reepay item: the loop breaks on the very first iteration
	 * (count <= 1), so no child orders are created at all and the main order is
	 * untouched.
	 */
	public function test_get_division_single_item_creates_no_child_orders() {
		PLUGINS_STATE::maybe_skip_test_by_product_type( 'rp_sub' );

		$order = $this->order_generator->add_product( 'rp_sub' )->order();

		$order_items = OrderItemsGenerator::for_order( $order );

		list( $created_orders, $created_order_ids ) = $this->renewals->get_division_of_products_into_orders( $order_items, $order );

		$this->assertSame( array(), $created_orders );
		$this->assertSame( array(), $created_order_ids );

		$main_order = wc_get_order( $order->get_id() );
		$this->assertCount( 1, $main_order->get_items(), 'Main order item must not be removed.' );
	}

	/**
	 * Test @see WC_Reepay_Renewals::get_division_of_products_into_orders with a
	 * mix of one non-reepay ('simple') item and two reepay items: the simple
	 * item is never unset() from the working array, so count() never drops to 1
	 * while a reepay item is being considered - BOTH reepay items get split out,
	 * and only the simple item remains on the main order. This is the
	 * non-obvious case the "last item wins" rule misses if you don't actually
	 * trace the loop.
	 */
	public function test_get_division_ignores_non_reepay_items_when_counting() {
		PLUGINS_STATE::maybe_skip_test_by_product_type( 'rp_sub' );

		$order = $this->order_generator
			->add_product( 'simple', array( 'name' => 'Plain product' ) )
			->add_product( 'rp_sub', array( 'name' => 'Sub A' ) )
			->add_product( 'rp_sub', array( 'name' => 'Sub B' ) )
			->order();

		$order_items = OrderItemsGenerator::for_order( $order );
		$this->assertCount( 3, $order_items, 'Fixture sanity check.' );

		list( $created_orders, $created_order_ids ) = $this->renewals->get_division_of_products_into_orders( $order_items, $order );

		$this->assertCount( 2, $created_orders, 'Both reepay items should be split out because the simple item never decrements the live count.' );
		$this->assertCount( 2, $created_order_ids );

		// Same caveat as test_get_division_splits_all_but_last_reepay_item: the
		// in-memory remove_item() calls are never persisted because
		// create_order_copy() re-fetches $main_order from the DB before its own
		// save(). All 3 original items (including the 2 split-out reepay ones)
		// remain in the DB row.
		$main_order = wc_get_order( $order->get_id() );
		$this->assertCount( 3, $main_order->get_items() );
	}

	/**
	 * Test @see WC_Reepay_Renewals::get_division_of_products_into_orders returns
	 * no child orders for an order made up entirely of non-reepay items (every
	 * item is skipped by the is_reepay_product() check).
	 */
	public function test_get_division_no_reepay_items_creates_nothing() {
		$order = $this->order_generator
			->add_product( 'simple' )
			->add_product( 'simple' )
			->order();

		$order_items = OrderItemsGenerator::for_order( $order );

		list( $created_orders, $created_order_ids ) = $this->renewals->get_division_of_products_into_orders( $order_items, $order );

		$this->assertSame( array(), $created_orders );
		$this->assertSame( array(), $created_order_ids );

		$main_order = wc_get_order( $order->get_id() );
		$this->assertCount( 2, $main_order->get_items(), 'Non-reepay items must be left untouched.' );
	}

	/*
	 * ------------------------------------------------------------------
	 * create_order_copy()
	 * ------------------------------------------------------------------
	 *
	 * Real signature (WC_Reepay_Renewals.php:1557-1563):
	 *   create_order_copy( $order_args, $main_order = false, $items = [], $calc_taxes = true, $invoice_data = false )
	 *
	 * When $main_order is truthy, it copies a long list of fields (billing/
	 * shipping address, currency, order key, payment method, several _reepay_*
	 * meta keys) from $main_order onto a freshly created order, then re-adds
	 * each of $items as a brand-new WC_Order_Item_Product/_Fee/_Coupon/_Shipping
	 * (matched by $item->is_type(...)), then applies $order_args['status'] at
	 * the very end via set_status()+save(). Deliberately avoiding setting
	 * 'reepay_session_id' meta on the source order in these tests, since that
	 * meta (if present) triggers an extra reepay()->api()->get_session_events()
	 * call inside create_order_copy() guarded only by function_exists('reepay') -
	 * untestable without the Frisbii Pay plugin and irrelevant to what this
	 * checklist item is about (line/total/meta copying).
	 */

	/**
	 * Test @see WC_Reepay_Renewals::create_order_copy copies line items, their
	 * totals, and a representative set of meta/billing fields from the source
	 * order onto the new order, and applies the requested status.
	 */
	public function test_create_order_copy_copies_items_totals_and_meta() {
		$source = $this->order_generator
			->add_product( 'simple', array(), 2 )
			->set_prop( 'billing_first_name', 'Jane' )
			->set_prop( 'billing_email', 'jane@example.com' )
			->set_prop( 'payment_method', 'reepay_checkout' )
			->set_prop( 'payment_method_title', 'Frisbii Pay' )
			->order();

		$source->update_meta_data( '_reepay_token', 'tok-copy-1' );
		$source->update_meta_data( '_reepay_customer', 'cust-copy-1' );
		$source->save_meta_data();

		$source_items = $source->get_items();
		$source_item  = reset( $source_items );
		$source_total = $source_item->get_total();

		$new_order = WC_Reepay_Renewals::create_order_copy(
			array(
				'status'      => 'processing',
				'customer_id' => $source->get_customer_id(),
			),
			$source,
			array( $source_item )
		);

		$this->assertInstanceOf( WC_Order::class, $new_order );
		$this->assertSame( 'processing', $new_order->get_status() );

		$new_items = $new_order->get_items();
		$this->assertCount( 1, $new_items, 'Only the explicitly passed item should be copied, not all of $main_order\'s items.' );
		$new_item = reset( $new_items );
		$this->assertSame( $source_item->get_name(), $new_item->get_name() );
		$this->assertEquals( $source_item->get_quantity(), $new_item->get_quantity() );
		$this->assertEquals( (float) $source_total, (float) $new_item->get_total() );

		$this->assertSame( 'jane@example.com', $new_order->get_billing_email() );
		$this->assertSame( 'Jane', $new_order->get_billing_first_name() );
		$this->assertSame( 'reepay_checkout', $new_order->get_payment_method() );
		$this->assertSame( 'Frisbii Pay', $new_order->get_payment_method_title() );
		$this->assertSame( $source->get_currency(), $new_order->get_currency() );
		$this->assertSame( $source->get_order_key(), $new_order->get_order_key() );
		$this->assertSame( 'tok-copy-1', $new_order->get_meta( '_reepay_token' ) );
		$this->assertSame( 'cust-copy-1', $new_order->get_meta( '_reepay_customer' ) );
	}

	/**
	 * Test @see WC_Reepay_Renewals::create_order_copy without a $main_order (the
	 * "import from invoice_data" branch): billing fields are populated from the
	 * customer fetched via reepay_s()->api()->request('customer/...'), the
	 * payment method is hardcoded to 'reepay_checkout', and _reepay_state_authorized
	 * is set to 1.
	 */
	public function test_create_order_copy_without_main_order_imports_from_invoice_data() {
		// Real production behavior: create_order_copy() calls
		// $new_order->update_meta_data( '_order_currency', ... ) directly
		// (WC_Reepay_Renewals.php:1799) instead of set_currency(), which WC flags
		// as a doing_it_wrong usage of the generic meta API on an internal meta
		// key. This is pre-existing behavior in the method under test, not
		// something this test introduces - acknowledge it rather than let it
		// fail the test or silently suppress real notices.
		$this->setExpectedIncorrectUsage( 'is_internal_meta_key' );

		ApiResponseMock::respond_with(
			array(
				'first_name' => 'Imported',
				'last_name'  => 'Customer',
				'email'      => 'imported@example.com',
				'city'       => 'Copenhagen',
				'country'    => 'DK',
			)
		);

		$new_order = WC_Reepay_Renewals::create_order_copy(
			array( 'status' => 'pending' ),
			false,
			array(),
			true,
			array(
				'customer' => 'cust-import-1',
				'currency' => 'DKK',
			)
		);

		$this->assertInstanceOf( WC_Order::class, $new_order );
		$this->assertSame( 'pending', $new_order->get_status() );
		$this->assertSame( 'imported@example.com', $new_order->get_billing_email() );
		$this->assertSame( 'Imported', $new_order->get_billing_first_name() );
		$this->assertSame( 'Copenhagen', $new_order->get_billing_city() );
		$this->assertSame( 'reepay_checkout', $new_order->get_payment_method() );
		$this->assertSame( 'Reepay Checkout', $new_order->get_payment_method_title() );
		$this->assertEquals( 1, $new_order->get_meta( '_reepay_state_authorized' ) );
		$this->assertSame( 'DKK', $new_order->get_currency() );
	}

	/*
	 * ------------------------------------------------------------------
	 * create_child_order() / get_child_order()
	 * ------------------------------------------------------------------
	 *
	 * Both unconditionally call rp_hpos_enabled(), a function defined only by
	 * the Frisbii Pay plugin (includes/Functions/hpos.php) - calling either
	 * method without reepay_pay active is a fatal "undefined function" error,
	 * not a graceful no-op. Both are skipped under the woo-only combo, mirroring
	 * LifecycleTest::test_renew_subscription_creates_child_order_under_parent's
	 * skip condition.
	 */

	/**
	 * Test @see WC_Reepay_Renewals::create_child_order creates a new WC_Order
	 * parented to the order referenced by $data['order_id'], with the requested
	 * status, and links the parent via '_reepay_order' meta (read by
	 * get_child_order()'s lookup, confirming the two methods agree on the same
	 * linkage key).
	 */
	public function test_create_child_order_creates_order_with_parent_and_status() {
		if ( ! PLUGINS_STATE::reepay_pay_activated() ) {
			$this->markTestSkipped( 'Frisbii Pay (reepay_pay) plugin required: create_child_order() calls rp_hpos_enabled().' );
		}

		$parent_order = $this->order_generator
			->add_product( 'rp_sub' )
			->set_prop( 'payment_method', 'reepay_checkout' )
			->set_prop( 'status', 'processing' )
			->order();

		ApiResponseMock::respond_with(
			array(
				'state'    => 'settled',
				'customer' => 'cust-child-1',
				// No 'order_lines' - exercises the "no invoice lines" path, as in
				// LifecycleTest::test_renew_subscription_creates_child_order_under_parent.
			)
		);

		WC_Reepay_Renewals::create_child_order(
			array(
				'order_id' => $parent_order->get_id(),
				'invoice'  => 'inv-child-1',
			),
			'wc-completed'
		);

		$parent_order = wc_get_order( $parent_order->get_id() );
		$this->assertSame( 'inv-child-1', $parent_order->get_meta( '_reepay_order' ), 'Parent order should be tagged with the new invoice handle.' );

		$children = wc_get_orders( array( 'parent' => $parent_order->get_id() ) );
		$this->assertCount( 1, $children, 'Exactly one child order should be created.' );

		$child = $children[0];
		$this->assertSame( 'completed', $child->get_status() );
		$this->assertSame( 'inv-child-1', $child->get_meta( '_reepay_order' ) );
	}

	/**
	 * Test @see WC_Reepay_Renewals::create_child_order does not create a
	 * duplicate child order when one already exists for the same parent +
	 * invoice handle combination (the method's own existing-order guard).
	 */
	public function test_create_child_order_skips_duplicate_for_same_invoice() {
		if ( ! PLUGINS_STATE::reepay_pay_activated() ) {
			$this->markTestSkipped( 'Frisbii Pay (reepay_pay) plugin required: create_child_order() calls rp_hpos_enabled().' );
		}

		$parent_order = $this->order_generator
			->add_product( 'rp_sub' )
			->set_prop( 'payment_method', 'reepay_checkout' )
			->set_prop( 'status', 'processing' )
			->order();

		// Pre-create a child order with the same invoice handle under the same parent.
		$existing_child = wc_create_order( array( 'parent' => $parent_order->get_id() ) );
		$existing_child->update_meta_data( '_reepay_order', 'inv-dup-1' );
		$existing_child->save_meta_data();

		ApiResponseMock::respond_with( array( 'state' => 'settled', 'customer' => 'cust-dup-1' ) );

		WC_Reepay_Renewals::create_child_order(
			array(
				'order_id' => $parent_order->get_id(),
				'invoice'  => 'inv-dup-1',
			),
			'wc-completed'
		);

		$children = wc_get_orders( array( 'parent' => $parent_order->get_id() ) );
		$this->assertCount( 1, $children, 'No second child order should be created for a duplicate invoice handle.' );
	}

	/**
	 * Test @see WC_Reepay_Renewals::get_child_order returns the matching child
	 * order when one exists with the given invoice handle under the given
	 * parent.
	 */
	public function test_get_child_order_returns_matching_order() {
		if ( ! PLUGINS_STATE::reepay_pay_activated() ) {
			$this->markTestSkipped( 'Frisbii Pay (reepay_pay) plugin required: get_child_order() calls rp_hpos_enabled().' );
		}

		$parent_order = $this->order_generator->add_product( 'rp_sub' )->order();

		$child = wc_create_order( array( 'parent' => $parent_order->get_id() ) );
		$child->update_meta_data( '_reepay_order', 'inv-lookup-1' );
		$child->save_meta_data();

		$parent_order = wc_get_order( $parent_order->get_id() );

		$found = $this->renewals->get_child_order( $parent_order, 'inv-lookup-1' );

		$this->assertInstanceOf( WC_Order::class, $found );
		$this->assertSame( $child->get_id(), $found->get_id() );
	}

	/**
	 * Test @see WC_Reepay_Renewals::get_child_order returns exactly `false` (not
	 * null, not an empty array) when no child order matches the given invoice
	 * handle under the given parent - the method's own "not found" branch is
	 * `! empty( $query ) ? $query[0] : false`.
	 */
	public function test_get_child_order_returns_false_when_not_found() {
		if ( ! PLUGINS_STATE::reepay_pay_activated() ) {
			$this->markTestSkipped( 'Frisbii Pay (reepay_pay) plugin required: get_child_order() calls rp_hpos_enabled().' );
		}

		$parent_order = $this->order_generator->add_product( 'rp_sub' )->order();

		$found = $this->renewals->get_child_order( $parent_order, 'inv-does-not-exist' );

		$this->assertFalse( $found, 'Not-found result must be exactly false.' );
	}
}
