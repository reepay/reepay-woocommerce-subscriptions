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
	 * @var string[]
	 */
	public $import_objects = [ 'customers', 'cards', 'subscriptions' ];

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

		new WC_Reepay_Import_Menu( $this->option_name, $this->menu_slug, $this->import_objects );

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
			foreach ( $this->import_objects as $object ) {
				if ( ! empty( $args[ $object ] ) && 'yes' == $args[ $object ] ) {
					$res = call_user_func( [ $this, "process_import_$object" ] );

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
				if ( $wp_user = get_user_by( 'email', $customer['email'] ) ) {
					update_user_meta( $wp_user->ID, 'reepay_customer_id', $customer['handle'] );
				} else {
					$wp_user_id = WC_Reepay_Import_Helpers::create_woo_customer( $customer );

					if ( is_wp_error( $wp_user_id ) ) {
						$this->log(
							"WC_Reepay_Import::process_import_customers",
							$wp_user_id,
							"Error with creating wp user - " . $customer['email']
						);
					}

				}
			}

			if ( ! empty( $customers_data['next_page_token'] ) ) {
				return $this->process_import_customers();
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
					} else {
						return true;
					}
				}

				/** Reepay order object
				(
				[handle] => 97_52
				[customer] => customer-1
				[plan] => plan_52-1665423681
				[state] => expired
				[test] => 1
				[quantity] => 1
				[timezone] => Europe/Copenhagen
				[created] => 2022-10-10T20:13:50.806+00:00
				[activated] => 2022-10-10T20:13:50.806+00:00
				[renewing] =>
				[plan_version] => 5
				[start_date] => 2022-10-10T20:13:50.806+00:00
				[grace_duration] => 172800
				[current_period_start] => 2022-10-10T20:13:50.806+00:00
				[next_period_start] => 2022-10-12T20:13:50.806+00:00
				[first_period_start] => 2022-10-10T20:13:50.806+00:00
				[is_cancelled] =>
				[in_trial] =>
				[has_started] => 1
				[renewal_count] => 1
				[expired_date] => 2022-10-12T20:15:00.660+00:00
				[expire_reason] => fixed
				[payment_method_added] => 1
				[failed_invoices] => 0
				[failed_amount] => 0
				[cancelled_invoices] => 0
				[cancelled_amount] => 0
				[pending_invoices] => 0
				[pending_amount] => 0
				[dunning_invoices] => 0
				[dunning_amount] => 0
				[settled_invoices] => 1
				[settled_amount] => 3734
				[refunded_amount] => 0
				[pending_additional_costs] => 0
				[pending_additional_cost_amount] => 0
				[transferred_additional_costs] => 0
				[transferred_additional_cost_amount] => 0
				[pending_credits] => 0
				[pending_credit_amount] => 0
				[transferred_credits] => 0
				[transferred_credit_amount] => 0
				[hosted_page_links] => Array
				(
				[payment_info] => https://checkout.reepay.com/#/subscription/pay/da_DK/8dae4a982d78e087380cebd5517fb67b/97_52
				)

				)
				 */

			/**
			* Reepay plan object
			(
			[name] => 2209-3
			[description] =>
			[vat] => 0
			[amount] => 5000
			[quantity] => 1
			[prepaid] => 1
			[handle] => plan_700-1663856557
			[version] => 2
			[state] => active
			[currency] => DKK
			[created] => 2022-09-22T15:56:23.580+00:00
			[dunning_plan] => dunning_plan_b954
			[partial_period_handling] => bill_prorated
			[include_zero_amount] =>
			[partial_proration_days] => 1
			[fixed_trial_days] => 1
			[amount_incl_vat] =>
			[interval_length] => 6
			[schedule_type] => month_startdate
			[notice_periods] => 1
			[notice_periods_after_current] =>
			[fixation_periods] => 1
			[fixation_periods_full] => 1
			)
			 */
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
