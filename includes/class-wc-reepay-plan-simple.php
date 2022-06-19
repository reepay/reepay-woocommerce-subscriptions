<?php

class WC_Reepay_Subscription_Plan_Simple {

    const TYPE_DAILY = 'daily';
    const TYPE_MONTH_START_DATE = 'month_startdate';
    const TYPE_MONTH_FIXED_DAY = 'month_fixedday';
    const TYPE_MONTH_LAST_DAY = 'month_lastday';
    const TYPE_PRIMO = 'primo';
    const TYPE_ULTIMO = 'ultimo';
    const TYPE_HALF_YEARLY = 'half_yearly';
    const TYPE_START_DATE_12 = 'month_startdate_12';
    const TYPE_WEEKLY_FIXED_DAY = 'weekly_fixedday';
    const TYPE_MANUAL = 'manual';

    public static $schedule_types = array(
        self::TYPE_DAILY            => 'Day(s)',
        self::TYPE_MONTH_START_DATE => 'Month(s)',
        self::TYPE_MONTH_FIXED_DAY  => 'Fixed day of month',
        self::TYPE_MONTH_LAST_DAY   => 'Last day of month',
        self::TYPE_PRIMO            => 'Quarterly Primo',
        self::TYPE_ULTIMO           => 'Quarterly Ultimo',
        self::TYPE_HALF_YEARLY      => 'Half-yearly',
        self::TYPE_START_DATE_12    => 'Yearly',
        self::TYPE_WEEKLY_FIXED_DAY => 'Fixed day of week',
        self::TYPE_MANUAL           => 'Manual',
    );

    public static $trial = array(
        ''          => 'No Trial',
        '7days'     => '7 days',
        '14days'    => '14 days',
        '1month'    => '1 month',
        'customize' => 'Customize',
    );

    public static $meta_fields = array(
        '_reepay_subscription_handle',
        '_reepay_subscription_choose',
        '_reepay_choose_exist',
        '_reepay_subscription_price',
        '_reepay_subscription_vat',
        '_reepay_subscription_schedule_type',
        '_reepay_subscription_daily',
        '_reepay_subscription_month_startdate',
        '_reepay_subscription_month_fixedday',
        '_reepay_subscription_month_lastday',
        '_reepay_subscription_primo',
        '_reepay_subscription_ultimo',
        '_reepay_subscription_half_yearly',
        '_reepay_subscription_month_startdate_12',
        '_reepay_subscription_weekly_fixedday',
        '_reepay_subscription_renewal_reminder',
        '_reepay_subscription_default_quantity',
        '_reepay_subscription_contract_periods',
        '_reepay_subscription_contract_periods_full',
        '_reepay_subscription_notice_period',
        '_reepay_subscription_notice_period_start',
        '_reepay_subscription_billing_cycles',
        '_reepay_subscription_billing_cycles_period',
        '_reepay_subscription_trial',
        '_reepay_subscription_fee',
        '_reepay_subscription_compensation',
    );

    public $params;

    public $default_params;

    /**
     * Constructor
     */
    public function __construct() {
        add_filter( 'product_type_selector', array( $this, 'add_reepay_type' ) );
        add_action( 'woocommerce_product_options_general_product_data', array( $this, 'subscription_pricing_fields' ) );
        add_action( 'save_post', array( $this, 'save_subscription_meta' ), 11 );
        add_filter( 'woocommerce_product_class', array( $this, 'reepay_load_subscription_product_class' ), 10, 2 );
        add_action( 'init', array( $this, 'reepay_create_subscription_product_class' ) );
        add_action( "woocommerce_reepay_simple_subscriptions_add_to_cart", array( $this, 'add_to_cart' ) );
    }

    public function add_to_cart() {
        $this->display_subscription_info();
        do_action( 'woocommerce_simple_add_to_cart' );
    }

    public function display_subscription_info() {
        global $product;

        wc_get_template(
            'plan-subscription-frontend.php',
            array(
                'product' => $product,
                'domain'  => reepay_s()->settings( 'domain' )
            ),
            '',
            reepay_s()->settings( 'plugin_path' ) . 'templates/'
        );
    }

