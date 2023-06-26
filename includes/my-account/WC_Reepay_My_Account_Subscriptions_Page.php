<?php

class WC_Reepay_My_Account_Subscriptions_Page {

	public static $menu_item_slug = 'r-subscriptions';

	public function __construct() {
		add_action( 'init', [ $this, 'rewrite_endpoint' ] );
		add_filter( 'woocommerce_account_menu_items', [ $this, 'add_subscriptions_menu_item' ] );
		add_action( 'woocommerce_account_' . self::$menu_item_slug . '_endpoint', [ $this, 'subscriptions_endpoint' ] );
		add_filter( 'woocommerce_endpoint_' . self::$menu_item_slug . '_title', function () {
			return __( 'Subscriptions', 'reepay-subscriptions-for-woocommerce' );
		} );
	}

	public function rewrite_endpoint() {
		add_rewrite_endpoint( self::$menu_item_slug, EP_ROOT | EP_PAGES );

		if ( get_transient( 'woocommerce_reepay_subscriptions_activated' ) ) {
			flush_rewrite_rules();
			delete_transient( 'woocommerce_reepay_subscriptions_activated' );
		}
	}

	public function add_subscriptions_menu_item( $menu_items ) {
		if ( ! empty( $menu_items[ self::$menu_item_slug ] ) ) {
			return $menu_items;
		}

		$menu_items_updated = [];

		foreach ( $menu_items as $key => $menu_item ) {
			$menu_items_updated[ $key ] = $menu_item;

			if ( 'orders' === $key ) {
				$menu_items_updated[ self::$menu_item_slug ] = __( 'Subscriptions', 'reepay-subscriptions-for-woocommerce' );
			}
		}

		return $menu_items_updated;
	}

	public function subscriptions_endpoint() {
		try {
			$reepay_customer_handle = rp_get_customer_handle( get_current_user_id() );

			if ( empty( $reepay_customer_handle ) ) {
				throw new Exception( esc_html__( 'You have no active subscriptions.', 'reepay-subscriptions-for-woocommerce' ) );
			}

			$reepay_subscriptions = reepay_s()->api()->request( "list/subscription?customer=$reepay_customer_handle&size=100" )['content'];

			if ( empty( $reepay_subscriptions ) ) {
				throw new Exception( esc_html__( 'You have no active subscriptions.', 'reepay-subscriptions-for-woocommerce' ) );
			}

			$plans = $this->get_plans_from_subscriptions( $reepay_subscriptions );

			$subscriptions_data = array_map( function ( $reepay_subscription ) use ( $plans ) {
				$subscription_data = [
					'id'                => explode( '_', $reepay_subscription['handle'] )[0],
					'link'              => wc_get_endpoint_url( WC_Reepay_My_Account_Subscription_Page::$menu_item_slug, $reepay_subscription['handle'], wc_get_page_permalink( 'myaccount' ) ),
					'state'             => $reepay_subscription['state'],
					'is_cancelled'      => $reepay_subscription['is_cancelled'],
					'trial_end'         => $reepay_subscription['trial_end'] ?? null,
					'next_period_start' => $reepay_subscription['next_period_start'] ?? null,
					'plan' => $reepay_subscription['plan'],
					'amount'            => '',
					'billing_period'    => ''
				];

				$plan = $plans[ $reepay_subscription['plan'] ] ?? null;

				if ( ! empty( $plan ) ) {
					$subscription_data['amount']         = wc_price( rp_make_initial_amount( $plan['amount'], $plan['currency'] ) );
					$subscription_data['billing_period'] = $this->get_billing_period_for_plan( $plan );
				}

				return $subscription_data;
			}, $reepay_subscriptions );

			reepay()->get_template( 'myaccount/my-subscriptions.php', array(
				'subscriptions' => $subscriptions_data,
				'plans'         => $plans
			) );
		} catch ( Exception $e ) {
			reepay()->get_template( 'myaccount/my-subscriptions-error.php', array(
				'error' => $e->getMessage()
			) );
		}
	}

	/**
	 * @param $subscriptions
	 *
	 * @return array
	 */
	private function get_plans_from_subscriptions( $subscriptions ) {
		static $plans = [];

		foreach ( $subscriptions as $subscription ) {
			$plan_handle = $subscription['plan'];

			if ( empty( $plans[ $plan_handle ] ) ) {
				try {
					$plans[ $plan_handle ] = reepay_s()->api()->request( "plan/{$plan_handle}/current" );
				} catch (Exception $e) {
					continue;
				}
			}
		}

		return $plans;
	}

	/**
	 * @param array $plan_data Reepay plan data
	 *
	 * @return string
	 */
	private function get_billing_period_for_plan( $plan_data ) {
		static $billing_periods = [];

		if ( isset( $billing_periods[ $plan_data['handle'] ] ) ) {
			return $billing_periods[ $plan_data['handle'] ];
		}

		$plan_meta_data = reepay_s()->plan()->get_remote_plan_meta( $plan_data );

		$schedule_type = $plan_meta_data['_reepay_subscription_schedule_type'];
		$schedule_data = $plan_meta_data[ $schedule_type ] ?? $plan_data['interval_length'] ?? array();

		$billing_periods[ $plan_data['handle'] ] = WC_Reepay_Subscription_Plan_Simple::get_billing_plan(
			array(
				'type'      => $schedule_type,
				'type_data' => $schedule_data,
				'interval'  => $plan_data['interval_length']
			),
			true
		);

		return $billing_periods[ $plan_data['handle'] ];
	}
}