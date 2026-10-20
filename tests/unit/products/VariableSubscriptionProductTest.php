<?php
/**
 * Class VariableSubscriptionProductTest
 *
 * Covers WC_Product_Reepay_Variable_Subscription (includes/WC_Product_Reepay_Variable_Subscription.php,
 * 104 lines, extends WC_Product_Variable):
 *   - __construct( $product )
 *   - get_type()
 *   - __get( $key )
 *   - get_price_html( $price = '' )
 *   - get_min_max_price_variations() [protected - via reflection]
 *   - get_currency( $variation, $currency = '' ) [static]
 *
 * IMPORTANT - __get() / wcs_product_deprecated_property_handler() gotcha,
 * VERIFIED (not assumed):
 *
 *   public function __get( $key ) {
 *       $value = wcs_product_deprecated_property_handler( $key, $this );
 *       ...
 *   }
 *
 * wcs_product_deprecated_property_handler() is defined as a plain global
 * function in
 * wp-content/plugins/woocommerce-subscriptions/vendor/woocommerce/subscriptions-core/includes/wcs-compatibility-functions.php:261,
 * loaded only when the WooCommerce Subscriptions plugin itself is loaded
 * (it is NOT shipped by WooCommerce core or by this plugin). There is no
 * function_exists() guard around the call in __get(). Confirmed by grepping
 * the WC Subscriptions plugin tree for the function definition - it lives
 * exclusively there. This matches the known pattern from Phase 3
 * (LifecycleTest/OrderSplittingTest): calling __get() on an instance of this
 * class with woo_subs NOT active triggers a PHP fatal error ("Call to
 * undefined function wcs_product_deprecated_property_handler()"), because
 * magic __get() is invoked implicitly by PHP on any undefined/inaccessible
 * property read and cannot be caught with a try/catch the way a normal
 * function-missing error could if it were wrapped - a fatal Error IS
 * catchable with a PHP try/catch(\Error) block since PHP 7, so we use that
 * to assert the exact failure mode under the woo-only combo rather than
 * letting it crash the whole test run, while still asserting correct
 * passthrough behavior under woo_subs.
 *
 * @package Reepay\Subscriptions
 */

use Reepay\Subscriptions\Tests\Helpers\DataProvider;
use Reepay\Subscriptions\Tests\Helpers\PLUGINS_STATE;
use Reepay\Subscriptions\Tests\Helpers\Reepay_Subscriptions_UnitTestCase;

/**
 * VariableSubscriptionProductTest.
 */
class VariableSubscriptionProductTest extends Reepay_Subscriptions_UnitTestCase {

	/**
	 * Create a real, saved 'reepay_variable_subscriptions' parent product.
	 * ProductGenerator does not support this type, so build it directly with
	 * core WC APIs/WC_Product_Reepay_Variable_Subscription's own constructor
	 * (same technique the production Plan_Variable class itself uses - see
	 * includes/WC_Reepay_Subscription_Plan_Variable.php - just invoked
	 * directly here instead of through the product-type factory filter).
	 *
	 * @return WC_Product_Reepay_Variable_Subscription
	 */
	private function create_parent_product(): WC_Product_Reepay_Variable_Subscription {
		$product = new WC_Product_Reepay_Variable_Subscription( 0 );
		$product->set_name( 'Variable Subscription Parent' );
		$product->save();

		return $product;
	}

