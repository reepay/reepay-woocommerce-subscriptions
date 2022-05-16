<?php
?>
<div class="options_group reepay_subscription_pricing show_if_reepay_subscription hidden">
    <p class="form-field pricing-fields">
        <label for="_subscription_price">
            <?php esc_html_e( 'Subscription pricing (kr)', WC_Reepay_Subscriptions::$domain ); ?>
        </label>
        <span class="wrap">
            <input type="number" id="_subscription_price" name="_reepay_subscription_price" class="wc_input_price wc_input_subscription_price" placeholder="<?php esc_attr_e( 'e.g. 5.90', WC_Reepay_Subscriptions::$domain ); ?>" step="any" min="0" value="<?php echo esc_attr( wc_format_localized_price( $meta['_reepay_subscription_price'][0] ) ); ?>" />
            <select id="_subscription_price_vat" name="_reepay_subscription_vat" class="wc_input_subscription_period_interval">
                <option value="include" <?php selected( 'include', $meta['_reepay_subscription_vat'][0], true ) ?>><?php esc_html_e( 'Incl. VAT', WC_Reepay_Subscriptions::$domain ); ?></option>
                <option value="exclude" <?php selected( 'exclude', $meta['_reepay_subscription_vat'][0], true ) ?>><?php esc_html_e( 'Excl. VAT', WC_Reepay_Subscriptions::$domain ); ?></option>
            </select>
            <select id="_subscription_schedule_type" name="_reepay_subscription_schedule_type" class="wc_input_subscription_period_interval">
                <?php foreach ( WC_Reepay_Subscription_Plans::$schedule_types as $value => $label ) { ?>
                    <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $value, $meta['_reepay_subscription_schedule_type'][0], true ) ?>><?php echo esc_html( $label ); ?></option>
                <?php } ?>
            </select>
        </span>
    </p>

    <!--Daily-->
    <p class="form-field type-fields fields-daily hidden">
        <label for="_subscription_days"><?php esc_html_e( 'Charge every', WC_Reepay_Subscriptions::$domain ); ?></label>
        <input type="number" id="_subscription_days" name="_subscription_days" value="<?= !empty($meta['_subscription_days'][0]) ? $meta['_subscription_days'][0] : 1?>">
        &nbsp<?php esc_html_e( 'Day', WC_Reepay_Subscriptions::$domain ); ?>
    </p>

    <!--Monthly-->
    <p class="form-field type-fields fields-month_startdate hidden">
        <label for="_subscription_month_startdate"><?php esc_html_e( 'Charge every', WC_Reepay_Subscriptions::$domain ); ?></label>
        <input type="number" id="_subscription_month_startdate" name="_reepay_subscription_month_startdate" value="<?= !empty($meta['_reepay_subscription_month_startdate'][0]) ? $meta['_reepay_subscription_month_startdate'][0] : 1?>">
        &nbsp<?php esc_html_e( 'Month', WC_Reepay_Subscriptions::$domain ); ?>
    </p>

    <!--Fixed day of month-->
    <?php $month_fixedday = unserialize($meta['_reepay_subscription_month_fixedday'][0]) ?>
    <p class="form-field type-fields fields-month_fixedday hidden">
        <label for="_subscription_month_fixedday"><?php esc_html_e( 'Charge every', WC_Reepay_Subscriptions::$domain ); ?></label>
        <input type="number" id="_subscription_month_fixedday" name="_reepay_subscription_month_fixedday[month]" value="<?= !empty($month_fixedday['month']) ? $month_fixedday['month'] : 1?>">
        &nbsp<?php esc_html_e( 'Month', WC_Reepay_Subscriptions::$domain ); ?>
    </p>
    <p class="form-field type-fields fields-month_fixedday hidden">
        <label for="_subscription_month_fixedday_day"><?php esc_html_e( 'On this day of the month', WC_Reepay_Subscriptions::$domain ); ?></label>
        <select id="_subscription_month_fixedday_day" name="_reepay_subscription_month_fixedday[day]" class="wc_input_subscription_period_interval">
            <?php for ($i = 1; $i <= 28; $i++) :?>
                <option value="<?php echo $i ?>" <?php selected( $i, $month_fixedday['day'], true ) ?>><?php echo $i ?></option>
            <?php endfor;?>
        </select>
    </p>
    <p class="form-field type-fields fields-month_fixedday hidden">
        <label for="_subscription_month_fixedday_period"><?php esc_html_e( 'Partial Period Handling', WC_Reepay_Subscriptions::$domain ); ?></label>
        <select id="_subscription_month_fixedday_period" name="_reepay_subscription_month_fixedday[period]" class="wc_input_subscription_period_interval">
            <option value="bill_prorated" <?php selected( 'bill_prorated', $month_fixedday['period'], true ) ?>>Bill prorated (Default)</option>
            <option value="bill_full" <?php selected( 'bill_full', $month_fixedday['period'], true ) ?>>Bill for full period</option>
            <option value="bill_zero_amount" <?php selected( 'bill_zero_amount', $month_fixedday['period'], true ) ?>>Bill a zero amount</option>
            <option value="no_bill" <?php selected( 'no_bill', $month_fixedday['period'], true ) ?>>Do not consider the partial period a billing period</option>
        </select>
    </p>
    <p class="form-field type-fields fields-month_fixedday hidden">
        <label for="_subscription_month_fixedday_proration"><?php esc_html_e( 'Proration setting', WC_Reepay_Subscriptions::$domain ); ?></label>
        <select id="_subscription_month_fixedday_proration" name="_subscription_month_fixedday[proration]" class="wc_input_subscription_period_interval">
            <option value="full_day" <?php selected( 'full_day', $month_fixedday['proration'], true ) ?>>Full day proration</option>
            <option value="by_minute" <?php selected( 'full_day', $month_fixedday['proration'], true ) ?>>By the minute proration</option>
        </select>
    </p>
    <p class="form-field type-fields fields-month_fixedday hidden">
        <label for="_subscription_month_fixedday_proration_minimum"><?php esc_html_e( 'Minimum prorated amount', WC_Reepay_Subscriptions::$domain ); ?></label>
        <input type="number" id="_subscription_month_fixedday_proration_minimum" value="<?= !empty($month_fixedday['proration_minimum']) ? $month_fixedday['proration_minimum'] : 0?>"  name="_reepay_subscription_month_fixedday[proration_minimum]" placeholder="<?php esc_attr_e( 'kr 0.00', WC_Reepay_Subscriptions::$domain ); ?>"/>
    </p>

    <!--Last day of month-->
    <?php $month_lastday = unserialize($meta['_reepay_subscription_month_lastday'][0]) ?>
    <p class="form-field type-fields fields-month_lastday hidden">
        <label for="_subscription_month_fixedday"><?php esc_html_e( 'Charge every', WC_Reepay_Subscriptions::$domain ); ?></label>
        <input type="number" id="_subscription_month_lastday" value="<?= !empty($month_lastday['month']) ? $month_lastday['month'] : 0?>" name="_reepay_subscription_month_lastday[month]">
        &nbsp<?php esc_html_e( 'Month', WC_Reepay_Subscriptions::$domain ); ?>
    </p>
    <p class="form-field type-fields fields-month_lastday hidden">
        <label for="_subscription_month_lastday_period"><?php esc_html_e( 'Partial Period Handling', WC_Reepay_Subscriptions::$domain ); ?></label>
        <select id="_subscription_month_lastday_period" name="_reepay_subscription_month_lastday[period]" class="wc_input_subscription_period_interval">
            <option value="bill_prorated" <?php selected( 'bill_prorated', $month_lastday['period'], true ) ?>>Bill prorated (Default)</option>
            <option value="bill_full" <?php selected( 'bill_full', $month_lastday['period'], true ) ?>>Bill for full period</option>
            <option value="bill_zero_amount" <?php selected( 'bill_zero_amount', $month_lastday['period'], true ) ?>>Bill a zero amount</option>
            <option value="no_bill" <?php selected( 'no_bill', $month_lastday['period'], true ) ?>>Do not consider the partial period a billing period</option>
        </select>
    </p>
    <p class="form-field type-fields fields-month_lastday hidden">
        <label for="_subscription_month_lastday_proration"><?php esc_html_e( 'Proration setting', WC_Reepay_Subscriptions::$domain ); ?></label>
        <select id="_subscription_month_lastday_proration" name="_reepay_subscription_month_lastday[proration]" class="wc_input_subscription_period_interval">
            <option value="full_day" <?php selected( 'full_day', $month_lastday['proration'], true ) ?>>Full day proration</option>
            <option value="by_minute" <?php selected( 'by_minute', $month_lastday['proration'], true ) ?>>By the minute proration</option>
        </select>
    </p>
    <p class="form-field type-fields fields-month_lastday hidden">
        <label for="_subscription_month_lastday_proration_minimum"><?php esc_html_e( 'Minimum prorated amount', WC_Reepay_Subscriptions::$domain ); ?></label>
        <input type="number" id="_subscription_month_lastday_proration_minimum" value="<?= !empty($month_lastday['proration_minimum']) ? $month_lastday['proration_minimum'] : 0?>" name="_subscription_month_lastday[proration_minimum]" placeholder="<?php esc_attr_e( 'kr 0.00', WC_Reepay_Subscriptions::$domain ); ?>" value="<?php echo esc_attr( wc_format_localized_price( $chosen_price ) ); ?>" />
    </p>

    <!--Quarterly Primo-->
    <p class="form-field type-fields fields-primo hidden">
        <label for="_subscription_primo"><?php _e( 'Charge first day of every', WC_Reepay_Subscriptions::$domain ); ?></label>
        <strong><?php _e( '3rd Month', WC_Reepay_Subscriptions::$domain ); ?></strong>
    </p>
    <p class="form-field type-fields fields-primo hidden">
        <label for="_subscription_primo"><?php _e( 'Fixed Months:', WC_Reepay_Subscriptions::$domain ); ?></label>
        <strong><?php _e( 'Jan, Apr, Jul, Oct', WC_Reepay_Subscriptions::$domain ); ?></strong>
    </p>
    <p class="form-field type-fields fields-primo hidden">
        <label for="_subscription_primo_period"><?php esc_html_e( 'Partial Period Handling', WC_Reepay_Subscriptions::$domain ); ?></label>
        <select id="_subscription_primo_period" name="_reepay_subscription_primo[period]" class="wc_input_subscription_period_interval">
            <option value="bill_prorated">Bill prorated (Default)</option>
            <option value="bill_full">Bill for full period</option>
            <option value="bill_zero_amount">Bill a zero amount</option>
            <option value="no_bill">Do not consider the partial period a billing period</option>
        </select>
    </p>
    <p class="form-field type-fields fields-primo hidden">
        <label for="_subscription_primo_proration"><?php esc_html_e( 'Proration setting', WC_Reepay_Subscriptions::$domain ); ?></label>
        <select id="_subscription_primo_proration" name="_reepay_subscription_primo[proration]" class="wc_input_subscription_period_interval">
            <option value="full_day">Full day proration</option>
            <option value="by_minute">By the minute proration</option>
        </select>
    </p>
    <p class="form-field type-fields fields-primo hidden">
        <label for="_subscription_primo_proration_minimum"><?php esc_html_e( 'Minimum prorated amount', WC_Reepay_Subscriptions::$domain ); ?></label>
        <input type="number" id="_subscription_primo_proration_minimum" name="_reepay_subscription_primo[proration_minimum]" placeholder="<?php esc_attr_e( 'kr 0.00', WC_Reepay_Subscriptions::$domain ); ?>" value="<?php echo esc_attr( wc_format_localized_price( $chosen_price ) ); ?>" />
    </p>

    <!--Quarterly Ultimo-->
    <p class="form-field type-fields fields-ultimo hidden">
        <label for="_subscription_ultimo"><?php _e( 'Charge last day of every', WC_Reepay_Subscriptions::$domain ); ?></label>
        <strong><?php _e( '3rd Month', WC_Reepay_Subscriptions::$domain ); ?></strong>
    </p>
    <p class="form-field type-fields fields-ultimo hidden">
        <label for="_subscription_ultimo"><?php _e( 'Fixed Months:', WC_Reepay_Subscriptions::$domain ); ?></label>
        <strong><?php _e( 'Jan, Apr, Jul, Oct', WC_Reepay_Subscriptions::$domain ); ?></strong>
    </p>
    <p class="form-field type-fields fields-ultimo hidden">
        <label for="_subscription_ultimo_period"><?php esc_html_e( 'Partial Period Handling', WC_Reepay_Subscriptions::$domain ); ?></label>
        <select id="_subscription_ultimo_period" name="_reepay_subscription_ultimo[period]" class="wc_input_subscription_period_interval">
            <option value="bill_prorated">Bill prorated (Default)</option>
            <option value="bill_full">Bill for full period</option>
            <option value="bill_zero_amount">Bill a zero amount</option>
            <option value="no_bill">Do not consider the partial period a billing period</option>
        </select>
    </p>
    <p class="form-field type-fields fields-ultimo hidden">
        <label for="_subscription_ultimo_proration"><?php esc_html_e( 'Proration setting', WC_Reepay_Subscriptions::$domain ); ?></label>
        <select id="_subscription_ultimo_proration" name="_reepay_subscription_ultimo[proration]" class="wc_input_subscription_period_interval">
            <option value="full_day">Full day proration</option>
            <option value="by_minute">By the minute proration</option>
        </select>
    </p>
    <p class="form-field type-fields fields-ultimo hidden">
        <label for="_subscription_ultimo_proration_minimum"><?php esc_html_e( 'Minimum prorated amount', WC_Reepay_Subscriptions::$domain ); ?></label>
        <input type="number" id="_subscription_ultimo_proration_minimum" name="_reepay_subscription_ultimo[proration_minimum]" placeholder="<?php esc_attr_e( 'kr 0.00', WC_Reepay_Subscriptions::$domain ); ?>" value="<?php echo esc_attr( wc_format_localized_price( $chosen_price ) ); ?>" />
    </p>

    <!--Half-yearly-->
    <p class="form-field type-fields fields-half_yearly hidden">
        <label for="_subscription_half_yearly"><?php _e( 'Charge every', WC_Reepay_Subscriptions::$domain ); ?></label>
        <strong><?php _e( '6th Month', WC_Reepay_Subscriptions::$domain ); ?></strong>
    </p>
    <p class="form-field type-fields fields-half_yearly hidden">
        <label for="_subscription_half_yearly"><?php _e( 'On this day of the month:', WC_Reepay_Subscriptions::$domain ); ?></label>
        <strong><?php _e( '1st', WC_Reepay_Subscriptions::$domain ); ?></strong>
    </p>
    <p class="form-field type-fields fields-half_yearly hidden">
        <label for="_subscription_half_yearly"><?php _e( 'Fixed Months:', WC_Reepay_Subscriptions::$domain ); ?></label>
        <strong><?php _e( 'Jan, Jul', WC_Reepay_Subscriptions::$domain ); ?></strong>
    </p>
    <p class="form-field type-fields fields-half_yearly hidden">
        <label for="_subscription_half_yearly_period"><?php esc_html_e( 'Partial Period Handling', WC_Reepay_Subscriptions::$domain ); ?></label>
        <select id="_subscription_half_yearly_period" name="_reepay_subscription_half_yearly[period]" class="wc_input_subscription_period_interval">
            <option value="bill_prorated">Bill prorated (Default)</option>
            <option value="bill_full">Bill for full period</option>
            <option value="bill_zero_amount">Bill a zero amount</option>
            <option value="no_bill">Do not consider the partial period a billing period</option>
        </select>
    </p>
    <p class="form-field type-fields fields-half_yearly hidden">
        <label for="_subscription_half_yearly_proration"><?php esc_html_e( 'Proration setting', WC_Reepay_Subscriptions::$domain ); ?></label>
        <select id="_subscription_half_yearly_proration" name="_reepay_subscription_half_yearly[proration]" class="wc_input_subscription_period_interval">
            <option value="full_day">Full day proration</option>
            <option value="by_minute">By the minute proration</option>
        </select>
    </p>
    <p class="form-field type-fields fields-half_yearly hidden">
        <label for="_subscription_half_yearly_proration_minimum"><?php esc_html_e( 'Minimum prorated amount', WC_Reepay_Subscriptions::$domain ); ?></label>
        <input type="number" id="_subscription_half_yearly_proration_minimum" name="_reepay_subscription_half_yearly[proration_minimum]" placeholder="<?php esc_attr_e( 'kr 0.00', WC_Reepay_Subscriptions::$domain ); ?>" value="<?php echo esc_attr( wc_format_localized_price( $chosen_price ) ); ?>" />
    </p>



    <!--Yearly-->
    <p class="form-field type-fields fields-month_startdate_12 hidden">
        <label for="_subscription_half_yearly"><?php _e( 'Charge every', WC_Reepay_Subscriptions::$domain ); ?></label>
        <strong><?php _e( '12th Month', WC_Reepay_Subscriptions::$domain ); ?></strong>
    </p>
    <p class="form-field type-fields fields-month_startdate_12 hidden">
        <label for="_subscription_month_startdate_12"><?php _e( 'On this day of the month:', WC_Reepay_Subscriptions::$domain ); ?></label>
        <strong><?php _e( '1st', WC_Reepay_Subscriptions::$domain ); ?></strong>
    </p>
    <p class="form-field type-fields fields-month_startdate_12 hidden">
        <label for="_subscription_month_startdate_12"><?php _e( 'Fixed Months:', WC_Reepay_Subscriptions::$domain ); ?></label>
        <strong><?php _e( 'Jan', WC_Reepay_Subscriptions::$domain ); ?></strong>
    </p>
    <p class="form-field type-fields fields-month_startdate_12 hidden">
        <label for="_subscription_month_startdate_12_period"><?php esc_html_e( 'Partial Period Handling', WC_Reepay_Subscriptions::$domain ); ?></label>
        <select id="_subscription_month_startdate_12_period" name="_reepay_subscription_month_startdate_12[period]" class="wc_input_subscription_period_interval">
            <option value="bill_prorated">Bill prorated (Default)</option>
            <option value="bill_full">Bill for full period</option>
            <option value="bill_zero_amount">Bill a zero amount</option>
            <option value="no_bill">Do not consider the partial period a billing period</option>
        </select>
    </p>
    <p class="form-field type-fields fields-month_startdate_12 hidden">
        <label for="_subscription_month_startdate_12_proration"><?php esc_html_e( 'Proration setting', WC_Reepay_Subscriptions::$domain ); ?></label>
        <select id="_subscription_month_startdate_12_proration" name="_reepay_subscription_month_startdate_12[proration]" class="wc_input_subscription_period_interval">
            <option value="full_day">Full day proration</option>
            <option value="by_minute">By the minute proration</option>
        </select>
    </p>
    <p class="form-field type-fields fields-month_startdate_12 hidden">
        <label for="_subscription_month_startdate_12_proration_minimum"><?php esc_html_e( 'Minimum prorated amount', WC_Reepay_Subscriptions::$domain ); ?></label>
        <input type="number" id="_subscription_month_startdate_12_proration_minimum" name="_reepay_subscription_month_startdate_12[proration_minimum]" placeholder="<?php esc_attr_e( 'kr 0.00', WC_Reepay_Subscriptions::$domain ); ?>" value="<?php echo esc_attr( wc_format_localized_price( $chosen_price ) ); ?>" />
    </p>


    <!--Fixed day of week-->
    <p class="form-field type-fields fields-weekly_fixedday hidden">
        <label for="_subscription_weekly_fixedday"><?php esc_html_e( 'Charge every', WC_Reepay_Subscriptions::$domain ); ?></label>
        <input type="number" id="_subscription_weekly_fixedday" name="_reepay_subscription_weekly_fixedday[week]">
        &nbsp<?php esc_html_e( 'Week', WC_Reepay_Subscriptions::$domain ); ?>
    </p>
    <p class="form-field type-fields fields-weekly_fixedday hidden">
        <label for="_subscription_weekly_fixedday_day"><?php esc_html_e( 'On this day of the month', WC_Reepay_Subscriptions::$domain ); ?></label>
        <select id="_subscription_weekly_fixedday_day" name="_reepay_subscription_weekly_fixedday[day]" class="wc_input_subscription_period_interval">
            <option value="1">Monday</option>
            <option value="2">Tuesday</option>
            <option value="3">Wednesday</option>
            <option value="4">Thursday</option>
            <option value="5">Friday</option>
            <option value="6">Saturday</option>
            <option value="7">Sunday</option>
        </select>
    </p>
    <p class="form-field type-fields fields-weekly_fixedday hidden">
        <label for="_subscription_weekly_fixedday_period"><?php esc_html_e( 'Partial Period Handling', WC_Reepay_Subscriptions::$domain ); ?></label>
        <select id="_subscription_weekly_fixedday_period" name="_reepay_subscription_weekly_fixedday[period]" class="wc_input_subscription_period_interval">
            <option value="bill_prorated">Bill prorated (Default)</option>
            <option value="bill_full">Bill for full period</option>
            <option value="bill_zero_amount">Bill a zero amount</option>
            <option value="no_bill">Do not consider the partial period a billing period</option>
        </select>
    </p>
    <p class="form-field type-fields fields-weekly_fixedday hidden">
        <label for="_subscription_weekly_fixedday_proration"><?php esc_html_e( 'Proration setting', WC_Reepay_Subscriptions::$domain ); ?></label>
        <select id="_subscription_weekly_fixedday_proration" name="_reepay_subscription_weekly_fixedday[proration]" class="wc_input_subscription_period_interval">
            <option value="full_day">Full day proration</option>
            <option value="by_minute">By the minute proration</option>
        </select>
    </p>
    <p class="form-field type-fields fields-weekly_fixedday hidden">
        <label for="_subscription_weekly_fixedday_proration_minimum"><?php esc_html_e( 'Minimum prorated amount', WC_Reepay_Subscriptions::$domain ); ?></label>
        <input type="number" id="_subscription_weekly_fixedday_proration_minimum" name="_reepay_subscription_weekly_fixedday[proration_minimum]" placeholder="<?php esc_attr_e( 'kr 0.00', WC_Reepay_Subscriptions::$domain ); ?>" value="<?php echo esc_attr( wc_format_localized_price( $chosen_price ) ); ?>" />
    </p>
