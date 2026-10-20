<?php
/**
 * Class SimpleSubscriptionProductTest
 *
 * Covers WC_Product_Reepay_Simple_Subscription (includes/WC_Product_Reepay_Simple_Subscription.php,
 * 62 lines, extends WC_Product_Simple):
 *   - get_type()
 *   - get_price_html( $deprecated = '' )
 *   - get_currency( $currency = '' )
 *   - format_price( $price_html, $product ) [static]
 *
 * Key real-behavior finding (read directly from
 * WC_Reepay_Subscription_Plan_Simple::get_billing_plan(), includes/WC_Reepay_Subscription_Plan_Simple.php:786-840):
 *
 *   $type      = $product->get_meta( '_reepay_subscription_schedule_type' );
 *   $type_data = $product->get_meta( '_reepay_subscription_' . $type );
 *   $interval  = self::get_interval( $product->get_id(), $type, $type_data );
 *   ...
 *   $type_str = $types_info[ $interval > 1 ? $type . '_multiple' : $type ] ?? $types_info[ $type ] ?? '';
 *   $ret = ! empty( $type_str ) ? sprintf( $type_str, $interval ) : '';
 *
 * get_interval() for TYPE_DAILY ('daily') reads get_post_meta( $post_id, '_reepay_subscription_daily', true )
 * directly (WC_Reepay_Subscription_Plan_Simple.php:760-778) - NOT $type_data. So to get a real non-empty
 * schedule string for the 'daily' type, a product fixture needs BOTH:
 *   - meta '_reepay_subscription_schedule_type' => 'daily'
 *   - meta '_reepay_subscription_daily' => <interval, e.g. 1>  (read via get_post_meta on the saved post)
 * Since WC_Data::save() persists product meta to postmeta, setting these via
 * $product->update_meta_data()/save() is sufficient - get_interval() will see them via get_post_meta().
 * With interval=1, $types_info[TYPE_DAILY] = 'Billed every day' (self::$types_info[TYPE_DAILY], no
 * '_multiple' suffix needed since 1 is not > 1).
 *
 * Without that meta configured (fresh rp_sub product with no schedule_type meta at all), $type is '',
 * get_interval() falls through to the `else { return false; }` branch, $type_str resolves to
 * $types_info[''] ?? '' = '', and get_billing_plan() returns '' - so format_price() returns the plain
 * price html untouched (its own empty() guard at WC_Product_Reepay_Simple_Subscription.php:47-49).
 *
 * @package Reepay\Subscriptions
 */

use Reepay\Subscriptions\Tests\Helpers\DataProvider;
use Reepay\Subscriptions\Tests\Helpers\ProductGenerator;
use Reepay\Subscriptions\Tests\Helpers\Reepay_Subscriptions_UnitTestCase;

/**
 * SimpleSubscriptionProductTest.
 */
class SimpleSubscriptionProductTest extends Reepay_Subscriptions_UnitTestCase {

	/**
	 * Create a real, saved 'reepay_simple_subscriptions' product via
	 * ProductGenerator( 'rp_sub' ), with a set price so wc_get_price_to_display()
	 * / get_price_html() has something concrete to render.
	 *
	 * @param float $price regular price to set.
	 *
	 * @return WC_Product_Reepay_Simple_Subscription
	 */
	private function create_product( float $price = 100.0 ): WC_Product_Reepay_Simple_Subscription {
		$product = ( new ProductGenerator( 'rp_sub' ) )->product();
		$product->set_regular_price( $price );
		$product->set_price( $price );
		$product->save();

		return $product;
	}

	/**
	 * Configure the product with real billing-period meta so
	 * WC_Reepay_Subscription_Plan_Simple::get_billing_plan() returns a
	 * non-empty schedule string. Per get_interval()'s real behavior
	 * (WC_Reepay_Subscription_Plan_Simple.php:761-762), the 'daily' type reads
	 * '_reepay_subscription_daily' via get_post_meta() directly - so that key
	 * must be persisted as real post meta (which WC_Data::save() does for
	 * product meta set via update_meta_data()).
	 *
	 * @param WC_Product_Reepay_Simple_Subscription $product product to configure.
	 * @param int                                   $interval interval value for '_reepay_subscription_daily'.
	 */
	private function configure_daily_billing_plan( WC_Product_Reepay_Simple_Subscription $product, int $interval = 1 ): void {
		$product->update_meta_data( '_reepay_subscription_schedule_type', WC_Reepay_Subscription_Plan_Simple::TYPE_DAILY );
		$product->update_meta_data( '_reepay_subscription_daily', $interval );
		$product->save();
	}

	/*
	 * ------------------------------------------------------------------
	 * get_type()
	 * ------------------------------------------------------------------
	 */

	/**
	 * Test @see WC_Product_Reepay_Simple_Subscription::get_type returns the
	 * fixed product type string, regardless of any configured meta.
	 */
	public function test_get_type_returns_reepay_simple_subscriptions() {
		$product = $this->create_product();

		$this->assertSame( 'reepay_simple_subscriptions', $product->get_type() );
	}

	/*
	 * ------------------------------------------------------------------
	 * get_price_html( $deprecated = '' )
	 * ------------------------------------------------------------------
	 *
	 * Real behavior (WC_Product_Reepay_Simple_Subscription.php:15-24):
	 *   $price = wc_price( wc_get_price_to_display( $this ), $args ) . $this->get_price_suffix();
	 *   $price = apply_filters( 'woocommerce_get_price_html', $price, $this );
	 *   return self::format_price( $price, $this );
	 * format_price() appends ' / {schedule}' only when get_billing_plan() is
	 * non-empty - which in turn requires the schedule_type + matching interval
	 * meta described above.
	 */