	/**
	 * Re-fetch a parent product by ID as a fresh object instance.
	 *
	 * IMPORTANT (discovered while debugging get_min_max_price_variations()
	 * test failures, not assumed): WC_Product_Variable::save() - invoked by
	 * create_parent_product() above, at a point when zero variations exist
	 * yet - caches $this->children and $this->visible_children as `array()`
	 * (not null) directly on that in-memory object instance as a side effect
	 * of the save/sync process. Both get_children() and get_visible_children()
	 * only call the data store's read_children() when the property is still
	 * literally `null`, so once that object has cached an empty array, it
	 * NEVER re-reads from the DB again, even after real variations are
	 * created and saved under it afterwards (confirmed directly via
	 * reflection on the children/visible_children properties: null before
	 * save(), array() immediately after save(), still array() after creating
	 * and saving a real child variation). Re-fetching via wc_get_product()
	 * returns a brand new object with children/visible_children still null,
	 * so the lazy read genuinely happens against current DB state.
	 *
	 * @param int $product_id product/post ID.
	 *
	 * @return WC_Product_Reepay_Variable_Subscription
	 */
	private function refetch( int $product_id ): WC_Product_Reepay_Variable_Subscription {
		return new WC_Product_Reepay_Variable_Subscription( $product_id );
	}

	/**
	 * Create and save a real WC_Product_Variation child of the given parent,
	 * with its own price, schedule meta and optional currency override meta -
	 * mirroring how the production code builds real variations (plain
	 * WC_Product_Variation instances, confirmed by
	 * WC_Reepay_Subscription_Plan_Variable.php:38 checking
	 * get_class( $product ) == 'WC_Product_Variation').
	 *
	 * @param WC_Product_Reepay_Variable_Subscription $parent parent product.
	 * @param float                                   $price variation price.
	 * @param array<string, mixed>                     $meta extra meta key => value pairs.
	 *
	 * @return WC_Product_Variation
	 */
	private function create_variation( WC_Product_Reepay_Variable_Subscription $parent, float $price, array $meta = array() ): WC_Product_Variation {
		$variation = new WC_Product_Variation();
		$variation->set_parent_id( $parent->get_id() );
		$variation->set_regular_price( $price );
		$variation->set_price( $price );
		$variation->set_status( 'publish' );

		foreach ( $meta as $key => $value ) {
			$variation->update_meta_data( $key, $value );
		}

		$variation->save();

		return $variation;
	}

	/**
	 * Configure a variation (or the parent, for schedule meta read directly
	 * off the passed-in $product in get_billing_plan()) with real
	 * 'daily' billing-period meta, same rationale as SimpleSubscriptionProductTest -
	 * get_interval() for TYPE_DAILY reads '_reepay_subscription_daily' via
	 * get_post_meta( $product->get_id(), ... ) directly
	 * (WC_Reepay_Subscription_Plan_Simple.php:761-762).
	 *
	 * @param WC_Product $product product/variation to configure.
	 * @param int        $interval interval value.
	 */
	private function configure_daily_billing_plan( WC_Product $product, int $interval = 1 ): void {
		$product->update_meta_data( '_reepay_subscription_schedule_type', WC_Reepay_Subscription_Plan_Simple::TYPE_DAILY );
		$product->update_meta_data( '_reepay_subscription_daily', $interval );
		$product->save();
	}

	/**
	 * Invoke the protected get_min_max_price_variations() method via
	 * reflection, since it has no public accessor (confirmed protected, not
	 * public, by reading WC_Product_Reepay_Variable_Subscription.php:74).
	 * Same technique as CurrencyTest::invoke_is_cart_or_checkout_context().
	 *
	 * @param WC_Product_Reepay_Variable_Subscription $product product instance.
	 *
	 * @return array<string, mixed>
	 */
	private function invoke_get_min_max_price_variations( WC_Product_Reepay_Variable_Subscription $product ): array {
		$method = new ReflectionMethod( $product, 'get_min_max_price_variations' );
		$method->setAccessible( true );

		return (array) $method->invoke( $product );
	}

	/*
	 * ------------------------------------------------------------------
	 * __construct( $product ) and get_type()
	 * ------------------------------------------------------------------
	 *
	 * Real behavior (WC_Product_Reepay_Variable_Subscription.php:4-12):
	 *   public function __construct( $product ) {
	 *       parent::__construct( $product );
	 *       $this->data_store = WC_Data_Store::load( 'product-variable' );
	 *   }
	 *   public function get_type() { return 'reepay_variable_subscriptions'; }
	 */

