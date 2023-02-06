<?php

class WC_Reepay_Import {
	/**
	 * @var string
	 */
	public static $option_name = 'reepay_import';

	/**
	 * @var string
	 */
	public $menu_slug = 'reepay_import';

	/**
	 * @var array
	 */
	public static $import_objects = [
		'customers' => [
			'options' => [
				'all' => 'All',
				'with_active_subscription' => 'Only customers with active subscriptions'
			]
		],
		'cards' => [
			'options' => [
				'all' => 'All',
				'active' => 'Only active cards'
			]
		],
//		'subscriptions' => [
//			'options' => [
//				'all' => 'All',
//				'active' => 'Active',
//				'on_hold' => 'On hold',
//				'dunning' => 'Dunning',
//				'canceled' => 'Cancelled',
//				'expired' => 'Expired'
//			]
//		]
	];

	/**
	 * @var string[]
	 */
	public $notices = [];

	/**
	 * Constructor
	 */
	public function __construct() {
		session_start();

		new WC_Reepay_Import_Menu();
		new WC_Reepay_Import_AJAX();
	}

	/**
	 * @param  array  $options
	 *
	 * @return array|WP_Error
	 */
	public static function get_reepay_customers( $options = array() ) {
		$params = [
			'from' => '1970-01-01',
			'size' => 100,
		];

		if ( ! empty( $token ) ) {
			$params['next_page_token'] = $token;
		}

		$only_with_active_subscription = in_array( 'with_active_subscription', $options );

		$customers_to_import = [];

		$customers_data['next_page_token'] = true;
		while ( ! empty( $customers_data['next_page_token'] ) ) {
			try {
				/**
				 * @see https://reference.reepay.com/api/#get-list-of-customers
				 **/
				$customers_data = reepay_s()->api()->request( "list/customer?" . http_build_query( $params ) );
			} catch ( Exception $e ) {
				return new WP_Error( 400, $e->getMessage() );
			}

			if ( empty( $customers_data ) || empty( $customers_data['content'] ) ) {
				break;
			}

			foreach ( $customers_data['content'] as $customer ) {
				if ( $only_with_active_subscription && 0 === $customer['active_subscriptions'] ) {
					continue;
				}

				$wp_user_id = rp_get_userid_by_handle( $customer['handle'] );

				if ( false === get_user_by( 'id', $wp_user_id ) ) {
					$customers_to_import[ $customer['handle'] ] = $customer;
				}
			}
		}

		return $customers_to_import;
	}

	/**
	 * @param array $customers array of reepay customers @see https://reference.reepay.com/api/#the-customer-object
	 * @param array $selected_customer_handles handles of customers to import from $customers array
	 *
	 * @return array|array[]
	 */
	public static function import_customers( $customers, $selected_customer_handles ) {
		$result = [];

		foreach ( $selected_customer_handles as $customer_handle ) {
			if ( empty( $customers[ $customer_handle ] ) ) {
				continue;
			}

			$create_result = WC_Reepay_Import_Helpers::create_woo_customer( $customers[ $customer_handle ] );

			$result[ $customer_handle ] = is_int( $create_result ) ? true : $create_result->get_error_message();
		}

		return $result;
	}

	/**
	 * @param  array  $options
	 *
	 * @return array
	 */
	public static function get_reepay_cards( $options = array() ) {
		$users = get_users( [ 'fields' => [ 'ID', 'user_email' ] ] );

		$only_active_cards = in_array( 'active', $options );

		$cards_to_import = [];

		foreach ( $users as $user ) {
			$reepay_user_id = rp_get_customer_handle( $user->ID );

			try {
				/**
				 * @see https://reference.reepay.com/api/#get-list-of-payment-methods
				 **/
				$res = reepay_s()->api()->request(
					'customer/' . $reepay_user_id . '/payment_method'
				);
			} catch ( Exception $e ) {
				continue;
			}

			if ( is_wp_error( $res ) || empty( $res['cards'] ) ) {
				continue;
			}

			$customer_tokens = WC_Reepay_Import_Helpers::get_customer_tokens( $user->ID );

			foreach ( $res['cards'] as $card ) {
				if ( in_array( $card['id'], $customer_tokens )
				     || ( $only_active_cards && 'active' !== $card['state'] )
				) {
					continue;
				}

				$card['customer_email'] = $user->user_email;

				$cards_to_import[ $card['id'] ] = $card;
			}

			return $cards_to_import;
		}

		return $cards_to_import;
	}