</div>

<div class="options_group show_if_reepay_subscription">
    <p class="form-field">
        <label for="_subscription_contract_periods"><?php esc_html_e( 'Minimum Contract Period', WC_Reepay_Subscriptions::$domain ); ?></label>
        <input type="number" id="_subscription_contract_periods" name="_reepay_subscription_contract_periods" placeholder="<?php esc_html_e( 'Periods', WC_Reepay_Subscriptions::$domain ); ?>" value="" />
    </p>
</div>

<div class="options_group show_if_reepay_subscription">
    <p class="form-field">
        <label for="_subscription_notice_period"><?php esc_html_e( 'Notice period', WC_Reepay_Subscriptions::$domain ); ?></label>
        <input type="number" id="_subscription_notice_period" name="_reepay_subscription_notice_period" placeholder="<?php esc_html_e( 'Periods', WC_Reepay_Subscriptions::$domain ); ?>" value="" />
    </p>
    <p class="form-field fields-notice_period hidden">
        <label for="_subscription_notice_period_start"></label>
        <?php esc_html_e( 'When the current cancelled period ends', WC_Reepay_Subscriptions::$domain ); ?> &nbsp<input type="radio" id="_subscription_notice_period_start" name="_subscription_notice_period_start" value="true" />
        &nbsp&nbsp <?php esc_html_e( 'Immediately after cancellation', WC_Reepay_Subscriptions::$domain ); ?> &nbsp<input type="radio" id="_subscription_notice_period_start" name="_subscription_notice_period_start" value="false" />
    </p>
