<?php
/**
 * @var Int $loop
 */

$disabled = '';
$variable = ! empty( $variable );
?>
<div class="options_group reepay_subscription_pricing show_if_reepay_subscription">
    <p class="form-field fields-name <?php echo $variable ? 'form-row' : '' ?>">
        <label for="_reepay_subscription_name"><?php echo __( 'Name', 'reepay-subscriptions-for-woocommerce' ); ?></label>
        <input type="text" id="_reepay_subscription_name" <?php echo esc_attr( $disabled ) ?>
               name="_reepay_subscription_name<?php echo $variable ? '[' . esc_attr( $loop ) . ']' : '' ?>"
               value="<?php echo ! empty( $_reepay_subscription_name ) ? esc_attr( $_reepay_subscription_name ) : '' ?>">
    </p>
    <p class="form-field pricing-fields <?php echo $variable ? 'dimensions_field form-row' : '' ?> ">
        <label for="_subscription_price">
			<?php echo __( 'Subscription pricing (kr)', 'reepay-subscriptions-for-woocommerce' ); ?>
        </label>
        <span class="wrap">
                <input type="number"
                       id="_subscription_price" <?php echo esc_attr( $disabled ) ?> name="_reepay_subscription_price<?php echo $variable ? '[' . esc_attr( $loop ) . ']' : '' ?>"
                       <?php if ( $variable ) : ?>
                           style="float: unset"
                       <?php endif; ?>
                       class="wc_input_price wc_input_subscription_price"
                       placeholder="<?php echo esc_attr( 'e.g. 5.90', 'reepay-subscriptions-for-woocommerce' ); ?>"
                       step="any" min="0"
                       value="<?php echo ! empty( $_reepay_subscription_price ) ? esc_attr( wc_format_localized_price( $_reepay_subscription_price ) ) : 0 ?>"/>

            </span>
    </p>

    <!--Type-->
    <p class="form-field pricing-fields <?php echo $variable ? 'form-row' : '' ?>">
        <label for="_subscription_schedule_type"><?php echo __( 'Schedule Type', 'reepay-subscriptions-for-woocommerce' ); ?></label>
        <select id="_subscription_schedule_type" <?php echo esc_attr( $disabled ) ?>
                name="_reepay_subscription_schedule_type<?php echo $variable ? '[' . esc_attr( $loop ) . ']' : '' ?>"
                class="wc_input_subscription_period_interval">
			<?php foreach ( WC_Reepay_Subscription_Plan_Simple::$schedule_types as $value => $label ) { ?>
                <option value="<?php echo esc_attr( $value ); ?>" <?php echo ! empty( $_reepay_subscription_schedule_type ) ? selected( $value, $_reepay_subscription_schedule_type, false ) : '' ?>><?php esc_html_e( $label, 'reepay-subscriptions-for-woocommerce' ); ?></option>
			<?php } ?>
        </select>
    </p>

    <!--Daily-->
    <p class="form-field type-fields fields-daily <?php echo $variable ? 'form-row' : '' ?> hidden">
        <label for="_reepay_subscription_daily"><?php echo __( 'Charge every', 'reepay-subscriptions-for-woocommerce' ); ?></label>
        <input type="number" min="0" id="_reepay_subscription_daily" <?php echo esc_attr( $disabled ) ?>
               name="_reepay_subscription_daily<?php echo $variable ? '[' . esc_attr( $loop ) . ']' : '' ?>"
               value="<?php echo ! empty( $_reepay_subscription_daily ) ? esc_attr( $_reepay_subscription_daily ) : 1 ?>">
		<?php echo __( 'Day', 'reepay-subscriptions-for-woocommerce' ); ?>
    </p>

    <!--Monthly-->
    <p class="form-field type-fields fields-month_startdate <?php echo $variable ? 'form-row' : '' ?> hidden">
        <label for="_subscription_month_startdate"><?php echo __( 'Charge every', 'reepay-subscriptions-for-woocommerce' ); ?></label>
        <input type="number" min="0" id="_subscription_month_startdate" <?php echo esc_attr( $disabled ) ?>
               name="_reepay_subscription_month_startdate<?php echo $variable ? '[' . esc_attr( $loop ) . ']' : '' ?>"
               value="<?php echo ! empty( $_reepay_subscription_month_startdate ) ? esc_attr( $_reepay_subscription_month_startdate ) : 1 ?>">
		<?php echo __( 'Month', 'reepay-subscriptions-for-woocommerce' ); ?>
    </p>

    <!--Fixed day of month-->
	<?php $month_fixedday = ! empty( $_reepay_subscription_month_fixedday ) ? $_reepay_subscription_month_fixedday : [] ?>
    <p class="form-field type-fields fields-month_fixedday <?php echo $variable ? 'form-row' : '' ?> hidden">
        <label for="_subscription_month_fixedday"><?php echo __( 'Charge every', 'reepay-subscriptions-for-woocommerce' ); ?></label>
        <input type="number" min="0" id="_subscription_month_fixedday" <?php echo esc_attr( $disabled ) ?>
               name="_reepay_subscription_month_fixedday<?php echo $variable ? '[' . esc_attr( $loop ) . ']' : '' ?>[month]"
               value="<?php echo ! empty( $month_fixedday['month'] ) ? esc_attr( $month_fixedday['month'] ) : 1 ?>">
		<?php echo __( 'Month', 'reepay-subscriptions-for-woocommerce' ); ?>
    </p>
    <p class="form-field type-fields fields-month_fixedday <?php echo $variable ? 'form-row' : '' ?> hidden">
        <label for="_subscription_month_fixedday_day"><?php echo __( 'On this day of the month', 'reepay-subscriptions-for-woocommerce' ); ?></label>
        <select id="_subscription_month_fixedday_day" <?php echo esc_attr( $disabled ) ?>
                name="_reepay_subscription_month_fixedday<?php echo $variable ? '[' . esc_attr( $loop ) . ']' : '' ?>[day]"
                class="wc_input_subscription_period_interval">
			<?php for ( $i = 1; $i <= 28; $i ++ ) : ?>
                <option value="<?php echo $i ?>" <?php echo ! empty( $month_fixedday['day'] ) ? selected( $i, $month_fixedday['day'], false ) : '' ?>><?php echo esc_attr( $i ) ?></option>
			<?php endfor; ?>
        </select>
    </p>
    <p class="form-field type-fields fields-month_fixedday <?php echo $variable ? 'form-row' : '' ?> hidden">
        <label for="_subscription_month_fixedday_period"><?php echo __( 'Partial Period Handling', 'reepay-subscriptions-for-woocommerce' ); ?></label>
        <select id="_subscription_month_fixedday_period" <?php echo esc_attr( $disabled ) ?>
                name="_reepay_subscription_month_fixedday<?php echo $variable ? '[' . esc_attr( $loop ) . ']' : '' ?>[period]"
                class="wc_input_subscription_period_interval">
            <option value="bill_prorated" <?php echo ! empty( $month_fixedday['period'] ) ? selected( 'bill_prorated', $month_fixedday['period'], false ) : '' ?>>
                Bill prorated (Default)
            </option>
            <option value="bill_full" <?php echo ! empty( $month_fixedday['period'] ) ? selected( 'bill_full', $month_fixedday['period'], false ) : '' ?>>
                Bill for full period
            </option>
            <option value="bill_zero_amount" <?php echo ! empty( $month_fixedday['period'] ) ? selected( 'bill_zero_amount', $month_fixedday['period'], false ) : '' ?>>
                Bill a zero amount
            </option>
            <option value="no_bill" <?php echo ! empty( $month_fixedday['period'] ) ? selected( 'no_bill', $month_fixedday['period'], false ) : '' ?>>
                Do not consider the partial period a billing period
            </option>
        </select>
    </p>
    <p class="form-field type-fields fields-month_fixedday <?php echo $variable ? 'form-row' : '' ?> hidden">
        <label for="_subscription_month_fixedday_proration"><?php echo __( 'Proration setting', 'reepay-subscriptions-for-woocommerce' ); ?></label>
        <select id="_subscription_month_fixedday_proration" <?php echo esc_attr( $disabled ) ?>
                name="_subscription_month_fixedday<?php echo $variable ? '[' . esc_attr( $loop ) . ']' : '' ?>[proration]"
                class="wc_input_subscription_period_interval">
            <option value="full_day" <?php echo ! empty( $month_fixedday['proration'] ) ? selected( 'full_day', $month_fixedday['proration'], false ) : '' ?>>
                Full day proration
            </option>
            <option value="by_minute" <?php echo ! empty( $month_fixedday['proration'] ) ? selected( 'full_day', $month_fixedday['proration'], false ) : '' ?>>
                By the minute proration
            </option>
        </select>
    </p>
    <p class="form-field type-fields fields-month_fixedday <?php echo $variable ? 'form-row' : '' ?> hidden">
        <label for="_subscription_month_fixedday_proration_minimum"><?php echo __( 'Minimum prorated amount', 'reepay-subscriptions-for-woocommerce' ); ?></label>
        <input type="number" min="0"
               id="_subscription_month_fixedday_proration_minimum" <?php echo esc_attr( $disabled ) ?>
               value="<?php echo ! empty( $month_fixedday['proration_minimum'] ) ? esc_attr( $month_fixedday['proration_minimum'] ) : 0 ?>"
               name="_reepay_subscription_month_fixedday<?php echo $variable ? '[' . esc_attr( $loop ) . ']' : '' ?>[proration_minimum]"
               placeholder="<?php echo esc_attr( 'kr 0.00', 'reepay-subscriptions-for-woocommerce' ); ?>"/>
    </p>

    <!--Last day of month-->
	<?php $month_lastday = ! empty( $_reepay_subscription_month_lastday ) ? $_reepay_subscription_month_lastday : [] ?>
    <p class="form-field type-fields fields-month_lastday <?php echo $variable ? 'form-row' : '' ?> hidden">
        <label for="_subscription_month_lastday<?php echo $variable ? '[' . esc_attr( $loop ) . ']' : '' ?>[month]"><?php echo __( 'Charge every', 'reepay-subscriptions-for-woocommerce' ); ?></label>
        <input type="number" min="0"
               id="_subscription_month_lastday<?php echo $variable ? '[' . esc_attr( $loop ) . ']' : '' ?>[month]" <?php echo esc_attr( $disabled ) ?>
               value="<?php echo ! empty( $month_lastday['month'] ) ? $month_lastday['month'] : 0 ?>"
               name="_reepay_subscription_month_lastday<?php echo $variable ? '[' . esc_attr( $loop ) . ']' : '' ?>[month]">
		<?php echo __( 'Month', 'reepay-subscriptions-for-woocommerce' ); ?>
    </p>
    <p class="form-field type-fields fields-month_lastday <?php echo $variable ? 'form-row' : '' ?> hidden">
        <label for="_subscription_month_lastday_period"><?php echo __( 'Partial Period Handling', 'reepay-subscriptions-for-woocommerce' ); ?></label>
        <select id="_subscription_month_lastday_period" <?php echo esc_attr( $disabled ) ?>
                name="_reepay_subscription_month_lastday<?php echo $variable ? '[' . esc_attr( $loop ) . ']' : '' ?>[period]"
                class="wc_input_subscription_period_interval">
            <option value="bill_prorated" <?php echo ! empty( $month_lastday['period'] ) ? selected( 'bill_prorated', $month_lastday['period'], false ) : '' ?>>
                Bill prorated (Default)
            </option>
            <option value="bill_full" <?php echo ! empty( $month_lastday['period'] ) ? selected( 'bill_full', $month_lastday['period'], false ) : '' ?>>
                Bill for full period
            </option>
            <option value="bill_zero_amount" <?php echo ! empty( $month_lastday['period'] ) ? selected( 'bill_zero_amount', $month_lastday['period'], false ) : '' ?>>
                Bill a zero amount
            </option>
            <option value="no_bill" <?php echo ! empty( $month_lastday['period'] ) ? selected( 'no_bill', $month_lastday['period'], false ) : '' ?>>
                Do not consider the partial period a billing period
            </option>
        </select>
    </p>
    <p class="form-field type-fields fields-month_lastday <?php echo $variable ? 'form-row' : '' ?> hidden">
        <label for="_subscription_month_lastday_proration"><?php echo __( 'Proration setting', 'reepay-subscriptions-for-woocommerce' ); ?></label>
        <select id="_subscription_month_lastday_proration" <?php echo esc_attr( $disabled ) ?>
                name="_reepay_subscription_month_lastday<?php echo $variable ? '[' . esc_attr( $loop ) . ']' : '' ?>[proration]"
                class="wc_input_subscription_period_interval">
            <option value="full_day" <?php echo ! empty( $month_lastday['proration'] ) ? selected( 'full_day', $month_lastday['proration'], false ) : '' ?>>
                Full day proration
            </option>
            <option value="by_minute" <?php echo ! empty( $month_lastday['proration'] ) ? selected( 'by_minute', $month_lastday['proration'], false ) : '' ?>>
                By the minute proration
            </option>
        </select>
    </p>
    <p class="form-field type-fields fields-month_lastday <?php echo $variable ? 'form-row' : '' ?> hidden">
        <label for="_subscription_month_lastday_proration_minimum"><?php echo __( 'Minimum prorated amount', 'reepay-subscriptions-for-woocommerce' ); ?></label>
        <input type="number" min="0"
               id="_subscription_month_lastday_proration_minimum" <?php echo esc_attr( $disabled ) ?>
               value="<?php echo ! empty( $month_lastday['proration_minimum'] ) ? esc_attr( $month_lastday['proration_minimum'] ) : 0 ?>"
               name="_subscription_month_lastday<?php echo $variable ? '[' . esc_attr( $loop ) . ']' : '' ?>[proration_minimum]"
               placeholder="<?php echo esc_attr( 'kr 0.00', 'reepay-subscriptions-for-woocommerce' ); ?>"/>
    </p>

    <!--Quarterly Primo-->
	<?php $primo = ! empty( $_reepay_subscription_primo ) ? $_reepay_subscription_primo : [] ?>
    <p class="form-field type-fields fields-primo hidden">
        <label for="_subscription_primo"><?php _e( 'Charge first day of every', 'reepay-subscriptions-for-woocommerce' ); ?></label>
        <strong><?php _e( '3rd Month', 'reepay-subscriptions-for-woocommerce' ); ?></strong>
    </p>
    <p class="form-field type-fields fields-primo hidden">
        <label for="_subscription_primo"><?php _e( 'Fixed Months:', 'reepay-subscriptions-for-woocommerce' ); ?></label>
        <strong><?php _e( 'Jan, Apr, Jul, Oct', 'reepay-subscriptions-for-woocommerce' ); ?></strong>
    </p>
    <p class="form-field type-fields fields-primo <?php echo $variable ? 'form-row' : '' ?> hidden">
        <label for="_subscription_primo_period"><?php echo __( 'Partial Period Handling', 'reepay-subscriptions-for-woocommerce' ); ?></label>
        <select id="_subscription_primo_period" <?php echo esc_attr( $disabled ) ?>
                name="_reepay_subscription_primo<?php echo $variable ? '[' . esc_attr( $loop ) . ']' : '' ?>[period]"
                class="wc_input_subscription_period_interval">
            <option value="bill_prorated" <?php echo ! empty( $primo['period'] ) ? selected( 'bill_prorated', $primo['period'], false ) : '' ?>>
                Bill prorated (Default)
            </option>
            <option value="bill_full" <?php echo ! empty( $primo['period'] ) ? selected( 'bill_full', $primo['period'], false ) : '' ?>>
                Bill for full period
            </option>
            <option value="bill_zero_amount" <?php echo ! empty( $primo['period'] ) ? selected( 'bill_zero_amount', $primo['period'], false ) : '' ?>>
                Bill a zero amount
            </option>
            <option value="no_bill" <?php echo ! empty( $primo['period'] ) ? selected( 'no_bill', $primo['period'], false ) : '' ?>>
                Do
                not consider the partial period a billing period
            </option>
        </select>
    </p>
    <p class="form-field type-fields fields-primo <?php echo $variable ? 'form-row' : '' ?> hidden">
        <label for="_subscription_primo_proration"><?php echo __( 'Proration setting', 'reepay-subscriptions-for-woocommerce' ); ?></label>
        <select id="_subscription_primo_proration" <?php echo esc_attr( $disabled ) ?>
                name="_reepay_subscription_primo<?php echo $variable ? '[' . esc_attr( $loop ) . ']' : '' ?>[proration]"
                class="wc_input_subscription_period_interval">
            <option value="full_day" <?php echo ! empty( $primo['proration'] ) ? selected( 'full_day', $primo['proration'], false ) : '' ?>>
                Full day proration
            </option>
            <option value="by_minute" <?php echo ! empty( $primo['proration'] ) ? selected( 'by_minute', $primo['proration'], false ) : '' ?>>
                By the minute proration
            </option>
        </select>
    </p>
    <p class="form-field type-fields fields-primo <?php echo $variable ? 'form-row' : '' ?> hidden">
        <label for="_subscription_primo_proration_minimum"><?php echo __( 'Minimum prorated amount', 'reepay-subscriptions-for-woocommerce' ); ?></label>
        <input type="number" min="0" id="_subscription_primo_proration_minimum" <?php echo esc_attr( $disabled ) ?>
               name="_reepay_subscription_primo<?php echo $variable ? '[' . esc_attr( $loop ) . ']' : '' ?>[proration_minimum]"
               placeholder="<?php echo esc_attr( 'kr 0.00', 'reepay-subscriptions-for-woocommerce' ); ?>"
               value="<?php echo ! empty( $primo['proration_minimum'] ) ? esc_attr( $primo['proration_minimum'] ) : 0 ?>"/>
    </p>

    <!--Quarterly Ultimo-->
	<?php $ultimo = ! empty( $_reepay_subscription_ultimo ) ? $_reepay_subscription_ultimo : [] ?>
    <p class="form-field type-fields fields-ultimo hidden">
        <label for="_subscription_ultimo"><?php _e( 'Charge last day of every', 'reepay-subscriptions-for-woocommerce' ); ?></label>
        <strong><?php _e( '3rd Month', 'reepay-subscriptions-for-woocommerce' ); ?></strong>
    </p>
    <p class="form-field type-fields fields-ultimo hidden">
        <label for="_subscription_ultimo"><?php _e( 'Fixed Months:', 'reepay-subscriptions-for-woocommerce' ); ?></label>
        <strong><?php _e( 'Jan, Apr, Jul, Oct', 'reepay-subscriptions-for-woocommerce' ); ?></strong>
    </p>
    <p class="form-field type-fields fields-ultimo <?php echo $variable ? 'form-row' : '' ?> hidden">
        <label for="_subscription_ultimo_period"><?php echo __( 'Partial Period Handling', 'reepay-subscriptions-for-woocommerce' ); ?></label>
        <select id="_subscription_ultimo_period" <?php echo esc_attr( $disabled ) ?>
                name="_reepay_subscription_ultimo<?php echo $variable ? '[' . esc_attr( $loop ) . ']' : '' ?>[period]"
                class="wc_input_subscription_period_interval">
            <option value="bill_prorated" <?php echo ! empty( $ultimo['period'] ) ? selected( 'bill_prorated', $ultimo['period'], false ) : '' ?>>
                Bill prorated (Default)
            </option>
            <option value="bill_full" <?php echo ! empty( $ultimo['period'] ) ? selected( 'bill_full', $ultimo['period'], false ) : '' ?>>
                Bill for full period
            </option>
            <option value="bill_zero_amount" <?php echo ! empty( $ultimo['period'] ) ? selected( 'bill_zero_amount', $ultimo['period'], false ) : '' ?>>
                Bill a zero amount
            </option>
            <option value="no_bill" <?php echo ! empty( $ultimo['period'] ) ? selected( 'no_bill', $ultimo['period'], false ) : '' ?>>
                Do not consider the partial period a billing period
            </option>
        </select>
    </p>
    <p class="form-field type-fields fields-ultimo <?php echo $variable ? 'form-row' : '' ?> hidden">
        <label for="_subscription_ultimo_proration"><?php echo __( 'Proration setting', 'reepay-subscriptions-for-woocommerce' ); ?></label>
        <select id="_subscription_ultimo_proration" <?php echo esc_attr( $disabled ) ?>
                name="_reepay_subscription_ultimo<?php echo $variable ? '[' . esc_attr( $loop ) . ']' : '' ?>[proration]"
                class="wc_input_subscription_period_interval">
            <option value="full_day" <?php echo ! empty( $ultimo['proration'] ) ? selected( 'full_day', $ultimo['proration'], false ) : '' ?>>
                Full day proration
            </option>
            <option value="by_minute" <?php echo ! empty( $ultimo['proration'] ) ? selected( 'by_minute', $ultimo['proration'], false ) : '' ?>>
                By the minute proration
            </option>
        </select>
    </p>
    <p class="form-field type-fields fields-ultimo <?php echo $variable ? 'form-row' : '' ?> hidden">
        <label for="_subscription_ultimo_proration_minimum"><?php echo __( 'Minimum prorated amount', 'reepay-subscriptions-for-woocommerce' ); ?></label>
        <input type="number" min="0" id="_subscription_ultimo_proration_minimum" <?php echo esc_attr( $disabled ) ?>
               name="_reepay_subscription_ultimo<?php echo $variable ? '[' . esc_attr( $loop ) . ']' : '' ?>[proration_minimum]"
               placeholder="<?php echo esc_attr( 'kr 0.00', 'reepay-subscriptions-for-woocommerce' ); ?>"
               value="<?php echo ! empty( $ultimo['proration_minimum'] ) ? esc_attr( $ultimo['proration_minimum'] ) : 0 ?>"/>
    </p>

    <!--Half-yearly-->
	<?php $half_yearly = ! empty( $_reepay_subscription_half_yearly ) ? $_reepay_subscription_half_yearly : [] ?>
    <p class="form-field type-fields fields-half_yearly hidden">
        <label for="_subscription_half_yearly"><?php _e( 'Charge every', 'reepay-subscriptions-for-woocommerce' ); ?></label>
        <strong><?php _e( '6th Month', 'reepay-subscriptions-for-woocommerce' ); ?></strong>
    </p>
    <p class="form-field type-fields fields-half_yearly hidden">
        <label for="_subscription_half_yearly"><?php _e( 'On this day of the month:', 'reepay-subscriptions-for-woocommerce' ); ?></label>
        <strong><?php _e( '1st', 'reepay-subscriptions-for-woocommerce' ); ?></strong>
    </p>
    <p class="form-field type-fields fields-half_yearly hidden">
        <label for="_subscription_half_yearly"><?php _e( 'Fixed Months:', 'reepay-subscriptions-for-woocommerce' ); ?></label>
        <strong><?php _e( 'Jan, Jul', 'reepay-subscriptions-for-woocommerce' ); ?></strong>
    </p>
    <p class="form-field type-fields fields-half_yearly <?php echo $variable ? 'form-row' : '' ?> hidden">
        <label for="_subscription_half_yearly_period"><?php echo __( 'Partial Period Handling', 'reepay-subscriptions-for-woocommerce' ); ?></label>
        <select id="_subscription_half_yearly_period" <?php echo esc_attr( $disabled ) ?>
                name="_reepay_subscription_half_yearly<?php echo $variable ? '[' . esc_attr( $loop ) . ']' : '' ?>[period]"
                class="wc_input_subscription_period_interval">
            <option value="bill_prorated" <?php echo ! empty( $half_yearly['period'] ) ? selected( 'bill_prorated', $half_yearly['period'], false ) : '' ?>>
                Bill prorated (Default)
            </option>
            <option value="bill_full" <?php echo ! empty( $half_yearly['period'] ) ? selected( 'bill_full', $half_yearly['period'], false ) : '' ?>>
                Bill for full period
            </option>
            <option value="bill_zero_amount" <?php echo ! empty( $half_yearly['period'] ) ? selected( 'bill_zero_amount', $half_yearly['period'], false ) : '' ?>>
                Bill a zero amount
            </option>
            <option value="no_bill" <?php echo ! empty( $half_yearly['period'] ) ? selected( 'no_bill', $half_yearly['period'], false ) : '' ?>>
                Do not consider the partial period a billing period
            </option>
        </select>
    </p>
    <p class="form-field type-fields fields-half_yearly <?php echo $variable ? 'form-row' : '' ?> hidden">
        <label for="_subscription_half_yearly_proration"><?php echo __( 'Proration setting', 'reepay-subscriptions-for-woocommerce' ); ?></label>
        <select id="_subscription_half_yearly_proration" <?php echo esc_attr( $disabled ) ?>
                name="_reepay_subscription_half_yearly<?php echo $variable ? '[' . esc_attr( $loop ) . ']' : '' ?>[proration]"
                class="wc_input_subscription_period_interval">
            <option value="full_day" <?php echo ! empty( $half_yearly['proration'] ) ? selected( 'full_day', $half_yearly['proration'], false ) : '' ?>>
                Full day proration
            </option>
            <option value="by_minute" <?php echo ! empty( $half_yearly['proration'] ) ? selected( 'by_minute', $half_yearly['proration'], false ) : '' ?>>
                By the minute proration
            </option>
        </select>
    </p>
    <p class="form-field type-fields fields-half_yearly <?php echo $variable ? 'form-row' : '' ?> hidden">
        <label for="_subscription_half_yearly_proration_minimum"><?php echo __( 'Minimum prorated amount', 'reepay-subscriptions-for-woocommerce' ); ?></label>
        <input type="number" min="0"
               id="_subscription_half_yearly_proration_minimum" <?php echo esc_attr( $disabled ) ?>
               name="_reepay_subscription_half_yearly<?php echo $variable ? '[' . esc_attr( $loop ) . ']' : '' ?>[proration_minimum]"
               placeholder="<?php echo esc_attr( 'kr 0.00', 'reepay-subscriptions-for-woocommerce' ); ?>"
               value="<?php echo ! empty( $half_yearly['proration_minimum'] ) ? esc_attr( $half_yearly['proration_minimum'] ) : 0 ?>"/>
    </p>


    <!--Yearly-->
	<?php $month_startdate_12 = ! empty( $_reepay_subscription_month_startdate_12 ) ? $_reepay_subscription_month_startdate_12 : [] ?>
    <p class="form-field type-fields fields-month_startdate_12 <?php echo $variable ? 'form-row' : '' ?> hidden">
        <label for="_subscription_half_yearly"><?php _e( 'Charge every', 'reepay-subscriptions-for-woocommerce' ); ?></label>
        <strong><?php _e( '12th Month', 'reepay-subscriptions-for-woocommerce' ); ?></strong>
    </p>
    <p class="form-field type-fields fields-month_startdate_12 <?php echo $variable ? 'form-row' : '' ?> hidden">
        <label for="_subscription_month_startdate_12"><?php _e( 'On this day of the month:', 'reepay-subscriptions-for-woocommerce' ); ?></label>
        <strong><?php _e( '1st', 'reepay-subscriptions-for-woocommerce' ); ?></strong>
    </p>
    <p class="form-field type-fields fields-month_startdate_12 <?php echo $variable ? 'form-row' : '' ?> hidden">
        <label for="_subscription_month_startdate_12"><?php _e( 'Fixed Months:', 'reepay-subscriptions-for-woocommerce' ); ?></label>
        <strong><?php _e( 'Jan', 'reepay-subscriptions-for-woocommerce' ); ?></strong>
    </p>
    <p class="form-field type-fields fields-month_startdate_12 <?php echo $variable ? 'form-row' : '' ?> hidden">
        <label for="_subscription_month_startdate_12_period"><?php echo __( 'Partial Period Handling', 'reepay-subscriptions-for-woocommerce' ); ?></label>
        <select id="_subscription_month_startdate_12_period" <?php echo esc_attr( $disabled ) ?>
                name="_reepay_subscription_month_startdate_12<?php echo $variable ? '[' . esc_attr( $loop ) . ']' : '' ?>[period]"
                class="wc_input_subscription_period_interval">
            <option value="bill_prorated" <?php echo ! empty( $month_startdate_12['period'] ) ? selected( 'bill_prorated', $month_startdate_12['period'], false ) : '' ?>>
                Bill prorated (Default)
            </option>
            <option value="bill_full" <?php echo ! empty( $month_startdate_12['period'] ) ? selected( 'bill_full', $month_startdate_12['period'], false ) : '' ?>>
                Bill for full period
            </option>
            <option value="bill_zero_amount" <?php echo ! empty( $month_startdate_12['period'] ) ? selected( 'bill_zero_amount', $month_startdate_12['period'], false ) : '' ?>>
                Bill a zero amount
            </option>
            <option value="no_bill" <?php echo ! empty( $month_startdate_12['period'] ) ? selected( 'no_bill', $month_startdate_12['period'], false ) : '' ?>>
                Do not consider the partial period a billing period
            </option>
        </select>
    </p>
    <p class="form-field type-fields fields-month_startdate_12 <?php echo $variable ? 'form-row' : '' ?> hidden">
        <label for="_subscription_month_startdate_12_proration"><?php echo __( 'Proration setting', 'reepay-subscriptions-for-woocommerce' ); ?></label>
        <select id="_subscription_month_startdate_12_proration" <?php echo esc_attr( $disabled ) ?>
                name="_reepay_subscription_month_startdate_12<?php echo $variable ? '[' . esc_attr( $loop ) . ']' : '' ?>[proration]"
                class="wc_input_subscription_period_interval">
            <option value="full_day" <?php echo ! empty( $month_startdate_12['proration'] ) ? selected( 'full_day', $month_startdate_12['proration'], false ) : '' ?>>
                Full day proration
            </option>
            <option value="by_minute" <?php echo ! empty( $month_startdate_12['proration'] ) ? selected( 'by_minute', $month_startdate_12['proration'], false ) : '' ?>>
                By the minute proration
            </option>
        </select>
    </p>
    <p class="form-field type-fields fields-month_startdate_12 <?php echo $variable ? 'form-row' : '' ?> hidden">
        <label for="_subscription_month_startdate_12_proration_minimum"><?php echo __( 'Minimum prorated amount', 'reepay-subscriptions-for-woocommerce' ); ?></label>
        <input type="number" min="0"
               id="_subscription_month_startdate_12_proration_minimum" <?php echo esc_attr( $disabled ) ?>
               name="_reepay_subscription_month_startdate_12<?php echo $variable ? '[' . esc_attr( $loop ) . ']' : '' ?>[proration_minimum]"
               placeholder="<?php echo esc_attr( 'kr 0.00', 'reepay-subscriptions-for-woocommerce' ); ?>"
               value="<?php echo ! empty( $month_startdate_12['proration_minimum'] ) ? esc_attr( $month_startdate_12['proration_minimum'] ) : 0 ?>"/>
    </p>


    <!--Fixed day of week-->
	<?php $weekly_fixedday = ! empty( $_reepay_subscription_weekly_fixedday ) ? $_reepay_subscription_weekly_fixedday : [] ?>
    <p class="form-field type-fields fields-weekly_fixedday <?php echo $variable ? 'form-row' : '' ?> hidden">
        <label for="_subscription_weekly_fixedday"><?php echo __( 'Charge every', 'reepay-subscriptions-for-woocommerce' ); ?></label>
        <input type="number" min="0" id="_subscription_weekly_fixedday" <?php echo esc_attr( $disabled ) ?>
               name="_reepay_subscription_weekly_fixedday<?php echo $variable ? '[' . esc_attr( $loop ) . ']' : '' ?>[week]">
		<?php echo __( 'Week', 'reepay-subscriptions-for-woocommerce' ); ?>
    </p>
    <p class="form-field type-fields fields-weekly_fixedday <?php echo $variable ? 'form-row' : '' ?> hidden">
        <label for="_subscription_weekly_fixedday_day"><?php echo __( 'On this day of the week', 'reepay-subscriptions-for-woocommerce' ); ?></label>
        <select id="_subscription_weekly_fixedday_day" <?php echo esc_attr( $disabled ) ?>
                name="_reepay_subscription_weekly_fixedday<?php echo $variable ? '[' . esc_attr( $loop ) . ']' : '' ?>[day]"
                class="wc_input_subscription_period_interval">
            <option value="1" <?php echo ! empty( $weekly_fixedday['day'] ) ? selected( '1', $weekly_fixedday['day'], false ) : '' ?>>
                Monday
            </option>
            <option value="2" <?php echo ! empty( $weekly_fixedday['day'] ) ? selected( '2', $weekly_fixedday['day'], false ) : '' ?>>
                Tuesday
            </option>
            <option value="3" <?php echo ! empty( $weekly_fixedday['day'] ) ? selected( '3', $weekly_fixedday['day'], false ) : '' ?>>
                Wednesday
            </option>
            <option value="4" <?php echo ! empty( $weekly_fixedday['day'] ) ? selected( '4', $weekly_fixedday['day'], false ) : '' ?>>
                Thursday
            </option>
            <option value="5" <?php echo ! empty( $weekly_fixedday['day'] ) ? selected( '5', $weekly_fixedday['day'], false ) : '' ?>>
                Friday
            </option>
            <option value="6" <?php echo ! empty( $weekly_fixedday['day'] ) ? selected( '6', $weekly_fixedday['day'], false ) : '' ?>>
                Saturday
            </option>
            <option value="7" <?php echo ! empty( $weekly_fixedday['day'] ) ? selected( '7', $weekly_fixedday['day'], false ) : '' ?>>
                Sunday
            </option>
        </select>
    </p>
    <p class="form-field type-fields fields-weekly_fixedday <?php echo $variable ? 'form-row' : '' ?> hidden">
        <label for="_subscription_weekly_fixedday_period"><?php echo __( 'Partial Period Handling', 'reepay-subscriptions-for-woocommerce' ); ?></label>
        <select id="_subscription_weekly_fixedday_period" <?php echo esc_attr( $disabled ) ?>
                name="_reepay_subscription_weekly_fixedday<?php echo $variable ? '[' . esc_attr( $loop ) . ']' : '' ?>[period]"
                class="wc_input_subscription_period_interval">
            <option value="bill_prorated" <?php echo ! empty( $weekly_fixedday['period'] ) ? selected( 'bill_prorated', $weekly_fixedday['period'], false ) : '' ?>>
                Bill prorated (Default)
            </option>
            <option value="bill_full" <?php echo ! empty( $weekly_fixedday['period'] ) ? selected( 'bill_prorated', $weekly_fixedday['period'], false ) : '' ?>>
                Bill for full period
            </option>
            <option value="bill_zero_amount" <?php echo ! empty( $weekly_fixedday['period'] ) ? selected( 'bill_prorated', $weekly_fixedday['period'], false ) : '' ?>>
                Bill a zero amount
            </option>
            <option value="no_bill" <?php echo ! empty( $weekly_fixedday['period'] ) ? selected( 'bill_prorated', $weekly_fixedday['period'], false ) : '' ?>>
                Do not consider the partial period a billing period
            </option>
        </select>
    </p>
    <p class="form-field type-fields fields-weekly_fixedday <?php echo $variable ? 'form-row' : '' ?> hidden">
        <label for="_subscription_weekly_fixedday_proration"><?php echo __( 'Proration setting', 'reepay-subscriptions-for-woocommerce' ); ?></label>
        <select id="_subscription_weekly_fixedday_proration" <?php echo esc_attr( $disabled ) ?>
                name="_reepay_subscription_weekly_fixedday<?php echo $variable ? '[' . esc_attr( $loop ) . ']' : '' ?>[proration]"
                class="wc_input_subscription_period_interval">
            <option value="full_day" <?php echo ! empty( $weekly_fixedday['proration'] ) ? selected( 'full_day', $weekly_fixedday['proration'], false ) : '' ?>>
                Full day proration
            </option>
            <option value="by_minute" <?php echo ! empty( $weekly_fixedday['proration'] ) ? selected( 'by_minute', $weekly_fixedday['proration'], false ) : '' ?>>
                By the minute proration
            </option>
        </select>
    </p>
    <p class="form-field type-fields fields-weekly_fixedday <?php echo $variable ? 'form-row' : '' ?> hidden">
        <label for="_subscription_weekly_fixedday_proration_minimum"><?php echo __( 'Minimum prorated amount', 'reepay-subscriptions-for-woocommerce' ); ?></label>
        <input type="number" min="0"
               id="_subscription_weekly_fixedday_proration_minimum" <?php echo esc_attr( $disabled ) ?>
               name="_reepay_subscription_weekly_fixedday<?php echo $variable ? '[' . esc_attr( $loop ) . ']' : '' ?>[proration_minimum]"
               placeholder="<?php echo esc_attr( 'kr 0.00', 'reepay-subscriptions-for-woocommerce' ); ?>"
               value="<?php echo ! empty( $weekly_fixedday['proration_minimum'] ) ? esc_attr( $weekly_fixedday['proration_minimum'] ) : 0 ?>"/>
    </p>

    <p class="form-field advanced-fields <?php echo $variable ? 'form-row' : '' ?>">
        <label for="_reepay_subscription_default_quantity">
			<?php echo __( 'Default Quantity', 'reepay-subscriptions-for-woocommerce' ); ?>
        </label>
		<?php echo wc_help_tip( __( 'Default quantity to use when creating a new subscription. Also used as the quantity on hosted pages.', 'reepay-subscriptions-for-woocommerce' ) ); ?>
        <input type="number" min="0" id="_reepay_subscription_default_quantity" <?php echo esc_attr( $disabled ) ?>
               name="_reepay_subscription_default_quantity<?php echo $variable ? '[' . esc_attr( $loop ) . ']' : '' ?>"
               class="wc_input_price wc_input_subscription_price"
               placeholder="<?php echo __( 'Default Quantity', 'reepay-subscriptions-for-woocommerce' ); ?>"
               value="<?php echo ! empty( $_reepay_subscription_default_quantity ) ? esc_attr( $_reepay_subscription_default_quantity ) : '1' ?>"/>
    </p>

    <!--Advanced-->
    <p class="form-field advanced-fields <?php echo $variable ? 'form-row' : '' ?>">
        <label for="_reepay_subscription_renewal_reminder">
			<?php echo __( 'Renewal Reminder', 'reepay-subscriptions-for-woocommerce' ); ?>
        </label>
        <input type="number" min="0" id="_reepay_subscription_renewal_reminder" <?php echo esc_attr( $disabled ) ?>
               name="_reepay_subscription_renewal_reminder<?php echo $variable ? '[' . esc_attr( $loop ) . ']' : '' ?>"
               class="wc_input_price wc_input_subscription_price"
               placeholder="<?php echo __( 'Renewal Reminder Schedule', 'reepay-subscriptions-for-woocommerce' ); ?>"
               value="<?php echo ! empty( $_reepay_subscription_renewal_reminder ) ? esc_attr( $_reepay_subscription_renewal_reminder ) : '' ?>"/>
    </p>
