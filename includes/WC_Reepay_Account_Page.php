<?php

/**
 * Class WC_Reepay_Checkout
 *
 * @since 1.0.0
 */
class WC_Reepay_Account_Page {
	/**
	 * @var bool
	 */
	public $add_reepay_subscriptions_to_woo_subscriptions = true;

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'init', [ $this, 'init' ] );
		add_action( 'template_redirect', [ $this, 'check_action' ] );
		add_filter( 'wcs_get_users_subscriptions', [ $this, 'add_reepay_subscriptions_to_woo_subscriptions_table' ], 2, 10 );
		add_filter( 'wcs_get_subscription', [ $this, 'view_reepay_subscription_like_woo' ], 2, 10 );
		add_filter( 'woocommerce_account_orders_columns', [ $this, 'add_column_to_account_orders' ], 2, 10 );
		add_filter( 'woocommerce_my_account_my_orders_column_order_type', [ $this, 'add_order_type_to_account_orders' ], 2, 10 );
		add_filter( 'woocommerce_get_formatted_order_total', [ $this, 'show_zero_order_total_on_account_orders' ], 1, 10 );
		add_action( 'woocommerce_account_subscriptions_endpoint', [ $this, 'subscriptions_endpoint' ], 5, 1 );
		add_action( 'woocommerce_account_view-subscription_endpoint', [ $this, 'subscription_endpoint' ], 5, 1 );
		add_filter( 'query_vars', array( $this, 'add_subscription_query_vars' ) );
		add_filter( 'woocommerce_get_query_vars', array( $this, 'add_subscription_query_vars' ) );
		add_filter( 'woocommerce_account_menu_items', [ $this, 'add_subscriptions_menu_item' ] );

		add_filter( 'woocommerce_reepay_payment_accept_url', [ $this, 'add_subscription_arg' ] );
		add_filter( 'woocommerce_reepay_payment_cancel_url', [ $this, 'add_subscription_arg' ] );
		add_action( 'woocommerce_reepay_payment_method_added', [ $this, 'payment_method_added' ] );
		add_filter( 'woocommerce_endpoint_subscriptions_title', [ $this, 'get_title' ] );
	}

	public function add_subscription_arg( $url ) {
		if ( $_GET['reepay_subscription'] ) {
			return sanitize_url( add_query_arg( 'reepay_subscription', $_GET['reepay_subscription'], $url ) );
		}

		return $url;
	}

	public function payment_method_added( WC_Payment_Token $token ) {
		$handle = sanitize_text_field( $_GET['reepay_subscription'] ) ?? '';
		if ( ! empty( $handle ) ) {
			try {
				$payment_methods = reepay_s()->api()->request( 'subscription/' . $handle . '/pm', 'POST', [
					'source' => $token->get_token(),
				] );
				set_transient( $handle . '_payment_methods', $payment_methods );
				wc_add_notice( __( 'Payment method successfully added.', 'reepay-subscriptions-for-woocommerce' ) );
			} catch ( Exception $exception ) {
				wc_add_notice( $exception->getMessage() );
			}
		}
		wp_redirect( wc_get_account_endpoint_url( 'subscriptions' ) );
		exit;
	}

	public function init() {
		$this->rewrite_endpoint();
	}

	public function rewrite_endpoint() {
		add_rewrite_endpoint( 'subscriptions', EP_ROOT | EP_PAGES );

		if ( get_transient( 'woocommerce_reepay_subscriptions_activated' ) ) {
			flush_rewrite_rules();
			delete_transient( 'woocommerce_reepay_subscriptions_activated' );
		}
	}

	public function check_action() {

		if ( ! empty( $_GET['cancel_subscription'] ) ) {
			if ( ! reepay_s()->settings( '_reepay_enable_cancel' ) ) {
				return;
			}

			$handle = sanitize_text_field( $_GET['cancel_subscription'] );
			$handle = urlencode( $handle );

			$order = wc_get_orders( [
					'meta_key'   => '_reepay_subscription_handle',
					'meta_value' => $handle,
				] )[0] ?? null;

			if ( $order && $order->get_customer_id() === get_current_user_id() ) {
				try {
					$result = reepay_s()->api()->request( "subscription/{$handle}/cancel", 'POST' );
				} catch ( Exception $exception ) {
					wc_add_notice( $exception->getMessage(), 'error' );
				}
			} else {
				wc_add_notice( 'Permission denied', 'error' );
			}

			wp_redirect( wc_get_endpoint_url( 'view-subscription', $order->get_id() ) );
			exit;
		}


		if ( ! empty( $_GET['uncancel_subscription'] ) ) {

			$handle = sanitize_text_field( $_GET['uncancel_subscription'] );
			$handle = urlencode( $handle );

			$order = wc_get_orders( [
					'meta_key'   => '_reepay_subscription_handle',
					'meta_value' => $handle,
				] )[0] ?? null;


			if ( $order && $order->get_customer_id() === get_current_user_id() ) {
				try {
					$result = reepay_s()->api()->request( "subscription/{$handle}/uncancel", 'POST' );
				} catch ( Exception $exception ) {
					wc_add_notice( $exception->getMessage(), 'error' );
				}
			} else {
				wc_add_notice( 'Permission denied', 'error' );
			}

			wp_redirect( wc_get_endpoint_url( 'view-subscription', $order->get_id() ) );
			exit;
		}

		if ( ! empty( $_GET['put_on_hold'] ) ) {
			if ( ! reepay_s()->settings( '_reepay_enable_on_hold' ) ) {
				return;
			}
			$handle = sanitize_text_field( $_GET['put_on_hold'] );
			$handle = urlencode( $handle );

			$order = wc_get_orders( [
					'meta_key'   => '_reepay_subscription_handle',
					'meta_value' => $handle,
				] )[0] ?? null;


			if ( $order && $order->get_customer_id() === get_current_user_id() ) {
				$compensation_method = reepay_s()->settings( '_reepay_on_hold_compensation_method' );

				$params = [
					"compensation_method" => $compensation_method,
				];

				try {
					$result = reepay_s()->api()->request( "subscription/{$handle}/on_hold", 'POST', $params );
				} catch ( Exception $e ) {
					wc_add_notice( $e->getMessage(), 'error' );
				}
			} else {
				wc_add_notice( 'Permission denied', 'error' );
			}


			wp_redirect( wc_get_endpoint_url( 'view-subscription', $order->get_id() ) );
			exit;
		}

		if ( ! empty( $_GET['reactivate'] ) ) {
			$handle = sanitize_text_field( $_GET['reactivate'] );
			$handle = urlencode( $handle );

			$order = wc_get_orders( [
					'meta_key'   => '_reepay_subscription_handle',
					'meta_value' => $handle,
				] )[0] ?? null;

			if ( $order && $order->get_customer_id() === get_current_user_id() ) {
				try {
					$result = reepay_s()->api()->request( "subscription/{$handle}/reactivate", 'POST' );
				} catch ( Exception $e ) {
					wc_add_notice( $e->getMessage() );
				}
			} else {
				wc_add_notice( 'Permission denied', 'error' );
			}
			wp_redirect( wc_get_endpoint_url( 'view-subscription', $order->get_id() ) );
			exit;
		}

		if ( ! empty( $_GET['change_payment_method'] ) ) {
			$handle   = sanitize_text_field( $_GET['change_payment_method'] );
			$token_id = intval( $_GET['token_id'] );
			$token    = WC_Payment_Tokens::get( $token_id );
			$handle   = urlencode( $handle );

			$order = wc_get_orders( [
					'meta_key'   => '_reepay_subscription_handle',
					'meta_value' => $handle,
				] )[0] ?? null;

			$params = [
				'source' => $token->get_token(),
			];

			if ( $order && $order->get_customer_id() === get_current_user_id() ) {
				try {
					$payment_methods = reepay_s()->api()->request( "subscription/{$handle}/pm", 'POST', $params );
					set_transient( $handle . '_payment_methods', $payment_methods );
				} catch ( Exception $e ) {
					wc_add_notice( $e->getMessage() );
				}
			} else {
				wc_add_notice( 'Permission denied', 'error' );
			}
			wp_redirect( wc_get_endpoint_url( 'view-subscription', $order->get_id() ) );
			exit;
		}
	}

	public function get_title() {
		return __( "Subscriptions", 'reepay-subscriptions-for-woocommerce' );
	}

	/**
	 * @param WC_Subscription[]|array $subscriptions
	 * @param int $user_id
	 *
	 * @return WC_Subscription[]|array
	 */
	public function add_reepay_subscriptions_to_woo_subscriptions_table( $subscriptions, $user_id ) {
		if ( ! $this->add_reepay_subscriptions_to_woo_subscriptions ) {
			return $subscriptions;
		}

		$params['size'] = 100;
		$params['page'] = 1;
		$params['sort'] = 'created';

		$reepay_subscriptions = wc_get_orders( [
			'limit' => - 1,
			'meta_key' => '_reepay_subscription_handle',
			'meta_compare' => 'EXISTS',
			'customer_id' => $user_id
		] );

		$subscriptions = array_merge( $reepay_subscriptions, $subscriptions );
		usort( $subscriptions, function ( $sub1, $sub2 ) {
			return $sub2->get_date_created()->getTimestamp() - $sub1->get_date_created()->getTimestamp();
		} );

		return $subscriptions;
	}

	public function subscriptions_endpoint( $current_page = 1 ) {
		if ( class_exists( 'WC_Subscriptions' ) ) {
			$this->add_reepay_subscriptions_to_woo_subscriptions = true;
			return;
		}

		$all_subscriptions = apply_filters( 'wcs_get_users_subscriptions', [], get_current_user_id() );
		$current_page      = empty( $current_page ) ? 1 : absint( $current_page );
		$posts_per_page    = get_option( 'posts_per_page' );
		$max_num_pages     = ceil( count( $all_subscriptions ) / $posts_per_page );
		$subscriptions     = array_slice( $all_subscriptions, ( $current_page - 1 ) * $posts_per_page, $posts_per_page );


		wc_get_template(
			'myaccount/my-subscriptions.php',
			array(
				'subscriptions' => $subscriptions,
				'current_page'  => $current_page,
				'max_num_pages' => $max_num_pages,
				'paginate'      => true,
			),
			'',
			reepay_s()->settings( 'plugin_path' ) . 'templates/'
		);
	}

	public function add_subscriptions_menu_item( $menu_items ) {
		if ( ! empty( $menu_items['subscriptions'] ) ) {
			return $menu_items;
		}

		$menu_items_updated = [];

		foreach ( $menu_items as $key => $menu_item ) {
			$menu_items_updated[ $key ] = $menu_item;

			if ( 'orders' === $key ) {
				$menu_items_updated['subscriptions'] = $this->get_title();
			}
		}

		return $menu_items_updated;
	}

	function get_status( $subscription ) {
		if ( $subscription['is_cancelled'] === true ) {
			return 'Cancelled';
		}
		if ( $subscription['state'] === 'expired' ) {
			return 'Expired';
		}

		if ( $subscription['state'] === 'on_hold' ) {
			return 'On hold';
		}

		if ( $subscription['state'] === 'is_cancelled' ) {
			return 'Cancelled';
		}

		if ( $subscription['state'] === 'active' ) {
			if ( isset( $subscription['trial_end'] ) ) {
				$now       = new DateTime();
				$trial_end = new DateTime( $subscription['trial_end'] );
				if ( $trial_end > $now ) {
					return 'Trial';
				}
			}

			return 'Active';
		}

		return $subscription['state'];
	}

	/**
	 * @param WC_Subscription|false $subscription
	 */
	public function view_reepay_subscription_like_woo( $subscription ) {
		global $wp;

		if ( ! did_action( 'woocommerce_account_content' ) || ! empty( $subscription ) ) {
			return $subscription;
		}

		$order_id = $wp->query_vars['view-subscription'] ?? '';

		if( empty( $order_id ) ) {
			return $subscription;
		}

		return wc_get_order( $order_id );
	}

	/**
	 * @param array $columns
	 */
	public function add_column_to_account_orders( $columns ) {
		$columns['order_type'] = 'Order type';

		return $columns;
	}

	/**
	 * @param WC_Order $order
	 */
	public function add_order_type_to_account_orders( $order ) {
		$type = '';

		if( $order->get_meta('_reepay_subscription_handle') ) {
			$type = 'Reepay subscription';
		} elseif ( class_exists( 'WC_Subscriptions_Product' ) ) {
			$product = current($order->get_items())->get_product();
			
			if ( WC_Subscriptions_Product::is_subscription( $product ) ) {
				$type = 'Subscription';
			} else {
				$type = 'Order';
			}
		} else {
			$type = 'Order';
		}

		echo '<span>' . $type . '</span>';
	}

	public function show_zero_order_total_on_account_orders( $formatted_total ) {
		global $wp;

		if ( ! isset( $wp->query_vars['orders'] ) ) {
			return $formatted_total;
		}

		return wc_price(0);
	}

	public function subscription_endpoint() {
		if ( class_exists( 'WC_Subscriptions' ) ) {
			return;
		}

		$subscription = apply_filters( 'wcs_get_subscription', false );

		if ( ! $subscription || ! current_user_can( 'view_order', $subscription->get_id() ) ) {
			echo '<div class="woocommerce-error">' . esc_html__( 'Invalid Subscription.', 'woocommerce-subscriptions' ) . ' <a href="' . esc_url( wc_get_page_permalink( 'myaccount' ) ) . '" class="wc-forward">' . esc_html__( 'My Account', 'woocommerce-subscriptions' ) . '</a>' . '</div>';
			return;
		}

		wc_get_template(
			'myaccount/view-subscription.php',
			compact( 'subscription' ),
			'',
			reepay_s()->settings( 'plugin_path' ) . 'templates/'
		);
	}

	public function add_subscription_query_vars ( $query_vars ) {
		$query_vars['view-subscription']           = get_option( 'woocommerce_myaccount_view_subscription_endpoint', 'view-subscription' );
		$query_vars['subscriptions']               = get_option( 'woocommerce_myaccount_subscriptions_endpoint', 'subscriptions' );
		$query_vars['subscription-payment-method'] = get_option( 'woocommerce_myaccount_subscription_payment_method_endpoint', 'subscription-payment-method' );

		return $query_vars;
	}
}
