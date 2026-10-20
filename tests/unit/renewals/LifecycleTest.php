<?php
/**
 * Class LifecycleTest
 *
 * Covers the subscription lifecycle methods of WC_Reepay_Renewals:
 * creation, renewal, hold, cancel, uncancel, expiry, status sync,
 * gateway filtering and total display.
 *
 * @package Reepay\Subscriptions
 */

use Reepay\Subscriptions\Tests\Helpers\PLUGINS_STATE;
use Reepay\Subscriptions\Tests\Helpers\Reepay_Subscriptions_UnitTestCase;
use Reepay\Subscriptions\Tests\Mocks\ApiResponseMock;

/**
 * LifecycleTest.
 */
class LifecycleTest extends Reepay_Subscriptions_UnitTestCase {

	/**
	 * @var WC_Reepay_Renewals
	 */
	private WC_Reepay_Renewals $renewals;

	public function set_up() {
		parent::set_up();

		$this->renewals = new WC_Reepay_Renewals();

		// Both WC_Reepay_Subscription_API (reepay_s()->api()) and the Frisbii Pay
		// Api class (reepay()->api()) bail out with an error *before* calling
		// wp_remote_request() when no private key is configured - which would
		// make ApiResponseMock unreachable. WC_Reepay_Subscription_API reads its
		// key from a settings snapshot cached once at plugin bootstrap (long
		// before this option exists), so updating the option alone has no
		// effect on it; inject a fake test key directly into the already
		// constructed singleton via reflection instead (reepay() exposes a
		// public reset_settings() for the same purpose, used below). This is
		// test-only wiring, no production code is touched.
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

		// Order status transitions in these tests (e.g. pending -> processing,
		// -> cancelled) would otherwise trigger WooCommerce's transactional order
		// emails. Rendering those emails calls WC_Reepay_Woocommerce_Subscription_Extension::clean_order_totals(),
		// which calls the WooCommerce Subscriptions function order_contains_subscription()
		// unconditionally (no function_exists guard) - fatal when woo_subs isn't
		// active. Unhooking WC's email dispatch is test-only wiring (no production
		// file is touched) and also keeps test output free of irrelevant email
		// side effects regardless of which plugin combo is active.
		foreach ( array(
			'woocommerce_order_status_pending_to_processing',
			'woocommerce_order_status_pending_to_completed',
			'woocommerce_order_status_processing_to_cancelled',
			'woocommerce_order_status_pending_to_cancelled',
			'woocommerce_order_status_pending_to_failed',
			'woocommerce_order_status_pending_to_on-hold',
			'woocommerce_order_status_failed_to_processing',
			'woocommerce_order_status_failed_to_completed',
			'woocommerce_order_status_failed_to_on-hold',
			'woocommerce_order_status_cancelled_to_processing',
			'woocommerce_order_status_cancelled_to_completed',
			'woocommerce_order_status_cancelled_to_on-hold',
			'woocommerce_order_status_on-hold_to_processing',
			'woocommerce_order_status_on-hold_to_cancelled',
			'woocommerce_order_status_on-hold_to_failed',
			'woocommerce_order_status_completed',
			'woocommerce_order_status_failed',
		) as $email_action ) {
			remove_action( $email_action, array( 'WC_Emails', 'send_transactional_email' ), 10 );
			remove_action( $email_action, array( 'WC_Emails', 'queue_transactional_email' ), 10 );
		}
	}

	public function tear_down() {
		ApiResponseMock::clear();

		parent::tear_down();
	}

	/**
	 * Set a private/protected property on an already-constructed object via
	 * reflection. Used only to inject a fake API key into the plugins' settings
	 * singletons, which cache their values once at bootstrap time - long before
	 * any option set in a test's set_up() would be read.
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

	/**
	 * Build an rp_sub order with a real customer and a payment token, ready to be
	 * handed to create_subscriptions().
	 *
	 * @return WC_Order
	 */
	private function build_subscription_order(): WC_Order {
		$customer_id = self::factory()->user->create();

		return $this->order_generator
			->add_product( 'rp_sub' )
			->set_prop( 'customer_id', $customer_id )
			->set_prop( 'payment_method', 'reepay_checkout' )
			->order();
	}

