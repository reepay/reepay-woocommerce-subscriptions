<div class="options_group reepay_subscription_pricing show_if_reepay_subscription">
    <p class="form-field">
        <label for="#"><?php _e( 'Subscription pricing (kr)', 'reepay-subscriptions-for-woocommerce' ); ?></label>
        <span><?php echo ! empty( $_reepay_subscription_price ) ? esc_attr( wc_format_localized_price( $_reepay_subscription_price ) ) : 0 ?></span>
    </p>

    <p class="form-field">
        <label for="#"><?php _e( 'Schedule Type', 'reepay-subscriptions-for-woocommerce' ); ?></label>
        <span><?php echo WC_Reepay_Subscription_Plan_Simple::$schedule_types[ $_reepay_subscription_schedule_type ?? '' ] ?? 'undefined' ?></span>
    </p>

	<?php if ( $_reepay_subscription_schedule_type === WC_Reepay_Subscription_Plan_Simple::TYPE_DAILY ) : ?>
        <p class="form-field">
            <label for="#"><?php _e( 'Charge every', 'reepay-subscriptions-for-woocommerce' ); ?></label>
            <span><?php _e( 'Day', 'reepay-subscriptions-for-woocommerce' ) ?></span>
        </p>
	<?php endif; ?>

	<?php if ( $_reepay_subscription_schedule_type === WC_Reepay_Subscription_Plan_Simple::TYPE_MONTH_START_DATE ) : ?>
        <p class="form-field">
            <label for="#"><?php _e( 'Charge every', 'reepay-subscriptions-for-woocommerce' ); ?></label>
            <span><?php echo ! empty( $_reepay_subscription_month_startdate ) ? esc_attr( $_reepay_subscription_month_startdate ) : 1 ?></span>
            <span><?php _e( 'Month', 'reepay-subscriptions-for-woocommerce' ) ?></span>
        </p>
	<?php endif; ?>

	<?php if ( $_reepay_subscription_schedule_type === WC_Reepay_Subscription_Plan_Simple::TYPE_MONTH_FIXED_DAY
	           || $_reepay_subscription_schedule_type === WC_Reepay_Subscription_Plan_Simple::TYPE_MONTH_LAST_DAY
	) :

		$month_data = [];

		if ( $_reepay_subscription_schedule_type === WC_Reepay_Subscription_Plan_Simple::TYPE_MONTH_FIXED_DAY ) {
			$month_data = $_reepay_subscription_month_fixedday ?? [];
		} elseif ( $_reepay_subscription_schedule_type === WC_Reepay_Subscription_Plan_Simple::TYPE_MONTH_LAST_DAY ) {
			$month_data = $_reepay_subscription_month_lastday ?? [];
		}

		?>

        <p class="form-field">
            <label for="#"><?php _e( 'Charge every', 'reepay-subscriptions-for-woocommerce' ); ?></label>
            <span><?php echo $month_data['month'] ?></span>
            <span><?php _e( 'Month', 'reepay-subscriptions-for-woocommerce' ); ?></span>
        </p>
        <p class="form-field">
            <label for="#"><?php _e( 'On this day of the month', 'reepay-subscriptions-for-woocommerce' ); ?></label>
            <span><?php echo $month_data['day'] ?></span>
        </p>
        <p class="form-field">
            <label for="#"><?php _e( 'Partial Period Handling', 'reepay-subscriptions-for-woocommerce' ); ?></label>
            <span><?php echo WC_Reepay_Subscription_Plan_Simple::$bill_types[ $month_data['period'] ?? '' ] ?? '' ?></span>
        </p>
        <p class="form-field">
            <label for="#"><?php _e( 'Proration setting', 'reepay-subscriptions-for-woocommerce' ); ?>: </label>
            <span><?php echo WC_Reepay_Subscription_Plan_Simple::$proration_types[ $month_data['proration'] ?? '' ] ?? '' ?></span>
        </p>
        <p class="form-field">
            <label for="#"><?php _e( 'Minimum prorated amount', 'reepay-subscriptions-for-woocommerce' ); ?></label>
            <span><?php echo ! empty( $month_data['proration_minimum'] ) ? esc_attr( $month_data['proration_minimum'] ) : 0 ?></span>
        </p>
	<?php endif; ?>

	<?php if ( $_reepay_subscription_schedule_type === WC_Reepay_Subscription_Plan_Simple::TYPE_PRIMO
	           || $_reepay_subscription_schedule_type === WC_Reepay_Subscription_Plan_Simple::TYPE_ULTIMO
	) :

		$quarter_data = [];

		if ( $_reepay_subscription_schedule_type === WC_Reepay_Subscription_Plan_Simple::TYPE_PRIMO ) {
			$quarter_data = $_reepay_subscription_primo ?? [];
		} elseif ( $_reepay_subscription_schedule_type === WC_Reepay_Subscription_Plan_Simple::TYPE_ULTIMO ) {
			$quarter_data = $_reepay_subscription_ultimo ?? [];
		}

		?>
        <p class="form-field">
            <label for="#"><?php _e( 'Charge first day of every', 'reepay-subscriptions-for-woocommerce' ); ?></label>
            <span><?php _e( '3rd Month', 'reepay-subscriptions-for-woocommerce' ); ?></span>
        </p>
        <p class="form-field">
            <label for="#"><?php _e( 'Fixed Months:', 'reepay-subscriptions-for-woocommerce' ); ?></label>
            <span><?php _e( 'Jan, Apr, Jul, Oct', 'reepay-subscriptions-for-woocommerce' ); ?></span>
        </p>
        <p class="form-field">
            <label for="#"><?php _e( 'Partial Period Handling', 'reepay-subscriptions-for-woocommerce' ); ?></label>
            <span><?php echo WC_Reepay_Subscription_Plan_Simple::$bill_types[ $quarter_data['period'] ?? '' ] ?? '' ?></span>
        </p>
        <p class="form-field">
            <label for="#"><?php _e( 'Proration setting', 'reepay-subscriptions-for-woocommerce' ); ?>: </label>
            <span><?php echo WC_Reepay_Subscription_Plan_Simple::$proration_types[ $quarter_data['proration'] ?? '' ] ?? '' ?></span>
        </p>
        <p class="form-field">
            <label for="#"><?php _e( 'Minimum prorated amount', 'reepay-subscriptions-for-woocommerce' ); ?></label>
            <span><?php echo ! empty( $quarter_data['proration_minimum'] ) ? esc_attr( $quarter_data['proration_minimum'] ) : 0 ?></span>
        </p>
	<?php endif; ?>

	<?php if ( $_reepay_subscription_schedule_type === WC_Reepay_Subscription_Plan_Simple::TYPE_HALF_YEARLY ) : ?>
        <p class="form-field">
            <label for="#"><?php _e( 'Charge first day of every', 'reepay-subscriptions-for-woocommerce' ); ?></label>
            <span><?php _e( '6th Month', 'reepay-subscriptions-for-woocommerce' ); ?></span>
        </p>
        <p class="form-field">
            <label for="#"><?php _e( 'On this day of the month:', 'reepay-subscriptions-for-woocommerce' ); ?></label>
            <span><?php _e( '1st', 'reepay-subscriptions-for-woocommerce' ); ?></span>
        </p>
        <p class="form-field">
            <label for="#"><?php _e( 'Fixed Months:', 'reepay-subscriptions-for-woocommerce' ); ?></label>
            <span><?php _e( 'Jan, Jul', 'reepay-subscriptions-for-woocommerce' ); ?></span>
        </p>
        <p class="form-field">
            <label for="#"><?php _e( 'Partial Period Handling', 'reepay-subscriptions-for-woocommerce' ); ?></label>
            <span><?php echo WC_Reepay_Subscription_Plan_Simple::$bill_types[ $_reepay_subscription_half_yearly['period'] ?? '' ] ?? '' ?></span>
        </p>
        <p class="form-field">
            <label for="#"><?php _e( 'Proration setting', 'reepay-subscriptions-for-woocommerce' ); ?>: </label>
            <span><?php echo WC_Reepay_Subscription_Plan_Simple::$proration_types[ $_reepay_subscription_half_yearly['proration'] ?? '' ] ?? '' ?></span>
        </p>
        <p class="form-field">
            <label for="#"><?php _e( 'Minimum prorated amount', 'reepay-subscriptions-for-woocommerce' ); ?></label>
            <span><?php echo ! empty( $_reepay_subscription_half_yearly['proration_minimum'] ) ? esc_attr( $_reepay_subscription_half_yearly['proration_minimum'] ) : 0 ?></span>
        </p>
	<?php endif; ?>

	<?php if ( $_reepay_subscription_schedule_type === WC_Reepay_Subscription_Plan_Simple::TYPE_START_DATE_12 ) : ?>
        <p class="form-field">
            <label for="#"><?php _e( 'Charge first day of every', 'reepay-subscriptions-for-woocommerce' ); ?></label>
            <span><?php _e( '12th Month', 'reepay-subscriptions-for-woocommerce' ); ?></span>
        </p>
        <p class="form-field">
            <label for="#"><?php _e( 'On this day of the month:', 'reepay-subscriptions-for-woocommerce' ); ?></label>
            <span><?php _e( '1st', 'reepay-subscriptions-for-woocommerce' ); ?></span>
        </p>
        <p class="form-field">
            <label for="#"><?php _e( 'Fixed Months:', 'reepay-subscriptions-for-woocommerce' ); ?></label>
            <span><?php _e( 'Jan', 'reepay-subscriptions-for-woocommerce' ); ?></span>
        </p>
        <p class="form-field">
            <label for="#"><?php _e( 'Partial Period Handling', 'reepay-subscriptions-for-woocommerce' ); ?></label>
            <span><?php echo WC_Reepay_Subscription_Plan_Simple::$bill_types[ $_reepay_subscription_month_startdate_12['period'] ?? '' ] ?? '' ?></span>
        </p>
        <p class="form-field">
            <label for="#"><?php _e( 'Proration setting', 'reepay-subscriptions-for-woocommerce' ); ?>: </label>
            <span><?php echo WC_Reepay_Subscription_Plan_Simple::$proration_types[ $_reepay_subscription_month_startdate_12['proration'] ?? '' ] ?? '' ?></span>
        </p>
        <p class="form-field">
            <label for="#"><?php _e( 'Minimum prorated amount', 'reepay-subscriptions-for-woocommerce' ); ?></label>
            <span><?php echo ! empty( $_reepay_subscription_month_startdate_12['proration_minimum'] ) ? esc_attr( $_reepay_subscription_month_startdate_12['proration_minimum'] )
					: 0 ?></span>
        </p>
	<?php endif; ?>

	<?php if ( $_reepay_subscription_schedule_type === WC_Reepay_Subscription_Plan_Simple::TYPE_WEEKLY_FIXED_DAY ) : ?>
        <p class="form-field">
            <label for="#"><?php _e( 'Charge every', 'reepay-subscriptions-for-woocommerce' ); ?></label>
            <span><?php echo $_reepay_subscription_weekly_fixedday['week'] ?></span>
            <span><?php _e( 'Week', 'reepay-subscriptions-for-woocommerce' ); ?></span>
        </p>
        <p class="form-field">
            <label for="#"><?php _e( 'On this day of the week', 'reepay-subscriptions-for-woocommerce' ); ?></label>
            <span> <?php echo WC_Reepay_Subscription_Plan_Simple::$number_to_week_day[ intval( $weekly_fixedday['day'] ?? '0' ) ] ?? '' ?></span>
        </p>
        <p class="form-field">
            <label for="#"><?php _e( 'Partial Period Handling', 'reepay-subscriptions-for-woocommerce' ); ?></label>
            <span><?php echo WC_Reepay_Subscription_Plan_Simple::$bill_types[ $_reepay_subscription_weekly_fixedday['period'] ?? '' ] ?? '' ?></span>
        </p>


        <p class="form-field">
            <label for="#"><?php _e( 'Proration setting', 'reepay-subscriptions-for-woocommerce' ); ?>: </label>
            <span><?php echo WC_Reepay_Subscription_Plan_Simple::$proration_types[ $_reepay_subscription_weekly_fixedday['proration'] ?? '' ] ?? '' ?></span>
        </p>
        <p class="form-field">
            <label for="#"><?php _e( 'Minimum prorated amount', 'reepay-subscriptions-for-woocommerce' ); ?></label>
            <span><?php echo ! empty( $_reepay_subscription_weekly_fixedday['proration_minimum'] ) ? esc_attr( $_reepay_subscription_weekly_fixedday['proration_minimum'] )
					: 0 ?></span>
        </p>
	<?php endif; ?>

    <p class="form-field">
        <label for="#"><?php _e( 'Default Quantity', 'reepay-subscriptions-for-woocommerce' ); ?></label>
        <span>
            <?php echo ! empty( $_reepay_subscription_default_quantity ) ? esc_attr( $_reepay_subscription_default_quantity ) : '1' ?>
            <?php echo wc_help_tip( __( 'Default quantity to use when creating a new subscription. Also used as the quantity on hosted pages.', 'reepay-subscriptions-for-woocommerce' ) ); ?>
        </span>
    </p>

	<?php if ( ! empty( $_reepay_subscription_renewal_reminder ) ) : ?>
        <p class="form-field">
            <label for="#"><?php _e( 'Renewal Reminder', 'reepay-subscriptions-for-woocommerce' ); ?></label>
            <span><?php echo esc_attr( $_reepay_subscription_renewal_reminder ) ?></span>
        </p>
	<?php endif; ?>