	/**
	 * Constructing with 0 (new product) and saving loads/persists correctly,
	 * and get_type() returns the fixed type string.
	 */
	public function test_construct_with_zero_creates_new_product_with_correct_type() {
		$product = new WC_Product_Reepay_Variable_Subscription( 0 );

		$this->assertSame( 'reepay_variable_subscriptions', $product->get_type() );
		$this->assertSame( 0, $product->get_id() );
	}

	/**
	 * Constructing with an existing product's ID correctly loads that same
	 * underlying product's data (name, price) via the wrapper - confirming
	 * __construct( $product ) proxies to the real parent::__construct() data
	 * loading, not just returning an empty shell.
	 */
	public function test_construct_with_existing_id_loads_underlying_product_data() {
		$original = $this->create_parent_product();
		$original->set_regular_price( 42.5 );
		$original->save();

		$loaded = new WC_Product_Reepay_Variable_Subscription( $original->get_id() );

		$this->assertSame( $original->get_id(), $loaded->get_id() );
		$this->assertSame( 'Variable Subscription Parent', $loaded->get_name() );
		$this->assertSame( '42.5', $loaded->get_regular_price() );
		$this->assertSame( 'reepay_variable_subscriptions', $loaded->get_type() );
	}

	/**
	 * Constructing by passing an existing WC_Product object (not just an ID)
	 * also correctly loads its data, since parent::__construct() accepts
	 * either form.
	 */
	public function test_construct_with_existing_product_object_loads_underlying_product_data() {
		$original = $this->create_parent_product();

		$loaded = new WC_Product_Reepay_Variable_Subscription( $original );

		$this->assertSame( $original->get_id(), $loaded->get_id() );
		$this->assertSame( 'reepay_variable_subscriptions', $loaded->get_type() );
	}

	/*
	 * ------------------------------------------------------------------
	 * __get( $key ) - the wcs_product_deprecated_property_handler() gotcha
	 * ------------------------------------------------------------------
	 */

	/**
	 * Under the woo_subs-active combo, __get() for a deprecated-property key
	 * (e.g. 'product_type', handled unconditionally by
	 * wcs_product_deprecated_property_handler() regardless of whether the
	 * product is an actual WC_Product_Subscription) correctly proxies to
	 * that handler and returns the expected deprecated value, matching
	 * get_type()'s own return value per the handler's own
	 * `case 'product_type': $value = $product->get_type();` branch.
	 */
	public function test_magic_get_proxies_to_deprecated_property_handler_when_woo_subs_active() {
		if ( ! PLUGINS_STATE::woo_subs_activated() ) {
			$this->markTestSkipped( 'Requires WooCommerce Subscriptions active (woo_subs) - wcs_product_deprecated_property_handler() is only defined by that plugin.' );
		}

		$product = $this->create_parent_product();

		// wcs_product_deprecated_property_handler() itself calls
		// wcs_deprecated_argument() for any recognized key (confirmed by
		// reading wcs-compatibility-functions.php:317: `wcs_deprecated_argument(
		// $class_name . '::$' . $property, ... )`), which fires a real
		// 'deprecated_argument_run' action - WP_UnitTestCase fails the test
		// on any UNEXPECTED deprecated notice, so it must be declared here.
		$this->setExpectedDeprecated( 'WC_Product_Reepay_Variable_Subscription::$product_type' );

		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- magic property name mirrors a WC Subscriptions deprecated key, not ours to rename.
		$value = $product->product_type;

		$this->assertSame( 'reepay_variable_subscriptions', $value );
	}

