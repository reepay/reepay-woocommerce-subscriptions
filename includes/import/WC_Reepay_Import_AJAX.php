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
	public static $session_key = 'reepay_subscriptions_import_data';

	/**
	 * @var string
	 */
	public static $ajax_nonce = 'reepay_subscriptions_import_nonce';

	/**
	 * @var array action to function
	 */
	public static $actions = [
		'get_objects'  => 'get_objects',
		'save_objects' => 'save_objects',
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
	 * @return array
	 */
	public static function get_localize_data() {
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

		return [
			'urls'    => $urls,
			'objects' => array_keys( WC_Reepay_Import::$import_objects ),
		];
	}

	/**
	 * AJAX handler to get objects that can be imported
	 */
	public function get_objects() {
		$this->chech_nonce();

		$result            = [];
		$objects_to_import = $this->get_objects_to_import_from_get();

		foreach ( array_keys( WC_Reepay_Import::$import_objects ) as $object ) {
			if ( ! empty( $objects_to_import[ $object ] ) ) {
				$res = call_user_func( "WC_Reepay_Import::get_reepay_$object", $objects_to_import[ $object ] );

				if ( is_wp_error( $res ) ) {
					wp_send_json_error( $res );
				}

				$result[ $object ] = $res;
			}
		}

		$_SESSION[ self::$session_key ] = json_encode( $result );

		wp_send_json_success( $result );
	}

	/**
	 * AJAX handler to save objects from request
	 */
	public function save_objects() {
		$this->chech_nonce();

		$res = [];

		$objects_data = [];

		try {
			$objects_data = json_decode( $_SESSION[ self::$session_key ], true ) ?: [];
		} catch ( Exception $e ) {
		}

		foreach ( array_keys( WC_Reepay_Import::$import_objects ) as $object ) {
			if ( empty( $_POST['selected'][ $object ] ) ) {
				continue;
			}

			//if no data in session
			if ( empty( $objects_data[ $object ] ) ) {
				$objects_data[ $object ] = call_user_func( "WC_Reepay_Import::get_reepay_$object", [ 'all' ] );
			}

			if ( ! empty( $objects_data[ $object ] ) ) {
				$res[ $object ] = call_user_func( "WC_Reepay_Import::import_$object", $objects_data[ $object ], $_POST['selected'][ $object ] );
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

		foreach ( $data as &$arg ) {
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
					'error' => 'User verification error. Reload page and try again',
				]
			);
		}

		return true;
	}
}
