<?php
/**
 * Class PaymentVerificationTest
 *
 * Covers the WC_Reepay_Renewals changes made after the first unit-test round
 * (commit a9f328c):
 *
 * - order_payment_verified() (private static, invoked via ReflectionMethod):
 *   an already-paid order passes without an API call; cancelled/refunded
 *   orders and orders without a '_reepay_order' handle fail; otherwise the
 *   Frisbii invoice must be 'authorized'/'settled' and cover the order total
 *   (settled_amount for settled, amount for authorized). Any API error fails.
 * - create_subscriptions_handle() for 'customer_payment_method_added': an
 *   order matched by its own 'reepay_session_id' skips the verification; an
 *   order matched through rp_get_order_by_customer()'s fallback is only
 *   processed when order_payment_verified() passes. "Processed" is observed
 *   via create_subscriptions()'s "Empty token" order note (no token is set
 *   on the fixture orders), which needs no further API round trips.
 * - create_subscriptions(): an order WITHOUT a customer is linked to the WP
 *   user matching its billing e-mail and flagged
 *   '_reepay_customer_linked_by_email'; an order that already has a
 *   (different) customer is left alone. The customer PUT to Frisbii includes
 *   the order's VAT number (rp_get_order_vat_number()).
 * - change_user_role() refuses: e-mail-linked customers, roles that do not
 *   exist, roles with 'manage_options', and administrator customers.
 * - create_order_copy() with $invoice_data: a drift of exactly one minor unit
 *   between the calculated total and Frisbii's authoritative invoice amount
 *   is reconciled to Frisbii's amount; a larger drift is left alone.
 *
 * rp_get_order_by_session()/rp_get_order_by_customer()/rp_prepare_amount()/
 * rp_make_initial_amount()/rp_get_order_vat_number() all come from the
 * Frisbii Pay (reepay_pay) plugin, so the tests that depend on them are
 * skipped when it is not active.
 *
 * @package Reepay\Subscriptions
 */

use Reepay\Subscriptions\Tests\Helpers\PLUGINS_STATE;
use Reepay\Subscriptions\Tests\Helpers\Reepay_Subscriptions_UnitTestCase;
use Reepay\Subscriptions\Tests\Mocks\ApiResponseMock;

/**
 * PaymentVerificationTest.
 */
class PaymentVerificationTest extends Reepay_Subscriptions_UnitTestCase {

	/**
	 * @var WC_Reepay_Renewals
	 */
	private WC_Reepay_Renewals $renewals;

	/**
	 * Number of outbound HTTP requests seen during a test.
	 *
	 * @var int
	 */
	private int $request_count = 0;

	/**
	 * @var callable|null
	 */
	private $request_counter = null;

	public function set_up() {
		parent::set_up();

		$this->renewals = new WC_Reepay_Renewals();

		// Same API-key wiring as LifecycleTest::set_up(): both API clients bail
		// out before wp_remote_request() (and so before ApiResponseMock) without
		// a configured key, and WC_Reepay_Subscription_API caches its key at
		// bootstrap, so inject it via reflection. Test-only wiring.
		update_option(
			'woocommerce_reepay_checkout_settings',
			array(
				'private_key_test' => 'test-key-reepay',
				'test_mode'        => 'yes',
			)
		);

		$this->set_private_property( WC_Reepay_Subscription_API::get_instance(), 'private_key_test', 'test-key-reepay' );
		$this->set_private_property( WC_Reepay_Subscription_API::get_instance(), 'test_mode', true );

		if ( PLUGINS_STATE::reepay_pay_activated() ) {
			reepay()->reset_settings();
		}

		// Count requests without answering them (priority 5, returns $preempt
		// untouched so ApiResponseMock at priority 10 still responds).
		$this->request_count   = 0;
		$this->request_counter = function ( $preempt ) {
			$this->request_count++;

			return $preempt;
		};
		add_filter( 'pre_http_request', $this->request_counter, 5 );

		// Same reason as LifecycleTest: status changes would otherwise render
		// transactional e-mails, which is irrelevant here.
		foreach ( array(
			'woocommerce_order_status_pending_to_processing',
			'woocommerce_order_status_pending_to_completed',
			'woocommerce_order_status_pending_to_cancelled',
			'woocommerce_order_status_pending_to_on-hold',
			'woocommerce_order_status_processing_to_cancelled',
			'woocommerce_order_status_completed',
		) as $email_action ) {
			remove_action( $email_action, array( 'WC_Emails', 'send_transactional_email' ), 10 );
			remove_action( $email_action, array( 'WC_Emails', 'queue_transactional_email' ), 10 );
		}
	}