</div>

<div class="options_group show_if_reepay_subscription">
    <p class="form-field">
        <label for="_subscription_billing_cycles"><?php esc_html_e( 'Billing Cycles', WC_Reepay_Subscriptions::$domain ); ?></label>
        <?php esc_html_e( 'Auto Renew until cancelled', WC_Reepay_Subscriptions::$domain ); ?> &nbsp<input type="radio" id="_subscription_billing_cycles" name="_subscription_billing_cycles" value="true" />
        &nbsp&nbsp <?php esc_html_e( 'Fixed Number of billing cycles', WC_Reepay_Subscriptions::$domain ); ?> &nbsp<input type="radio" id="_subscription_billing_cycles" name="_subscription_billing_cycles" value="false" />
    </p>
    <p class="form-field fields-billing_cycles hidden">
        <label for="_subscription_billing_cycles_period"><?php esc_html_e( 'Number of billing cycles', WC_Reepay_Subscriptions::$domain ); ?></label>
        <input type="number" id="_subscription_billing_cycles_period" name="_reepay_subscription_billing_cycles_period" placeholder="*" value="" />
    </p>
</div>

<div class="options_group reepay_subscription_trial show_if_reepay_subscription">
    <p class="form-field">
        <label for="_subscription_trial"><?php esc_html_e( 'Trial', WC_Reepay_Subscriptions::$domain ); ?></label>
        <select id="_subscription_trial" name="_reepay_subscription_trial[type]" class="wc_input_subscription_period_interval">
            <?php foreach ( WC_Reepay_Subscription_Plans::$trial as $value => $label ) { ?>
                <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $value, $chosen_period, true ) ?>><?php echo esc_html( $label ); ?></option>
            <?php } ?>
        </select>
    </p>
    <p class="form-field trial-fields fields-customize hidden">
        <label for="_subscription_trial_length"><?php esc_html_e( 'Trial Length', WC_Reepay_Subscriptions::$domain ); ?></label>
        <input type="number" id="_subscription_trial_length" name="_reepay_subscription_trial[length]" placeholder="<?php esc_html_e( 'Length', WC_Reepay_Subscriptions::$domain ); ?>" value="" />
        <select id="_subscription_trial_unit" name="_reepay_subscription_trial[unit]" class="wc_input_subscription_period_interval">
            <option value="days">Days</option>
            <option value="months">Months</option>
        </select>
    </p>
    <p class="form-field trial-fields fields-7days fields-14days fields-1month fields-customize hidden">
        <label for="_subscription_billing_trial_reminder"><?php esc_html_e( 'Optional Trial Reminder Schedule', WC_Reepay_Subscriptions::$domain ); ?></label>
        <input type="number" id="_subscription_trial_reminder" name="_reepay_subscription_trial[reminder]" placeholder="<?php esc_html_e( 'Days', WC_Reepay_Subscriptions::$domain ); ?>" value="" />
    </p>
