<?php
/** @var Bool $is_update */
?>

<!-- Amount -->
<p class="form-field">
    <label for="_reepay_discount_amount"><?php _e( 'Amount', 'reepay-subscriptions-for-woocommerce' ); ?></label>
    <span><?php echo esc_attr( $meta['_reepay_discount_amount'][0] ?? '0' ) ?></span>
</p>
<!-- End Amount -->

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

<!--Apply to-->
<p class="form-field">
    <label for="_reepay_discount_apply_to"><?php _e( 'Apply to', 'reepay-subscriptions-for-woocommerce' ); ?></label>
	<?php if ( $meta['_reepay_discount_apply_to'][0] == 'all' ): ?>
        <span><?php _e( 'All', 'reepay-subscriptions-for-woocommerce' ); ?></span>
	<?php else: ?>
        <span><?php _e( 'Custom', 'reepay-subscriptions-for-woocommerce' ); ?></span>
	<?php endif; ?>
</p>
<p class="form-field active_if_apply_to_custom" style="margin-left: 20px">
	<?php foreach ( array_chunk( WC_Reepay_Discounts_And_Coupons::$apply_to, 2, true ) as $chunk ): ?>
		<?php foreach ( $chunk as $value => $label ): ?>
            <input disabled type="checkbox" id="<?php echo esc_attr( $value ) ?>"
                   name="_reepay_discount_apply_to_items[]"
                   required
				<?php echo $is_update ? 'disabled="disabled"' : '' ?>
                   value="<?php echo esc_attr( $value ) ?>" <?php checked( in_array( $value, $meta['_reepay_discount_apply_to_items'][0] ?? [] ), true ); ?>/> &nbsp<?php esc_html_e( $label, 'reepay-subscriptions-for-woocommerce' ); ?>
            &nbsp
		<?php endforeach; ?>
        <br>
	<?php endforeach; ?>
</p>
<!--End Apply to-->


<!--Duration-->
<p class="form-field">
    <label for="_reepay_discount_duration"><?php _e( 'Duration', 'reepay-subscriptions-for-woocommerce' ); ?></label>
	<?php if ( $meta['_reepay_discount_duration'][0] == 'forever' ): ?>
        <span><?php _e( 'Forever', 'reepay-subscriptions-for-woocommerce' ); ?></span>
	<?php else: ?>
        <span><?php _e( 'Fixed number', 'reepay-subscriptions-for-woocommerce' ); ?></span>
	<?php endif; ?>
</p>

<?php if ( $meta['_reepay_discount_duration'][0] == 'fixed_number' ): ?>
    <p class="form-field">
        <label for="_reepay_discount_fixed_count"><?php _e( 'Times', 'reepay-subscriptions-for-woocommerce' ); ?></label>
        <span><?php echo esc_attr( $meta['_reepay_discount_fixed_count'][0] ?? '1' ) ?></span>
    </p>
<?php endif; ?>

<?php if ( $meta['_reepay_discount_duration'][0] == 'limited_time' || $meta['_reepay_discount_duration'][0] == 'limited_duration' ): ?>
    <p class="form-field">
        <label for="_reepay_discount_fixed_count"><?php _e( 'Limited Time', 'reepay-subscriptions-for-woocommerce' ); ?></label>
        <span><?php echo esc_attr( $meta['_reepay_discount_fixed_period'][0] ?? '1' ) ?></span>
        <span><?php echo esc_attr( $meta['_reepay_discount_fixed_period_unit'][0] ?? '1' ) ?></span>
    </p>
<?php endif; ?>
<!--End Duration-->
