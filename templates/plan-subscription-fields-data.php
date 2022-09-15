<?php
$disabled = '';
$variable = !empty($variable);
?>
    <div class="options_group reepay_subscription_pricing show_if_reepay_subscription">
        <p class="form-field fields-name <?php echo $variable ? 'form-row' : '' ?>">
            <label for="_reepay_subscription_name"><?php esc_html_e('Name', $domain); ?></label>
            <input type="text" id="_reepay_subscription_name" <?php echo $disabled ?>
                   name="_reepay_subscription_name<?php echo $variable ? '[' . $loop . ']' : '' ?>"
                   value="<?php echo !empty($_reepay_subscription_name) ? $_reepay_subscription_name : '' ?>">
        </p>
        <p class="form-field pricing-fields <?php echo $variable ? 'dimensions_field form-row' : '' ?> ">
            <label for="_subscription_price">
                <?php esc_html_e('Subscription pricing (kr)', $domain); ?>
            </label>
            <span class="wrap">
            <input type="number"
                   id="_subscription_price" <?php echo $disabled ?> name="_reepay_subscription_price<?php echo $variable ? '[' . $loop . ']' : '' ?>"
                   class="wc_input_price wc_input_subscription_price"
                   placeholder="<?php esc_attr_e('e.g. 5.90', $domain); ?>" step="any" min="0"
                   value="<?php echo !empty($_reepay_subscription_price) ?? esc_attr(wc_format_localized_price($_reepay_subscription_price)); ?>"/>
            <select id="_subscription_schedule_type" <?php echo $disabled ?> name="_reepay_subscription_schedule_type<?php echo $variable ? '[' . $loop . ']' : '' ?>"
                    class="wc_input_subscription_period_interval">
                <?php foreach (WC_Reepay_Subscription_Plan_Simple::$schedule_types as $value => $label) { ?>
                    <option value="<?php esc_attr_e($value); ?>" <?php !empty($_reepay_subscription_schedule_type) ?? selected($value, $_reepay_subscription_schedule_type, true) ?>><?php esc_html_e($label); ?></option>
                <?php } ?>
            </select>
        </span>
        </p>

        <!--Daily-->
        <p class="form-field type-fields fields-daily <?php echo $variable ? 'form-row' : '' ?> hidden">
            <label for="_reepay_subscription_daily"><?php esc_html_e('Charge every', $domain); ?></label>
            <input type="number" min="0" id="_reepay_subscription_daily" <?php echo $disabled ?>
                   name="_reepay_subscription_daily<?php echo $variable ? '[' . $loop . ']' : '' ?>"
                   value="<?php echo !empty($_reepay_subscription_daily) ? $_reepay_subscription_daily : 1 ?>">
            &nbsp<?php esc_html_e('Day', $domain); ?>
        </p>

        <!--Monthly-->
        <p class="form-field type-fields fields-month_startdate <?php echo $variable ? 'form-row' : '' ?> hidden">
            <label for="_subscription_month_startdate"><?php esc_html_e('Charge every', $domain); ?></label>
            <input type="number" min="0" id="_subscription_month_startdate" <?php echo $disabled ?>
                   name="_reepay_subscription_month_startdate<?php echo $variable ? '[' . $loop . ']' : '' ?>"
                   value="<?php echo !empty($_reepay_subscription_month_startdate) ? $_reepay_subscription_month_startdate : 1 ?>">
            &nbsp<?php esc_html_e('Month', $domain); ?>
        </p>

        <!--Fixed day of month-->
        <?php $month_fixedday = !empty($_reepay_subscription_month_fixedday) ? $_reepay_subscription_month_fixedday : [] ?>
        <p class="form-field type-fields fields-month_fixedday <?php echo $variable ? 'form-row' : '' ?> hidden">
            <label for="_subscription_month_fixedday"><?php esc_html_e('Charge every', $domain); ?></label>
            <input type="number" min="0" id="_subscription_month_fixedday" <?php echo $disabled ?>
                   name="_reepay_subscription_month_fixedday<?php echo $variable ? '[' . $loop . ']' : '' ?>[month]"
                   value="<?php echo !empty($month_fixedday['month']) ? $month_fixedday['month'] : 1 ?>">
            &nbsp<?php esc_html_e('Month', $domain); ?>
        </p>
        <p class="form-field type-fields fields-month_fixedday <?php echo $variable ? 'form-row' : '' ?> hidden">
            <label for="_subscription_month_fixedday_day"><?php esc_html_e('On this day of the month', $domain); ?></label>
            <select id="_subscription_month_fixedday_day" <?php echo $disabled ?>
                    name="_reepay_subscription_month_fixedday<?php echo $variable ? '[' . $loop . ']' : '' ?>[day]"
                    class="wc_input_subscription_period_interval">
                <?php for ($i = 1; $i <= 28; $i++) : ?>
                    <option value="<?php echo $i ?>" <?php !empty($month_fixedday['day']) ?? selected($i, $month_fixedday['day'], true) ?>><?php echo $i ?></option>
                <?php endfor; ?>
            </select>
        </p>
        <p class="form-field type-fields fields-month_fixedday <?php echo $variable ? 'form-row' : '' ?> hidden">
            <label for="_subscription_month_fixedday_period"><?php esc_html_e('Partial Period Handling', $domain); ?></label>
            <select id="_subscription_month_fixedday_period" <?php echo $disabled ?>
                    name="_reepay_subscription_month_fixedday<?php echo $variable ? '[' . $loop . ']' : '' ?>[period]"
                    class="wc_input_subscription_period_interval">
                <option value="bill_prorated" <?php !empty($month_fixedday['period']) ?? selected('bill_prorated', $month_fixedday['period'], true) ?>>
                    Bill prorated (Default)
                </option>
                <option value="bill_full" <?php !empty($month_fixedday['period']) ?? selected('bill_full', $month_fixedday['period'], true) ?>>
                    Bill for full period
                </option>
                <option value="bill_zero_amount" <?php !empty($month_fixedday['period']) ?? selected('bill_zero_amount', $month_fixedday['period'], true) ?>>
                    Bill a zero amount
                </option>
                <option value="no_bill" <?php !empty($month_fixedday['period']) ?? selected('no_bill', $month_fixedday['period'], true) ?>>
                    Do not consider the partial period a billing period
                </option>
            </select>
        </p>
        <p class="form-field type-fields fields-month_fixedday <?php echo $variable ? 'form-row' : '' ?> hidden">
            <label for="_subscription_month_fixedday_proration"><?php esc_html_e('Proration setting', $domain); ?></label>
            <select id="_subscription_month_fixedday_proration" <?php echo $disabled ?>
                    name="_subscription_month_fixedday<?php echo $variable ? '[' . $loop . ']' : '' ?>[proration]"
                    class="wc_input_subscription_period_interval">
                <option value="full_day" <?php !empty($month_fixedday['proration']) ?? selected('full_day', $month_fixedday['proration'], true) ?>>
                    Full day proration
                </option>
                <option value="by_minute" <?php !empty($month_fixedday['proration']) ?? selected('full_day', $month_fixedday['proration'], true) ?>>
                    By the minute proration
                </option>
            </select>
        </p>
        <p class="form-field type-fields fields-month_fixedday <?php echo $variable ? 'form-row' : '' ?> hidden">
            <label for="_subscription_month_fixedday_proration_minimum"><?php esc_html_e('Minimum prorated amount', $domain); ?></label>
            <input type="number" min="0" id="_subscription_month_fixedday_proration_minimum" <?php echo $disabled ?>
                   value="<?php echo !empty($month_fixedday['proration_minimum']) ? $month_fixedday['proration_minimum'] : 0 ?>"
                   name="_reepay_subscription_month_fixedday<?php echo $variable ? '[' . $loop . ']' : '' ?>[proration_minimum]"
                   placeholder="<?php esc_attr_e('kr 0.00', $domain); ?>"/>
        </p>

        <!--Last day of month-->
        <?php $month_lastday = !empty($_reepay_subscription_month_lastday) ? $_reepay_subscription_month_lastday : [] ?>
        <p class="form-field type-fields fields-month_lastday <?php echo $variable ? 'form-row' : '' ?> hidden">
            <label for="_subscription_month_fixedday"><?php esc_html_e('Charge every', $domain); ?></label>
            <input type="number" min="0"
                   id="_subscription_month_lastday<?php echo $variable ? '[' . $loop . ']' : '' ?>[month]" <?php echo $disabled ?>
                   value="<?php echo !empty($month_lastday['month']) ? $month_lastday['month'] : 0 ?>"
                   name="_reepay_subscription_month_lastday<?php echo $variable ? '[' . $loop . ']' : '' ?>[month]">
            &nbsp<?php esc_html_e('Month', $domain); ?>
        </p>
        <p class="form-field type-fields fields-month_lastday <?php echo $variable ? 'form-row' : '' ?> hidden">
            <label for="_subscription_month_lastday_period"><?php esc_html_e('Partial Period Handling', $domain); ?></label>
            <select id="_subscription_month_lastday_period" <?php echo $disabled ?>
                    name="_reepay_subscription_month_lastday<?php echo $variable ? '[' . $loop . ']' : '' ?>[period]"
                    class="wc_input_subscription_period_interval">
                <option value="bill_prorated" <?php !empty($month_lastday['period']) ?? selected('bill_prorated', $month_lastday['period'], true) ?>>
                    Bill prorated (Default)
                </option>
                <option value="bill_full" <?php !empty($month_lastday['period']) ?? selected('bill_full', $month_lastday['period'], true) ?>>
                    Bill for full period
                </option>
                <option value="bill_zero_amount" <?php !empty($month_lastday['period']) ?? selected('bill_zero_amount', $month_lastday['period'], true) ?>>
                    Bill a zero amount
                </option>
                <option value="no_bill" <?php !empty($month_lastday['period']) ?? selected('no_bill', $month_lastday['period'], true) ?>>
                    Do not consider the partial period a billing period
                </option>
            </select>
        </p>
        <p class="form-field type-fields fields-month_lastday <?php echo $variable ? 'form-row' : '' ?> hidden">
            <label for="_subscription_month_lastday_proration"><?php esc_html_e('Proration setting', $domain); ?></label>
            <select id="_subscription_month_lastday_proration" <?php echo $disabled ?>
                    name="_reepay_subscription_month_lastday<?php echo $variable ? '[' . $loop . ']' : '' ?>[proration]"
                    class="wc_input_subscription_period_interval">
                <option value="full_day" <?php !empty($month_lastday['proration']) ?? selected('full_day', $month_lastday['proration'], true) ?>>
                    Full day proration
                </option>
                <option value="by_minute" <?php !empty($month_lastday['proration']) ?? selected('by_minute', $month_lastday['proration'], true) ?>>
                    By the minute proration
                </option>
            </select>
        </p>
        <p class="form-field type-fields fields-month_lastday <?php echo $variable ? 'form-row' : '' ?> hidden">
            <label for="_subscription_month_lastday_proration_minimum"><?php esc_html_e('Minimum prorated amount', $domain); ?></label>
            <input type="number" min="0" id="_subscription_month_lastday_proration_minimum" <?php echo $disabled ?>
                   value="<?php echo !empty($month_lastday['proration_minimum']) ? $month_lastday['proration_minimum'] : 0 ?>"
                   name="_subscription_month_lastday<?php echo $variable ? '[' . $loop . ']' : '' ?>[proration_minimum]"
                   placeholder="<?php esc_attr_e('kr 0.00', $domain); ?>"/>
        </p>

        <!--Quarterly Primo-->
        <?php $primo = !empty($_reepay_subscription_primo) ? $_reepay_subscription_primo : [] ?>
        <p class="form-field type-fields fields-primo hidden">
            <label for="_subscription_primo"><?php _e('Charge first day of every', $domain); ?></label>
            <strong><?php _e('3rd Month', $domain); ?></strong>
        </p>
        <p class="form-field type-fields fields-primo hidden">
            <label for="_subscription_primo"><?php _e('Fixed Months:', $domain); ?></label>
            <strong><?php _e('Jan, Apr, Jul, Oct', $domain); ?></strong>
        </p>
        <p class="form-field type-fields fields-primo <?php echo $variable ? 'form-row' : '' ?> hidden">
            <label for="_subscription_primo_period"><?php esc_html_e('Partial Period Handling', $domain); ?></label>
            <select id="_subscription_primo_period" <?php echo $disabled ?>
                    name="_reepay_subscription_primo<?php echo $variable ? '[' . $loop . ']' : '' ?>[period]"
                    class="wc_input_subscription_period_interval">
                <option value="bill_prorated" <?php !empty($primo['period']) ?? selected('bill_prorated', $primo['period'], true) ?>>
                    Bill prorated (Default)
                </option>
                <option value="bill_full" <?php !empty($primo['period']) ?? selected('bill_full', $primo['period'], true) ?>>
                    Bill for full period
                </option>
                <option value="bill_zero_amount" <?php !empty($primo['period']) ?? selected('bill_zero_amount', $primo['period'], true) ?>>
                    Bill a zero amount
                </option>
                <option value="no_bill" <?php !empty($primo['period']) ?? selected('no_bill', $primo['period'], true) ?>>
                    Do
                    not consider the partial period a billing period
                </option>
            </select>
        </p>
        <p class="form-field type-fields fields-primo <?php echo $variable ? 'form-row' : '' ?> hidden">
            <label for="_subscription_primo_proration"><?php esc_html_e('Proration setting', $domain); ?></label>
            <select id="_subscription_primo_proration" <?php echo $disabled ?>
                    name="_reepay_subscription_primo<?php echo $variable ? '[' . $loop . ']' : '' ?>[proration]"
                    class="wc_input_subscription_period_interval">
                <option value="full_day" <?php !empty($primo['proration']) ?? selected('full_day', $primo['proration'], true) ?>>
                    Full day proration
                </option>
                <option value="by_minute" <?php !empty($primo['proration']) ?? selected('by_minute', $primo['proration'], true) ?>>
                    By the minute proration
                </option>
            </select>
        </p>
        <p class="form-field type-fields fields-primo <?php echo $variable ? 'form-row' : '' ?> hidden">
            <label for="_subscription_primo_proration_minimum"><?php esc_html_e('Minimum prorated amount', $domain); ?></label>
            <input type="number" min="0" id="_subscription_primo_proration_minimum" <?php echo $disabled ?>
                   name="_reepay_subscription_primo<?php echo $variable ? '[' . $loop . ']' : '' ?>[proration_minimum]"
                   placeholder="<?php esc_attr_e('kr 0.00', $domain); ?>"
                   value="<?php echo !empty($primo['proration_minimum']) ? $primo['proration_minimum'] : 0 ?>"/>
        </p>

        <!--Quarterly Ultimo-->
        <?php $ultimo = !empty($_reepay_subscription_ultimo) ? $_reepay_subscription_ultimo : [] ?>
        <p class="form-field type-fields fields-ultimo hidden">
            <label for="_subscription_ultimo"><?php _e('Charge last day of every', $domain); ?></label>
            <strong><?php _e('3rd Month', $domain); ?></strong>
        </p>
        <p class="form-field type-fields fields-ultimo hidden">
            <label for="_subscription_ultimo"><?php _e('Fixed Months:', $domain); ?></label>
            <strong><?php _e('Jan, Apr, Jul, Oct', $domain); ?></strong>
        </p>
        <p class="form-field type-fields fields-ultimo <?php echo $variable ? 'form-row' : '' ?> hidden">
            <label for="_subscription_ultimo_period"><?php esc_html_e('Partial Period Handling', $domain); ?></label>
            <select id="_subscription_ultimo_period" <?php echo $disabled ?>
                    name="_reepay_subscription_ultimo<?php echo $variable ? '[' . $loop . ']' : '' ?>[period]"
                    class="wc_input_subscription_period_interval">
                <option value="bill_prorated" <?php !empty($ultimo['period']) ?? selected('bill_prorated', $ultimo['period'], true) ?>>
                    Bill prorated (Default)
                </option>
                <option value="bill_full" <?php !empty($ultimo['period']) ?? selected('bill_full', $ultimo['period'], true) ?>>
                    Bill for full period
                </option>
                <option value="bill_zero_amount" <?php !empty($ultimo['period']) ?? selected('bill_zero_amount', $ultimo['period'], true) ?>>
                    Bill a zero amount
                </option>
                <option value="no_bill" <?php !empty($ultimo['period']) ?? selected('no_bill', $ultimo['period'], true) ?>>
                    Do not consider the partial period a billing period
                </option>
            </select>
        </p>
        <p class="form-field type-fields fields-ultimo <?php echo $variable ? 'form-row' : '' ?> hidden">
            <label for="_subscription_ultimo_proration"><?php esc_html_e('Proration setting', $domain); ?></label>
            <select id="_subscription_ultimo_proration" <?php echo $disabled ?>
                    name="_reepay_subscription_ultimo<?php echo $variable ? '[' . $loop . ']' : '' ?>[proration]"
                    class="wc_input_subscription_period_interval">
                <option value="full_day" <?php !empty($ultimo['proration']) ?? selected('full_day', $ultimo['proration'], true) ?>>
                    Full day proration
                </option>
                <option value="by_minute" <?php !empty($ultimo['proration']) ?? selected('by_minute', $ultimo['proration'], true) ?>>
                    By the minute proration
                </option>
            </select>
        </p>
        <p class="form-field type-fields fields-ultimo <?php echo $variable ? 'form-row' : '' ?> hidden">
            <label for="_subscription_ultimo_proration_minimum"><?php esc_html_e('Minimum prorated amount', $domain); ?></label>
            <input type="number" min="0" id="_subscription_ultimo_proration_minimum" <?php echo $disabled ?>
                   name="_reepay_subscription_ultimo<?php echo $variable ? '[' . $loop . ']' : '' ?>[proration_minimum]"
                   placeholder="<?php esc_attr_e('kr 0.00', $domain); ?>"
                   value="<?php echo !empty($ultimo['proration_minimum']) ? $ultimo['proration_minimum'] : 0 ?>"/>
        </p>

        <!--Half-yearly-->
        <?php $half_yearly = !empty($_reepay_subscription_half_yearly) ? $_reepay_subscription_half_yearly : [] ?>
        <p class="form-field type-fields fields-half_yearly hidden">
            <label for="_subscription_half_yearly"><?php _e('Charge every', $domain); ?></label>
            <strong><?php _e('6th Month', $domain); ?></strong>
        </p>
        <p class="form-field type-fields fields-half_yearly hidden">
            <label for="_subscription_half_yearly"><?php _e('On this day of the month:', $domain); ?></label>
            <strong><?php _e('1st', $domain); ?></strong>
        </p>
        <p class="form-field type-fields fields-half_yearly hidden">
            <label for="_subscription_half_yearly"><?php _e('Fixed Months:', $domain); ?></label>
            <strong><?php _e('Jan, Jul', $domain); ?></strong>
        </p>
        <p class="form-field type-fields fields-half_yearly <?php echo $variable ? 'form-row' : '' ?> hidden">
            <label for="_subscription_half_yearly_period"><?php esc_html_e('Partial Period Handling', $domain); ?></label>
            <select id="_subscription_half_yearly_period" <?php echo $disabled ?>
                    name="_reepay_subscription_half_yearly<?php echo $variable ? '[' . $loop . ']' : '' ?>[period]"
                    class="wc_input_subscription_period_interval">
                <option value="bill_prorated" <?php !empty($half_yearly['period']) ?? selected('bill_prorated', $half_yearly['period'], true) ?>>
                    Bill prorated (Default)
                </option>
                <option value="bill_full" <?php !empty($half_yearly['period']) ?? selected('bill_full', $half_yearly['period'], true) ?>>
                    Bill for full period
                </option>
                <option value="bill_zero_amount" <?php !empty($half_yearly['period']) ?? selected('bill_zero_amount', $half_yearly['period'], true) ?>>
                    Bill a zero amount
                </option>
                <option value="no_bill" <?php !empty($half_yearly['period']) ?? selected('no_bill', $half_yearly['period'], true) ?>>
                    Do not consider the partial period a billing period
                </option>
            </select>
        </p>
        <p class="form-field type-fields fields-half_yearly <?php echo $variable ? 'form-row' : '' ?> hidden">
            <label for="_subscription_half_yearly_proration"><?php esc_html_e('Proration setting', $domain); ?></label>
            <select id="_subscription_half_yearly_proration" <?php echo $disabled ?>
                    name="_reepay_subscription_half_yearly<?php echo $variable ? '[' . $loop . ']' : '' ?>[proration]"
                    class="wc_input_subscription_period_interval">
                <option value="full_day" <?php !empty($half_yearly['proration']) ?? selected('full_day', $half_yearly['proration'], true) ?>>
                    Full day proration
                </option>
                <option value="by_minute" <?php !empty($half_yearly['proration']) ?? selected('by_minute', $half_yearly['proration'], true) ?>>
                    By the minute proration
                </option>
            </select>
        </p>
        <p class="form-field type-fields fields-half_yearly <?php echo $variable ? 'form-row' : '' ?> hidden">
            <label for="_subscription_half_yearly_proration_minimum"><?php esc_html_e('Minimum prorated amount', $domain); ?></label>
            <input type="number" min="0" id="_subscription_half_yearly_proration_minimum" <?php echo $disabled ?>
                   name="_reepay_subscription_half_yearly<?php echo $variable ? '[' . $loop . ']' : '' ?>[proration_minimum]"
                   placeholder="<?php esc_attr_e('kr 0.00', $domain); ?>"
                   value="<?php echo !empty($half_yearly['proration_minimum']) ? $half_yearly['proration_minimum'] : 0 ?>"/>
        </p>


        <!--Yearly-->
        <?php $month_startdate_12 = !empty($_reepay_subscription_month_startdate_12) ? $_reepay_subscription_month_startdate_12 : [] ?>
        <p class="form-field type-fields fields-month_startdate_12 <?php echo $variable ? 'form-row' : '' ?> hidden">
            <label for="_subscription_half_yearly"><?php _e('Charge every', $domain); ?></label>
            <strong><?php _e('12th Month', $domain); ?></strong>
        </p>
        <p class="form-field type-fields fields-month_startdate_12 <?php echo $variable ? 'form-row' : '' ?> hidden">
            <label for="_subscription_month_startdate_12"><?php _e('On this day of the month:', $domain); ?></label>
            <strong><?php _e('1st', $domain); ?></strong>
        </p>
        <p class="form-field type-fields fields-month_startdate_12 <?php echo $variable ? 'form-row' : '' ?> hidden">
            <label for="_subscription_month_startdate_12"><?php _e('Fixed Months:', $domain); ?></label>
            <strong><?php _e('Jan', $domain); ?></strong>
        </p>
        <p class="form-field type-fields fields-month_startdate_12 <?php echo $variable ? 'form-row' : '' ?> hidden">
            <label for="_subscription_month_startdate_12_period"><?php esc_html_e('Partial Period Handling', $domain); ?></label>
            <select id="_subscription_month_startdate_12_period" <?php echo $disabled ?>
                    name="_reepay_subscription_month_startdate_12<?php echo $variable ? '[' . $loop . ']' : '' ?>[period]"
                    class="wc_input_subscription_period_interval">
                <option value="bill_prorated" <?php !empty($month_startdate_12['period']) ?? selected('bill_prorated', $month_startdate_12['period'], true) ?>>
                    Bill prorated (Default)
                </option>
                <option value="bill_full" <?php !empty($month_startdate_12['period']) ?? selected('bill_full', $month_startdate_12['period'], true) ?>>
                    Bill for full period
                </option>
                <option value="bill_zero_amount" <?php !empty($month_startdate_12['period']) ?? selected('bill_zero_amount', $month_startdate_12['period'], true) ?>>
                    Bill a zero amount
                </option>
                <option value="no_bill" <?php !empty($month_startdate_12['period']) ?? selected('no_bill', $month_startdate_12['period'], true) ?>>
                    Do not consider the partial period a billing period
                </option>
            </select>
        </p>
        <p class="form-field type-fields fields-month_startdate_12 <?php echo $variable ? 'form-row' : '' ?> hidden">
            <label for="_subscription_month_startdate_12_proration"><?php esc_html_e('Proration setting', $domain); ?></label>
            <select id="_subscription_month_startdate_12_proration" <?php echo $disabled ?>
                    name="_reepay_subscription_month_startdate_12<?php echo $variable ? '[' . $loop . ']' : '' ?>[proration]"
                    class="wc_input_subscription_period_interval">
                <option value="full_day" <?php !empty($month_startdate_12['proration']) ?? selected('full_day', $month_startdate_12['proration'], true) ?>>
                    Full day proration
                </option>
                <option value="by_minute" <?php !empty($month_startdate_12['proration']) ?? selected('by_minute', $month_startdate_12['proration'], true) ?>>
                    By the minute proration
                </option>
            </select>
        </p>
        <p class="form-field type-fields fields-month_startdate_12 <?php echo $variable ? 'form-row' : '' ?> hidden">
            <label for="_subscription_month_startdate_12_proration_minimum"><?php esc_html_e('Minimum prorated amount', $domain); ?></label>
            <input type="number" min="0" id="_subscription_month_startdate_12_proration_minimum" <?php echo $disabled ?>
                   name="_reepay_subscription_month_startdate_12<?php echo $variable ? '[' . $loop . ']' : '' ?>[proration_minimum]"
                   placeholder="<?php esc_attr_e('kr 0.00', $domain); ?>"
                   value="<?php echo !empty($month_startdate_12['proration_minimum']) ? $month_startdate_12['proration_minimum'] : 0 ?>"/>
        </p>


        <!--Fixed day of week-->
        <?php $weekly_fixedday = !empty($_reepay_subscription_weekly_fixedday) ? $_reepay_subscription_weekly_fixedday : [] ?>
        <p class="form-field type-fields fields-weekly_fixedday <?php echo $variable ? 'form-row' : '' ?> hidden">
            <label for="_subscription_weekly_fixedday"><?php esc_html_e('Charge every', $domain); ?></label>
            <input type="number" min="0" id="_subscription_weekly_fixedday" <?php echo $disabled ?>
                   name="_reepay_subscription_weekly_fixedday<?php echo $variable ? '[' . $loop . ']' : '' ?>[week]">
            &nbsp<?php esc_html_e('Week', $domain); ?>
        </p>
        <p class="form-field type-fields fields-weekly_fixedday <?php echo $variable ? 'form-row' : '' ?> hidden">
            <label for="_subscription_weekly_fixedday_day"><?php esc_html_e('On this day of the week', $domain); ?></label>
            <select id="_subscription_weekly_fixedday_day" <?php echo $disabled ?>
                    name="_reepay_subscription_weekly_fixedday<?php echo $variable ? '[' . $loop . ']' : '' ?>[day]"
                    class="wc_input_subscription_period_interval">
                <option value="1" <?php !empty($weekly_fixedday['day']) ?? selected('1', $weekly_fixedday['day'], true) ?>>
                    Monday
                </option>
                <option value="2" <?php !empty($weekly_fixedday['day']) ?? selected('2', $weekly_fixedday['day'], true) ?>>
                    Tuesday
                </option>
                <option value="3" <?php !empty($weekly_fixedday['day']) ?? selected('3', $weekly_fixedday['day'], true) ?>>
                    Wednesday
                </option>
                <option value="4" <?php !empty($weekly_fixedday['day']) ?? selected('4', $weekly_fixedday['day'], true) ?>>
                    Thursday
                </option>
                <option value="5" <?php !empty($weekly_fixedday['day']) ?? selected('5', $weekly_fixedday['day'], true) ?>>
                    Friday
                </option>
                <option value="6" <?php !empty($weekly_fixedday['day']) ?? selected('6', $weekly_fixedday['day'], true) ?>>
                    Saturday
                </option>
                <option value="7" <?php !empty($weekly_fixedday['day']) ?? selected('7', $weekly_fixedday['day'], true) ?>>
                    Sunday
                </option>
            </select>
        </p>
        <p class="form-field type-fields fields-weekly_fixedday <?php echo $variable ? 'form-row' : '' ?> hidden">
            <label for="_subscription_weekly_fixedday_period"><?php esc_html_e('Partial Period Handling', $domain); ?></label>
            <select id="_subscription_weekly_fixedday_period" <?php echo $disabled ?>
                    name="_reepay_subscription_weekly_fixedday<?php echo $variable ? '[' . $loop . ']' : '' ?>[period]"
                    class="wc_input_subscription_period_interval">
                <option value="bill_prorated" <?php !empty($weekly_fixedday['period']) ?? selected('bill_prorated', $weekly_fixedday['period'], true) ?>>
                    Bill prorated (Default)
                </option>
                <option value="bill_full" <?php !empty($weekly_fixedday['period']) ?? selected('bill_prorated', $weekly_fixedday['period'], true) ?>>
                    Bill for full period
                </option>
                <option value="bill_zero_amount" <?php !empty($weekly_fixedday['period']) ?? selected('bill_prorated', $weekly_fixedday['period'], true) ?>>
                    Bill a zero amount
                </option>
                <option value="no_bill" <?php !empty($weekly_fixedday['period']) ?? selected('bill_prorated', $weekly_fixedday['period'], true) ?>>
                    Do not consider the partial period a billing period
                </option>
            </select>
        </p>
        <p class="form-field type-fields fields-weekly_fixedday <?php echo $variable ? 'form-row' : '' ?> hidden">
            <label for="_subscription_weekly_fixedday_proration"><?php esc_html_e('Proration setting', $domain); ?></label>
            <select id="_subscription_weekly_fixedday_proration" <?php echo $disabled ?>
                    name="_reepay_subscription_weekly_fixedday<?php echo $variable ? '[' . $loop . ']' : '' ?>[proration]"
                    class="wc_input_subscription_period_interval">
                <option value="full_day" <?php !empty($weekly_fixedday['proration']) ?? selected('full_day', $weekly_fixedday['proration'], true) ?>>
                    Full day proration
                </option>
                <option value="by_minute" <?php !empty($weekly_fixedday['proration']) ?? selected('by_minute', $weekly_fixedday['proration'], true) ?>>
                    By the minute proration
                </option>
            </select>
        </p>
        <p class="form-field type-fields fields-weekly_fixedday <?php echo $variable ? 'form-row' : '' ?> hidden">
            <label for="_subscription_weekly_fixedday_proration_minimum"><?php esc_html_e('Minimum prorated amount', $domain); ?></label>
            <input type="number" min="0" id="_subscription_weekly_fixedday_proration_minimum" <?php echo $disabled ?>
                   name="_reepay_subscription_weekly_fixedday<?php echo $variable ? '[' . $loop . ']' : '' ?>[proration_minimum]"
                   placeholder="<?php esc_attr_e('kr 0.00', $domain); ?>"
                   value="<?php echo !empty($weekly_fixedday['proration_minimum']) ? $weekly_fixedday['proration_minimum'] : 0 ?>"/>
        </p>

        <!--Advanced-->
        <p class="form-field advanced-fields <?php echo $variable ? 'form-row' : '' ?>">
            <label for="_reepay_subscription_renewal_reminder">
                <?php esc_html_e('Renewal Reminder', $domain); ?>
            </label>
            <input type="number" min="0" id="_reepay_subscription_renewal_reminder" <?php echo $disabled ?>
                   name="_reepay_subscription_renewal_reminder<?php echo $variable ? '[' . $loop . ']' : '' ?>"
                   class="wc_input_price wc_input_subscription_price"
                   placeholder="<?php esc_html_e('Renewal Reminder Schedule', $domain); ?>"
                   value="<?php echo !empty($_reepay_subscription_renewal_reminder) ? esc_attr($_reepay_subscription_renewal_reminder) : '' ?>"/>
        </p>
        <p class="form-field advanced-fields <?php echo $variable ? 'form-row' : '' ?>">
            <label for="_reepay_subscription_default_quantity">
                <?php esc_html_e('Default Quantity', $domain); ?>
            </label>
            <input type="number" min="0" id="_reepay_subscription_default_quantity" <?php echo $disabled ?>
                   name="_reepay_subscription_default_quantity<?php echo $variable ? '[' . $loop . ']' : '' ?>"
                   class="wc_input_price wc_input_subscription_price"
                   placeholder="<?php esc_html_e('Default Quantity', $domain); ?>"
                   value="<?php echo !empty($_reepay_subscription_default_quantity) ? esc_attr($_reepay_subscription_default_quantity) : '1' ?>"/>
        </p>
    </div>

    <div class="options_group show_if_reepay_subscription">
        <p class="form-field <?php echo $variable ? 'form-row' : '' ?>">
            <label for="_subscription_contract_periods"><?php esc_html_e('Minimum Contract Period', $domain); ?></label>
            <input type="number" min="0" id="_subscription_contract_periods" <?php echo $disabled ?>
                   name="_reepay_subscription_contract_periods<?php echo $variable ? '[' . $loop . ']' : '' ?>"
                   placeholder="<?php esc_html_e('Periods', $domain); ?>"
                   value="<?php echo !empty($_reepay_subscription_contract_periods) ? $_reepay_subscription_contract_periods : 0 ?>"/>
        </p>
        <p class="form-field fields-contract_periods hidden">
            <label for="_reepay_subscription_contract_periods_full"></label>
            <?php esc_html_e('When the subscription is created', $domain); ?> &nbsp<input <?php echo $disabled ?>
                    type="radio"
                    id="_reepay_subscription_contract_periods_full"
                    name="_reepay_subscription_contract_periods_full<?php echo $variable ? '[' . $loop . ']' : '' ?>"
                    value="false" <?php !empty($_reepay_subscription_contract_periods_full) ?? checked('false', $_reepay_subscription_contract_periods_full, true); ?>/>
            &nbsp&nbsp <?php esc_html_e('When the first period starts', $domain); ?> &nbsp<input <?php echo $disabled ?>
                    type="radio" id="_reepay_subscription_contract_periods_full"
                    name="_reepay_subscription_contract_periods_full<?php echo $variable ? '[' . $loop . ']' : '' ?>"
                    value="true" <?php checked('true', $_reepay_subscription_contract_periods_full, true); ?>/>
        </p>
    </div>

    <div class="options_group show_if_reepay_subscription">
        <p class="form-field">
            <label for="_subscription_notice_period"><?php esc_html_e('Notice period', $domain); ?></label>
            <input type="number" min="0" id="_subscription_notice_period" <?php echo $disabled ?>
                   name="_reepay_subscription_notice_period<?php echo $variable ? '[' . $loop . ']' : '' ?>"
                   placeholder="<?php esc_html_e('Periods', $domain); ?>"
                   value="<?php echo !empty($_reepay_subscription_notice_period) ? $_reepay_subscription_notice_period : 0 ?>"/>
        </p>
        <p class="form-field fields-notice_period hidden">
            <label for="_subscription_notice_period_start"></label>
            <?php esc_html_e('When the current cancelled period ends', $domain); ?> &nbsp<input type="radio"
                                                                                                id="_subscription_notice_period_start"
                                                                                                name="_reepay_subscription_notice_period_start<?php echo $variable ? '[' . $loop . ']' : '' ?>"
                                                                                                value="true" <?php !empty($_reepay_subscription_notice_period_start) ?? checked('true', $_reepay_subscription_notice_period_start, true); ?>/>
            &nbsp&nbsp <?php esc_html_e('Immediately after cancellation', $domain); ?> &nbsp<input type="radio"
                                                                                                   id="_subscription_notice_period_start"
                                                                                                   name="_reepay_subscription_notice_period_start<?php echo $variable ? '[' . $loop . ']' : '' ?>"
                                                                                                   value="false" <?php !empty($_reepay_subscription_notice_period_start) ?? checked('false', $_reepay_subscription_notice_period_start, true); ?>/>
        </p>
    </div>

    <div class="options_group show_if_reepay_subscription billing_cycles_block">
        <p class="form-field <?php echo $variable ? 'form-row' : '' ?>">
            <label for="_subscription_billing_cycles"><?php esc_html_e('Billing Cycles', $domain); ?></label>
            <?php esc_html_e('Auto Renew until cancelled', $domain); ?> &nbsp<input type="radio"
                                                                                    id="_subscription_billing_cycles" <?php echo $disabled ?>
                                                                                    name="_reepay_subscription_billing_cycles<?php echo $variable ? '[' . $loop . ']' : '' ?>"
                                                                                    value="false" <?php !empty($_reepay_subscription_billing_cycles) ?? checked('false', $_reepay_subscription_billing_cycles, true); ?>/>
            &nbsp&nbsp <?php esc_html_e('Fixed Number of billing cycles', $domain); ?> &nbsp<input type="radio"
                                                                                                   id="_subscription_billing_cycles" <?php echo $disabled ?>
                                                                                                   name="_reepay_subscription_billing_cycles<?php echo $variable ? '[' . $loop . ']' : '' ?>"
                                                                                                   value="true" <?php !empty($_reepay_subscription_billing_cycles) ?? checked('true', $_reepay_subscription_billing_cycles, true); ?>/>
        </p>
        <p class="form-field fields-billing_cycles <?php echo $variable ? 'form-row' : '' ?> hidden">
            <label for="_subscription_billing_cycles_period"><?php esc_html_e('Number of billing cycles', $domain); ?></label>
            <input type="number" min="0" id="_subscription_billing_cycles_period"
                   name="_reepay_subscription_billing_cycles_period<?php echo $variable ? '[' . $loop . ']' : '' ?>" <?php echo $disabled ?>
                   placeholder="*"
                   value="<?php echo !empty($_reepay_subscription_billing_cycles_period) ? $_reepay_subscription_billing_cycles_period : 0 ?>"/>
        </p>
    </div>

