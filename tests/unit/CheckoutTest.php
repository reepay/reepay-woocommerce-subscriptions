<?php
/**
 * Class CheckoutTest
 *
 * Covers:
 *  - WC_Reepay_Checkout::is_reepay_product() (includes/WC_Reepay_Checkout.php:140-158)
 *  - WooCommerce_Reepay_Subscriptions::subscription_terms_checkbox_process()
 *    (reepay-subscriptions-for-woocommerce.php:884-902), accessed via the
 *    global singleton accessor reepay_s(). This method is NOT on
 *    WC_Reepay_Checkout despite the surface-level name similarity to the
 *    "subscription terms checkout validation" concept - confirmed by reading
 *    the main plugin file directly.
 *
 * @package Reepay\Subscriptions
 */

use Reepay\Subscriptions\Tests\Helpers\Reepay_Subscriptions_Ajax_UnitTestCase;
use Reepay\Subscriptions\Tests\Helpers\ProductGenerator;

/**
 * CheckoutTest.
 *
 * Uses the Ajax test case base because subscription_terms_checkbox_process()
 * reads directly from $_POST['subscription_terms'], and WC()->cart is needed
 * for is_reepay_product_in_cart()-style checks.
 */
class CheckoutTest extends Reepay_Subscriptions_Ajax_UnitTestCase {

	public function tear_down() {
		// Reset the option + notices + cart so one test can't leak into the next.
		delete_option( '_reepay_enable_subscription_terms' );
		wc_clear_notices();
		if ( ! is_null( WC()->cart ) ) {
			WC()->cart->empty_cart();
		}

		parent::tear_down();
	}

	/*
	 * ------------------------------------------------------------------
	 * WC_Reepay_Checkout::is_reepay_product()
	 * ------------------------------------------------------------------
	 *
	 * Real behavior (WC_Reepay_Checkout.php:140-158):
	 *   $product = wc_get_product( $product );
	 *   if ( empty( $product ) ) { return false; }
	 *   $type = $product->get_type();
	 *   if ( in_array( $type, $skip_types ) ) { return true; }
	 *   if ( $product->is_type( 'variation' ) ) {
	 *       $product = wc_get_product( $product->get_parent_id() );
	 *       $type = $product->get_type();
	 *   }
	 *   return str_contains( $type, 'reepay' );
	 *
	 * So it accepts a product object OR a raw ID (wc_get_product() normalizes
	 * either), and the "is reepay" check is string-based on the product type
	 * slug (e.g. 'reepay_simple_subscriptions' contains 'reepay').
	 */

	/**
	 * Test @see WC_Reepay_Checkout::is_reepay_product returns true for a real
	 * 'rp_sub' (WC_Product_Reepay_Simple_Subscription) product, by product ID.
	 */
	public function test_is_reepay_product_true_for_rp_sub_product_id() {
		$product = ( new ProductGenerator( 'rp_sub' ) )->product();

		$this->assertTrue( WC_Reepay_Checkout::is_reepay_product( $product->get_id() ) );
	}

	/**
	 * Test @see WC_Reepay_Checkout::is_reepay_product returns true for a real
	 * 'rp_sub' product passed directly as a WC_Product object (not just an ID).
	 */
	public function test_is_reepay_product_true_for_rp_sub_product_object() {
		$product = ( new ProductGenerator( 'rp_sub' ) )->product();

		$this->assertTrue( WC_Reepay_Checkout::is_reepay_product( $product ) );
	}

	/**
	 * Test @see WC_Reepay_Checkout::is_reepay_product returns false for a plain
	 * 'simple' WooCommerce product (its type slug 'simple' does not contain
	 * 'reepay').
	 */
	public function test_is_reepay_product_false_for_simple_product() {
		$product = ( new ProductGenerator( 'simple' ) )->product();

		$this->assertFalse( WC_Reepay_Checkout::is_reepay_product( $product->get_id() ) );
	}

	/**
	 * Test @see WC_Reepay_Checkout::is_reepay_product returns false for a
	 * non-existent product ID - wc_get_product() returns false/null for an
	 * unknown ID, which the empty() guard turns into a false return, never
	 * reaching get_type().
	 */
	public function test_is_reepay_product_false_for_nonexistent_product_id() {
		$nonexistent_id = 999999999;

		$this->assertFalse( WC_Reepay_Checkout::is_reepay_product( $nonexistent_id ) );
	}

	/**
	 * Test @see WC_Reepay_Checkout::is_reepay_product with no argument at all
	 * (default $product = false) also returns false, since wc_get_product(false)
	 * cannot resolve to a product.
	 */
	public function test_is_reepay_product_false_for_default_false_argument() {
		$this->assertFalse( WC_Reepay_Checkout::is_reepay_product() );
	}

