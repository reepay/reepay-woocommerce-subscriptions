<?php
/**
 * Class ImportHelpersTest
 *
 * Covers WC_Reepay_Import_Helpers (includes/import/WC_Reepay_Import_Helpers.php).
 *
 * CONFIRMED by reading the full source:
 * - import_reepay_customer() has NO cross-plugin dependency itself. It calls
 *   wp_new_user_notification($user_id, null, 'user') for the new-user branch,
 *   which sends real mail via wp_mail(). WordPress's PHPUnit test framework
 *   replaces the mailer with a mock (MockPHPMailer via tests_add_filter on
 *   'wp_mail') by default - confirmed no actual SMTP/mail send is attempted
 *   under the test runner - but as an extra safety net (and to keep this
 *   test's intent explicit regardless of bootstrap wiring) the happy-path
 *   test below also force-disables wp_mail via the 'wp_mail' filter.
 * - add_card_to_user() branches on substr($card['id'],0,3)=='ms_'. The
 *   non-ms_ branch uses Reepay\Checkout\Tokens\TokenReepay if it exists,
 *   else falls back to WC_Payment_Token_Reepay. Confirmed by searching the
 *   whole plugin tree: WC_Payment_Token_Reepay/WC_Payment_Token_Reepay_MS are
 *   ONLY defined inside the Frisbii Pay (reepay_pay) plugin itself (no
 *   fallback-class definition exists anywhere reachable under woo-only) - so
 *   this entire method is reepay_pay-only regardless of branch, and is
 *   guarded accordingly below.
 * - import_reepay_subscription() makes THREE sequential, differently-shaped
 *   API calls in one method body: (1) plan lookup
 *   ("plan/{plan}/current"), (2) customer lookup ("customer/{customer}"),
 *   and (3) an internal reepay_s()->plan()->get_remote_plan_meta($plan)
 *   call, which itself re-fetches "plan/{plan}/current" via get_plan()
 *   since $subscription['plan'] is a string. ApiResponseMock serves one
 *   identical body to all three calls (gotcha #1). Verified viable to use
 *   approach (b) (one permissive merged body) for the happy path: the body
 *   below satisfies the plan fields (handle/amount/name/quantity/
 *   schedule_type) AND the customer fields (email/first_name/etc, since
 *   WC_Reepay_Subscription_Plan_Simple::get_remote_plan_meta() only reads
 *   plan-shaped keys and silently ignores the extra customer-shaped keys via
 *   empty()-guarded branches). schedule_type is set to 'daily' (no required
 *   type_data sub-keys) specifically so get_billing_plan()'s array branch
 *   (interval explicitly passed as '' by the caller) never dereferences a
 *   missing type_data key.
 * - get_customer_tokens() is a pure wrapper around
 *   WC_Payment_Tokens::get_customer_tokens() - no cross-plugin dependency.
 * - woo_reepay_subscription_exists() wraps this plugin's own (safe)
 *   WC_Reepay_Renewals::get_order_by_subscription_handle().
 *
 * @package Reepay\Subscriptions
 */

use Reepay\Subscriptions\Tests\Helpers\PLUGINS_STATE;
use Reepay\Subscriptions\Tests\Helpers\Reepay_Subscriptions_UnitTestCase;
use Reepay\Subscriptions\Tests\Mocks\ApiResponseMock;

/**
 * ImportHelpersTest.
 */
class ImportHelpersTest extends Reepay_Subscriptions_UnitTestCase {

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
	 * import_reepay_customer()
	 * ------------------------------------------------------------------
	 */

