<?php
/**
 * Class SubscriptionGenerator
 *
 * @package Reepay\Subscriptions
 */

namespace Reepay\Subscriptions\Tests\Helpers;

use Exception;
use WC_Order;
use WC_Subscription;

/**
 * Class SubscriptionGenerator
 */
class SubscriptionGenerator {
	/**
	 * @var WC_Order
	 */
	private WC_Order $parent_order;

	/**
	 * @var string
	 */
	private string $status = 'active';

	/**
	 * @var string
	 */
	private string $billing_period = 'month';

	/**
	 * @var int
	 */
	private int $billing_interval = 1;

	/**
	 * @param WC_Order $parent_order the order the subscription is created from.
	 */
	public function __construct( WC_Order $parent_order ) {
		$this->parent_order = $parent_order;
	}

	/**
	 * @param string $status a valid WC_Subscription status (without the 'wc-' prefix).
	 *
	 * @return $this
	 */
	public function with_status( string $status ): SubscriptionGenerator {
		$this->status = $status;

		return $this;
	}

	/**
	 * @param string $period 'day'|'week'|'month'|'year'.
	 * @param int    $interval billing interval.
	 *
	 * @return $this
	 */
	public function with_billing_period( string $period, int $interval = 1 ): SubscriptionGenerator {
		$this->billing_period   = $period;
		$this->billing_interval = $interval;

		return $this;
	}

	/**
	 * Build the WC_Subscription.
	 *
	 * @return WC_Subscription
	 *
	 * @throws Exception If WooCommerce Subscriptions is not active.
	 */
	public function subscription(): WC_Subscription {
		if ( ! PLUGINS_STATE::woo_subs_activated() ) {
			throw new Exception( 'WooCommerce Subscriptions is not active; cannot build a WC_Subscription.' );
		}

		$subscription = wcs_create_subscription(
			array(
				'order_id'         => $this->parent_order->get_id(),
				'billing_period'   => $this->billing_period,
				'billing_interval' => $this->billing_interval,
				'customer_id'      => $this->parent_order->get_customer_id(),
			)
		);

		foreach ( $this->parent_order->get_items() as $item ) {
			$subscription->add_item( clone $item );
		}

		$subscription->calculate_totals();
		$subscription->update_status( $this->status );
		$subscription->save();

		return $subscription;
	}
}
