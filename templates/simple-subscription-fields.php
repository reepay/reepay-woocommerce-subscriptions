<?php
?>
<div class="options_group reepay_subscription_pricing show_if_reepay_subscription">
    <p class="form-field pricing-fields <?= $variable ? 'dimensions_field form-row' : '' ?> ">
        <label for="_subscription_price">
            <?php esc_html_e( 'Subscription pricing (kr)', $domain ); ?>
        </label>
        <span class="wrap">
            <input type="number" id="_subscription_price" <?= $is_update ? 'disabled' : '' ?> name="_reepay_subscription_price" class="wc_input_price wc_input_subscription_price" placeholder="<?php esc_attr_e( 'e.g. 5.90', $domain ); ?>" step="any" min="0" value="<?php echo esc_attr( wc_format_localized_price( $meta['_reepay_subscription_price'][0] ) ); ?>"/>
            <select id="_subscription_price_vat" <?= $is_update ? 'disabled' : '' ?> name="_reepay_subscription_vat" class="wc_input_subscription_period_interval">
                <option value="include" <?php selected( 'include', $meta['_reepay_subscription_vat'][0], true ) ?>><?php esc_html_e( 'Incl. VAT', $domain ); ?></option>
                <option value="exclude" <?php selected( 'exclude', $meta['_reepay_subscription_vat'][0], true ) ?>><?php esc_html_e( 'Excl. VAT', $domain ); ?></option>
            </select>
            <select id="_subscription_schedule_type" <?= $is_update ? 'disabled' : '' ?> name="_reepay_subscription_schedule_type" class="wc_input_subscription_period_interval">
                <?php foreach ( WC_Reepay_Subscription_Plans::$schedule_types as $value => $label ) { ?>
                    <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $value, $meta['_reepay_subscription_schedule_type'][0], true ) ?>><?php echo esc_html( $label ); ?></option>
                <?php } ?>
            </select>
        </span>
    </p>

    <!--Daily-->
    <p class="form-field type-fields fields-daily <?= $variable ? 'form-row' : '' ?> hidden">
        <label for="_reepay_subscription_daily"><?php esc_html_e( 'Charge every', $domain ); ?></label>
        <input type="number" id="_reepay_subscription_daily" name="_reepay_subscription_daily" <?= $is_update ? 'disabled' : '' ?> value="<?= !empty($meta['_subscription_days'][0]) ? $meta['_subscription_days'][0] : 1?>">
        &nbsp<?php esc_html_e( 'Day', $domain ); ?>
    </p>

    <!--Monthly-->
    <p class="form-field type-fields fields-month_startdate <?= $variable ? 'form-row' : '' ?> hidden">
        <label for="_subscription_month_startdate"><?php esc_html_e( 'Charge every', $domain ); ?></label>
        <input type="number" id="_subscription_month_startdate" name="_reepay_subscription_month_startdate" <?= $is_update ? 'disabled' : '' ?> value="<?= !empty($meta['_reepay_subscription_month_startdate'][0]) ? $meta['_reepay_subscription_month_startdate'][0] : 1?>">
        &nbsp<?php esc_html_e( 'Month', $domain ); ?>
    </p>

    <!--Fixed day of month-->
    <?php $month_fixedday = !empty($meta['_reepay_subscription_month_fixedday']) ? unserialize($meta['_reepay_subscription_month_fixedday'][0]) : array() ?>
    <p class="form-field type-fields fields-month_fixedday <?= $variable ? 'form-row' : '' ?> hidden">
        <label for="_subscription_month_fixedday"><?php esc_html_e( 'Charge every', $domain ); ?></label>
        <input type="number" id="_subscription_month_fixedday" <?= $is_update ? 'disabled' : '' ?> name="_reepay_subscription_month_fixedday[month]" value="<?= !empty($month_fixedday['month']) ? $month_fixedday['month'] : 1?>">
        &nbsp<?php esc_html_e( 'Month', $domain ); ?>
    </p>
    <p class="form-field type-fields fields-month_fixedday <?= $variable ? 'form-row' : '' ?> hidden">
        <label for="_subscription_month_fixedday_day"><?php esc_html_e( 'On this day of the month', $domain ); ?></label>
        <select id="_subscription_month_fixedday_day" <?= $is_update ? 'disabled' : '' ?> name="_reepay_subscription_month_fixedday[day]" class="wc_input_subscription_period_interval">
            <?php for ($i = 1; $i <= 28; $i++) :?>
                <option value="<?php echo $i ?>" <?php selected( $i, $month_fixedday['day'], true ) ?>><?php echo $i ?></option>
            <?php endfor;?>
        </select>
    </p>
    <p class="form-field type-fields fields-month_fixedday <?= $variable ? 'form-row' : '' ?> hidden">
        <label for="_subscription_month_fixedday_period"><?php esc_html_e( 'Partial Period Handling', $domain ); ?></label>
        <select id="_subscription_month_fixedday_period" <?= $is_update ? 'disabled' : '' ?> name="_reepay_subscription_month_fixedday[period]" class="wc_input_subscription_period_interval">
            <option value="bill_prorated" <?php selected( 'bill_prorated', $month_fixedday['period'], true ) ?>>Bill prorated (Default)</option>
            <option value="bill_full" <?php selected( 'bill_full', $month_fixedday['period'], true ) ?>>Bill for full period</option>
            <option value="bill_zero_amount" <?php selected( 'bill_zero_amount', $month_fixedday['period'], true ) ?>>Bill a zero amount</option>
            <option value="no_bill" <?php selected( 'no_bill', $month_fixedday['period'], true ) ?>>Do not consider the partial period a billing period</option>
        </select>
    </p>
    <p class="form-field type-fields fields-month_fixedday <?= $variable ? 'form-row' : '' ?> hidden">
        <label for="_subscription_month_fixedday_proration"><?php esc_html_e( 'Proration setting', $domain ); ?></label>
        <select id="_subscription_month_fixedday_proration" <?= $is_update ? 'disabled' : '' ?> name="_subscription_month_fixedday[proration]" class="wc_input_subscription_period_interval">
            <option value="full_day" <?php selected( 'full_day', $month_fixedday['proration'], true ) ?>>Full day proration</option>
            <option value="by_minute" <?php selected( 'full_day', $month_fixedday['proration'], true ) ?>>By the minute proration</option>
        </select>
    </p>
    <p class="form-field type-fields fields-month_fixedday <?= $variable ? 'form-row' : '' ?> hidden">
        <label for="_subscription_month_fixedday_proration_minimum"><?php esc_html_e( 'Minimum prorated amount', $domain ); ?></label>
        <input type="number" id="_subscription_month_fixedday_proration_minimum"  value="<?= !empty($month_fixedday['proration_minimum']) ? $month_fixedday['proration_minimum'] : 0?>"  name="_reepay_subscription_month_fixedday[proration_minimum]" placeholder="<?php esc_attr_e( 'kr 0.00', $domain ); ?>"/>
    </p>

    <!--Last day of month-->
    <?php $month_lastday = !empty($meta['_reepay_subscription_month_lastday']) ? unserialize($meta['_reepay_subscription_month_lastday'][0]) : array() ?>
    <p class="form-field type-fields fields-month_lastday <?= $variable ? 'form-row' : '' ?> hidden">
        <label for="_subscription_month_fixedday"><?php esc_html_e( 'Charge every', $domain ); ?></label>
        <input type="number" id="_subscription_month_lastday" <?= $is_update ? 'disabled' : '' ?> value="<?= !empty($month_lastday['month']) ? $month_lastday['month'] : 0?>" name="_reepay_subscription_month_lastday[month]">
        &nbsp<?php esc_html_e( 'Month', $domain ); ?>
    </p>
    <p class="form-field type-fields fields-month_lastday <?= $variable ? 'form-row' : '' ?> hidden">
        <label for="_subscription_month_lastday_period"><?php esc_html_e( 'Partial Period Handling', $domain ); ?></label>
        <select id="_subscription_month_lastday_period" <?= $is_update ? 'disabled' : '' ?> name="_reepay_subscription_month_lastday[period]" class="wc_input_subscription_period_interval">
            <option value="bill_prorated" <?php selected( 'bill_prorated', $month_lastday['period'], true ) ?>>Bill prorated (Default)</option>
            <option value="bill_full" <?php selected( 'bill_full', $month_lastday['period'], true ) ?>>Bill for full period</option>
            <option value="bill_zero_amount" <?php selected( 'bill_zero_amount', $month_lastday['period'], true ) ?>>Bill a zero amount</option>
            <option value="no_bill" <?php selected( 'no_bill', $month_lastday['period'], true ) ?>>Do not consider the partial period a billing period</option>
        </select>
    </p>
    <p class="form-field type-fields fields-month_lastday <?= $variable ? 'form-row' : '' ?> hidden">
        <label for="_subscription_month_lastday_proration"><?php esc_html_e( 'Proration setting', $domain ); ?></label>
        <select id="_subscription_month_lastday_proration" <?= $is_update ? 'disabled' : '' ?> name="_reepay_subscription_month_lastday[proration]" class="wc_input_subscription_period_interval">
            <option value="full_day" <?php selected( 'full_day', $month_lastday['proration'], true ) ?>>Full day proration</option>
            <option value="by_minute" <?php selected( 'by_minute', $month_lastday['proration'], true ) ?>>By the minute proration</option>
        </select>
    </p>
    <p class="form-field type-fields fields-month_lastday <?= $variable ? 'form-row' : '' ?> hidden">
        <label for="_subscription_month_lastday_proration_minimum"><?php esc_html_e( 'Minimum prorated amount', $domain ); ?></label>
        <input type="number" id="_subscription_month_lastday_proration_minimum" value="<?= !empty($month_lastday['proration_minimum']) ? $month_lastday['proration_minimum'] : 0?>" name="_subscription_month_lastday[proration_minimum]" placeholder="<?php esc_attr_e( 'kr 0.00', $domain ); ?>" value="<?php echo esc_attr( wc_format_localized_price( $chosen_price ) ); ?>" />
    </p>

    <!--Quarterly Primo-->
    <?php $primo = !empty($meta['_reepay_subscription_primo']) ? unserialize($meta['_reepay_subscription_primo'][0]) : array() ?>
    <p class="form-field type-fields fields-primo hidden">
        <label for="_subscription_primo"><?php _e( 'Charge first day of every', $domain ); ?></label>
        <strong><?php _e( '3rd Month', $domain ); ?></strong>
    </p>
    <p class="form-field type-fields fields-primo hidden">
        <label for="_subscription_primo"><?php _e( 'Fixed Months:', $domain ); ?></label>
        <strong><?php _e( 'Jan, Apr, Jul, Oct', $domain ); ?></strong>
    </p>
    <p class="form-field type-fields fields-primo <?= $variable ? 'form-row' : '' ?> hidden">
        <label for="_subscription_primo_period"><?php esc_html_e( 'Partial Period Handling', $domain ); ?></label>
        <select id="_subscription_primo_period" <?= $is_update ? 'disabled' : '' ?> name="_reepay_subscription_primo[period]" class="wc_input_subscription_period_interval">
            <option value="bill_prorated" <?php selected( 'bill_prorated', $primo['period'], true ) ?>>Bill prorated (Default)</option>
            <option value="bill_full" <?php selected( 'bill_full', $primo['period'], true ) ?>>Bill for full period</option>
            <option value="bill_zero_amount" <?php selected( 'bill_zero_amount', $primo['period'], true ) ?>>Bill a zero amount</option>
            <option value="no_bill" <?php selected( 'no_bill', $primo['period'], true ) ?>>Do not consider the partial period a billing period</option>
        </select>
    </p>
    <p class="form-field type-fields fields-primo <?= $variable ? 'form-row' : '' ?> hidden">
        <label for="_subscription_primo_proration"><?php esc_html_e( 'Proration setting', $domain ); ?></label>
        <select id="_subscription_primo_proration" <?= $is_update ? 'disabled' : '' ?> name="_reepay_subscription_primo[proration]" class="wc_input_subscription_period_interval">
            <option value="full_day" <?php selected( 'full_day', $primo['proration'], true ) ?>>Full day proration</option>
            <option value="by_minute" <?php selected( 'by_minute', $primo['proration'], true ) ?>>By the minute proration</option>
        </select>
    </p>
    <p class="form-field type-fields fields-primo <?= $variable ? 'form-row' : '' ?> hidden">
        <label for="_subscription_primo_proration_minimum"><?php esc_html_e( 'Minimum prorated amount', $domain ); ?></label>
        <input type="number" id="_subscription_primo_proration_minimum" name="_reepay_subscription_primo[proration_minimum]" placeholder="<?php esc_attr_e( 'kr 0.00', $domain ); ?>" value="<?= !empty($primo['proration_minimum']) ? $primo['proration_minimum'] : 0?>" />
    </p>

    <!--Quarterly Ultimo-->
    <?php $ultimo = !empty($meta['_reepay_subscription_ultimo']) ? unserialize($meta['_reepay_subscription_ultimo'][0]) : array() ?>
    <p class="form-field type-fields fields-ultimo hidden">
        <label for="_subscription_ultimo"><?php _e( 'Charge last day of every', $domain ); ?></label>
        <strong><?php _e( '3rd Month', $domain ); ?></strong>
    </p>
    <p class="form-field type-fields fields-ultimo hidden">
        <label for="_subscription_ultimo"><?php _e( 'Fixed Months:', $domain ); ?></label>
        <strong><?php _e( 'Jan, Apr, Jul, Oct', $domain ); ?></strong>
    </p>
    <p class="form-field type-fields fields-ultimo <?= $variable ? 'form-row' : '' ?> hidden">
        <label for="_subscription_ultimo_period"><?php esc_html_e( 'Partial Period Handling', $domain ); ?></label>
        <select id="_subscription_ultimo_period" <?= $is_update ? 'disabled' : '' ?> name="_reepay_subscription_ultimo[period]" class="wc_input_subscription_period_interval">
            <option value="bill_prorated" <?php selected( 'bill_prorated', $ultimo['period'], true ) ?>>Bill prorated (Default)</option>
            <option value="bill_full" <?php selected( 'bill_full', $ultimo['period'], true ) ?>>Bill for full period</option>
            <option value="bill_zero_amount" <?php selected( 'bill_zero_amount', $ultimo['period'], true ) ?>>Bill a zero amount</option>
            <option value="no_bill" <?php selected( 'no_bill', $ultimo['period'], true ) ?>>Do not consider the partial period a billing period</option>
        </select>
    </p>
    <p class="form-field type-fields fields-ultimo <?= $variable ? 'form-row' : '' ?> hidden">
        <label for="_subscription_ultimo_proration"><?php esc_html_e( 'Proration setting', $domain ); ?></label>
        <select id="_subscription_ultimo_proration" <?= $is_update ? 'disabled' : '' ?> name="_reepay_subscription_ultimo[proration]" class="wc_input_subscription_period_interval">
            <option value="full_day" <?php selected( 'full_day', $ultimo['proration'], true ) ?>>Full day proration</option>
            <option value="by_minute" <?php selected( 'by_minute', $ultimo['proration'], true ) ?>>By the minute proration</option>
        </select>
    </p>
    <p class="form-field type-fields fields-ultimo <?= $variable ? 'form-row' : '' ?> hidden">
        <label for="_subscription_ultimo_proration_minimum"><?php esc_html_e( 'Minimum prorated amount', $domain ); ?></label>
        <input type="number" id="_subscription_ultimo_proration_minimum" name="_reepay_subscription_ultimo[proration_minimum]" placeholder="<?php esc_attr_e( 'kr 0.00', $domain ); ?>"  value="<?= !empty($ultimo['proration_minimum']) ? $ultimo['proration_minimum'] : 0?>" />
    </p>

    <!--Half-yearly-->
    <?php $half_yearly = !empty($meta['_reepay_subscription_half_yearly']) ? unserialize($meta['_reepay_subscription_half_yearly'][0]) : array() ?>
    <p class="form-field type-fields fields-half_yearly hidden">
        <label for="_subscription_half_yearly"><?php _e( 'Charge every', $domain ); ?></label>
        <strong><?php _e( '6th Month', $domain ); ?></strong>
    </p>
    <p class="form-field type-fields fields-half_yearly hidden">
        <label for="_subscription_half_yearly"><?php _e( 'On this day of the month:', $domain ); ?></label>
        <strong><?php _e( '1st', $domain ); ?></strong>
    </p>
    <p class="form-field type-fields fields-half_yearly hidden">
        <label for="_subscription_half_yearly"><?php _e( 'Fixed Months:', $domain ); ?></label>
        <strong><?php _e( 'Jan, Jul', $domain ); ?></strong>
    </p>
    <p class="form-field type-fields fields-half_yearly <?= $variable ? 'form-row' : '' ?> hidden">
        <label for="_subscription_half_yearly_period"><?php esc_html_e( 'Partial Period Handling', $domain ); ?></label>
        <select id="_subscription_half_yearly_period" <?= $is_update ? 'disabled' : '' ?> name="_reepay_subscription_half_yearly[period]" class="wc_input_subscription_period_interval">
            <option value="bill_prorated" <?php selected( 'bill_prorated', $half_yearly['period'], true ) ?>>Bill prorated (Default)</option>
            <option value="bill_full" <?php selected( 'bill_full', $half_yearly['period'], true ) ?>>Bill for full period</option>
            <option value="bill_zero_amount" <?php selected( 'bill_zero_amount', $half_yearly['period'], true ) ?>>Bill a zero amount</option>
            <option value="no_bill" <?php selected( 'no_bill', $half_yearly['period'], true ) ?>>Do not consider the partial period a billing period</option>
        </select>
    </p>
    <p class="form-field type-fields fields-half_yearly <?= $variable ? 'form-row' : '' ?> hidden">
        <label for="_subscription_half_yearly_proration"><?php esc_html_e( 'Proration setting', $domain ); ?></label>
        <select id="_subscription_half_yearly_proration" <?= $is_update ? 'disabled' : '' ?> name="_reepay_subscription_half_yearly[proration]" class="wc_input_subscription_period_interval">
            <option value="full_day" <?php selected( 'full_day', $half_yearly['proration'], true ) ?>>Full day proration</option>
            <option value="by_minute" <?php selected( 'by_minute', $half_yearly['proration'], true ) ?>>By the minute proration</option>
        </select>
    </p>
    <p class="form-field type-fields fields-half_yearly <?= $variable ? 'form-row' : '' ?> hidden">
        <label for="_subscription_half_yearly_proration_minimum"><?php esc_html_e( 'Minimum prorated amount', $domain ); ?></label>
        <input type="number" id="_subscription_half_yearly_proration_minimum" name="_reepay_subscription_half_yearly[proration_minimum]" placeholder="<?php esc_attr_e( 'kr 0.00', $domain ); ?>" value="<?= !empty($half_yearly['proration_minimum']) ? $half_yearly['proration_minimum'] : 0?>" />
    </p>


    <!--Yearly-->
    <?php $month_startdate_12 = !empty($meta['_reepay_subscription_month_startdate_12']) ? unserialize($meta['_reepay_subscription_month_startdate_12'][0]) : array() ?>
    <p class="form-field type-fields fields-month_startdate_12 <?= $variable ? 'form-row' : '' ?> hidden">
        <label for="_subscription_half_yearly"><?php _e( 'Charge every', $domain ); ?></label>
        <strong><?php _e( '12th Month', $domain ); ?></strong>
    </p>
    <p class="form-field type-fields fields-month_startdate_12 <?= $variable ? 'form-row' : '' ?> hidden">
        <label for="_subscription_month_startdate_12"><?php _e( 'On this day of the month:', $domain ); ?></label>
        <strong><?php _e( '1st', $domain ); ?></strong>
    </p>
    <p class="form-field type-fields fields-month_startdate_12 <?= $variable ? 'form-row' : '' ?> hidden">
        <label for="_subscription_month_startdate_12"><?php _e( 'Fixed Months:', $domain ); ?></label>
        <strong><?php _e( 'Jan', $domain ); ?></strong>
    </p>
    <p class="form-field type-fields fields-month_startdate_12 <?= $variable ? 'form-row' : '' ?> hidden">
        <label for="_subscription_month_startdate_12_period"><?php esc_html_e( 'Partial Period Handling', $domain ); ?></label>
        <select id="_subscription_month_startdate_12_period" <?= $is_update ? 'disabled' : '' ?> name="_reepay_subscription_month_startdate_12[period]" class="wc_input_subscription_period_interval">
            <option value="bill_prorated" <?php selected( 'bill_prorated', $month_startdate_12['period'], true ) ?>>Bill prorated (Default)</option>
            <option value="bill_full" <?php selected( 'bill_full', $month_startdate_12['period'], true ) ?>>Bill for full period</option>
            <option value="bill_zero_amount" <?php selected( 'bill_zero_amount', $month_startdate_12['period'], true ) ?>>Bill a zero amount</option>
            <option value="no_bill" <?php selected( 'no_bill', $month_startdate_12['period'], true ) ?>>Do not consider the partial period a billing period</option>
        </select>
    </p>
    <p class="form-field type-fields fields-month_startdate_12 <?= $variable ? 'form-row' : '' ?> hidden">
        <label for="_subscription_month_startdate_12_proration"><?php esc_html_e( 'Proration setting', $domain ); ?></label>
        <select id="_subscription_month_startdate_12_proration" <?= $is_update ? 'disabled' : '' ?> name="_reepay_subscription_month_startdate_12[proration]" class="wc_input_subscription_period_interval">
            <option value="full_day" <?php selected( 'full_day', $month_startdate_12['proration'], true ) ?>>Full day proration</option>
            <option value="by_minute" <?php selected( 'by_minute', $month_startdate_12['proration'], true ) ?>>By the minute proration</option>
        </select>
    </p>
    <p class="form-field type-fields fields-month_startdate_12 <?= $variable ? 'form-row' : '' ?> hidden">
        <label for="_subscription_month_startdate_12_proration_minimum"><?php esc_html_e( 'Minimum prorated amount', $domain ); ?></label>
        <input type="number" id="_subscription_month_startdate_12_proration_minimum" name="_reepay_subscription_month_startdate_12[proration_minimum]" placeholder="<?php esc_attr_e( 'kr 0.00', $domain ); ?>" value="<?= !empty($month_startdate_12['proration_minimum']) ? $month_startdate_12['proration_minimum'] : 0?>" />
    </p>


    <!--Fixed day of week-->
    <?php $weekly_fixedday = !empty($meta['_reepay_subscription_weekly_fixedday']) ? unserialize($meta['_reepay_subscription_weekly_fixedday'][0]) : array() ?>
    <p class="form-field type-fields fields-weekly_fixedday <?= $variable ? 'form-row' : '' ?> hidden">
        <label for="_subscription_weekly_fixedday"><?php esc_html_e( 'Charge every', $domain ); ?></label>
        <input type="number" id="_subscription_weekly_fixedday" <?= $is_update ? 'disabled' : '' ?> name="_reepay_subscription_weekly_fixedday[week]">
        &nbsp<?php esc_html_e( 'Week', $domain ); ?>
    </p>
    <p class="form-field type-fields fields-weekly_fixedday <?= $variable ? 'form-row' : '' ?> hidden">
        <label for="_subscription_weekly_fixedday_day"><?php esc_html_e( 'On this day of the month', $domain ); ?></label>
        <select id="_subscription_weekly_fixedday_day" <?= $is_update ? 'disabled' : '' ?> name="_reepay_subscription_weekly_fixedday[day]" class="wc_input_subscription_period_interval">
            <option value="1" <?php selected( '1', $weekly_fixedday['day'], true ) ?>>Monday</option>
            <option value="2" <?php selected( '2', $weekly_fixedday['day'], true ) ?>>Tuesday</option>
            <option value="3" <?php selected( '3', $weekly_fixedday['day'], true ) ?>>Wednesday</option>
            <option value="4" <?php selected( '4', $weekly_fixedday['day'], true ) ?>>Thursday</option>
            <option value="5" <?php selected( '5', $weekly_fixedday['day'], true ) ?>>Friday</option>
            <option value="6" <?php selected( '6', $weekly_fixedday['day'], true ) ?>>Saturday</option>
            <option value="7" <?php selected( '7', $weekly_fixedday['day'], true ) ?>>Sunday</option>
        </select>
    </p>
    <p class="form-field type-fields fields-weekly_fixedday <?= $variable ? 'form-row' : '' ?> hidden">
        <label for="_subscription_weekly_fixedday_period"><?php esc_html_e( 'Partial Period Handling', $domain ); ?></label>
        <select id="_subscription_weekly_fixedday_period" <?= $is_update ? 'disabled' : '' ?> name="_reepay_subscription_weekly_fixedday[period]" class="wc_input_subscription_period_interval">
            <option value="bill_prorated" <?php selected( 'bill_prorated', $weekly_fixedday['period'], true ) ?>>Bill prorated (Default)</option>
            <option value="bill_full" <?php selected( 'bill_prorated', $weekly_fixedday['period'], true ) ?>>Bill for full period</option>
            <option value="bill_zero_amount" <?php selected( 'bill_prorated', $weekly_fixedday['period'], true ) ?>>Bill a zero amount</option>
            <option value="no_bill" <?php selected( 'bill_prorated', $weekly_fixedday['period'], true ) ?>>Do not consider the partial period a billing period</option>
        </select>
    </p>
    <p class="form-field type-fields fields-weekly_fixedday <?= $variable ? 'form-row' : '' ?> hidden">
        <label for="_subscription_weekly_fixedday_proration"><?php esc_html_e( 'Proration setting', $domain ); ?></label>
        <select id="_subscription_weekly_fixedday_proration" <?= $is_update ? 'disabled' : '' ?> name="_reepay_subscription_weekly_fixedday[proration]" class="wc_input_subscription_period_interval">
            <option value="full_day" <?php selected( 'full_day', $weekly_fixedday['proration'], true ) ?>>Full day proration</option>
            <option value="by_minute" <?php selected( 'by_minute', $weekly_fixedday['proration'], true ) ?>>By the minute proration</option>
        </select>
    </p>
    <p class="form-field type-fields fields-weekly_fixedday <?= $variable ? 'form-row' : '' ?> hidden">
        <label for="_subscription_weekly_fixedday_proration_minimum"><?php esc_html_e( 'Minimum prorated amount', $domain ); ?></label>
        <input type="number" id="_subscription_weekly_fixedday_proration_minimum" name="_reepay_subscription_weekly_fixedday[proration_minimum]" placeholder="<?php esc_attr_e( 'kr 0.00', $domain ); ?>" value="<?= !empty($weekly_fixedday['proration_minimum']) ? $weekly_fixedday['proration_minimum'] : 0?>" />
    </p>

    <!--Advanced-->
    <p class="form-field advanced-fields <?= $variable ? 'dimensions_field form-row' : '' ?>">
        <label for="_reepay_subscription_renewal_reminder">
            <?php esc_html_e( 'Advanced', $domain ); ?>
        </label>
        <span class="wrap">
            <input type="number" id="_reepay_subscription_renewal_reminder" name="_reepay_subscription_renewal_reminder" class="wc_input_price wc_input_subscription_price" placeholder="<?php esc_html_e( 'Renewal Reminder Schedule', $domain ); ?>" value="<?php echo !empty($meta['_reepay_subscription_renewal_reminder'][0]) ? esc_attr($meta['_reepay_subscription_renewal_reminder'][0]) : ''?>"/>
            <input type="number" id="_reepay_subscription_default_quantity" <?= $is_update ? 'disabled' : '' ?> name="_reepay_subscription_default_quantity" class="wc_input_price wc_input_subscription_price" placeholder="<?php esc_html_e( 'Default Quantity', $domain ); ?>" value="<?php echo !empty($meta['_reepay_subscription_default_quantity'][0]) ? esc_attr($meta['_reepay_subscription_default_quantity'][0]) : ''?>"/>
        </span>
    </p>