</div>

<div class="options_group show_if_reepay_subscription">
    <p class="form-field">
        <label for="#"><?php _e( 'Minimum Contract Period', 'reepay-subscriptions-for-woocommerce' ); ?></label>
        <span>
            <?php echo ! empty( $_reepay_subscription_contract_periods ) ? esc_attr( $_reepay_subscription_contract_periods ) : 0 ?>
            <?php echo wc_help_tip( __( 'Periods are relative to the billing frequency. If you have chosen to bill every month, a period is one month.', 'reepay-subscriptions-for-woocommerce' ) ); ?>
        </span>
    </p>
    <p class="form-field">
        <label for="#"></label>
        <span>
            <?php
            if ( $_reepay_subscription_contract_periods_full === 'true' ) {
	            _e( 'When the first period starts', 'reepay-subscriptions-for-woocommerce' );
            } else {
	            _e( 'When the subscription is created', 'reepay-subscriptions-for-woocommerce' );
            }
            ?>
        </span>
    </p>
</div>

<div class="options_group show_if_reepay_subscription">
    <p class="form-field">
        <label for="#"><?php _e( 'Notice period', 'reepay-subscriptions-for-woocommerce' ); ?></label>
        <span>
            <?php echo ! empty( $_reepay_subscription_notice_period ) ? esc_attr( $_reepay_subscription_notice_period ) : 0 ?>
            <?php echo wc_help_tip( __( 'Periods are relative to the billing frequency. If you have chosen to bill every month, a period is one month.', 'reepay-subscriptions-for-woocommerce' ) ); ?>
        </span>
    </p>
    <p class="form-field">
        <label for="#"></label>
        <span>
	        <?php
	        if ( $_reepay_subscription_notice_period_start === 'true' ) {
		        _e( 'When the current cancelled period ends', 'reepay-subscriptions-for-woocommerce' );
	        } else {
		        _e( 'Immediately after cancellation', 'reepay-subscriptions-for-woocommerce' );
	        }
	        ?>
        </span>
    </p>
