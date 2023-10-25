<?php

class WC_Reepay_My_Account_Subscription_Actions {

	private $subscription_actions = array(
		'cancel_subscription',
		'uncancel_subscription',
		'put_on_hold',
		'reactivate',
		'change_payment_method'
	);

	public function __construct() {
		add_action( 'template_redirect', [ $this, 'do_action' ] );
	}

	public function do_action() {
		if ( ! isset( $_GET['reepay_subscriptions_action'] ) ) {
			return;
		}
		
		foreach ( $this->subscription_actions as $subscription_action ) {
			if ( empty( $_GET[ $subscription_action ] ) ) {
				continue;
			}

			$subscription_handle = urlencode( sanitize_text_field( $_GET[ $subscription_action ] ) );

			try {
				$subscription = reepay_s()->api()->request( "subscription/{$subscription_handle}" );
				WC_Reepay_My_Account_Subscription_Page::customer_has_access_to_subscription( $subscription );
				call_user_func( array( $this, "do_action_$subscription_action" ), $subscription_handle );
			} catch ( Exception $exception ) {
				reepay_s()->log()->log(array(
					'source' => 'WC_Reepay_My_Account_Subscription_Page::do_action',
					'action' => $subscription_action,
					'error' => $exception->getMessage()
				));
				
				wc_add_notice( $exception->getMessage(), 'error' );
			}

			wp_redirect( wc_get_endpoint_url( WC_Reepay_My_Account_Subscription_Page::$menu_item_slug, $subscription_handle ) );
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
			reepay_s()->api()->request( "subscription/{$handle}/pm", 'POST', [
				'source' => $_GET['token_id'],
			] );
		}
	}
}