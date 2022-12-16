<?php

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
		add_action( 'manage_shop_order_posts_custom_column', [ $this, 'shop_order_custom_columns' ], 11 );
		add_filter( 'manage_edit-shop_order_columns', [ $this, 'admin_shop_order_edit_columns' ], 11 );
		add_filter( 'post_class', [ $this, 'admin_shop_order_row_classes' ], 10, 2 );

		//add_filter( 'posts_orderby', [ $this, 'modify_search_results_order' ], 10, 2 );
		add_action( 'pre_get_posts', [ $this, 'add_invoice_to_search' ], 10, 1 );
		add_filter( 'posts_fields', [ $this, 'modify_search_results_fields' ], 10, 2 );
		add_filter( 'woocommerce_order_number', [ $this, 'modify_order_id' ], 10, 2 );

		add_action( 'woocommerce_admin_order_data_after_billing_address', array(
			$this,
			'reepay_show_extra_order_fields'
		) );
	}

	public function add_invoice_to_search( $query ) {

		if ( ! is_admin() ) {
			return;
		}

		global $pagenow;

		if ( 'edit.php' === $pagenow && 'shop_order' === $query->query['post_type'] && isset( $query->query['s'] ) ) {
			$s          = str_replace( '#', '', $query->query['s'] );
			$meta_query = array(
				array(
					'key'     => '_reepay_order',
					'value'   => $s,
					'compare' => 'LIKE'
				)
			);

			unset( $query->query['s'] );
			unset( $query->query_vars['s'] );

			$query->set( 'meta_query', $meta_query );
		}

		return;
	}

	public function modify_order_id( $id, $order ) {
		global $post;

		$reepay_order = get_post_meta( $order->get_id(), '_reepay_order', true );
		if ( ! empty( $reepay_order ) && ! empty( $post->post_parent ) && $post->post_parent !== 0 ) {
			return $reepay_order;
		}

		return $id;
	}

	public function reepay_show_extra_order_fields( $order ) {

		echo '<p>
            <a class="button refund-items" role="button" href="https://app.reepay.com/#/rp/customers/customers/customer/customer-' . $order->get_customer_id() . '" target="_blank">
            ' . __( 'See customer', 'reepay-subscriptions-for-woocommerce' ) . '
            </a>
        </p>';
	}

	/**
	 * Adds css classes on admin shop order table
	 *
	 * @param array $classes
	 * @param int $post_id
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

		if ( $post->post_type == 'shop_order' && $post->post_parent != 0 ) {
			$classes[] = 'sub-order parent-' . $post->post_parent;
		}

		return $classes;
	}

	/**
	 * Adds custom column on admin shop order table
	 *
	 * @param string $col
	 *
	 * @return void
	 */
	public function shop_order_custom_columns( $col ) {
		/**
		 * @global \WP_Post $post
		 * @global \WC_Order $the_order
		 */
		global $post, $the_order;

		if ( empty( $the_order ) || $the_order->get_id() !== $post->ID ) {
			$the_order = new \WC_Order( $post->ID );
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		if ( ! in_array( $col, [ 'order_number', 'order_type', 'reepay_sub' ], true ) ) {
			return;
		}

		$output = '';
		switch ( $col ) {
			case 'order_number':
				if ( $post->post_parent !== 0 ) {
					$output = '<strong>';
					$output .= __( '&nbsp;Sub Order of', 'reepay-subscriptions-for-woocommerce' );
					$output .= sprintf( ' <a href="%s">#%s</a>', esc_url( admin_url( 'post.php?action=edit&post=' . $post->post_parent ) ),
						esc_html( $post->post_parent ) );
					$output .= '</strong>';
				}
				break;

			case 'order_type':
				$handle   = $the_order->get_meta( '_reepay_subscription_handle' );
				$imported = $the_order->get_meta( '_reepay_imported' );
				if ( ! empty( $handle ) && $post->post_parent == 0 ) {
					/*$output = sprintf( '<a href="#" class="show-sub-orders" data-class="parent-%1$d" data-show="%2$s" data-hide="%3$s">%2$s</a>',
						esc_attr( $post->ID ), esc_attr__( 'Show history', 'reepay-subscriptions-for-woocommerce' ),
						esc_attr__( 'Hide history', 'reepay-subscriptions-for-woocommerce' ) );*/
					$output = 'Subscription';
				} elseif ( ! empty( $the_order->get_meta( '_reepay_order' ) ) && $post->post_parent != 0 ) {
					$output = 'Renewal';
				} else {
					$output = 'Regular';
				}

				break;

			case 'reepay_sub':
				$handle = $the_order->get_meta( '_reepay_subscription_handle' );

				if ( empty( $handle ) && ! empty( $the_order->get_parent_id() )) {
					$handle = get_post_meta( $the_order->get_parent_id(), '_reepay_subscription_handle', true );
				}

				if ( ! empty( $handle ) ) {
					$admin_page = 'https://app.reepay.com/#/rp/';

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
	 * @param array $existing_columns
	 *
	 * @return array
	 */
	public function admin_shop_order_edit_columns( $existing_columns ) {
		if ( WC_VERSION > '3.2.6' ) {
			unset( $existing_columns['wc_actions'] );

			$columns = array_slice( $existing_columns, 0, count( $existing_columns ), true ) +
			           array(
				           'reepay_sub' => __( 'Subscription', 'reepay-subscriptions-for-woocommerce' ),
				           'order_type' => __( 'Order type', 'reepay-subscriptions-for-woocommerce' ),
			           )
			           + array_slice( $existing_columns, count( $existing_columns ), count( $existing_columns ) - 1, true );
		} else {
			$existing_columns['reepay_sub'] = __( 'Vendor', 'reepay-subscriptions-for-woocommerce' );
			$existing_columns['order_type'] = __( 'Order type', 'reepay-subscriptions-for-woocommerce' );
		}

		if ( WC_VERSION > '3.2.6' ) {
			// Remove seller, suborder column if seller is viewing his own product
			if ( ! current_user_can( 'manage_woocommerce' ) || ( isset( $_GET['author'] ) && ! empty( $_GET['author'] ) ) ) {
				unset( $columns['order_type'] );
				unset( $columns['reepay_sub'] );
			}

			return $columns;
		}

		// Remove seller, suborder column if seller is viewing his own product
		if ( ! current_user_can( 'manage_woocommerce' ) || ( isset( $_GET['author'] ) && ! empty( $_GET['author'] ) ) ) {
			unset( $existing_columns['order_type'] );
			unset( $existing_columns['reepay_sub'] );
		}

		return $existing_columns;
	}

	/*function modify_search_results_order( $orderby, $query ) {
		if ( is_admin() && $query->is_main_query() && $query->get( 'post_type' ) === 'shop_order' ) {
			global $wpdb;
			$orderby = "CASE WHEN $wpdb->posts.post_parent != 0 THEN $wpdb->posts.post_parent WHEN $wpdb->posts.post_parent = 0 THEN $wpdb->posts.id END desc";
		}

		return $orderby;
	}*/

	function modify_search_results_fields( $orderby, $query ) {

		if ( is_admin() && $query->is_main_query() && $query->get( 'post_type' ) === 'shop_order' ) {
			global $wpdb;
			$orderby = "$wpdb->posts.*, $wpdb->posts.post_title";
		}

		return $orderby;
	}
}
