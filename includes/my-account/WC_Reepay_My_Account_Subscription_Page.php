<?php

class WC_Reepay_My_Account_Subscription_Page {

	public static $menu_item_slug = 'r-subscription-view';

	private $subscription_actions = array(
		'cancel_subscription',
		'uncancel_subscription',
		'put_on_hold',
		'reactivate',
		'change_payment_method'
	);

	public function __construct() {
		add_action( 'woocommerce_account_' . self::$menu_item_slug . '_endpoint', [ $this, 'subscription_endpoint' ], 10, 1 );
		add_filter( 'woocommerce_endpoint_' . self::$menu_item_slug . '_title', function () {
			return __( 'Subscription', 'reepay-subscriptions-for-woocommerce' );
		} );
		add_action( 'template_redirect', [ $this, 'do_action' ] );
	}

	public function subscription_endpoint( $subscription_handle ) {
		try {
			$subscription    = reepay_s()->api()->request( "subscription/{$subscription_handle}" );

			$this->can_customer_view_subscription( $subscription );

			reepay_s()->get_template( 'myaccount/my-subscription.php', array(
				'subscription'     => $subscription,
				'plan'             => reepay_s()->api()->request( "plan/{$subscription['plan']}/current" ),
				'cards'            => $this->get_customer_cards( $subscription_handle ),
				'dates_to_display' => $this->get_dates_to_display( $subscription )
			) );
		} catch ( Exception $e ) {
			reepay()->get_template( 'myaccount/my-subscriptions-error.php', array(
				'error' => __( 'Subscription not found', 'reepay-subscriptions-for-woocommerce' )
			) );
		}
	}

	public function can_customer_view_subscription( $subscription, $customer_handle = '' ) {
		if( empty( $customer ) ) {
			$customer_handle = rp_get_customer_handle( get_current_user_id() );
		}

		if ( $subscription['customer'] !== $customer_handle ) {
			throw new Exception();
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

		return $payment_methods;
	}

	public function do_action() {
		foreach ( $this->subscription_actions as $subscription_action ) {
			if ( empty( $_GET[ $subscription_action ] ) ) {
				continue;
			}

			$handle = urlencode( sanitize_text_field( $_GET[ $subscription_action ] ) );

			$order = wc_get_orders( [
					'meta_key'   => '_reepay_subscription_handle',
					'meta_value' => $handle,
				] )[0] ?? null;

			if ( $order && $order->get_customer_id() === get_current_user_id() ) {
				try {
					call_user_func( array( $this, "do_action_$subscription_action" ), $handle );
				} catch ( Exception $exception ) {
					wc_add_notice( $exception->getMessage(), 'error' );
				}
			} else {
				wc_add_notice( 'Permission denied', 'error' );
			}

			wp_redirect( wc_get_endpoint_url( WC_Reepay_My_Account_Subscription_Page::$menu_item_slug, $order->get_id() ) );
			exit;
		}
	}

	private function do_action_cancel_subscription( $handle ) {
		if ( reepay_s()->settings( '_reepay_enable_cancel' ) ) {
			reepay_s()->api()->request( "subscription/{$handle}/cancel", 'POST' );
		}
	}

	private function do_action_uncancel_subscription( $handle ) {
		reepay_s()->api()->request( "subscription/{$handle}/uncancel", 'POST' );
	}

	private function do_action_put_on_hold( $handle ) {
		if ( reepay_s()->settings( '_reepay_enable_on_hold' ) ) {
			reepay_s()->api()->request( "subscription/{$handle}/on_hold", 'POST', [
				"compensation_method" => reepay_s()->settings( '_reepay_on_hold_compensation_method' ),
			] );
		}
	}

	private function do_action_reactivate( $handle ) {
		reepay_s()->api()->request( "subscription/{$handle}/reactivate", 'POST' );
	}

	private function do_action_change_payment_method( $handle ) {
		if ( ! empty( $_GET['token_id'] ) ) {
			$token_id = intval( $_GET['token_id'] );
			$token    = WC_Payment_Tokens::get( $token_id );

			reepay_s()->api()->request( "subscription/{$handle}/pm", 'POST', [
				'source' => $token->get_token(),
			] );
		}
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
}