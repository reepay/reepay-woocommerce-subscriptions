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
	public static $import_objects = [];

	/**
	 * Constructor
	 */
	public function __construct() {
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
	 * @param  bool  $debug
	 *
	 * @return array|WP_Error
	 */
	public static function get_reepay_customers( $options = array(), $debug = false ) {
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
				$customer['debug_message'] = '';

				if ( $only_with_active_subscription && 0 === $customer['active_subscriptions'] ) {
					if( ! $debug ) {
						continue;
					}

					$customer['debug'] = true;
					$customer['debug_message'] .= __( 'Active subscription not found', 'reepay-subscriptions-for-woocommerce' ) . ' ';
				}

				if ( empty( rp_get_user_id_by_handle( $customer['handle'] ) ) ) {
					$customers_to_import[ $customer['handle'] ] = $customer;
				} else if( $debug ) {
					$customer['debug'] = true;
					$customer['debug_message'] .= __( 'Already imported', 'reepay-subscriptions-for-woocommerce' ) . ' ';
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

			$create_result = WC_Reepay_Import_Helpers::import_reepay_customer( $customers[ $customer_handle ] );

			$result[ $customer_handle ] = is_int( $create_result ) ? true : $create_result->get_error_message();
		}

		return $result;
	}

	/**
	 * @param  array  $options
	 * @param  bool  $debug
	 *
	 * @return array
	 */
	public static function get_reepay_cards( $options = array(), $debug = false  ) {
		$users = get_users( [ 'fields' => [ 'ID', 'user_email' ] ] );

		$only_active_cards = in_array( 'active', $options );

		$cards_to_import = [];

		add_filter( 'woocommerce_get_customer_payment_tokens_limit', function () {
			return PHP_INT_MAX;
		} );

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
				$card['customer_email'] = $user->user_email;

				if ( ! in_array( $card['id'], $customer_tokens )
				     && ( ! $only_active_cards || 'active' === $card['state'] )
				) {
					$cards_to_import[ $card['id'] ] = $card;
				} elseif ( $debug ) {
					$card['debug']         = true;
					$card['debug_message'] = '';

					if ( in_array( $card['id'], $customer_tokens ) ) {
						$card['debug_message'] = __( 'Already imported', 'reepay-subscriptions-for-woocommerce' ) . '<br> ';
					}

					if ( $only_active_cards && 'active' !== $card['state'] ) {
						$card['debug_message'] = __( 'Wrong card status: ', 'reepay-subscriptions-for-woocommerce' ) . $card['state'] . '<br> ';
					}

					$cards_to_import[ $card['id'] ] = $card;
				}
			}
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

			$wp_user_id = rp_get_user_id_by_handle( $cards[ $card_id ]['customer'] );

			if ( empty( $wp_user_id ) ) {
				$result[ $card_id ] = __( 'User not found', 'reepay-subscriptions-for-woocommerce' );
				continue;
			}

			$card_added = WC_Reepay_Import_Helpers::add_card_to_user( $wp_user_id, $cards[ $card_id ] );

			$result[ $card_id ] = true === $card_added ? true : $card_added->get_error_message();
		}

		return $result;
	}

	/**
	 * @param  array  $statuses
	 * @param  bool  $debug
	 *
	 * @return array|WP_Error
	 */
	public static function get_reepay_subscriptions( $statuses = array(), $debug = false  ) {
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
					$wp_user_id   = rp_get_user_id_by_handle( $subscription['customer'] );
					$wp_user_data = get_userdata( $wp_user_id );

					if ( $wp_user_data ) {
						$subscription['customer_email'] = $wp_user_data->user_email ?: __( 'Email not set', 'reepay-subscriptions-for-woocommerce' );
					}

					$subscriptions_to_import[ $subscription['handle'] ] = $subscription;
				} else if( $debug ) {
					$subscription['debug'] = true;
					$subscription['debug_message'] = '';

					if( WC_Reepay_Import_Helpers::woo_reepay_subscription_exists( $subscription['handle'] ) ) {
						$subscription['debug_message'] .= __( 'Subscription already exists in store', 'reepay-subscriptions-for-woocommerce' ) . '<br> ';
					}

					if ( ! $import_all_statuses ) {
						if ( ! in_array( $subscription['state'], $statuses ) ) {
							$subscription['debug_message'] .= __( 'Wrong status: ', 'reepay-subscriptions-for-woocommerce' ) . $subscription['state'] . '<br> ';
						}

						if ( $import_dunning && $subscription['dunning_invoices'] === 0 ) {
							$subscription['debug_message'] .= __( 'No dunning invoice', 'reepay-subscriptions-for-woocommerce' ) . '<br> ';
						}

						if ( $import_cancelled && ! $subscription['is_cancelled'] ) {
							$subscription['debug_message'] .= __( 'Subscription not canceled', 'reepay-subscriptions-for-woocommerce' ) . '<br> ';
						}
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
		}

		return $result;
	}
}
