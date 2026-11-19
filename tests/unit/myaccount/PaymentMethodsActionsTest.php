<?php
/**
 * Class PaymentMethodsActionsTest
 *
 * Covers WC_Reepay_My_Account_Payment_Methods_Actions
 * (includes/my-account/WC_Reepay_My_Account_Payment_Methods_Actions.php):
 * delete_payment_method_action(). Uses the Ajax testcase base since the method
 * reads get_query_var()/$_GET directly.
 *
 * EXIT HAZARD - only one safe branch. Confirmed by reading the full method
 * body: if absint(get_query_var('delete-payment-method', 0)) is empty/zero,
 * the method returns immediately with no notice, no redirect, no exit - this
 * is the ONLY branch safely callable directly. Every other branch (a
 * non-zero token_id, whether or not it resolves to a real/owned/reepay token)
 * falls through to wc_nocache_headers() and ultimately wp_safe_redirect() +
 * exit() at the bottom of the method, regardless of outcome. Verified this
 * is true even for a non-zero token_id that does not resolve to a real token
 * at all: the ReepayTokens::is_reepay_token()/ReepayGateway::is_reepay_token()
 * early-return guard (lines 23-26) is itself gated by class_exists()/
 * method_exists() - under woo-only (reepay_pay inactive) neither class/method
 * exists, so that guard's condition is false and execution falls through to
 * the nonce check and redirect regardless. The nonce/ownership-check logic on
 * lines 30-46 is inline in this single static method (not factored into a
 * separate private helper), so unlike WC_Reepay_My_Account_Subscription_Actions
 * there is no ReflectionMethod escape hatch here - that logic is intentionally
 * left untested beyond the early-return, consistent with the brief's
 * documented coverage gap for this class.
 *
 * Phase 5 scope: baseline regression coverage per requirements.md §3.4, not
 * exhaustive scenario enumeration.
 *
 * @package Reepay\Subscriptions
 */

use Reepay\Subscriptions\Tests\Helpers\Reepay_Subscriptions_Ajax_UnitTestCase;

/**
 * PaymentMethodsActionsTest.
 */
class PaymentMethodsActionsTest extends Reepay_Subscriptions_Ajax_UnitTestCase {

	public function set_up() {
		parent::set_up();

		new WC_Reepay_My_Account_Payment_Methods_Actions();
	}

	public function tear_down() {
		global $wp_query;
		$wp_query->query_vars['delete-payment-method'] = null;

		parent::tear_down();
	}

	/*
	 * ------------------------------------------------------------------
	 * delete_payment_method_action() - the single safe early-return branch
	 * ------------------------------------------------------------------
	 */

	/**
	 * Test @see WC_Reepay_My_Account_Payment_Methods_Actions::delete_payment_method_action
	 * returns immediately (no notice added) when the 'delete-payment-method'
	 * query var is not set at all - absint(get_query_var(..., 0)) resolves to 0.
	 */
	public function test_delete_payment_method_action_noop_when_query_var_unset() {
		global $wp_query;
		unset( $wp_query->query_vars['delete-payment-method'] );

		WC_Reepay_My_Account_Payment_Methods_Actions::delete_payment_method_action();

		$this->assertEmpty( wc_get_notices( 'error' ) );
		$this->assertEmpty( wc_get_notices( 'success' ) );
	}

	/**
	 * Test @see WC_Reepay_My_Account_Payment_Methods_Actions::delete_payment_method_action
	 * returns immediately when the 'delete-payment-method' query var is
	 * explicitly '0'.
	 */
	public function test_delete_payment_method_action_noop_when_query_var_zero() {
		global $wp_query;
		set_query_var( 'delete-payment-method', '0' );

		WC_Reepay_My_Account_Payment_Methods_Actions::delete_payment_method_action();

		$this->assertEmpty( wc_get_notices( 'error' ) );
		$this->assertEmpty( wc_get_notices( 'success' ) );
	}
}
