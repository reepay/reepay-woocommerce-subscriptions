<?php

class WC_Reepay_Subscription_Admin_Notice {

	/**
	 * Stores notices.
	 *
	 * @var array
	 */
	private static $notices = array();

	private static $activation_notices = array();


	/**
	 * Constructor
	 */
	public function __construct() {
		self::$notices            = get_option( 'reepay_admin_notices', array() );
		self::$activation_notices = get_option( 'reepay_admin_activation_notices', array() );
		add_action( 'post_updated_messages', array( $this, 'show_editor_message' ) );
		add_action( 'admin_notices', array( $this, 'show_activation_message' ) );
		add_filter( 'woocommerce_reepay_check_payment', array( $this, 'show_thankyou_message' ), 10, 2 );

	}

	/**
	 * Show a notice.
	 *
	 * @param string $name Notice name.
	 * @param bool $force_save Force saving inside this method instead of at the 'shutdown'.
	 */
	public static function add_activation_notice( $notice ) {
		self::$activation_notices = array_unique( array_merge( self::$activation_notices, array( $notice ) ) );

		update_option( 'reepay_admin_activation_notices', self::$activation_notices );
	}


	/**
	 * Show a notice.
	 *
	 * @param string $name Notice name.
	 * @param bool $force_save Force saving inside this method instead of at the 'shutdown'.
	 */
	public static function add_notice( $notice ) {
		self::$notices = array_unique( array_merge( self::$notices, array( $notice ) ) );

		self::store_notices();
	}

	/**
	 * Add a frontend notice.
	 *
	 * @param string $name Notice name.
	 * @param bool $force_save Force saving inside this method instead of at the 'shutdown'.
	 */
	public static function add_frontend_notice( $notice, $order_id ) {
		self::store_frontend_notices( $notice, $order_id );
	}

	/**
	 * Store notices to DB
	 */
	public static function store_notices() {
		update_option( 'reepay_admin_notices', self::$notices );
	}

	/**
	 * Store notices to DB
	 */
	public static function store_frontend_notices( $notice, $order_id ) {
		update_post_meta( $order_id, '_reepay_frontend_notices', $notice );
	}

	public function show_editor_message( $messages ) {
		$notices = self::$notices;

		if ( ! empty( $notices ) ) {
			foreach ( $notices as $i => $notice ) {
				add_settings_error( 'reepay_notice_' . $i, '', $notice, 'error' );
				settings_errors( 'reepay_notice_' . $i );
			}
			update_option( 'reepay_admin_notices', array() );
		}

		return $messages;
	}

	public function show_activation_message() {
		$notices = self::$activation_notices;
		if ( ! empty( $notices ) ) {
			foreach ( $notices as $notice ) {
				echo "<div class='error'><p>" . wp_kses( $notice, [
						'a' => [
							'href' => true
						]
					] ) . "</p></div>";
			}
			update_option( 'reepay_admin_activation_notices', [] );
		}
	}

	public function show_thankyou_message( $ret, $order_id ) {
		$notice = get_post_meta( $order_id, '_reepay_frontend_notices', true );
		if ( ! empty( $notice ) ) {
			$ret = array(
				'state'   => 'failed',
				'message' => $notice
			);
		} else {
			$sub_handle = get_post_meta( $order_id, '_reepay_subscription_handle', true );
			$reloaded   = get_post_meta( $order_id, '_reepay_thankyou_reloaded', true );

			if ( empty( $reloaded ) ) {
				$order = wc_get_order( $order_id );
				if ( WC_Reepay_Renewals::is_order_contain_subscription( $order ) ) {
					$ret = array(
						'state' => 'reload',
					);
					update_post_meta( $order_id, '_reepay_thankyou_reloaded', true );
				}
			} elseif ( ! empty( $sub_handle ) ) {
				try {
					$sub = reepay_s()->api()->request( "subscription/{$sub_handle}" );
					if ( $sub['in_trial'] ) {
						$ret = array(
							'state'   => 'paid',
							'message' => 'Subscription is activated in trial'
						);
					}

					if ( WooCommerce_Reepay_Subscriptions::settings( '_reepay_manual_start_date' ) && strtotime( $sub['next_period_start'] ) > strtotime( 'now' ) ) {
						$ret = array(
							'state'   => 'paid',
							'message' => 'Subscription is activated in trial'
						);
					}

					if ( ! empty( $sub ) && $sub['state'] == 'active' ) {
						$ret = array(
							'state'   => 'paid',
							'message' => 'Order has been paid'
						);
					}

				} catch ( Exception $exception ) {
					$ret = array(
						'state'   => 'failed',
						'message' => $exception->getMessage()
					);
				}
			}
		}


		return $ret;
	}
}