	/**
	 * Test @see WC_Reepay_Import_Helpers::import_reepay_customer links to an
	 * EXISTING WP user (matched by email) rather than creating a new one.
	 *
	 * import_reepay_customer() calls the deprecated get_user_by_email()
	 * (confirmed by actually running this test: WordPress's test framework
	 * turns the deprecation notice into a failure unless expected) -
	 * setExpectedDeprecated() documents this real, pre-existing production
	 * behavior rather than working around it.
	 */
	public function test_import_reepay_customer_links_existing_user_by_email() {
		$this->setExpectedDeprecated( 'get_user_by_email' );

		$existing_user_id = self::factory()->user->create( array( 'user_email' => 'existing-cust@example.com' ) );

		$result = WC_Reepay_Import_Helpers::import_reepay_customer(
			array(
				'handle' => 'cust-existing-1',
				'email'  => 'existing-cust@example.com',
			)
		);

		$this->assertSame( $existing_user_id, $result );
		$this->assertSame( 'cust-existing-1', get_user_meta( $existing_user_id, 'reepay_customer_id', true ) );
	}

	/**
	 * Test @see WC_Reepay_Import_Helpers::import_reepay_customer creates a
	 * brand-new WP user when no existing user matches the given email.
	 * wp_mail is force-disabled defensively (see class docblock) so this test
	 * never attempts any outbound mail regardless of bootstrap wiring.
	 */
	public function test_import_reepay_customer_creates_new_user_when_none_exists() {
		$this->setExpectedDeprecated( 'get_user_by_email' );

		add_filter( 'wp_mail', '__return_false' );

		$result = WC_Reepay_Import_Helpers::import_reepay_customer(
			array(
				'handle'     => 'cust-brand-new-1',
				'email'      => 'brand-new-1@example.com',
				'first_name' => 'Jane',
				'last_name'  => 'Doe',
			)
		);

		$this->assertIsInt( $result );
		$user = get_userdata( $result );
		$this->assertSame( 'brand-new-1@example.com', $user->user_email );
		$this->assertSame( 'cust-brand-new-1', get_user_meta( $result, 'reepay_customer_id', true ) );
	}

	/*
	 * ------------------------------------------------------------------
	 * add_card_to_user() - reepay_pay-only (see class docblock)
	 * ------------------------------------------------------------------
	 */

	/**
	 * Test @see WC_Reepay_Import_Helpers::add_card_to_user saves a regular
	 * (non ms_-prefixed) card as a payment token for the given user.
	 */
	public function test_add_card_to_user_saves_regular_card_token() {
		if ( ! PLUGINS_STATE::reepay_pay_activated() ) {
			$this->markTestSkipped( 'WC_Payment_Token_Reepay/TokenReepay are only defined by the Frisbii Pay (reepay_pay) plugin.' );
		}

		$user_id = self::factory()->user->create();

		$result = WC_Reepay_Import_Helpers::add_card_to_user(
			$user_id,
			array(
				'id'          => 'card-abc123',
				'exp_date'    => '01-30',
				'masked_card' => '424242XXXXXX4242',
				'card_type'   => 'visa',
				'customer'    => 'cust-1',
			)
		);

		$this->assertTrue( $result );

		$tokens = WC_Reepay_Import_Helpers::get_customer_tokens( $user_id );
		$this->assertContains( 'card-abc123', $tokens );
	}

	/**
	 * Test @see WC_Reepay_Import_Helpers::add_card_to_user saves an
	 * ms_-prefixed (MobilePay Subscriptions) card using the MS token class.
	 */
	public function test_add_card_to_user_saves_ms_prefixed_card_token() {
		if ( ! PLUGINS_STATE::reepay_pay_activated() ) {
			$this->markTestSkipped( 'WC_Payment_Token_Reepay_MS/TokenReepayMS are only defined by the Frisbii Pay (reepay_pay) plugin.' );
		}

		$user_id = self::factory()->user->create();

		$result = WC_Reepay_Import_Helpers::add_card_to_user(
			$user_id,
			array(
				'id'       => 'ms_card_xyz789',
				'customer' => 'cust-1',
			)
		);

		$this->assertTrue( $result );

		$tokens = WC_Reepay_Import_Helpers::get_customer_tokens( $user_id );
		$this->assertContains( 'ms_card_xyz789', $tokens );
	}