<?php $trial = !empty($_reepay_subscription_trial) ? $_reepay_subscription_trial : [] ?>
    <div class="options_group reepay_subscription_trial show_if_reepay_subscription">
        <p class="form-field <?php echo $variable ? 'form-row' : '' ?>">
            <label for="_subscription_trial"><?php esc_html_e('Trial', $domain); ?></label>
            <select id="_subscription_trial" <?php echo $disabled ?>
                    name="_reepay_subscription_trial<?php echo $variable ? '[' . $loop . ']' : '' ?>[type]"
                    class="wc_input_subscription_period_interval">
                <?php foreach (WC_Reepay_Subscription_Plan_Simple::$trial as $value => $label) { ?>
                    <option value="<?php esc_attr_e($value); ?>" <?php !empty($trial['type']) ?? selected($value, $trial['type'], true) ?>><?php esc_html_e($label); ?></option>
                <?php } ?>
            </select>
        </p>
        <p class="form-field trial-fields fields-customize <?php echo $variable ? 'form-row' : '' ?> hidden">
            <label for="_subscription_trial_length"><?php esc_html_e('Trial Length', $domain); ?></label>
            <input type="number" min="0" id="_subscription_trial_length" <?php echo $disabled ?>
                   name="_reepay_subscription_trial<?php echo $variable ? '[' . $loop . ']' : '' ?>[length]"
                   placeholder="<?php esc_html_e('Length', $domain); ?>"
                   value="<?php echo !empty($trial['length']) ? $trial['length'] : 0 ?>"/>
            <select id="_subscription_trial_unit" <?php echo $disabled ?>
                    name="_reepay_subscription_trial<?php echo $variable ? '[' . $loop . ']' : '' ?>[unit]"
                    class="wc_input_subscription_period_interval">
                <option value="days" <?php !empty($trial['unit']) ?? selected('days', $trial['unit'], true) ?>>Days
                </option>
                <option value="months" <?php !empty($trial['unit']) ?? selected('months', $trial['unit'], true) ?>>
                    Months
                </option>
            </select>
        </p>
        <p class="form-field <?php echo $variable ? 'form-row' : '' ?>  trial-fields fields-7days fields-14days fields-1month fields-customize hidden">
            <label for="_subscription_billing_trial_reminder"><?php esc_html_e('Optional Trial Reminder Schedule', $domain); ?></label>
            <input type="number" min="0" id="_subscription_trial_reminder" <?php echo $disabled ?>
                   name="_reepay_subscription_trial<?php echo $variable ? '[' . $loop . ']' : '' ?>[reminder]"
                   placeholder="<?php esc_html_e('Days', $domain); ?>"
                   value="<?php echo !empty($trial['reminder']) ? $trial['reminder'] : 0 ?>"/>
        </p>
    </div>