</div>

<div class="options_group show_if_reepay_subscription">
    <p class="form-field <?php echo $variable ? 'form-row' : '' ?>">
        <label for="_subscription_contract_periods"><?php echo __( 'Minimum Contract Period', 'reepay-subscriptions-for-woocommerce' ); ?></label>
		<?php echo wc_help_tip( __( 'Periods are relative to the billing frequency. If you have chosen to bill every month, a period is one month.', 'reepay-subscriptions-for-woocommerce' ) ); ?>
        <input type="number" min="0" id="_subscription_contract_periods" <?php echo esc_attr( $disabled ) ?>
               name="_reepay_subscription_contract_periods<?php echo $variable ? '[' . esc_attr( $loop ) . ']' : '' ?>"
               placeholder="<?php echo __( 'Periods', 'reepay-subscriptions-for-woocommerce' ); ?>"
               value="<?php echo ! empty( $_reepay_subscription_contract_periods ) ? esc_attr( $_reepay_subscription_contract_periods ) : 0 ?>"/>
    </p>
    <p class="form-field fields-contract_periods hidden">
        <label for="_reepay_subscription_contract_periods_full"></label>
		<?php echo __( 'When the subscription is created', 'reepay-subscriptions-for-woocommerce' ); ?>
        <input <?php echo esc_attr( $disabled ) ?>
                type="radio"
                id="_reepay_subscription_contract_periods_full"
                name="_reepay_subscription_contract_periods_full<?php echo $variable ? '[' . esc_attr( $loop ) . ']' : '' ?>"
                value="false" <?php echo ! empty( $_reepay_subscription_contract_periods_full ) ? checked( 'false', $_reepay_subscription_contract_periods_full, false ) : ''; ?>/>
		<?php echo __( 'When the first period starts', 'reepay-subscriptions-for-woocommerce' ); ?>
        <input <?php echo esc_attr( $disabled ) ?>
                type="radio" id="_reepay_subscription_contract_periods_full"
                name="_reepay_subscription_contract_periods_full<?php echo $variable ? '[' . esc_attr( $loop ) . ']' : '' ?>"
                value="true" <?php checked( 'true', $_reepay_subscription_contract_periods_full ?? false, false ); ?>/>
    </p>