</div>


<div class="options_group show_if_reepay_subscription">
    <p class="form-field <?= $variable ? 'form-row' : '' ?>">
        <label for="_subscription_contract_periods"><?php esc_html_e( 'Minimum Contract Period', $domain ); ?></label>
        <input type="number" id="_subscription_contract_periods" <?= $is_update ? 'disabled' : '' ?> name="_reepay_subscription_contract_periods" <?= $is_update ? 'disabled' : '' ?> placeholder="<?php esc_html_e( 'Periods', $domain ); ?>" value="<?= !empty($meta['_reepay_subscription_contract_periods'][0]) ? $meta['_reepay_subscription_contract_periods'][0] : 0?>" />
    </p>
    <p class="form-field fields-contract_periods hidden">
        <label for="_reepay_subscription_contract_periods_full"></label>
        <?php esc_html_e( 'When the subscription is created', $domain ); ?> &nbsp<input <?= $is_update ? 'disabled' : '' ?> type="radio" id="_reepay_subscription_contract_periods_full" name="_reepay_subscription_contract_periods_full" value="false" <?php checked( 'false', $meta['_reepay_subscription_contract_periods_full'][0], true ); ?>/>
        &nbsp&nbsp <?php esc_html_e( 'When the first period starts', $domain ); ?> &nbsp<input <?= $is_update ? 'disabled' : '' ?> type="radio" id="_reepay_subscription_contract_periods_full" name="_reepay_subscription_contract_periods_full" value="true" <?php checked( 'true', $meta['_reepay_subscription_contract_periods_full'][0], true ); ?>/>
    </p>
