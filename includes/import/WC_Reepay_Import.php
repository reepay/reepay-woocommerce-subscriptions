<?php

class WC_Reepay_Import {
	/**
	 * @var string
	 */
    public $option_name = 'reepay_import';

    public $menu_slug = 'reepay_import';

	public $import_objects = [ 'users', 'cards', 'subscriptions' ];

	/**
	 * Constructor
	 */
	public function __construct() {
	    new WC_Reepay_Import_Menu($this->option_name, $this->menu_slug, $this->import_objects);

		/*
		 * Start import with saving import settings
		 * Use pre_update for the case when the options have not changed
		 * Also use filter as action, but don't forget to return the value
		 */
		add_filter( 'pre_update_option', [ $this, 'process_import' ], 10, 2 );
	}

	public function process_import( $args, $option ) {
		if($option == $this->option_name) {
            foreach ($this->import_objects as $object) {
	            if ( ! empty( $args[$object] ) && 'yes' == $args[$object] ) {
                    call_user_func(array($this, "process_import_$object"));
	            }
            }
        }

		return $args;
	}

	public function process_import_users( $token = '' ) {
		$params = [
			'from' => '1970-01-01',
			'size' => 100,
		];

		if ( ! empty( $token ) ) {
			$params['next_page_token'] = $token;
		}

		try {
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
				$this->process_import_users();
			}
		}
	}

	public function process_import_cards() {

	}

	public function process_import_subscriptions() {

	}
}
