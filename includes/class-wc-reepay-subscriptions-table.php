<?php

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( \WP_List_Table::class ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Class Drafts_List_Table.
 *
 * @since 0.1.0
 * @package Admin_Table_Tut
 * @see WP_List_Table
 */
class Subscriptions_Table extends \WP_List_Table {

    /**
     * Draft_List_Table constructor.
     */
    public function __construct() {

        parent::__construct(
            array(
                'singular' => 'Subscription',
                'plural'   => 'Subscriptions',
                'ajax'     => false,
            )
        );

    }

    /**
     * Return instances post object.
     *
     * @return WP_Query Custom query object with passed arguments.
     */
    protected function get_subscriptions() {

        $params = [];
        $search = esc_sql( filter_input( INPUT_GET, 's' ) );

        $paged = filter_input( INPUT_GET, 'paged', FILTER_VALIDATE_INT );

        if ( $paged ) {
            $params['page'] = $paged;
        }

        if ( ! empty( $search ) ) {
            $params['search'] = 'text;' . $search;
        }

        $orderby = sanitize_sql_orderby( filter_input( INPUT_GET, 'orderby' ) );
        $order = sanitize_sql_orderby( filter_input( INPUT_GET, 'order' ) );

        if ($orderby === 'date' && $order === 'asc') {
            $params['sort'] = 'created';
        }

        $subsResult = reepay_s()->api()->request("subscription?" . http_build_query($params));

        return $subsResult;
    }

    /**
     * Display text for when there are no items.
     */
    public function no_items() {
        esc_html_e( 'No subscriptions found.', 'admin-table-tut' );
    }

    /**
     * Get list columns.
     *
     * @return array
     */
    public function get_columns() {
        return array(
            'status' => __( 'Status', reepay_s()->settings('domain') ),
            'handle'   => __( 'Subscription handle', reepay_s()->settings('domain') ),
            'customer_handle'   => __( 'Customer handle', reepay_s()->settings('domain') ),
            'plan'   => __( 'Plan', reepay_s()->settings('domain') ),
            'date'   => __( 'Created date', reepay_s()->settings('domain') ),
            'next_period_start'   => __( 'Next renewal date', reepay_s()->settings('domain') ),
        );
    }

    /**
     * Include the columns which can be sortable.
     *
     * @return array $sortable_columns Return array of sortable columns.
     */
    public function get_sortable_columns() {

        return array(
            'id'  => array( 'id', false ),
            'date'  => array( 'date', false ),
        );
    }

    public function column_id($item) {
        return $item['handle'];
    }

    public function column_status($item) {
        return $item['status'];
    }

    public function column_plan($item) {
        $admin_page = 'https://admin.reepay.com/#/misha-rudrastyh-team/misha-rudrastyh-team/';

        $output = '<a href="' . $admin_page . 'plan/' . $item['plan'] . '" target="_blank">' . $item['plan'] . '</a>';

        return $output;
    }

    /**
     * Return title column.
     *
     * @param  array $item Item data.
     * @return string
     */
    public function column_handle( $item ) {
        $admin_page = 'https://admin.reepay.com/#/misha-rudrastyh-team/misha-rudrastyh-team/';

        $output = '<a href="' . $admin_page . 'subscriptions/' . $item['handle'] . '" target="_blank">' . $item['id'] . '</a>';

        return $output;
    }

    public function column_date($item) {
        return $this->format_date($item['date']);
    }

    function format_date($dateStr) {
        return (new DateTime($dateStr))->format('d M Y');
    }

    public function column_next_period_start($item) {
        return $this->format_date($item['next_period_start']);
    }

    public function column_customer_handle($item) {
        return $item['customer_handle'];
    }

    function format_status($subscription) {
        if ($subscription['is_cancelled'] === true) {
            return '<mark class="canceled"><span>Cancelled</span></mark>';
        }
        if ($subscription['state'] === 'expired') {
            return '<mark class="expired"><span>Expired</span></mark>';
        }

        if ($subscription['state'] === 'on_hold') {
            return '<mark class="on-hold"><span>On Hold</span></mark>';
        }

        if ($subscription['state'] === 'is_cancelled') {
            return '<mark class="is-canceled"><span>Is cancelled</span></mark>';
        }

        if ($subscription['state'] === 'active') {
            if (isset($subscription['trial_end'])) {
                $now = new DateTime();
                $trial_end = new DateTime($subscription['trial_end']);
                if ($trial_end > $now) {
                    return '<mark class="trial"><span>Trial</span></mark>';
                }
            }
            return '<mark class="active"><span>Active</span></mark>';
        }

        return $subscription['state'];
    }

    /**
     * Prepare the data for the WP List Table
     *
     * @return void
     */
    public function prepare_items() {
        $columns               = $this->get_columns();
        $sortable              = $this->get_sortable_columns();
        $hidden                = array();
        $primary               = 'title';
        $this->_column_headers = array( $columns, $hidden, $sortable, $primary );
        $data                  = array();

        $subscriptions = $this->get_subscriptions();

        if ( !empty($subscriptions['content']) ) {
            foreach ($subscriptions['content'] as $subscription) {
                $data[ $subscription['handle'] ] = array(
                    'id'     => $subscription['handle'],
                    'handle'  => $subscription['handle'],
                    'status'   => $this->format_status($subscription),
                    'date'   => $subscription['created'],
                    'next_period_start'   => $subscription['next_period_start'],
                    'customer_handle'   => $subscription['customer'],
                    'plan' => $subscription['plan'],
                );
            }
        }

        $this->items = $data;

        $this->set_pagination_args(
            array(
                'total_items' => $subscriptions['total_elements'],
                'per_page'    => $subscriptions['size'],
                'total_pages' => $subscriptions['total_pages'],
            )
        );
    }


    /**
     * Generates the table navigation above or below the table
     *
     * @since 3.1.0
     * @param string $which
     */
    protected function display_tablenav( $which ) {
        ?>
        <div class="tablenav <?php echo esc_attr( $which ); ?>">

            <?php
            $this->pagination( $which );
            ?>

            <br class="clear" />
        </div>
        <?php
    }
}