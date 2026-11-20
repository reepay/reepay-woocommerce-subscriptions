<?php
/**
 * Class CurrencyTest
 *
 * Covers WC_Reepay_Subscription_Currency (includes/WC_Reepay_Subscription_Currency.php):
 *  - is_cart_or_checkout_context() (protected, line ~216) via reflection.
 *  - get_product_currency() / get_real_currency() multi-currency formatting
 *    (lines 310-334 / 324-334), exercised through maybe_change_item_price_currency()
 *    (lines 157-171) which is the concrete public method that does real
 *    formatting work (returns a wc_price()-wrapped HTML string in the
 *    product's own currency when it differs from the shop's default).
 *
 * @package Reepay\Subscriptions
 */

use Reepay\Subscriptions\Tests\Helpers\Reepay_Subscriptions_Ajax_UnitTestCase;
use Reepay\Subscriptions\Tests\Helpers\DataProvider;
use Reepay\Subscriptions\Tests\Helpers\ProductGenerator;

/**
 * CurrencyTest.
 *
 * Uses the Ajax test case base because is_cart_or_checkout_context() reads
 * directly from $_SERVER['REQUEST_URI'] and $_REQUEST['wc-ajax'], and the
 * trait's tear_down() resets $_GET/$_POST/$_REQUEST (but not $_SERVER, which
 * we reset manually below).
 */
class CurrencyTest extends Reepay_Subscriptions_Ajax_UnitTestCase {

	/**
	 * @var WC_Reepay_Subscription_Currency
	 */
	private WC_Reepay_Subscription_Currency $currency;

	/**
	 * Original REQUEST_URI, saved/restored around each test since several
	 * tests mutate $_SERVER['REQUEST_URI'] directly (not covered by the Ajax
	 * base class's superglobal reset, which only touches $_GET/$_POST/$_REQUEST).
	 *
	 * @var string|null
	 */
	private $original_request_uri;

	public function set_up() {
		parent::set_up();

		$this->currency              = new WC_Reepay_Subscription_Currency();
		$this->original_request_uri  = $_SERVER['REQUEST_URI'] ?? null;

		// is_admin() defaults to true in the WP test environment (no screen
		// has been set yet), which would make is_cart_or_checkout_context()'s
		// "if (is_admin() || empty(REQUEST_URI)) return false;" guard
		// short-circuit every single test in this class to false, regardless
		// of which branch is actually under test. Reset to a front-end screen
		// here so each test genuinely exercises its intended branch; the one
		// test that specifically covers the is_admin() branch
		// (test_is_cart_or_checkout_context_false_when_is_admin) switches
		// back to an admin screen itself.
		set_current_screen( 'front' );
	}

	public function tear_down() {
		if ( null === $this->original_request_uri ) {
			unset( $_SERVER['REQUEST_URI'] );
		} else {
			$_SERVER['REQUEST_URI'] = $this->original_request_uri;
		}
		unset( $_REQUEST['wc-ajax'] );

		if ( ! is_null( WC()->cart ) ) {
			WC()->cart->empty_cart();
		}

		set_current_screen( 'front' );

		parent::tear_down();
	}

	/**
	 * Invoke the protected is_cart_or_checkout_context() method via reflection,
	 * since it has no public accessor (confirmed protected, not public, by
	 * reading WC_Reepay_Subscription_Currency.php:216).
	 *
	 * @return bool
	 */
	private function invoke_is_cart_or_checkout_context(): bool {
		$method = new ReflectionMethod( $this->currency, 'is_cart_or_checkout_context' );
		$method->setAccessible( true );

		return (bool) $method->invoke( $this->currency );
	}

	/*
	 * ------------------------------------------------------------------
	 * is_cart_or_checkout_context() - wc-ajax branch
	 * ------------------------------------------------------------------
	 *
	 * Real behavior (lines 221-227):
	 *   if ( isset($_REQUEST['wc-ajax']) ) {
	 *       return in_array(
	 *           wp_unslash($_REQUEST['wc-ajax']),
	 *           array('update_order_review', 'checkout', 'get_refreshed_fragments', 'apply_coupon', 'remove_coupon', 'update_shipping_method', 'get_cart_totals'),
	 *           true
	 *       );
	 *   }
	 * This branch is only reached when the REST_REQUEST condition above did
	 * not short-circuit - i.e. REST_REQUEST is undefined/false OR
	 * REQUEST_URI is empty. We clear REQUEST_URI here so the wc-ajax branch
	 * is reached regardless of whatever REST_REQUEST ended up being defined
	 * as by other tests in this process.
	 */

