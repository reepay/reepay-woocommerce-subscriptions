<?php
/**
 * Class WooBlocksTest
 *
 * Covers WC_Reepay_Woo_Blocks (includes/woo-blocks/WC_Reepay_Woo_Blocks.php).
 *
 * CONFIRMED by reading the full source:
 * - filter_gateways() delegates to WC_Reepay_Checkout::is_reepay_product_in_cart()
 *   (this plugin's own method, already proven safe elsewhere in this test
 *   suite) - no cross-plugin dependency, direct test with cart fixtures.
 * - set_token_saving($data) calls wcs_cart_have_subscription() and
 *   wcs_is_payment_change() UNCONDITIONALLY, with no function_exists guard
 *   (gotcha #4) - both are WooCommerce-Subscriptions-only functions. Guarded
 *   with PLUGINS_STATE::woo_subs_activated() below.
 *
 * @package Reepay\Subscriptions
 */

use Reepay\Subscriptions\Tests\Helpers\PLUGINS_STATE;
use Reepay\Subscriptions\Tests\Helpers\Reepay_Subscriptions_UnitTestCase;

/**
 * WooBlocksTest.
 */
class WooBlocksTest extends Reepay_Subscriptions_UnitTestCase {

	/**
	 * @var WC_Reepay_Woo_Blocks
	 */
	private WC_Reepay_Woo_Blocks $woo_blocks;

	public function set_up() {
		parent::set_up();

		$this->woo_blocks = new WC_Reepay_Woo_Blocks();
	}

	public function tear_down() {
		WC()->cart->empty_cart();

		parent::tear_down();
	}

	/**
	 * Test @see WC_Reepay_Woo_Blocks::__construct does not fatal and
	 * registers the expected hooks.
	 */
	public function test_constructor_registers_hooks() {
		$this->assertNotFalse( has_action( 'woocommerce_blocks_loaded', array( $this->woo_blocks, 'add_gateways_filter' ) ) );
		$this->assertNotFalse( has_action( 'reepay_blocks_payment_method_data', array( $this->woo_blocks, 'set_token_saving' ) ) );
	}

	/**
	 * Test @see WC_Reepay_Woo_Blocks::filter_gateways returns the
	 * subscriptions-only requirement when the cart contains a Reepay
	 * subscription product.
	 */
	public function test_filter_gateways_returns_requirement_when_reepay_product_in_cart() {
		PLUGINS_STATE::maybe_skip_test_by_product_type( 'rp_sub' );

		$product = ( new \Reepay\Subscriptions\Tests\Helpers\ProductGenerator( 'rp_sub' ) )->product();
		WC()->cart->empty_cart();
		WC()->cart->add_to_cart( $product->get_id() );

		$result = $this->woo_blocks->filter_gateways();

		$this->assertSame( array( 'woo_blocks_only_subscriptions_in_cart' ), $result );
	}

	/**
	 * Test @see WC_Reepay_Woo_Blocks::filter_gateways returns an empty array
	 * when the cart has no Reepay product.
	 */
	public function test_filter_gateways_returns_empty_without_reepay_product_in_cart() {
		$product = ( new \Reepay\Subscriptions\Tests\Helpers\ProductGenerator( 'simple' ) )->product();
		WC()->cart->empty_cart();
		WC()->cart->add_to_cart( $product->get_id() );

		$result = $this->woo_blocks->filter_gateways();

		$this->assertSame( array(), $result );
	}

	/**
	 * Test @see WC_Reepay_Woo_Blocks::set_token_saving sets
	 * always_save_token to true when the cart contains a WooCommerce
	 * Subscriptions subscription product. woo_subs-only (gotcha #4).
	 */
	public function test_set_token_saving_sets_flag_when_cart_has_subscription() {
		// CONFIRMED via the full 5-combination matrix, in THREE stages
		// (neither check alone is sufficient - both are required):
		// (1) PHPUNIT_PLUGINS=woo,woo_subs (no reepay_pay):
		// class_exists('WC_Subscriptions_Product', false) is TRUE (that
		// class loads independently) but
		// function_exists('wcs_cart_have_subscription') is FALSE - the real
		// woo_subs global functions file doesn't load in this combo.
		// (2) PHPUNIT_PLUGINS=woo,reepay_pay (no woo_subs): the inverse -
		// function_exists('wcs_cart_have_subscription') is TRUE (satisfied
		// by reepay-woocommerce-payment's own function_exists()-guarded shim
		// in includes/Functions/subscriptions.php, which gracefully returns
		// false via its own internal class_exists check) but class_exists
		// ('WC_Subscriptions_Product', false) is FALSE (woo_subs was never
		// included at all), so ProductGenerator('woo_sub') below can't build
		// a real subscription product fixture.
		// Only requiring BOTH checks together correctly skips in both of
		// those combos and correctly runs under the full combo.
		if ( ! function_exists( 'wcs_cart_have_subscription' ) || ! class_exists( 'WC_Subscriptions_Product', false ) ) {
			$this->markTestSkipped( 'wcs_cart_have_subscription()/wcs_is_payment_change() require both a callable function AND the real WooCommerce Subscriptions plugin (woo_subs), not just a callable shim.' );
		}

		$product = ( new \Reepay\Subscriptions\Tests\Helpers\ProductGenerator( 'woo_sub' ) )->product();
		WC()->cart->empty_cart();
		WC()->cart->add_to_cart( $product->get_id() );

		$result = $this->woo_blocks->set_token_saving( array() );

		$this->assertTrue( $result['always_save_token'] ?? false );
	}

	/**
	 * Test @see WC_Reepay_Woo_Blocks::set_token_saving leaves $data unchanged
	 * when the cart has no subscription and this isn't a payment-change
	 * context. woo_subs-only (gotcha #4).
	 */
	public function test_set_token_saving_passthrough_without_subscription_in_cart() {
		// Unlike the test above, this one uses an EMPTY cart and never
		// builds a real 'woo_sub' product - it only needs
		// wcs_cart_have_subscription()/wcs_is_payment_change() to be safely
		// callable, which the real function OR reepay-woocommerce-payment's
		// shim both satisfy (the shim gracefully returns false for an empty
		// cart / no real woo_subs product class, confirmed by reading
		// includes/Functions/subscriptions.php). function_exists() alone is
		// the correct, less restrictive guard for this specific test -
		// don't combine it with class_exists('WC_Subscriptions_Product')
		// like the test above, that would skip this unnecessarily under
		// PHPUNIT_PLUGINS=woo,reepay_pay.
		if ( ! function_exists( 'wcs_cart_have_subscription' ) ) {
			$this->markTestSkipped( 'wcs_cart_have_subscription()/wcs_is_payment_change() require WooCommerce Subscriptions (woo_subs) or the reepay-woocommerce-payment shim to be callable.' );
		}

		WC()->cart->empty_cart();

		$result = $this->woo_blocks->set_token_saving( array( 'foo' => 'bar' ) );

		$this->assertSame( array( 'foo' => 'bar' ), $result );
	}
}