<?php $fee = !empty($_reepay_subscription_fee) ? $_reepay_subscription_fee : [] ?>
    <div class="options_group reepay_subscription_fee show_if_reepay_subscription">
        <p class="form-field">
            <label for="_subscription_fee"><?php esc_html_e('Include setup fee', $domain); ?></label>
            <input type="checkbox" id="_subscription_fee" <?php echo $disabled ?>
                   name="_reepay_subscription_fee<?php echo $variable ? '[' . $loop . ']' : '' ?>[enabled]"
                   value="yes" <?php echo !empty($fee['enabled']) && $fee['enabled'] == 'yes' ? 'checked' : '' ?> />
        </p>

        <p class="form-field fee-fields <?php echo $variable ? 'dimensions_field form-row' : '' ?> hidden">
            <label for="_subscription_fee_amount"><?php esc_html_e('Setup Fee (kr)', $domain); ?></label>
            <span class="wrap">
            <input type="number" min="0"
                   id="_subscription_fee_amount" <?php echo $disabled ?> name="_reepay_subscription_fee<?php echo $variable ? '[' . $loop . ']' : '' ?>[amount]"
                   class="wc_input_price wc_input_subscription_price"
                   placeholder="<?php esc_attr_e('Amount', $domain); ?>" step="any" min="0"
                   value="<?php echo !empty($fee['amount']) ? $fee['amount'] : 0 ?>"/>
            <input type="text"
                   id="_subscription_fee_text" <?php echo $disabled ?> name="_reepay_subscription_fee<?php echo $variable ? '[' . $loop . ']' : '' ?>[text]"
                   placeholder="<?php esc_attr_e('Text', $domain); ?>"
                   value="<?php echo !empty($fee['text']) ? $fee['text'] : '' ?>"/>
            <select id="_subscription_fee_handling" <?php echo $disabled ?> name="_reepay_subscription_fee<?php echo $variable ? '[' . $loop . ']' : '' ?>[handling]"
                    class="wc_input_subscription_period_interval">
                <option value="first" <?php !empty($fee['handling']) ?? selected('first', $fee['handling'], true) ?>><?php esc_html_e('Include setup fee as order line on the first scheduled invoice', $domain); ?></option>
                <option value="separate" <?php !empty($fee['handling']) ?? selected('separate', $fee['handling'], true) ?>><?php esc_html_e('Create a separate invoice for the setup fee', $domain); ?></option>
                <option value="separate_conditional" <?php !empty($fee['handling']) ?? selected('separate_conditional', $fee['handling'], true) ?>><?php esc_html_e('Create a separate invoice for the setup fee, if the first invoice is not created in conjunction with the creation', $domain); ?></option>
            </select>
        </span>
        </p>
    </div>
    <div class="options_group show_if_reepay_subscription">
        <?php
        if (!empty($product_object) && function_exists('woocommerce_wp_select')) {
            woocommerce_wp_select(
                [
                    'id' => '_tax_status',
                    'value' => $product_object->get_tax_status('edit'),
                    'label' => __('Tax status', $domain),
                    'options' => [
                        'taxable' => __('Taxable', $domain),
                        'shipping' => __('Shipping only', $domain),
                        'none' => _x('None', 'Tax status', $domain),
                    ],
                    'desc_tip' => 'true',
                    'description' => __('Define whether or not the entire product is taxable, or just the cost of shipping it.', $domain),
                    'custom_attributes' => empty($disabled) ? [] : ['disabled' => 'disabled']
                ]
            );

            woocommerce_wp_select(
                [
                    'id' => '_tax_class',
                    'value' => $product_object->get_tax_class('edit'),
                    'label' => __('Tax class', $domain),
                    'options' => wc_get_product_tax_class_options(),
                    'desc_tip' => 'true',
                    'description' => __('Choose a tax class for this product. Tax classes are used to apply different tax rates specific to certain types of product.', $domain),
                    'custom_attributes' => empty($disabled) ? [] : ['disabled' => 'disabled']
                ]
            );
        }
        do_action('woocommerce_product_options_tax');
        ?>
    </div>

