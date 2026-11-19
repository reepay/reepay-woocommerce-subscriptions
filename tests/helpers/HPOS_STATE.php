<?php
/**
 * Class HPOS_STATE
 *
 * @package Reepay\Subscriptions
 */

namespace Reepay\Subscriptions\Tests\Helpers;

use Exception;

/**
 * Class HPOS_STATE
 */
abstract class HPOS_STATE {
	private static bool $inited = false;

	private static bool $hpos_activated;

	private static string $option_name = 'woocommerce_custom_orders_table_enabled';

	/**
	 * Initialize HPOS state for this test run.
	 *
	 * @param bool|null $hpos_activated force a value, or null to read HPOS_ENABLED env var.
	 *
	 * @throws Exception If called more than once per run.
	 */
	public static function init( ?bool $hpos_activated = null ) {
		if ( self::$inited ) {
			throw new Exception( 'HPOS_STATE already inited' );
		}

		if ( is_null( $hpos_activated ) ) {
			$hpos_activated = trim( (string) getenv( 'HPOS_ENABLED' ) ) === 'yes';
		}

		echo 'HPOS_ENABLED=' . ( $hpos_activated ? 'yes' : 'no' ) . "\n";

		self::$hpos_activated = $hpos_activated;
		self::$inited         = true;

		update_option( self::$option_name, self::$hpos_activated ? 'yes' : 'no' );
	}

	/**
	 * @return bool
	 */
	public static function is_active(): bool {
		return self::$hpos_activated;
	}
}
