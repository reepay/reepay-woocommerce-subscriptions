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
		/**
		 * @see ???
		 **/


		return new WP_Error( 500, 'Method doesn\'t implemented' );
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
				if ( true ) {
					// Обновить подписку
				} else {
					//Создать подписку
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
	 * @param  WP_Error $error
	 * @param  string  $notice
	 */
	function log( $source, $error, $notice ) {
		reepay_s()->log()->log( [
			'source' => $source,
			'message' => $error->get_error_messages()
		] );

		$this->notices = $notice;
	}

	function add_notices() {
		foreach ( $_SESSION[ $this->session_notices_key ] ?? [] as $message ) {
			printf( '<div class="notice notice-error"><p>%1$s</p></div>', esc_html( $message ) );
		}

		$_SESSION[ $this->session_notices_key ] = [];
	}
}
