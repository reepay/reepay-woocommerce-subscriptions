<?php
/**
 * Class DataProvider
 *
 * @package Reepay\Subscriptions
 */

namespace Reepay\Subscriptions\Tests\Helpers;

/**
 * Class DataProvider
 */
abstract class DataProvider {
	/**
	 * @return string[]
	 */
	public static function currencies(): array {
		return array( 'DKK', 'EUR', 'SEK', 'NOK', 'USD', 'GBP' );
	}

	/**
	 * @return string[]
	 */
	public static function subscription_statuses(): array {
		return array( 'active', 'on-hold', 'cancelled', 'expired', 'pending-cancel' );
	}
}