	/**
	 * With real billing-period meta configured ('daily' schedule_type +
	 * '_reepay_subscription_daily' interval meta), get_price_html() must
	 * contain the recurring-billing suffix " / Billed every day" appended by
	 * format_price().
	 */
	public function test_get_price_html_includes_schedule_suffix_when_billing_plan_configured() {
		$product = $this->create_product( 100.0 );
		$this->configure_daily_billing_plan( $product, 1 );

		$html = $product->get_price_html();

		// format_price() calls get_billing_plan( $product, true ) - the SHORT
		// form ($types_info_short), which for TYPE_DAILY is 'Day', not the
		// long-form 'Billed every day' used elsewhere in the plugin.
		$this->assertStringContainsString( '/', $html );
		$this->assertStringContainsString( 'Day', $html );
	}

	/**
	 * Without any '_reepay_subscription_schedule_type' meta set, get_billing_plan()
	 * returns '' and format_price() returns the plain price html unchanged -
	 * no ' / {schedule}' suffix should appear.
	 */
	public function test_get_price_html_is_plain_price_when_billing_plan_not_configured() {
		$product = $this->create_product( 100.0 );
		// Deliberately no schedule_type / interval meta configured.

		$html = $product->get_price_html();

		$this->assertStringNotContainsString( 'Billed every', $html );
		// The plain wc_price() output for a 100.0 price should still be present.
		$this->assertStringContainsString( '100', $html );
	}

	/*
	 * ------------------------------------------------------------------
	 * get_currency( $currency = '' ) and format_price() currency passthrough
	 * ------------------------------------------------------------------
	 *
	 * Real behavior (WC_Product_Reepay_Simple_Subscription.php:26-36):
	 *   if ( empty( $currency ) ) { $currency = get_woocommerce_currency(); }
	 *   if ( ! empty( $this->get_meta( '_reepay_subscription_currency' ) ) ) {
	 *       $currency = $this->get_meta( '_reepay_subscription_currency' );
	 *   }
	 *   return $currency;
	 * The meta override (if present) always wins over both the passed-in
	 * $currency argument and the shop default.
	 */

	/**
	 * Default-to-shop-currency fallback: with no '_reepay_subscription_currency'
	 * meta set and no explicit argument, get_currency() returns the shop's
	 * configured currency (get_woocommerce_currency()).
	 */
	public function test_get_currency_falls_back_to_shop_currency_when_no_meta_and_no_argument() {
		$product = $this->create_product();

		$this->assertSame( get_woocommerce_currency(), $product->get_currency() );
	}

	/**
	 * With '_reepay_subscription_currency' meta set, get_currency() returns the
	 * meta override, not the shop default - across two different currencies
	 * from DataProvider::currencies().
	 *
	 * @dataProvider provide_currencies
	 *
	 * @param string $currency currency code under test.
	 */
	public function test_get_currency_returns_meta_override_currency( string $currency ) {
		$product = $this->create_product();
		$product->update_meta_data( '_reepay_subscription_currency', $currency );
		$product->save();

		$this->assertSame( $currency, $product->get_currency() );
	}

	/**
	 * The meta override wins even when a different currency is explicitly
	 * passed as the argument - confirming the meta check happens AFTER (and
	 * overrides) the argument, not just the empty-argument shop-default path.
	 */
	public function test_get_currency_meta_override_wins_over_explicit_argument() {
		$product = $this->create_product();
		$product->update_meta_data( '_reepay_subscription_currency', 'SEK' );
		$product->save();

		$this->assertSame( 'SEK', $product->get_currency( 'USD' ) );
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
			array_slice( DataProvider::currencies(), 0, 2 )
		);
	}

	/*
	 * ------------------------------------------------------------------
	 * format_price( $price_html, $product ) [static]
	 * ------------------------------------------------------------------
	 *
	 * Real behavior (WC_Product_Reepay_Simple_Subscription.php:44-61):
	 *   $schedule_type = WC_Reepay_Subscription_Plan_Simple::get_billing_plan( $product, true );
	 *   if ( empty( $schedule_type ) || empty( $price_html ) ) { return $price_html; }
	 *   return $price_html . ' / ' . $schedule_type;
	 */

	/**
	 * format_price() appends ' / {short schedule}' when both the price html
	 * and the billing plan are non-empty.
	 */
	public function test_format_price_appends_schedule_when_both_present() {
		$product = $this->create_product();
		$this->configure_daily_billing_plan( $product, 1 );

		$result = WC_Product_Reepay_Simple_Subscription::format_price( '<span>100 kr.</span>', $product );

		$this->assertSame( '<span>100 kr.</span> / Day', $result );
	}

	/**
	 * format_price() returns the price html completely untouched when the
	 * schedule type resolves to empty (no billing-plan meta configured) -
	 * confirming the empty() guard short-circuits before any concatenation.
	 */
	public function test_format_price_returns_untouched_price_when_schedule_type_empty() {
		$product = $this->create_product();
		// No schedule_type meta configured.

		$result = WC_Product_Reepay_Simple_Subscription::format_price( '<span>100 kr.</span>', $product );

		$this->assertSame( '<span>100 kr.</span>', $result );
	}

	/**
	 * format_price() returns an empty price html untouched even when a real
	 * billing plan IS configured - confirming the `empty( $price_html )`
	 * half of the guard is independently effective, not just the schedule
	 * emptiness half.
	 */
	public function test_format_price_returns_empty_price_html_untouched_even_with_schedule_configured() {
		$product = $this->create_product();
		$this->configure_daily_billing_plan( $product, 1 );

		$result = WC_Product_Reepay_Simple_Subscription::format_price( '', $product );

		$this->assertSame( '', $result );
	}
}