</div>

<div class="options_group show_if_reepay_subscription">
    <p class="form-field">
        <label for="_subscription_notice_period"><?php echo __( 'Notice period', 'reepay-subscriptions-for-woocommerce' ); ?></label>
		<?php echo wc_help_tip( __( 'Periods are relative to the billing frequency. If you have chosen to bill every month, a period is one month.', 'reepay-subscriptions-for-woocommerce' ) ); ?>
        <input type="number" min="0" id="_subscription_notice_period" <?php echo esc_attr( $disabled ) ?>
               name="_reepay_subscription_notice_period<?php echo $variable ? '[' . esc_attr( $loop ) . ']' : '' ?>"
               placeholder="<?php echo __( 'Periods', 'reepay-subscriptions-for-woocommerce' ); ?>"
               value="<?php echo ! empty( $_reepay_subscription_notice_period ) ? esc_attr( $_reepay_subscription_notice_period ) : 0 ?>"/>
    </p>
    <p class="form-field fields-notice_period hidden">
        <label for="_subscription_notice_period_start"></label>
		<?php echo __( 'When the current cancelled period ends', 'reepay-subscriptions-for-woocommerce' ); ?> <input
                type="radio"
                id="_subscription_notice_period_start"
                name="_reepay_subscription_notice_period_start<?php echo $variable ? '[' . esc_attr( $loop ) . ']' : '' ?>"
                value="true" <?php echo ! empty( $_reepay_subscription_notice_period_start ) ? checked( 'true', $_reepay_subscription_notice_period_start, false ) : ''; ?>/>
		<?php echo __( 'Immediately after cancellation', 'reepay-subscriptions-for-woocommerce' ); ?> <input
                type="radio"
                id="_subscription_notice_period_start"
                name="_reepay_subscription_notice_period_start<?php echo $variable ? '[' . esc_attr( $loop ) . ']' : '' ?>"
                value="false" <?php echo ! empty( $_reepay_subscription_notice_period_start ) ? checked( 'false', $_reepay_subscription_notice_period_start, false ) : ''; ?>/>
    </p>