	/**
	 * For a key NOT recognized by wcs_product_deprecated_property_handler()
	 * (handler returns null since the key matches none of its special cases
	 * nor any is_callable( array( 'WC_Subscriptions_Product', ... ) ) match),
	 * __get() falls through to parent::__get( $key ) - WC_Product's own
	 * magic getter (abstract-wc-legacy-product.php). Verified (not assumed)
	 * that for a key matching none of ITS switch cases either,
	 * WC_Product::__get()'s default branch returns
	 * get_post_meta( $this->id, '_' . $key, true ) - i.e. an empty STRING
	 * for a nonexistent meta key, not null. Confirms the "is_null on the
	 * handler's result - fall through to parent" branch is real, not dead
	 * code, still only reachable under woo_subs (same reason as above:
	 * calling __get() at all requires the handler function to exist first).
	 * WC_Product::__get() itself also fires its own generic
	 * 'Product properties should not be accessed directly.' doing_it_wrong
	 * notice for any key other than 'post_type', which must likewise be
	 * declared.
	 */
	public function test_magic_get_falls_through_to_parent_for_unrecognized_key_when_woo_subs_active() {
		if ( ! PLUGINS_STATE::woo_subs_activated() ) {
			$this->markTestSkipped( 'Requires WooCommerce Subscriptions active (woo_subs) - wcs_product_deprecated_property_handler() is only defined by that plugin.' );
		}

		$product = $this->create_parent_product();

		$this->setExpectedIncorrectUsage( 'totally_unrecognized_property_xyz' );

		$value = $product->totally_unrecognized_property_xyz;

		// WC_Product::__get()'s default branch: get_post_meta( $id, '_totally_unrecognized_property_xyz', true ).
		$this->assertSame( '', $value );
	}

	/**
	 * VERIFIED (not assumed): under the woo-only combo (WooCommerce Subscriptions
	 * NOT active), wcs_product_deprecated_property_handler() is genuinely
	 * undefined, and __get() calls it completely unconditionally with no
	 * function_exists() guard - so reading ANY undefined/inaccessible
	 * property on this class fatals with a PHP Error ("Call to undefined
	 * function"). A PHP fatal Error (as opposed to a warning/notice) IS
	 * catchable via try/catch(\Throwable) since PHP 7, which lets us assert
	 * the exact failure mode here safely instead of crashing the whole test
	 * run or just assuming the behavior.
	 */
	public function test_magic_get_fatals_without_woo_subs_confirming_the_gotcha() {
		if ( PLUGINS_STATE::woo_subs_activated() ) {
			$this->markTestSkipped( 'This test specifically targets the woo-only combo where wcs_product_deprecated_property_handler() is undefined; woo_subs is active in this run.' );
		}

		$this->assertFalse(
			function_exists( 'wcs_product_deprecated_property_handler' ),
			'Precondition check: the WC Subscriptions deprecated-property handler function must genuinely be absent for this test to prove anything.'
		);

		$product = $this->create_parent_product();

		$caught = null;
		try {
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- deliberately triggering magic __get().
			$unused = $product->product_type;
		} catch ( \Throwable $e ) {
			$caught = $e;
		}

		$this->assertNotNull( $caught, '__get() was expected to fatal with wcs_product_deprecated_property_handler() undefined, but no Throwable was caught.' );
		$this->assertStringContainsString( 'wcs_product_deprecated_property_handler', $caught->getMessage() );
	}

	/*
	 * ------------------------------------------------------------------
	 * get_price_html( $price = '' ) and get_min_max_price_variations()
	 * ------------------------------------------------------------------
	 *
	 * Real behavior (WC_Product_Reepay_Variable_Subscription.php:39-103):
	 *   $prices = $this->get_min_max_price_variations();
	 *   if ( is_null( $prices['min'] ) ) {
	 *       $price = apply_filters( 'woocommerce_variable_empty_price_html', '', $this );
	 *   } else if ( $prices['min'] !== $prices['max'] ) {
	 *       // range: "wc_price(min)/schedule - wc_price(max)/schedule"
	 *   } else {
	 *       // single: "wc_price(min)/schedule"
	 *   }
	 * get_min_max_price_variations() iterates get_visible_children(),
	 * tracking (float) $variation->get_price() min/max + per-bound schedule
	 * type + per-bound currency; 'min'/'max' both stay null when there are no
	 * visible children at all.
	 */