	public function tear_down() {
		remove_filter( 'pre_http_request', $this->request_counter, 5 );
		ApiResponseMock::clear();

		parent::tear_down();
	}

	/**
	 * Set a private/protected property on an already-constructed object.
	 *
	 * @param object $object object instance to modify.
	 * @param string $property property name.
	 * @param mixed  $value value to assign.
	 */
	private function set_private_property( object $object, string $property, $value ): void {
		$ref = new ReflectionProperty( $object, $property );
		$ref->setAccessible( true );
		$ref->setValue( $object, $value );
	}

	/**
	 * Call the private static WC_Reepay_Renewals::order_payment_verified().
	 *
	 * @param WC_Order $order order to verify.
	 *
	 * @return bool
	 */
	private function order_payment_verified( WC_Order $order ): bool {
		$method = new ReflectionMethod( WC_Reepay_Renewals::class, 'order_payment_verified' );
		$method->setAccessible( true );

		return $method->invoke( null, $order );
	}

	/**
	 * A pending rp_sub order (total 12.23) with a Frisbii invoice handle.
	 *
	 * @return WC_Order
	 */
	private function build_pending_order_with_handle(): WC_Order {
		$order = $this->order_generator->add_product( 'rp_sub' )->order();
		$order->update_meta_data( '_reepay_order', 'order-' . $order->get_id() );
		$order->save();

		return $order;
	}

	/**
	 * The order total in minor units, as order_payment_verified() computes it.
	 *
	 * @param WC_Order $order order.
	 *
	 * @return int
	 */
	private function expected_minor_amount( WC_Order $order ): int {
		return (int) rp_prepare_amount( (float) $order->get_total(), $order->get_currency() );
	}

	/**
	 * Whether the order has create_subscriptions()'s "Empty token" note, i.e.
	 * create_subscriptions_handle() got past all of its guards.
	 *
	 * @param int $order_id order ID.
	 *
	 * @return bool
	 */
	private function reached_create_subscriptions( int $order_id ): bool {
		foreach ( wc_get_order_notes( array( 'order_id' => $order_id ) ) as $note ) {
			if ( false !== strpos( $note->content, 'Unable to create subscription. Empty token' ) ) {
				return true;
			}
		}

		return false;
	}

	/*
	 * ------------------------------------------------------------------
	 * order_payment_verified()
	 * ------------------------------------------------------------------
	 */

	/**
	 * Test @see WC_Reepay_Renewals::order_payment_verified returns true for an
	 * order WooCommerce already counts as paid, without calling the API.
	 */
	public function test_order_payment_verified_true_for_paid_order_without_api_call() {
		$order = $this->build_pending_order_with_handle();
		$order->set_status( 'processing' );
		$order->save();

		$this->assertTrue( $this->order_payment_verified( $order ) );
		$this->assertSame( 0, $this->request_count );
	}

	/**
	 * Test @see WC_Reepay_Renewals::order_payment_verified returns false for
	 * cancelled and refunded orders, without calling the API.
	 */
	public function test_order_payment_verified_false_for_cancelled_and_refunded() {
		foreach ( array( 'cancelled', 'refunded' ) as $status ) {
			$order = $this->build_pending_order_with_handle();
			$order->set_status( $status );
			$order->save();

			$this->assertFalse( $this->order_payment_verified( $order ), "Status: $status" );
		}

		$this->assertSame( 0, $this->request_count );
	}

	/**
	 * Test @see WC_Reepay_Renewals::order_payment_verified returns false when
	 * the order has no '_reepay_order' invoice handle.
	 */
	public function test_order_payment_verified_false_without_invoice_handle() {
		$order = $this->order_generator->add_product( 'rp_sub' )->order();

		$this->assertFalse( $this->order_payment_verified( $order ) );
		$this->assertSame( 0, $this->request_count );
	}

