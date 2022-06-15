<?php
?>
<div class="options_group reepay_subscription_choose show_if_reepay_subscription">
    <p class="form-field choose-fields <?= $variable ? 'form-row' : '' ?> ">
        <label for="_subscription_price">
            <?php esc_html_e( 'Creation type', $domain ); ?>
        </label>
        <?php esc_html_e( 'Create new plan', $domain ); ?> &nbsp
        <input type="radio" id="_reepay_subscription_choose" name="_reepay_subscription_choose<?= $variable ? '['.$loop.']' : '' ?>" value="new" <?php checked( 'new', $_reepay_subscription_choose, true ); ?>>
        &nbsp&nbsp<?php esc_html_e( 'Choose existing plan', $domain ); ?> &nbsp
        <input type="radio" id="_reepay_subscription_choose" name="_reepay_subscription_choose<?= $variable ? '['.$loop.']' : '' ?>" value="exist" <?php checked( 'exist', $_reepay_subscription_choose, true ); ?>>
    </p>
</div>

<div class="reepay_subscription_choose_exist">
    <div class="options_group reepay_subscription_choose_exist show_if_reepay_subscription">
        <p class="form-field exist-fields <?= $variable ? 'dimensions_field form-row' : '' ?> ">
            <?php if(!empty($plans_list)):?>
            <select id="_subscription_choose_exist"  name="_reepay_choose_exist<?= $variable ? '['.$loop.']' : '' ?>" class="wc_input_subscription_period_interval">
                <option value=""><?php esc_html_e( 'Select plan', $domain ); ?></option>
                <?php foreach ($plans_list as $plan):?>
                    <option value="<?=$plan['handle']?>" <?php selected( $plan['handle'], $_reepay_choose_exist, true ) ?>><?=$plan['name']?></option>
                <?php endforeach; ?>
            </select>
            <?php else: ?>
                <?php esc_html_e( 'Plans list is empty', $domain ); ?>
            <?php endif; ?>
        </p>
    </div>
</div>