</div>
<?php $_reepay_subscription_billing_cycles = empty( $_reepay_subscription_billing_cycles ) ? false : $_reepay_subscription_billing_cycles; ?>
<div class="options_group show_if_reepay_subscription billing_cycles_block">
    <p class="form-field <?php echo $variable ? 'form-row' : '' ?>">
        <label for="_subscription_billing_cycles"><?php echo __( 'Billing Cycles', 'reepay-subscriptions-for-woocommerce' ); ?></label>
		<?php echo __( 'Auto Renew until cancelled', 'reepay-subscriptions-for-woocommerce' ); ?> <input type="radio"
                                                                                                         id="_subscription_billing_cycles" <?php echo esc_attr( $disabled ) ?>
                                                                                                         name="_reepay_subscription_billing_cycles<?php echo $variable ? '[' . esc_attr( $loop ) . ']' : '' ?>"
                                                                                                         value="false" <?php echo ! empty( $_reepay_subscription_billing_cycles ) ? checked( 'false', $_reepay_subscription_billing_cycles, false ) : ''; ?>/>
		<?php echo __( 'Fixed Number of billing cycles', 'reepay-subscriptions-for-woocommerce' ); ?> <input
                type="radio"
                id="_subscription_billing_cycles" <?php echo esc_attr( $disabled ) ?>
                name="_reepay_subscription_billing_cycles<?php echo $variable ? '[' . esc_attr( $loop ) . ']' : '' ?>"
                value="true" <?php echo ! empty( $_reepay_subscription_billing_cycles ) ? checked( 'true', $_reepay_subscription_billing_cycles, false ) : ''; ?>/>
    </p>
    <p class="form-field fields-billing_cycles <?php echo $variable ? 'form-row' : '' ?> hidden">
        <label for="_subscription_billing_cycles_period"><?php echo __( 'Number of billing cycles', 'reepay-subscriptions-for-woocommerce' ); ?></label>
        <input type="number" min="0" id="_subscription_billing_cycles_period"
               name="_reepay_subscription_billing_cycles_period<?php echo $variable ? '[' . esc_attr( $loop ) . ']' : '' ?>" <?php echo esc_attr( $disabled ) ?>
               placeholder="*"
               value="<?php echo ! empty( $_reepay_subscription_billing_cycles_period ) ? esc_attr( $_reepay_subscription_billing_cycles_period ) : 0 ?>"/>
    </p>
