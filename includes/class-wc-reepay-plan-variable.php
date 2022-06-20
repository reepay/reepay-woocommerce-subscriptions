<?php
class WC_Reepay_Subscription_Plan_Variable extends WC_Reepay_Subscription_Plan_Simple {
	public $loop = 0;

    public function create_subscription_product_class(){
        include_once( reepay_s()->settings('plugin_path') . '/includes/class-wc-reepay-plan-variable-product.php' );
    }

    public function load_subscription_product_class($php_classname, $product_type){
        if ( $product_type == 'reepay_variable_subscriptions' ) {
            $php_classname = 'WC_Product_Reepay_Variable_Subscription';
        }
        return $php_classname;
    }

    public function add_subscription_product_type( $types ){
        $types['reepay_variable_subscriptions'] = __( 'Reepay Variable Subscription', reepay_s()->settings('domain') );

        return $types;
    }

    protected function register_actions() {
        add_action( 'woocommerce_variation_options_pricing', array( $this, 'add_custom_field_to_variations' ), 10, 3 );
        add_action( 'woocommerce_save_product_variation', array( $this, 'save_reepay_variation' ), 10, 2 );
    }

    /**
     * @param int    $post_id
     * @param string $key
     * @param mixed  $value
     *
     * @return bool|int
     */
    public function update_post_meta( $post_id, $key, $value ) {
        $value = get_post_meta( $post_id, $key, true );

        if ( empty( $value ) ) {
            return false;
        }

        $value[ $this->loop ] = $value;

        return parent::update_post_meta( $post_id, $key, $value );
    }

	/**
	 * @param  int  $post_id
	 *
	 * @return array<string, mixed>
	 */
	public function get_subscription_template_data( $post_id ) {
		$data = parent::get_subscription_template_data( $post_id );

		$data['variable'] = true;

		foreach ( self::$meta_fields as $meta_field ) {
			$data[ $meta_field ] = $data[ $meta_field ][$this->loop];
		}

		return $data;
	}

	public function save_reepay_variation($variation_id, $i){
        if(empty($_REQUEST['product-type'])){
            return;
        }

        if($_REQUEST['product-type'] != 'reepay_variable_subscriptions'){
            return;
        }

        if(!empty($_REQUEST['_reepay_subscription_choose']) && $_REQUEST['_reepay_subscription_choose'][$i] == 'exist'){
            if(!empty($_REQUEST['_reepay_choose_exist'])){
                update_post_meta( $variation_id, '_reepay_subscription_handle', $_REQUEST['_reepay_choose_exist'] );
                update_post_meta( $variation_id, '_reepay_choose_exist', $_REQUEST['_reepay_choose_exist'] );
                update_post_meta( $variation_id, '_reepay_subscription_choose', $_REQUEST['_reepay_subscription_choose'] );

                $this->loop = $i;
                $this->save_remote_plan($variation_id, $_REQUEST['_reepay_choose_exist'][$i]);
            }else{
                $this->plan_error(__( 'Please choose the plan', reepay_s()->settings('domain') ));
            }
        }else{

            /*if(get_post_meta($variation_id, '_reepay_subscription_choose', true)[$i] == 'exist'){
                delete_post_meta( $variation_id, '_reepay_subscription_handle' );
            }*/

            if(!empty($_REQUEST['_reepay_subscription_price'])){
                $this->set_price($variation_id, $_REQUEST['_reepay_subscription_price'][$i]);
            }

            $title = get_the_title( $variation_id );
            if(!empty($title) && $title != 'AUTO-DRAFT'){
                $handle = get_post_meta($variation_id, '_reepay_subscription_handle', true);
                $this->default_params = $this->get_default_params_variable($variation_id, $i);

                if(!empty($handle)){
                    if($this->update_plan($handle)) $this->save_meta($variation_id);
                }else{
                    $handle = get_post_meta($variation_id, '_reepay_subscription_handle', true);
                    $this->default_params = $this->get_default_params_variable($variation_id, $i);
                    if(!empty($handle)){
                        if($this->update_plan($handle)) $this->save_meta($variation_id);
                    }else{
                        $handle = 'wc_subscription_'.$i.'_'.$variation_id;
                        if($this->save_meta($variation_id)){
                            $this->params = $this->get_params_variable($variation_id, $i);
                            $this->create_plan($variation_id, $handle);
                        }
                    }
                }
            }
        }
    }

