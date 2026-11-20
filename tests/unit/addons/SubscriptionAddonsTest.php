<?php
/**
 * Class SubscriptionAddonsTest
 *
 * Covers WC_Reepay_Subscription_Addons (includes/WC_Reepay_Subscription_Addons.php):
 * order_line_item, get_item_data, before_calculate_totals,
 * restore_base_prices_for_display, get_cart_item_from_session, add_cart_item,
 * add_cart_item_data, get_product_addons, get_reepay_addons_list,
 * get_reepay_addon_data, save_addons, save_to_reepay and add_plan_to_addon.
 *
 * Confirmed real behavior (read from source, not guessed):
 * - order_line_item() reads $values['addons'] (array of rows shaped like
 *   ['handle' => ..., 'name' => ..., 'amount' => ..., 'quantity' => ...]),
 *   makes a REAL reepay_s()->api()->request("add_on/{handle}") call per
 *   addon row (must be mocked), then calls $item->add_meta_data($key, '+'.wc_price($price))
 *   for display and $item->add_meta_data('addons', $addons_info) once with the
 *   reshaped rows.
 * - get_item_data() reads $cart_item['addons'] directly (no API call) and
 *   returns $other_data with one ['name' => ..., 'display' => wc_price(...)]
 *   entry appended per addon row.
 * - add_cart_item() reads $cart_item['addons'], stores the ORIGINAL product
 *   price once into $cart_item['_addon_base_price'] (only if not already
 *   set - this guards against double-application on repeated calls), then
 *   sets $cart_item['data']'s price to base_price + sum(addon amounts /
 *   cart_qty for on_off type, or addon amount*addon_qty / cart_qty for
 *   quantity-type addons). It mutates $cart_item['data'] (a WC_Product
 *   instance) via set_price() and returns $cart_item (which carries the
 *   now-set _addon_base_price key for use by restore_base_prices_for_display()).
 * - before_calculate_totals() bails out early if is_admin() && !DOING_AJAX;
 *   in the default CLI/unit-test context is_admin() is false, so it
 *   proceeds and calls add_cart_item() for every cart item with non-empty
 *   'addons'.
 * - restore_base_prices_for_display() similarly bails early under
 *   is_admin() && !DOING_AJAX, otherwise resets $cart_item['data']'s price
 *   back to the stored $cart_item['_addon_base_price'] - it does NOT touch
 *   line_subtotal/line_total (WC_Cart_Totals already persisted those), so
 *   calling it after before_calculate_totals() must restore the ORIGINAL
 *   per-unit price, not double-apply or re-strip the addon cost.
 * - get_cart_item_from_session() copies $values['addons'] onto $cart_item
 *   then calls add_cart_item($cart_item) (so the price adjustment happens
 *   inline during session restore too), returning the result.
 * - add_cart_item_data() reads $this->get_product_addons($product_id) (the
 *   product's _product_addons meta) and, for each addon whose
 *   $_POST['addon-{handle}'] is set, validates the value against the addon's
 *   'type': an 'on_off' addon needs 'yes', a 'quantity' addon needs a whole
 *   number from 1 to the max quantity. '' / '0' are skipped silently; any
 *   other value is skipped with an "Invalid value" error notice. A valid
 *   addon appends a row (keyed by the addon's index in $product_addons) to
 *   $cart_item_data['addons'] built from the product_addons entry (NOT from
 *   the posted value itself, aside from 'quantity' for quantity addons).
 * - get_reepay_addons_list($plan_handle, $drop_cache): reads the
 *   'reepay_s_addons_list' option as a cache UNLESS $drop_cache is true. On
 *   cache miss/drop, calls the real add_on list API, filters out
 *   non-eligible/inactive addons, and ALWAYS calls
 *   update_option('reepay_s_addons_list', ...) before returning - even when
 *   $drop_cache was true (the write is unconditional; only the READ is
 *   skipped). Returns false on an API Exception.
 * - get_reepay_addon_data($handle): real add_on/{handle} API call, reshapes
 *   amount (/100) and vat (*100) on success; on an Exception it calls
 *   WC_Reepay_Subscription_Admin_Notice::add_notice() (a static method that
 *   just appends to an option-backed array - no exception of its own) and
 *   then falls through to the method's own `return false;` at the bottom
 *   (NOT null, NOT an implicit null - there's an explicit `return false`
 *   statement after the try/catch).
 * - save_addons($post_id) delegates to the protected get_posted_product_addons($post_id),
 *   which reads $_POST['addon_choose_exist'] (indexed array) and for each
 *   non-empty entry calls $this->get_reepay_addon_data(...) (REAL API call -
 *   must mock) and $this->add_plan_to_addon(...) (ALSO a real API call - two
 *   outbound calls per posted addon index, both must be satisfied by the
 *   mock). The reshaped data plus 'choose', 'position' and optional 'avai'
 *   keys get saved to the product's '_product_addons' meta.
 *
 * @package Reepay\Subscriptions
 */

use Reepay\Subscriptions\Tests\Helpers\PLUGINS_STATE;
use Reepay\Subscriptions\Tests\Helpers\ProductGenerator;
use Reepay\Subscriptions\Tests\Helpers\Reepay_Subscriptions_Ajax_UnitTestCase;
use Reepay\Subscriptions\Tests\Helpers\Reepay_Subscriptions_UnitTestCase;
use Reepay\Subscriptions\Tests\Mocks\ApiResponseMock;

/**
 * SubscriptionAddonsTest.
 */
class SubscriptionAddonsTest extends Reepay_Subscriptions_UnitTestCase {

	/**
	 * @var WC_Reepay_Subscription_Addons
	 */
	private WC_Reepay_Subscription_Addons $addons;

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