	/**
	 * Test @see WC_Reepay_Renewals::order_payment_verified returns true for a
	 * settled invoice whose settled_amount covers the order total.
	 */
	public function test_order_payment_verified_true_for_settled_invoice_covering_total() {
		PLUGINS_STATE::maybe_skip_test_by_product_type( 'rp_sub' );
		if ( ! PLUGINS_STATE::reepay_pay_activated() ) {
			$this->markTestSkipped( 'rp_prepare_amount() requires the Frisbii Pay (reepay_pay) plugin.' );
		}

		$order = $this->build_pending_order_with_handle();

		ApiResponseMock::respond_with(
			array(
				'state'          => 'settled',
				'settled_amount' => $this->expected_minor_amount( $order ),
				'amount'         => 0,
			)
		);

		$this->assertTrue( $this->order_payment_verified( $order ) );
		$this->assertSame( 1, $this->request_count );
	}

	/**
	 * Test @see WC_Reepay_Renewals::order_payment_verified returns true for an
	 * authorized invoice whose amount covers the order total.
	 */
	public function test_order_payment_verified_true_for_authorized_invoice_covering_total() {
		if ( ! PLUGINS_STATE::reepay_pay_activated() ) {
			$this->markTestSkipped( 'rp_prepare_amount() requires the Frisbii Pay (reepay_pay) plugin.' );
		}

		$order = $this->build_pending_order_with_handle();

		ApiResponseMock::respond_with(
			array(
				'state'  => 'authorized',
				'amount' => $this->expected_minor_amount( $order ),
			)
		);

		$this->assertTrue( $this->order_payment_verified( $order ) );
	}

	/**
	 * Test @see WC_Reepay_Renewals::order_payment_verified returns false when
	 * the invoice amount is one minor unit short of the order total.
	 */
	public function test_order_payment_verified_false_for_underpaid_invoice() {
		if ( ! PLUGINS_STATE::reepay_pay_activated() ) {
			$this->markTestSkipped( 'rp_prepare_amount() requires the Frisbii Pay (reepay_pay) plugin.' );
		}

		$order = $this->build_pending_order_with_handle();

		ApiResponseMock::respond_with(
			array(
				'state'  => 'authorized',
				'amount' => $this->expected_minor_amount( $order ) - 1,
			)
		);

		$this->assertFalse( $this->order_payment_verified( $order ) );
	}

	/**
	 * Test @see WC_Reepay_Renewals::order_payment_verified uses settled_amount
	 * (not amount) for a settled invoice.
	 */
	public function test_order_payment_verified_settled_invoice_uses_settled_amount() {
		if ( ! PLUGINS_STATE::reepay_pay_activated() ) {
			$this->markTestSkipped( 'rp_prepare_amount() requires the Frisbii Pay (reepay_pay) plugin.' );
		}

		$order = $this->build_pending_order_with_handle();

		ApiResponseMock::respond_with(
			array(
				'state'          => 'settled',
				'amount'         => $this->expected_minor_amount( $order ),
				'settled_amount' => 0,
			)
		);

		$this->assertFalse( $this->order_payment_verified( $order ) );
	}

	/**
	 * Test @see WC_Reepay_Renewals::order_payment_verified returns false for an
	 * invoice that is not authorized or settled, even with a full amount.
	 */
	public function test_order_payment_verified_false_for_unpaid_invoice_state() {
		if ( ! PLUGINS_STATE::reepay_pay_activated() ) {
			$this->markTestSkipped( 'rp_prepare_amount() requires the Frisbii Pay (reepay_pay) plugin.' );
		}

		$order = $this->build_pending_order_with_handle();

		ApiResponseMock::respond_with(
			array(
				'state'  => 'created',
				'amount' => $this->expected_minor_amount( $order ),
			)
		);

		$this->assertFalse( $this->order_payment_verified( $order ) );
	}

	/**
	 * Test @see WC_Reepay_Renewals::order_payment_verified returns false when
	 * the invoice request fails.
	 */
	public function test_order_payment_verified_false_on_api_error() {
		$order = $this->build_pending_order_with_handle();

		ApiResponseMock::respond_with( array( 'error' => 'Not found' ), 404 );

		$this->assertFalse( $this->order_payment_verified( $order ) );
		$this->assertSame( 1, $this->request_count );
	}

