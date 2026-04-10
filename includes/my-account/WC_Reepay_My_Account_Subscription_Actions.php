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

		// Security: Check user is logged in
		if ( ! is_user_logged_in() ) {
			wc_add_notice( __( 'You must be logged in to perform this action.', 'reepay-subscriptions-for-woocommerce' ), 'error' );
			return;
		}

		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'reepay_subscription_action' ) ) {
			wc_add_notice( __( 'Security check failed. Please try again.', 'reepay-subscriptions-for-woocommerce' ), 'error' );
			return;
		}

		foreach ( $this->subscription_actions as $subscription_action ) {
			if ( empty( $_GET[ $subscription_action ] ) ) {
				continue;
			}

			$subscription_handle = sanitize_text_field( $_GET[ $subscription_action ] );

			// Security: Validate subscription handle format
			if ( ! preg_match( '/^[a-zA-Z0-9_-]{1,64}$/', $subscription_handle ) ) {
				wc_add_notice( __( 'Invalid subscription handle format.', 'reepay-subscriptions-for-woocommerce' ), 'error' );
				wp_redirect( wc_get_endpoint_url( WC_Reepay_My_Account_Subscription_Page::$menu_item_slug ) );
				exit();
			}

			try {
				$subscription = reepay_s()->api()->request( "subscription/{$subscription_handle}" );

				// Security: Verify subscription ownership
				WC_Reepay_My_Account_Subscription_Page::customer_has_access_to_subscription( $subscription );

				// Security: Use switch instead of call_user_func for better security
				switch ( $subscription_action ) {
					case 'cancel_subscription':
						$this->do_action_cancel_subscription( $subscription_handle );
						break;
					case 'uncancel_subscription':
						$this->do_action_uncancel_subscription( $subscription_handle );
						break;
					case 'put_on_hold':
						$this->do_action_put_on_hold( $subscription_handle );
						break;
					case 'reactivate':
						$this->do_action_reactivate( $subscription_handle );
						break;
					case 'change_payment_method':
						$this->do_action_change_payment_method( $subscription_handle );
						break;
					default:
						throw new Exception( __( 'Invalid subscription action.', 'reepay-subscriptions-for-woocommerce' ) );
				}
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
			$token_id = sanitize_text_field( wp_unslash( $_GET['token_id'] ) );

			// Security: Validate token format (alphanumeric, dashes, underscores only)
			if ( ! preg_match( '/^[a-zA-Z0-9_-]{10,64}$/', $token_id ) ) {
				throw new Exception( __( 'Invalid token format.', 'reepay-subscriptions-for-woocommerce' ) );
			}

			// Security: Verify token belongs to current user by matching against their saved WC tokens.
			// token_id is a Reepay card ID (ca_...) stored as the WC token value — not a WC token numeric ID.
			$user_tokens = WC_Payment_Tokens::get_customer_tokens( get_current_user_id() );
			$token_owned = false;
			foreach ( $user_tokens as $user_token ) {
				if ( $user_token->get_token() === $token_id ) {
					$token_owned = true;
					break;
				}
			}

			if ( ! $token_owned ) {
				throw new Exception( __( 'Invalid payment method.', 'reepay-subscriptions-for-woocommerce' ) );
			}

			reepay_s()->api()->request( "subscription/{$handle}/pm", 'POST', [
				'source' => $token_id,
			] );
		}
	}
}