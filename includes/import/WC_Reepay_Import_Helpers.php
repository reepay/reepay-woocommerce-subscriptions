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
			$customer_data['email'],
			wp_generate_password( 8, false ),
			$customer_data['email']
		);

		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		$meta_to_data = [
			"first_name" => $customer_data['first_name'],
			"last_name" => $customer_data['last_name'],

			"billing_first_name" => $customer_data['first_name'],
			"billing_last_name" => $customer_data['last_name'],
			"billing_company"   => $customer_data['company'],
			"billing_email"     => $customer_data['email'],
			"billing_address_1" => $customer_data['address'],
			"billing_address_2" => $customer_data['address2'],
			"billing_city"      => $customer_data['city'],
			"billing_postcode"  => $customer_data['postal_code'],
			"billing_country"   => $customer_data['country'],
//			"billing_state"     => $customer_data[''],
			"billing_phone"     => $customer_data['phone'],

			"shipping_first_name" => $customer_data['first_name'],
			"shipping_last_name"  => $customer_data['last_name'],
			"shipping_company"    => $customer_data['company'],
			"shipping_address_1"  => $customer_data['address'],
			"shipping_address_2"  => $customer_data['address2'],
			"shipping_city"       => $customer_data['city'],
			"shipping_postcode"   => $customer_data['postal_code'],
			"shipping_country"    => $customer_data['country'],
//			"shipping_state"      => $customer_data['']
			"shipping_phone"     => $customer_data['phone'],

			"reepay_customer_id" => $customer_data['handle'],
		];

		foreach ( $meta_to_data as $meta_key => $datum ) {
			update_user_meta( $user_id, $meta_key, $datum );
		}

		return $user_id;
	}
}
