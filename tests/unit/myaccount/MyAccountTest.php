<?php
/**
 * Class MyAccountTest
 *
 * Covers WC_Reepay_My_Account (includes/my-account/WC_Reepay_My_Account.php):
 * rewrite_endpoint() and add_pages_to_woo_query_vars(). The constructor itself
 * (which instantiates all six sibling my-account classes and hooks 'init' /
 * 'woocommerce_get_query_vars') is exercised indirectly by every construction
 * below - no exit hazards in this file, both methods are plain hook callbacks.
 *
 * Phase 5 scope: baseline regression coverage per requirements.md §3.4, not
 * exhaustive scenario enumeration.
 *
 * Confirmed real behavior (read from source):
 * - The constructor instantiates WC_Reepay_My_Account_Orders_Page,
 *   WC_Reepay_My_Account_Payment_Method, WC_Reepay_My_Account_Subscription_Page,
 *   WC_Reepay_My_Account_Subscription_Actions, WC_Reepay_My_Account_Subscriptions_Page
 *   and WC_Reepay_My_Account_Payment_Methods_Actions, then hooks
 *   rewrite_endpoint() onto 'init' and add_pages_to_woo_query_vars() onto
 *   'woocommerce_get_query_vars'. Constructing WC_Reepay_My_Account more than
 *   once (e.g. across multiple test methods/files in this directory) is
 *   harmless: WordPress dedupes identical callback+priority add_action/
 *   add_filter registrations, confirmed by running the full tests/unit/myaccount
 *   directory together without any duplicate-output or duplicate-side-effect
 *   symptoms.
 * - rewrite_endpoint() calls add_rewrite_endpoint() twice unconditionally, then
 *   only if the 'woocommerce_reepay_subscriptions_activated' transient is set:
 *   flush_rewrite_rules() and delete_transient() for that same transient.
 * - add_pages_to_woo_query_vars($query_vars) is a pure array-mutation function:
 *   it adds the two my-account page slugs as query vars and returns the array.
 *
 * @package Reepay\Subscriptions
 */

use Reepay\Subscriptions\Tests\Helpers\Reepay_Subscriptions_UnitTestCase;

/**
 * MyAccountTest.
 */
class MyAccountTest extends Reepay_Subscriptions_UnitTestCase {

	/**
	 * @var WC_Reepay_My_Account
	 */
	private WC_Reepay_My_Account $my_account;

	public function set_up() {
		parent::set_up();

		$this->my_account = new WC_Reepay_My_Account();
	}

	public function tear_down() {
		delete_transient( 'woocommerce_reepay_subscriptions_activated' );

		parent::tear_down();
	}

	/*
	 * ------------------------------------------------------------------
	 * rewrite_endpoint()
	 * ------------------------------------------------------------------
	 */

	/**
	 * Test @see WC_Reepay_My_Account::rewrite_endpoint deletes the
	 * 'woocommerce_reepay_subscriptions_activated' transient once it has been
	 * consumed, after flushing rewrite rules.
	 */
	public function test_rewrite_endpoint_deletes_activation_transient_when_set() {
		set_transient( 'woocommerce_reepay_subscriptions_activated', true, 60 * 60 );

		$this->my_account->rewrite_endpoint();

		$this->assertFalse( get_transient( 'woocommerce_reepay_subscriptions_activated' ) );
	}

	/**
	 * Test @see WC_Reepay_My_Account::rewrite_endpoint does not error and leaves
	 * no activation transient behind when none was set to begin with.
	 */
	public function test_rewrite_endpoint_noop_without_activation_transient() {
		delete_transient( 'woocommerce_reepay_subscriptions_activated' );

		$this->my_account->rewrite_endpoint();

		$this->assertFalse( get_transient( 'woocommerce_reepay_subscriptions_activated' ) );
	}

	/**
	 * Test @see WC_Reepay_My_Account::rewrite_endpoint registers both my-account
	 * rewrite endpoints (subscriptions list + single subscription view).
	 */
	public function test_rewrite_endpoint_registers_both_endpoints() {
		global $wp_rewrite;

		$this->my_account->rewrite_endpoint();

		// WP_Rewrite::$endpoints entries are array($places, $name, $query_var) -
		// confirmed by reading WP_Rewrite::add_endpoint() in wp-includes/class-wp-rewrite.php.
		$registered_names = array_column( $wp_rewrite->endpoints, 1 );

		$this->assertContains( WC_Reepay_My_Account_Subscriptions_Page::$menu_item_slug, $registered_names );
		$this->assertContains( WC_Reepay_My_Account_Subscription_Page::$menu_item_slug, $registered_names );
	}

	/*
	 * ------------------------------------------------------------------
	 * add_pages_to_woo_query_vars()
	 * ------------------------------------------------------------------
	 */

	/**
	 * Test @see WC_Reepay_My_Account::add_pages_to_woo_query_vars adds both
	 * my-account page slugs as self-mapped query vars, preserving any existing
	 * entries.
	 */
	public function test_add_pages_to_woo_query_vars_adds_both_slugs() {
		$query_vars = array( 'orders' => 'orders' );

		$result = $this->my_account->add_pages_to_woo_query_vars( $query_vars );

		$this->assertSame( 'orders', $result['orders'] );
		$this->assertSame(
			WC_Reepay_My_Account_Subscriptions_Page::$menu_item_slug,
			$result[ WC_Reepay_My_Account_Subscriptions_Page::$menu_item_slug ]
		);
		$this->assertSame(
			WC_Reepay_My_Account_Subscription_Page::$menu_item_slug,
			$result[ WC_Reepay_My_Account_Subscription_Page::$menu_item_slug ]
		);
	}

	/**
	 * Test @see WC_Reepay_My_Account::add_pages_to_woo_query_vars works from an
	 * empty starting array too (pure function, no external state dependency).
	 */
	public function test_add_pages_to_woo_query_vars_from_empty_array() {
		$result = $this->my_account->add_pages_to_woo_query_vars( array() );

		$this->assertCount( 2, $result );
	}
}
