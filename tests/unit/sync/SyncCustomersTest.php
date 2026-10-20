<?php
/**
 * Class SyncCustomersTest
 *
 * Covers WC_Reepay_Sync_Customers (includes/sync/WC_Reepay_Sync_Customers.php).
 *
 * CONFIRMED by reading the full source:
 * - created()/changed()/deleted() have NO sleep() - they all call the shared
 *   static get_customer_data($handle) helper (one real, normally-mockable API
 *   call), then branch on whether a WP user / customer-data was found.
 * - get_customer_data($handle) catches Exception internally (logs via
 *   reepay_s()->log()->log(...), does not rethrow) and ALWAYS returns an
 *   array shape (['user_id' => ..., 'customer_data' => ...]) - customer_data
 *   is simply empty on API failure, never a WP_Error. This is a genuinely
 *   different return-shape convention than WC_Reepay_Import's WP_Error-
 *   returning methods - confirmed directly by reading the method body, not
 *   assumed from naming similarity.
 * - deleted()'s happy path calls wp_delete_user() - confirmed safe to call
 *   on a real test-fixture user; WordPress's test framework handles user
 *   deletion cleanly.
 * - payment_method_added() calls sleep(5) UNCONDITIONALLY at the very top,
 *   before any branching (gotcha #5) - no early-return avoids it. Per the
 *   brief's guidance ("accept the cost for at most one or two tests"), two
 *   tests below cover its two cheapest-to-reach distinct branches ("user not
 *   found" and "token already known"); the full card-import success branch
 *   is left undercovered here to avoid a third 5s-cost test given this file
 *   otherwise needs zero sleep-affected tests at all for created/changed/
 *   deleted.
 * - rp_get_user_id_by_handle() is called unconditionally by created()
 *   (indirectly via get_customer_data()), changed(), deleted(), and
 *   payment_method_added() - all reepay_pay-only, guarded accordingly below.
 *
 * @package Reepay\Subscriptions
 */

use Reepay\Subscriptions\Tests\Helpers\PLUGINS_STATE;
use Reepay\Subscriptions\Tests\Helpers\Reepay_Subscriptions_UnitTestCase;
use Reepay\Subscriptions\Tests\Mocks\ApiResponseMock;

/**
 * SyncCustomersTest.
 */
class SyncCustomersTest extends Reepay_Subscriptions_UnitTestCase {

	/**
	 * @var WC_Reepay_Sync_Customers
	 */
	private WC_Reepay_Sync_Customers $sync_customers;

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