</div>

<?php $trial = ! empty( $_reepay_subscription_trial ) ? $_reepay_subscription_trial : [] ?>
<div class="options_group reepay_subscription_trial show_if_reepay_subscription">
    <p class="form-field <?php echo $variable ? 'form-row' : '' ?>">
        <label for="_subscription_trial"><?php echo __( 'Trial', 'reepay-subscriptions-for-woocommerce' ); ?></label>
        <select id="_subscription_trial" <?php echo esc_attr( $disabled ) ?>
                name="_reepay_subscription_trial<?php echo $variable ? '[' . esc_attr( $loop ) . ']' : '' ?>[type]"
                class="wc_input_subscription_period_interval">
			<?php foreach ( WC_Reepay_Subscription_Plan_Simple::$trial as $value => $label ) { ?>
                <option value="<?php echo esc_attr( $value ); ?>" <?php echo ! empty( $trial['type'] ) ? selected( $value, $trial['type'], false ) : '' ?>><?php echo esc_html( $label ); ?></option>
			<?php } ?>
        </select>
    </p>
    <p class="form-field trial-fields fields-customize <?php echo $variable ? 'form-row' : '' ?> hidden">
        <label for="_subscription_trial_length"><?php echo __( 'Trial Length', 'reepay-subscriptions-for-woocommerce' ); ?></label>
        <input type="number" min="0" id="_subscription_trial_length" <?php echo esc_attr( $disabled ) ?>
               name="_reepay_subscription_trial<?php echo $variable ? '[' . esc_attr( $loop ) . ']' : '' ?>[length]"
               placeholder="<?php echo __( 'Length', 'reepay-subscriptions-for-woocommerce' ); ?>"
               value="<?php echo ! empty( $trial['length'] ) ? esc_attr( $trial['length'] ) : 0 ?>"/>
        <select id="_subscription_trial_unit" <?php echo esc_attr( $disabled ) ?>
                name="_reepay_subscription_trial<?php echo $variable ? '[' . esc_attr( $loop ) . ']' : '' ?>[unit]"
                class="wc_input_subscription_period_interval">
            <option value="days" <?php echo ! empty( $trial['unit'] ) ? selected( 'days', $trial['unit'], false ) : '' ?>>
                Days
            </option>
            <option value="months" <?php echo ! empty( $trial['unit'] ) ? selected( 'months', $trial['unit'], false ) : '' ?>>
                Months
            </option>
        </select>
    </p>
    <p class="form-field <?php echo $variable ? 'form-row' : '' ?>  trial-fields fields-7days fields-14days fields-1month fields-customize hidden">
        <label for="_subscription_trial_reminder"><?php echo __( 'Optional Trial Reminder Schedule', 'reepay-subscriptions-for-woocommerce' ); ?></label>
        <input type="number" min="0" id="_subscription_trial_reminder" <?php echo esc_attr( $disabled ) ?>
               name="_reepay_subscription_trial<?php echo $variable ? '[' . esc_attr( $loop ) . ']' : '' ?>[reminder]"
               placeholder="<?php echo __( 'Days', 'reepay-subscriptions-for-woocommerce' ); ?>"
               value="<?php echo ! empty( $trial['reminder'] ) ? esc_attr( $trial['reminder'] ) : 0 ?>"/>
    </p>