    public function get_plan( $handle ) {
        try {
            $result = reepay_s()->api()->request( "plan/" . $handle . "/current" );

            return $result;
        }catch( Exception $e ) {
            $this->plan_error( $e->getMessage() );
        }

        return false;
    }

    public static function get_plans_wc() {
        $plansQuery = new WP_Query( [
            'post_type'   => 'product',
            'post_status' => 'publish',
            'meta_query'  => [
                [
                    'key'     => '_reepay_subscription_handle',
                    'compare' => 'EXISTS',
                ]
            ]
        ] );
        $plans      = [];
        foreach ( $plansQuery->posts as $item ) {
            $handle           = get_post_meta( $item->ID, '_reepay_subscription_handle', true );
            $plans[ $handle ] = $item->post_title;
        }

        return $plans;
    }

    public static function wc_get_plan( $handle ) {
        $query = new WP_Query( [
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'meta_query'     => [
                [
                    'key'   => '_reepay_subscription_handle',
                    'value' => $handle,
                ]
            ]
        ] );

        return $query->post??null;
    }


    /**
     * @return array|bool
     */
    public function get_reepay_plans_list() {
        try {
            return reepay_s()->api()->request( "plan?only_active=true" ) ?: false;
        }catch( Exception $e ) {
            $this->plan_error( $e->getMessage() );
        }

        return false;
    }

    /**
     * @param  int  $post_id
     *
     * @return array<string, mixed>
     */
    public function get_subscription_template_data( $post_id ) {
        $data = array(
            'plans_list' => $this->get_reepay_plans_list() ?: array(),
            'domain'     => reepay_s()->settings( 'domain' ),
        );

        foreach ( self::$meta_fields as $meta_field ) {
            $data[ $meta_field ] = get_post_meta( $post_id, $meta_field, true );
        }

        return $data;
    }

    public function subscription_pricing_fields() {
        global $post;
        $post_id = $post->ID;

        $data = $this->get_subscription_template_data( $post_id );

        if ( empty( $data['_reepay_subscription_choose'] ) ) {
            $data['_reepay_subscription_choose'] = 'new';
        }

        $data['is_update'] = ! empty( $data['_reepay_subscription_handle'] ) && $data['_reepay_subscription_choose'] == 'new';

        wc_get_template(
            'plan-subscription-fields.php',
            $data,
            '',
            reepay_s()->settings( 'plugin_path' ) . 'templates/'
        );
    }

    public function set_price( $post_id, $price ) {
        $_REQUEST['_regular_price'] = $price;
        update_post_meta( $post_id, '_regular_price', $price );
        update_post_meta( $post_id, '_price', $price );
    }

    /**
     * @param int    $post_id
     * @param string $key
     * @param mixed  $value
     *
     * @return bool|int
     */
    public function update_post_meta( $post_id, $key, $value ) {
        return update_post_meta( $post_id, $key, $value );
    }