<?php if (isset($is_exist) && $is_exist): ?>
    <div class="options_group show_if_reepay_subscription">
        <p class="form-field <?php echo $variable ? 'form-row' : '' ?>">
            <label for="_reepay_subscription_supersedes"><?php esc_html_e('Supersede mode', $domain); ?></label>
            <?php esc_html_e("Don't schedule subscription update", $domain); ?> &nbsp
            <input type="radio"
                   id="_reepay_subscription_supersedes" <?php echo $disabled ?>
                   name="_reepay_subscription_supersedes<?php echo $variable ? '[' . $loop . ']' : '' ?>"
                   value="no_sub_update" <?php !empty($_reepay_subscription_supersedes) ?? checked('no_sub_update', $_reepay_subscription_supersedes, true); ?>/>
            &nbsp&nbsp <?php esc_html_e('Schedule subscription update', $domain); ?> &nbsp
            <input type="radio"
                   id="_reepay_subscription_supersedes" <?php echo $disabled ?>
                   name="_reepay_subscription_supersedes<?php echo $variable ? '[' . $loop . ']' : '' ?>"
                   value="scheduled_sub_update" <?php !empty($_reepay_subscription_supersedes) ?? checked('scheduled_sub_update', $_reepay_subscription_supersedes, true); ?>/>
        </p>

    </div>
<?php endif; ?>