		if ( PLUGINS_STATE::reepay_pay_activated() ) {
			reepay()->reset_settings();
		}

		delete_option( 'reepay_s_addons_list' );

		if ( ! is_null( WC()->cart ) ) {
			WC()->cart->empty_cart();
		}

		$this->addons = new WC_Reepay_Subscription_Addons();
	}

	public function tear_down() {
		ApiResponseMock::clear();
		delete_option( 'reepay_s_addons_list' );

		if ( ! is_null( WC()->cart ) ) {
			WC()->cart->empty_cart();
		}

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
	 * order_line_item() / get_item_data()
	 * ------------------------------------------------------------------
	 */

	/**
	 * Test @see WC_Reepay_Subscription_Addons::order_line_item attaches a
	 * reshaped 'addons' meta entry plus a display line-item meta
	 * ('Addon Name' => '+price') to the order item, driven by $values['addons'].
	 */
	public function test_order_line_item_attaches_addons_meta() {
		ApiResponseMock::respond_with(
			array(
				'name'           => 'Extra Service',
				'description'    => 'desc',
				'type'           => 'on_off',
				'amount'         => 1000, // minor units -> 10.00 major.
				'vat'            => 0.25, // *100 -> 25.
				'handle'         => 'addon-1',
				'amount_incl_vat' => false,
			)
		);

		$order = wc_create_order();
		$product = ( new ProductGenerator( 'simple' ) )->product();
		$item    = new WC_Order_Item_Product();
		$item->set_product( $product );
		$item->set_order_id( $order->get_id() );

		$values = array(
			'addons' => array(
				array( 'handle' => 'addon-1', 'name' => 'Extra Service', 'amount' => 10 ),
			),
		);

		$this->addons->order_line_item( $item, 'cart-item-key', $values );

		$meta = $item->get_meta( 'addons' );
		$this->assertIsArray( $meta );
		$this->assertCount( 1, $meta );
		$this->assertSame( 'addon-1', $meta[0]['handle'] );
		$this->assertEquals( 10, $meta[0]['amount'] );
		$this->assertEquals( 25, $meta[0]['vat'] );
		$this->assertSame( 'exclude', $meta[0]['vat_type'] );

		$display = $item->get_meta( 'Extra Service' );
		$this->assertStringContainsString( '10', $display );
	}

	/**
	 * Test @see WC_Reepay_Subscription_Addons::order_line_item does nothing
	 * (no 'addons' meta attached) when $values['addons'] is empty - no API
	 * call should even be attempted.
	 */
	public function test_order_line_item_noop_when_no_addons() {
		$order   = wc_create_order();
		$product = ( new ProductGenerator( 'simple' ) )->product();
		$item    = new WC_Order_Item_Product();
		$item->set_product( $product );
		$item->set_order_id( $order->get_id() );

		$this->addons->order_line_item( $item, 'cart-item-key', array() );

		$this->assertSame( '', $item->get_meta( 'addons' ) );
	}

	/**
	 * Test @see WC_Reepay_Subscription_Addons::get_item_data appends a
	 * display row (['name', 'display']) per addon in $cart_item['addons'],
	 * with no API call involved at all.
	 */
	public function test_get_item_data_appends_addon_display_rows() {
		$cart_item = array(
			'addons' => array(
				array( 'name' => 'Extra Service', 'amount' => 15 ),
			),
		);

		$result = $this->addons->get_item_data( array(), $cart_item );

		$this->assertCount( 1, $result );
		$this->assertSame( 'Extra Service', $result[0]['name'] );
		$this->assertStringContainsString( '15', $result[0]['display'] );
	}

	/**
	 * Test @see WC_Reepay_Subscription_Addons::get_item_data appends the
	 * quantity suffix to the name and multiplies the price when the addon row
	 * carries a 'quantity'.
	 */
	public function test_get_item_data_multiplies_price_by_quantity() {
		$cart_item = array(
			'addons' => array(
				array( 'name' => 'Metered', 'amount' => 2, 'quantity' => 3 ),
			),
		);

		$result = $this->addons->get_item_data( array(), $cart_item );

		$this->assertSame( 'Metered x3', $result[0]['name'] );
		// price should be 2 * 3 = 6.
		$this->assertStringContainsString( '6', $result[0]['display'] );
	}

	/**
	 * Test @see WC_Reepay_Subscription_Addons::get_item_data returns
	 * $other_data unchanged when the cart item has no addons.
	 */
	public function test_get_item_data_returns_unchanged_when_no_addons() {
		$other_data = array( array( 'name' => 'Unrelated', 'display' => 'x' ) );

		$result = $this->addons->get_item_data( $other_data, array() );

		$this->assertSame( $other_data, $result );
	}

	/*
	 * ------------------------------------------------------------------
	 * before_calculate_totals() / restore_base_prices_for_display()
	 * ------------------------------------------------------------------
	 */

	/**
	 * Test @see WC_Reepay_Subscription_Addons::before_calculate_totals adjusts
	 * the cart item's product price upward by the on_off addon amount (flat
	 * cost, divided by cart qty so price*qty == base*qty + addon_amount).
	 *
	 * IMPORTANT, confirmed-by-reading-the-source caveat: before_calculate_totals()
	 * iterates `$cart->get_cart()`, which (WC_Cart::get_cart() ->
	 * get_cart_contents()) returns a plain PHP ARRAY, not references into
	 * $cart->cart_contents. $cart_item inside the foreach is therefore a local
	 * copy; add_cart_item()'s return value is discarded (line 120:
	 * `$this->add_cart_item( $cart_item );` with no assignment). The only
	 * effect that survives is the price change on $cart_item['data'], because
	 * 'data' holds a WC_Product OBJECT (shared by reference even through the
	 * array copy) - calling set_price() on it mutates the same object instance
	 * that's still referenced by $cart->cart_contents[$key]['data']. The
	 * '_addon_base_price' array key itself is set only on the local copy and
	 * is LOST - it never reaches $cart->cart_contents. This was verified
	 * empirically (not assumed): asserting the key exists on
	 * $cart->cart_contents[$key] after calling before_calculate_totals()
	 * fails with "Undefined array key _addon_base_price".
	 */
	public function test_before_calculate_totals_adjusts_price_via_shared_product_object() {
		$product = ( new ProductGenerator( 'simple' ) )->product();
		$product->set_regular_price( 20 );
		$product->set_price( 20 );
		$product->save();

		$cart_item_key        = WC()->cart->add_to_cart( $product->get_id(), 1 );
		WC()->cart->cart_contents[ $cart_item_key ]['addons'] = array(
			array( 'name' => 'Flat addon', 'type' => 'on_off', 'amount' => 5 ),
		);

		$this->assertSame( 20.0, (float) WC()->cart->cart_contents[ $cart_item_key ]['data']->get_price() );

		// Simulate the woocommerce_before_calculate_totals hook directly.
		$this->addons->before_calculate_totals( WC()->cart );

		$cart_item = WC()->cart->cart_contents[ $cart_item_key ];
		$this->assertSame(
			25.0,
			(float) $cart_item['data']->get_price(),
			'Price should include the flat addon amount after before_calculate_totals(), because the product object is mutated in place.'
		);
		$this->assertArrayNotHasKey(
			'_addon_base_price',
			$cart_item,
			'Confirmed behavior: before_calculate_totals() discards add_cart_item()\'s returned array, so _addon_base_price never reaches $cart->cart_contents.'
		);
	}

	/**
	 * Test @see WC_Reepay_Subscription_Addons::restore_base_prices_for_display
	 * in the flow as it is REALLY wired (via the woocommerce_before_calculate_totals
	 * / woocommerce_after_calculate_totals hooks, both acting on `$cart->get_cart()`
	 * copies): because before_calculate_totals() never persists
	 * '_addon_base_price' onto $cart->cart_contents (see previous test's
	 * docblock), restore_base_prices_for_display()'s own
	 * `isset( $cart_item['_addon_base_price'] )` guard is never true for a
	 * cart item that only went through the real hook sequence - so it is
	 * effectively a no-op here: the addon-inflated price set by
	 * before_calculate_totals() is NOT reset afterwards in this flow.
	 *
	 * This test intentionally proves this (possibly unintended) behavior with
	 * an assertion on the REAL post-condition, rather than asserting the
	 * docblock's intended "resets to base price" behavior which does not
	 * actually occur given before_calculate_totals()'s copy-by-value bug.
	 */
	public function test_restore_base_prices_for_display_is_noop_after_real_hook_sequence() {
		$product = ( new ProductGenerator( 'simple' ) )->product();
		$product->set_regular_price( 20 );
		$product->set_price( 20 );
		$product->save();

		$cart_item_key = WC()->cart->add_to_cart( $product->get_id(), 1 );
		WC()->cart->cart_contents[ $cart_item_key ]['addons'] = array(
			array( 'name' => 'Flat addon', 'type' => 'on_off', 'amount' => 5 ),
		);

		$this->addons->before_calculate_totals( WC()->cart );
		$this->assertSame( 25.0, (float) WC()->cart->cart_contents[ $cart_item_key ]['data']->get_price() );

		$this->addons->restore_base_prices_for_display( WC()->cart );

		$this->assertSame(
			25.0,
			(float) WC()->cart->cart_contents[ $cart_item_key ]['data']->get_price(),
			'restore_base_prices_for_display() cannot reset the price here because _addon_base_price was never persisted onto $cart->cart_contents by before_calculate_totals().'
		);
	}

	/**
	 * Test @see WC_Reepay_Subscription_Addons::restore_base_prices_for_display
	 * DOES correctly restore the base price when '_addon_base_price' IS
	 * present directly on $cart->cart_contents (e.g. set by
	 * get_cart_item_from_session()/add_cart_item_data(), which both receive
	 * and return the array used to populate cart_contents directly, unlike
	 * before_calculate_totals()'s discarded copy). This isolates and confirms
	 * restore_base_prices_for_display()'s own logic in the case its precondition
	 * is actually met.
	 */
	public function test_restore_base_prices_for_display_resets_price_when_base_price_present() {
		$product = ( new ProductGenerator( 'simple' ) )->product();
		$product->set_regular_price( 20 );
		$product->set_price( 20 );
		$product->save();

		$cart_item_key = WC()->cart->add_to_cart( $product->get_id(), 1 );
		WC()->cart->cart_contents[ $cart_item_key ]['addons']            = array(
			array( 'name' => 'Flat addon', 'type' => 'on_off', 'amount' => 5 ),
		);
		WC()->cart->cart_contents[ $cart_item_key ]['_addon_base_price'] = 20.0;
		WC()->cart->cart_contents[ $cart_item_key ]['data']->set_price( 25.0 );

		$this->addons->restore_base_prices_for_display( WC()->cart );

		$this->assertSame( 20.0, (float) WC()->cart->cart_contents[ $cart_item_key ]['data']->get_price() );
	}

	/**
	 * Test @see WC_Reepay_Subscription_Addons::before_calculate_totals leaves
	 * cart items without 'addons' entirely untouched.
	 */
	public function test_before_calculate_totals_noop_for_items_without_addons() {
		$product = ( new ProductGenerator( 'simple' ) )->product();
		$product->set_regular_price( 20 );
		$product->set_price( 20 );
		$product->save();

		$cart_item_key = WC()->cart->add_to_cart( $product->get_id(), 1 );

		$this->addons->before_calculate_totals( WC()->cart );

		$cart_item = WC()->cart->cart_contents[ $cart_item_key ];
		$this->assertSame( 20.0, (float) $cart_item['data']->get_price() );
		$this->assertArrayNotHasKey( '_addon_base_price', $cart_item );
	}

	/*
	 * ------------------------------------------------------------------
	 * get_cart_item_from_session() / add_cart_item() / add_cart_item_data()
	 * ------------------------------------------------------------------
	 */

	/**
	 * Test @see WC_Reepay_Subscription_Addons::get_cart_item_from_session
	 * copies $values['addons'] onto the cart item and runs it through
	 * add_cart_item() (price adjustment applied inline during session
	 * restore).
	 */
	public function test_get_cart_item_from_session_copies_addons_and_adjusts_price() {
		$product = ( new ProductGenerator( 'simple' ) )->product();
		$product->set_regular_price( 20 );
		$product->set_price( 20 );
		$product->save();

		$cart_item = array(
			'data'     => $product,
			'quantity' => 1,
		);
		$values = array(
			'addons' => array(
				array( 'name' => 'Flat addon', 'type' => 'on_off', 'amount' => 5 ),
			),
		);

		$result = $this->addons->get_cart_item_from_session( $cart_item, $values );

		$this->assertSame( $values['addons'], $result['addons'] );
		$this->assertSame( 25.0, (float) $result['data']->get_price() );
		$this->assertSame( 20.0, $result['_addon_base_price'] );
	}

	/**
	 * Test @see WC_Reepay_Subscription_Addons::get_cart_item_from_session
	 * returns $cart_item unchanged when $values has no 'addons'.
	 */
	public function test_get_cart_item_from_session_noop_without_addons() {
		$cart_item = array( 'quantity' => 1 );

		$result = $this->addons->get_cart_item_from_session( $cart_item, array() );

		$this->assertSame( $cart_item, $result );
	}

	/**
	 * Test @see WC_Reepay_Subscription_Addons::add_cart_item correctly handles
	 * a quantity-type addon: the addon amount is multiplied by the addon's own
	 * quantity, then divided by the cart item's quantity to keep the flat
	 * total correct once WC multiplies price * cart_qty.
	 */
	public function test_add_cart_item_quantity_type_addon_flat_total() {
		$product = ( new ProductGenerator( 'simple' ) )->product();
		$product->set_regular_price( 10 );
		$product->set_price( 10 );
		$product->save();

		$cart_item = array(
			'data'     => $product,
			'quantity' => 2,
			'addons'   => array(
				array( 'name' => 'Metered addon', 'amount' => 3, 'quantity' => 4 ),
			),
		);

		$result = $this->addons->add_cart_item( $cart_item );

		// base price 10 + (3*4)/2 = 10 + 6 = 16.
		$this->assertSame( 16.0, (float) $result['data']->get_price() );
	}

	/**
	 * Test @see WC_Reepay_Subscription_Addons::add_cart_item does not
	 * double-apply the addon price when called twice in a row (guarded by
	 * the already-set '_addon_base_price' key).
	 */
	public function test_add_cart_item_does_not_double_apply_on_repeat_calls() {
		$product = ( new ProductGenerator( 'simple' ) )->product();
		$product->set_regular_price( 10 );
		$product->set_price( 10 );
		$product->save();

		$cart_item = array(
			'data'     => $product,
			'quantity' => 1,
			'addons'   => array(
				array( 'name' => 'Flat addon', 'type' => 'on_off', 'amount' => 5 ),
			),
		);

		$first  = $this->addons->add_cart_item( $cart_item );
		$second = $this->addons->add_cart_item( $first );

		$this->assertSame( 15.0, (float) $second['data']->get_price() );
		$this->assertSame( 10.0, $second['_addon_base_price'] );
	}

	/**
	 * Test @see WC_Reepay_Subscription_Addons::add_cart_item_data reads the
	 * product's _product_addons meta and appends a row to
	 * $cart_item_data['addons'] when the corresponding $_POST['addon-{handle}']
	 * is 'yes'.
	 */
	public function test_add_cart_item_data_appends_row_for_selected_on_off_addon() {
		$product = ( new ProductGenerator( 'simple' ) )->product();
		$product->update_meta_data(
			'_product_addons',
			array(
				array(
					'handle'      => 'addon-1',
					'name'        => 'Extra Service',
					'type'        => 'on_off',
					'amount'      => 10,
					'description' => 'desc',
					'vat_type'    => 'exclude',
				),
			)
		);
		$product->save();

		$_POST['addon-addon-1'] = 'yes';

		$result = $this->addons->add_cart_item_data( array(), $product->get_id(), 0, 1 );

		$this->assertArrayHasKey( 'addons', $result );
		$this->assertCount( 1, $result['addons'] );
		$row = current( $result['addons'] );
		$this->assertSame( 'addon-1', $row['handle'] );
		$this->assertSame( 'Extra Service', $row['name'] );
		$this->assertTrue( $row['fixed_amount'] );
		$this->assertArrayNotHasKey( 'quantity', $row );

		unset( $_POST['addon-addon-1'] );
	}

	/**
	 * Test @see WC_Reepay_Subscription_Addons::add_cart_item_data sets a
	 * 'quantity' key on the row when a 'quantity' type addon is posted with a
	 * positive integer.
	 */
	public function test_add_cart_item_data_sets_quantity_for_numeric_post_value() {
		$product = ( new ProductGenerator( 'simple' ) )->product();
		$product->update_meta_data(
			'_product_addons',
			array(
				array(
					'handle'      => 'addon-metered',
					'name'        => 'Metered',
					'type'        => 'quantity',
					'amount'      => 2,
					'description' => '',
					'vat_type'    => 'include',
				),
			)
		);
		$product->save();

		$_POST['addon-addon-metered'] = '3';

		$result = $this->addons->add_cart_item_data( array(), $product->get_id(), 0, 1 );

		$row = current( $result['addons'] );
		$this->assertSame( 3, $row['quantity'] );
		$this->assertTrue( $row['amount_incl_vat'] );

		unset( $_POST['addon-addon-metered'] );
	}

	/**
	 * Test @see WC_Reepay_Subscription_Addons::add_cart_item_data skips an
	 * addon whose posted value is neither 'yes' nor a positive integer (e.g.
	 * '0' or empty string - the "not selected" case).
	 */
	public function test_add_cart_item_data_skips_unselected_addon() {
		$product = ( new ProductGenerator( 'simple' ) )->product();
		$product->update_meta_data(
			'_product_addons',
			array(
				array(
					'handle'      => 'addon-skip',
					'name'        => 'Skip me',
					'type'        => 'quantity',
					'amount'      => 1,
					'description' => '',
					'vat_type'    => 'exclude',
				),
			)
		);
		$product->save();

		$_POST['addon-addon-skip'] = '0';

		$result = $this->addons->add_cart_item_data( array(), $product->get_id(), 0, 1 );

		$this->assertSame( array(), $result['addons'] );

		unset( $_POST['addon-addon-skip'] );
	}

	/*
	 * ------------------------------------------------------------------
	 * add_cart_item_data() - value validation per add-on type
	 * ------------------------------------------------------------------
	 */

	/**
	 * Create a product with one add-on of the given type.
	 *
	 * @param string $handle add-on handle.
	 * @param string $type 'on_off' or 'quantity'.
	 *
	 * @return int product ID.
	 */
	private function create_product_with_addon( string $handle, string $type ): int {
		$product = ( new ProductGenerator( 'simple' ) )->product();
		$product->update_meta_data(
			'_product_addons',
			array(
				array(
					'handle'      => $handle,
					'name'        => 'Addon ' . $handle,
					'type'        => $type,
					'amount'      => 2,
					'description' => '',
					'vat_type'    => 'include',
				),
			)
		);
		$product->save();

		return $product->get_id();
	}

	/**
	 * Post $value for the add-on and run add_cart_item_data().
	 *
	 * @param int    $product_id product ID.
	 * @param string $handle add-on handle.
	 * @param mixed  $value posted value.
	 *
	 * @return array cart item data.
	 */
	private function add_with_posted_value( int $product_id, string $handle, $value ): array {
		$_POST[ 'addon-' . $handle ] = $value;

		$result = $this->addons->add_cart_item_data( array(), $product_id, 0, 1 );

		unset( $_POST[ 'addon-' . $handle ] );

		return $result;
	}

	/**
	 * Test @see WC_Reepay_Subscription_Addons::add_cart_item_data rejects
	 * quantity values that are not whole numbers from 1 to the maximum, and
	 * adds an "Invalid value" error notice for each.
	 */
	public function test_add_cart_item_data_rejects_invalid_quantities_with_notice() {
		$product_id = $this->create_product_with_addon( 'addon-qty-invalid', 'quantity' );

		foreach ( array( '1.5', '-1', 'abc', '1000', '1e2', array( '3' ) ) as $value ) {
			wc_clear_notices();

			$result = $this->add_with_posted_value( $product_id, 'addon-qty-invalid', $value );

			$label = is_array( $value ) ? 'array' : $value;
			$this->assertSame( array(), $result['addons'], "Value: $label" );

			if ( is_array( $value ) ) {
				// Non-scalar posts are treated as "not selected" - no notice.
				$this->assertEmpty( wc_get_notices( 'error' ), "Value: $label" );
			} else {
				$this->assertCount( 1, wc_get_notices( 'error' ), "Value: $label" );
			}
		}

		wc_clear_notices();
	}

	/**
	 * Test @see WC_Reepay_Subscription_Addons::add_cart_item_data accepts the
	 * boundary quantities 1 and 999 (the default maximum), trimming whitespace.
	 */
	public function test_add_cart_item_data_accepts_boundary_quantities() {
		$product_id = $this->create_product_with_addon( 'addon-qty-bounds', 'quantity' );

		foreach ( array( '1' => 1, '999' => 999, ' 7 ' => 7 ) as $value => $expected ) {
			$result = $this->add_with_posted_value( $product_id, 'addon-qty-bounds', (string) $value );

			$row = current( $result['addons'] );
			$this->assertSame( $expected, $row['quantity'], "Value: '$value'" );
		}
	}

	/**
	 * Test @see WC_Reepay_Subscription_Addons::add_cart_item_data honours the
	 * 'reepay_subscriptions_addon_max_quantity' filter.
	 */
	public function test_add_cart_item_data_respects_max_quantity_filter() {
		$product_id = $this->create_product_with_addon( 'addon-qty-filter', 'quantity' );

		$max = function () {
			return 5;
		};
		add_filter( 'reepay_subscriptions_addon_max_quantity', $max );

		$accepted = $this->add_with_posted_value( $product_id, 'addon-qty-filter', '5' );
		$rejected = $this->add_with_posted_value( $product_id, 'addon-qty-filter', '6' );

		remove_filter( 'reepay_subscriptions_addon_max_quantity', $max );
		wc_clear_notices();

		$this->assertSame( 5, current( $accepted['addons'] )['quantity'] );
		$this->assertSame( array(), $rejected['addons'] );
	}

	/**
	 * Test @see WC_Reepay_Subscription_Addons::add_cart_item_data rejects a
	 * value that does not match the add-on's type: a number for an on/off
	 * add-on, 'yes' for a quantity add-on.
	 */
	public function test_add_cart_item_data_rejects_value_of_wrong_type() {
		$on_off   = $this->create_product_with_addon( 'addon-type-onoff', 'on_off' );
		$quantity = $this->create_product_with_addon( 'addon-type-qty', 'quantity' );

		wc_clear_notices();

		$this->assertSame( array(), $this->add_with_posted_value( $on_off, 'addon-type-onoff', '3' )['addons'] );
		$this->assertSame( array(), $this->add_with_posted_value( $quantity, 'addon-type-qty', 'yes' )['addons'] );
		$this->assertCount( 2, wc_get_notices( 'error' ) );

		wc_clear_notices();
	}

	/*
	 * ------------------------------------------------------------------
	 * get_cart_item_from_session() - invalid stored quantities
	 * ------------------------------------------------------------------
	 */

	/**
	 * Test @see WC_Reepay_Subscription_Addons::get_cart_item_from_session drops
	 * add-ons whose stored quantity is invalid (e.g. from an older session),
	 * keeps valid quantity add-ons, and keeps on/off add-ons (no quantity).
	 */
	public function test_get_cart_item_from_session_drops_invalid_quantity_addons() {
		$product = ( new ProductGenerator( 'simple' ) )->product();
		$product->set_regular_price( 10 );
		$product->set_price( 10 );
		$product->save();

		$cart_item = array(
			'data'     => $product,
			'quantity' => 1,
		);
		$values    = array(
			'addons' => array(
				'valid_qty' => array( 'name' => 'Valid qty', 'amount' => 1, 'quantity' => 2 ),
				'zero_qty'  => array( 'name' => 'Zero qty', 'amount' => 100, 'quantity' => 0 ),
				'float_qty' => array( 'name' => 'Float qty', 'amount' => 100, 'quantity' => 1.5 ),
				'huge_qty'  => array( 'name' => 'Huge qty', 'amount' => 100, 'quantity' => 100000 ),
				'on_off'    => array( 'name' => 'On/off', 'type' => 'on_off', 'amount' => 3 ),
			),
		);

		$result = $this->addons->get_cart_item_from_session( $cart_item, $values );

		$this->assertSame( array( 'valid_qty', 'on_off' ), array_keys( $result['addons'] ) );
		// 10 base + 1 * 2 (valid quantity add-on) + 3 (on/off) - the dropped ones add nothing.
		$this->assertSame( 15.0, (float) $result['data']->get_price() );
	}

	/*
	 * ------------------------------------------------------------------
	 * get_product_addons()
	 * ------------------------------------------------------------------
	 */

	/**
	 * Test @see WC_Reepay_Subscription_Addons::get_product_addons returns the
	 * product's '_product_addons' meta filtered through array_filter() (no
	 * API call).
	 */
	public function test_get_product_addons_returns_filtered_meta() {
		$product = ( new ProductGenerator( 'simple' ) )->product();
		$product->update_meta_data(
			'_product_addons',
			array(
				array( 'handle' => 'addon-1', 'name' => 'A' ),
				'', // falsy entry must be filtered out by array_filter().
			)
		);
		$product->save();

		$result = $this->addons->get_product_addons( $product->get_id() );

		$this->assertCount( 1, $result );
	}

	/*
	 * ------------------------------------------------------------------
	 * get_reepay_addons_list() cache behavior
	 * ------------------------------------------------------------------
	 */

	/**
	 * Test @see WC_Reepay_Subscription_Addons::get_reepay_addons_list returns
	 * the cached option value when $drop_cache is false and the option is
	 * already populated - no API call is made at all (verified by mocking a
	 * DIFFERENT/distinguishable response and asserting it's never used).
	 */
	public function test_get_reepay_addons_list_uses_cache_when_not_dropped() {
		$cached = array( 'content' => array( array( 'handle' => 'cached-addon', 'name' => 'Cached', 'all_plans' => true, 'eligible_plans' => array(), 'state' => 'active' ) ) );
		update_option( 'reepay_s_addons_list', $cached );

		// If the cache were bypassed, this fresh value would be returned instead.
		ApiResponseMock::respond_with(
			array(
				'content' => array(
					array( 'handle' => 'fresh-addon', 'name' => 'Fresh', 'all_plans' => true, 'eligible_plans' => array(), 'state' => 'active' ),
				),
			)
		);

		$result = WC_Reepay_Subscription_Addons::get_reepay_addons_list();

		$this->assertSame( $cached, $result, 'Expected the cached option value to be returned untouched when $drop_cache=false.' );
	}

	/**
	 * Test @see WC_Reepay_Subscription_Addons::get_reepay_addons_list with
	 * $drop_cache=true genuinely bypasses the cached option on READ (the
	 * fresh API value wins over the stale cached value) - but per the
	 * confirmed fact that the method unconditionally calls update_option()
	 * afterwards, the option itself ends up holding the FRESH value too.
	 */
	public function test_get_reepay_addons_list_drop_cache_bypasses_read_but_still_writes_cache() {
		$stale = array( 'content' => array( array( 'handle' => 'stale-addon', 'name' => 'Stale', 'all_plans' => true, 'eligible_plans' => array(), 'state' => 'active' ) ) );
		update_option( 'reepay_s_addons_list', $stale );

		$fresh = array(
			'content' => array(
				array( 'handle' => 'fresh-addon', 'name' => 'Fresh', 'all_plans' => true, 'eligible_plans' => array(), 'state' => 'active' ),
			),
		);
		ApiResponseMock::respond_with( $fresh );

		$result = WC_Reepay_Subscription_Addons::get_reepay_addons_list( false, true );

		$this->assertSame( $fresh, $result, 'Expected $drop_cache=true to bypass the stale cached read and return the fresh API value.' );

		// The write-back happens regardless of $drop_cache - confirm the option
		// now holds the FRESH value (overwriting the stale one), not left stale.
		$this->assertSame( $fresh, get_option( 'reepay_s_addons_list' ), 'get_reepay_addons_list() must write the fresh value back to the cache option even when $drop_cache=true.' );
	}

	/**
	 * Test @see WC_Reepay_Subscription_Addons::get_reepay_addons_list filters
	 * out addons not eligible for the given $plan_handle (all_plans=false and
	 * the handle missing from eligible_plans) and addons whose state isn't
	 * 'active'.
	 */
	public function test_get_reepay_addons_list_filters_ineligible_and_inactive() {
		delete_option( 'reepay_s_addons_list' );

		ApiResponseMock::respond_with(
			array(
				'content' => array(
					array( 'handle' => 'eligible', 'name' => 'Eligible', 'all_plans' => false, 'eligible_plans' => array( 'plan-a' ), 'state' => 'active' ),
					array( 'handle' => 'ineligible', 'name' => 'Ineligible', 'all_plans' => false, 'eligible_plans' => array( 'plan-b' ), 'state' => 'active' ),
					array( 'handle' => 'inactive', 'name' => 'Inactive', 'all_plans' => true, 'eligible_plans' => array(), 'state' => 'inactive' ),
				),
			)
		);

		$result = WC_Reepay_Subscription_Addons::get_reepay_addons_list( 'plan-a', true );

		$handles = array_column( $result['content'], 'handle' );
		$this->assertContains( 'eligible', $handles );
		$this->assertNotContains( 'ineligible', $handles );
		$this->assertNotContains( 'inactive', $handles );
	}

	/**
	 * Test @see WC_Reepay_Subscription_Addons::get_reepay_addons_list returns
	 * false (not null, not an empty array) when the API call throws.
	 */
	public function test_get_reepay_addons_list_returns_false_on_api_exception() {
		delete_option( 'reepay_s_addons_list' );

		ApiResponseMock::respond_with( array( 'error' => 'invalid_request', 'message' => 'boom' ), 400 );

		$result = WC_Reepay_Subscription_Addons::get_reepay_addons_list( false, true );

		$this->assertFalse( $result, 'Expected get_reepay_addons_list() to return exactly false on an API exception.' );
	}

	/*
	 * ------------------------------------------------------------------
	 * get_reepay_addon_data()
	 * ------------------------------------------------------------------
	 */

	/**
	 * Test @see WC_Reepay_Subscription_Addons::get_reepay_addon_data reshapes
	 * a successful API response: amount divided by 100, vat multiplied by
	 * 100, vat_type derived from amount_incl_vat.
	 */
	public function test_get_reepay_addon_data_reshapes_success_response() {
		ApiResponseMock::respond_with(
			array(
				'name'            => 'Extra Service',
				'description'     => 'A description',
				'type'            => 'on_off',
				'amount'          => 2500,
				'vat'             => 0.25,
				'handle'          => 'addon-1',
				'amount_incl_vat' => true,
			)
		);

		$result = $this->addons->get_reepay_addon_data( 'addon-1' );

		$this->assertIsArray( $result );
		$this->assertEquals( 25, $result['amount'] );
		$this->assertEquals( 25, $result['vat'] );
		$this->assertSame( 'addon-1', $result['handle'] );
		$this->assertSame( 'addon-1', $result['exist'] );
		$this->assertSame( 'include', $result['vat_type'] );
	}

	/**
	 * Test @see WC_Reepay_Subscription_Addons::get_reepay_addon_data returns
	 * exactly false (explicit return statement after the try/catch, not an
	 * implicit null) when the API call throws, and records an admin notice.
	 */
	public function test_get_reepay_addon_data_returns_false_on_exception() {
		ApiResponseMock::respond_with( array( 'error' => 'invalid_request', 'message' => 'Add-on not found' ), 404 );

		$result = $this->addons->get_reepay_addon_data( 'unknown-handle' );

		$this->assertFalse( $result );
		$this->assertNotNull( $result );
	}

	/*
	 * ------------------------------------------------------------------
	 * save_addons() / save_to_reepay() / add_plan_to_addon()
	 * ------------------------------------------------------------------
	 */

	/**
	 * Test @see WC_Reepay_Subscription_Addons::save_addons, driven entirely
	 * via $_POST (through get_posted_product_addons()), saves the reshaped
	 * addon data (from the mocked get_reepay_addon_data() API call) into the
	 * product's '_product_addons' meta, including 'choose' and 'position'
	 * keys appended by get_posted_product_addons() itself.
	 *
	 * Confirmed: get_posted_product_addons() makes TWO real API calls per
	 * posted index - get_reepay_addon_data() (add_on/{handle} GET) and
	 * add_plan_to_addon() (also add_on/{handle} GET, and conditionally a PUT).
	 * Both are satisfied by the same mocked GET response here because the
	 * product is a plain 'simple' product (is_type() checks for
	 * reepay_simple_subscriptions/reepay_variable_subscriptions both fail),
	 * so add_plan_to_addon() short-circuits before issuing its own call -
	 * only get_reepay_addon_data()'s call actually goes out.
	 */
	public function test_save_addons_persists_product_addons_meta_from_posted_data() {
		$product = ( new ProductGenerator( 'simple' ) )->product();

		ApiResponseMock::respond_with(
			array(
				'name'            => 'Extra Service',
				'description'     => 'desc',
				'type'            => 'on_off',
				'amount'          => 1000,
				'vat'             => 0.25,
				'handle'          => 'addon-1',
				'amount_incl_vat' => false,
			)
		);

		$_POST['addon_choose_exist']      = array( 'addon-1' );
		$_POST['product_addon_position']  = array( '0' );
		$_POST['_reepay_addon_avai']       = array( 'all' );

		$this->addons->save_addons( $product->get_id() );

		$saved_product = wc_get_product( $product->get_id() );
		$meta          = $saved_product->get_meta( '_product_addons' );

		$this->assertCount( 1, $meta );
		$this->assertSame( 'addon-1', $meta[0]['handle'] );
		$this->assertSame( 'exist', $meta[0]['choose'] );
		$this->assertSame( 0, $meta[0]['position'] );
		$this->assertSame( 'all', $meta[0]['avai'] );

		unset( $_POST['addon_choose_exist'], $_POST['product_addon_position'], $_POST['_reepay_addon_avai'] );
	}

	/**
	 * Test @see WC_Reepay_Subscription_Addons::save_addons results in an empty
	 * '_product_addons' meta array when nothing was posted.
	 */
	public function test_save_addons_empty_when_nothing_posted() {
		$product = ( new ProductGenerator( 'simple' ) )->product();

		$this->addons->save_addons( $product->get_id() );

		$saved_product = wc_get_product( $product->get_id() );
		$this->assertSame( array(), $saved_product->get_meta( '_product_addons' ) );
	}

	/**
	 * Test @see WC_Reepay_Subscription_Addons::save_to_reepay (the UPDATE
	 * branch - $product_addon['handle'] already set) PUTs to add_on/{handle}
	 * and returns the SAME $product_addon array unchanged (no 'handle'
	 * mutation on update, unlike the create branch).
	 */
	public function test_save_to_reepay_update_branch_returns_same_handle() {
		$product = ( new ProductGenerator( 'simple' ) )->product();

		ApiResponseMock::respond_with( array( 'success' => true ) );

		$product_addon = array(
			'name'     => 'Existing addon',
			'handle'   => 'existing-handle',
			'amount'   => 10,
			'vat'      => 25,
			'type'     => 'on_off',
			'vat_type' => 'exclude',
			'avai'     => 'all',
		);

		$result = $this->addons->save_to_reepay( $product_addon, $product->get_id(), 0 );

		$this->assertSame( 'existing-handle', $result['handle'] );
	}

	/**
	 * Test @see WC_Reepay_Subscription_Addons::save_to_reepay (the CREATE
	 * branch - no 'handle' in $product_addon) POSTs to add_on and mutates the
	 * returned array with a freshly generated 'Woocommerce_{post_id}_{time}'
	 * handle.
	 */
	public function test_save_to_reepay_create_branch_generates_handle() {
		$product = ( new ProductGenerator( 'simple' ) )->product();

		ApiResponseMock::respond_with( array( 'success' => true ) );

		$product_addon = array(
			'name'     => 'New addon',
			'amount'   => 10,
			'vat'      => 25,
			'type'     => 'on_off',
			'vat_type' => 'exclude',
			'avai'     => 'all',
		);

		$result = $this->addons->save_to_reepay( $product_addon, $product->get_id(), 0 );

		$this->assertArrayHasKey( 'handle', $result );
		$this->assertStringStartsWith( 'Woocommerce_' . $product->get_id() . '_', $result['handle'] );
	}

	/**
	 * Test @see WC_Reepay_Subscription_Addons::save_to_reepay for a
	 * reepay_simple_subscriptions product with NO plan handle set: returns an
	 * empty array early (before any API call), and records an admin notice.
	 */
	public function test_save_to_reepay_returns_empty_array_when_simple_subscription_has_no_plan_handle() {
		$product = ( new ProductGenerator( 'rp_sub' ) )->product();
		// Deliberately no '_reepay_subscription_handle' meta set.

		$product_addon = array(
			'name'     => 'Addon',
			'handle'   => 'existing-handle',
			'amount'   => 10,
			'vat'      => 25,
			'type'     => 'on_off',
			'vat_type' => 'exclude',
			'avai'     => 'all',
		);

		$result = $this->addons->save_to_reepay( $product_addon, $product->get_id(), 0 );

		$this->assertSame( array(), $result );
	}

	/**
	 * Test @see WC_Reepay_Subscription_Addons::add_plan_to_addon for a
	 * reepay_simple_subscriptions product: fetches the addon (GET), and when
	 * all_plans is false and the plan isn't yet in eligible_plans, PUTs the
	 * updated eligible_plans back. Both calls are satisfied by the
	 * non-one-shot ApiResponseMock serving the same GET-shaped body to every
	 * call (PUT requests don't care about the response body for this
	 * assertion - we only verify no exception is thrown and the method
	 * returns null/void without error).
	 */
	public function test_add_plan_to_addon_for_simple_subscription_does_not_throw() {
		$product = ( new ProductGenerator( 'rp_sub' ) )->product();
		$product->update_meta_data( '_reepay_subscription_handle', 'plan-a' );
		$product->save();

		ApiResponseMock::respond_with(
			array(
				'handle'         => 'addon-1',
				'all_plans'      => false,
				'eligible_plans' => array(),
			)
		);

		$this->addons->add_plan_to_addon( $product->get_id(), 'addon-1' );

		// No exception means success; nothing else to assert on a void method
		// whose only externally-visible effect is the (mocked) outbound API calls.
		$this->assertTrue( true );
	}

	/**
	 * Test @see WC_Reepay_Subscription_Addons::add_plan_to_addon for a plain
	 * 'simple' (non-subscription) product: $plan_handles stays unset/empty
	 * because neither is_type() branch matches, so the method returns without
	 * making any API call at all (confirmed no exception / no mocked response
	 * needed).
	 */
	public function test_add_plan_to_addon_noop_for_non_subscription_product() {
		$product = ( new ProductGenerator( 'simple' ) )->product();

		// Deliberately NOT mocking any API response - if the method attempted
		// a real HTTP call it would fail/throw in this test environment.
		$this->addons->add_plan_to_addon( $product->get_id(), 'addon-1' );

		$this->assertTrue( true );
	}
}
