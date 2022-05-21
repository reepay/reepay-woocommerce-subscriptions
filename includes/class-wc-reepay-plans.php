<?php

class WC_Reepay_Subscription_Plans{

    public static $schedule_types = array(
        'daily' => 'Day(s)',
        'month_startdate' => 'Month(s)',
        'month_fixedday' => 'Fixed day of month',
        'month_lastday' => 'Last day of month',
        'primo' => 'Quarterly Primo',
        'ultimo' => 'Quarterly Ultimo',
        'half_yearly' => 'Half-yearly',
        'month_startdate_12' => 'Yearly',
        'weekly_fixedday' => 'Fixed day of week',
        'manual' => 'Manual',
    );

    public static $trial = array(
        '' => 'No Trial',
        '7days' => '7 days',
        '14days' => '14 days',
        '1month' => '1 month',
        'customize' => 'Customize',
    );

    /**
     * Constructor
     */
    public function __construct() {
        add_filter( 'product_type_selector', array( $this, 'add_reepay_type' ) );
        add_action( 'woocommerce_product_options_general_product_data', array( $this, 'subscription_pricing_fields' ) );
        add_action( 'save_post', array( $this, 'save_subscription_meta' ), 11 );
        add_filter( 'woocommerce_product_class', array( $this, 'reepay_load_subscription_product_class' ),10,2);
    }

    public function subscription_pricing_fields(){
        global $post;

        $meta = get_post_meta( $post->ID );

        wc_get_template(
            'simple-subscription-fields.php',
            array(
                'meta' => $meta
            ),
            '',
            WC_Reepay_Subscriptions::$plugin_path.'templates/'
        );
    }

    public function save_subscription_meta($post_id){
        if(!empty($_POST['product-type']) && $_POST['product-type'] != 'reepay_simple_subscriptions'){
            return;
        }

        if(!empty($_REQUEST)){
            foreach ($_REQUEST as $i => $value){
                if(strpos($i, 'reepay_subscription')){
                    update_post_meta( $post_id, $i, $value );
                }
            }
        }

        $this->create_plan($post_id);

    }


