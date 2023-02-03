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
		'get_objects' => 'get_objects',
		'save_objects' => 'save_objects'
	];

	/**
	 * Constructor
	 */
	public function __construct() {
		foreach ( self::$actions as $action => $function ) {
			add_action( 'wp_ajax_' . self::$ajax_prefix . '_' . $action, [ $this, $function ] );
		}
	}

	public static function get_localize_data() {
		$nonce = wp_create_nonce(self::$ajax_nonce);
		$ajax_url = admin_url( "admin-ajax.php" );

		$urls = [];

		foreach ( array_keys( self::$actions ) as $action ) {
			$urls[ $action ] = add_query_arg(
				[
					'nonce'  => $nonce,
					'action' => self::$ajax_prefix . '_' .$action,
				],
				$ajax_url
			);
		}

		return [
			'urls' => $urls,
		];
	}

	public function get_objects() {
		$res = [];
		$objects_to_import = $this->get_object_to_import();

		foreach ( array_keys( WC_Reepay_Import::$import_objects ) as $object ) {
			if ( ! empty( $objects_to_import[ $object ] ) ) {
				$res[ $object ] = call_user_func( "WC_Reepay_Import::process_import_$object", $objects_to_import[ $object ] );
			}
		}

		wp_send_json_success($res);
	}

	public function get_object_to_import($data = null) {
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

	public function chech_nonce() {
		if ( ! check_ajax_referer( self::$ajax_prefix, 'nonce', false ) ) {
			wp_send_json_error(
				[
					'error' => 'User verification error. Reload page and try again'
				]
			);
		}

		return true;
	}
}