</div>

<div class="options_group show_if_reepay_subscription">
    <p class="form-field">
        <label for="_subscription_notice_period"><?php esc_html_e( 'Notice period', $domain ); ?></label>
        <input type="number" id="_subscription_notice_period" <?= $is_update ? 'disabled' : '' ?> name="_reepay_subscription_notice_period" placeholder="<?php esc_html_e( 'Periods', $domain ); ?>" value="<?= !empty($meta['_reepay_subscription_notice_period'][0]) ? $meta['_reepay_subscription_notice_period'][0] : 0?>" />
    </p>
    <p class="form-field fields-notice_period hidden">
        <label for="_subscription_notice_period_start"></label>
        <?php esc_html_e( 'When the current cancelled period ends', $domain ); ?> &nbsp<input <?= $is_update ? 'disabled' : '' ?> type="radio" id="_subscription_notice_period_start" name="_reepay_subscription_notice_period_start" value="true" <?php checked( 'true', $meta['_reepay_subscription_notice_period_start'][0], true ); ?>/>
        &nbsp&nbsp <?php esc_html_e( 'Immediately after cancellation', $domain ); ?> &nbsp<input <?= $is_update ? 'disabled' : '' ?> type="radio" id="_subscription_notice_period_start" name="_reepay_subscription_notice_period_start" value="false" <?php checked( 'false', $meta['_reepay_subscription_notice_period_start'][0], true ); ?>/>
    </p>
