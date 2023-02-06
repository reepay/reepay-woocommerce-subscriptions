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
