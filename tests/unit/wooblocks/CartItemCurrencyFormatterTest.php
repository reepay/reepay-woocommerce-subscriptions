<?php
/**
 * Class CartItemCurrencyFormatterTest
 *
 * Covers WC_Reepay_Subscription_Cart_Item_Currency_Formatter
 * (includes/woo-blocks/WC_Reepay_Subscription_Cart_Item_Currency_Formatter.php).
 *
 * STATIC-STATE LEAK (gotcha #6): $current_item_currency is a public static
 * property with no automatic reset. tear_down() below explicitly resets it
 * to null so one test's currency override can never leak into the next.
 *
 * format()'s early-return branch ($current_item_currency empty -> delegates
 * to parent::format()) and 2 of the 4 woocommerce_currency_pos position
 * branches (left, right) are covered directly below, per the brief's "2
 * positions plus the passthrough branch meets this task's bar" guidance.
 *
 * @package Reepay\Subscriptions
 */

use Reepay\Subscriptions\Tests\Helpers\Reepay_Subscriptions_UnitTestCase;

/**
 * CartItemCurrencyFormatterTest.
 */
class CartItemCurrencyFormatterTest extends Reepay_Subscriptions_UnitTestCase {

	/**
	 * @var WC_Reepay_Subscription_Cart_Item_Currency_Formatter
	 */
	private WC_Reepay_Subscription_Cart_Item_Currency_Formatter $formatter;

	/**
	 * Snapshot of the 'woocommerce_currency_pos' option, restored in
	 * tear_down().
	 *
	 * @var string
	 */
	private string $currency_pos_before;

	public function set_up() {
		parent::set_up();

		$this->formatter           = new WC_Reepay_Subscription_Cart_Item_Currency_Formatter();
		$this->currency_pos_before = get_option( 'woocommerce_currency_pos' );
	}

	public function tear_down() {
		// Gotcha #6: explicit static-state reset - no automatic reset exists
		// in production code.
		WC_Reepay_Subscription_Cart_Item_Currency_Formatter::$current_item_currency = null;

		update_option( 'woocommerce_currency_pos', $this->currency_pos_before );

		parent::tear_down();
	}

	/**
	 * Test @see WC_Reepay_Subscription_Cart_Item_Currency_Formatter::format
	 * delegates to parent::format() (shop-wide currency resolution) when
	 * $current_item_currency is empty - the passthrough branch.
	 */
	public function test_format_delegates_to_parent_when_no_item_currency_tracked() {
		WC_Reepay_Subscription_Cart_Item_Currency_Formatter::$current_item_currency = null;

		$result = $this->formatter->format( array( 'amount' => 1000 ) );

		$this->assertSame(
			get_woocommerce_currency(),
			$result['currency_code']
		);
	}

	/**
	 * Test @see WC_Reepay_Subscription_Cart_Item_Currency_Formatter::format
	 * stamps the tracked per-item currency and a LEFT-positioned prefix
	 * symbol (no space) when woocommerce_currency_pos is 'left'.
	 */
	public function test_format_left_position_sets_prefix_symbol() {
		update_option( 'woocommerce_currency_pos', 'left' );
		WC_Reepay_Subscription_Cart_Item_Currency_Formatter::$current_item_currency = 'USD';

		$result = $this->formatter->format( array( 'amount' => 1000 ) );

		$this->assertSame( 'USD', $result['currency_code'] );
		$this->assertSame( html_entity_decode( get_woocommerce_currency_symbol( 'USD' ) ), $result['currency_prefix'] );
		$this->assertSame( '', $result['currency_suffix'] );
	}

	/**
	 * Test @see WC_Reepay_Subscription_Cart_Item_Currency_Formatter::format
	 * stamps a RIGHT-positioned suffix symbol (no space) when
	 * woocommerce_currency_pos is 'right'.
	 */
	public function test_format_right_position_sets_suffix_symbol() {
		update_option( 'woocommerce_currency_pos', 'right' );
		WC_Reepay_Subscription_Cart_Item_Currency_Formatter::$current_item_currency = 'EUR';

		$result = $this->formatter->format( array( 'amount' => 1000 ) );

		$this->assertSame( 'EUR', $result['currency_code'] );
		$this->assertSame( '', $result['currency_prefix'] );
		$this->assertSame( html_entity_decode( get_woocommerce_currency_symbol( 'EUR' ) ), $result['currency_suffix'] );
	}
}