	/*
	 * ------------------------------------------------------------------
	 * create_subscriptions_handle() - customer_payment_method_added gate
	 * ------------------------------------------------------------------
	 */

	/**
	 * Test @see WC_Reepay_Renewals::create_subscriptions_handle processes an
	 * unpaid order matched by its own 'reepay_session_id' without verifying
	 * payment (the Frisbii invoice does not exist yet at that point).
	 */
	public function test_payment_method_added_matched_by_session_skips_verification() {
		PLUGINS_STATE::maybe_skip_test_by_product_type( 'rp_sub' );
		if ( ! PLUGINS_STATE::reepay_pay_activated() ) {
			$this->markTestSkipped( 'rp_get_order_by_session() requires the Frisbii Pay (reepay_pay) plugin.' );
		}

		$order = $this->order_generator->add_product( 'rp_sub' )->order();
		$order->update_meta_data( 'reepay_session_id', 'cs_session_own_1' );
		$order->save();

		$this->renewals->create_subscriptions_handle(
			array(
				'event_type'               => 'customer_payment_method_added',
				'payment_method_reference' => 'cs_session_own_1',
				'customer'                 => 'cust-session-1',
			)
		);

		$this->assertTrue( $this->reached_create_subscriptions( $order->get_id() ) );
		$this->assertSame( 0, $this->request_count, 'No invoice verification request expected for a session match.' );
	}

	/**
	 * Test @see WC_Reepay_Renewals::create_subscriptions_handle skips an
	 * unpaid order that was only found through the customer-handle fallback
	 * and whose invoice is not paid.
	 */
	public function test_payment_method_added_fallback_match_unpaid_order_is_skipped() {
		PLUGINS_STATE::maybe_skip_test_by_product_type( 'rp_sub' );
		if ( ! PLUGINS_STATE::reepay_pay_activated() ) {
			$this->markTestSkipped( 'rp_get_order_by_session() requires the Frisbii Pay (reepay_pay) plugin.' );
		}

		$order = $this->build_pending_order_with_handle();

		// One response serves both the list/invoice lookup (content) and the
		// invoice/{handle} verification (state/amount).
		ApiResponseMock::respond_with(
			array(
				'content' => array( array( 'handle' => 'order-' . $order->get_id() ) ),
				'state'   => 'created',
				'amount'  => $this->expected_minor_amount( $order ),
			)
		);

		$this->renewals->create_subscriptions_handle(
			array(
				'event_type'               => 'customer_payment_method_added',
				'payment_method_reference' => 'cs_session_unrelated',
				'customer'                 => 'cust-fallback-1',
			)
		);

		$this->assertFalse( $this->reached_create_subscriptions( $order->get_id() ) );
		$this->assertEmpty( wc_get_order( $order->get_id() )->get_meta( '_reepay_subscription_handle' ) );
	}

	/**
	 * Test @see WC_Reepay_Renewals::create_subscriptions_handle processes an
	 * order found through the customer-handle fallback when its invoice is
	 * settled for the full amount.
	 */
	public function test_payment_method_added_fallback_match_paid_invoice_is_processed() {
		PLUGINS_STATE::maybe_skip_test_by_product_type( 'rp_sub' );
		if ( ! PLUGINS_STATE::reepay_pay_activated() ) {
			$this->markTestSkipped( 'rp_get_order_by_session() requires the Frisbii Pay (reepay_pay) plugin.' );
		}

		$order = $this->build_pending_order_with_handle();

		ApiResponseMock::respond_with(
			array(
				'content'        => array( array( 'handle' => 'order-' . $order->get_id() ) ),
				'state'          => 'settled',
				'settled_amount' => $this->expected_minor_amount( $order ),
			)
		);

		$this->renewals->create_subscriptions_handle(
			array(
				'event_type'               => 'customer_payment_method_added',
				'payment_method_reference' => 'cs_session_unrelated',
				'customer'                 => 'cust-fallback-2',
			)
		);

		$this->assertTrue( $this->reached_create_subscriptions( $order->get_id() ) );
	}