</div>

<?php $fee = ! empty( $_reepay_subscription_fee ) ? $_reepay_subscription_fee : [] ?>
<div class="options_group reepay_subscription_fee show_if_reepay_subscription">
    <p class="form-field">
        <label for="_subscription_fee"><?php echo __( 'Include setup fee', 'reepay-subscriptions-for-woocommerce' ); ?></label>
        <input type="checkbox" id="_subscription_fee" <?php echo esc_attr( $disabled ) ?>
               name="_reepay_subscription_fee<?php echo $variable ? '[' . esc_attr( $loop ) . ']' : '' ?>[enabled]"
               value="yes" <?php echo ! empty( $fee['enabled'] ) && $fee['enabled'] == 'yes' ? 'checked' : '' ?> />
    </p>

    <p class="form-field fee-fields <?php echo $variable ? 'dimensions_field form-row' : '' ?> hidden">
        <label for="_subscription_fee_amount"><?php echo __( 'Setup Fee (kr)', 'reepay-subscriptions-for-woocommerce' ); ?></label>
        <span class="wrap">
            <input type="number" min="0"
                   id="_subscription_fee_amount" <?php echo esc_attr( $disabled ) ?> name="_reepay_subscription_fee<?php echo $variable ? '[' . esc_attr( $loop ) . ']' : '' ?>[amount]"
                   class="wc_input_price wc_input_subscription_price"
                   placeholder="<?php echo esc_attr( 'Amount', 'reepay-subscriptions-for-woocommerce' ); ?>" step="any"
                   min="0"
                   value="<?php echo ! empty( $fee['amount'] ) ? esc_attr( $fee['amount'] ) : 0 ?>"/>
            <input type="text"
                   id="_subscription_fee_text" <?php echo esc_attr( $disabled ) ?> name="_reepay_subscription_fee<?php echo $variable ? '[' . esc_attr( $loop ) . ']' : '' ?>[text]"
                   placeholder="<?php echo esc_attr( 'Text', 'reepay-subscriptions-for-woocommerce' ); ?>"
                   value="<?php echo ! empty( $fee['text'] ) ? esc_attr( $fee['text'] ) : '' ?>"/>
            <select id="_subscription_fee_handling" <?php echo esc_attr( $disabled ) ?> name="_reepay_subscription_fee<?php echo $variable ? '[' . esc_attr( $loop ) . ']' : '' ?>[handling]"
                    class="wc_input_subscription_period_interval">
                <option value="first" <?php echo ! empty( $fee['handling'] ) ? selected( 'first', $fee['handling'], false ) : '' ?>><?php echo __( 'Include setup fee as order line on the first scheduled invoice', 'reepay-subscriptions-for-woocommerce' ); ?></option>
                <option value="separate" <?php echo ! empty( $fee['handling'] ) ? selected( 'separate', $fee['handling'], false ) : '' ?>><?php echo __( 'Create a separate invoice for the setup fee', 'reepay-subscriptions-for-woocommerce' ); ?></option>
                <option value="separate_conditional" <?php echo ! empty( $fee['handling'] ) ? selected( 'separate_conditional', $fee['handling'], false ) : '' ?>><?php echo __( 'Create a separate invoice for the setup fee, if the first invoice is not created in conjunction with the creation', 'reepay-subscriptions-for-woocommerce' ); ?></option>
            </select>
        </span>
    </p>
