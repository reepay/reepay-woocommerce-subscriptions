<?php
/**
 * Class OrderGenerator
 *
 * @package Reepay\Subscriptions
 */

namespace Reepay\Subscriptions\Tests\Helpers;

use WC_Order;

/**
 * Class OrderGenerator
 */
class OrderGenerator {
	/**
	 * @var WC_Order
	 */
	private WC_Order $order;

	public function __construct() {
		$this->order = wc_create_order();
	}

	/**
	 * Add a product line item to the order.
	 *
	 * @param string $type product type (see ProductGenerator).
	 * @param array  $product_data product meta data.
	 * @param int    $qty line item quantity.
	 *
	 * @return $this
	 */
	public function add_product( string $type, array $product_data = array(), int $qty = 1 ): OrderGenerator {
		$product = ( new ProductGenerator( $type, $product_data ) )->product();

		if ( null !== $product ) {
			$this->order->add_product( $product, $qty );
			$this->order->calculate_totals();
			$this->order->save();
		}

		return $this;
	}

	/**
	 * Set a WC_Order property via its setter.
	 *
	 * @param string $key property name (without leading underscore).
	 * @param mixed  $value value to set.
	 *
	 * @return $this
	 */
	public function set_prop( string $key, $value ): OrderGenerator {
		$function = 'set_' . ltrim( $key, '_' );

		if ( is_callable( array( $this->order, $function ) ) ) {
			$this->order->{$function}( $value );
			$this->order->save();
		}

		return $this;
	}

	/**
	 * @return WC_Order
	 */
	public function order(): WC_Order {
		return $this->order;
	}
}
