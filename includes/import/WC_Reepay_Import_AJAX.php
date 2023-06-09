<?php

class WC_Reepay_Import_AJAX {
	/**
	 * @var string
	 */
	public static $js_object_name = 'reepayImport';

	/**
	 * @var string
	 */
	public static $ajax_prefix = 'reepay_subscriptions_import';

	/**
	 * @var string
	 */
	public static $ajax_nonce = 'reepay_subscriptions_import_nonce';

	/**
	 * @var array action to function
	 */
	public static $actions = [
		'get_objects'       => 'get_objects',
		'get_import_status' => 'get_import_status',
		'save_objects'      => 'save_objects',
	];

	/**
	 * Constructor
	 */
	public function __construct() {
		foreach ( self::$actions as $action => $function ) {
			add_action( 'wp_ajax_' . self::$ajax_prefix . '_' . $action, [ $this, $function ] );
		}
	}

	/**
	 * Prepare data for script
	 *
	 * @param array $additional_data
	 *
	 * @return array
	 */
	public static function get_localize_data( $data = []) {
		$nonce    = wp_create_nonce( self::$ajax_nonce );
		$ajax_url = admin_url( "admin-ajax.php" );

		$urls = [];

		foreach ( array_keys( self::$actions ) as $action ) {
			$urls[ $action ] = add_query_arg(
				[
					'nonce'  => $nonce,
					'action' => self::$ajax_prefix . '_' . $action,
				],
				$ajax_url
			);
		}

		$data['urls'] = $urls;
		$data['objects'] = array_keys( WC_Reepay_Import::$import_objects );

		return $data;
	}

	/**
	 * AJAX handler to get objects that can be imported
	 */
	public function get_objects() {
		$this->chech_nonce();

		$result            = [];
		$objects_to_import = $this->get_objects_to_import_from_get();
		$debug = ! empty( $_GET[ WC_Reepay_Import::$option_name ]['debug'] ) && $_GET[ WC_Reepay_Import::$option_name ]['debug'] === 'on';

		foreach ( array_keys( WC_Reepay_Import::$import_objects ) as $object ) {
			if ( ! empty( $objects_to_import[ $object ] ) ) {
				$res = call_user_func( "WC_Reepay_Import::get_reepay_$object", $objects_to_import[ $object ], $debug );

				if ( is_wp_error( $res ) ) {
					wp_send_json_error( $res );
				}

				$result[ $object ] = $res;
			}
		}

		wp_send_json_success( $result );
	}

	/**
	 * AJAX handler to save objects from request
	 */
	public function save_objects() {
		$this->chech_nonce();

		$res = [];

		foreach ( array_keys( WC_Reepay_Import::$import_objects ) as $object ) {
			if ( empty( $_POST['selected'][ $object ] ) ) {
				continue;
			}

			$objects_data = call_user_func( "WC_Reepay_Import::get_reepay_$object", [ 'all' ] );

			if ( ! empty( $objects_data[ $object ] ) ) {
				$res[ $object ] = call_user_func( "WC_Reepay_Import::import_$object", $objects_data, $_POST['selected'][ $object ] );
			}
		}

		wp_send_json_success( $res );
	}

	/**
	 * @param  array|null  $data
	 *
	 * @return array
	 */
	public function get_objects_to_import_from_get( $data = null ) {
		if ( is_null( $data ) ) {
			$data = $_GET[ WC_Reepay_Import::$option_name ] ?? [];
		}

		foreach ( $data as $key => &$arg ) {
			if ( ! in_array( $key, array_keys( WC_Reepay_Import::$import_objects ) ) ) {
				unset( $data[ $key ] );
				continue;
			}

			if ( is_array( $arg ) ) {
				$arg = array_keys( $arg );

				if ( in_array( 'all', $arg ) ) {
					$arg = [ 'all' ];
				}
			} else {
				$arg = [ 'all' ];
			}
		}

		return $data;
	}

	/**
	 * Prevent AJAX request if nonce fails check
	 *
	 * @return true
	 */
	public function chech_nonce() {
		if ( ! check_ajax_referer( self::$ajax_nonce, 'nonce', false ) ) {
			wp_send_json_error(
				[
					'error' => __( 'User verification error. Reload page and try again', 'reepay-subscriptions-for-woocommerce' ),
				]
			);
		}

		return true;
	}
}