<div class="reepay_subscription_settings">
    <div class="options_group reepay_subscription_pricing show_if_reepay_subscription">
        <p class="form-field pricing-fields <?= $variable ? 'dimensions_field form-row' : '' ?> ">
            <label for="_subscription_price">
                <?php esc_html_e( 'Subscription pricing (kr)', $domain ); ?>
            </label>
            <span class="wrap">
            <input type="number" id="_subscription_price" <?= $is_update ? 'disabled' : '' ?> name="_reepay_subscription_price<?= $variable ? '['.$loop.']' : '' ?>" class="wc_input_price wc_input_subscription_price" placeholder="<?php esc_attr_e( 'e.g. 5.90', $domain ); ?>" step="any" min="0" value="<?php echo esc_attr( wc_format_localized_price( $_reepay_subscription_price ) ); ?>"/>
            <select id="_subscription_price_vat" <?= $is_update ? 'disabled' : '' ?> name="_reepay_subscription_vat<?= $variable ? '['.$loop.']' : '' ?>" class="wc_input_subscription_period_interval">
                <option value="include" <?php selected( 'include', $_reepay_subscription_vat, true ) ?>><?php esc_html_e( 'Incl. VAT', $domain ); ?></option>
                <option value="exclude" <?php selected( 'exclude', $_reepay_subscription_vat, true ) ?>><?php esc_html_e( 'Excl. VAT', $domain ); ?></option>
            </select>
            <select id="_subscription_schedule_type" <?= $is_update ? 'disabled' : '' ?> name="_reepay_subscription_schedule_type<?= $variable ? '['.$loop.']' : '' ?>" class="wc_input_subscription_period_interval">
                <?php foreach ( WC_Reepay_Subscription_Plans::$schedule_types as $value => $label ) { ?>
                    <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $value, $_reepay_subscription_schedule_type, true ) ?>><?php echo esc_html( $label ); ?></option>
                <?php } ?>
            </select>
        </span>
        </p>

        <!--Daily-->
        <p class="form-field type-fields fields-daily <?= $variable ? 'form-row' : '' ?> hidden">
            <label for="_reepay_subscription_daily"><?php esc_html_e( 'Charge every', $domain ); ?></label>
            <input type="number" id="_reepay_subscription_daily" name="_reepay_subscription_daily<?= $variable ? '['.$loop.']' : '' ?>" <?= $is_update ? 'disabled' : '' ?> value="<?= !empty($_reepay_subscription_daily) ? $_reepay_subscription_daily : 1?>">
            &nbsp<?php esc_html_e( 'Day', $domain ); ?>
        </p>

        <!--Monthly-->
        <p class="form-field type-fields fields-month_startdate <?= $variable ? 'form-row' : '' ?> hidden">
            <label for="_subscription_month_startdate"><?php esc_html_e( 'Charge every', $domain ); ?></label>
            <input type="number" id="_subscription_month_startdate" name="_reepay_subscription_month_startdate<?= $variable ? '['.$loop.']' : '' ?>" <?= $is_update ? 'disabled' : '' ?> value="<?= !empty($_reepay_subscription_month_startdate) ? $_reepay_subscription_month_startdate : 1?>">
            &nbsp<?php esc_html_e( 'Month', $domain ); ?>
        </p>

        <!--Fixed day of month-->
        <?php $month_fixedday = $_reepay_subscription_month_fixedday ?>
        <p class="form-field type-fields fields-month_fixedday <?= $variable ? 'form-row' : '' ?> hidden">
            <label for="_subscription_month_fixedday"><?php esc_html_e( 'Charge every', $domain ); ?></label>
            <input type="number" id="_subscription_month_fixedday" <?= $is_update ? 'disabled' : '' ?> name="_reepay_subscription_month_fixedday<?= $variable ? '['.$loop.']' : '' ?>[month]" value="<?= !empty($month_fixedday['month']) ? $month_fixedday['month'] : 1?>">
            &nbsp<?php esc_html_e( 'Month', $domain ); ?>
        </p>
        <p class="form-field type-fields fields-month_fixedday <?= $variable ? 'form-row' : '' ?> hidden">
            <label for="_subscription_month_fixedday_day"><?php esc_html_e( 'On this day of the month', $domain ); ?></label>
            <select id="_subscription_month_fixedday_day" <?= $is_update ? 'disabled' : '' ?> name="_reepay_subscription_month_fixedday<?= $variable ? '['.$loop.']' : '' ?>[day]" class="wc_input_subscription_period_interval">
                <?php for ($i = 1; $i <= 28; $i++) :?>
                    <option value="<?php echo $i ?>" <?php !empty($month_fixedday['day']) ?? selected( $i, $month_fixedday['day'], true ) ?>><?php echo $i ?></option>
                <?php endfor;?>
            </select>
        </p>
        <p class="form-field type-fields fields-month_fixedday <?= $variable ? 'form-row' : '' ?> hidden">
            <label for="_subscription_month_fixedday_period"><?php esc_html_e( 'Partial Period Handling', $domain ); ?></label>
            <select id="_subscription_month_fixedday_period" <?= $is_update ? 'disabled' : '' ?> name="_reepay_subscription_month_fixedday<?= $variable ? '['.$loop.']' : '' ?>[period]" class="wc_input_subscription_period_interval">
                <option value="bill_prorated" <?php !empty($month_fixedday['period']) ?? selected( 'bill_prorated', $month_fixedday['period'], true ) ?>>Bill prorated (Default)</option>
                <option value="bill_full" <?php !empty($month_fixedday['period']) ?? selected( 'bill_full', $month_fixedday['period'], true ) ?>>Bill for full period</option>
                <option value="bill_zero_amount" <?php !empty($month_fixedday['period']) ?? selected( 'bill_zero_amount', $month_fixedday['period'], true ) ?>>Bill a zero amount</option>
                <option value="no_bill" <?php !empty($month_fixedday['period']) ?? selected( 'no_bill', $month_fixedday['period'], true ) ?>>Do not consider the partial period a billing period</option>
            </select>
        </p>
        <p class="form-field type-fields fields-month_fixedday <?= $variable ? 'form-row' : '' ?> hidden">
            <label for="_subscription_month_fixedday_proration"><?php esc_html_e( 'Proration setting', $domain ); ?></label>
            <select id="_subscription_month_fixedday_proration" <?= $is_update ? 'disabled' : '' ?> name="_subscription_month_fixedday<?= $variable ? '['.$loop.']' : '' ?>[proration]" class="wc_input_subscription_period_interval">
                <option value="full_day" <?php !empty($month_fixedday['proration']) ?? selected( 'full_day', $month_fixedday['proration'], true ) ?>>Full day proration</option>
                <option value="by_minute" <?php !empty($month_fixedday['proration']) ?? selected( 'full_day', $month_fixedday['proration'], true ) ?>>By the minute proration</option>
            </select>
        </p>
        <p class="form-field type-fields fields-month_fixedday <?= $variable ? 'form-row' : '' ?> hidden">
            <label for="_subscription_month_fixedday_proration_minimum"><?php esc_html_e( 'Minimum prorated amount', $domain ); ?></label>
            <input type="number" id="_subscription_month_fixedday_proration_minimum"  value="<?= !empty($month_fixedday['proration_minimum']) ? $month_fixedday['proration_minimum'] : 0?>"  name="_reepay_subscription_month_fixedday<?= $variable ? '['.$loop.']' : '' ?>[proration_minimum]" placeholder="<?php esc_attr_e( 'kr 0.00', $domain ); ?>"/>
        </p>

        <!--Last day of month-->
        <?php $month_lastday = $_reepay_subscription_month_lastday ?>
        <p class="form-field type-fields fields-month_lastday <?= $variable ? 'form-row' : '' ?> hidden">
            <label for="_subscription_month_fixedday"><?php esc_html_e( 'Charge every', $domain ); ?></label>
            <input type="number" id="_subscription_month_lastday<?= $variable ? '['.$loop.']' : '' ?>[month]" <?= $is_update ? 'disabled' : '' ?> value="<?= !empty($month_lastday['month']) ? $month_lastday['month'] : 0?>" name="_reepay_subscription_month_lastday<?= $variable ? '['.$loop.']' : '' ?>[month]">
            &nbsp<?php esc_html_e( 'Month', $domain ); ?>
        </p>
        <p class="form-field type-fields fields-month_lastday <?= $variable ? 'form-row' : '' ?> hidden">
            <label for="_subscription_month_lastday_period"><?php esc_html_e( 'Partial Period Handling', $domain ); ?></label>
            <select id="_subscription_month_lastday_period" <?= $is_update ? 'disabled' : '' ?> name="_reepay_subscription_month_lastday<?= $variable ? '['.$loop.']' : '' ?>[period]" class="wc_input_subscription_period_interval">
                <option value="bill_prorated" <?php !empty($month_lastday['period']) ?? selected( 'bill_prorated', $month_lastday['period'], true ) ?>>Bill prorated (Default)</option>
                <option value="bill_full" <?php !empty($month_lastday['period']) ?? selected( 'bill_full', $month_lastday['period'], true ) ?>>Bill for full period</option>
                <option value="bill_zero_amount" <?php !empty($month_lastday['period']) ?? selected( 'bill_zero_amount', $month_lastday['period'], true ) ?>>Bill a zero amount</option>
                <option value="no_bill" <?php !empty($month_lastday['period']) ?? selected( 'no_bill', $month_lastday['period'], true ) ?>>Do not consider the partial period a billing period</option>
            </select>
        </p>
        <p class="form-field type-fields fields-month_lastday <?= $variable ? 'form-row' : '' ?> hidden">
            <label for="_subscription_month_lastday_proration"><?php esc_html_e( 'Proration setting', $domain ); ?></label>
            <select id="_subscription_month_lastday_proration" <?= $is_update ? 'disabled' : '' ?> name="_reepay_subscription_month_lastday<?= $variable ? '['.$loop.']' : '' ?>[proration]" class="wc_input_subscription_period_interval">
                <option value="full_day" <?php !empty($month_lastday['proration']) ?? selected( 'full_day', $month_lastday['proration'], true ) ?>>Full day proration</option>
                <option value="by_minute" <?php !empty($month_lastday['proration']) ?? selected( 'by_minute', $month_lastday['proration'], true ) ?>>By the minute proration</option>
            </select>
        </p>
        <p class="form-field type-fields fields-month_lastday <?= $variable ? 'form-row' : '' ?> hidden">
            <label for="_subscription_month_lastday_proration_minimum"><?php esc_html_e( 'Minimum prorated amount', $domain ); ?></label>
            <input type="number" id="_subscription_month_lastday_proration_minimum" value="<?= !empty($month_lastday['proration_minimum']) ? $month_lastday['proration_minimum'] : 0?>" name="_subscription_month_lastday<?= $variable ? '['.$loop.']' : '' ?>[proration_minimum]" placeholder="<?php esc_attr_e( 'kr 0.00', $domain ); ?>" />
        </p>

        <!--Quarterly Primo-->
        <?php $primo = $_reepay_subscription_primo ?>
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
            <select id="_subscription_primo_period" <?= $is_update ? 'disabled' : '' ?> name="_reepay_subscription_primo<?= $variable ? '['.$loop.']' : '' ?>[period]" class="wc_input_subscription_period_interval">
                <option value="bill_prorated" <?php !empty($primo['period']) ?? selected( 'bill_prorated', $primo['period'], true ) ?>>Bill prorated (Default)</option>
                <option value="bill_full" <?php !empty($primo['period']) ?? selected( 'bill_full', $primo['period'], true ) ?>>Bill for full period</option>
                <option value="bill_zero_amount" <?php !empty($primo['period']) ?? selected( 'bill_zero_amount', $primo['period'], true ) ?>>Bill a zero amount</option>
                <option value="no_bill" <?php !empty($primo['period']) ?? selected( 'no_bill', $primo['period'], true ) ?>>Do not consider the partial period a billing period</option>
            </select>
        </p>
        <p class="form-field type-fields fields-primo <?= $variable ? 'form-row' : '' ?> hidden">
            <label for="_subscription_primo_proration"><?php esc_html_e( 'Proration setting', $domain ); ?></label>
            <select id="_subscription_primo_proration" <?= $is_update ? 'disabled' : '' ?> name="_reepay_subscription_primo<?= $variable ? '['.$loop.']' : '' ?>[proration]" class="wc_input_subscription_period_interval">
                <option value="full_day" <?php !empty($primo['proration']) ?? selected( 'full_day', $primo['proration'], true ) ?>>Full day proration</option>
                <option value="by_minute" <?php !empty($primo['proration']) ?? selected( 'by_minute', $primo['proration'], true ) ?>>By the minute proration</option>
            </select>
        </p>
        <p class="form-field type-fields fields-primo <?= $variable ? 'form-row' : '' ?> hidden">
            <label for="_subscription_primo_proration_minimum"><?php esc_html_e( 'Minimum prorated amount', $domain ); ?></label>
            <input type="number" id="_subscription_primo_proration_minimum" name="_reepay_subscription_primo<?= $variable ? '['.$loop.']' : '' ?>[proration_minimum]" placeholder="<?php esc_attr_e( 'kr 0.00', $domain ); ?>" value="<?= !empty($primo['proration_minimum']) ? $primo['proration_minimum'] : 0?>" />
        </p>

        <!--Quarterly Ultimo-->
        <?php $ultimo = $_reepay_subscription_ultimo ?>
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
            <select id="_subscription_ultimo_period" <?= $is_update ? 'disabled' : '' ?> name="_reepay_subscription_ultimo<?= $variable ? '['.$loop.']' : '' ?>[period]" class="wc_input_subscription_period_interval">
                <option value="bill_prorated" <?php !empty($ultimo['period']) ?? selected( 'bill_prorated', $ultimo['period'], true ) ?>>Bill prorated (Default)</option>
                <option value="bill_full" <?php !empty($ultimo['period']) ?? selected( 'bill_full', $ultimo['period'], true ) ?>>Bill for full period</option>
                <option value="bill_zero_amount" <?php !empty($ultimo['period']) ?? selected( 'bill_zero_amount', $ultimo['period'], true ) ?>>Bill a zero amount</option>
                <option value="no_bill" <?php !empty($ultimo['period']) ?? selected( 'no_bill', $ultimo['period'], true ) ?>>Do not consider the partial period a billing period</option>
            </select>
        </p>
        <p class="form-field type-fields fields-ultimo <?= $variable ? 'form-row' : '' ?> hidden">
            <label for="_subscription_ultimo_proration"><?php esc_html_e( 'Proration setting', $domain ); ?></label>
            <select id="_subscription_ultimo_proration" <?= $is_update ? 'disabled' : '' ?> name="_reepay_subscription_ultimo<?= $variable ? '['.$loop.']' : '' ?>[proration]" class="wc_input_subscription_period_interval">
                <option value="full_day" <?php !empty($ultimo['proration']) ?? selected( 'full_day', $ultimo['proration'], true ) ?>>Full day proration</option>
                <option value="by_minute" <?php !empty($ultimo['proration']) ?? selected( 'by_minute', $ultimo['proration'], true ) ?>>By the minute proration</option>
            </select>
        </p>
        <p class="form-field type-fields fields-ultimo <?= $variable ? 'form-row' : '' ?> hidden">
            <label for="_subscription_ultimo_proration_minimum"><?php esc_html_e( 'Minimum prorated amount', $domain ); ?></label>
            <input type="number" id="_subscription_ultimo_proration_minimum" name="_reepay_subscription_ultimo<?= $variable ? '['.$loop.']' : '' ?>[proration_minimum]" placeholder="<?php esc_attr_e( 'kr 0.00', $domain ); ?>"  value="<?= !empty($ultimo['proration_minimum']) ? $ultimo['proration_minimum'] : 0?>" />
        </p>

        <!--Half-yearly-->
        <?php $half_yearly = $_reepay_subscription_half_yearly ?>
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
            <select id="_subscription_half_yearly_period" <?= $is_update ? 'disabled' : '' ?> name="_reepay_subscription_half_yearly<?= $variable ? '['.$loop.']' : '' ?>[period]" class="wc_input_subscription_period_interval">
                <option value="bill_prorated" <?php !empty($half_yearly['period']) ?? selected( 'bill_prorated', $half_yearly['period'], true ) ?>>Bill prorated (Default)</option>
                <option value="bill_full" <?php !empty($half_yearly['period']) ?? selected( 'bill_full', $half_yearly['period'], true ) ?>>Bill for full period</option>
                <option value="bill_zero_amount" <?php !empty($half_yearly['period']) ?? selected( 'bill_zero_amount', $half_yearly['period'], true ) ?>>Bill a zero amount</option>
                <option value="no_bill" <?php !empty($half_yearly['period']) ?? selected( 'no_bill', $half_yearly['period'], true ) ?>>Do not consider the partial period a billing period</option>
            </select>
        </p>
        <p class="form-field type-fields fields-half_yearly <?= $variable ? 'form-row' : '' ?> hidden">
            <label for="_subscription_half_yearly_proration"><?php esc_html_e( 'Proration setting', $domain ); ?></label>
            <select id="_subscription_half_yearly_proration" <?= $is_update ? 'disabled' : '' ?> name="_reepay_subscription_half_yearly<?= $variable ? '['.$loop.']' : '' ?>[proration]" class="wc_input_subscription_period_interval">
                <option value="full_day" <?php !empty($half_yearly['proration']) ?? selected( 'full_day', $half_yearly['proration'], true ) ?>>Full day proration</option>
                <option value="by_minute" <?php !empty($half_yearly['proration']) ?? selected( 'by_minute', $half_yearly['proration'], true ) ?>>By the minute proration</option>
            </select>
        </p>
        <p class="form-field type-fields fields-half_yearly <?= $variable ? 'form-row' : '' ?> hidden">
            <label for="_subscription_half_yearly_proration_minimum"><?php esc_html_e( 'Minimum prorated amount', $domain ); ?></label>
            <input type="number" id="_subscription_half_yearly_proration_minimum" name="_reepay_subscription_half_yearly<?= $variable ? '['.$loop.']' : '' ?>[proration_minimum]" placeholder="<?php esc_attr_e( 'kr 0.00', $domain ); ?>" value="<?= !empty($half_yearly['proration_minimum']) ? $half_yearly['proration_minimum'] : 0?>" />
        </p>


        <!--Yearly-->
        <?php $month_startdate_12 = $_reepay_subscription_month_startdate_12 ?>
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
            <select id="_subscription_month_startdate_12_period" <?= $is_update ? 'disabled' : '' ?> name="_reepay_subscription_month_startdate_12<?= $variable ? '['.$loop.']' : '' ?>[period]" class="wc_input_subscription_period_interval">
                <option value="bill_prorated" <?php !empty($month_startdate_12['period']) ?? selected( 'bill_prorated', $month_startdate_12['period'], true ) ?>>Bill prorated (Default)</option>
                <option value="bill_full" <?php !empty($month_startdate_12['period']) ?? selected( 'bill_full', $month_startdate_12['period'], true ) ?>>Bill for full period</option>
                <option value="bill_zero_amount" <?php !empty($month_startdate_12['period']) ?? selected( 'bill_zero_amount', $month_startdate_12['period'], true ) ?>>Bill a zero amount</option>
                <option value="no_bill" <?php !empty($month_startdate_12['period']) ?? selected( 'no_bill', $month_startdate_12['period'], true ) ?>>Do not consider the partial period a billing period</option>
            </select>
        </p>
        <p class="form-field type-fields fields-month_startdate_12 <?= $variable ? 'form-row' : '' ?> hidden">
            <label for="_subscription_month_startdate_12_proration"><?php esc_html_e( 'Proration setting', $domain ); ?></label>
            <select id="_subscription_month_startdate_12_proration" <?= $is_update ? 'disabled' : '' ?> name="_reepay_subscription_month_startdate_12<?= $variable ? '['.$loop.']' : '' ?>[proration]" class="wc_input_subscription_period_interval">
                <option value="full_day" <?php !empty($month_startdate_12['proration']) ?? selected( 'full_day', $month_startdate_12['proration'], true ) ?>>Full day proration</option>
                <option value="by_minute" <?php !empty($month_startdate_12['proration']) ?? selected( 'by_minute', $month_startdate_12['proration'], true ) ?>>By the minute proration</option>
            </select>
        </p>
        <p class="form-field type-fields fields-month_startdate_12 <?= $variable ? 'form-row' : '' ?> hidden">
            <label for="_subscription_month_startdate_12_proration_minimum"><?php esc_html_e( 'Minimum prorated amount', $domain ); ?></label>
            <input type="number" id="_subscription_month_startdate_12_proration_minimum" name="_reepay_subscription_month_startdate_12<?= $variable ? '['.$loop.']' : '' ?>[proration_minimum]" placeholder="<?php esc_attr_e( 'kr 0.00', $domain ); ?>" value="<?= !empty($month_startdate_12['proration_minimum']) ? $month_startdate_12['proration_minimum'] : 0?>" />
        </p>


        <!--Fixed day of week-->
        <?php $weekly_fixedday = $_reepay_subscription_weekly_fixedday ?>
        <p class="form-field type-fields fields-weekly_fixedday <?= $variable ? 'form-row' : '' ?> hidden">
            <label for="_subscription_weekly_fixedday"><?php esc_html_e( 'Charge every', $domain ); ?></label>
            <input type="number" id="_subscription_weekly_fixedday" <?= $is_update ? 'disabled' : '' ?> name="_reepay_subscription_weekly_fixedday<?= $variable ? '['.$loop.']' : '' ?>[week]">
            &nbsp<?php esc_html_e( 'Week', $domain ); ?>
        </p>
        <p class="form-field type-fields fields-weekly_fixedday <?= $variable ? 'form-row' : '' ?> hidden">
            <label for="_subscription_weekly_fixedday_day"><?php esc_html_e( 'On this day of the week', $domain ); ?></label>
            <select id="_subscription_weekly_fixedday_day" <?= $is_update ? 'disabled' : '' ?> name="_reepay_subscription_weekly_fixedday<?= $variable ? '['.$loop.']' : '' ?>[day]" class="wc_input_subscription_period_interval">
                <option value="1" <?php !empty($weekly_fixedday['day']) ?? selected( '1', $weekly_fixedday['day'], true ) ?>>Monday</option>
                <option value="2" <?php !empty($weekly_fixedday['day']) ?? selected( '2', $weekly_fixedday['day'], true ) ?>>Tuesday</option>
                <option value="3" <?php !empty($weekly_fixedday['day']) ?? selected( '3', $weekly_fixedday['day'], true ) ?>>Wednesday</option>
                <option value="4" <?php !empty($weekly_fixedday['day']) ?? selected( '4', $weekly_fixedday['day'], true ) ?>>Thursday</option>
                <option value="5" <?php !empty($weekly_fixedday['day']) ?? selected( '5', $weekly_fixedday['day'], true ) ?>>Friday</option>
                <option value="6" <?php !empty($weekly_fixedday['day']) ?? selected( '6', $weekly_fixedday['day'], true ) ?>>Saturday</option>
                <option value="7" <?php !empty($weekly_fixedday['day']) ?? selected( '7', $weekly_fixedday['day'], true ) ?>>Sunday</option>
            </select>
        </p>
        <p class="form-field type-fields fields-weekly_fixedday <?= $variable ? 'form-row' : '' ?> hidden">
            <label for="_subscription_weekly_fixedday_period"><?php esc_html_e( 'Partial Period Handling', $domain ); ?></label>
            <select id="_subscription_weekly_fixedday_period" <?= $is_update ? 'disabled' : '' ?> name="_reepay_subscription_weekly_fixedday<?= $variable ? '['.$loop.']' : '' ?>[period]" class="wc_input_subscription_period_interval">
                <option value="bill_prorated" <?php !empty($weekly_fixedday['period']) ?? selected( 'bill_prorated', $weekly_fixedday['period'], true ) ?>>Bill prorated (Default)</option>
                <option value="bill_full" <?php !empty($weekly_fixedday['period']) ?? selected( 'bill_prorated', $weekly_fixedday['period'], true ) ?>>Bill for full period</option>
                <option value="bill_zero_amount" <?php !empty($weekly_fixedday['period']) ?? selected( 'bill_prorated', $weekly_fixedday['period'], true ) ?>>Bill a zero amount</option>
                <option value="no_bill" <?php !empty($weekly_fixedday['period']) ?? selected( 'bill_prorated', $weekly_fixedday['period'], true ) ?>>Do not consider the partial period a billing period</option>
            </select>
        </p>
        <p class="form-field type-fields fields-weekly_fixedday <?= $variable ? 'form-row' : '' ?> hidden">
            <label for="_subscription_weekly_fixedday_proration"><?php esc_html_e( 'Proration setting', $domain ); ?></label>
            <select id="_subscription_weekly_fixedday_proration" <?= $is_update ? 'disabled' : '' ?> name="_reepay_subscription_weekly_fixedday<?= $variable ? '['.$loop.']' : '' ?>[proration]" class="wc_input_subscription_period_interval">
                <option value="full_day" <?php !empty($weekly_fixedday['proration']) ?? selected( 'full_day', $weekly_fixedday['proration'], true ) ?>>Full day proration</option>
                <option value="by_minute" <?php !empty($weekly_fixedday['proration']) ?? selected( 'by_minute', $weekly_fixedday['proration'], true ) ?>>By the minute proration</option>
            </select>
        </p>
        <p class="form-field type-fields fields-weekly_fixedday <?= $variable ? 'form-row' : '' ?> hidden">
            <label for="_subscription_weekly_fixedday_proration_minimum"><?php esc_html_e( 'Minimum prorated amount', $domain ); ?></label>
            <input type="number" id="_subscription_weekly_fixedday_proration_minimum" name="_reepay_subscription_weekly_fixedday<?= $variable ? '['.$loop.']' : '' ?>[proration_minimum]" placeholder="<?php esc_attr_e( 'kr 0.00', $domain ); ?>" value="<?= !empty($weekly_fixedday['proration_minimum']) ? $weekly_fixedday['proration_minimum'] : 0?>" />
        </p>

        <!--Advanced-->
        <p class="form-field advanced-fields <?= $variable ? 'dimensions_field form-row' : '' ?>">
            <label for="_reepay_subscription_renewal_reminder">
                <?php esc_html_e( 'Advanced', $domain ); ?>
            </label>
            <span class="wrap">
            <input type="number" id="_reepay_subscription_renewal_reminder" name="_reepay_subscription_renewal_reminder<?= $variable ? '['.$loop.']' : '' ?>" class="wc_input_price wc_input_subscription_price" placeholder="<?php esc_html_e( 'Renewal Reminder Schedule', $domain ); ?>" value="<?php echo !empty($_reepay_subscription_renewal_reminder) ? esc_attr($_reepay_subscription_renewal_reminder) : ''?>"/>
            <input type="number" id="_reepay_subscription_default_quantity" <?= $is_update ? 'disabled' : '' ?> name="_reepay_subscription_default_quantity<?= $variable ? '['.$loop.']' : '' ?>" class="wc_input_price wc_input_subscription_price" placeholder="<?php esc_html_e( 'Default Quantity', $domain ); ?>" value="<?php echo !empty($_reepay_subscription_default_quantity) ? esc_attr($_reepay_subscription_default_quantity) : '1'?>"/>
        </span>
        </p>
    </div>

    <div class="options_group show_if_reepay_subscription">
        <p class="form-field <?= $variable ? 'form-row' : '' ?>">
            <label for="_subscription_contract_periods"><?php esc_html_e( 'Minimum Contract Period', $domain ); ?></label>
            <input type="number" id="_subscription_contract_periods" <?= $is_update ? 'disabled' : '' ?> name="_reepay_subscription_contract_periods<?= $variable ? '['.$loop.']' : '' ?>" <?= $is_update ? 'disabled' : '' ?> placeholder="<?php esc_html_e( 'Periods', $domain ); ?>" value="<?= !empty($_reepay_subscription_contract_periods) ? $_reepay_subscription_contract_periods : 0?>" />
        </p>
        <p class="form-field fields-contract_periods hidden">
            <label for="_reepay_subscription_contract_periods_full"></label>
            <?php esc_html_e( 'When the subscription is created', $domain ); ?> &nbsp<input <?= $is_update ? 'disabled' : '' ?> type="radio" id="_reepay_subscription_contract_periods_full" name="_reepay_subscription_contract_periods_full<?= $variable ? '['.$loop.']' : '' ?>" value="false" <?php checked( 'false', $_reepay_subscription_contract_periods_full, true ); ?>/>
            &nbsp&nbsp <?php esc_html_e( 'When the first period starts', $domain ); ?> &nbsp<input <?= $is_update ? 'disabled' : '' ?> type="radio" id="_reepay_subscription_contract_periods_full" name="_reepay_subscription_contract_periods_full<?= $variable ? '['.$loop.']' : '' ?>" value="true" <?php checked( 'true', $_reepay_subscription_contract_periods_full, true ); ?>/>
        </p>
    </div>

    <div class="options_group show_if_reepay_subscription">
        <p class="form-field">
            <label for="_subscription_notice_period"><?php esc_html_e( 'Notice period', $domain ); ?></label>
            <input type="number" id="_subscription_notice_period" <?= $is_update ? 'disabled' : '' ?> name="_reepay_subscription_notice_period<?= $variable ? '['.$loop.']' : '' ?>" placeholder="<?php esc_html_e( 'Periods', $domain ); ?>" value="<?= !empty($_reepay_subscription_notice_period) ? $_reepay_subscription_notice_period : 0?>" />
        </p>
        <p class="form-field fields-notice_period hidden">
            <label for="_subscription_notice_period_start"></label>
            <?php esc_html_e( 'When the current cancelled period ends', $domain ); ?> &nbsp<input <?= $is_update ? 'disabled' : '' ?> type="radio" id="_subscription_notice_period_start" name="_reepay_subscription_notice_period_start<?= $variable ? '['.$loop.']' : '' ?>" value="true" <?php checked( 'true', $_reepay_subscription_notice_period_start, true ); ?>/>
            &nbsp&nbsp <?php esc_html_e( 'Immediately after cancellation', $domain ); ?> &nbsp<input <?= $is_update ? 'disabled' : '' ?> type="radio" id="_subscription_notice_period_start" name="_reepay_subscription_notice_period_start<?= $variable ? '['.$loop.']' : '' ?>" value="false" <?php checked( 'false', $_reepay_subscription_notice_period_start, true ); ?>/>
        </p>
    </div>

    <div class="options_group show_if_reepay_subscription">
        <p class="form-field <?= $variable ? 'form-row' : '' ?>">
            <label for="_subscription_billing_cycles"><?php esc_html_e( 'Billing Cycles', $domain ); ?></label>
            <?php esc_html_e( 'Auto Renew until cancelled', $domain ); ?> &nbsp<input type="radio" id="_subscription_billing_cycles" <?= $is_update ? 'disabled' : '' ?> name="_reepay_subscription_billing_cycles<?= $variable ? '['.$loop.']' : '' ?>" value="false" <?php checked( 'false', $_reepay_subscription_billing_cycles, true ); ?>/>
            &nbsp&nbsp <?php esc_html_e( 'Fixed Number of billing cycles', $domain ); ?> &nbsp<input type="radio" <?= $is_update ? 'disabled' : '' ?> id="_subscription_billing_cycles" name="_reepay_subscription_billing_cycles<?= $variable ? '['.$loop.']' : '' ?>" value="true" <?php checked( 'true', $_reepay_subscription_billing_cycles, true ); ?>/>
        </p>
        <p class="form-field fields-billing_cycles <?= $variable ? 'form-row' : '' ?> hidden">
            <label for="_subscription_billing_cycles_period"><?php esc_html_e( 'Number of billing cycles', $domain ); ?></label>
            <input type="number" id="_subscription_billing_cycles_period" name="_reepay_subscription_billing_cycles_period<?= $variable ? '['.$loop.']' : '' ?>" <?= $is_update ? 'disabled' : '' ?> placeholder="*" value="<?= !empty($_reepay_subscription_billing_cycles_period) ? $_reepay_subscription_billing_cycles_period : 0?>" />
        </p>
    </div>

    <?php $trial = $_reepay_subscription_trial?>
    <div class="options_group reepay_subscription_trial show_if_reepay_subscription">
        <p class="form-field <?= $variable ? 'form-row' : '' ?>">
            <label for="_subscription_trial"><?php esc_html_e( 'Trial', $domain ); ?></label>
            <select id="_subscription_trial" <?= $is_update ? 'disabled' : '' ?> name="_reepay_subscription_trial<?= $variable ? '['.$loop.']' : '' ?>[type]" class="wc_input_subscription_period_interval">
                <?php foreach ( WC_Reepay_Subscription_Plans::$trial as $value => $label ) { ?>
                    <option value="<?php echo esc_attr( $value ); ?>" <?php !empty($trial['type']) ?? selected( $value, $trial['type'], true ) ?>><?php echo esc_html_e( $label ); ?></option>
                <?php } ?>
            </select>
        </p>
        <p class="form-field trial-fields fields-customize <?= $variable ? 'form-row' : '' ?> hidden">
            <label for="_subscription_trial_length"><?php esc_html_e( 'Trial Length', $domain ); ?></label>
            <input type="number" id="_subscription_trial_length" <?= $is_update ? 'disabled' : '' ?> name="_reepay_subscription_trial<?= $variable ? '['.$loop.']' : '' ?>[length]" placeholder="<?php esc_html_e( 'Length', $domain ); ?>" value="<?= !empty($trial['length']) ? $trial['length'] : 0?>" />
            <select id="_subscription_trial_unit" <?= $is_update ? 'disabled' : '' ?> name="_reepay_subscription_trial<?= $variable ? '['.$loop.']' : '' ?>[unit]" class="wc_input_subscription_period_interval">
                <option value="days" <?php !empty($trial['unit']) ?? selected( 'days', $trial['unit'], true ) ?>>Days</option>
                <option value="months" <?php !empty($trial['unit']) ?? selected( 'months', $trial['unit'], true ) ?>>Months</option>
            </select>
        </p>
        <p class="form-field <?= $variable ? 'form-row' : '' ?>  trial-fields fields-7days fields-14days fields-1month fields-customize hidden">
            <label for="_subscription_billing_trial_reminder"><?php esc_html_e( 'Optional Trial Reminder Schedule', $domain ); ?></label>
            <input type="number" id="_subscription_trial_reminder"  name="_reepay_subscription_trial<?= $variable ? '['.$loop.']' : '' ?>[reminder]" placeholder="<?php esc_html_e( 'Days', $domain ); ?>" value="<?= !empty($trial['reminder']) ? $trial['reminder'] : 0?>" />
        </p>
    </div>

    <?php $fee = $_reepay_subscription_fee;?>
    <div class="options_group reepay_subscription_fee show_if_reepay_subscription">
        <p class="form-field">
            <label for="_subscription_fee"><?php esc_html_e( 'Include setup fee', $domain ); ?></label>
            <input type="checkbox" id="_subscription_fee" name="_reepay_subscription_fee<?= $variable ? '['.$loop.']' : '' ?>[enabled]" value="yes" <?= !empty($fee['enabled']) && $fee['enabled'] == 'yes' ? 'checked' : '' ?> />
        </p>

        <p class="form-field fee-fields <?= $variable ? 'dimensions_field form-row' : '' ?> hidden">
            <label for="_subscription_fee_amount"><?php esc_html_e( 'Setup Fee (kr)', $domain ); ?></label>
            <span class="wrap">
            <input type="number" id="_subscription_fee_amount" name="_reepay_subscription_fee<?= $variable ? '['.$loop.']' : '' ?>[amount]" class="wc_input_price wc_input_subscription_price" placeholder="<?php esc_attr_e( 'Amount', $domain ); ?>" step="any" min="0" value="<?= !empty($fee['amount']) ? $fee['amount'] : 0?>" />
            <input type="text" id="_subscription_fee_text" name="_reepay_subscription_fee<?= $variable ? '['.$loop.']' : '' ?>[text]" placeholder="<?php esc_attr_e( 'Text', $domain ); ?>"  value="<?= !empty($fee['text']) ? $fee['text'] : ''?>" />
            <select id="_subscription_fee_handling" name="_reepay_subscription_fee<?= $variable ? '['.$loop.']' : '' ?>[handling]" class="wc_input_subscription_period_interval">
                <option value="first" <?php !empty($fee['handling']) ?? selected( 'first', $fee['handling'], true ) ?>><?php esc_html_e( 'Include setup fee as order line on the first scheduled invoice', $domain ); ?></option>
                <option value="separate" <?php !empty($fee['handling']) ?? selected( 'separate', $fee['handling'], true ) ?>><?php esc_html_e( 'Create a separate invoice for the setup fee', $domain ); ?></option>
                <option value="separate_conditional" <?php !empty($fee['handling']) ?? selected( 'separate_conditional', $fee['handling'], true ) ?>><?php esc_html_e( 'Create a separate invoice for the setup fee, if the first invoice is not created in conjunction with the creation', $domain ); ?></option>
            </select>
        </span>
        </p>
    </div>
    <div class="options_group reepay_subscription_compensation show_if_reepay_subscription">
        <p class="form-field">
            <label for="_subscription_compensation"><?php esc_html_e( 'Compensation method', $domain ); ?></label>
            <select id="_subscription_compensation" name="_reepay_subscription_compensation<?= $variable ? '['.$loop.']' : '' ?>" class="wc_input_subscription_period_interval">
                <option value="none" <?php selected( 'none', $_reepay_subscription_compensation, true ) ?>><?php esc_html_e( 'None', $domain ); ?></option>
                <option value="full_refund" <?php selected( 'full_refund', $_reepay_subscription_compensation, true ) ?>><?php esc_html_e( 'Full refund', $domain ); ?></option>
                <option value="prorated_refund" <?php selected( 'prorated_refund', $_reepay_subscription_compensation, true ) ?>><?php esc_html_e( 'Prorated refund', $domain ); ?></option>
                <option value="full_credit" <?php selected( 'full_credit', $_reepay_subscription_compensation, true ) ?>><?php esc_html_e( 'Full credit', $domain ); ?></option>
                <option value="prorated_credit" <?php selected( 'prorated_credit', $_reepay_subscription_compensation, true ) ?>><?php esc_html_e( 'Prorated credit', $domain ); ?></option>
            </select>
        </p>
    </div>
</div>

<div class="options_group show_if_reepay_simple_subscriptions clear"></div>