</div>

<div class="options_group show_if_reepay_subscription billing_cycles_block">
    <p class="form-field">
        <label for="#"><?php _e( 'Billing Cycles', 'reepay-subscriptions-for-woocommerce' ); ?></label>
        <span>
	        <?php
	        if ( $_reepay_subscription_billing_cycles === 'true' ) {
		        _e( 'Auto Renew until cancelled', 'reepay-subscriptions-for-woocommerce' );
	        } else {
		        _e( 'Immediately after cancellation', 'reepay-subscriptions-for-woocommerce' );
	        }
	        ?>
        </span>
    </p>
    <p class="form-field">
        <label for="#"><?php _e( 'Number of billing cycles', 'reepay-subscriptions-for-woocommerce' ); ?></label>
        <span><?php echo ! empty( $_reepay_subscription_billing_cycles_period ) ? esc_attr( $_reepay_subscription_billing_cycles_period ) : 0 ?></span>
    </p>
</div>

<?php if ( ! empty( $_reepay_subscription_trial ) ) : ?>
    <div class="options_group reepay_subscription_trial show_if_reepay_subscription">
        <p class="form-field">
            <label for="#"><?php _e( 'Trial', 'reepay-subscriptions-for-woocommerce' ); ?></label>
            <span><?php echo WC_Reepay_Subscription_Plan_Simple::$trial[ $_reepay_subscription_trial['type'] ?? '' ] ?? '' ?></span>
        </p>

		<?php if ( ! empty( $_reepay_subscription_trial['type'] ) ) : ?>
            <p class="form-field">
                <label for="#"><?php _e( 'Trial Length', 'reepay-subscriptions-for-woocommerce' ); ?></label>
                <span><?php echo ! empty( $_reepay_subscription_trial['length'] ) ? esc_attr( $_reepay_subscription_trial['length'] ) : 0 ?></span>
                <span><?php echo ucwords( $_reepay_subscription_trial['unit'] ) ?></span>
            </p>
            <p class="form-field">
                <label for="#"><?php _e( 'Optional Trial Reminder Schedule', 'reepay-subscriptions-for-woocommerce' ); ?></label>
                <span><?php echo ! empty( $_reepay_subscription_trial['reminder'] ) ? esc_attr( $_reepay_subscription_trial['reminder'] ) : 0 ?></span>
                <span><?php _e( 'Days' ) ?></span>
            </p>
		<?php endif; ?>
    </div>
