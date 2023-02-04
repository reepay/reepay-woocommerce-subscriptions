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
			'input_type' => 'radio',
			'options' => [
				'all' => 'All',
				'with_active_subscription' => 'Only customers with active subscriptions'
			]
		],
//		'cards' => [
//			'input_type' => 'radio',
//			'options' => [
//				'all' => 'All',
//				'active' => 'Only active cards'
//			]
//		],
//		'subscriptions' => [
//			'input_type' => 'checkbox',
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
	 * @param  string  $options
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

	public static function get_reepay_cards( $options = array() ) {
		$users = get_users( [ 'fields' => [ 'ID' ] ] );

		foreach ( $users as $user ) {
			/** @var WP_User $user */
			$reepay_user_id = rp_get_customer_handle( $user->ID );

			try {
				/**
				 * @see ? https://reference.reepay.com/api/#get-customer
				 **/
				$res = reepay_s()->api()->request(
					'customer/' . $reepay_user_id . '/payment_method'
				);

				if ( is_wp_error( $res ) || empty( $res['cards'] ) ) {
					continue;
				}

				$customer_tokens = WC_Reepay_Import_Helpers::get_customer_tokens( $user->ID );

				foreach ( $res['cards'] as $card ) {
					if ( in_array( $card['id'], $customer_tokens ) ) {
						continue;
					}

					$card_added = WC_Reepay_Import_Helpers::add_card_to_user( $user->ID, $card );
				}

			} catch ( Exception $e ) {

			}
		}

		return true;
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
