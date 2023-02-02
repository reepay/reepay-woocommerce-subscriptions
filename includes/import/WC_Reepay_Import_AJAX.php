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
		'get_items' => 'get_items',
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

	public function get_items() {
		wp_send_json_success();
	}
}