	/**
	 * Test @see WC_Reepay_Subscription_Currency::is_cart_or_checkout_context
	 * returns true for 'update_order_review' - the exact wc-ajax action named
	 * in the brief as the classic checkout-review-refresh request.
	 */
	public function test_is_cart_or_checkout_context_true_for_update_order_review_ajax() {
		unset( $_SERVER['REQUEST_URI'] );
		$_REQUEST['wc-ajax'] = 'update_order_review';

		$this->assertTrue( $this->invoke_is_cart_or_checkout_context() );
	}

	/**
	 * Test @see WC_Reepay_Subscription_Currency::is_cart_or_checkout_context
	 * returns true for each of the other wc-ajax actions explicitly listed in
	 * the source array.
	 */
	public function test_is_cart_or_checkout_context_true_for_other_listed_ajax_actions() {
		unset( $_SERVER['REQUEST_URI'] );

		foreach ( array( 'checkout', 'get_refreshed_fragments', 'apply_coupon', 'remove_coupon', 'update_shipping_method', 'get_cart_totals' ) as $action ) {
			$_REQUEST['wc-ajax'] = $action;
			$this->assertTrue( $this->invoke_is_cart_or_checkout_context(), "Expected true for wc-ajax={$action}" );
		}
	}

	/**
	 * Test @see WC_Reepay_Subscription_Currency::is_cart_or_checkout_context
	 * returns false for a wc-ajax action NOT in the allow-list (e.g. an
	 * unrelated ajax action), confirming the in_array() uses strict comparison
	 * against a fixed list rather than "any wc-ajax request".
	 */
	public function test_is_cart_or_checkout_context_false_for_unlisted_ajax_action() {
		unset( $_SERVER['REQUEST_URI'] );
		$_REQUEST['wc-ajax'] = 'some_unrelated_action';

		$this->assertFalse( $this->invoke_is_cart_or_checkout_context() );
	}

	/*
	 * ------------------------------------------------------------------
	 * is_cart_or_checkout_context() - admin / empty REQUEST_URI branch
	 * ------------------------------------------------------------------
	 *
	 * Real behavior (lines 229-231):
	 *   if ( is_admin() || empty($_SERVER['REQUEST_URI']) ) {
	 *       return false;
	 *   }
	 */

	/**
	 * Test @see WC_Reepay_Subscription_Currency::is_cart_or_checkout_context
	 * returns false when $_SERVER['REQUEST_URI'] is empty/unset and there is
	 * no wc-ajax request - the "normal admin URL context" case from the
	 * checklist, modeled here via an empty REQUEST_URI and no wc-ajax key,
	 * which the empty() half of the admin/empty-URI guard catches directly
	 * regardless of is_admin()'s value in the unit test environment.
	 */
	public function test_is_cart_or_checkout_context_false_for_empty_request_uri() {
		unset( $_SERVER['REQUEST_URI'] );
		unset( $_REQUEST['wc-ajax'] );

		$this->assertFalse( $this->invoke_is_cart_or_checkout_context() );
	}

	/**
	 * Test @see WC_Reepay_Subscription_Currency::is_cart_or_checkout_context
	 * returns false when is_admin() is true, regardless of REQUEST_URI value.
	 */
	public function test_is_cart_or_checkout_context_false_when_is_admin() {
		unset( $_REQUEST['wc-ajax'] );
		$_SERVER['REQUEST_URI'] = '/some/random/admin/path';

		set_current_screen( 'edit-post' );
		$this->assertTrue( is_admin(), 'Precondition: is_admin() must be true for this test to be meaningful.' );

		$this->assertFalse( $this->invoke_is_cart_or_checkout_context() );

		set_current_screen( 'front' );
	}

	/*
	 * ------------------------------------------------------------------
	 * is_cart_or_checkout_context() - cart/checkout page path match
	 * ------------------------------------------------------------------
	 *
	 * Real behavior (lines 233-243): parses the path out of REQUEST_URI and
	 * compares it (trailing-slash normalized) against the permalink paths of
	 * the WooCommerce cart and checkout pages.
	 */

