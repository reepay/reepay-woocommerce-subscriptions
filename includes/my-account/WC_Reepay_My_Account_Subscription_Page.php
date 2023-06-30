<?php

class WC_Reepay_My_Account_Subscription_Page {

	public static $menu_item_slug = 'r-subscription-view';

	public function __construct() {
		add_action( 'woocommerce_account_' . self::$menu_item_slug . '_endpoint', [ $this, 'subscription_endpoint' ], 10, 1 );
		add_filter( 'woocommerce_endpoint_' . self::$menu_item_slug . '_title', function () {
			return __( 'Subscription', 'reepay-subscriptions-for-woocommerce' );
		} );
	}

	public function subscription_endpoint( $subscription_handle ) {
		try {
			$subscription = reepay_s()->api()->request( "subscription/{$subscription_handle}" );

			self::customer_has_access_to_subscription( $subscription );

			reepay_s()->get_template( 'myaccount/my-subscription.php', array(
				'subscription'     => $subscription,
				'plan'             => reepay_s()->api()->request( "plan/{$subscription['plan']}/current" ),
				'cards'            => $this->get_customer_cards( $subscription_handle ),
				'dates_to_display' => $this->get_dates_to_display( $subscription )
			) );
		} catch ( Exception $e ) {
			reepay_s()->get_template( 'myaccount/my-subscriptions-error.php', array(
				'error' => __( 'Subscription not found', 'reepay-subscriptions-for-woocommerce' )
			) );
		}
	}

	public function get_dates_to_display( $subscription ) {
		return [
			'start_date'              => [
				'label' => _x( 'Start date', 'customer subscription table header', 'reepay-subscriptions-for-woocommerce' ),
				'value' => $subscription['first_period_start'] ?? '',
			],
			'last_order_date_created' => [
				'label' => _x( 'Last payment date', 'customer subscription table header', 'reepay-subscriptions-for-woocommerce' ),
				'value' => $subscription['current_period_start'] ?? '',
			],
			'next_payment'            => [
				'label' => _x( 'Next payment date', 'customer subscription table header', 'reepay-subscriptions-for-woocommerce' ),
				'value' => $subscription['next_period_start'] ?? '',
			],
			'end'                     => [
				'label' => _x( 'End date', 'customer subscription table header', 'reepay-subscriptions-for-woocommerce' ),
				'value' => $subscription['expires'] ?? '',
			],
			'start_end'               => [
				'label' => _x( 'Trial start date', 'customer subscription table header', 'reepay-subscriptions-for-woocommerce' ),
				'value' => $subscription['trial_start'] ?? '',
			],
			'trial_end'               => [
				'label' => _x( 'Trial end date', 'customer subscription table header', 'reepay-subscriptions-for-woocommerce' ),
				'value' => $subscription['trial_end'] ?? '',
			],
		];
	}

	public function get_customer_cards( $subscription_handle, $customer_handle = '' ) {
		if ( empty( $customer ) ) {
			$customer_handle = rp_get_customer_handle( get_current_user_id() );
		}

		$current_payment_method = reepay_s()->api()->request( "subscription/$subscription_handle/pm" )[0] ?? array();

		if ( empty( $current_payment_method['card'] ) ) {
			return array();
		}

		$payment_methods = reepay_s()->api()->request( "customer/$customer_handle/payment_method" )['cards'] ?? array();

		$current_payment_method = array_merge( $current_payment_method, $current_payment_method['card'] );
		unset( $current_payment_method['card'] );
		$current_payment_method['current'] = true;

		array_unshift( $payment_methods, $current_payment_method );

		return [
			'current' => $current_payment_method,
			'all' => $payment_methods
		];
	}

	public static function get_status( $subscription ) {
		if ( $subscription['is_cancelled'] === true ) {
			return __( 'Cancelled' );
		}

		if ( $subscription['state'] === 'expired' ) {
			return __( 'Expired' );
		}

		if ( $subscription['state'] === 'on_hold' ) {
			return __( 'On hold' );
		}

		if ( $subscription['state'] === 'is_cancelled' ) {
			return __( 'Cancelled' );
		}

		if ( $subscription['state'] === 'active' ) {
			if ( isset( $subscription['trial_end'] ) ) {
				$now       = new DateTime();
				$trial_end = new DateTime( $subscription['trial_end'] );
				if ( $trial_end > $now ) {
					return __( 'Trial' );
				}
			}

			return __( 'Active' );
		}

		return $subscription['state'];
	}

	public static function customer_has_access_to_subscription( $subscription, $customer_handle = '' ) {
		if ( empty( $customer ) ) {
			$customer_handle = rp_get_customer_handle( get_current_user_id() );
		}

		if ( $subscription['customer'] !== $customer_handle ) {
			throw new Exception( __( 'Permission denied', 'reepay-subscriptions-for-woocommerce' ) );
		}
	}
}