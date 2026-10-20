<?php
/**
 * Class ImportTest
 *
 * Covers WC_Reepay_Import (includes/import/WC_Reepay_Import.php).
 *
 * Phase 5 scope: baseline regression coverage per requirements.md §3.4, not
 * exhaustive scenario enumeration.
 *
 * CONFIRMED by reading the full source:
 * - get_reepay_customers()/get_reepay_subscriptions() both loop
 *   `while ( ! empty( $data['next_page_token'] ) )`, re-requesting the API
 *   each iteration. ApiResponseMock serves the SAME body every call, so any
 *   mocked response with a truthy 'next_page_token' causes an infinite loop.
 *   Every test below mocking these two methods omits 'next_page_token' (or
 *   sets it falsy) so the loop runs exactly once - confirmed safe.
 * - Both catch Exception from the API call and return a WP_Error (not false,
 *   not null).
 * - get_reepay_customers()/get_reepay_subscriptions()/get_reepay_cards() all
 *   call rp_get_user_id_by_handle()/rp_get_customer_handle() unconditionally
 *   - these functions are defined ONLY by the Frisbii Pay plugin
 *   (reepay-woocommerce-payment/includes/Functions/customer.php). Tests
 *   touching these paths are guarded with PLUGINS_STATE::reepay_pay_activated()
 *   and markTestSkipped() under woo-only.
 * - import_customers()/import_cards()/import_subscriptions() are thin
 *   dispatcher loops over WC_Reepay_Import_Helpers::import_*() - the
 *   "selected handle not present in source array -> skipped" branch needs no
 *   API mocking at all and is plugin-combo-independent.
 *
 * @package Reepay\Subscriptions
 */

use Reepay\Subscriptions\Tests\Helpers\PLUGINS_STATE;
use Reepay\Subscriptions\Tests\Helpers\Reepay_Subscriptions_UnitTestCase;
use Reepay\Subscriptions\Tests\Mocks\ApiResponseMock;

/**
 * ImportTest.
 */
class ImportTest extends Reepay_Subscriptions_UnitTestCase {

	public function set_up() {
		parent::set_up();

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
	}

	public function tear_down() {
		ApiResponseMock::clear();

		parent::tear_down();
	}

	/**
	 * Set a private/protected property on an already-constructed object via
	 * reflection. Pattern copied verbatim from tests/unit/renewals/LifecycleTest.php.
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

	/*
	 * ------------------------------------------------------------------
	 * init()
	 * ------------------------------------------------------------------
	 */

	/**
	 * Test @see WC_Reepay_Import::init populates the static $import_objects
	 * array with the three expected object types, each with a 'label' and
	 * 'options' key. Idempotent - safe to call more than once.
	 */
	public function test_init_populates_import_objects() {
		$import = new WC_Reepay_Import();
		$import->init();

		$this->assertArrayHasKey( 'customers', WC_Reepay_Import::$import_objects );
		$this->assertArrayHasKey( 'cards', WC_Reepay_Import::$import_objects );
		$this->assertArrayHasKey( 'subscriptions', WC_Reepay_Import::$import_objects );
		$this->assertArrayHasKey( 'options', WC_Reepay_Import::$import_objects['customers'] );
	}

	/*
	 * ------------------------------------------------------------------
	 * get_reepay_customers() - pagination-loop infinite-loop risk (gotcha #2)
	 * ------------------------------------------------------------------
	 */

	/**
	 * Test @see WC_Reepay_Import::get_reepay_customers returns a WP_Error
	 * (not false/null) when the API call throws.
	 */
	public function test_get_reepay_customers_returns_wp_error_on_exception() {
		ApiResponseMock::respond_with( array( 'error' => 'invalid_request', 'message' => 'boom' ), 400 );

		$result = WC_Reepay_Import::get_reepay_customers();

		$this->assertInstanceOf( WP_Error::class, $result );
	}