	/**
	 * No variations at all ("no visible children" branch): get_min_max_price_variations()
	 * leaves $prices['min'] (and 'max') as null, and get_price_html() takes
	 * the "empty price html" branch - default filter passthrough returns ''.
	 */
	public function test_get_price_html_is_empty_when_no_visible_children() {
		$parent  = $this->create_parent_product();
		$product = $this->refetch( $parent->get_id() );
		// Deliberately no variations created.

		$prices = $this->invoke_get_min_max_price_variations( $product );
		$this->assertNull( $prices['min'] );
		$this->assertNull( $prices['max'] );

		$this->assertSame( '', $product->get_price_html() );
	}

	/**
	 * Two variations with the SAME price: min === max, so get_min_max_price_variations()
	 * resolves both bounds to that one price, and get_price_html() takes the
	 * single-price branch (not the range-formatting branch) - confirmed by
	 * asserting the rendered html does NOT contain wc_format_price_range()'s
	 * "&ndash;" range separator.
	 */
	public function test_get_price_html_shows_single_price_when_min_equals_max() {
		$parent = $this->create_parent_product();
		$this->create_variation( $parent, 50.0, array(
			'_reepay_subscription_schedule_type' => WC_Reepay_Subscription_Plan_Simple::TYPE_DAILY,
			'_reepay_subscription_daily'         => 1,
		) );
		$this->create_variation( $parent, 50.0, array(
			'_reepay_subscription_schedule_type' => WC_Reepay_Subscription_Plan_Simple::TYPE_DAILY,
			'_reepay_subscription_daily'         => 1,
		) );
		$product = $this->refetch( $parent->get_id() );

		$prices = $this->invoke_get_min_max_price_variations( $product );
		$this->assertSame( 50.0, $prices['min'] );
		$this->assertSame( 50.0, $prices['max'] );
		$this->assertSame( $prices['min'], $prices['max'] );

		$html = $product->get_price_html();

		$this->assertStringContainsString( '50', $html );
		$this->assertStringContainsString( 'Day', $html );
		$this->assertStringNotContainsString( '&ndash;', $html );
	}

	/**
	 * Two variations with DIFFERENT prices: min !== max, so get_price_html()
	 * takes the wc_format_price_range() branch - confirmed by asserting both
	 * the low and high price appear, along with the range separator
	 * "&ndash;" that wc_format_price_range() inserts between the two bounds.
	 */
	public function test_get_price_html_shows_price_range_when_min_differs_from_max() {
		$parent = $this->create_parent_product();
		$this->create_variation( $parent, 25.0, array(
			'_reepay_subscription_schedule_type' => WC_Reepay_Subscription_Plan_Simple::TYPE_DAILY,
			'_reepay_subscription_daily'         => 1,
		) );
		$this->create_variation( $parent, 75.0, array(
			'_reepay_subscription_schedule_type' => WC_Reepay_Subscription_Plan_Simple::TYPE_DAILY,
			'_reepay_subscription_daily'         => 1,
		) );
		$product = $this->refetch( $parent->get_id() );

		$prices = $this->invoke_get_min_max_price_variations( $product );
		$this->assertSame( 25.0, $prices['min'] );
		$this->assertSame( 75.0, $prices['max'] );
		$this->assertNotSame( $prices['min'], $prices['max'] );

		$html = $product->get_price_html();

		$this->assertStringContainsString( '25', $html );
		$this->assertStringContainsString( '75', $html );
		$this->assertStringContainsString( '&ndash;', $html );
	}

