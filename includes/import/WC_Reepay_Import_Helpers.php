<?php

class WC_Reepay_Import_Helpers {

	/**
	 * Constructor
	 */
	public function __construct() {
	}

	/**
	 * @param  array  $customer_data  https://reference.reepay.com/api/#the-customer-object
	 */
	public static function create_woo_customer( $customer_data ) {
		$user_id = wp_create_user(
			$customer_data['email'] ?? $customer_data['handle'],
			wp_generate_password( 8, false ),
			$customer_data['email'] ?? ''
		);

		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		self::import_user_data( $user_id, $customer_data );

		wp_new_user_notification( $user_id, null, 'user' );

		return $user_id;
	}

	/**
	 * @param  int    $user_id
	 * @param  array  $customer_data  https://reference.reepay.com/api/#the-customer-object
	 */
	public static function import_user_data( $user_id, $customer_data ) {
		$meta_to_data = [
			"first_name" => $customer_data['first_name'] ?? '',
			"last_name"  => $customer_data['last_name'] ?? '',

			"billing_first_name" => $customer_data['first_name'] ?? '',
			"billing_last_name"  => $customer_data['last_name'] ?? '',
			"billing_company"    => $customer_data['company'] ?? '',
			"billing_email"      => $customer_data['email'] ?? '',
			"billing_address_1"  => $customer_data['address'] ?? '',
			"billing_address_2"  => $customer_data['address2'] ?? '',
			"billing_city"       => $customer_data['city'] ?? '',
			"billing_postcode"   => $customer_data['postal_code'] ?? '',
			"billing_country"    => $customer_data['country'] ?? '',
			"billing_phone"      => $customer_data['phone'] ?? '',

			"shipping_first_name" => $customer_data['first_name'] ?? '',
			"shipping_last_name"  => $customer_data['last_name'] ?? '',
			"shipping_company"    => $customer_data['company'] ?? '',
			"shipping_address_1"  => $customer_data['address'] ?? '',
			"shipping_address_2"  => $customer_data['address2'] ?? '',
			"shipping_city"       => $customer_data['city'] ?? '',
			"shipping_postcode"   => $customer_data['postal_code'] ?? '',
			"shipping_country"    => $customer_data['country'] ?? '',
			"shipping_phone"      => $customer_data['phone'] ?? '',

			"reepay_customer_id" => $customer_data['handle'],
		];

		foreach ( $meta_to_data as $meta_key => $datum ) {
			update_user_meta( $user_id, $meta_key, $datum );
		}

		wp_update_user([
			'ID' => $user_id,
			'user_email' => $customer_data['email'] ?? '',
			'first_name' => $customer_data['first_name'] ?? '',
			'last_name' => $customer_data['last_name'] ?? ''
		]);
	}

	/**
	 * @param  int                   $user_id
	 * @param  array<string, mixed>  $card
	 *
	 * @return bool|WP_Error
	 */
	public static function add_card_to_user( $user_id, $card ) {
		if ( 'ms_' == substr( $card['id'], 0, 3 ) ) {
			$token = new WC_Payment_Token_Reepay_MS();
			$token->set_gateway_id( 'reepay_checkout' );
			$token->set_token( $card['id'] );
			$token->set_user_id( $user_id );
		} else {
			$expiryDate = explode( '-', $card['exp_date'] );

			$token = new WC_Payment_Token_Reepay();
			$token->set_gateway_id( 'reepay_checkout' );
			$token->set_token( $card['id'] );
			$token->set_last4( substr( $card['masked_card'], - 4 ) );
			$token->set_expiry_year( 2000 + $expiryDate[1] );
			$token->set_expiry_month( $expiryDate[0] );
			$token->set_card_type( $card['card_type'] );
			$token->set_user_id( $user_id );
			$token->set_masked_card( $card['masked_card'] );
		}

		$token->save();
		if ( ! $token->get_id() ) {
			return new WP_Error( 'Unable to save bank card - ' . $card['masked_card'] . ', ' . $card['customer'] );
		}

		return true;
	}

	/**
	 * @param  int  $user_id
	 *
	 * @return string[]
	 */
	public static function get_customer_tokens( $user_id ) {
		$tokens    = WC_Payment_Tokens::get_customer_tokens( $user_id ) ?: [];
		$token_ids = [];

		foreach ( $tokens as $token ) {
			$token_ids[] = $token->get_token();
		}

		return $token_ids;
	}

	/**
	 * @param  string  $handle  <order_id>_<product_id>
	 *
	 * @return bool order exists
	 */
	public static function woo_reepay_subscription_exists( $handle ) {
		return ! empty( WC_Reepay_Renewals::get_order_by_subscription_handle( $handle ) );
	}

	/**
	 * @param  array  $subscription  - reepay subscription object @see https://reference.reepay.com/api/#the-subscription-object
	 *
	 * @return bool|WP_Error
	 * @throws WC_Data_Exception
	 */
	public static function import_reepay_subscription( $subscription ) {
		try {
			$plan = $subscription['plan'];
			$plan = reepay_s()->api()->request( "plan/$plan/current" );
		} catch ( Exception $e ) {
			return new WP_Error( 'Plan request error' );
		}

		try {
			$customer = $subscription['customer'];
			$customer = reepay_s()->api()->request( "customer/$customer" );
		} catch ( Exception $e ) {
			return new WP_Error( 'Plan request error' );
		}

		$reepay_to_woo_statuses = [
			'active'      => 'wc-completed',
			'expired'     => 'wc-cancelled',
			'on_hold'     => 'wc-on-hold',
			'pending'     => 'wc-pending',
			'cancelled'   => 'wc-cancelled',
			'reactivated' => 'wc-completed',
		];

		$order = wc_create_order( [
			'status' => $reepay_to_woo_statuses[ $subscription['state'] ] ?? '',
		] );

		//import logic
		$order->set_billing_city( $customer['city'] );
		$order->set_billing_postcode( $customer['postal_code'] );
		$order->set_billing_email( $customer['email'] );
		$order->set_billing_phone( $customer['phone'] );
		$order->set_billing_address_1( $customer['address'] );
		$order->set_billing_address_2( $customer['address2'] );
		$order->set_billing_country( $customer['country'] );
		$order->set_billing_first_name( $customer['first_name'] );
		$order->set_billing_last_name( $customer['last_name'] );
		$order->set_billing_company( $customer['company'] );

		$order->set_payment_method( 'reepay_checkout' );
		$order->set_payment_method_title( 'Reepay Checkout' );
		$order->add_meta_data( '_reepay_state_authorized', 1 );

		$order->add_meta_data( '_reepay_order', $subscription['handle'] );
		$order->add_meta_data( '_reepay_subscription_handle', $subscription['handle'] );
		$order->add_meta_data( '_reepay_imported', 1 );

		$order_item = new WC_Order_Item_Product();
		$order_item->set_name( 'Plan ' . $plan['name'] );
		$order_item->set_quantity( $plan['quantity'] );
		$order_item->set_product_id( 0 );
		$order_item->set_subtotal( $plan['amount'] / 100 );
		$order_item->set_total( $plan['amount'] / 100 );
		$order->add_item( $order_item );

		if ( ! empty( $plan['setup_fee'] ) && $plan['setup_fee'] > 0 ) {
			$order_item = new WC_Order_Item_Fee();
			$order_item->set_name( 'Fee ' . $plan['setup_fee_text'] );
			$order_item->set_amount( $plan['setup_fee'] / 100 );
			$order_item->set_total( $plan['setup_fee'] / 100 );
			$order->add_item( $order_item );
		}

		$order->save();
		$order->calculate_totals();

		return true;
	}
}