	/*
	 * ------------------------------------------------------------------
	 * create_subscriptions()
	 * ------------------------------------------------------------------
	 */

	/**
	 * Test @see WC_Reepay_Renewals::create_subscriptions with a single-line rp_sub
	 * order: no order splitting should occur (get_division_of_products_into_orders
	 * breaks early when there's only one item), the main order itself becomes the
	 * subscription order, and it is tagged with _reepay_subscription_handle and
	 * _reepay_is_subscription.
	 */
	public function test_create_subscriptions_single_item_tags_main_order() {
		PLUGINS_STATE::maybe_skip_test_by_product_type( 'rp_sub' );

		$order = $this->build_subscription_order();

		// One mocked response serves every outbound request made during the call
		// (customer PUT, subscription POST, payment-method POST) - we only assert
		// on the resulting order state, not on distinguishing the individual calls.
		ApiResponseMock::respond_with(
			array(
				'id'     => 'sub-123',
				'handle' => 'handle-123',
				'state'  => 'created',
			)
		);

		$this->renewals->create_subscriptions(
			array(
				'customer'       => 'cust-abc',
				'payment_method' => 'test-token-123',
			),
			$order
		);

		$order = wc_get_order( $order->get_id() );

		$this->assertEmpty(
			$order->get_meta( '_reepay_another_orders' ),
			'Single-item order should not be split into additional orders.'
		);
		$this->assertEquals( 1, $order->get_meta( '_reepay_is_subscription' ) );
		$this->assertNotEmpty(
			$order->get_meta( '_reepay_subscription_handle' ),
			'Main order should be tagged with the subscription handle once created.'
		);
		$this->assertSame(
			reepay_s()->settings( '_reepay_orders_default_subscription_status' ),
			'wc-' . $order->get_status(),
			'Order status should be moved to the configured default subscription status.'
		);
	}

	/**
	 * Test @see WC_Reepay_Renewals::create_subscriptions bails out (no API calls, no
	 * subscription handle set) when the order carries no payment token at all.
	 */
	public function test_create_subscriptions_without_token_adds_order_note_and_skips() {
		PLUGINS_STATE::maybe_skip_test_by_product_type( 'rp_sub' );

		$order = $this->build_subscription_order();
		// No reepay_token / _reepay_token meta and no 'payment_method' key in $data.

		$this->renewals->create_subscriptions( array( 'customer' => 'cust-abc' ), $order );

		$order = wc_get_order( $order->get_id() );

		$this->assertEmpty( $order->get_meta( '_reepay_subscription_handle' ) );

		$notes = wc_get_order_notes( array( 'order_id' => $order->get_id() ) );
		$found = false;
		foreach ( $notes as $note ) {
			if ( false !== strpos( $note->content, 'Unable to create subscription. Empty token' ) ) {
				$found = true;
			}
		}
		$this->assertTrue( $found, 'Expected an order note about the missing token.' );
	}

	/*
	 * ------------------------------------------------------------------
	 * renew_subscription() / hold_subscription() / cancel_subscription() /
	 * uncancel_subscription() / expired_subscription()
	 * ------------------------------------------------------------------
	 */

	/**
	 * Test @see WC_Reepay_Renewals::hold_subscription moves the order found by
	 * subscription handle to 'on-hold'.
	 */
	public function test_hold_subscription_sets_order_on_hold() {
		$order = $this->order_generator
			->add_product( 'rp_sub' )
			->set_prop( 'status', 'processing' )
			->order();
		$order->update_meta_data( '_reepay_subscription_handle', 'sub-hold-1' );
		$order->save_meta_data();

		ApiResponseMock::respond_with( array( 'handle' => 'sub-hold-1', 'state' => 'on_hold' ) );

		$this->renewals->hold_subscription( array( 'subscription' => 'sub-hold-1', 'event_type' => 'subscription_on_hold' ) );

		$order = wc_get_order( $order->get_id() );
		$this->assertSame( 'on-hold', $order->get_status() );
	}

