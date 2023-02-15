<?php
/** @var Bool $is_update */


?>

<!-- Name -->
<p class="form-field">
    <label for="_reepay_discount_name"><?php _e( 'Name', 'reepay-subscriptions-for-woocommerce' ); ?></label>
    <span><?php echo $meta['_reepay_discount_name'][0] ? esc_attr( $meta['_reepay_discount_name'][0] ) : esc_attr( $meta['_reepay_coupon_handle'][0] ) ?></span>

</p>
<!--End Name -->
<!--Availability-->
<p class="form-field">
    <label for="_reepay_discount_all_plans"><?php _e( 'Availability', 'reepay-subscriptions-for-woocommerce' ); ?></label>
	<?php if ( $meta['_reepay_discount_all_plans'][0] == '1' ): ?>
        <span><?php _e( 'All plans', 'reepay-subscriptions-for-woocommerce' ); ?></span>
	<?php else: ?>
        <span><?php _e( 'Selected plans', 'reepay-subscriptions-for-woocommerce' ); ?></span>
	<?php endif; ?>
</p>

<?php if ( $meta['_reepay_discount_all_plans'][0] == '0' ): ?>
    <p class="form-field">
		<?php if ( ! empty( $plans ) ): ?>
			<?php if ( ! $is_update ): ?>
				<?php _e( 'Select one or more plans', 'reepay-subscriptions-for-woocommerce' ); ?>
                <br>
			<?php endif; ?>
            <select name="_reepay_discount_eligible_plans[]" id="_reepay_discount_eligible_plans"
                    multiple="multiple" class="wc-enhanced-select short reepay-required"
                    required>
				<?php foreach ( $plans as $value => $label ): ?>
					<?php if ( $is_update && in_array( $value, $meta['_reepay_discount_eligible_plans'][0] ?? [] ) ): ?>
                        <option value="<?php echo esc_attr( $value ) ?>" <?php echo selected( in_array( $value, $meta['_reepay_discount_eligible_plans'][0] ?? [] ) ) ?>><?php echo esc_attr( $label ) ?></option>
					<?php elseif ( ! $is_update ): ?>
                        <option value="<?php echo esc_attr( $value ) ?>" <?php echo selected( in_array( $value, $meta['_reepay_discount_eligible_plans'][0] ?? [] ) ) ?>><?php echo esc_attr( $label ) ?></option>
					<?php endif; ?>
				<?php endforeach; ?>
            </select>
		<?php endif; ?>
		<?php if ( empty( $plans ) ): ?>
			<?php _e( 'No plans found', 'reepay-subscriptions-for-woocommerce' ); ?>
		<?php endif; ?>
    </p>
<?php endif; ?>

<!--End Availability to-->
