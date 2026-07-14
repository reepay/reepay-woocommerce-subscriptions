<?php
/** @var Bool $is_update */
?>

<!--Discount application-->
<p class="form-field">
    <label for="_reepay_discount_apply_to"><?php _e( 'Discount application', 'reepay-subscriptions-for-woocommerce' ); ?></label>
    <?php
    $apply_to_value = $meta['_reepay_discount_apply_to'][0] ?? '';
    $apply_to_items = $meta['_reepay_discount_apply_to_items'][0] ?? [];
    if ( $apply_to_value === 'all' || empty( $apply_to_items ) ) {
        echo '<span>' . esc_html__( 'All', 'reepay-subscriptions-for-woocommerce' ) . '</span>';
    } else {
        // Use the class map when available (after reepay_subscriptions_init fires),
        // otherwise fall back to the inline map so the template is self-contained.
        $apply_to_map = WC_Reepay_Discounts_And_Coupons::$apply_to ?: [
            'setup_fee'       => __( 'Setup fee', 'reepay-subscriptions-for-woocommerce' ),
            'plan'            => __( 'Plan', 'reepay-subscriptions-for-woocommerce' ),
            'additional_cost' => __( 'Additional Costs', 'reepay-subscriptions-for-woocommerce' ),
            'ondemand'        => __( 'Instant Charges', 'reepay-subscriptions-for-woocommerce' ),
            'add_on'          => __( 'Add-on', 'reepay-subscriptions-for-woocommerce' ),
        ];
        $labels = array_map(
            fn( $v ) => $apply_to_map[ $v ] ?? $v,
            (array) $apply_to_items
        );
        echo '<span>' . esc_html( implode( ', ', $labels ) ) . '</span>';
    }
    ?>
</p>
<!--End Discount application-->

<!--Discount type-->
<p class="form-field">
    <label for="_reepay_discount_type"><?php _e( 'Discount Type', 'reepay-subscriptions-for-woocommerce' ); ?></label>
	<?php if ( $meta['_reepay_discount_type'][0] == 'reepay_fixed_product' ): ?>
        <span><?php _e( 'Fixed amount', 'reepay-subscriptions-for-woocommerce' ); ?></span>
	<?php else: ?>
        <span><?php _e( 'Percentage', 'reepay-subscriptions-for-woocommerce' ); ?></span>
	<?php endif; ?>
</p>
<!--End Discount type-->

<!-- Amount -->
<p class="form-field">
    <label for="_reepay_discount_amount"><?php _e( 'Size', 'reepay-subscriptions-for-woocommerce' ); ?></label>
    <span><?php echo esc_attr( $meta['_reepay_discount_amount'][0] ?? '0' ) ?></span>
</p>
<!-- End Amount -->

<!--Discount duration-->
<p class="form-field">
    <label for="_reepay_discount_duration"><?php _e( 'Discount duration', 'reepay-subscriptions-for-woocommerce' ); ?></label>
	<?php if ( $meta['_reepay_discount_duration'][0] == 'forever' ): ?>
        <span><?php _e( 'Unlimited', 'reepay-subscriptions-for-woocommerce' ); ?></span>
	<?php elseif ( $meta['_reepay_discount_duration'][0] == 'fixed_number' ): ?>
        <span><?php _e( 'Limited count', 'reepay-subscriptions-for-woocommerce' ); ?></span>
	<?php else: ?>
        <span><?php _e( 'Limited period', 'reepay-subscriptions-for-woocommerce' ); ?></span>
	<?php endif; ?>
</p>
<!--End Discount duration-->

<?php if ( ! empty( $meta['_reepay_coupon_max_redemptions'][0] ) ): ?>
    <p class="form-field">
        <label><?php _e( 'Coupon usage limit', 'reepay-subscriptions-for-woocommerce' ); ?></label>
        <span><?php echo esc_attr( $meta['_reepay_coupon_max_redemptions'][0] ) ?></span>
    </p>
<?php endif; ?>

<?php if ( ! empty( $meta['_reepay_coupon_valid_until'][0] ) ): ?>
    <p class="form-field">
        <label><?php _e( 'Coupon expiry date', 'reepay-subscriptions-for-woocommerce' ); ?></label>
        <span><?php
            $ts = strtotime( $meta['_reepay_coupon_valid_until'][0] );
            echo $ts ? esc_html( date_i18n( get_option( 'date_format' ), $ts ) ) : esc_html( $meta['_reepay_coupon_valid_until'][0] );
        ?></span>
    </p>
<?php endif; ?>
<input type="hidden" class="js-reepay-valid-until" value="<?php
    $raw = $meta['_reepay_coupon_valid_until'][0] ?? '';
    $ts  = $raw ? strtotime( $raw ) : 0;
    echo $ts ? esc_attr( date( 'Y-m-d', $ts ) ) : '';
?>">
<input type="hidden" class="js-reepay-max-redemptions" value="<?php
    echo esc_attr( $meta['_reepay_coupon_max_redemptions'][0] ?? '' );
?>">
<!--End Discount duration-->