    public function save_remote_plan( $post_id, $handle ) {
        $plan_data = $this->get_plan( $handle );
        if ( ! empty( $plan_data ) ) {
            $this->set_price( $post_id, $plan_data['amount']/100 ); //@todo уточнить нужно ли добавлять fee в цену или выводить отдельно

            if ( ! empty( $plan_data['amount'] ) ) {
                $this->update_post_meta( $post_id, '_reepay_subscription_price', intval( $plan_data['amount'] )/100 );
            }

            if ( ! empty( $plan_data['vat'] ) ) {
                $this->update_post_meta( $post_id, '_reepay_subscription_vat', $plan_data['vat'] );
            }

            if ( ! empty( $plan_data['setup_fee'] ) ) {
                $fee = [
                    'enabled'  => 'yes',
                    'amount'   => intval( $plan_data['setup_fee'] )/100,
                    'text'     => $plan_data['setup_fee_text'],
                    'handling' => $plan_data['setup_fee_handling'],
                ];

                $this->update_post_meta( $post_id, '_reepay_subscription_fee', $fee );
            }

            if ( ! empty( $plan_data['trial_interval_length'] ) ) {
                $type = '';
                if ( $plan_data['trial_interval_length'] == 7 && $plan_data['trial_interval_unit'] == 'days' ) {
                    $type = '7days';
                } elseif ( $plan_data['trial_interval_length'] == 14 && $plan_data['trial_interval_unit'] == 'days' ) {
                    $type = '14days';
                } elseif ( $plan_data['trial_interval_length'] == 1 && $plan_data['trial_interval_unit'] == 'months' ) {
                    $type = '1month';
                }

                $trial = [
                    'type'     => $type,
                    'length'   => $plan_data['trial_interval_length'],
                    'unit'     => $plan_data['trial_interval_unit'],
                    'reminder' => $plan_data['trial_reminder_email_days'],
                ];

                $this->update_post_meta( $post_id, '_reepay_subscription_trial', $trial );
            }

            if ( $plan_data["fixed_count"] ) {
                $this->update_post_meta( $post_id, '_reepay_subscription_billing_cycles', 'true' );
                $this->update_post_meta( $post_id, '_reepay_subscription_billing_cycles_period', $plan_data["fixed_count"] );
            } else {
                $this->update_post_meta( $post_id, '_reepay_subscription_billing_cycles', 'false' );
            }

            if ( ! empty( $plan_data['notice_periods'] ) ) {
                $this->update_post_meta( $post_id, '_reepay_subscription_notice_period', $plan_data['notice_periods'] );
            }

            if ( isset( $plan_data['notice_periods_after_current'] ) ) {
                $this->update_post_meta( $post_id, '_reepay_subscription_notice_period_start', $plan_data['notice_periods_after_current'] ? 'true' : 'false' );
            }

            if ( ! empty( $plan_data['fixation_periods'] ) ) {
                $this->update_post_meta( $post_id, '_reepay_subscription_contract_periods', $plan_data['fixation_periods'] );
            }

            if ( isset( $plan_data['fixation_periods_full'] ) ) {
                $this->update_post_meta( $post_id, '_reepay_subscription_contract_periods_full', $plan_data['fixation_periods_full'] ? 'true' : 'false' );
            }

            if ( ! empty( $plan_data['quantity'] ) ) {
                $this->update_post_meta( $post_id, '_reepay_subscription_default_quantity', $plan_data['quantity'] );
            }

            if ( ! empty( $plan_data['renewal_reminder_email_days'] ) ) {
                $this->update_post_meta( $post_id, '_reepay_subscription_renewal_reminder', $plan_data['renewal_reminder_email_days'] );
            }

            if ( ! empty( $plan_data['schedule_type'] ) ) {
                $type = $plan_data['schedule_type'];

                if ( ! empty( $plan_data['schedule_fixed_day'] ) && ! empty( $plan_data['interval_length'] ) && $plan_data['schedule_type'] == 'month_fixedday' ) {
                    if ( $plan_data['schedule_fixed_day'] == 28 ) {
                        $type = 'ultimo';
                    } elseif ( $plan_data['schedule_fixed_day'] == 1 ) {
                        if ( $plan_data['interval_length'] == 3 ) {
                            $type = 'primo';
                        } elseif ( $plan_data['interval_length'] == 6 ) {
                            $type = 'half_yearly';
                        } elseif ( $plan_data['interval_length'] == 12 ) {
                            $type = 'month_startdate_12';
                        }
                    }
                }

                $this->update_post_meta( $post_id, '_reepay_subscription_schedule_type', $type );
            }


            if ( ! empty( $plan_data['interval_length'] ) ) {
                $this->update_post_meta( $post_id, '_reepay_subscription_daily', $plan_data['interval_length'] );
                $this->update_post_meta( $post_id, '_reepay_subscription_month_startdate', $plan_data['interval_length'] );

                $type_data      = [
                    'month'             => $plan_data['interval_length'],
                    'day'               => ! empty( $plan_data['schedule_fixed_day'] ) ? $plan_data['schedule_fixed_day'] : '',
                    'period'            => ! empty( $plan_data['partial_period_handling'] ) ? $plan_data['partial_period_handling'] : '',
                    'proration'         => $plan_data['proration'] ? 'full_day' : 'by_minute',
                    'proration_minimum' => ! empty( $plan_data['minimum_prorated_amount'] ) ? $plan_data['minimum_prorated_amount'] : '',

                ];

                $this->update_post_meta( $post_id, '_reepay_subscription_month_fixedday', $type_data );

                unset( $type_data['day'] );
                $this->update_post_meta( $post_id, '_reepay_subscription_month_lastday', $type_data );

                unset( $type_data['month'] );
                $this->update_post_meta( $post_id, '_reepay_subscription_primo', $type_data );
                $this->update_post_meta( $post_id, '_reepay_subscription_month_startdate_12', $type_data );
                $this->update_post_meta( $post_id, '_reepay_subscription_half_yearly', $type_data );
                $this->update_post_meta( $post_id, '_reepay_subscription_ultimo', $type_data );


                $type_data['week'] = $plan_data['interval_length'];
                $type_data['day']  = ! empty( $plan_data['schedule_fixed_day'] ) ? $plan_data['schedule_fixed_day'] : '';
                $this->update_post_meta( $post_id, '_reepay_subscription_weekly_fixedday', $type_data );
            }
        } else {
            $this->plan_error( __( 'Plan not found', reepay_s()->settings( 'domain' ) ) );
        }
    }

