<?php
/**
 * Class SubscriptionsListTest
 *
 * Covers WC_Reepay_Subscriptions_List (includes/WC_Reepay_Subscriptions_List.php):
 * create_menu and render_page.
 *
 * Phase 5 scope: baseline regression coverage per requirements.md §3.4 - this
 * is a thin (37-line) class, so a lighter checklist applies per the brief.
 *
 * Confirmed real behavior (read from source):
 * - create_menu() is a thin wrapper around add_submenu_page() - a single
 *   test confirming it does not fatal when called is sufficient; this suite
 *   does not assert on WordPress's internal submenu registry state.
 * - render_page() echoes a `<form>` wrapper, then instantiates a REAL
 *   WC_Reepay_Subscriptions_Table and calls prepare_items(), search_box(),
 *   display() on it. prepare_items() transitively makes the same outbound
 *   API call as WC_Reepay_Subscriptions_Table::get_subscriptions()
 *   (reepay_s()->api()->request("subscription?...")), so it must be mocked
 *   the same way (ApiResponseMock + a fake private key injected via
 *   reflection, copied verbatim from Phase 3/4 test files) or the call fails
 *   and prepare_items() catches the Exception internally (no fatal either
 *   way, but mocking lets us exercise the real happy path end-to-end).
 *
 * @package Reepay\Subscriptions
 */

use Reepay\Subscriptions\Tests\Helpers\Reepay_Subscriptions_Ajax_UnitTestCase;
use Reepay\Subscriptions\Tests\Mocks\ApiResponseMock;

/**
 * SubscriptionsListTest.
 */
class SubscriptionsListTest extends Reepay_Subscriptions_Ajax_UnitTestCase {

	/**
	 * @var WC_Reepay_Subscriptions_List
	 */
	private WC_Reepay_Subscriptions_List $list;

	public function set_up() {
		parent::set_up();

		// Both WC_Reepay_Subscription_API (reepay_s()->api()) reads its key
		// from a settings snapshot cached once at plugin bootstrap, long
		// before any option set here would be read - inject a fake test key
		// directly via reflection instead. Pattern copied verbatim from
		// tests/unit/renewals/LifecycleTest.php.
		update_option(
			'woocommerce_reepay_checkout_settings',
			array(
				'private_key_test' => 'test-key-reepay',
				'test_mode'        => 'yes',
			)
		);

		$this->set_fake_private_key( WC_Reepay_Subscription_API::get_instance(), 'private_key_test', 'test-key-reepay' );
		$this->set_fake_private_key( WC_Reepay_Subscription_API::get_instance(), 'test_mode', true );

		$this->list = new WC_Reepay_Subscriptions_List();

		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );
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
	 * create_menu()
	 * ------------------------------------------------------------------
	 */

	/**
	 * Test @see WC_Reepay_Subscriptions_List::create_menu does not fatal when
	 * called directly (thin add_submenu_page() wrapper).
	 */
	public function test_create_menu_does_not_fatal() {
		set_current_screen( 'edit-post' );

		$this->list->create_menu();

		set_current_screen( 'front' );

		$this->addToAssertionCount( 1 );
	}

	/*
	 * ------------------------------------------------------------------
	 * render_page()
	 * ------------------------------------------------------------------
	 */

	/**
	 * Test @see WC_Reepay_Subscriptions_List::render_page renders without a
	 * fatal and outputs the expected <form> wrapper markup, with the
	 * transitive WC_Reepay_Subscriptions_Table API call mocked to a
	 * realistic (empty-content) response.
	 */
	public function test_render_page_outputs_form_markup() {
		ApiResponseMock::respond_with(
			array(
				'content'       => array(),
				'total_elements' => 0,
				'size'           => 20,
				'total_pages'    => 0,
			)
		);

		ob_start();
		$this->list->render_page();
		$output = ob_get_clean();

		$this->assertStringContainsString( '<form action="" method="get" class="reepay-subscriptions-page">', $output );
		$this->assertStringContainsString( 'name="page" value="reepay-subscriptions-for-woocommerce"', $output );
		$this->assertStringContainsString( '</form>', $output );
	}

	/**
	 * Test @see WC_Reepay_Subscriptions_List::render_page renders without a
	 * fatal when the transitive API call throws (mocked error response) -
	 * WC_Reepay_Subscriptions_Table::prepare_items() catches the Exception
	 * internally and leaves its items empty rather than propagating.
	 */
	public function test_render_page_does_not_fatal_on_api_error() {
		ApiResponseMock::respond_with( array( 'error' => 'invalid_request', 'message' => 'boom' ), 400 );

		ob_start();
		$this->list->render_page();
		$output = ob_get_clean();

		$this->assertStringContainsString( '<form', $output );
	}

	/**
	 * Test @see WC_Reepay_Subscriptions_List::render_page refuses users
	 * without 'manage_woocommerce' (wp_die) before rendering anything or
	 * calling the Frisbii API with the merchant's private key.
	 */
	public function test_render_page_dies_for_user_without_manage_woocommerce() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

		$requests = 0;
		$counter  = function ( $preempt ) use ( &$requests ) {
			$requests++;

			return $preempt;
		};
		add_filter( 'pre_http_request', $counter, 5 );

		// The Ajax testcase's wp_die handler closes the current output buffer
		// itself (appending it to $this->_last_response), so only close it
		// here when render_page() did NOT die.
		ob_start();
		try {
			$this->list->render_page();
			$died   = false;
			$output = ob_get_clean();
		} catch ( WPDieException $e ) {
			$died   = true;
			$output = $this->_last_response;
		}

		remove_filter( 'pre_http_request', $counter, 5 );

		$this->assertTrue( $died, 'Expected wp_die() for a user without manage_woocommerce.' );
		$this->assertStringNotContainsString( '<form', $output );
		$this->assertSame( 0, $requests );
	}
}