	/**
	 * Test @see WC_Reepay_Renewals::cancel_subscription moves the order found by
	 * subscription handle to 'cancelled'.
	 */
	public function test_cancel_subscription_sets_order_cancelled() {
		$order = $this->order_generator
			->add_product( 'rp_sub' )
			->set_prop( 'status', 'processing' )
			->order();
		$order->update_meta_data( '_reepay_subscription_handle', 'sub-cancel-1' );
		$order->save_meta_data();

		ApiResponseMock::respond_with( array( 'handle' => 'sub-cancel-1', 'state' => 'cancelled' ) );

		$this->renewals->cancel_subscription( array( 'subscription' => 'sub-cancel-1', 'event_type' => 'subscription_cancelled' ) );

		$order = wc_get_order( $order->get_id() );
		$this->assertSame( 'cancelled', $order->get_status() );
	}

	/**
	 * Test @see WC_Reepay_Renewals::uncancel_subscription moves the order to the
	 * configured default renew status (not a hardcoded 'completed').
	 */
	public function test_uncancel_subscription_sets_order_to_default_renew_status() {
		$order = $this->order_generator
			->add_product( 'rp_sub' )
			->set_prop( 'status', 'cancelled' )
			->order();
		$order->update_meta_data( '_reepay_subscription_handle', 'sub-uncancel-1' );
		$order->save_meta_data();

		ApiResponseMock::respond_with( array( 'handle' => 'sub-uncancel-1', 'state' => 'active' ) );

		$this->renewals->uncancel_subscription( array( 'subscription' => 'sub-uncancel-1', 'event_type' => 'subscription_uncancelled' ) );

		$order          = wc_get_order( $order->get_id() );
		$expected_status = str_replace( 'wc-', '', reepay_s()->settings( '_reepay_suborders_default_renew_status' ) );
		$this->assertSame( $expected_status, $order->get_status() );
	}

	/**
	 * Test @see WC_Reepay_Renewals::expired_subscription moves the order to
	 * 'cancelled' (expiry is modeled as a cancellation of the WC order).
	 */
	public function test_expired_subscription_sets_order_cancelled() {
		$order = $this->order_generator
			->add_product( 'rp_sub' )
			->set_prop( 'status', 'processing' )
			->order();
		$order->update_meta_data( '_reepay_subscription_handle', 'sub-expired-1' );
		$order->save_meta_data();

		ApiResponseMock::respond_with( array( 'handle' => 'sub-expired-1', 'state' => 'expired' ) );

		$this->renewals->expired_subscription( array( 'subscription' => 'sub-expired-1', 'event_type' => 'subscription_expired' ) );

		$order = wc_get_order( $order->get_id() );
		$this->assertSame( 'cancelled', $order->get_status() );
	}

	/**
	 * Test @see WC_Reepay_Renewals::renew_subscription creates a child order under
	 * the parent and links it via _reepay_order. Requires the Frisbii Pay plugin
	 * (reepay()->api()->get_invoice_by_handle()) and rp_get_payment_method(), both
	 * of which are only defined when reepay_pay is active.
	 */
	public function test_renew_subscription_creates_child_order_under_parent() {
		if ( ! PLUGINS_STATE::reepay_pay_activated() ) {
			$this->markTestSkipped( 'Frisbii Pay (reepay_pay) plugin required for renew_subscription().' );
		}

		$parent_order = $this->order_generator
			->add_product( 'rp_sub' )
			->set_prop( 'payment_method', 'reepay_checkout' )
			->set_prop( 'status', 'processing' )
			->order();
		$parent_order->update_meta_data( '_reepay_subscription_handle', 'sub-renew-1' );
		$parent_order->save_meta_data();

		ApiResponseMock::respond_with(
			array(
				'state'    => 'settled',
				'customer' => 'cust-renew-1',
				// Deliberately no 'order_lines' - exercises the "no invoice lines" path.
			)
		);

		$renew_status = reepay_s()->settings( '_reepay_suborders_default_renew_status' );

		$this->renewals->renew_subscription(
			array(
				'order_id' => $parent_order->get_id(),
				'invoice'  => 'inv-renew-1',
				'subscription' => 'sub-renew-1',
				'event_type' => 'subscription_renewal',
			)
		);

		$parent_order = wc_get_order( $parent_order->get_id() );
		$this->assertSame( 'inv-renew-1', $parent_order->get_meta( '_reepay_order' ) );

		$children = wc_get_orders( array( 'parent' => $parent_order->get_id() ) );
		$this->assertCount( 1, $children, 'Exactly one child (renewal) order should be created.' );

		$child = $children[0];
		$this->assertSame( 'inv-renew-1', $child->get_meta( '_reepay_order' ) );
		$this->assertSame( str_replace( 'wc-', '', $renew_status ), $child->get_status() );
	}

