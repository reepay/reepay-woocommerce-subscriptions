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
        add_action( 'woocommerce_save_product_variation', array( $this, 'save_subscription_meta' ), 10, 2 );
    }

	/**
	 * @param  int  $post_id
	 *
	 * @return array<string, mixed>
	 */
	public function get_subscription_template_data( $post_id ) {
		$data = parent::get_subscription_template_data( $post_id );

		$data['variable'] = true;
		$data['loop'] = $this->loop;

		return $data;
	}

	public function save_subscription_meta($post_id, $i = null){
        $this->loop = $i;

        parent::save_subscription_meta( $post_id );
    }

    public function generate_subscription_handle( $post_id) {
        return 'wc_subscription_'.$this->loop.'_'.$post_id;
    }

    public function get_params( $post_id ) {
        return parent::get_params( $post_id );
    }

    public function get_default_params($post_id){

        $type = get_post_meta($post_id, '_reepay_subscription_schedule_type', true);
        $type_data = get_post_meta($post_id, '_reepay_subscription_'.$type, true);

        $params = [
            'name' => get_the_title( $post_id ),
            'description' => get_post_field( 'post_content', $post_id ),
            //'fixed_trial_days' => '', //@todo Уточнить что за поле в админке
        ];

        if(!empty($_REQUEST['_reepay_subscription_renewal_reminder'][$this->loop])){
            $params['renewal_reminder_email_days'] = intval($_REQUEST['_reepay_subscription_renewal_reminder'][$this->loop]);
        }

        if(!empty($_REQUEST['_reepay_subscription_trial'][$this->loop]) && !empty($_REQUEST['_reepay_subscription_trial'][$this->loop]['reminder'])){
            $params['trial_reminder_email_days'] = intval($_REQUEST['_reepay_subscription_trial'][$this->loop]['reminder']);
        }

        if(is_array($type_data) && !empty($type_data['period'])){
            $params['partial_period_handling'] = $type_data['period'];
        }

        if(!empty($_REQUEST['_reepay_subscription_fee'][$this->loop])){
            $fee = $_REQUEST['_reepay_subscription_fee'][$this->loop];
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

    public function is_reepay_product_saving() {
        return empty( $_REQUEST ) ||
               empty( $_REQUEST['product-type'] ) ||
               $_REQUEST['product-type'] != 'reepay_variable_subscriptions' ||
               empty( $_REQUEST['_reepay_subscription_choose'] );
    }

    public function get_meta_from_request() {
        $data = [];

        foreach ( self::$meta_fields as $key ) {
            if ( isset( $_REQUEST[ $key ] ) ) {
                $data[ $key ] = $_REQUEST[ $key ][ $this->loop ] ?? '';
            }
        }

        return $data;
    }

    public function save_meta_from_request( $post_id ) {
        foreach ( self::$meta_fields as $key ) {
            if ( isset( $_REQUEST[ $key ] ) ) {
                update_post_meta( $post_id, $key, $_REQUEST[ $key ][ $this->loop ] ?? '' );
            }
        }
    }

    /**
     * @param int     $loop           Position in the loop.
     * @param array   $variation_data Variation data.
     * @param WP_Post $variation      Post data.
     */
    public function add_custom_field_to_variations( $loop, $variation_data, $variation ) {
        global $post;
        $_post = $post;
        $post = $variation;

		$this->loop = $loop;
        $this->subscription_pricing_fields();

        $post = $_post;
    }
}

new WC_Reepay_Subscription_Plan_Variable();