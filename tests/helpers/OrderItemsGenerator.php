<?php
/**
 * Class OrderItemsGenerator
 *
 * @package Reepay\Subscriptions
 */

namespace Reepay\Subscriptions\Tests\Helpers;

use WC_Order;

/**
 * Class OrderItemsGenerator
 */
abstract class OrderItemsGenerator {
	/**
	 * Return the order's line items as a plain array (helper for assertions).
	 *
	 * @param WC_Order $order order to read from.
	 *
	 * @return array
	 */
	public static function for_order( WC_Order $order ): array {
		return array_values( $order->get_items() );
	}
}