    public function save_subscription_meta( $post_id ) {
        if ( empty( $_REQUEST['product-type'] ) ) {
            return;
        }

        if ( $_REQUEST['product-type'] != 'reepay_simple_subscriptions' ) {
            return;
        }

        if ( ! empty( $_REQUEST ) ) {
            if ( ! empty( $_REQUEST['_reepay_subscription_choose'] ) && $_REQUEST['_reepay_subscription_choose'] == 'exist' ) {
                if ( ! empty( $_REQUEST['_reepay_choose_exist'] ) ) {
                    update_post_meta( $post_id, '_reepay_subscription_handle', $_REQUEST['_reepay_choose_exist'] );
                    update_post_meta( $post_id, '_reepay_choose_exist', $_REQUEST['_reepay_choose_exist'] );
                    update_post_meta( $post_id, '_reepay_subscription_choose', $_REQUEST['_reepay_subscription_choose'] );

                    $this->save_remote_plan( $post_id, $_REQUEST['_reepay_choose_exist'] );
                } else {
                    $this->plan_error( __( 'Please choose the plan', reepay_s()->settings( 'domain' ) ) );
                }
            } else {

                if ( get_post_meta( $post_id, '_reepay_subscription_choose', true ) == 'exist' ) {
                    delete_post_meta( $post_id, '_reepay_subscription_handle' );
                }

                if ( ! empty( $_REQUEST['_reepay_subscription_price'] ) ) {
                    $this->set_price( $post_id, $_REQUEST['_reepay_subscription_price'] );
                }

                $title = get_the_title( $post_id );
                if ( ! empty( $title ) && $title != 'AUTO-DRAFT' ) {
                    $handle               = get_post_meta( $post_id, '_reepay_subscription_handle', true );
                    $this->default_params = $this->get_default_params( $post_id );
                    if ( ! empty( $handle ) ) {
                        if ( $this->update_plan( $handle ) ) {
                            $this->save_meta( $post_id );
                        }
                    } else {
                        $handle = 'wc_subscription_' . $post_id;
                        if ( $this->save_meta( $post_id ) ) {
                            $this->params = $this->get_params( $post_id );
                            $this->create_plan( $post_id, $handle );
                        }
                    }
                }
            }
        }
    }

    public function save_meta( $post_id ) {
        foreach ( $_REQUEST as $i => $value ) {
            if ( strpos( $i, 'reepay_subscription' ) ) {
                update_post_meta( $post_id, $i, $value );
            }
        }

        return true;
    }

    public function update_plan( $handle ) {
        $params = $this->params;
        try {
            $result = reepay_s()->api()->request( "plan/$handle", 'PUT', $params );

            return true;
        }catch( Exception $e ) {
            $this->plan_error( $e->getMessage() );
        }

        return false;
    }