</div>
<div class="options_group show_if_reepay_subscription">
	<?php
	if ( ! empty( $product_object ) && function_exists( 'woocommerce_wp_select' ) ) {
		woocommerce_wp_select(
			[
				'id'                => '_tax_status',
				'value'             => $product_object->get_tax_status( 'edit' ),
				'label'             => __( 'Tax status', 'reepay-subscriptions-for-woocommerce' ),
				'options'           => [
					'taxable'  => __( 'Taxable', 'reepay-subscriptions-for-woocommerce' ),
					'shipping' => __( 'Shipping only', 'reepay-subscriptions-for-woocommerce' ),
					'none'     => _x( 'None', 'Tax status', 'reepay-subscriptions-for-woocommerce' ),
				],
				'desc_tip'          => 'true',
				'description'       => __( 'Define whether or not the entire product is taxable, or just the cost of shipping it.', 'reepay-subscriptions-for-woocommerce' ),
				'custom_attributes' => empty( $disabled ) ? [] : [ 'disabled' => 'disabled' ]
			]
		);

		woocommerce_wp_select(
			[
				'id'                => '_tax_class',
				'value'             => $product_object->get_tax_class( 'edit' ),
				'label'             => __( 'Tax class', 'reepay-subscriptions-for-woocommerce' ),
				'options'           => wc_get_product_tax_class_options(),
				'desc_tip'          => 'true',
				'description'       => __( 'Choose a tax class for this product. Tax classes are used to apply different tax rates specific to certain types of product.', 'reepay-subscriptions-for-woocommerce' ),
				'custom_attributes' => empty( $disabled ) ? [] : [ 'disabled' => 'disabled' ]
			]
		);
	}
	do_action( 'woocommerce_product_options_tax' );
	?>