	/*
	 * ------------------------------------------------------------------
	 * create_subscriptions() - linking a customer by billing e-mail, VAT
	 * ------------------------------------------------------------------
	 */

	/**
	 * Test @see WC_Reepay_Renewals::create_subscriptions links an order with
	 * no customer to the WP user matching its billing e-mail, and flags it
	 * '_reepay_customer_linked_by_email'.
	 */
	public function test_create_subscriptions_links_guest_order_by_billing_email() {
		PLUGINS_STATE::maybe_skip_test_by_product_type( 'rp_sub' );

		$user_id = self::factory()->user->create( array( 'user_email' => 'linked-by-email@example.com' ) );

		$order = $this->order_generator
			->add_product( 'rp_sub' )
			->set_prop( 'billing_email', 'linked-by-email@example.com' )
			->set_prop( 'payment_method', 'reepay_checkout' )
			->order();

		$this->assertSame( 0, $order->get_customer_id(), 'Fixture order must have no customer.' );

		ApiResponseMock::respond_with( array( 'id' => 'sub-1', 'handle' => 'handle-1', 'state' => 'created' ) );

		$this->renewals->create_subscriptions(
			array(
				'customer'       => 'cust-email-1',
				'payment_method' => 'tok-email-1',
			),
			$order
		);

		$order = wc_get_order( $order->get_id() );
		$this->assertSame( $user_id, $order->get_customer_id() );
		$this->assertSame( 'yes', $order->get_meta( '_reepay_customer_linked_by_email' ) );
	}

	/**
	 * Test @see WC_Reepay_Renewals::create_subscriptions does not re-link an
	 * order that already has a customer, even when the billing e-mail belongs
	 * to a different WP user.
	 */
	public function test_create_subscriptions_keeps_existing_customer() {
		PLUGINS_STATE::maybe_skip_test_by_product_type( 'rp_sub' );

		$owner_id = self::factory()->user->create();
		self::factory()->user->create( array( 'user_email' => 'someone-else@example.com' ) );

		$order = $this->order_generator
			->add_product( 'rp_sub' )
			->set_prop( 'customer_id', $owner_id )
			->set_prop( 'billing_email', 'someone-else@example.com' )
			->set_prop( 'payment_method', 'reepay_checkout' )
			->order();

		ApiResponseMock::respond_with( array( 'id' => 'sub-2', 'handle' => 'handle-2', 'state' => 'created' ) );

		$this->renewals->create_subscriptions(
			array(
				'customer'       => 'cust-email-2',
				'payment_method' => 'tok-email-2',
			),
			$order
		);

		$order = wc_get_order( $order->get_id() );
		$this->assertSame( $owner_id, $order->get_customer_id() );
		$this->assertEmpty( $order->get_meta( '_reepay_customer_linked_by_email' ) );
	}

	/**
	 * Test @see WC_Reepay_Renewals::create_subscriptions sends the order's VAT
	 * number in the customer PUT to Frisbii.
	 */
	public function test_create_subscriptions_sends_vat_number_in_customer_update() {
		PLUGINS_STATE::maybe_skip_test_by_product_type( 'rp_sub' );
		if ( ! PLUGINS_STATE::reepay_pay_activated() ) {
			$this->markTestSkipped( 'The customer update and rp_get_order_vat_number() require the Frisbii Pay (reepay_pay) plugin.' );
		}

		$order = $this->order_generator
			->add_product( 'rp_sub' )
			->set_prop( 'customer_id', self::factory()->user->create() )
			->set_prop( 'billing_country', 'DK' )
			->set_prop( 'payment_method', 'reepay_checkout' )
			->order();
		$order->update_meta_data( '_billing_eu_vat_number', 'DK12345678' );
		$order->save();

		$customer_update = null;
		$capture         = function ( $preempt, $args, $url ) use ( &$customer_update ) {
			if ( 'PUT' === ( $args['method'] ?? '' ) && false !== strpos( $url, 'customer/cust-vat-1' ) ) {
				$customer_update = json_decode( $args['body'], true );
			}

			return $preempt;
		};
		add_filter( 'pre_http_request', $capture, 6, 3 );

		ApiResponseMock::respond_with( array( 'id' => 'sub-3', 'handle' => 'handle-3', 'state' => 'created' ) );

		$this->renewals->create_subscriptions(
			array(
				'customer'       => 'cust-vat-1',
				'payment_method' => 'tok-vat-1',
			),
			$order
		);

		remove_filter( 'pre_http_request', $capture, 6 );

		$this->assertIsArray( $customer_update, 'Expected a customer PUT request.' );
		$this->assertSame( 'DK12345678', $customer_update['vat'] );
		$this->assertSame( 'DK', $customer_update['country'] );
	}