	/**
	 * Test @see WC_Reepay_Subscription_Currency::is_cart_or_checkout_context
	 * returns true when REQUEST_URI's path matches the configured WooCommerce
	 * cart page's permalink path.
	 *
	 * Fixture setup notes (test-only, no production file touched):
	 *  - The test environment does not have a 'woocommerce_cart_page_id'
	 *    option configured by default (wc_get_page_id('cart') returns -1),
	 *    so a page is created and the option set here, wiring up
	 *    WooCommerce's own standard page-id option.
	 *  - The test environment also uses the default (non-pretty) permalink
	 *    structure, under which get_permalink() returns a query-string URL
	 *    like 'http://example.org/?page_id=N' whose PHP_URL_PATH component is
	 *    just '/' - not a meaningful distinct path to match against. A pretty
	 *    permalink structure is set (and restored in a finally block) so the
	 *    cart page resolves to a real path (e.g. '/cart/'), which is what the
	 *    production code's path-matching branch is actually designed for.
	 */
	public function test_is_cart_or_checkout_context_true_for_cart_page_path() {
		unset( $_REQUEST['wc-ajax'] );

		global $wp_rewrite;
		$original_structure = get_option( 'permalink_structure' );
		update_option( 'permalink_structure', '/%postname%/' );
		$wp_rewrite->init();
		$wp_rewrite->flush_rules();

		try {
			$cart_page_id = wc_get_page_id( 'cart' );
			if ( $cart_page_id <= 0 ) {
				$cart_page_id = self::factory()->post->create(
					array(
						'post_title'  => 'Cart',
						'post_type'   => 'page',
						'post_status' => 'publish',
					)
				);
				update_option( 'woocommerce_cart_page_id', $cart_page_id );
			}

			$path = wp_parse_url( get_permalink( $cart_page_id ), PHP_URL_PATH );
			$this->assertNotEmpty( $path, 'Precondition: cart page permalink must resolve to a non-empty path under pretty permalinks.' );
			$this->assertNotSame( '/', $path, 'Precondition: expected a distinct pretty-permalink path, not the site root.' );

			$_SERVER['REQUEST_URI'] = $path;

			$this->assertTrue( $this->invoke_is_cart_or_checkout_context() );
		} finally {
			update_option( 'permalink_structure', $original_structure );
			$wp_rewrite->init();
			$wp_rewrite->flush_rules();
		}
	}

	/**
	 * Test @see WC_Reepay_Subscription_Currency::is_cart_or_checkout_context
	 * returns false for a path that is neither the cart nor the checkout page.
	 */
	public function test_is_cart_or_checkout_context_false_for_unrelated_frontend_path() {
		unset( $_REQUEST['wc-ajax'] );
		$_SERVER['REQUEST_URI'] = '/some-random-shop-page/';

		$this->assertFalse( $this->invoke_is_cart_or_checkout_context() );
	}

	/*
	 * ------------------------------------------------------------------
	 * Multi-currency price formatting - maybe_change_item_price_currency()
	 * ------------------------------------------------------------------
	 *
	 * Real behavior (lines 157-171):
	 *   $default_currency = get_option('woocommerce_currency');
	 *   $product_currency = self::get_product_currency($product, $default_currency);
	 *   if ($product_currency === $default_currency) { return $price_html; }
	 *   $cart = WC()->cart;
	 *   $product_price = $cart->display_prices_including_tax()
	 *       ? wc_get_price_including_tax($product)
	 *       : wc_get_price_excluding_tax($product);
	 *   return wc_price($product_price, array('currency' => $product_currency));
	 *
	 * get_product_currency() (lines 324-334) only overrides the currency for a
	 * Reepay product (WC_Reepay_Checkout::is_reepay_product()) that has a
	 * non-empty '_reepay_subscription_currency' meta value; otherwise it
	 * passes the given $currency straight through unchanged.
	 */

	/**
	 * Test @see WC_Reepay_Subscription_Currency::maybe_change_item_price_currency
	 * returns the price formatted in the product's OWN currency (via
	 * '_reepay_subscription_currency' meta) when it differs from the shop's
	 * default currency - tested across 2+ currencies from DataProvider::currencies().
	 */
	public function test_maybe_change_item_price_currency_formats_in_product_currency() {
		$default_currency = get_option( 'woocommerce_currency' );
		$candidates        = array_filter(
			DataProvider::currencies(),
			static function ( $currency ) use ( $default_currency ) {
				return $currency !== $default_currency;
			}
		);

		$tested = 0;
		foreach ( array_slice( array_values( $candidates ), 0, 2 ) as $product_currency ) {
			$product = ( new ProductGenerator( 'rp_sub' ) )->product();
			$product->set_regular_price( 100 );
			$product->update_meta_data( '_reepay_subscription_currency', $product_currency );
			$product->save();

			$cart = WC()->cart;
			$expected_price = $cart->display_prices_including_tax()
				? wc_get_price_including_tax( $product )
				: wc_get_price_excluding_tax( $product );
			$expected_html = wc_price( $expected_price, array( 'currency' => $product_currency ) );

			$result = $this->currency->maybe_change_item_price_currency( '<span>original</span>', $product );

			$this->assertSame( $expected_html, $result, "Mismatch for currency {$product_currency}" );
			$this->assertNotSame( '<span>original</span>', $result );
			++$tested;
		}

		$this->assertGreaterThanOrEqual( 2, $tested, 'Expected to exercise at least 2 distinct currencies.' );
	}