	/**
	 * Test @see WC_Reepay_Import::get_reepay_customers happy path: a single
	 * page (no 'next_page_token' key - loop runs exactly once, see gotcha #2)
	 * with one not-yet-imported customer is returned keyed by handle.
	 */
	public function test_get_reepay_customers_single_page_returns_new_customer() {
		if ( ! PLUGINS_STATE::reepay_pay_activated() ) {
			$this->markTestSkipped( 'rp_get_user_id_by_handle() requires the Frisbii Pay (reepay_pay) plugin.' );
		}

		ApiResponseMock::respond_with(
			array(
				'content' => array(
					array(
						'handle'               => 'cust-new-1',
						'email'                => 'new1@example.com',
						'active_subscriptions' => 1,
					),
				),
				// Deliberately absent: 'next_page_token' - loop runs once only.
			)
		);

		$result = WC_Reepay_Import::get_reepay_customers();

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'cust-new-1', $result );
	}

	/*
	 * ------------------------------------------------------------------
	 * get_reepay_subscriptions() - same pagination-loop risk
	 * ------------------------------------------------------------------
	 */

	/**
	 * Test @see WC_Reepay_Import::get_reepay_subscriptions returns a WP_Error
	 * when the API call throws.
	 */
	public function test_get_reepay_subscriptions_returns_wp_error_on_exception() {
		ApiResponseMock::respond_with( array( 'error' => 'invalid_request', 'message' => 'boom' ), 400 );

		$result = WC_Reepay_Import::get_reepay_subscriptions();

		$this->assertInstanceOf( WP_Error::class, $result );
	}

	/**
	 * Test @see WC_Reepay_Import::get_reepay_subscriptions happy path with
	 * statuses=['all'] and a single-page response (no 'next_page_token').
	 */
	public function test_get_reepay_subscriptions_single_page_returns_subscription() {
		if ( ! PLUGINS_STATE::reepay_pay_activated() ) {
			$this->markTestSkipped( 'rp_get_user_id_by_handle() requires the Frisbii Pay (reepay_pay) plugin.' );
		}

		ApiResponseMock::respond_with(
			array(
				'content' => array(
					array(
						'handle'           => 'sub-new-1',
						'customer'         => 'cust-1',
						'state'            => 'active',
						'dunning_invoices' => 0,
						'is_cancelled'     => false,
					),
				),
			)
		);

		$result = WC_Reepay_Import::get_reepay_subscriptions( array( 'all' ) );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'sub-new-1', $result );
	}

	/**
	 * Test @see WC_Reepay_Import::get_reepay_subscriptions excludes a
	 * subscription whose handle already exists as a WooCommerce order (no
	 * API-exposed cross-plugin dependency on this specific branch - the
	 * exists-check short-circuits before rp_get_user_id_by_handle() is
	 * reached for that row).
	 */
	public function test_get_reepay_subscriptions_skips_already_existing_handle() {
		$order = $this->order_generator->add_product( 'simple' )->order();
		$order->update_meta_data( '_reepay_subscription_handle', 'sub-exists-1' );
		$order->save_meta_data();

		ApiResponseMock::respond_with(
			array(
				'content' => array(
					array(
						'handle'           => 'sub-exists-1',
						'customer'         => 'cust-1',
						'state'            => 'active',
						'dunning_invoices' => 0,
						'is_cancelled'     => false,
					),
				),
			)
		);

		$result = WC_Reepay_Import::get_reepay_subscriptions( array( 'all' ) );

		$this->assertIsArray( $result );
		$this->assertArrayNotHasKey( 'sub-exists-1', $result );
	}

	/*
	 * ------------------------------------------------------------------
	 * get_reepay_cards()
	 * ------------------------------------------------------------------
	 */

	/**
	 * Test @see WC_Reepay_Import::get_reepay_cards returns an empty array
	 * when there are no WordPress users to iterate (cheap, no API mocking
	 * needed - get_users() with a brand-new test DB still returns at least
	 * the default admin user from WP's own test fixtures, so this asserts
	 * the method completes and returns an array rather than asserting zero
	 * users specifically).
	 */
	public function test_get_reepay_cards_returns_array_without_fatal() {
		if ( ! PLUGINS_STATE::reepay_pay_activated() ) {
			$this->markTestSkipped( 'rp_get_customer_handle() requires the Frisbii Pay (reepay_pay) plugin.' );
		}

		// No mocked response registered: the per-user API call will throw,
		// which get_reepay_cards() catches internally via `continue` - so no
		// ApiResponseMock registration is needed for this branch.
		$result = WC_Reepay_Import::get_reepay_cards();

		$this->assertIsArray( $result );
	}

	/*
	 * ------------------------------------------------------------------
	 * import_customers() / import_cards() / import_subscriptions() -
	 * dispatcher "skip" branches (cheap, no API mocking needed)
	 * ------------------------------------------------------------------
	 */

	/**
	 * Test @see WC_Reepay_Import::import_customers skips (no entry in result)
	 * a selected handle that isn't present in the $customers source array.
	 */
	public function test_import_customers_skips_handle_not_in_source_array() {
		$result = WC_Reepay_Import::import_customers( array(), array( 'missing-handle' ) );

		$this->assertArrayNotHasKey( 'missing-handle', $result );
	}

	/**
	 * Test @see WC_Reepay_Import::import_customers happy path for a customer
	 * present in the source array, with no WP user matching that email -
	 * import_reepay_customer() creates a brand-new user.
	 *
	 * import_reepay_customer() calls the deprecated get_user_by_email()
	 * (confirmed by actually running this test) - setExpectedDeprecated()
	 * documents this real, pre-existing production behavior.
	 */
	public function test_import_customers_happy_path_creates_user() {
		$this->setExpectedDeprecated( 'get_user_by_email' );

		add_filter( 'wp_mail', '__return_false' );

		$customers = array(
			'cust-import-1' => array(
				'handle' => 'cust-import-1',
				'email'  => 'import-happy-1@example.com',
			),
		);

		$result = WC_Reepay_Import::import_customers( $customers, array( 'cust-import-1' ) );

		$this->assertTrue( $result['cust-import-1'] );
		$this->assertNotFalse( get_user_by( 'email', 'import-happy-1@example.com' ) );
	}

	/**
	 * Test @see WC_Reepay_Import::import_cards skips a selected card id not
	 * present in the $cards source array.
	 */
	public function test_import_cards_skips_id_not_in_source_array() {
		$result = WC_Reepay_Import::import_cards( array(), array( 'missing-card-id' ) );

		$this->assertArrayNotHasKey( 'missing-card-id', $result );
	}

	/**
	 * Test @see WC_Reepay_Import::import_cards reports "User not found" for a
	 * card whose owning customer handle has no matching WP user.
	 */
	public function test_import_cards_reports_user_not_found() {
		if ( ! PLUGINS_STATE::reepay_pay_activated() ) {
			$this->markTestSkipped( 'rp_get_user_id_by_handle() requires the Frisbii Pay (reepay_pay) plugin.' );
		}

		$cards = array(
			'card-no-user-1' => array(
				'id'       => 'card-no-user-1',
				'customer' => 'cust-does-not-exist',
			),
		);

		$result = WC_Reepay_Import::import_cards( $cards, array( 'card-no-user-1' ) );

		$this->assertSame( 'User not found', $result['card-no-user-1'] );
	}

	/**
	 * Test @see WC_Reepay_Import::import_subscriptions skips a selected
	 * handle not present in the $subscriptions source array.
	 */
	public function test_import_subscriptions_skips_handle_not_in_source_array() {
		$result = WC_Reepay_Import::import_subscriptions( array(), array( 'missing-sub-handle' ) );

		$this->assertArrayNotHasKey( 'missing-sub-handle', $result );
	}

	/**
	 * Test @see WC_Reepay_Import::import_subscriptions skips a handle that
	 * already exists as a WooCommerce order - confirms the exists-check
	 * short-circuits before any import attempt (no API mocking needed since
	 * import_reepay_subscription() must never be reached).
	 */
	public function test_import_subscriptions_skips_already_existing_handle() {
		$order = $this->order_generator->add_product( 'simple' )->order();
		$order->update_meta_data( '_reepay_subscription_handle', 'sub-dispatch-exists-1' );
		$order->save_meta_data();

		$subscriptions = array(
			'sub-dispatch-exists-1' => array( 'handle' => 'sub-dispatch-exists-1' ),
		);

		$result = WC_Reepay_Import::import_subscriptions( $subscriptions, array( 'sub-dispatch-exists-1' ) );

		$this->assertArrayNotHasKey( 'sub-dispatch-exists-1', $result );
	}
}