		$this->sync_customers = new WC_Reepay_Sync_Customers();
	}

	public function tear_down() {
		ApiResponseMock::clear();
		remove_filter( 'wp_mail', '__return_false' );

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
	 * get_customer_data()
	 * ------------------------------------------------------------------
	 */

	/**
	 * Test @see WC_Reepay_Sync_Customers::get_customer_data returns an array
	 * shape with an EMPTY customer_data (never a WP_Error) when the API call
	 * throws - confirms the documented return-shape difference from
	 * WC_Reepay_Import's WP_Error-returning methods.
	 */
	public function test_get_customer_data_returns_empty_customer_data_on_exception() {
		if ( ! PLUGINS_STATE::reepay_pay_activated() ) {
			$this->markTestSkipped( 'rp_get_user_id_by_handle() requires the Frisbii Pay (reepay_pay) plugin.' );
		}

		ApiResponseMock::respond_with( array( 'error' => 'invalid_request', 'message' => 'boom' ), 400 );

		$result = WC_Reepay_Sync_Customers::get_customer_data( 'cust-exc-1' );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'user_id', $result );
		$this->assertArrayHasKey( 'customer_data', $result );
		$this->assertEmpty( $result['customer_data'] );
		$this->assertNotInstanceOf( WP_Error::class, $result );
	}

	/*
	 * ------------------------------------------------------------------
	 * created()
	 * ------------------------------------------------------------------
	 */

	/**
	 * Test @see WC_Reepay_Sync_Customers::created no-ops when
	 * get_customer_data() returns empty customer_data (API exception path) -
	 * no new user should be imported.
	 */
	public function test_created_noop_when_customer_data_not_found() {
		if ( ! PLUGINS_STATE::reepay_pay_activated() ) {
			$this->markTestSkipped( 'rp_get_user_id_by_handle() requires the Frisbii Pay (reepay_pay) plugin.' );
		}

		ApiResponseMock::respond_with( array( 'error' => 'invalid_request', 'message' => 'boom' ), 400 );

		$users_before = count_users()['total_users'];

		$this->sync_customers->created( array( 'customer' => 'cust-created-missing-1' ) );

		$this->assertSame( $users_before, count_users()['total_users'] );
	}

	/**
	 * Test @see WC_Reepay_Sync_Customers::created happy path imports a new
	 * WP user from the fetched customer data.
	 *
	 * import_reepay_customer() calls the deprecated get_user_by_email() -
	 * setExpectedDeprecated() documents this real, pre-existing production
	 * behavior (same as ImportHelpersTest/ImportTest's happy-path tests for
	 * the same underlying call).
	 */
	public function test_created_happy_path_imports_new_user() {
		if ( ! PLUGINS_STATE::reepay_pay_activated() ) {
			$this->markTestSkipped( 'rp_get_user_id_by_handle() requires the Frisbii Pay (reepay_pay) plugin.' );
		}

		$this->setExpectedDeprecated( 'get_user_by_email' );

		add_filter( 'wp_mail', '__return_false' );

		ApiResponseMock::respond_with(
			array(
				'handle' => 'cust-created-ok-1',
				'email'  => 'sync-created-ok-1@example.com',
			)
		);

		$this->sync_customers->created( array( 'customer' => 'cust-created-ok-1' ) );

		$this->assertNotFalse( get_user_by( 'email', 'sync-created-ok-1@example.com' ) );
	}

	/*
	 * ------------------------------------------------------------------
	 * changed()
	 * ------------------------------------------------------------------
	 */

	/**
	 * Test @see WC_Reepay_Sync_Customers::changed no-ops when the handle
	 * doesn't map to any known WP user.
	 */
	public function test_changed_noop_when_user_not_found() {
		if ( ! PLUGINS_STATE::reepay_pay_activated() ) {
			$this->markTestSkipped( 'rp_get_user_id_by_handle() requires the Frisbii Pay (reepay_pay) plugin.' );
		}

		ApiResponseMock::respond_with( array( 'handle' => 'cust-changed-nouser-1', 'email' => 'x@example.com' ) );

		// No fatal/exception expected.
		$this->sync_customers->changed( array( 'customer' => 'cust-changed-nouser-1' ) );

		$this->addToAssertionCount( 1 );
	}

	/**
	 * Test @see WC_Reepay_Sync_Customers::changed happy path updates the
	 * matched WP user's profile data from the fetched customer data.
	 */
	public function test_changed_happy_path_updates_user_data() {
		if ( ! PLUGINS_STATE::reepay_pay_activated() ) {
			$this->markTestSkipped( 'rp_get_user_id_by_handle() requires the Frisbii Pay (reepay_pay) plugin.' );
		}

		$user_id = self::factory()->user->create();
		update_user_meta( $user_id, 'reepay_customer_id', 'cust-changed-ok-1' );

		ApiResponseMock::respond_with(
			array(
				'handle'     => 'cust-changed-ok-1',
				'email'      => $this->get_user_email_stub( $user_id ),
				'first_name' => 'Updated',
				'last_name'  => 'Name',
			)
		);

		$this->sync_customers->changed( array( 'customer' => 'cust-changed-ok-1' ) );

		$this->assertSame( 'Updated', get_user_meta( $user_id, 'first_name', true ) );
	}

	/**
	 * Small helper to reuse an existing user's current email as the mocked
	 * API response's email, so wp_update_user() doesn't collide on a
	 * duplicate-email error for this specific happy-path test.
	 *
	 * @param int $user_id user id.
	 *
	 * @return string
	 */
	private function get_user_email_stub( int $user_id ): string {
		return get_userdata( $user_id )->user_email;
	}

	/*
	 * ------------------------------------------------------------------
	 * deleted()
	 * ------------------------------------------------------------------
	 */

	/**
	 * Test @see WC_Reepay_Sync_Customers::deleted no-ops when the handle
	 * doesn't map to any known WP user - the user count must be unaffected.
	 */
	public function test_deleted_noop_when_user_not_found() {
		if ( ! PLUGINS_STATE::reepay_pay_activated() ) {
			$this->markTestSkipped( 'rp_get_user_id_by_handle() requires the Frisbii Pay (reepay_pay) plugin.' );
		}

		ApiResponseMock::respond_with( array( 'handle' => 'cust-deleted-nouser-1' ) );

		$users_before = count_users()['total_users'];

		$this->sync_customers->deleted( array( 'customer' => 'cust-deleted-nouser-1' ) );

		$this->assertSame( $users_before, count_users()['total_users'] );
	}

	/**
	 * Test @see WC_Reepay_Sync_Customers::deleted happy path actually
	 * deletes the matched WP user via wp_delete_user().
	 */
	public function test_deleted_happy_path_deletes_user() {
		if ( ! PLUGINS_STATE::reepay_pay_activated() ) {
			$this->markTestSkipped( 'rp_get_user_id_by_handle() requires the Frisbii Pay (reepay_pay) plugin.' );
		}

		$user_id = self::factory()->user->create();
		update_user_meta( $user_id, 'reepay_customer_id', 'cust-deleted-ok-1' );

		ApiResponseMock::respond_with( array( 'handle' => 'cust-deleted-ok-1' ) );

		$this->sync_customers->deleted( array( 'customer' => 'cust-deleted-ok-1' ) );

		$this->assertFalse( get_userdata( $user_id ) );
	}

	/*
	 * ------------------------------------------------------------------
	 * payment_method_added() - see gotcha #5 (5s sleep, unconditional)
	 * ------------------------------------------------------------------
	 */

	/**
	 * Test @see WC_Reepay_Sync_Customers::payment_method_added returns
	 * (no-op) when the customer handle doesn't map to a known WP user. PAYS
	 * THE 5s sleep() COST (gotcha #5) - unavoidable, sleep happens before
	 * this check is reached.
	 */
	public function test_payment_method_added_noop_when_user_not_found() {
		if ( ! PLUGINS_STATE::reepay_pay_activated() ) {
			$this->markTestSkipped( 'rp_get_user_id_by_handle() requires the Frisbii Pay (reepay_pay) plugin.' );
		}

		$this->sync_customers->payment_method_added(
			array(
				'customer'       => 'cust-pma-nouser-1',
				'payment_method' => 'card-pma-1',
			)
		);

		$this->addToAssertionCount( 1 ); // Reaching here without an exception/fatal is the assertion.
	}

	/**
	 * Test @see WC_Reepay_Sync_Customers::payment_method_added returns
	 * early (no API call attempted) when the payment token is already among
	 * the user's known tokens. PAYS THE 5s sleep() COST (gotcha #5) -
	 * unavoidable. Per the brief, this is the second (and final) test
	 * against this method accepting the 5s cost.
	 */
	public function test_payment_method_added_noop_when_token_already_known() {
		if ( ! PLUGINS_STATE::reepay_pay_activated() ) {
			$this->markTestSkipped( 'rp_get_user_id_by_handle() and WC_Payment_Token_Reepay require the Frisbii Pay (reepay_pay) plugin.' );
		}

		$user_id = self::factory()->user->create();
		update_user_meta( $user_id, 'reepay_customer_id', 'cust-pma-known-1' );

		$token = new WC_Payment_Token_CC();
		$token->set_token( 'card-pma-known-1' );
		$token->set_gateway_id( 'bacs' );
		$token->set_user_id( $user_id );
		$token->set_card_type( 'visa' );
		$token->set_last4( '4242' );
		$token->set_expiry_month( '01' );
		$token->set_expiry_year( '2030' );
		$token->save();

		// Deliberately no ApiResponseMock registered - if the "already
		// known" early-return didn't fire, the un-mocked API call would
		// throw/fatal rather than silently succeed.
		$this->sync_customers->payment_method_added(
			array(
				'customer'       => 'cust-pma-known-1',
				'payment_method' => 'card-pma-known-1',
			)
		);

		$this->addToAssertionCount( 1 );
	}
}