	/**
	 * Test @see WC_Reepay_Subscription_Currency::maybe_change_item_price_currency
	 * returns the original, unmodified $price_html when the product's
	 * resolved currency equals the shop's default (no '_reepay_subscription_currency'
	 * override set at all), confirming the early-return guard.
	 */
	public function test_maybe_change_item_price_currency_passthrough_when_currency_matches_default() {
		$product = ( new ProductGenerator( 'rp_sub' ) )->product();
		// Deliberately no '_reepay_subscription_currency' meta set.

		$original_html = '<span class="price">unchanged</span>';
		$result        = $this->currency->maybe_change_item_price_currency( $original_html, $product );

		$this->assertSame( $original_html, $result );
	}

	/*
	 * ------------------------------------------------------------------
	 * get_real_currency() / get_product_currency() - direct coverage across
	 * multiple currencies, independent of the cart-item price filter above.
	 * ------------------------------------------------------------------
	 */

	/**
	 * Test @see WC_Reepay_Subscription_Currency::get_product_currency returns
	 * the product's '_reepay_subscription_currency' meta value for a Reepay
	 * product, across 2+ currencies from DataProvider::currencies().
	 */
	public function test_get_product_currency_returns_product_meta_currency_for_reepay_product() {
		$tested = 0;
		foreach ( array_slice( DataProvider::currencies(), 0, 2 ) as $currency ) {
			$product = ( new ProductGenerator( 'rp_sub' ) )->product();
			$product->update_meta_data( '_reepay_subscription_currency', $currency );
			$product->save();

			$this->assertSame(
				$currency,
				WC_Reepay_Subscription_Currency::get_product_currency( $product, 'XXX' ),
				"Expected product currency override to be {$currency}"
			);
			++$tested;
		}

		$this->assertGreaterThanOrEqual( 2, $tested );
	}

	/**
	 * Test @see WC_Reepay_Subscription_Currency::get_product_currency passes
	 * the given fallback $currency straight through, unchanged, for a plain
	 * (non-Reepay) 'simple' product - confirms the override only applies to
	 * Reepay products.
	 */
	public function test_get_product_currency_passthrough_for_non_reepay_product() {
		$product = ( new ProductGenerator( 'simple' ) )->product();

		foreach ( array_slice( DataProvider::currencies(), 0, 2 ) as $currency ) {
			$this->assertSame(
				$currency,
				WC_Reepay_Subscription_Currency::get_product_currency( $product, $currency )
			);
		}
	}

	/**
	 * Test @see WC_Reepay_Subscription_Currency::get_real_currency scans the
	 * cart for the first Reepay product whose currency differs from the given
	 * $currency and returns that product's currency, across 2+ currencies.
	 */
	public function test_get_real_currency_returns_first_differing_product_currency_in_cart() {
		WC()->cart->empty_cart();

		// A baseline currency that is guaranteed not to collide with the
		// product currencies under test below (it is not in
		// DataProvider::currencies(), so it can never equal $product_currency).
		$baseline_currency = 'XXX';

		$tested = 0;
		foreach ( array_slice( DataProvider::currencies(), 0, 2 ) as $product_currency ) {
			WC()->cart->empty_cart();

			$product = ( new ProductGenerator( 'rp_sub' ) )->product();
			$product->update_meta_data( '_reepay_subscription_currency', $product_currency );
			$product->save();
			WC()->cart->add_to_cart( $product->get_id() );

			$this->assertSame(
				$product_currency,
				WC_Reepay_Subscription_Currency::get_real_currency( $baseline_currency ),
				"Expected get_real_currency to surface the product's own currency {$product_currency}"
			);
			++$tested;
		}

		WC()->cart->empty_cart();

		$this->assertGreaterThanOrEqual( 2, $tested );
	}