	/*
	 * ------------------------------------------------------------------
	 * update_subscription_status()
	 * ------------------------------------------------------------------
	 */

	/**
	 * Test @see WC_Reepay_Renewals::update_subscription_status with
	 * $update_status = true actually transitions the order status.
	 */
	public function test_update_subscription_status_true_changes_status() {
		$order = $this->order_generator
			->add_product( 'rp_sub' )
			->set_prop( 'status', 'processing' )
			->order();
		$order->update_meta_data( '_reepay_subscription_handle', 'sub-update-true' );
		$order->save_meta_data();

		ApiResponseMock::respond_with( array( 'handle' => 'sub-update-true', 'state' => 'on_hold' ) );

		$result = WC_Reepay_Renewals::update_subscription_status(
			array( 'subscription' => 'sub-update-true' ),
			'wc-on-hold',
			true
		);

		$this->assertTrue( $result );
		$order = wc_get_order( $order->get_id() );
		$this->assertSame( 'on-hold', $order->get_status() );
	}

	/**
	 * Test @see WC_Reepay_Renewals::update_subscription_status with
	 * $update_status = false leaves the WC order status untouched but still
	 * refreshes the cached _reepay_subscription_dates meta.
	 */
	public function test_update_subscription_status_false_does_not_change_status() {
		$order = $this->order_generator
			->add_product( 'rp_sub' )
			->set_prop( 'status', 'processing' )
			->order();
		$order->update_meta_data( '_reepay_subscription_handle', 'sub-update-false' );
		$order->save_meta_data();

		ApiResponseMock::respond_with(
			array(
				'handle'  => 'sub-update-false',
				'state'   => 'active',
				'expires' => '2030-01-01T00:00:00.000Z',
			)
		);

		$result = WC_Reepay_Renewals::update_subscription_status(
			array( 'subscription' => 'sub-update-false' ),
			'wc-cancelled',
			false
		);

		$this->assertTrue( $result );
		$order = wc_get_order( $order->get_id() );
		$this->assertSame( 'processing', $order->get_status(), 'Status must be untouched when $update_status is false.' );
		$this->assertNotEmpty( $order->get_meta( '_reepay_subscription_dates' ), 'Dates should still be refreshed.' );
	}

	/**
	 * Test @see WC_Reepay_Renewals::update_subscription_status returns a WP_Error
	 * when $data has no 'subscription' key at all.
	 */
	public function test_update_subscription_status_missing_subscription_handle_returns_error() {
		$result = WC_Reepay_Renewals::update_subscription_status( array(), 'wc-cancelled' );

		$this->assertWPError( $result );
	}

	/*
	 * ------------------------------------------------------------------
	 * is_order_subscription_active() / is_order_contain_subscription()
	 * ------------------------------------------------------------------
	 */

	/**
	 * Test @see WC_Reepay_Renewals::is_order_contain_subscription returns the
	 * matching order item for an rp_sub order.
	 */
	public function test_is_order_contain_subscription_true_for_rp_sub_order() {
		PLUGINS_STATE::maybe_skip_test_by_product_type( 'rp_sub' );

		$order = $this->order_generator->add_product( 'rp_sub' )->order();

		$result = WC_Reepay_Renewals::is_order_contain_subscription( $order );

		$this->assertNotFalse( $result );
		$this->assertInstanceOf( 'WC_Order_Item', $result );
	}

	/**
	 * Test @see WC_Reepay_Renewals::is_order_contain_subscription returns false for
	 * a plain simple-product order.
	 */
	public function test_is_order_contain_subscription_false_for_simple_order() {
		$order = $this->order_generator->add_product( 'simple' )->order();

		$this->assertFalse( WC_Reepay_Renewals::is_order_contain_subscription( $order ) );
	}

