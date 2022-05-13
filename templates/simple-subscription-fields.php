<?php
?>
<div class="options_group reepay_subscription_pricing show_if_reepay_subscription hidden">
    <p class="form-field pricing-fields">
        <label for="_subscription_price">
            <?php esc_html_e( 'Subscription pricing (kr)', WC_Reepay_Subscriptions::$domain ); ?>
        </label>
        <span class="wrap">
				<input type="number" id="_subscription_price" name="_reepay_subscription_price" class="wc_input_price wc_input_subscription_price" placeholder="<?php esc_attr_e( 'e.g. 5.90', WC_Reepay_Subscriptions::$domain ); ?>" step="any" min="0" value="<?php echo esc_attr( wc_format_localized_price( $chosen_price ) ); ?>" />
				<label for="_subscription_price_vat" class="wcs_hidden_label"><?php esc_html_e( 'Vat type', WC_Reepay_Subscriptions::$domain ); ?></label>
				<select id="_subscription_price_vat" name="_reepay_subscription_vat" class="wc_input_subscription_period_interval">
                    <option value="include" <?php selected( 'include', $chosen_interval, true ) ?>><?php esc_html_e( 'Incl. VAT', WC_Reepay_Subscriptions::$domain ); ?></option>
                    <option value="exclude" <?php selected( 'exclude', $chosen_interval, true ) ?>><?php esc_html_e( 'Excl. VAT', WC_Reepay_Subscriptions::$domain ); ?></option>
				</select>
                <label for="_subscription_schedule_type" class="wcs_hidden_label"><?php esc_html_e( 'Schedule type', WC_Reepay_Subscriptions::$domain ); ?></label>
				<select id="_subscription_schedule_type" name="_reepay_subscription_schedule_type" class="wc_input_subscription_period_interval">
                    <?php foreach ( WC_Reepay_Subscription_Plans::$schedule_types as $value => $label ) { ?>
                        <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $value, $chosen_period, true ) ?>><?php echo esc_html( $label ); ?></option>
                    <?php } ?>
				</select>
			</span>
    </p>

    <!--Daily-->
    <p class="form-field type-fields fields-daily hidden">
        <label for="_subscription_days"><?php esc_html_e( 'Charge every', WC_Reepay_Subscriptions::$domain ); ?></label>
        <input type="number" id="_subscription_days" name="_reepay_subscription_price">
        &nbsp<?php esc_html_e( 'Day', WC_Reepay_Subscriptions::$domain ); ?>
    </p>

    <!--Monthly-->
    <p class="form-field type-fields fields-month_startdate hidden">
        <label for="_subscription_month_startdate"><?php esc_html_e( 'Charge every', WC_Reepay_Subscriptions::$domain ); ?></label>
        <input type="number" id="_subscription_month_startdate" name="_subscription_month_startdate">
        &nbsp<?php esc_html_e( 'Month', WC_Reepay_Subscriptions::$domain ); ?>
    </p>

    <!--Fixed day of month-->
    <p class="form-field type-fields fields-month_fixedday hidden">
        <label for="_subscription_month_fixedday"><?php esc_html_e( 'Charge every', WC_Reepay_Subscriptions::$domain ); ?></label>
        <input type="number" id="_subscription_month_fixedday" name="_subscription_month_fixedday[month]">
        &nbsp<?php esc_html_e( 'Month', WC_Reepay_Subscriptions::$domain ); ?>
    </p>
    <p class="form-field type-fields fields-month_fixedday hidden">
        <label for="_subscription_month_fixedday_day"><?php esc_html_e( 'On this day of the month', WC_Reepay_Subscriptions::$domain ); ?></label>
        <select id="_subscription_month_fixedday_day" name="_subscription_month_fixedday[day]" class="wc_input_subscription_period_interval">
            <?php for ($i = 1; $i <= 28; $i++) :?>
                <option value="<?php echo $i ?>" ><?php echo $i ?></option>
            <?php endfor;?>
        </select>
    </p>
    <p class="form-field type-fields fields-month_fixedday hidden">
        <label for="_subscription_month_fixedday_period"><?php esc_html_e( 'Partial Period Handling', WC_Reepay_Subscriptions::$domain ); ?></label>
        <select id="_subscription_month_fixedday_period" name="_subscription_month_fixedday[period]" class="wc_input_subscription_period_interval">
            <option value="bill_prorated">Bill prorated (Default)</option>
            <option value="bill_full">Bill for full period</option>
            <option value="bill_zero_amount">Bill a zero amount</option>
            <option value="no_bill">Do not consider the partial period a billing period</option>
        </select>
    </p>
    <p class="form-field type-fields fields-month_fixedday hidden">
        <label for="_subscription_month_fixedday_proration"><?php esc_html_e( 'Proration setting', WC_Reepay_Subscriptions::$domain ); ?></label>
        <select id="_subscription_month_fixedday_proration" name="_subscription_month_fixedday[proration]" class="wc_input_subscription_period_interval">
            <option value="full_day">Full day proration</option>
            <option value="by_minute">By the minute proration</option>
        </select>
    </p>
    <p class="form-field type-fields fields-month_fixedday hidden">
        <label for="_subscription_month_fixedday_proration_minimum"><?php esc_html_e( 'Minimum prorated amount', WC_Reepay_Subscriptions::$domain ); ?></label>
        <input type="number" id="_subscription_month_fixedday_proration_minimum" name="_subscription_month_fixedday[proration_minimum]" placeholder="<?php esc_attr_e( 'kr 0.00', WC_Reepay_Subscriptions::$domain ); ?>" value="<?php echo esc_attr( wc_format_localized_price( $chosen_price ) ); ?>" />
    </p>

    <!--Fixed day of month-->
    <p class="form-field type-fields fields-month_lastday hidden">
        <label for="_subscription_month_fixedday"><?php esc_html_e( 'Charge every', WC_Reepay_Subscriptions::$domain ); ?></label>
        <input type="number" id="_subscription_month_lastday" name="_subscription_month_fixedday[month]">
        &nbsp<?php esc_html_e( 'Month', WC_Reepay_Subscriptions::$domain ); ?>
    </p>
    <p class="form-field type-fields fields-month_lastday hidden">
        <label for="_subscription_month_lastday_period"><?php esc_html_e( 'Partial Period Handling', WC_Reepay_Subscriptions::$domain ); ?></label>
        <select id="_subscription_month_lastday_period" name="_subscription_month_lastday[period]" class="wc_input_subscription_period_interval">
            <option value="bill_prorated">Bill prorated (Default)</option>
            <option value="bill_full">Bill for full period</option>
            <option value="bill_zero_amount">Bill a zero amount</option>
            <option value="no_bill">Do not consider the partial period a billing period</option>
        </select>
    </p>
    <p class="form-field type-fields fields-month_lastday hidden">
        <label for="_subscription_month_lastday_proration"><?php esc_html_e( 'Proration setting', WC_Reepay_Subscriptions::$domain ); ?></label>
        <select id="_subscription_month_lastday_proration" name="_subscription_month_lastday[proration]" class="wc_input_subscription_period_interval">
            <option value="full_day">Full day proration</option>
            <option value="by_minute">By the minute proration</option>
        </select>
    </p>
    <p class="form-field type-fields fields-month_lastday hidden">
        <label for="_subscription_month_lastday_proration_minimum"><?php esc_html_e( 'Minimum prorated amount', WC_Reepay_Subscriptions::$domain ); ?></label>
        <input type="number" id="_subscription_month_lastday_proration_minimum" name="_subscription_month_lastday[proration_minimum]" placeholder="<?php esc_attr_e( 'kr 0.00', WC_Reepay_Subscriptions::$domain ); ?>" value="<?php echo esc_attr( wc_format_localized_price( $chosen_price ) ); ?>" />
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
        <select id="_subscription_primo_period" name="_subscription_primo[period]" class="wc_input_subscription_period_interval">
            <option value="bill_prorated">Bill prorated (Default)</option>
            <option value="bill_full">Bill for full period</option>
            <option value="bill_zero_amount">Bill a zero amount</option>
            <option value="no_bill">Do not consider the partial period a billing period</option>
        </select>
    </p>
    <p class="form-field type-fields fields-primo hidden">
        <label for="_subscription_primo_proration"><?php esc_html_e( 'Proration setting', WC_Reepay_Subscriptions::$domain ); ?></label>
        <select id="_subscription_primo_proration" name="_subscription_primo[proration]" class="wc_input_subscription_period_interval">
            <option value="full_day">Full day proration</option>
            <option value="by_minute">By the minute proration</option>
        </select>
    </p>
    <p class="form-field type-fields fields-primo hidden">
        <label for="_subscription_primo_proration_minimum"><?php esc_html_e( 'Minimum prorated amount', WC_Reepay_Subscriptions::$domain ); ?></label>
        <input type="number" id="_subscription_primo_proration_minimum" name="_subscription_primo[proration_minimum]" placeholder="<?php esc_attr_e( 'kr 0.00', WC_Reepay_Subscriptions::$domain ); ?>" value="<?php echo esc_attr( wc_format_localized_price( $chosen_price ) ); ?>" />
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
        <select id="_subscription_ultimo_period" name="_subscription_ultimo[period]" class="wc_input_subscription_period_interval">
            <option value="bill_prorated">Bill prorated (Default)</option>
            <option value="bill_full">Bill for full period</option>
            <option value="bill_zero_amount">Bill a zero amount</option>
            <option value="no_bill">Do not consider the partial period a billing period</option>
        </select>
    </p>
    <p class="form-field type-fields fields-ultimo hidden">
        <label for="_subscription_ultimo_proration"><?php esc_html_e( 'Proration setting', WC_Reepay_Subscriptions::$domain ); ?></label>
        <select id="_subscription_ultimo_proration" name="_subscription_ultimo[proration]" class="wc_input_subscription_period_interval">
            <option value="full_day">Full day proration</option>
            <option value="by_minute">By the minute proration</option>
        </select>
    </p>
    <p class="form-field type-fields fields-ultimo hidden">
        <label for="_subscription_ultimo_proration_minimum"><?php esc_html_e( 'Minimum prorated amount', WC_Reepay_Subscriptions::$domain ); ?></label>
        <input type="number" id="_subscription_ultimo_proration_minimum" name="_subscription_ultimo[proration_minimum]" placeholder="<?php esc_attr_e( 'kr 0.00', WC_Reepay_Subscriptions::$domain ); ?>" value="<?php echo esc_attr( wc_format_localized_price( $chosen_price ) ); ?>" />
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
        <select id="_subscription_half_yearly_period" name="_subscription_half_yearly[period]" class="wc_input_subscription_period_interval">
            <option value="bill_prorated">Bill prorated (Default)</option>
            <option value="bill_full">Bill for full period</option>
            <option value="bill_zero_amount">Bill a zero amount</option>
            <option value="no_bill">Do not consider the partial period a billing period</option>
        </select>
    </p>
    <p class="form-field type-fields fields-half_yearly hidden">
        <label for="_subscription_half_yearly_proration"><?php esc_html_e( 'Proration setting', WC_Reepay_Subscriptions::$domain ); ?></label>
        <select id="_subscription_half_yearly_proration" name="_subscription_half_yearly[proration]" class="wc_input_subscription_period_interval">
            <option value="full_day">Full day proration</option>
            <option value="by_minute">By the minute proration</option>
        </select>
    </p>
    <p class="form-field type-fields fields-half_yearly hidden">
        <label for="_subscription_half_yearly_proration_minimum"><?php esc_html_e( 'Minimum prorated amount', WC_Reepay_Subscriptions::$domain ); ?></label>
        <input type="number" id="_subscription_half_yearly_proration_minimum" name="_subscription_half_yearly[proration_minimum]" placeholder="<?php esc_attr_e( 'kr 0.00', WC_Reepay_Subscriptions::$domain ); ?>" value="<?php echo esc_attr( wc_format_localized_price( $chosen_price ) ); ?>" />
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
        <select id="_subscription_month_startdate_12_period" name="_subscription_month_startdate_12[period]" class="wc_input_subscription_period_interval">
            <option value="bill_prorated">Bill prorated (Default)</option>
            <option value="bill_full">Bill for full period</option>
            <option value="bill_zero_amount">Bill a zero amount</option>
            <option value="no_bill">Do not consider the partial period a billing period</option>
        </select>
    </p>
    <p class="form-field type-fields fields-month_startdate_12 hidden">
        <label for="_subscription_month_startdate_12_proration"><?php esc_html_e( 'Proration setting', WC_Reepay_Subscriptions::$domain ); ?></label>
        <select id="_subscription_month_startdate_12_proration" name="_subscription_month_startdate_12[proration]" class="wc_input_subscription_period_interval">
            <option value="full_day">Full day proration</option>
            <option value="by_minute">By the minute proration</option>
        </select>
    </p>
    <p class="form-field type-fields fields-month_startdate_12 hidden">
        <label for="_subscription_month_startdate_12_proration_minimum"><?php esc_html_e( 'Minimum prorated amount', WC_Reepay_Subscriptions::$domain ); ?></label>
        <input type="number" id="_subscription_month_startdate_12_proration_minimum" name="_subscription_month_startdate_12[proration_minimum]" placeholder="<?php esc_attr_e( 'kr 0.00', WC_Reepay_Subscriptions::$domain ); ?>" value="<?php echo esc_attr( wc_format_localized_price( $chosen_price ) ); ?>" />
    </p>


    <!--Fixed day of week-->
    <p class="form-field type-fields fields-weekly_fixedday hidden">
        <label for="_subscription_weekly_fixedday"><?php esc_html_e( 'Charge every', WC_Reepay_Subscriptions::$domain ); ?></label>
        <input type="number" id="_subscription_weekly_fixedday" name="_subscription_weekly_fixedday[week]">
        &nbsp<?php esc_html_e( 'Week', WC_Reepay_Subscriptions::$domain ); ?>
    </p>
    <p class="form-field type-fields fields-weekly_fixedday hidden">
        <label for="_subscription_weekly_fixedday_day"><?php esc_html_e( 'On this day of the month', WC_Reepay_Subscriptions::$domain ); ?></label>
        <select id="_subscription_weekly_fixedday_day" name="_subscription_weekly_fixedday[day]" class="wc_input_subscription_period_interval">
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
        <select id="_subscription_weekly_fixedday_period" name="_subscription_weekly_fixedday[period]" class="wc_input_subscription_period_interval">
            <option value="bill_prorated">Bill prorated (Default)</option>
            <option value="bill_full">Bill for full period</option>
            <option value="bill_zero_amount">Bill a zero amount</option>
            <option value="no_bill">Do not consider the partial period a billing period</option>
        </select>
    </p>
    <p class="form-field type-fields fields-weekly_fixedday hidden">
        <label for="_subscription_weekly_fixedday_proration"><?php esc_html_e( 'Proration setting', WC_Reepay_Subscriptions::$domain ); ?></label>
        <select id="_subscription_weekly_fixedday_proration" name="_subscription_weekly_fixedday[proration]" class="wc_input_subscription_period_interval">
            <option value="full_day">Full day proration</option>
            <option value="by_minute">By the minute proration</option>
        </select>
    </p>
    <p class="form-field type-fields fields-weekly_fixedday hidden">
        <label for="_subscription_weekly_fixedday_proration_minimum"><?php esc_html_e( 'Minimum prorated amount', WC_Reepay_Subscriptions::$domain ); ?></label>
        <input type="number" id="_subscription_weekly_fixedday_proration_minimum" name="_subscription_weekly_fixedday[proration_minimum]" placeholder="<?php esc_attr_e( 'kr 0.00', WC_Reepay_Subscriptions::$domain ); ?>" value="<?php echo esc_attr( wc_format_localized_price( $chosen_price ) ); ?>" />
    </p>


</div>
<div class="options_group show_if_reepay_simple_subscriptions clear"></div>