    public function get_type( $type ) {
        if ( $type == 'primo' || $type == 'ultimo' || $type == 'half_yearly' || $type == 'month_startdate_12' ) {
            return 'month_fixedday';
        }

        return $type;
    }

    public function get_params( $post_id ) {
        $handle = 'wc_subscription_' . $post_id;

        $params = $this->default_params;

        $type      = get_post_meta( $post_id, '_reepay_subscription_schedule_type', true );
        $type_data = get_post_meta( $post_id, '_reepay_subscription_' . $type, true );

        $params['amount']        = floatval( get_post_meta( $post_id, '_reepay_subscription_price', true ) )*100;
        $params['handle']        = $handle;
        $params['quantity']      = intval( get_post_meta( $post_id, '_reepay_subscription_default_quantity', true ) );
        $params['schedule_type'] = $this->get_type( $type );
        //$params['fixed_life_time_unit'] = ''; //@todo Уточнить что за поле в админке
        //$params['fixed_life_time_length'] = ''; //@todo Уточнить что за поле в админке
        //$params['fixed_trial_days'] = ''; //@todo Уточнить что за поле в админке

        $billing_cycles = get_post_meta( $post_id, '_reepay_subscription_billing_cycles', true );
        if ( $billing_cycles == 'true' ) {
            $params['fixed_count'] = intval( get_post_meta( $post_id, '_reepay_subscription_billing_cycles_period', true ) );
        }

        $trial = get_post_meta( $post_id, '_reepay_subscription_trial', true );
        if ( ! empty( $trial['type'] ) ) {
            if ( $trial['type'] == 'customize' ) {
                $params['trial_interval_unit']   = $trial['unit'];
                $params['trial_interval_length'] = intval( $trial['length'] );
            } else {
                if ( $trial['type'] == '7days' ) {
                    $params['trial_interval_unit']   = 'days';
                    $params['trial_interval_length'] = 7;
                } elseif ( $trial['type'] == '14days' ) {
                    $params['trial_interval_unit']   = 'days';
                    $params['trial_interval_length'] = 14;
                } elseif ( $trial['type'] == '1month' ) {
                    $params['trial_interval_unit']   = 'months';
                    $params['trial_interval_length'] = 1;
                }
            }
        }

        $vat = get_post_meta( $post_id, '_reepay_subscription_vat', true );
        if ( $vat == 'include' ) {
            $params['amount_incl_vat'] = true;
        } else {
            $params['amount_incl_vat'] = false;
        }

        $fixation_periods = get_post_meta( $post_id, '_reepay_subscription_contract_periods', true );
        if ( $fixation_periods ) {
            $params['fixation_periods']      = intval( $fixation_periods );
            $fixation_periods_full           = get_post_meta( $post_id, '_reepay_subscription_contract_periods_full', true );
            $params['fixation_periods_full'] = $fixation_periods_full == 'true';
        }

        $notice_periods = get_post_meta( $post_id, '_reepay_subscription_notice_period', true );
        if ( $notice_periods ) {
            $params['notice_periods']               = intval( $notice_periods );
            $notice_period_start                    = get_post_meta( $post_id, '_subscription_notice_period_start', true );
            $params['notice_periods_after_current'] = $notice_period_start == 'true';
        }

        if ( $type == 'month_fixedday' || $type == 'weekly_fixedday' ) {
            $params['schedule_fixed_day'] = intval( $type_data['day'] );
        } elseif ( $type == 'primo' || $type == 'half_yearly' || $type == 'month_startdate_12' ) {
            $params['schedule_fixed_day'] = 1;
        } elseif ( $type == 'ultimo' ) {
            $params['schedule_fixed_day'] = 28;
        }

        if ( $length = intval( self::get_interval( $post_id, $type, $type_data ) ) ) {
            $params['interval_length'] = $length;
        }

        return $params;
    }