	/**
	 * Test @see WC_Reepay_Renewals::is_order_subscription_active reads the
	 * subscription 'state' from the API and caches it in a transient, returning
	 * true only for state === 'active'.
	 */
	public function test_is_order_subscription_active_true_when_api_reports_active() {
		$order = $this->order_generator->add_product( 'rp_sub' )->order();
		$order->update_meta_data( '_reepay_subscription_handle', 'sub-active-1' );
		$order->save_meta_data();

		ApiResponseMock::respond_with( array( 'handle' => 'sub-active-1', 'state' => 'active' ) );

		$this->assertTrue( WC_Reepay_Renewals::is_order_subscription_active( $order ) );
	}

	/**
	 * Test @see WC_Reepay_Renewals::is_order_subscription_active returns false
	 * when the API reports a non-active state (e.g. 'cancelled').
	 */
	public function test_is_order_subscription_active_false_when_api_reports_inactive() {
		$order = $this->order_generator->add_product( 'rp_sub' )->order();
		$order->update_meta_data( '_reepay_subscription_handle', 'sub-active-2' );
		$order->save_meta_data();

		ApiResponseMock::respond_with( array( 'handle' => 'sub-active-2', 'state' => 'cancelled' ) );

		$this->assertFalse( WC_Reepay_Renewals::is_order_subscription_active( $order ) );
	}

	/**
	 * Test @see WC_Reepay_Renewals::is_order_subscription_active returns false
	 * immediately (no API call) when the order has no subscription handle at all.
	 */
	public function test_is_order_subscription_active_false_without_handle() {
		$order = $this->order_generator->add_product( 'simple' )->order();

		$this->assertFalse( WC_Reepay_Renewals::is_order_subscription_active( $order ) );
	}

	/*
	 * ------------------------------------------------------------------
	 * reepay_order_contains_subscription() / reepay_cart_subscription_count()
	 * ------------------------------------------------------------------
	 */

	/**
	 * Test @see WC_Reepay_Renewals::reepay_order_contains_subscription true case:
	 * any Reepay-type product (not only the "subscription" product types) counts,
	 * since this method delegates to WC_Reepay_Checkout::is_reepay_product().
	 */
	public function test_reepay_order_contains_subscription_true_for_rp_sub_order() {
		PLUGINS_STATE::maybe_skip_test_by_product_type( 'rp_sub' );

		$order = $this->order_generator->add_product( 'rp_sub' )->order();

		$this->assertTrue( $this->renewals->reepay_order_contains_subscription( $order ) );
	}

	/**
	 * Test @see WC_Reepay_Renewals::reepay_order_contains_subscription false case
	 * for a plain simple-product order.
	 */
	public function test_reepay_order_contains_subscription_false_for_simple_order() {
		$order = $this->order_generator->add_product( 'simple' )->order();

		$this->assertFalse( $this->renewals->reepay_order_contains_subscription( $order ) );
	}

	/**
	 * Test @see WC_Reepay_Renewals::reepay_cart_subscription_count sums the
	 * quantities of Reepay-type cart items and ignores non-Reepay ones. Note:
	 * unlike get_available_payment_gateways() (which reads $_GET['order_id']),
	 * this method reads WC()->cart->cart_contents directly - it does not touch
	 * any superglobal, so the plain base test case is sufficient here.
	 */
	public function test_reepay_cart_subscription_count_sums_reepay_items_only() {
		PLUGINS_STATE::maybe_skip_test_by_product_type( 'rp_sub' );

		$rp_product     = ( new \Reepay\Subscriptions\Tests\Helpers\ProductGenerator( 'rp_sub' ) )->product();
		$simple_product = ( new \Reepay\Subscriptions\Tests\Helpers\ProductGenerator( 'simple' ) )->product();

		WC()->cart->empty_cart();
		WC()->cart->add_to_cart( $rp_product->get_id(), 2 );
		WC()->cart->add_to_cart( $simple_product->get_id(), 5 );

		$this->assertSame( 2, $this->renewals->reepay_cart_subscription_count() );

		WC()->cart->empty_cart();
	}

	/**
	 * Test @see WC_Reepay_Renewals::reepay_cart_subscription_count returns 0 for
	 * an empty cart.
	 */
	public function test_reepay_cart_subscription_count_zero_for_empty_cart() {
		WC()->cart->empty_cart();

		$this->assertSame( 0, $this->renewals->reepay_cart_subscription_count() );
	}