	/**
	 * get_min_max_price_variations() tracks the schedule type and currency
	 * PER bound (min vs max) independently - using two variations with
	 * different billing-plan meta and different currency override meta
	 * confirms the per-bound fields are each correctly sourced from their
	 * own variation, not a single shared value.
	 */
	public function test_get_min_max_price_variations_tracks_schedule_and_currency_per_bound() {
		$parent = $this->create_parent_product();
		$this->create_variation( $parent, 10.0, array(
			'_reepay_subscription_schedule_type' => WC_Reepay_Subscription_Plan_Simple::TYPE_DAILY,
			'_reepay_subscription_daily'         => 1,
			'_reepay_subscription_currency'      => 'SEK',
		) );
		$this->create_variation( $parent, 90.0, array(
			'_reepay_subscription_schedule_type' => WC_Reepay_Subscription_Plan_Simple::TYPE_DAILY,
			'_reepay_subscription_daily'         => 1,
			'_reepay_subscription_currency'      => 'NOK',
		) );
		$product = $this->refetch( $parent->get_id() );

		$prices = $this->invoke_get_min_max_price_variations( $product );

		$this->assertSame( 10.0, $prices['min'] );
		$this->assertSame( 90.0, $prices['max'] );
		$this->assertSame( 'Day', $prices['min_schedule_type'] );
		$this->assertSame( 'Day', $prices['max_schedule_type'] );
		$this->assertSame( 'SEK', $prices['min_currency'] );
		$this->assertSame( 'NOK', $prices['max_currency'] );
	}

	/*
	 * ------------------------------------------------------------------
	 * get_currency( $variation, $currency = '' ) [static]
	 * ------------------------------------------------------------------
	 *
	 * Real behavior (WC_Product_Reepay_Variable_Subscription.php:62-72):
	 *   if ( empty( $currency ) ) { $currency = get_woocommerce_currency(); }
	 *   if ( ! empty( $variation->get_meta( '_reepay_subscription_currency' ) ) ) {
	 *       $currency = $variation->get_meta( '_reepay_subscription_currency' );
	 *   }
	 *   return $currency;
	 * Identical shape to the simple variant's instance get_currency(), but
	 * static and takes the variation explicitly as its first argument.
	 */

	/**
	 * Default-to-shop-currency fallback: a variation with no
	 * '_reepay_subscription_currency' meta and no explicit argument resolves
	 * to the shop's configured currency.
	 */
	public function test_get_currency_falls_back_to_shop_currency_when_no_meta_and_no_argument() {
		$product   = $this->create_parent_product();
		$variation = $this->create_variation( $product, 10.0 );

		$this->assertSame( get_woocommerce_currency(), WC_Product_Reepay_Variable_Subscription::get_currency( $variation ) );
	}

	/**
	 * With '_reepay_subscription_currency' meta set on the variation,
	 * get_currency() returns the meta override, not the shop default -
	 * across two different currencies from DataProvider::currencies().
	 *
	 * @dataProvider provide_currencies
	 *
	 * @param string $currency currency code under test.
	 */
	public function test_get_currency_returns_meta_override_currency( string $currency ) {
		$product   = $this->create_parent_product();
		$variation = $this->create_variation( $product, 10.0, array( '_reepay_subscription_currency' => $currency ) );

		$this->assertSame( $currency, WC_Product_Reepay_Variable_Subscription::get_currency( $variation ) );
	}

	/**
	 * The meta override wins even when a different currency is explicitly
	 * passed as the argument, same asymmetry as the simple variant.
	 */
	public function test_get_currency_meta_override_wins_over_explicit_argument() {
		$product   = $this->create_parent_product();
		$variation = $this->create_variation( $product, 10.0, array( '_reepay_subscription_currency' => 'GBP' ) );

		$this->assertSame( 'GBP', WC_Product_Reepay_Variable_Subscription::get_currency( $variation, 'USD' ) );
	}

	/**
	 * Data provider supplying real currency codes from DataProvider::currencies().
	 *
	 * @return array<int, array<int, string>>
	 */
	public function provide_currencies(): array {
		return array_map(
			static function ( string $currency ) {
				return array( $currency );
			},
			array_slice( DataProvider::currencies(), 2, 2 )
		);
	}
}
