<?php
/**
 * Class ImportMenuTest
 *
 * Covers WC_Reepay_Import_Menu (includes/import/WC_Reepay_Import_Menu.php).
 *
 * Phase 5 scope: baseline regression coverage. create_submenu()/
 * create_settings_fields() are thin WordPress registration wrappers
 * (add_submenu_page/register_setting/add_settings_section/add_settings_field)
 * - one "doesn't fatal when called" smoke test per method is sufficient,
 * consistent with the pattern already used for
 * WC_Reepay_Subscriptions_List::create_menu() in Task 5.1.
 *
 * print_checkbox()/print_import_page() both call wc_get_template() against
 * real template files under templates/import/ - confirmed those files exist
 * on disk (templates/import/page.php, templates/import/checkbox.php,
 * templates/import/tables/{customers,cards,subscriptions}.php), so the smoke
 * tests below render the real templates rather than stubbing them out.
 *
 * @package Reepay\Subscriptions
 */

use Reepay\Subscriptions\Tests\Helpers\Reepay_Subscriptions_UnitTestCase;

/**
 * ImportMenuTest.
 */
class ImportMenuTest extends Reepay_Subscriptions_UnitTestCase {

	/**
	 * @var WC_Reepay_Import_Menu
	 */
	private WC_Reepay_Import_Menu $import_menu;

	public function set_up() {
		parent::set_up();

		( new WC_Reepay_Import() )->init();

		$this->import_menu = new WC_Reepay_Import_Menu();
	}

	public function tear_down() {
		unset( $_GET['page'] );

		parent::tear_down();
	}

	/**
	 * Test @see WC_Reepay_Import_Menu::create_submenu does not fatal when
	 * invoked directly on the admin_menu hook context.
	 *
	 * Requires a user with 'manage_options' - confirmed by reading WordPress
	 * core's add_submenu_page(): it returns false WITHOUT ever touching the
	 * global $submenu array at all when current_user_can($capability) is
	 * false, so without an admin user this assertion would fail not because
	 * of a bug, but because $submenu['tools.php'] is never populated for an
	 * unprivileged current user (confirmed by actually running this test).
	 */
	public function test_create_submenu_does_not_fatal() {
		global $submenu;

		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		$this->import_menu->create_submenu();

		$this->assertArrayHasKey( 'tools.php', $submenu );
	}

	/**
	 * Test @see WC_Reepay_Import_Menu::create_settings_fields does not fatal
	 * when invoked directly, and registers the 'reepay_import' setting.
	 */
	public function test_create_settings_fields_does_not_fatal() {
		global $wp_settings_fields;

		$this->import_menu->create_settings_fields();

		$this->assertArrayHasKey( WC_Reepay_Import_Menu::$menu_slug, $wp_settings_fields );
	}

	/**
	 * Test @see WC_Reepay_Import_Menu::is_current_page returns true only when
	 * $_GET['page'] matches the menu slug.
	 */
	public function test_is_current_page_true_when_page_matches() {
		$_GET['page'] = WC_Reepay_Import_Menu::$menu_slug;

		$this->assertTrue( WC_Reepay_Import_Menu::is_current_page() );
	}

	/**
	 * Test @see WC_Reepay_Import_Menu::is_current_page returns false when
	 * $_GET['page'] is unset or does not match.
	 */
	public function test_is_current_page_false_when_page_unset_or_mismatched() {
		unset( $_GET['page'] );
		$this->assertFalse( WC_Reepay_Import_Menu::is_current_page() );

		$_GET['page'] = 'some-other-page';
		$this->assertFalse( WC_Reepay_Import_Menu::is_current_page() );
	}

	/**
	 * Test @see WC_Reepay_Import_Menu::print_checkbox renders without fatal
	 * and echoes a checkbox input for the given option_name path - smoke
	 * test per the lighter bar for this file.
	 */
	public function test_print_checkbox_does_not_fatal() {
		ob_start();
		$this->import_menu->print_checkbox(
			array(
				'option_name' => array( 'customers', 'all' ),
				'class'       => 'reepay-import__row',
			)
		);
		$output = ob_get_clean();

		$this->assertStringContainsString( 'checkbox', $output );
	}

	/**
	 * Test @see WC_Reepay_Import_Menu::print_import_page renders without
	 * fatal across the main page template and each object type's table
	 * template - smoke test per the lighter bar for this file.
	 */
	public function test_print_import_page_does_not_fatal() {
		ob_start();
		$this->import_menu->print_import_page();
		$output = ob_get_clean();

		$this->assertIsString( $output );
	}
}
