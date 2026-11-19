<?php
/**
 * PHPUnit bootstrap file.
 *
 * @package Reepay\Subscriptions
 */

use Reepay\Subscriptions\Tests\Helpers\HPOS_STATE;
use Reepay\Subscriptions\Tests\Helpers\PLUGINS_STATE;

$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

$_phpunit_polyfills_path = getenv( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' );
if ( false !== $_phpunit_polyfills_path ) {
	define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', $_phpunit_polyfills_path );
}

if ( ! file_exists( "$_tests_dir/includes/functions.php" ) ) {
	echo "Could not find $_tests_dir/includes/functions.php, have you run npm run phpunit:init ?" . PHP_EOL;
	exit( 1 );
}

require_once "$_tests_dir/includes/functions.php";

/**
 * Manually load the plugin and its dependencies.
 */
tests_add_filter(
	'muplugins_loaded',
	function () {
		HPOS_STATE::init();
		PLUGINS_STATE::activate_plugins();

		require_once __DIR__ . '/../../reepay-subscriptions-for-woocommerce.php';
	}
);

tests_add_filter( 'deprecated_function_trigger_error', '__return_false' );

if ( ! defined( 'FS_METHOD' ) ) {
	define( 'FS_METHOD', 'direct' );
}

require_once __DIR__ . '/../../vendor/autoload.php';

require_once "$_tests_dir/includes/bootstrap.php";

/**
 * WordPress core itself (not this plugin) triggers PHP 8.1+ E_DEPRECATED
 * notices in a few places (e.g. preg_match()/preg_replace() receiving null)
 * that have nothing to do with this plugin's code or test correctness - they
 * just clutter test output. WP_DEBUG=true (set by bin/install-wp-tests.sh)
 * makes wp_debug_mode() set error_reporting(E_ALL) during the require above,
 * so this must run AFTER it to actually take effect for the rest of the run.
 */
error_reporting( E_ALL & ~E_DEPRECATED );