	/**
	 * @param array $cards array of cards @see https://reference.reepay.com/api/#get-list-of-payment-methods
	 * @param array $selected_card_ids ids of cards to import from $cards array
	 *
	 * @return array|array[]
	 */
	public static function import_cards( $cards, $selected_card_ids ) {
		$result = [];

		foreach ( $selected_card_ids as $card_id ) {
			if ( empty( $cards[ $card_id ] ) ) {
				continue;
			}

			$wp_user_id = rp_get_userid_by_handle(  $cards[ $card_id ]['customer'] );

			if ( empty( $wp_user_id ) ) {
				$result[ $card_id ] = "User not found";
				continue;
			}

			$card_added = WC_Reepay_Import_Helpers::add_card_to_user( $wp_user_id,  $cards[ $card_id ] );

			$result[ $card_id ] = true === $card_added ? true : $card_added->get_error_message();
		}

		return $result;
	}

	/**
	 * @param  string  $token
	 *
	 * @return bool|WP_Error
	 * @throws WC_Data_Exception
	 */
	public static function get_reepay_subscriptions( $options = array(),  $token = '' ) {
		$params = [
			'from' => '1970-01-01',
			'size' => 100,
		];

		if ( ! empty( $token ) ) {
			$params['next_page_token'] = $token;
		}

		try {
			/**
			 * @see https://reference.reepay.com/api/#get-list-of-subscriptions
			 **/
			$subscriptions_data = reepay_s()->api()->request( "list/subscription?" . http_build_query( $params ) );
		} catch ( Exception $e ) {
			return new WP_Error( 400, $e->getMessage() );
		}

		if ( ! empty( $subscriptions_data ) && ! empty( $subscriptions_data['content'] ) ) {
			$subscriptions = $subscriptions_data['content'];

			foreach ( $subscriptions as $subscription ) {
				if ( ! WC_Reepay_Import_Helpers::woo_reepay_subscription_exists( $subscription['handle'] ) ) {
					$imported = WC_Reepay_Import_Helpers::import_reepay_subscription( $subscription );
				}
			}

			if ( ! empty( $subscriptions_data['next_page_token'] ) ) {
				return self::get_reepay_subscriptions( $options,  $subscriptions_data['next_page_token'] );
			}
		}

		return true;
	}
}

?>

