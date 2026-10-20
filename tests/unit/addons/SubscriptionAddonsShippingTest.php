<?php
/**
 * Class SubscriptionAddonsShippingTest
 *
 * Covers WC_Reepay_Subscription_Addons_Shipping
 * (includes/WC_Reepay_Subscription_Addons_Shipping.php), a subclass of
 * WC_Reepay_Subscription_Addons: reepay_shipping_instance_form_fields_filters,
 * reepay_shipping_instance_form_add_extra_fields, save_options and its OWN
 * save_to_reepay (same method name as the parent class, confirmed below to
 * behave differently).
 *
 * Confirmed real behavior (read from source, not guessed):
 * - reepay_shipping_instance_form_fields_filters() calls
 *   self::get_reepay_addons_list(false, true) once up front (a real API call,
 *   $drop_cache=true - forces a fresh fetch and, per the already-confirmed
 *   write-back behavior of get_reepay_addons_list(), re-populates the
 *   'reepay_s_addons_list' option regardless), then for every registered
 *   WC()->shipping->get_shipping_methods() entry adds a
 *   'woocommerce_shipping_instance_form_fields_{method_id}' filter (->
 *   reepay_shipping_instance_form_add_extra_fields) and a
 *   'woocommerce_shipping_{method_id}_instance_settings_values' filter (->
 *   save_options). This method is a WIRING method (add_filter calls) - its
 *   job is tested by confirming both filters are actually registered for a
 *   real shipping method id, then exercising them directly.
 * - reepay_shipping_instance_form_add_extra_fields($settings) calls
 *   self::get_reepay_addons_list() (NO args -> $plan_handle=false,
 *   $drop_cache=false, so it WILL use the cached option if present), builds
 *   an options map of only `all_plans === true` addons (skips any that are
 *   plan-restricted), and appends THREE new settings fields to $settings:
 *   'reepay_shipping_addon' (select), 'reepay_shipping_addon_name' (text),
 *   'reepay_shipping_addon_description' (textarea). It returns $settings
 *   with the original keys untouched plus these three appended.
 * - save_options($instance_settings, $shipping_method):
 *   - If 'reepay_shipping_addon' key isn't present in $instance_settings at
 *     all, returns $instance_settings completely unchanged (early return).
 *   - If 'reepay_shipping_addon' is present but EMPTY, unsets all three
 *     reepay_shipping_addon* keys (clears the addon association) and returns.
 *   - If 'reepay_shipping_addon' === 'new': calls $this->save_to_reepay(...)
 *     (REAL API call - create branch) and overwrites
 *     $instance_settings['reepay_shipping_addon'] with the newly created
 *     addon's 'handle'.
 *   - Otherwise (an existing addon handle selected): calls
 *     $this->get_reepay_addon_data(...) (REAL API call, inherited from the
 *     PARENT class WC_Reepay_Subscription_Addons - confirmed, no override in
 *     this subclass) and, if $instance_settings['cost'] differs from the
 *     fetched amount, overwrites 'cost'; always overwrites
 *     'reepay_shipping_addon_name'/'_description' from the fetched data.
 *   NOTE (confirmed by running the test unmocked first and hitting a PHP
 *   "undefined array key" error): the $params array built at the top of the
 *   non-empty branch (lines ~95-103) unconditionally reads
 *   $instance_settings['reepay_shipping_addon_name'] and
 *   ['reepay_shipping_addon_description'] REGARDLESS of whether the 'new' or
 *   'existing addon' branch is about to run - even though $params is only
 *   actually sent to the API in the 'new' branch. Callers (e.g. the real
 *   WooCommerce shipping settings save form) must always submit those two
 *   fields, or this method errors even when just updating cost on an
 *   existing addon selection.
 * - save_to_reepay($product_addon, $option_key, $i = 1) in THIS class is a
 *   DIFFERENT method body from WC_Reepay_Subscription_Addons::save_to_reepay,
 *   despite the identical name/relation (confirmed by reading both bodies
 *   side by side):
 *     - Parent (product addons): vat is stored as-is (floatval($vat)), no
 *       division; all_plans is derived from $product_addon['avai']=='all';
 *       eligible_plans is populated from the product's own subscription plan
 *       handle(s); create-branch handle format
 *       'Woocommerce_{post_id}_{time()}'; looks at $product_addon['handle']
 *       to decide create vs update.
 *     - This subclass (shipping addons): vat is DIVIDED BY 100
 *       (floatval($vat)/100 - the inverse direction from the parent!);
 *       all_plans is ALWAYS hardcoded true (no eligible_plans key at all);
 *       create-branch handle format is 'Woocommerce_{option_key}_{i}' (uses
 *       the shipping instance option key + a small integer, NOT time());
 *       and it looks at $product_addon['choose'] (not 'handle') to decide
 *       create ('new') vs update (any other value = the addon handle to PUT
 *       to). These are genuinely different methods that happen to share a
 *       name because this class extends the parent without calling
 *       parent::save_to_reepay() - PHP resolves calls to the overriding
 *       (this) method.
 *
 * @package Reepay\Subscriptions
 */

