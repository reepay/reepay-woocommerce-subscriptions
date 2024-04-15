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
	 * @param  string  $name  Notice name.
	 * @param  bool  $force_save  Force saving inside this method instead of at the 'shutdown'.
	 */
	public static function add_activation_notice( $notice ) {
		self::$activation_notices = array_unique( array_merge( self::$activation_notices, array( $notice ) ) );

		update_option( 'reepay_admin_activation_notices', self::$activation_notices );
	}


	/**
	 * Show a notice.
	 *
	 * @param  string  $name  Notice name.
	 * @param  bool  $force_save  Force saving inside this method instead of at the 'shutdown'.
	 */
	public static function add_notice( $notice ) {
		self::$notices = array_unique( array_merge( self::$notices, array( $notice ) ) );

		self::store_notices();
	}

	/**
	 * Add a frontend notice.
	 *
	 * @param  string  $name  Notice name.
	 * @param  bool  $force_save  Force saving inside this method instead of at the 'shutdown'.
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
		$order = wc_get_order( $order_id );
		$order->update_meta_data( '_reepay_frontend_notices', $notice );
		$order->save_meta_data();
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
		$order  = wc_get_order( $order_id );
		$notice = $order->get_meta( '_reepay_frontend_notices' );
		if ( ! empty( $notice ) ) {
			$ret = array(
				'state'   => 'failed',
				'message' => $notice
			);
		} else {
			$reloaded   = $order->get_meta( '_reepay_thankyou_reloaded' );
			$sub_handle = $order->get_meta( '_reepay_subscription_handle' );
			if ( empty( $reloaded ) ) {
				if ( WC_Reepay_Renewals::is_order_contain_subscription( $order ) ) {
					$ret = array(
						'state' => 'reload',
					);

					$order->update_meta_data( '_reepay_thankyou_reloaded', true );
					$order->save_meta_data();
				}
			} elseif ( ! empty( $sub_handle ) ) {
				try {
					$sub = reepay_s()->api()->request( "subscription/{$sub_handle}" );
					if ( $sub['in_trial'] ) {
						$ret = array(
							'state'   => 'paid',
							'message' => __( 'Subscription is activated in trial',
								'reepay-subscriptions-for-woocommerce' )
						);
					}

					if ( WooCommerce_Reepay_Subscriptions::settings( '_reepay_manual_start_date' ) && strtotime( $sub['next_period_start'] ) > strtotime( 'now' ) ) {
						$ret = array(
							'state'   => 'paid',
							'message' => __( 'Subscription is activated in trial',
								'reepay-subscriptions-for-woocommerce' )
						);
					}

					if ( ! empty( $sub ) && $sub['state'] == 'active' ) {
						$ret = array(
							'state'   => 'paid',
							'message' => __( 'Order has been paid', 'reepay-subscriptions-for-woocommerce' )
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