</div>

<div class="options_group show_if_reepay_subscription">
    <p class="form-field <?= $variable ? 'form-row' : '' ?>">
        <label for="_subscription_billing_cycles"><?php esc_html_e( 'Billing Cycles', $domain ); ?></label>
        <?php esc_html_e( 'Auto Renew until cancelled', $domain ); ?> &nbsp<input type="radio" id="_subscription_billing_cycles" <?= $is_update ? 'disabled' : '' ?> name="_reepay_subscription_billing_cycles" value="false" <?php checked( 'false', $meta['_reepay_subscription_billing_cycles'][0], true ); ?>/>
        &nbsp&nbsp <?php esc_html_e( 'Fixed Number of billing cycles', $domain ); ?> &nbsp<input type="radio" <?= $is_update ? 'disabled' : '' ?> id="_subscription_billing_cycles" name="_reepay_subscription_billing_cycles" value="true" <?php checked( 'true', $meta['_reepay_subscription_billing_cycles'][0], true ); ?>/>
    </p>
    <p class="form-field fields-billing_cycles <?= $variable ? 'form-row' : '' ?> hidden">
        <label for="_subscription_billing_cycles_period"><?php esc_html_e( 'Number of billing cycles', $domain ); ?></label>
        <input type="number" id="_subscription_billing_cycles_period" name="_reepay_subscription_billing_cycles_period" <?= $is_update ? 'disabled' : '' ?> placeholder="*" value="<?= !empty($meta['_reepay_subscription_billing_cycles_period'][0]) ? $meta['_reepay_subscription_billing_cycles_period'][0] : 0?>" />
    </p>
