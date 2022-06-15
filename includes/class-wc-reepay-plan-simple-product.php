<?php
class WC_Product_Reepay_Simple_Subscription extends WC_Product_Simple {


    public static $types_info = array(
        WC_Reepay_Subscription_Plans::TYPE_DAILY => 'For %s may(s)',
        WC_Reepay_Subscription_Plans::TYPE_MONTH_START_DATE => 'For %s month(s), on the first day of the month',
        WC_Reepay_Subscription_Plans::TYPE_MONTH_FIXED_DAY => 'For %s month(s)',
        WC_Reepay_Subscription_Plans::TYPE_MONTH_LAST_DAY => 'For %s month(s), on the last day of the month',
        WC_Reepay_Subscription_Plans::TYPE_PRIMO => 'For %s month(s), on the first day of the month, fixed months Jan, Apr, Jul, Oct',
        WC_Reepay_Subscription_Plans::TYPE_ULTIMO => 'For %s month(s), on the last day of the month, fixed months Jan, Apr, Jul, Oct',
        WC_Reepay_Subscription_Plans::TYPE_HALF_YEARLY => 'For %s month(s), on the first day of the month, fixed months Jan, Jul',
        WC_Reepay_Subscription_Plans::TYPE_START_DATE_12 => 'For %s month(s), on the first day of the month, fixed months Jan',
        WC_Reepay_Subscription_Plans::TYPE_WEEKLY_FIXED_DAY => 'For %s Week',
        WC_Reepay_Subscription_Plans::TYPE_MANUAL => 'Manual',
    );

    public function get_type() {
        return 'reepay_simple_subscriptions';
    }



    public function reepay_get_billing_plan() {
        $type = $this->get_meta('_reepay_subscription_schedule_type');
        $type_data = $this->get_meta('_reepay_subscription_'.$type);
        $interval = WC_Reepay_Subscription_Plans::get_interval($this->get_id(), $type, $type_data);
        $type_str = self::$types_info[$type];
        $ret = '';
        if(!empty($type_str)){
            $ret = sprintf(
                __($type_str, reepay_s()->settings('domain')),
                $interval
            );
        }

        return $ret;
    }


    public function reepay_get_trial() {
        $trial = $this->get_meta('_reepay_subscription_trial');
        $ret = '';

        if(!empty($trial['type'])){
            if($trial['type'] != 'customize'){
                $ret = 'Trial period - '.WC_Reepay_Subscription_Plans::$trial[$trial['type']];
            }else{
                $ret = 'Trial period - '.$trial['length'].' '.$trial['unit'];
            }
        }

        return $ret;
    }

    /**
     * Get subscription's price HTML.
     *
     * @return string containing the formatted price
     */
    public function get_price_html( $price = '' ) {

        return parent::get_price_html( $price );
    }

}