<?php

class WC_Reepay_Import {
	/**
	 * @var string
	 */
	public $option_name = 'reepay_import';

	/**
	 * @var string
	 */
	public $menu_slug = 'reepay_import';

	/**
	 * @var array
	 */
	public $import_objects = [
		'users' => [
			'input_type' => 'radio',
			'options' => [
				'all' => 'All',
				'with_active_subscription' => 'Only customers with active subscriptions'
			]
		],
		'cards' => [
			'input_type' => 'radio',
			'options' => [
				'all' => 'All',
				'active' => 'Only active cards'
			]
		],
		'subscriptions' => [
			'input_type' => 'checkbox',
			'options' => [
				'all' => 'All',
				'active' => 'Active',
				'on_hold' => 'On hold',
				'dunning' => 'Dunning',
				'canceled' => 'Cancelled',
				'expired' => 'Expired'
			]
		]
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

		new WC_Reepay_Import_Menu( $this->option_name, $this->import_objects );
		new WC_Reepay_Import_AJAX();
	}

	/**
	 * @param  string  $token
	 *
	 * @return bool|WP_Error
	 */
	public static function process_import_customers( $options = array(), $token = '' ) {
		$params = [
			'from' => '1970-01-01',
			'size' => 100,
		];

		if ( ! empty( $token ) ) {
			$params['next_page_token'] = $token;
		}

		try {
			/**
			 * @see https://reference.reepay.com/api/#get-list-of-customers
			 **/
			$customers_data = reepay_s()->api()->request( "list/customer?" . http_build_query( $params ) );
		} catch ( Exception $e ) {
			return new WP_Error( 400, $e->getMessage() );
		}


		if ( ! empty( $customers_data ) && ! empty( $customers_data['content'] ) ) {
			$customers = $customers_data['content'];

			foreach ( $customers as $customer ) {
				$wp_user_id = rp_get_userid_by_handle( $customer['handle'] );

				if ( false === get_user_by( 'id', $wp_user_id ) ) {
					$wp_user_id = WC_Reepay_Import_Helpers::create_woo_customer( $customer );
				}
			}

			if ( ! empty( $customers_data['next_page_token'] ) ) {
				return self::process_import_customers( $options, $customers_data['next_page_token'] );
			}
		}

		return true;
	}

	public static function process_import_cards( $options = array() ) {
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
	public static function process_import_subscriptions( $options = array(),  $token = '' ) {
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
				return self::process_import_subscriptions( $options,  $subscriptions_data['next_page_token'] );
			}
		}

		return true;
	}
}