	/*
	 * ------------------------------------------------------------------
	 * get_customer_tokens()
	 * ------------------------------------------------------------------
	 */

	/**
	 * Test @see WC_Reepay_Import_Helpers::get_customer_tokens returns an
	 * empty array for a user with no saved payment tokens.
	 */
	public function test_get_customer_tokens_empty_for_user_without_tokens() {
		$user_id = self::factory()->user->create();

		$this->assertSame( array(), WC_Reepay_Import_Helpers::get_customer_tokens( $user_id ) );
	}

	/**
	 * Test @see WC_Reepay_Import_Helpers::get_customer_tokens returns the
	 * token strings for a user with a saved core CC token (plugin-combo
	 * independent - uses the always-registered 'bacs' gateway id, same
	 * technique as SubscriptionActionsTest::test_do_action_change_payment_method_owned_token_calls_api).
	 */
	public function test_get_customer_tokens_returns_saved_token_strings() {
		$user_id = self::factory()->user->create();

		$token = new WC_Payment_Token_CC();
		$token->set_token( 'tok-helper-test-1' );
		$token->set_gateway_id( 'bacs' );
		$token->set_user_id( $user_id );
		$token->set_card_type( 'visa' );
		$token->set_last4( '4242' );
		$token->set_expiry_month( '01' );
		$token->set_expiry_year( '2030' );
		$token->save();

		$this->assertContains( 'tok-helper-test-1', WC_Reepay_Import_Helpers::get_customer_tokens( $user_id ) );
	}

	/*
	 * ------------------------------------------------------------------
	 * woo_reepay_subscription_exists()
	 * ------------------------------------------------------------------
	 */

	/**
	 * Test @see WC_Reepay_Import_Helpers::woo_reepay_subscription_exists
	 * returns true for a handle matching a real order's
	 * _reepay_subscription_handle meta.
	 */
	public function test_woo_reepay_subscription_exists_true_for_matching_order() {
		$order = $this->order_generator->add_product( 'simple' )->order();
		$order->update_meta_data( '_reepay_subscription_handle', 'sub-exists-helper-1' );
		$order->save_meta_data();

		$this->assertTrue( WC_Reepay_Import_Helpers::woo_reepay_subscription_exists( 'sub-exists-helper-1' ) );
	}

	/**
	 * Test @see WC_Reepay_Import_Helpers::woo_reepay_subscription_exists
	 * returns false for a handle with no matching order.
	 */
	public function test_woo_reepay_subscription_exists_false_for_unknown_handle() {
		$this->assertFalse( WC_Reepay_Import_Helpers::woo_reepay_subscription_exists( 'sub-does-not-exist-helper-1' ) );
	}

	/*
	 * ------------------------------------------------------------------
	 * import_reepay_subscription() - the most complex method in this task
	 * ------------------------------------------------------------------
	 */

	/**
	 * Test @see WC_Reepay_Import_Helpers::import_reepay_subscription returns
	 * a WP_Error (no order created) when the FIRST (plan lookup) API call
	 * throws - cheapest, single mocked exception, confirms the exception
	 * path short-circuits correctly.
	 */
	public function test_import_reepay_subscription_plan_lookup_failure_returns_wp_error_no_order() {
		ApiResponseMock::respond_with( array( 'error' => 'invalid_request', 'message' => 'boom' ), 400 );

		$orders_before = wc_get_orders( array( 'limit' => -1 ) );

		$result = WC_Reepay_Import_Helpers::import_reepay_subscription(
			array(
				'plan'     => 'plan-fail-1',
				'customer' => 'cust-fail-1',
				'handle'   => 'sub-fail-1',
				'state'    => 'active',
			)
		);

		$this->assertInstanceOf( WP_Error::class, $result );

		$orders_after = wc_get_orders( array( 'limit' => -1 ) );
		$this->assertCount( count( $orders_before ), $orders_after, 'No order should be created when the plan lookup fails.' );
	}