	/*
	 * ------------------------------------------------------------------
	 * change_user_role() - guards
	 * ------------------------------------------------------------------
	 */

	/**
	 * Build an order for a subscription handle whose product grants $role.
	 *
	 * @param int    $customer_id customer user ID.
	 * @param string $handle subscription handle.
	 * @param string $role role stored on the product.
	 *
	 * @return WC_Order
	 */
	private function build_role_order( int $customer_id, string $handle, string $role ): WC_Order {
		$order = $this->order_generator
			->add_product( 'rp_sub' )
			->set_prop( 'customer_id', $customer_id )
			->order();
		$order->update_meta_data( '_reepay_subscription_handle', $handle );
		$order->save_meta_data();

		$order_item = WC_Reepay_Renewals::is_order_contain_subscription( $order );
		update_post_meta( $order_item->get_product_id(), '_reepay_subscription_customer_role', $role );

		return $order;
	}

	/**
	 * Test @see WC_Reepay_Renewals::change_user_role refuses to change the
	 * role of a customer linked by (unverified) billing e-mail.
	 */
	public function test_change_user_role_skips_customer_linked_by_email() {
		PLUGINS_STATE::maybe_skip_test_by_product_type( 'rp_sub' );

		$customer_id = self::factory()->user->create( array( 'role' => 'customer' ) );
		$order       = $this->build_role_order( $customer_id, 'sub-guard-email', 'subscriber' );
		$order->update_meta_data( '_reepay_customer_linked_by_email', 'yes' );
		$order->save_meta_data();

		$result = WC_Reepay_Renewals::change_user_role(
			array( 'subscription' => 'sub-guard-email', 'event_type' => 'subscription_renewal' )
		);

		$this->assertWPError( $result );
		$this->assertSame( array( 'customer' ), get_userdata( $customer_id )->roles );
	}

	/**
	 * Test @see WC_Reepay_Renewals::change_user_role refuses a role that does
	 * not exist.
	 */
	public function test_change_user_role_rejects_unknown_role() {
		PLUGINS_STATE::maybe_skip_test_by_product_type( 'rp_sub' );

		$customer_id = self::factory()->user->create( array( 'role' => 'customer' ) );
		$this->build_role_order( $customer_id, 'sub-guard-unknown', 'no-such-role' );

		$result = WC_Reepay_Renewals::change_user_role(
			array( 'subscription' => 'sub-guard-unknown', 'event_type' => 'subscription_renewal' )
		);

		$this->assertWPError( $result );
		$this->assertSame( array( 'customer' ), get_userdata( $customer_id )->roles );
	}

	/**
	 * Test @see WC_Reepay_Renewals::change_user_role refuses a role that has
	 * 'manage_options' (e.g. a product saved before role validation existed).
	 */
	public function test_change_user_role_rejects_privileged_role() {
		PLUGINS_STATE::maybe_skip_test_by_product_type( 'rp_sub' );

		$customer_id = self::factory()->user->create( array( 'role' => 'customer' ) );
		$this->build_role_order( $customer_id, 'sub-guard-admin-role', 'administrator' );

		$result = WC_Reepay_Renewals::change_user_role(
			array( 'subscription' => 'sub-guard-admin-role', 'event_type' => 'subscription_renewal' )
		);

		$this->assertWPError( $result );
		$this->assertSame( array( 'customer' ), get_userdata( $customer_id )->roles );
	}