<?php endif; ?>

<?php if ( ! empty( $_reepay_subscription_fee ) ) : ?>
    <div class="options_group reepay_subscription_fee show_if_reepay_subscription">
        <p class="form-field">
            <label for="#"><?php _e( 'Include setup fee', 'reepay-subscriptions-for-woocommerce' ); ?></label>
            <span><?php echo ! empty( $_reepay_subscription_fee['enabled'] ) && $_reepay_subscription_fee['enabled'] == 'yes' ? 'active' : 'disabled' ?></span>
        </p>
		<?php if ( ! empty( $_reepay_subscription_fee['enabled'] ) ) : ?>
            <p class="form-field">
                <label for="#"><?php _e( 'Setup Fee (kr)', 'reepay-subscriptions-for-woocommerce' ); ?></label>
                <span><?php echo ! empty( $_reepay_subscription_fee['amount'] ) ? esc_attr( $_reepay_subscription_fee['amount'] ) : 0 ?></span>
                <br>
                <span><?php echo ! empty( $_reepay_subscription_fee['text'] ) ? esc_attr( $_reepay_subscription_fee['text'] ) : '' ?></span>
                <br>
                <span>
                    <?php if ( 'first' === $_reepay_subscription_fee['handling'] ) {
	                    _e( 'Include setup fee as order line on the first scheduled invoice', 'reepay-subscriptions-for-woocommerce' );
                    } elseif ( 'separate' === $_reepay_subscription_fee['handling'] ) {
	                    _e( 'Create a separate invoice for the setup fee', 'reepay-subscriptions-for-woocommerce' );
                    } elseif ( 'separate_conditional' === $_reepay_subscription_fee['handling'] ) {
	                    _e( 'Create a separate invoice for the setup fee, if the first invoice is not created in conjunction with the creation', 'reepay-subscriptions-for-woocommerce' );
                    } ?>
                </span>
            </p>
		<?php endif; ?>
    </div>