</div>

<?php if ( isset( $is_exist ) && $is_exist ):
	if ( empty( $_reepay_subscription_supersedes ) ) {
		$_reepay_subscription_supersedes = 'no_sub_update';
	} ?>
    <div class="options_group reepay_subscription_supersedes_block">
        <p class="form-field <?php echo $variable ? 'supersedes_block_variable"' : '' ?>">
            <label for="_reepay_subscription_supersedes"><?php echo __( 'Supersede mode', 'reepay-subscriptions-for-woocommerce' ); ?></label>
			<?php echo __( "Don't schedule subscription update", 'reepay-subscriptions-for-woocommerce' ); ?>
			<?php echo wc_help_tip( __( 'Using this, existing subscriptions will stay on the current version of the plan', 'reepay-subscriptions-for-woocommerce' ) ); ?>
            <input type="radio"
                   id="_reepay_subscription_supersedes" <?php echo esc_attr( $disabled ) ?>
                   name="_reepay_subscription_supersedes<?php echo $variable ? '[' . esc_attr( $loop ) . ']' : '' ?>"
                   value="no_sub_update" <?php echo ! empty( $_reepay_subscription_supersedes ) ? checked( 'no_sub_update', $_reepay_subscription_supersedes, false ) : ''; ?>/>
			<?php echo __( 'Schedule subscription update', 'reepay-subscriptions-for-woocommerce' ); ?>
			<?php echo wc_help_tip( __( 'This will update all subscriptions to use the new version after the current billing period', 'reepay-subscriptions-for-woocommerce' ) ); ?>
            <input type="radio"
                   id="_reepay_subscription_supersedes" <?php echo esc_attr( $disabled ) ?>
                   name="_reepay_subscription_supersedes<?php echo $variable ? '[' . esc_attr( $loop ) . ']' : '' ?>"
                   value="scheduled_sub_update" <?php echo ! empty( $_reepay_subscription_supersedes ) ? checked( 'scheduled_sub_update', $_reepay_subscription_supersedes, false ) : ''; ?>/>
        </p>

    </div>
<?php endif; ?>