	/*
	 * ------------------------------------------------------------------
	 * WooCommerce_Reepay_Subscriptions::subscription_terms_checkbox_process()
	 * via reepay_s()->subscription_terms_checkbox_process()
	 * ------------------------------------------------------------------
	 *
	 * Real behavior (reepay-subscriptions-for-woocommerce.php:884-902):
	 *   if ( get_option('_reepay_enable_subscription_terms') === 'yes' ) {
	 *       $has_reepay_product = false;
	 *       foreach ( WC()->cart->get_cart() as $cart_item ) {
	 *           if ( WC_Reepay_Checkout::is_reepay_product( $cart_item['data'] ) ) {
	 *               $has_reepay_product = true;
	 *               break;
	 *           }
	 *       }
	 *       if ( $has_reepay_product && ! isset( $_POST['subscription_terms'] ) ) {
	 *           wc_add_notice( 'Please read and accept the subscription terms to proceed', 'error' );
	 *       }
	 *   }
	 * Notably: when the option is disabled, the method is a complete no-op
	 * (never even looks at the cart or $_POST). When $_POST['subscription_terms']
	 * IS set, no notice is added regardless of cart contents.
	 */

	/**
	 * Test @see WooCommerce_Reepay_Subscriptions::subscription_terms_checkbox_process
	 * adds the expected error notice when: the option is enabled, a Reepay
	 * product is in the cart, and $_POST['subscription_terms'] is NOT set.
	 */
	public function test_checkbox_process_adds_notice_when_enabled_and_unchecked() {
		update_option( '_reepay_enable_subscription_terms', 'yes' );

		$product = ( new ProductGenerator( 'rp_sub' ) )->product();
		WC()->cart->empty_cart();
		WC()->cart->add_to_cart( $product->get_id() );

		unset( $_POST['subscription_terms'] );

		wc_clear_notices();
		reepay_s()->subscription_terms_checkbox_process();

		$errors = wc_get_notices( 'error' );
		$this->assertNotEmpty( $errors, 'Expected an error notice when the checkbox is unchecked.' );

		$found = false;
		foreach ( $errors as $notice ) {
			if ( false !== strpos( $notice['notice'], 'Please read and accept the subscription terms' ) ) {
				$found = true;
			}
		}
		$this->assertTrue( $found, 'Expected the specific subscription-terms error message.' );
	}

	/**
	 * Test @see WooCommerce_Reepay_Subscriptions::subscription_terms_checkbox_process
	 * does NOT add the notice when $_POST['subscription_terms'] IS set, even
	 * though the option is enabled and a Reepay product is in the cart.
	 */
	public function test_checkbox_process_no_notice_when_checkbox_is_checked() {
		update_option( '_reepay_enable_subscription_terms', 'yes' );

		$product = ( new ProductGenerator( 'rp_sub' ) )->product();
		WC()->cart->empty_cart();
		WC()->cart->add_to_cart( $product->get_id() );

		$_POST['subscription_terms'] = '1';

		wc_clear_notices();
		reepay_s()->subscription_terms_checkbox_process();

		$this->assertEmpty( wc_get_notices( 'error' ), 'No error notice expected once the checkbox is checked.' );
	}

	/**
	 * Test @see WooCommerce_Reepay_Subscriptions::subscription_terms_checkbox_process
	 * is a full no-op (no notice) when the option is disabled entirely, even
	 * with a Reepay product in the cart and no checkbox value posted.
	 */
	public function test_checkbox_process_no_notice_when_option_disabled() {
		update_option( '_reepay_enable_subscription_terms', 'no' );

		$product = ( new ProductGenerator( 'rp_sub' ) )->product();
		WC()->cart->empty_cart();
		WC()->cart->add_to_cart( $product->get_id() );

		unset( $_POST['subscription_terms'] );

		wc_clear_notices();
		reepay_s()->subscription_terms_checkbox_process();

		$this->assertEmpty( wc_get_notices( 'error' ), 'No error notice expected when the option is disabled.' );
	}

	/**
	 * Test @see WooCommerce_Reepay_Subscriptions::subscription_terms_checkbox_process
	 * does not add a notice when the option is enabled but the cart has no
	 * Reepay product at all (has_reepay_product stays false).
	 */
	public function test_checkbox_process_no_notice_without_reepay_product_in_cart() {
		update_option( '_reepay_enable_subscription_terms', 'yes' );

		$product = ( new ProductGenerator( 'simple' ) )->product();
		WC()->cart->empty_cart();
		WC()->cart->add_to_cart( $product->get_id() );

		unset( $_POST['subscription_terms'] );

		wc_clear_notices();
		reepay_s()->subscription_terms_checkbox_process();

		$this->assertEmpty( wc_get_notices( 'error' ), 'No error notice expected without a Reepay product in the cart.' );
	}
}
