<?php

use Automattic\WooCommerce\Utilities\OrderUtil;

/**
 * Class WC_Reepay_Admin_Frontend
 *
 * @since 1.0.0
 */
class WC_Reepay_Admin_Frontend {

	/**
	 * Constructor
	 */
	public function __construct() {
		if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
			add_action( 'manage_woocommerce_page_wc-orders_custom_column', [ $this, 'shop_order_custom_columns' ], 11,
				2 );
			add_filter( 'manage_woocommerce_page_wc-orders_columns', [ $this, 'admin_shop_order_edit_columns' ], 11 );
		} else {
			add_action( 'manage_shop_order_posts_custom_column', [ $this, 'shop_order_custom_columns' ], 11, 2 );
			add_filter( 'manage_edit-shop_order_columns', [ $this, 'admin_shop_order_edit_columns' ], 11 );
		}

		add_filter( 'post_class', [ $this, 'admin_shop_order_row_classes' ], 10, 2 );

		add_filter( 'posts_fields', [ $this, 'modify_search_results_fields' ], 10, 2 );
		add_filter( 'woocommerce_order_number', [ $this, 'modify_order_id' ], 10, 2 );
	}

	public function modify_order_id( $id, $order ) {
		global $post;

		$reepay_order = $order->get_meta( '_reepay_order' );
		if ( ! empty( $post ) ) {
			if ( ! empty( $reepay_order ) && ( ( ! empty( $post->post_parent ) && $post->post_parent !== 0 ) || ! empty( get_post_meta( $post->ID,
						'_reepay_subscription_handle_parent', true ) ) ) ) {
				return $reepay_order;
			}
		}

		return $id;
	}

	/**
	 * Adds css classes on admin shop order table
	 *
	 * @param  array  $classes
	 * @param  int  $post_id
	 *
	 * @return array
	 * @global WP_Post $post
	 *
	 */
	public function admin_shop_order_row_classes( $classes, $post_id ) {
		global $post;

		if ( is_search() || ! current_user_can( 'manage_woocommerce' ) ) {
			return $classes;
		}

		if ( OrderUtil::get_order_type( $post_id ) == 'shop_order' && $post->post_parent != 0 ) {
			$classes[] = 'sub-order parent-' . $post->post_parent;
		}

		return $classes;
	}

	/**
	 * Adds custom column on admin shop order table
	 *
	 * @param  string  $column_id  column id.
	 * @param  WC_Order|null  $order  order object. For compatibility with WooCommerce HPOS orders table
	 *
	 * @return void
	 */
	public function shop_order_custom_columns( $column_id, $order = null ) {
		$order = wc_get_order( $order );
		$post  = get_post( ! empty( $order ) ? $order->get_id() : null );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		if ( ! in_array( $column_id, [ 'order_number', 'order_type', 'reepay_sub' ], true ) ) {
			return;
		}

		$output = '';
		switch ( $column_id ) {
			case 'order_number':
				if ( $post->post_parent !== 0 ) {
					$output = '<strong>&nbsp;';
					$output .= __( 'Sub Order of', 'reepay-subscriptions-for-woocommerce' );
					$output .= sprintf( ' <a href="%s">#%s</a>',
						esc_url( admin_url( 'post.php?action=edit&post=' . $post->post_parent ) ),
						esc_html( $post->post_parent ) );
					$output .= '</strong>';
				}

				if ( ! empty( $order->get_meta( '_reepay_subscription_handle_parent' ) ) ) {
					$output     = '<strong>&nbsp;';
					$output     .= __( 'Sub Order of', 'reepay-subscriptions-for-woocommerce' );
					$handle     = $order->get_meta( '_reepay_subscription_handle_parent' );
					$admin_page = 'https://admin.billwerk.plus/#/rp/';

					$link = $admin_page . 'subscriptions/subscription/' . $handle;

					$output .= sprintf( ' <a target="_blank" href="%s">%s</a>', $link, $handle );
					$output .= '</strong>';
				}

				break;

			case 'order_type':
				$handle = $order->get_meta( '_reepay_subscription_handle' );
				if ( ! empty( $handle ) && $post->post_parent == 0 ) {
					$output = __( 'Subscription', 'reepay-subscriptions-for-woocommerce' );
				} elseif ( ! empty( $order->get_meta( '_reepay_order' ) ) && ( $post->post_parent != 0 || ! empty( $order->get_meta( '_reepay_is_renewal' ) ) ) ) {
					$output = __( 'Renewal', 'reepay-subscriptions-for-woocommerce' );
				} else {
					$output = __( 'Regular', 'reepay-subscriptions-for-woocomerce' );
				}

				break;

			case 'reepay_sub':
				$handle = $order->get_meta( '_reepay_subscription_handle' );

				if ( empty( $handle ) ) {
					$handle = $order->get_meta( '_reepay_subscription_handle_parent' );
				}

				if ( empty( $handle ) && ! empty( $order->get_parent_id() ) ) {
					$parent_order = wc_get_order( $order->get_parent_id() );
					$handle       = $parent_order->get_meta( '_reepay_subscription_handle' );
				}

				if ( ! empty( $handle ) ) {
					$admin_page = 'https://admin.billwerk.plus/#/rp/';

					$link = $admin_page . 'subscriptions/subscription/' . $handle;

					$output = sprintf( '<a target="_blank" href="%s">%s</a>', $link, $handle );
				}

				break;
		}

		if ( ! empty( $output ) ) {
			echo wp_kses_post( $output );
		}
	}

	/**
	 * Change the columns shown in admin.
	 *
	 * @param  array  $existing_columns
	 *
	 * @return array
	 */
	public function admin_shop_order_edit_columns( $existing_columns ) {
		$columns = array_slice( $existing_columns, 0, count( $existing_columns ) - 1, true ) +
		           array(
			           'reepay_sub' => __( 'Subscription', 'reepay-subscriptions-for-woocommerce' ),
			           'order_type' => __( 'Order type', 'reepay-subscriptions-for-woocommerce' ),
		           )
		           + array_slice( $existing_columns, count( $existing_columns ) - 1, count( $existing_columns ), true );

		// Remove seller, suborder column if seller is viewing his own product
		if ( ! current_user_can( 'manage_woocommerce' ) || ( isset( $_GET['author'] ) && ! empty( $_GET['author'] ) ) ) {
			unset( $columns['order_type'] );
			unset( $columns['reepay_sub'] );
		}

		return $columns;
	}

	function modify_search_results_fields( $orderby, $query ) {
		if ( is_admin() && $query->is_main_query() && $query->get( 'post_type' ) === 'shop_order' ) {
			global $wpdb;
			$orderby = "$wpdb->posts.*, $wpdb->posts.post_title";
		}

		return $orderby;
	}
}