	/**
	 * Test @see WC_Reepay_Subscription_Currency::get_real_currency returns the
	 * given $currency unchanged when the cart has no products overriding the
	 * currency (empty cart).
	 */
	public function test_get_real_currency_passthrough_for_empty_cart() {
		WC()->cart->empty_cart();

		$this->assertSame( 'EUR', WC_Reepay_Subscription_Currency::get_real_currency( 'EUR' ) );
	}

	/*
	 * ------------------------------------------------------------------
	 * is_cart_or_checkout_context() - REST_REQUEST branch
	 * ------------------------------------------------------------------
	 *
	 * Real behavior (lines 217-219):
	 *   if ( defined('REST_REQUEST') && REST_REQUEST && ! empty($_SERVER['REQUEST_URI']) ) {
	 *       return (bool) preg_match('#/wc/store/v1/(cart|checkout|batch)#', wp_unslash($_SERVER['REQUEST_URI']));
	 *   }
	 *
	 * REST_REQUEST is a PHP constant - once defined in this process it cannot
	 * be undefined again. The brief flags this directly. Once any test here
	 * defines REST_REQUEST as true, that first "if" branch in the production
	 * code short-circuits EVERY subsequent call to is_cart_or_checkout_context()
	 * in this process for any non-empty REQUEST_URI, regardless of which
	 * branch that later test actually means to exercise (confirmed by hitting
	 * exactly this bug while writing this suite: the cart-page-path test
	 * failed once a REST_REQUEST-defining test ran before it in the same
	 * process). There is no way to "undefine" a PHP constant, so the only
	 * reliable fix is ORDERING: these REST_REQUEST tests are deliberately the
	 * LAST tests declared in this class, so every REQUEST_URI/wc-ajax/admin/
	 * path-matching test above runs - and is asserted - before REST_REQUEST
	 * is ever defined in this process. Do not add further is_cart_or_checkout_context()
	 * test methods below this block without re-verifying this ordering
	 * constraint still holds.
	 */

	/**
	 * Test @see WC_Reepay_Subscription_Currency::is_cart_or_checkout_context
	 * returns true for a Store API cart REQUEST_URI while REST_REQUEST is
	 * defined and true.
	 */
	public function test_is_cart_or_checkout_context_true_for_store_api_cart_uri() {
		if ( ! defined( 'REST_REQUEST' ) ) {
			define( 'REST_REQUEST', true );
		}
		if ( ! REST_REQUEST ) {
			$this->markTestSkipped( 'REST_REQUEST constant was already defined as false by a prior test/bootstrap; cannot test the true branch in this process.' );
		}

		$_SERVER['REQUEST_URI'] = '/wp-json/wc/store/v1/cart';

		$this->assertTrue( $this->invoke_is_cart_or_checkout_context() );
	}

	/**
	 * Test @see WC_Reepay_Subscription_Currency::is_cart_or_checkout_context
	 * returns true for the Store API checkout and batch endpoints too (the
	 * regex alternation covers cart|checkout|batch).
	 */
	public function test_is_cart_or_checkout_context_true_for_store_api_checkout_and_batch_uris() {
		if ( ! defined( 'REST_REQUEST' ) ) {
			define( 'REST_REQUEST', true );
		}
		if ( ! REST_REQUEST ) {
			$this->markTestSkipped( 'REST_REQUEST constant was already defined as false by a prior test/bootstrap; cannot test the true branch in this process.' );
		}

		$_SERVER['REQUEST_URI'] = '/wp-json/wc/store/v1/checkout';
		$this->assertTrue( $this->invoke_is_cart_or_checkout_context() );

		$_SERVER['REQUEST_URI'] = '/wp-json/wc/store/v1/batch';
		$this->assertTrue( $this->invoke_is_cart_or_checkout_context() );
	}

	/**
	 * Test @see WC_Reepay_Subscription_Currency::is_cart_or_checkout_context
	 * returns false for a Store API URI that is NOT cart/checkout/batch (e.g.
	 * the product collection endpoint), even with REST_REQUEST true - confirms
	 * the regex is specific, not "any Store API request".
	 */
	public function test_is_cart_or_checkout_context_false_for_unrelated_store_api_uri() {
		if ( ! defined( 'REST_REQUEST' ) ) {
			define( 'REST_REQUEST', true );
		}
		if ( ! REST_REQUEST ) {
			$this->markTestSkipped( 'REST_REQUEST constant was already defined as false by a prior test/bootstrap; cannot test this branch meaningfully.' );
		}

		$_SERVER['REQUEST_URI'] = '/wp-json/wc/store/v1/products';

		$this->assertFalse( $this->invoke_is_cart_or_checkout_context() );
	}
}
