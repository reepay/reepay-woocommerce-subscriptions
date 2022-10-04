<?php
/** @var Bool $is_update */
?>

<!-- Name -->
<p class="form-field">
    <label for="_reepay_discount_name"><?php echo __( 'Name', 'reepay-subscriptions' ); ?></label>
    <input
            type="text"
            id="_reepay_discount_name"
            name="_reepay_discount_name"
            value="<?php echo esc_attr( $meta['_reepay_discount_name'][0] ?? '' ) ?>"
            class="reepay-required"
            required
    />
</p>
<!--End Name -->
<!--Availability-->
<p class="form-field">
    <label for="_reepay_discount_all_plans"><?php echo __( 'Availability', 'reepay-subscriptions' ); ?></label>
    <input type="radio" id="_reepay_discount_all_plans" name="_reepay_discount_all_plans"
           class="reepay-required"
           value="1" <?php checked( '1', esc_attr( $meta['_reepay_discount_all_plans'][0] ?? '1' ) ); ?>
		<?php echo $is_update ? 'disabled="disabled"' : '' ?>
    />
    &nbsp<?php echo __( 'All plans', 'reepay-subscriptions' ); ?>
</p>
<p class="form-field">
    <input type="radio" id="_reepay_discount_all_plans" name="_reepay_discount_all_plans"
           class="reepay-required"
           value="0" <?php checked( '0', esc_attr( $meta['_reepay_discount_all_plans'][0] ?? '' ) ); ?>
		<?php echo $is_update ? 'disabled="disabled"' : '' ?>
    />
    &nbsp<?php echo __( 'Selected plans', 'reepay-subscriptions' ); ?>
</p>
<p class="form-field show_if_selected_plans">
	<?php if ( ! empty( $plans ) ): ?>
		<?php if ( ! $is_update ): ?>
			<?php echo __( 'Select one or more plans', 'reepay-subscriptions' ); ?>
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
		<?php echo __( 'No plans found', 'reepay-subscriptions' ); ?>
	<?php endif; ?>
</p>
<!--End Availability to-->