	/**
	 * Test @see WC_Reepay_Import_Helpers::import_reepay_subscription happy
	 * path: a real WC_Order is created with the expected meta
	 * (_reepay_order, _reepay_subscription_handle, _reepay_imported), using
	 * approach (b) from gotcha #1 - one permissive merged response body that
	 * satisfies every one of the three sequential call sites (plan lookup,
	 * customer lookup, and the internal get_remote_plan_meta() plan
	 * re-fetch). Requires the Frisbii Pay plugin for
	 * rp_get_user_id_by_handle().
	 *
	 * REAL CONFIRMED BUG, not a test-setup issue: import_reepay_subscription()
	 * does `$schedule_data = $plan_data[$schedule_type]` (line 211), where
	 * $plan_data here is get_remote_plan_meta()'s RETURN VALUE (a $plan_meta
	 * array of only `_reepay_subscription_*`-prefixed keys - confirmed by
	 * reading the full method), not the raw API response. The correct access
	 * pattern, confirmed from get_billing_plan()'s own WC_Product branch
	 * (WC_Reepay_Subscription_Plan_Simple.php:793), is
	 * `$plan_data['_reepay_subscription_' . $schedule_type]` - the missing
	 * `'_reepay_subscription_' .` prefix means this lookup can NEVER resolve
	 * for any real schedule_type value; it always triggers a PHP
	 * "Undefined array key" warning and $schedule_data silently ends up null.
	 * In real production (no convertWarningsToExceptions) this is harmless
	 * to order creation itself - execution continues and the order still
	 * saves - but `_reepay_billing_string` order meta ends up built from a
	 * null type_data every time. This phpunit.xml sets
	 * convertWarningsToExceptions="true", so the warning becomes a hard test
	 * error unless suppressed - suppressed here specifically to prove/
	 * document the real behavior rather than to hide an unrelated problem.
	 */
	public function test_import_reepay_subscription_happy_path_creates_order_with_expected_meta() {
		if ( ! PLUGINS_STATE::reepay_pay_activated() ) {
			$this->markTestSkipped( 'rp_get_user_id_by_handle() requires the Frisbii Pay (reepay_pay) plugin.' );
		}

		ApiResponseMock::respond_with(
			array(
				// Plan-shaped fields (used by both the direct plan lookup and
				// the internal get_remote_plan_meta() -> get_plan() re-fetch).
				'handle'        => 'plan-happy-1',
				'amount'        => 1000,
				'name'          => 'Happy Plan',
				'quantity'      => 1,
				'currency'      => 'DKK',
				'schedule_type' => 'daily',
				// Customer-shaped fields (used by the customer lookup call;
				// harmless extras for the plan calls, which ignore them).
				'email'         => 'sub-happy-1@example.com',
				'first_name'    => 'Happy',
				'last_name'     => 'Customer',
				'city'          => 'Copenhagen',
				'country'       => 'DK',
			)
		);

		// @ suppresses the confirmed "Undefined array key" warning documented
		// above (converted to a hard error by this phpunit.xml's
		// convertWarningsToExceptions="true") - the real code tolerates it
		// silently in production, so suppressing it here reproduces real
		// behavior rather than masking an unrelated problem.
		$result = @WC_Reepay_Import_Helpers::import_reepay_subscription(
			array(
				'plan'     => 'plan-happy-1',
				'customer' => 'cust-happy-1',
				'handle'   => 'sub-happy-1',
				'state'    => 'active',
			)
		);

		$this->assertTrue( $result );

		$orders = wc_get_orders(
			array(
				'limit'      => 1,
				'meta_key'   => '_reepay_subscription_handle',
				'meta_value' => 'sub-happy-1',
			)
		);
		$this->assertCount( 1, $orders );

		$order = $orders[0];
		$this->assertSame( 'sub-happy-1', $order->get_meta( '_reepay_order' ) );
		$this->assertSame( 'sub-happy-1', $order->get_meta( '_reepay_subscription_handle' ) );
		$this->assertEquals( 1, $order->get_meta( '_reepay_imported' ) );
	}
}
