<?php
/**
 * Class WooBlocksTermsTest
 *
 * Covers WC_Reepay_Woo_Blocks_Terms (includes/woo-blocks/WC_Reepay_Woo_Blocks_Terms.php).
 *
 * Per plan.md: the nested terms-checkbox/ subfolder this class require_once's
 * from is explicitly OUT OF SCOPE for this phase ("built JS/asset glue, not
 * business logic") - this file does NOT attempt to test
 * wc_reepay_woo_blocks_terms_init()'s full registration behavior or anything
 * under terms-checkbox/.
 *
 * is_woocommerce_blocks_active() and is_woocommerce_blocks_version_supported()
 * are both easy, safe, direct tests. Confirmed by reading WooCommerce core's
 * composer.json/plugin bundling: WooCommerce core bundles the Blocks package
 * as of the WC version installed in this test environment, so
 * is_woocommerce_blocks_active() is expected true here - verified directly
 * below rather than assumed.
 *
 * CONFIRMED side effect discovered by actually running this test: since
 * 'woocommerce_blocks_loaded' has already fired by the time tests run - both
 * from the real plugin bootstrap (once, before any test runs) AND from the
 * constructor's own did_action() check being true thereafter - every
 * `new WC_Reepay_Woo_Blocks_Terms()` call (including the very first one in
 * this file) re-runs wc_reepay_woo_blocks_terms_init() immediately,
 * including register_block_type('wc-reepay-woo-blocks-terms/checkbox', ...),
 * which is NOT idempotent: re-registering an already-registered block type
 * trips WordPress's "_doing_it_wrong"-style incorrect-usage notice, which
 * this suite's convertNoticesToExceptions turns into a fatal test error.
 * set_up() below proactively unregisters the block type (if present, e.g.
 * from the real plugin bootstrap or a previous test) immediately before
 * constructing a fresh instance, so every test - including the first -
 * starts from a clean, unregistered state. test-only wiring, no production
 * file touched.
 *
 * @package Reepay\Subscriptions
 */

use Reepay\Subscriptions\Tests\Helpers\Reepay_Subscriptions_UnitTestCase;

/**
 * WooBlocksTermsTest.
 */
class WooBlocksTermsTest extends Reepay_Subscriptions_UnitTestCase {

	/**
	 * @var WC_Reepay_Woo_Blocks_Terms
	 */
	private WC_Reepay_Woo_Blocks_Terms $woo_blocks_terms;

	public function set_up() {
		parent::set_up();

		$this->unregister_terms_checkbox_block();

		$this->woo_blocks_terms = new WC_Reepay_Woo_Blocks_Terms();
	}

	public function tear_down() {
		$this->unregister_terms_checkbox_block();

		parent::tear_down();
	}

	/**
	 * Unregister the 'wc-reepay-woo-blocks-terms/checkbox' block type if
	 * currently registered - see class docblock for why this is necessary.
	 */
	private function unregister_terms_checkbox_block(): void {
		if ( WP_Block_Type_Registry::get_instance()->is_registered( 'wc-reepay-woo-blocks-terms/checkbox' ) ) {
			WP_Block_Type_Registry::get_instance()->unregister( 'wc-reepay-woo-blocks-terms/checkbox' );
		}
	}

	/**
	 * Test @see WC_Reepay_Woo_Blocks_Terms::__construct does not fatal -
	 * smoke test per the lighter bar for this file.
	 */
	public function test_constructor_does_not_fatal() {
		$this->assertInstanceOf( WC_Reepay_Woo_Blocks_Terms::class, $this->woo_blocks_terms );
	}

	/**
	 * Test @see WC_Reepay_Woo_Blocks_Terms::is_woocommerce_blocks_active
	 * returns true in this test environment - WooCommerce core bundles the
	 * Blocks package.
	 */
	public function test_is_woocommerce_blocks_active_true_in_this_environment() {
		$this->assertTrue( $this->woo_blocks_terms->is_woocommerce_blocks_active() );
	}

	/**
	 * Test @see WC_Reepay_Woo_Blocks_Terms::is_woocommerce_blocks_version_supported
	 * reflects a real version_compare() against the actually-installed WC
	 * Blocks package version (>= 7.3.0).
	 */
	public function test_is_woocommerce_blocks_version_supported_matches_real_version_compare() {
		$expected = version_compare(
			\Automattic\WooCommerce\Blocks\Package::get_version(),
			'7.3.0',
			'>='
		);

		$this->assertSame( $expected, $this->woo_blocks_terms->is_woocommerce_blocks_version_supported() );
	}
}