array(3){
[
0
]=>string(35)"ca_805a7f005f8e6d5105aef16769efdc12"[
1
]=>string(35)"ca_d19670f1a4b42aa0b29c2650dbe2e9b3"[
2
]=>string(35)"ca_d8c06b766312bda0b231a8788d7bf615"
}{
"success": true,
"data": {
"cards": {
"ca_c68cdc5cb3c80ccca961ed7f080d86f3": {
"id": "ca_c68cdc5cb3c80ccca961ed7f080d86f3",
"state": "active",
"customer": "customer-1",
"reference": "cs_8fb3e1e18a697d44d01db10968c6411b",
"created": "2023-01-09T10:24:10.921+00:00",
"fingerprint": "cst_788db4edabee69ccc0b94ac09c0af38b",
"gw_ref": "ce00badc39eedc546441998a7c374208",
"card_type": "visa",
"transaction_card_type": "visa",
"exp_date": "01-33",
"masked_card": "411111XXXXXX1111",
"last_success": "2023-01-31T11:29:07.383+00:00",
"card_country": "US",
"customer_email": "dimaspolohov@yandex.ru"
},
"ca_e5886ce6a4bee7044866f32bbd688462": {
"id": "ca_e5886ce6a4bee7044866f32bbd688462",
"state": "active",
"customer": "customer-1",
"reference": "cs_7e12c60a154e86c709c296b6ec36a9e6",
"created": "2023-01-11T12:22:17.529+00:00",
"fingerprint": "cst_788db4edabee69ccc0b94ac09c0af38b",
"gw_ref": "d5c11ff3f4373306cfabaa67a62b5614",
"card_type": "visa",
"transaction_card_type": "visa",
"exp_date": "01-23",
"masked_card": "411111XXXXXX1111",
"last_success": "2023-01-30T11:01:13.557+00:00",
"card_country": "US",
"customer_email": "dimaspolohov@yandex.ru"
},
"ca_977ec914f2a36dc04a23a3aa4c885f10": {
"id": "ca_977ec914f2a36dc04a23a3aa4c885f10",
"state": "active",
"customer": "customer-1",
"reference": "cs_aaf72b2942a52386c6c14eb3f0b674a1",
"created": "2023-01-23T14:35:05.716+00:00",
"fingerprint": "cst_788db4edabee69ccc0b94ac09c0af38b",
"gw_ref": "7b1c4567c8c778b134e76769e013f042",
"card_type": "visa",
"transaction_card_type": "visa",
"exp_date": "12-23",
"masked_card": "411111XXXXXX1111",
"last_success": "2023-01-30T11:26:54.438+00:00",
"card_country": "US",
"customer_email": "dimaspolohov@yandex.ru"
},
"ca_75f17914145d87e7dde40131b4fdd561": {
"id": "ca_75f17914145d87e7dde40131b4fdd561",
"state": "active",
"customer": "customer-1",
"reference": "cs_91bad8df596742bcc7c4794644135a4c",
"created": "2023-01-30T08:34:35.892+00:00",
"fingerprint": "cst_788db4edabee69ccc0b94ac09c0af38b",
"gw_ref": "0cfebd85f28739c474f954830c4a588f",
"card_type": "visa",
"transaction_card_type": "visa",
"exp_date": "12-23",
"masked_card": "411111XXXXXX1111",
"card_country": "US",
"customer_email": "dimaspolohov@yandex.ru"
},
"ca_9261ae7af5b9e3779ff8e4769887dfde": {
"id": "ca_9261ae7af5b9e3779ff8e4769887dfde",
"state": "active",
"customer": "customer-1",
"reference": "cs_4a5ba3eefcf426c67f1cc29313a2d7ee",
"created": "2023-01-30T08:59:16.300+00:00",
"fingerprint": "cst_788db4edabee69ccc0b94ac09c0af38b",
"gw_ref": "e3f926aee84d18d1c47bd8e3e0eb6955",
"card_type": "visa",
"transaction_card_type": "visa",
"exp_date": "12-23",
"masked_card": "411111XXXXXX1111",
"card_country": "US",
"customer_email": "dimaspolohov@yandex.ru"
},
"ca_927293a4200305f9e378a79bda015c07": {
"id": "ca_927293a4200305f9e378a79bda015c07",
"state": "active",
"customer": "customer-1",
"reference": "cs_7ad57e4687c86a80cbea64a4cb297ad7",
"created": "2023-02-06T09:43:20.334+00:00",
"fingerprint": "cst_788db4edabee69ccc0b94ac09c0af38b",
"gw_ref": "f7d1e34a28e62c8f8f016141de4d42e9",
"card_type": "visa",
"transaction_card_type": "visa",
"exp_date": "12-34",
"masked_card": "411111XXXXXX1111",
"card_country": "US",
"customer_email": "dimaspolohov@yandex.ru"
}
}
}
}