	/**
	 * Test @see WC_Reepay_Renewals::change_user_role never changes the role
	 * of an administrator customer.
	 */
	public function test_change_user_role_skips_administrator_customer() {
		PLUGINS_STATE::maybe_skip_test_by_product_type( 'rp_sub' );

		$customer_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->build_role_order( $customer_id, 'sub-guard-admin-user', 'subscriber' );

		$result = WC_Reepay_Renewals::change_user_role(
			array( 'subscription' => 'sub-guard-admin-user', 'event_type' => 'subscription_renewal' )
		);

		$this->assertWPError( $result );
		$this->assertSame( array( 'administrator' ), get_userdata( $customer_id )->roles );
	}

	/*
	 * ------------------------------------------------------------------
	 * create_order_copy() - reconciling to Frisbii's invoice amount
	 * ------------------------------------------------------------------
	 */

	/**
	 * Copy $source's first item into a new order, optionally with invoice data.
	 *
	 * @param WC_Order    $source source order.
	 * @param array|false $invoice_data Frisbii invoice data.
	 *
	 * @return WC_Order
	 */
	private function copy_order( WC_Order $source, $invoice_data = false ): WC_Order {
		$items = $source->get_items();

		return WC_Reepay_Renewals::create_order_copy(
			array(
				'status'      => 'processing',
				'customer_id' => $source->get_customer_id(),
			),
			$source,
			array( reset( $items ) ),
			true,
			$invoice_data
		);
	}

	/**
	 * Test @see WC_Reepay_Renewals::create_order_copy sets the total to
	 * Frisbii's invoice amount when the calculated total is off by exactly one
	 * minor unit.
	 */
	public function test_create_order_copy_reconciles_one_minor_unit_drift() {
		if ( ! PLUGINS_STATE::reepay_pay_activated() ) {
			$this->markTestSkipped( 'rp_make_initial_amount() requires the Frisbii Pay (reepay_pay) plugin.' );
		}

		$source     = $this->order_generator->add_product( 'simple' )->order();
		$calculated = (float) $this->copy_order( $source )->get_total();

		$authoritative = round( $calculated + 0.01, 2 );
		$copy          = $this->copy_order(
			$source,
			array( 'amount' => (int) round( $authoritative * 100 ) )
		);

		$this->assertEqualsWithDelta( $authoritative, (float) wc_get_order( $copy->get_id() )->get_total(), 0.0001 );
	}

	/**
	 * Test @see WC_Reepay_Renewals::create_order_copy prefers settled_amount
	 * over amount when reconciling.
	 */
	public function test_create_order_copy_reconcile_prefers_settled_amount() {
		if ( ! PLUGINS_STATE::reepay_pay_activated() ) {
			$this->markTestSkipped( 'rp_make_initial_amount() requires the Frisbii Pay (reepay_pay) plugin.' );
		}

		$source     = $this->order_generator->add_product( 'simple' )->order();
		$calculated = (float) $this->copy_order( $source )->get_total();

		$settled = round( $calculated - 0.01, 2 );
		$copy    = $this->copy_order(
			$source,
			array(
				'settled_amount' => (int) round( $settled * 100 ),
				'amount'         => (int) round( ( $calculated + 0.01 ) * 100 ),
			)
		);

		$this->assertEqualsWithDelta( $settled, (float) wc_get_order( $copy->get_id() )->get_total(), 0.0001 );
	}

	/**
	 * Test @see WC_Reepay_Renewals::create_order_copy leaves the calculated
	 * total alone when the difference to Frisbii's amount is more than one
	 * minor unit (something else is wrong and must not be masked).
	 */
	public function test_create_order_copy_does_not_reconcile_larger_drift() {
		if ( ! PLUGINS_STATE::reepay_pay_activated() ) {
			$this->markTestSkipped( 'rp_make_initial_amount() requires the Frisbii Pay (reepay_pay) plugin.' );
		}

		$source     = $this->order_generator->add_product( 'simple' )->order();
		$calculated = (float) $this->copy_order( $source )->get_total();

		$copy = $this->copy_order(
			$source,
			array( 'amount' => (int) round( ( $calculated + 0.05 ) * 100 ) )
		);

		$this->assertEqualsWithDelta( $calculated, (float) wc_get_order( $copy->get_id() )->get_total(), 0.0001 );
	}
}
