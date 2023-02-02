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
	 * @var string
	 */
	public $session_notices_key = 'reepay_import_notices';

	/**
	 * Constructor
	 */
	public function __construct() {
		session_start();

		new WC_Reepay_Import_Menu( $this->option_name, $this->import_objects );

		/*
		 * Start import with saving import settings
		 * Use pre_update for the case when the options have not changed
		 * Also use filter as action, but don't forget to return the value
		 */
		add_filter( 'pre_update_option', [ $this, 'process_import' ], 10, 2 );

		add_action( 'admin_notices', [ $this, 'add_notices' ] );
	}

	public function process_import( $args, $option ) {
		if ( $option == $this->option_name ) {
			__log('!1!', $args, $option);
			foreach ( $this->import_objects as $object ) {
				if ( ! empty( $args[ $object ] ) && 'yes' == $args[ $object ] ) {
					$res = false && call_user_func( [ $this, "process_import_$object" ] );

					if ( is_wp_error( $res ) ) {
						$this->log(
							"WC_Reepay_Import::process_import::process_import_$object",
							$res,
							"Error with $object import: " . $res->get_error_message()
						);
					}
				}
			}

			$_SESSION[ $this->session_notices_key ] = $this->notices;
		}

		return $args;
	}

	/**
	 * @param  string  $token
	 *
	 * @return bool|WP_Error
	 */
	public function process_import_customers( $token = '' ) {
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

					if ( is_wp_error( $wp_user_id ) ) {
						/** @var WP_Error $wp_user_id */

						$name = $customer['email'] ?? $customer['handle'];

						$this->log(
							"WC_Reepay_Import::process_import_customers",
							$wp_user_id,
							"Error with creating wp user - {$name}. " . $wp_user_id->get_error_message()
						);
					}
				}
			}

			if ( ! empty( $customers_data['next_page_token'] ) ) {
				return $this->process_import_customers( $customers_data['next_page_token'] );
			}
		}

		return true;
	}

	public function process_import_cards() {
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

					if ( is_wp_error( $card_added ) ) {
						$this->log(
							"WC_Reepay_Import::process_import_cards",
							$card_added,
							"Error with import customer's card - " . $user->user_email
						);
					}
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
	public function process_import_subscriptions( $token = '' ) {
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

					if ( is_wp_error( $imported ) ) {
						$this->log(
							"WC_Reepay_Import::process_import_subscriptions",
							$imported,
							"Error with import subscription - " . $subscription['handle']
						);
					}
				}
			}

			if ( ! empty( $subscriptions_data['next_page_token'] ) ) {
				return $this->process_import_subscriptions( $subscriptions_data['next_page_token'] );
			}
		}

		return true;
	}

	/**
	 * @param  string  $source
	 * @param  WP_Error  $error
	 * @param  string  $notice
	 */
	function log( $source, $error, $notice = null ) {
		reepay_s()->log()->log( [
			'source'  => $source,
			'message' => $error->get_error_messages()
		] );

		if ( ! empty( $notice ) ) {
			$this->notices[] = $notice;
		}
	}

	function add_notices() {
		foreach ( $_SESSION[ $this->session_notices_key ] ?? [] as $message ) {
			printf( '<div class="notice notice-error"><p>%1$s</p></div>', esc_html( $message ) );
		}

		$_SESSION[ $this->session_notices_key ] = [];
	}
}
