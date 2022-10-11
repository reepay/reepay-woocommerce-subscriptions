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
	public $import_objects = [ 'users', 'cards', 'subscriptions' ];

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
			$notices = [];

			foreach ( $this->import_objects as $object ) {
				if ( ! empty( $args[ $object ] ) && 'yes' == $args[ $object ] ) {
					$res = call_user_func( [ $this, "process_import_$object" ] );

					if ( is_wp_error( $res ) ) {
						reepay_s()->log()->log( [
							'source'  => "WC_Reepay_Import::process_import::process_import_$object",
							'message' => $res->get_error_messages()
						] );

						$notices[] = "Error with $object import: " . $res->get_error_message();
					}
				}
			}

			$_SESSION[ $this->session_notices_key ] = $notices;
		}

		return $args;
	}

	/**
	 * @param  string  $token
	 *
	 * @return bool|WP_Error
	 */
	public function process_import_users( $token = '' ) {
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
			$users_data = reepay_s()->api()->request( "list/customer?" . http_build_query( $params ) );
		} catch ( Exception $e ) {
			return new WP_Error( 400, $e->getMessage() );
		}


		if ( ! empty( $users_data ) && ! empty( $users_data['content'] ) ) {
			$users = $users_data['content'];

			foreach ( $users as $user ) {
				if ( $wp_user = get_user_by( 'email', $user['email'] ) ) {
					$wp_user_data = $wp_user->data;
					if ( ! empty( get_user_meta( $wp_user_data->ID, 'reepay_customer_id', true ) ) ) {
						// Что если handel не совпадает?
						continue;
					} else {
						update_user_meta( $wp_user_data->ID, 'reepay_customer_id', $user['handle'] );
					}
				} else {
					//Создать юзера
				}
			}

			if ( ! empty( $users_data['next_page_token'] ) ) {
				return $this->process_import_users();
			}
		}

		return true;
	}

	public function process_import_cards() {
		return new WP_Error( 500, 'Method doesn\'t implemented' );
	}

	public function process_import_subscriptions() {
		return new WP_Error( 500, 'Method doesn\'t implemented' );
	}

	function add_notices() {
		foreach ( $_SESSION[ $this->session_notices_key ] ?? [] as $message ) {
			printf( '<div class="notice notice-error"><p>%1$s</p></div>', esc_html( $message ) );
		}

		$_SESSION[ $this->session_notices_key ] = [];
	}
}