<?php endif; ?>

<div class="options_group show_if_reepay_subscription">
	<?php
	if ( ! empty( $product_object ) && function_exists( 'woocommerce_wp_select' ) ) {
		woocommerce_wp_select( [
			'id'                => '_tax_status',
			'value'             => $product_object->get_tax_status( 'edit' ),
			'label'             => __( 'Tax status', 'reepay-subscriptions-for-woocommerce' ),
			'options'           => [
				'taxable'  => __( 'Taxable', 'reepay-subscriptions-for-woocommerce' ),
				'shipping' => __( 'Shipping only', 'reepay-subscriptions-for-woocommerce' ),
				'none'     => _x( 'None', 'Tax status', 'reepay-subscriptions-for-woocommerce' ),
			],
//			'desc_tip'          => 'true',
//			'description'       => __( 'Define whether or not the entire product is taxable, or just the cost of shipping it.', 'reepay-subscriptions-for-woocommerce' ),
			'custom_attributes' => empty( $disabled ) ? [] : [ 'disabled' => 'disabled' ],
		] );

		woocommerce_wp_select( [
			'id'                => '_tax_class',
			'value'             => $product_object->get_tax_class( 'edit' ),
			'label'             => __( 'Tax class', 'reepay-subscriptions-for-woocommerce' ),
			'options'           => wc_get_product_tax_class_options(),
//			'desc_tip'          => 'true',
//			'description'       => __( 'Choose a tax class for this product. Tax classes are used to apply different tax rates specific to certain types of product.', 'reepay-subscriptions-for-woocommerce' ),
			'custom_attributes' => empty( $disabled ) ? [] : [ 'disabled' => 'disabled' ],
		] );
	}
	do_action( 'woocommerce_product_options_tax' );
	?>
</div>