    public function create_plan($post_id){

        $type = get_post_meta($post_id, '_reepay_subscription_schedule_type', true);
        $handle = 'wc_subscription_'.$post_id;
        $params = [
            'name' => get_the_title( $post_id ),
            'description' => get_post_field( 'post_content', $post_id ),
            'amount' => floatval(get_post_meta($post_id, '_reepay_subscription_price', true)),
            'handle' => $handle,
            'quantity' => intval(get_post_meta($post_id, '_reepay_subscription_renewal_reminder', true)),
            'renewal_reminder_email_days' => intval(get_post_meta($post_id, '_reepay_subscription_default_quantity', true)),
            'schedule_type' => $type,
            //'fixed_life_time_unit' => '', //@todo Уточнить что за поле в админке
            //'fixed_life_time_length' => '', //@todo Уточнить что за поле в админке
        ];

        $billing_cycles = boolval(get_post_meta($post_id, '_reepay_subscription_billing_cycles', true));
        if($billing_cycles){
            $params['fixed_count'] = intval(get_post_meta($post_id, '_reepay_subscription_billing_cycles_period', true));
        }

        $trial = get_post_meta($post_id, '_reepay_subscription_trial', true);
        if(!empty($trial['type'])){
            $params['trial_reminder_email_days'] = !empty($trial['reminder']) ? intval($trial['reminder']) : 7;
            if($trial['type'] == 'customize'){
                $params['trial_interval_unit'] = $trial['unit'];
                $params['trial_interval_length'] = intval($trial['length']);
            }else{
                if($trial['type'] == '7days'){
                    $params['trial_interval_unit'] = 'days';
                    $params['trial_interval_length'] = 7;
                }elseif($trial['type'] == '14days'){
                    $params['trial_interval_unit'] = 'days';
                    $params['trial_interval_length'] = 14;
                }elseif($trial['type'] == '1month'){
                    $params['trial_interval_unit'] = 'months';
                    $params['trial_interval_length'] = 1;
                }
            }
        }

        $type_data = get_post_meta($post_id, '_reepay_subscription_'.$type, true);
        if(is_array($type_data) && !empty($type_data['period'])){
            $params['partial_period_handling'] = $type_data['period'];
        }

        $fee = get_post_meta($post_id, '_reepay_subscription_fee', true);
        if(!empty($fee)){
            $params['setup_fee'] = !empty($fee['amount']) ? floatval($fee['amount']) : 0;
            $params['setup_fee_text'] = !empty($fee['text']) ? $fee['text'] : '';
            $params['setup_fee_handling'] = !empty($fee['handling']) ? $fee['handling'] : '';
        }

        if(!empty($type_data['proration'])){
            if($type_data['proration'] == 'full_day'){
                $params['partial_proration_days'] = true;
            }else{
                $params['partial_proration_days'] = false;
            }
        }

        if(!empty($type_data['proration_minimum'])){
            $params['minimum_prorated_amount'] = floatval($type_data['proration_minimum']);
        }

        $vat = get_post_meta($post_id, '_reepay_subscription_vat', true);
        if($vat == 'include'){
            $params['amount_incl_vat'] = true;
        }else{
            $params['amount_incl_vat'] = false;
        }

        $fixation_periods = get_post_meta($post_id, '_reepay_subscription_contract_periods', true);
        if($fixation_periods){
            $params['fixation_periods'] = intval($fixation_periods);
            $fixation_periods_full = get_post_meta($post_id, '_reepay_subscription_contract_periods_full', true);
            $params['fixation_periods_full'] = boolval($fixation_periods_full);
        }

        $notice_periods = get_post_meta($post_id, '_reepay_subscription_notice_period', true);
        if($notice_periods){
            $params['notice_periods'] = intval($notice_periods);
            $notice_period_start = get_post_meta($post_id, '_subscription_notice_period_start', true);
            $params['notice_periods_after_current'] = boolval($notice_period_start);
        }

        if($type == 'month_fixedday' || $type == 'weekly_fixedday'){
            $params['schedule_fixed_day'] = intval($type_data['day']);
        }

        if($length = intval($this->get_interval($post_id, $type, $type_data))){
            $params['interval_length'] = $length;
        }

        $api = new WC_Reepay_Subscription_API();
        $api->set_params($params);

        try{
            $result = $api->request('POST', 'https://api.reepay.com/v1/plan');
            update_post_meta($post_id, '_reepay_subscription_handle', $handle);
        }catch (Exception $e){
            //WC_Admin_Notices::add_custom_notice( 'reepay_subscription_plan',$e->getMessage() );
            var_dump($e->getMessage());
            return;
        }
    }


    public function get_interval($post_id, $type, $type_data){
        if($type == 'daily'){
            return get_post_meta($post_id, '_reepay_subscription_daily', true);
        }elseif($type == 'month_startdate'){
            return get_post_meta($post_id, '_reepay_subscription_month_startdate', true);
        }elseif($type == 'month_fixedday' || $type == 'month_lastday'){
            return $type_data['month'];
        }elseif($type == 'weekly_fixedday'){
            return $type_data['week'];
        }else{ //@todo Primo, ultimo, half_yearly, month_startdate_12, manual
            return false;
        }
    }

    public function reepay_load_subscription_product_class($php_classname, $product_type){
        if ( $product_type == 'reepay_simple_subscriptions' ) {
            $php_classname = 'WC_Product_Reepay_Simple_Subscription';
        }
        return $php_classname;
    }

    public function add_reepay_type( $types ){
        $types['reepay_simple_subscriptions'] = __( 'Reepay Simple Subscription', WC_Reepay_Subscriptions::$domain );
        $types['reepay_variable_subscriptions'] = __( 'Reepay Variable Subscription', WC_Reepay_Subscriptions::$domain );

        return $types;
    }
}

new WC_Reepay_Subscription_Plans();

add_action( 'init', 'reepay_create_subscription_product_class' );

function reepay_create_subscription_product_class(){
    class WC_Product_Reepay_Simple_Subscription extends WC_Product {
        public function get_type() {
            return 'reepay_simple_subscriptions'; // so you can use $product = wc_get_product(); $product->get_type()
        }
    }
}