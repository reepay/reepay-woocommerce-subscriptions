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

	public static $session_key_last_imported = 'reepay_subscriptions_import_last_imported';

	/**
	 * @var array
	 */
	public static $import_objects = [];

	/**
	 * Constructor
	 */
	public function __construct() {
		session_start();

		add_action( 'reepay_subscriptions_init', [ $this, 'init'] );
	}

	public function init() {
		self::$import_objects = [
			'customers'     => [
				'label'   => __( 'customers', 'reepay-subscriptions-for-woocommerce' ),
				'options' => [
					'all'                      => __( 'All', 'reepay-subscriptions-for-woocommerce' ),
					'with_active_subscription' => __( 'Only customers with active subscriptions', 'reepay-subscriptions-for-woocommerce' ),
				],
			],
			'cards'         => [
				'label'   => __( 'cards', 'reepay-subscriptions-for-woocommerce' ),
				'options' => [
					'all'    => __( 'All', 'reepay-subscriptions-for-woocommerce' ),
					'active' => __( 'Only active cards', 'reepay-subscriptions-for-woocommerce' ),
				],
			],
			'subscriptions' => [
				'label'   => __( 'subscriptions', 'reepay-subscriptions-for-woocommerce' ),
				'options' => [
					'all'       => __( 'All', 'reepay-subscriptions-for-woocommerce' ),
					'active'    => __( 'Active', 'reepay-subscriptions-for-woocommerce' ),
					'on_hold'   => __( 'On hold', 'reepay-subscriptions-for-woocommerce' ),
					'pending'   => __( 'Pending', 'reepay-subscriptions-for-woocommerce' ),
					'dunning'   => __( 'Dunning', 'reepay-subscriptions-for-woocommerce' ),
					'cancelled' => __( 'Cancelled', 'reepay-subscriptions-for-woocommerce' ),
					'expired'   => __( 'Expired', 'reepay-subscriptions-for-woocommerce' ),
				],
			],
		];

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

			$params['next_page_token'] = $customers_data['next_page_token'] ?? '';
		}

		return $customers_to_import;
	}

	/**
	 * @param  array  $customers                  array of reepay customers @see https://reference.reepay.com/api/#the-customer-object
	 * @param  array  $selected_customer_handles  handles of customers to import from $customers array
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

			self::set_last_imported('customers', $customer_handle, $result[ $customer_handle ] );
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
	 * @param  array  $cards              array of cards @see https://reference.reepay.com/api/#get-list-of-payment-methods
	 * @param  array  $selected_card_ids  ids of cards to import from $cards array
	 *
	 * @return array
	 */
	public static function import_cards( $cards, $selected_card_ids ) {
		$result = [];

		foreach ( $selected_card_ids as $card_id ) {
			if ( empty( $cards[ $card_id ] ) ) {
				continue;
			}

			$wp_user_id = rp_get_userid_by_handle( $cards[ $card_id ]['customer'] );

			if ( empty( $wp_user_id ) ) {
				$result[ $card_id ] = __( 'User not found', 'reepay-subscriptions-for-woocommerce' );
				continue;
			}

			$card_added = WC_Reepay_Import_Helpers::add_card_to_user( $wp_user_id, $cards[ $card_id ] );

			$result[ $card_id ] = true === $card_added ? true : $card_added->get_error_message();

			self::set_last_imported('cards', $card_id, $result[ $card_id ] );
		}

		return $result;
	}

	/**
	 * @param  array  $statuses
	 *
	 * @return array|WP_Error
	 */
	public static function get_reepay_subscriptions( $statuses = array() ) {
		$params = [
			'from' => '1970-01-01',
			'size' => 100,
		];

		$import_all_statuses = in_array( 'all', $statuses );
		$import_dunning      = in_array( 'dunning', $statuses );
		$import_cancelled    = in_array( 'cancelled', $statuses );

		$subscriptions_to_import = [];

		$subscriptions_data['next_page_token'] = true;
		while ( ! empty( $subscriptions_data['next_page_token'] ) ) {
			try {
				/**
				 * @see https://reference.reepay.com/api/#get-list-of-subscriptions
				 **/
				$subscriptions_data = reepay_s()->api()->request( "list/subscription?" . http_build_query( $params ) );
			} catch ( Exception $e ) {
				return new WP_Error( 400, $e->getMessage() );
			}

			if ( empty( $subscriptions_data ) || empty( $subscriptions_data['content'] ) ) {
				break;
			}

			foreach ( $subscriptions_data['content'] as $subscription ) {
				if ( ! WC_Reepay_Import_Helpers::woo_reepay_subscription_exists( $subscription['handle'] )
				     && ( $import_all_statuses
				          || in_array( $subscription['state'], $statuses )
				          || $import_dunning && $subscription['dunning_invoices'] !== 0
				          || $import_cancelled && $subscription['is_cancelled'] )

				) {
					$wp_user_id   = rp_get_userid_by_handle( $subscription['customer'] );
					$wp_user_data = get_userdata( $wp_user_id );

					if ( $wp_user_data ) {
						$subscription['customer_email'] = $wp_user_data->user_email ?: __( 'Email not set', 'reepay-subscriptions-for-woocommerce' );
					}

					$subscriptions_to_import[ $subscription['handle'] ] = $subscription;
				}
			}

			$params['next_page_token'] = $subscriptions_data['next_page_token'] ?? '';
		}

		return $subscriptions_to_import;
	}

	/**
	 * @param  array  $subscriptions                  array of reepay subscriptions @see https://reference.reepay.com/api/#the-subscription-object
	 * @param  array  $selected_subscription_handles  handles of subscriptions to import from $subscriptions array
	 *
	 * @return array
	 */
	public static function import_subscriptions( $subscriptions, $selected_subscription_handles ) {
		$result = [];

		foreach ( $selected_subscription_handles as $subscription_handle ) {
			if ( empty( $subscriptions[ $subscription_handle ] )
			     || WC_Reepay_Import_Helpers::woo_reepay_subscription_exists( $subscription_handle )
			) {
				continue;
			}

			try {
				$create_result = WC_Reepay_Import_Helpers::import_reepay_subscription( $subscriptions[ $subscription_handle ] );
			} catch ( Exception $e ) {
				$create_result = new WP_Error( __( 'Import error', 'reepay-subscriptions-for-woocommerce' ) );
			}

			$result[ $subscription_handle ] = true === $create_result ? true : $create_result->get_error_message();

			self::set_last_imported('subscriptions', $subscription_handle, $result[ $subscription_handle ] );
		}

		return $result;
	}

	public static function set_last_imported( $object, $handle, $message ) {
		if ( ! in_array( $object, array_keys( self::$import_objects ) ) ) {
			return false;
		}

		if ( ! is_array( $_SESSION[ self::$session_key_last_imported ] ) ) {
			$_SESSION[ self::$session_key_last_imported ] = [];
		}

		try {
			$_SESSION[ self::$session_key_last_imported ][] =
				[
					'object'  => $object,
					'handle'  => $handle,
					'message' => $message,
				];
		} catch (Exception $e) {
			return false;
		}

		return true;
	}

	public static function get_last_imported( $clean = true ) {
		$data = $_SESSION[ self::$session_key_last_imported ] ?? false;

		if ( $clean ) {
			self::clean_last_imported();
		}

		return $data;
	}

	public static function clean_last_imported() {
		unset( $_SESSION[ self::$session_key_last_imported ] );
	}
}
