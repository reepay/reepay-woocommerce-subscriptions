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
		add_action( 'woocommerce_account_' . self::$menu_item_slug . '_endpoint', [ $this, 'subscription_endpoint' ], 5, 1 );
		add_action( 'template_redirect', [ $this, 'do_action' ] );
	}

	public function subscriptions_endpoint( $current_page = 1 ) {
		if ( class_exists( 'WC_Subscriptions' ) ) {
			$this->add_reepay_subscriptions_to_woo_subscriptions = true;

			return;
		}

		$all_subscriptions = apply_filters( 'wcs_get_users_subscriptions', [], get_current_user_id() );
		$current_page      = empty( $current_page ) ? 1 : absint( $current_page );
		$posts_per_page    = get_option( 'posts_per_page' );
		$max_num_pages     = ceil( count( $all_subscriptions ) / $posts_per_page );
		$subscriptions     = array_slice( $all_subscriptions, ( $current_page - 1 ) * $posts_per_page, $posts_per_page );


		wc_get_template(
			'myaccount/my-subscriptions.php',
			array(
				'subscriptions' => $subscriptions,
				'current_page'  => $current_page,
				'max_num_pages' => $max_num_pages,
				'paginate'      => true,
			),
			'',
			reepay_s()->settings( 'plugin_path' ) . 'templates/'
		);
	}

	public function do_action() {
		foreach ( $this->subscription_actions as $subscription_action ) {
			if ( empty( $_GET[ $subscription_action ] ) ) {
				continue;
			}

			$handle = urlencode( sanitize_text_field( $_GET[$subscription_action] ) );

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
		if( ! empty( $_GET['token_id'] ) ) {
			$token_id = intval( $_GET['token_id'] );
			$token    = WC_Payment_Tokens::get( $token_id );

			reepay_s()->api()->request( "subscription/{$handle}/pm", 'POST', [
				'source' => $token->get_token(),
			] );
		}
	}
}