	/*
	 * ------------------------------------------------------------------
	 * get_available_payment_gateways() / disable_for_sub()
	 * ------------------------------------------------------------------
	 */

	/**
	 * Test @see WC_Reepay_Renewals::get_available_payment_gateways removes a
	 * gateway that doesn't support subscriptions when the order referenced by
	 * $_GET['order_id'] contains a Reepay subscription product, and keeps a
	 * subscriptions-capable gateway.
	 */
	public function test_get_available_payment_gateways_filters_when_order_has_subscription() {
		PLUGINS_STATE::maybe_skip_test_by_product_type( 'rp_sub' );

		$order = $this->order_generator->add_product( 'rp_sub' )->order();

		$_GET['order_id'] = $order->get_id();

		$gateways = array(
			'no_subs_support'  => new LifecycleTest_FakeGateway( array() ),
			'with_subs_support' => new LifecycleTest_FakeGateway( array( 'subscriptions', 'multiple_subscriptions' ) ),
		);

		$filtered = $this->renewals->get_available_payment_gateways( $gateways );

		$this->assertArrayNotHasKey( 'no_subs_support', $filtered );
		$this->assertArrayHasKey( 'with_subs_support', $filtered );

		unset( $_GET['order_id'] );
	}

	/**
	 * Test @see WC_Reepay_Renewals::get_available_payment_gateways leaves the
	 * gateway list untouched when there is no subscription in the cart and no
	 * order_id pointing at a subscription order.
	 */
	public function test_get_available_payment_gateways_unfiltered_without_subscription() {
		$gateways = array(
			'no_subs_support' => new LifecycleTest_FakeGateway( array() ),
		);

		$filtered = $this->renewals->get_available_payment_gateways( $gateways );

		$this->assertArrayHasKey( 'no_subs_support', $filtered );
		$this->assertSame( $gateways, $filtered );
	}

	/**
	 * Test @see WC_Reepay_Renewals::disable_for_sub returns false (metabox hidden)
	 * for an order that has a _reepay_subscription_handle set.
	 */
	public function test_disable_for_sub_false_when_order_has_subscription_handle() {
		$order = $this->order_generator->add_product( 'rp_sub' )->order();
		$order->update_meta_data( '_reepay_subscription_handle', 'sub-disable-1' );
		$order->save_meta_data();

		$this->assertFalse( $this->renewals->disable_for_sub( true, $order ) );
	}

	/**
	 * Test @see WC_Reepay_Renewals::disable_for_sub passes through the given
	 * $is_able value unchanged for a plain order without a subscription handle.
	 */
	public function test_disable_for_sub_passthrough_without_subscription_handle() {
		$order = $this->order_generator->add_product( 'simple' )->order();

		$this->assertTrue( $this->renewals->disable_for_sub( true, $order ) );
		$this->assertFalse( $this->renewals->disable_for_sub( false, $order ) );
	}

	/*
	 * ------------------------------------------------------------------
	 * reepay_subscriptions_order_status() / status_manual_start_date()
	 * ------------------------------------------------------------------
	 */

	/**
	 * Test @see WC_Reepay_Renewals::reepay_subscriptions_order_status overrides the
	 * settled status to the configured default subscription status when the order
	 * contains a subscription product (manual-start-date setting left off).
	 */
	public function test_reepay_subscriptions_order_status_overridden_for_subscription_order() {
		PLUGINS_STATE::maybe_skip_test_by_product_type( 'rp_sub' );

		$order = $this->order_generator->add_product( 'rp_sub' )->order();

		$status = $this->renewals->reepay_subscriptions_order_status( 'wc-completed', $order );

		$this->assertSame(
			reepay_s()->settings( '_reepay_orders_default_subscription_status' ),
			$status
		);
	}

	/**
	 * Test @see WC_Reepay_Renewals::reepay_subscriptions_order_status leaves the
	 * incoming $status untouched for a plain (non-subscription) order.
	 */
	public function test_reepay_subscriptions_order_status_unchanged_for_simple_order() {
		$order = $this->order_generator->add_product( 'simple' )->order();

		$status = $this->renewals->reepay_subscriptions_order_status( 'wc-completed', $order );

		$this->assertSame( 'wc-completed', $status );
	}

