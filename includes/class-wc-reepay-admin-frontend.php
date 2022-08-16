<?php

/**
 * Class WC_Reepay_Admin_Frontend
 *
 * @since 1.0.0
 */
class WC_Reepay_Admin_Frontend
{

    /**
     * Constructor
     */
    public function __construct()
    {
        add_action('manage_shop_order_posts_custom_column', [$this, 'shop_order_custom_columns'], 11);
        add_filter('manage_edit-shop_order_columns', [$this, 'admin_shop_order_edit_columns'], 11);
        add_filter('post_class', [$this, 'admin_shop_order_row_classes'], 10, 2);

        add_filter('posts_orderby', [$this, 'modify_search_results_order'], 10, 2);
        add_filter('posts_fields', [$this, 'modify_search_results_fields'], 10, 2);
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
    public function admin_shop_order_row_classes($classes, $post_id)
    {
        global $post;

        if (is_search() || !current_user_can('manage_woocommerce')) {
            return $classes;
        }

        if ($post->post_type == 'shop_order' && $post->post_parent != 0) {
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
    public function shop_order_custom_columns($col)
    {
        /**
         * @global \WP_Post $post
         * @global \WC_Order $the_order
         */
        global $post, $the_order;

        if (empty($the_order) || $the_order->get_id() !== $post->ID) {
            $the_order = new \WC_Order($post->ID);
        }

        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        if (!in_array($col, ['order_number', 'suborder', 'reepay_sub'], true)) {
            return;
        }

        $output = '';
        switch ($col) {
            case 'order_number':
                if ($post->post_parent !== 0) {
                    $output = '<strong>';
                    $output .= esc_html__('&nbsp;Sub Order of', reepay_s()->settings('domain'));
                    $output .= sprintf(' <a href="%s">#%s</a>', esc_url(admin_url('post.php?action=edit&post=' . $post->post_parent)),
                        esc_html($post->post_parent));
                    $output .= '</strong>';
                }
                break;

            case 'suborder':
                $handle = $the_order->get_meta('_reepay_subscription_handle', true);
                if (!empty($handle) && $post->post_parent == 0) {
                    $output = sprintf('<a href="#" class="show-sub-orders" data-class="parent-%1$d" data-show="%2$s" data-hide="%3$s">%2$s</a>',
                        esc_attr($post->ID), esc_attr__('Show history', reepay_s()->settings('domain')),
                        esc_attr__('Hide history', reepay_s()->settings('domain')));
                }
                break;

            case 'reepay_sub':
                $handle = $the_order->get_meta('_reepay_subscription_handle', true);
                if (!empty($handle)) {
                    $admin_page = 'https://app.reepay.com/#/rp/';

                    $link = $admin_page . 'subscriptions/' . $handle;

                    $output = sprintf('<a target="_blank" href="%s">%s</a>', $link, $handle);
                }

                break;
        }

        if (!empty($output)) {
            echo $output;
        }
    }

    /**
     * Change the columns shown in admin.
     *
     * @param array $existing_columns
     *
     * @return array
     */
    public function admin_shop_order_edit_columns($existing_columns)
    {
        if (WC_VERSION > '3.2.6') {
            unset($existing_columns['wc_actions']);

            $columns = array_slice($existing_columns, 0, count($existing_columns), true) +
                array(
                    'reepay_sub' => __('Subscription', reepay_s()->settings('domain')),
                    'suborder' => __('Sub Order', reepay_s()->settings('domain')),
                )
                + array_slice($existing_columns, count($existing_columns), count($existing_columns) - 1, true);
        } else {
            $existing_columns['reepay_sub'] = __('Vendor', reepay_s()->settings('domain'));
            $existing_columns['suborder'] = __('Sub Order', reepay_s()->settings('domain'));
        }

        if (WC_VERSION > '3.2.6') {
            // Remove seller, suborder column if seller is viewing his own product
            if (!current_user_can('manage_woocommerce') || (isset($_GET['author']) && !empty($_GET['author']))) {
                unset($columns['suborder']);
                unset($columns['reepay_sub']);
            }

            return $columns;
        }

        // Remove seller, suborder column if seller is viewing his own product
        if (!current_user_can('manage_woocommerce') || (isset($_GET['author']) && !empty($_GET['author']))) {
            unset($existing_columns['suborder']);
            unset($existing_columns['reepay_sub']);
        }

        return $existing_columns;
    }

    function modify_search_results_order($orderby, $query)
    {
        if (is_admin() && $query->is_main_query() && $query->get('post_type') === 'shop_order') {
            global $wpdb;
            $orderby = "CASE WHEN $wpdb->posts.post_parent != 0 THEN $wpdb->posts.post_parent WHEN $wpdb->posts.post_parent = 0 THEN $wpdb->posts.id END desc";
        }

        return $orderby;
    }

    function modify_search_results_fields($orderby, $query)
    {

        if (is_admin() && $query->is_main_query() && $query->get('post_type') === 'shop_order') {
            global $wpdb;
            $orderby = "$wpdb->posts.*, $wpdb->posts.post_title";
        }

        return $orderby;
    }
}

new WC_Reepay_Admin_Frontend();