</div>

<div class="options_group reepay_subscription_fee show_if_reepay_subscription">
    <p class="form-field">
        <label for="_subscription_fee"><?php esc_html_e( 'Include setup fee', WC_Reepay_Subscriptions::$domain ); ?></label>
        <input type="checkbox" id="_subscription_fee" name="_reepay_subscription_fee[enabled]" value="" />
    </p>

    <p class="form-field fee-fields hidden">
        <label for="_subscription_fee_amount"><?php esc_html_e( 'Setup Fee (kr)', WC_Reepay_Subscriptions::$domain ); ?></label>
        <span class="wrap">
            <input type="number" id="_subscription_fee_amount" name="_reepay_subscription_fee[amount]" class="wc_input_price wc_input_subscription_price" placeholder="<?php esc_attr_e( 'Amount', WC_Reepay_Subscriptions::$domain ); ?>" step="any" min="0" value="" />
            <input type="text" id="_subscription_fee_text" name="_reepay_subscription_fee[text]" placeholder="<?php esc_attr_e( 'Text', WC_Reepay_Subscriptions::$domain ); ?>"  value="" />
            <select id="_subscription_fee_handling" name="_reepay_subscription_fee[handling]" class="wc_input_subscription_period_interval">
                <option value="first">Include setup fee as order line on the first scheduled invoice</option>
                <option value="separate">Create a separate invoice for the setup fee</option>
                <option value="separate_conditional">Create a separate invoice for the setup fee, if the first invoice is not created in conjunction with the creation</option>
            </select>
        </span>
    </p>
</div>

<div class="options_group show_if_reepay_simple_subscriptions clear"></div>