</div>

<?php $trial = !empty($meta['_reepay_subscription_trial']) ? unserialize($meta['_reepay_subscription_trial'][0]) : array()?>
<div class="options_group reepay_subscription_trial show_if_reepay_subscription">
    <p class="form-field <?= $variable ? 'form-row' : '' ?>">
        <label for="_subscription_trial"><?php esc_html_e( 'Trial', $domain ); ?></label>
        <select id="_subscription_trial" <?= $is_update ? 'disabled' : '' ?> name="_reepay_subscription_trial[type]" class="wc_input_subscription_period_interval">
            <?php foreach ( WC_Reepay_Subscription_Plans::$trial as $value => $label ) { ?>
                <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $value, $trial['type'], true ) ?>><?php echo esc_html( $label ); ?></option>
            <?php } ?>
        </select>
    </p>
    <p class="form-field trial-fields fields-customize <?= $variable ? 'form-row' : '' ?> hidden">
        <label for="_subscription_trial_length"><?php esc_html_e( 'Trial Length', $domain ); ?></label>
        <input type="number" id="_subscription_trial_length" <?= $is_update ? 'disabled' : '' ?> name="_reepay_subscription_trial[length]" placeholder="<?php esc_html_e( 'Length', $domain ); ?>" value="<?= !empty($trial['length']) ? $trial['length'] : 0?>" />
        <select id="_subscription_trial_unit" <?= $is_update ? 'disabled' : '' ?> name="_reepay_subscription_trial[unit]" class="wc_input_subscription_period_interval">
            <option value="days" <?php selected( 'days', $trial['unit'], true ) ?>>Days</option>
            <option value="months" <?php selected( 'months', $trial['unit'], true ) ?>>Months</option>
        </select>
    </p>
    <p class="form-field <?= $variable ? 'form-row' : '' ?>  trial-fields fields-7days fields-14days fields-1month fields-customize hidden">
        <label for="_subscription_billing_trial_reminder"><?php esc_html_e( 'Optional Trial Reminder Schedule', $domain ); ?></label>
        <input type="number" id="_subscription_trial_reminder"  name="_reepay_subscription_trial[reminder]" placeholder="<?php esc_html_e( 'Days', $domain ); ?>" value="<?= !empty($trial['reminder']) ? $trial['reminder'] : 0?>" />
    </p>