	/**
	 * Test @see WC_Reepay_Renewals::status_manual_start_date signature: it is a
	 * hook callback for 'woocommerce_order_status_changed' taking
	 * ($order_id, $from, $to, $instance). For a non-Reepay payment method it must
	 * return immediately without altering the order total.
	 */
	public function test_status_manual_start_date_noop_for_non_reepay_payment_method() {
		$order = $this->order_generator
			->add_product( 'simple' )
			->set_prop( 'payment_method', 'bacs' )
			->order();

		$total_before = $order->get_total();

		$this->renewals->status_manual_start_date( $order->get_id(), 'pending', 'processing', $order );

		$order = wc_get_order( $order->get_id() );
		$this->assertSame( $total_before, $order->get_total(), 'Total must be untouched for non-Reepay orders.' );
		$this->assertEmpty( $order->get_meta( '_real_total' ) );
	}

	/**
	 * Test @see WC_Reepay_Renewals::status_manual_start_date zeroes the order total
	 * (storing the real total in _real_total) for a Reepay subscription order with
	 * a non-zero total, regardless of the manual-start-date setting.
	 */
	public function test_status_manual_start_date_zeroes_total_for_subscription_order() {
		PLUGINS_STATE::maybe_skip_test_by_product_type( 'rp_sub' );

		$order = $this->order_generator
			->add_product( 'rp_sub' )
			->set_prop( 'payment_method', 'reepay_checkout' )
			->order();

		$real_total = (float) $order->get_total();
		$this->assertGreaterThan( 0, $real_total, 'Fixture order is expected to have a non-zero total.' );

		$this->renewals->status_manual_start_date( $order->get_id(), 'pending', 'processing', $order );

		$order = wc_get_order( $order->get_id() );
		$this->assertSame( 0.0, (float) $order->get_total() );
		$this->assertEquals( $real_total, (float) $order->get_meta( '_real_total' ) );
	}

	/*
	 * ------------------------------------------------------------------
	 * display_real_total()
	 * ------------------------------------------------------------------
	 */

	/**
	 * Test @see WC_Reepay_Renewals::display_real_total falls back to the already
	 * formatted total unchanged when there is no stored _real_total meta.
	 */
	public function test_display_real_total_passthrough_without_real_total_meta() {
		$order = $this->order_generator->add_product( 'simple' )->order();

		$formatted = wc_price( $order->get_total() );
		$result    = $this->renewals->display_real_total( $formatted, $order, '', false );

		$this->assertSame( $formatted, $result );
	}

	/**
	 * Test @see WC_Reepay_Renewals::display_real_total reconstructs the real total
	 * price (not the zeroed order total) once _real_total meta has been stored,
	 * on the order-received (thank you) endpoint.
	 */
	public function test_display_real_total_reflects_real_total_on_order_received() {
		$order = $this->order_generator->add_product( 'simple' )->order();
		$order->update_meta_data( '_real_total', '42.50' );
		$order->save_meta_data();
		$order->set_total( 0 );
		$order->save();

		global $wp;
		$wp->query_vars['order-received'] = (string) $order->get_id();

		$formatted_zero = wc_price( 0, array( 'currency' => $order->get_currency() ) );
		$result         = $this->renewals->display_real_total( $formatted_zero, $order, '', false );

		unset( $wp->query_vars['order-received'] );

		$this->assertSame(
			wc_price( '42.50', array( 'currency' => $order->get_currency() ) ),
			$result
		);
	}
}

/**
 * Class LifecycleTest_FakeGateway
 *
 * Minimal WC_Payment_Gateway stand-in with a controllable `supports` feature
 * list, used to test WC_Reepay_Renewals::get_available_payment_gateways()
 * without depending on every real gateway registered by the Frisbii Pay plugin.
 */
class LifecycleTest_FakeGateway extends WC_Payment_Gateway {
	public function __construct( array $supports = array() ) {
		$this->id       = 'lifecycle_test_fake_gateway_' . spl_object_id( $this );
		$this->supports = $supports;
	}
}