    public function get_params_variable($post_id, $i){
        $handle = 'wc_subscription_'.$i.'_'.$post_id;

        $params = $this->default_params;

        $type = get_post_meta($post_id, '_reepay_subscription_schedule_type', true)[$i];
        $type_data = get_post_meta($post_id, '_reepay_subscription_'.$type, true)[$i];

        $params['amount'] = floatval(get_post_meta($post_id, '_reepay_subscription_price', true)[$i]) * 100;
        $params['handle'] = $handle;
        $params['quantity'] = intval(get_post_meta($post_id, '_reepay_subscription_default_quantity', true)[$i]);
        $params['schedule_type'] = $this->get_type($type);
        //$params['fixed_life_time_unit'] = ''; //@todo Уточнить что за поле в админке
        //$params['fixed_life_time_length'] = ''; //@todo Уточнить что за поле в админке
        //$params['fixed_trial_days'] = ''; //@todo Уточнить что за поле в админке

        $billing_cycles = get_post_meta($post_id, '_reepay_subscription_billing_cycles', true);
        if($billing_cycles == 'true'){
            $params['fixed_count'] = intval(get_post_meta($post_id, '_reepay_subscription_billing_cycles_period', true)[$i]);
        }

        $trial = get_post_meta($post_id, '_reepay_subscription_trial', true)[$i];
        if(!empty($trial['type'])){
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

        $vat = get_post_meta($post_id, '_reepay_subscription_vat', true)[$i];
        if($vat == 'include'){
            $params['amount_incl_vat'] = true;
        }else{
            $params['amount_incl_vat'] = false;
        }

        $fixation_periods = get_post_meta($post_id, '_reepay_subscription_contract_periods', true)[$i];
        if($fixation_periods){
            $params['fixation_periods'] = intval($fixation_periods);
            $fixation_periods_full = get_post_meta($post_id, '_reepay_subscription_contract_periods_full', true)[$i];
            $params['fixation_periods_full'] = boolval($fixation_periods_full);
        }

        $notice_periods = get_post_meta($post_id, '_reepay_subscription_notice_period', true)[$i];
        if($notice_periods){
            $params['notice_periods'] = intval($notice_periods);
            $notice_period_start = get_post_meta($post_id, '_subscription_notice_period_start', true)[$i];
            $params['notice_periods_after_current'] = boolval($notice_period_start);
        }

        if($type == 'month_fixedday' || $type == 'weekly_fixedday'){
            $params['schedule_fixed_day'] = intval($type_data['day']);
        }elseif($type == 'primo' || $type == 'half_yearly' || $type == 'month_startdate_12'){
            $params['schedule_fixed_day'] = 1;
        }elseif($type == 'ultimo'){
            $params['schedule_fixed_day'] = 28;
        }

        if($length = intval(self::get_interval($post_id, $type, $type_data))){
            $params['interval_length'] = $length;
        }

        return $params;
    }

    public function get_default_params_variable($post_id, $i){

        $type = get_post_meta($post_id, '_reepay_subscription_schedule_type', true)[$i];
        $type_data = get_post_meta($post_id, '_reepay_subscription_'.$type, true)[$i];

        $params = [
            'name' => get_the_title( $post_id ),
            'description' => get_post_field( 'post_content', $post_id ),
            //'fixed_trial_days' => '', //@todo Уточнить что за поле в админке
        ];

        if(!empty($_REQUEST['_reepay_subscription_renewal_reminder'][$i])){
            $params['renewal_reminder_email_days'] = intval($_REQUEST['_reepay_subscription_renewal_reminder'][$i]);
        }

        if(!empty($_REQUEST['_reepay_subscription_trial'][$i]) && !empty($_REQUEST['_reepay_subscription_trial'][$i]['reminder'])){
            $params['trial_reminder_email_days'] = intval($_REQUEST['_reepay_subscription_trial'][$i]['reminder']);
        }

        if(is_array($type_data) && !empty($type_data['period'])){
            $params['partial_period_handling'] = $type_data['period'];
        }

        if(!empty($_REQUEST['_reepay_subscription_fee'][$i])){
            $fee = $_REQUEST['_reepay_subscription_fee'][$i];
            $params['setup_fee'] = !empty($fee['amount']) ? floatval($fee['amount']) * 100 : 0;
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

        return $params;
    }

    public function add_custom_field_to_variations( $loop, $variation_data, $variation ) {
		$this->loop = $loop;
        $this->subscription_pricing_fields();
    }
}

new WC_Reepay_Subscription_Plan_Variable();