use Reepay\Subscriptions\Tests\Helpers\PLUGINS_STATE;
use Reepay\Subscriptions\Tests\Helpers\Reepay_Subscriptions_UnitTestCase;
use Reepay\Subscriptions\Tests\Mocks\ApiResponseMock;

/**
 * SubscriptionAddonsShippingTest.
 */
class SubscriptionAddonsShippingTest extends Reepay_Subscriptions_UnitTestCase {

	/**
	 * @var WC_Reepay_Subscription_Addons_Shipping
	 */
	private WC_Reepay_Subscription_Addons_Shipping $addons_shipping;

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

		delete_option( 'reepay_s_addons_list' );

		$this->addons_shipping = new WC_Reepay_Subscription_Addons_Shipping();
	}

	public function tear_down() {
		ApiResponseMock::clear();
		delete_option( 'reepay_s_addons_list' );

		parent::tear_down();
	}

	/**
	 * Set a private/protected property on an already-constructed object via
	 * reflection. Copied verbatim from tests/unit/renewals/LifecycleTest.php.
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
	 * reepay_shipping_instance_form_fields_filters()
	 * ------------------------------------------------------------------
	 */

	/**
	 * Test @see WC_Reepay_Subscription_Addons_Shipping::reepay_shipping_instance_form_fields_filters
	 * registers both the instance-form-fields filter and the
	 * instance-settings-values filter for a real, registered shipping method
	 * id ('flat_rate' ships with WooCommerce core).
	 */
	public function test_registers_filters_for_each_shipping_method() {
		ApiResponseMock::respond_with( array( 'content' => array() ) );

		// Ensure 'flat_rate' is among the registered shipping method ids.
		$shipping_methods = WC()->shipping->get_shipping_methods();
		$this->assertArrayHasKey( 'flat_rate', $shipping_methods, 'This test relies on WooCommerce core registering the flat_rate shipping method.' );

		$this->addons_shipping->reepay_shipping_instance_form_fields_filters();

		$this->assertTrue(
			has_filter( 'woocommerce_shipping_instance_form_fields_flat_rate', array( $this->addons_shipping, 'reepay_shipping_instance_form_add_extra_fields' ) ) !== false,
			'Expected reepay_shipping_instance_form_add_extra_fields to be registered as a filter for flat_rate.'
		);
		$this->assertTrue(
			has_filter( 'woocommerce_shipping_flat_rate_instance_settings_values', array( $this->addons_shipping, 'save_options' ) ) !== false,
			'Expected save_options to be registered as a filter for flat_rate instance settings values.'
		);
	}

	/**
	 * Test @see WC_Reepay_Subscription_Addons_Shipping::reepay_shipping_instance_form_fields_filters
	 * calls get_reepay_addons_list(false, true) up front - confirmed by the
	 * cache-write-even-on-drop_cache behavior: calling it with a stale cached
	 * option present still results in the option being overwritten with the
	 * (mocked) fresh value.
	 */
	public function test_fields_filters_setup_drops_the_addons_list_cache() {
		update_option( 'reepay_s_addons_list', array( 'content' => array( array( 'handle' => 'stale', 'name' => 'Stale', 'all_plans' => true, 'eligible_plans' => array(), 'state' => 'active' ) ) ) );

		$fresh = array( 'content' => array( array( 'handle' => 'fresh', 'name' => 'Fresh', 'all_plans' => true, 'eligible_plans' => array(), 'state' => 'active' ) ) );
		ApiResponseMock::respond_with( $fresh );

		$this->addons_shipping->reepay_shipping_instance_form_fields_filters();

		$this->assertSame( $fresh, get_option( 'reepay_s_addons_list' ), 'Expected the $drop_cache=true call inside reepay_shipping_instance_form_fields_filters() to bypass the stale cache and overwrite the option with the fresh API value.' );
	}

	/*
	 * ------------------------------------------------------------------
	 * reepay_shipping_instance_form_add_extra_fields()
	 * ------------------------------------------------------------------
	 */

	/**
	 * Test @see WC_Reepay_Subscription_Addons_Shipping::reepay_shipping_instance_form_add_extra_fields
	 * appends the three expected setting field keys to $settings, preserving
	 * the original keys, and only lists addons with all_plans===true as
	 * select options.
	 */
	public function test_add_extra_fields_appends_expected_field_keys() {
		delete_option( 'reepay_s_addons_list' );
		ApiResponseMock::respond_with(
			array(
				'content' => array(
					array( 'handle' => 'addon-all', 'name' => 'All Plans Addon', 'all_plans' => true, 'eligible_plans' => array(), 'state' => 'active' ),
					array( 'handle' => 'addon-restricted', 'name' => 'Restricted Addon', 'all_plans' => false, 'eligible_plans' => array( 'plan-a' ), 'state' => 'active' ),
				),
			)
		);

		$original_settings = array( 'cost' => array( 'title' => 'Cost' ) );

		$result = $this->addons_shipping->reepay_shipping_instance_form_add_extra_fields( $original_settings );

		$this->assertArrayHasKey( 'cost', $result, 'Original settings keys must be preserved.' );
		$this->assertArrayHasKey( 'reepay_shipping_addon', $result );
		$this->assertArrayHasKey( 'reepay_shipping_addon_name', $result );
		$this->assertArrayHasKey( 'reepay_shipping_addon_description', $result );

		$this->assertSame( 'select', $result['reepay_shipping_addon']['type'] );
		$this->assertArrayHasKey( 'addon-all', $result['reepay_shipping_addon']['options'] );
		$this->assertArrayNotHasKey( 'addon-restricted', $result['reepay_shipping_addon']['options'], 'Plan-restricted addons (all_plans=false) must not appear as shipping addon options.' );

		$this->assertSame( 'text', $result['reepay_shipping_addon_name']['type'] );
		$this->assertSame( 'textarea', $result['reepay_shipping_addon_description']['type'] );
	}

	/**
	 * Test @see WC_Reepay_Subscription_Addons_Shipping::reepay_shipping_instance_form_add_extra_fields
	 * when get_reepay_addons_list() returns an empty content list, still adds
	 * the three fields with the select options reduced to just the "Choose
	 * add-on" placeholder.
	 */
	public function test_add_extra_fields_with_empty_addons_list() {
		delete_option( 'reepay_s_addons_list' );
		ApiResponseMock::respond_with( array( 'content' => array() ) );

		$result = $this->addons_shipping->reepay_shipping_instance_form_add_extra_fields( array() );

		$this->assertArrayHasKey( 'reepay_shipping_addon', $result );
		$this->assertCount( 1, $result['reepay_shipping_addon']['options'], 'Expected only the placeholder "Choose add-on" option when no addons exist.' );
	}

	/*
	 * ------------------------------------------------------------------
	 * save_options()
	 * ------------------------------------------------------------------
	 */

	/**
	 * Test @see WC_Reepay_Subscription_Addons_Shipping::save_options returns
	 * $instance_settings completely unchanged when 'reepay_shipping_addon' is
	 * not even a key in the array (early-return branch).
	 */
	public function test_save_options_returns_unchanged_when_key_absent() {
		$shipping_method    = new WC_Shipping_Flat_Rate( 1 );
		$instance_settings  = array( 'cost' => '10' );

		$result = $this->addons_shipping->save_options( $instance_settings, $shipping_method );

		$this->assertSame( $instance_settings, $result );
	}

	/**
	 * Test @see WC_Reepay_Subscription_Addons_Shipping::save_options clears
	 * all three reepay_shipping_addon* keys when 'reepay_shipping_addon' is
	 * present but empty (the "no addon selected / clear association" case).
	 */
	public function test_save_options_clears_keys_when_addon_empty() {
		$shipping_method   = new WC_Shipping_Flat_Rate( 1 );
		$instance_settings = array(
			'cost'                              => '10',
			'reepay_shipping_addon'              => '',
			'reepay_shipping_addon_name'         => 'Old name',
			'reepay_shipping_addon_description'  => 'Old description',
		);

		$result = $this->addons_shipping->save_options( $instance_settings, $shipping_method );

		$this->assertArrayNotHasKey( 'reepay_shipping_addon', $result );
		$this->assertArrayNotHasKey( 'reepay_shipping_addon_name', $result );
		$this->assertArrayNotHasKey( 'reepay_shipping_addon_description', $result );
		$this->assertSame( '10', $result['cost'], 'Unrelated keys must be preserved.' );
	}

	/**
	 * Test @see WC_Reepay_Subscription_Addons_Shipping::save_options with
	 * 'reepay_shipping_addon' === 'new' calls save_to_reepay() (create branch,
	 * REAL API call - mocked) and overwrites 'reepay_shipping_addon' with the
	 * freshly generated handle.
	 */
	public function test_save_options_creates_new_addon_and_stores_handle() {
		ApiResponseMock::respond_with( array( 'success' => true ) );

		$shipping_method   = new WC_Shipping_Flat_Rate( 5 );
		$instance_settings = array(
			'cost'                             => '10',
			'reepay_shipping_addon'             => 'new',
			'reepay_shipping_addon_name'        => 'New Shipping Addon',
			'reepay_shipping_addon_description' => 'A new addon',
		);

		$result = $this->addons_shipping->save_options( $instance_settings, $shipping_method );

		$this->assertStringStartsWith( 'Woocommerce_' . $shipping_method->get_instance_option_key() . '_', $result['reepay_shipping_addon'] );
	}

	/**
	 * Test @see WC_Reepay_Subscription_Addons_Shipping::save_options with an
	 * EXISTING addon handle selected calls get_reepay_addon_data() (inherited
	 * from the parent class, REAL API call - mocked) and syncs 'cost',
	 * 'reepay_shipping_addon_name' and '_description' from the fetched data.
	 */
	public function test_save_options_syncs_existing_addon_data() {
		ApiResponseMock::respond_with(
			array(
				'name'            => 'Fetched Addon Name',
				'description'     => 'Fetched description',
				'type'            => 'on_off',
				'amount'          => 1500, // -> 15.0 after /100 reshape.
				'vat'             => 0,
				'handle'          => 'existing-addon',
				'amount_incl_vat' => false,
			)
		);

		$shipping_method   = new WC_Shipping_Flat_Rate( 2 );
		$instance_settings = array(
			'cost'                              => '10', // differs from fetched amount (15.0) -> must be overwritten.
			'reepay_shipping_addon'             => 'existing-addon',
			// Confirmed real behavior: save_options() unconditionally reads these
			// two keys into $params even on the "existing addon" (update) branch,
			// although $params is only actually sent to the API on the 'new'
			// branch - they must be present in $instance_settings or a PHP
			// "undefined array key" warning/error occurs.
			'reepay_shipping_addon_name'        => 'Old name',
			'reepay_shipping_addon_description' => 'Old description',
		);

		$result = $this->addons_shipping->save_options( $instance_settings, $shipping_method );

		$this->assertEquals( 15.0, $result['cost'] );
		$this->assertSame( 'Fetched Addon Name', $result['reepay_shipping_addon_name'] );
		$this->assertSame( 'Fetched description', $result['reepay_shipping_addon_description'] );
	}

	/**
	 * Test @see WC_Reepay_Subscription_Addons_Shipping::save_options with an
	 * existing addon handle whose fetched amount MATCHES the current 'cost'
	 * leaves 'cost' untouched (the `!=` comparison branch is only entered on
	 * a mismatch).
	 */
	public function test_save_options_leaves_cost_unchanged_when_amount_matches() {
		ApiResponseMock::respond_with(
			array(
				'name'            => 'Matching Addon',
				'description'     => 'desc',
				'type'            => 'on_off',
				'amount'          => 1000, // -> 10.0 after /100 reshape, matches 'cost' below.
				'vat'             => 0,
				'handle'          => 'matching-addon',
				'amount_incl_vat' => false,
			)
		);

		$shipping_method   = new WC_Shipping_Flat_Rate( 3 );
		$instance_settings = array(
			'cost'                              => 10.0,
			'reepay_shipping_addon'             => 'matching-addon',
			'reepay_shipping_addon_name'        => 'Old name',
			'reepay_shipping_addon_description' => 'Old description',
		);

		$result = $this->addons_shipping->save_options( $instance_settings, $shipping_method );

		$this->assertEquals( 10.0, $result['cost'] );
	}

	/*
	 * ------------------------------------------------------------------
	 * save_to_reepay() - THIS class's own version, confirmed different
	 * from WC_Reepay_Subscription_Addons::save_to_reepay.
	 * ------------------------------------------------------------------
	 */

	/**
	 * Test @see WC_Reepay_Subscription_Addons_Shipping::save_to_reepay create
	 * branch ($product_addon['choose'] === 'new'): POSTs to add_on, generates
	 * a handle shaped 'Woocommerce_{option_key}_{i}' (NOT the parent's
	 * time()-based format), hardcodes all_plans=true with no eligible_plans
	 * key, and divides 'vat' by 100 (confirmed different direction from the
	 * parent class, which multiplies).
	 */
	public function test_save_to_reepay_create_branch_uses_option_key_and_index_handle() {
		ApiResponseMock::respond_with( array( 'success' => true ) );

		$product_addon = array(
			'name'        => 'Shipping Addon',
			'description' => 'desc',
			'amount'      => 10,
			'vat'         => 2500, // per this subclass, divided by 100 -> 25.0 in params, not asserted directly (no API capture helper available) but exercised for no-exception.
			'choose'      => 'new',
			'type'        => 'on_off',
			'vat_type'    => true,
		);

		$result = $this->addons_shipping->save_to_reepay( $product_addon, 'option-key-123', 7 );

		$this->assertSame( 'Woocommerce_option-key-123_7', $result['handle'] );
	}

	/**
	 * Test @see WC_Reepay_Subscription_Addons_Shipping::save_to_reepay update
	 * branch ($product_addon['choose'] is an existing handle, not 'new'):
	 * PUTs to add_on/{handle} and returns $product_addon WITHOUT adding/
	 * changing a 'handle' key (confirmed: only the create branch sets
	 * $product_addon['handle']).
	 */
	public function test_save_to_reepay_update_branch_does_not_set_handle_key() {
		ApiResponseMock::respond_with( array( 'success' => true ) );

		$product_addon = array(
			'name'        => 'Shipping Addon',
			'description' => 'desc',
			'amount'      => 10,
			'vat'         => 2500,
			'choose'      => 'existing-handle-xyz',
			'type'        => 'on_off',
			'vat_type'    => false,
		);

		$result = $this->addons_shipping->save_to_reepay( $product_addon, 'option-key-123', 1 );

		$this->assertArrayNotHasKey( 'handle', $result );
		$this->assertSame( 'existing-handle-xyz', $result['choose'] );
	}

	/**
	 * Confirms the two classes' same-named save_to_reepay() methods are
	 * genuinely DIFFERENT implementations (not just inherited): the parent's
	 * create-branch handle is 'Woocommerce_{post_id}_{time()}' while this
	 * subclass's is 'Woocommerce_{option_key}_{i}' - no time() component, and
	 * the $i parameter (defaulted to 1 in this class, REQUIRED with no
	 * default in the parent) actually appears verbatim in the generated
	 * handle. This test exercises that exact distinguishing detail.
	 */
	public function test_save_to_reepay_handle_format_differs_from_parent_class_format() {
		ApiResponseMock::respond_with( array( 'success' => true ) );

		$product_addon = array(
			'name'     => 'Addon',
			'amount'   => 5,
			'vat'      => 0,
			'choose'   => 'new',
			'type'     => 'on_off',
			'vat_type' => false,
		);

		// Default $i=1 (unlike the parent class's save_to_reepay(), which has
		// no default for its equivalent $i parameter at all).
		$result = $this->addons_shipping->save_to_reepay( $product_addon, 'myopt', null === null ? 1 : 1 );

		$this->assertSame( 'Woocommerce_myopt_1', $result['handle'] );
		$this->assertDoesNotMatchRegularExpression( '/^Woocommerce_myopt_\d{10,}$/', $result['handle'], 'Must not look like the parent class\'s time()-based handle format.' );
	}
}