    public function create_plan( $post_id, $handle ) {
        $params = $this->params;

        try {
            $result = reepay_s()->api()->request( 'plan', 'POST', $params );
            update_post_meta( $post_id, '_reepay_subscription_handle', $handle );

            return true;
        }catch( Exception $e ) {
            $this->plan_error( $e->getMessage() );
        }

        return false;
    }

    public function get_default_params( $post_id ) {

        $type      = get_post_meta( $post_id, '_reepay_subscription_schedule_type', true );
        $type_data = get_post_meta( $post_id, '_reepay_subscription_' . $type, true );

        $params = [
            'name'        => get_the_title( $post_id ),
            'description' => get_post_field( 'post_content', $post_id ),
            //'fixed_trial_days' => '', //@todo Уточнить что за поле в админке
        ];

        if ( ! empty( $_REQUEST['_reepay_subscription_renewal_reminder'] ) ) {
            $params['renewal_reminder_email_days'] = intval( $_REQUEST['_reepay_subscription_renewal_reminder'] );
        }

        if ( ! empty( $_REQUEST['_reepay_subscription_trial'] ) && ! empty( $_REQUEST['_reepay_subscription_trial']['reminder'] ) ) {
            $params['trial_reminder_email_days'] = intval( $_REQUEST['_reepay_subscription_trial']['reminder'] );
        }

        if ( is_array( $type_data ) && ! empty( $type_data['period'] ) ) {
            $params['partial_period_handling'] = $type_data['period'];
        }

        if ( ! empty( $_REQUEST['_reepay_subscription_fee'] ) ) {
            $fee                          = $_REQUEST['_reepay_subscription_fee'];
            $params['setup_fee']          = ! empty( $fee['amount'] ) ? floatval( $fee['amount'] )*100 : 0;
            $params['setup_fee_text']     = ! empty( $fee['text'] ) ? $fee['text'] : '';
            $params['setup_fee_handling'] = ! empty( $fee['handling'] ) ? $fee['handling'] : '';
        }

        if ( ! empty( $type_data['proration'] ) ) {
            if ( $type_data['proration'] == 'full_day' ) {
                $params['partial_proration_days'] = true;
            } else {
                $params['partial_proration_days'] = false;
            }
        }

        if ( ! empty( $type_data['proration_minimum'] ) ) {
            $params['minimum_prorated_amount'] = floatval( $type_data['proration_minimum'] );
        }

        return $params;
    }

    public static function get_interval( $post_id, $type, $type_data ) {
        if ( $type == 'daily' ) {
            return get_post_meta( $post_id, '_reepay_subscription_daily', true );
        } elseif ( $type == 'month_startdate' ) {
            return get_post_meta( $post_id, '_reepay_subscription_month_startdate', true );
        } elseif ( $type == 'month_fixedday' || $type == 'month_lastday' ) {
            return $type_data['month'];
        } elseif ( $type == 'weekly_fixedday' ) {
            return $type_data['week'];
        } elseif ( $type == 'primo' || $type == 'ultimo' ) {
            return 3;
        } elseif ( $type == 'half_yearly' ) {
            return 6;
        } elseif ( $type == 'month_startdate_12' ) {
            return 12;
        } else {
            return false;
        }
    }

    public function reepay_load_subscription_product_class( $php_classname, $product_type ) {
        if ( $product_type == 'reepay_simple_subscriptions' ) {
            $php_classname = 'WC_Product_Reepay_Simple_Subscription';
        }

        return $php_classname;
    }

    public function add_reepay_type( $types ) {
        $types['reepay_simple_subscriptions'] = __( 'Reepay Simple Subscription', reepay_s()->settings( 'domain' ) );

        return $types;
    }

    public function reepay_create_subscription_product_class() {
        include_once( reepay_s()->settings( 'plugin_path' ) . '/includes/class-wc-reepay-plan-simple-product.php' );
    }

    protected function plan_error( $message ) {
        if ( is_ajax() ) {
            WC_Admin_Meta_Boxes::add_error( $message );
        } else {
            WC_Reepay_Subscription_Admin_Notice::add_notice( $message );
        }
    }
}

new WC_Reepay_Subscription_Plan_Simple();