</div>

<?php $fee = !empty($meta['_reepay_subscription_fee']) ? unserialize($meta['_reepay_subscription_fee'][0]) : array()?>
<div class="options_group reepay_subscription_fee show_if_reepay_subscription">
    <p class="form-field">
        <label for="_subscription_fee"><?php esc_html_e( 'Include setup fee', $domain ); ?></label>
        <input type="checkbox" id="_subscription_fee" name="_reepay_subscription_fee[enabled]" value="yes" <?php checked( 'yes', $fee['enabled'], true ); ?> />
    </p>

    <p class="form-field fee-fields <?= $variable ? 'dimensions_field form-row' : '' ?> hidden">
        <label for="_subscription_fee_amount"><?php esc_html_e( 'Setup Fee (kr)', $domain ); ?></label>
        <span class="wrap">
            <input type="number" id="_subscription_fee_amount" name="_reepay_subscription_fee[amount]" class="wc_input_price wc_input_subscription_price" placeholder="<?php esc_attr_e( 'Amount', $domain ); ?>" step="any" min="0" value="<?= !empty($fee['amount']) ? $fee['amount'] : 0?>" />
            <input type="text" id="_subscription_fee_text" name="_reepay_subscription_fee[text]" placeholder="<?php esc_attr_e( 'Text', $domain ); ?>"  value="<?= !empty($fee['text']) ? $fee['text'] : ''?>" />
            <select id="_subscription_fee_handling" name="_reepay_subscription_fee[handling]" class="wc_input_subscription_period_interval">
                <option value="first" <?php selected( 'first', $fee['handling'], true ) ?>>Include setup fee as order line on the first scheduled invoice</option>
                <option value="separate" <?php selected( 'separate', $fee['handling'], true ) ?>>Create a separate invoice for the setup fee</option>
                <option value="separate_conditional" <?php selected( 'separate_conditional', $fee['handling'], true ) ?>>Create a separate invoice for the setup fee, if the first invoice is not created in conjunction with the creation</option>
            </select>
        </span>
    </p>
</div>

<div class="options_group show_if_reepay_simple_subscriptions clear"></div>