<?php
/** @var Bool $is_update */
?>

<!-- Amount -->
<p class="form-field">
    <label for="_reepay_discount_amount"><?php echo esc_html( 'Amount', 'reepay-subscriptions' ); ?></label>
    <input
            type="number"
            id="_reepay_discount_amount"
            name="_reepay_discount_amount"
            value="<?php echo esc_attr( $meta['_reepay_discount_amount'][0] ?? '0' ) ?>"
            class="reepay-required"
		<?php echo $is_update ? 'disabled="disabled"' : '' ?>
            required
    />
</p>
<!-- End Amount -->

<!--Discount type-->
<p class="form-field">
    <label for="_reepay_discount_type"><?php echo esc_html( 'Discount Type', 'reepay-subscriptions' ); ?></label>
    <input
            type="radio"
            id="_reepay_discount_type"
            name="_reepay_discount_type"
            class="reepay-required"
            required
		<?php echo $is_update ? 'disabled="disabled"' : '' ?>
            value="reepay_fixed_product" <?php checked( 'reepay_fixed_product', esc_attr( $meta['_reepay_discount_type'][0] ?? 'reepay_fixed_product' ) ); ?> />
    &nbsp<?php echo esc_html( 'Fixed amount', 'reepay-subscriptions' ); ?>
</p>
<p class="form-field">
    <input
            type="radio"
            id="_reepay_discount_type"
            name="_reepay_discount_type"
            class="reepay-required"
		<?php echo $is_update ? 'disabled="disabled"' : '' ?>
            value="reepay_percentage" <?php checked( 'reepay_percentage', $meta['_reepay_discount_type'][0] ?? '' ); ?> />
    &nbsp<?php echo esc_html( 'Percentage', 'reepay-subscriptions' ); ?>
</p>
<!--End Discount type-->

<!--Apply to-->
<p class="form-field">
    <label for="_reepay_discount_apply_to"><?php echo esc_html( 'Apply to', 'reepay-subscriptions' ); ?></label>
    <input
            type="radio"
            id="_reepay_discount_apply_to"
            name="_reepay_discount_apply_to"
		<?php echo $is_update ? 'disabled="disabled"' : '' ?>
            class="reepay-required"
            value="all" <?php checked( 'all', esc_attr( $meta['_reepay_discount_apply_to'][0] ?? 'all' ) ); ?> />
    &nbsp<?php echo esc_html( 'All', 'reepay-subscriptions' ); ?>
</p>
<p class="form-field">
    <input
            type="radio"
            id="_reepay_discount_apply_to"
            name="_reepay_discount_apply_to"
            class="reepay-required"
		<?php echo $is_update ? 'disabled="disabled"' : '' ?>
            value="custom" <?php checked( 'custom', $meta['_reepay_discount_apply_to'][0] ?? 'all' ); ?> />
    &nbsp<?php echo esc_html( 'Custom', 'reepay-subscriptions' ); ?>
</p>
<p class="form-field active_if_apply_to_custom" style="margin-left: 20px">
	<?php foreach ( array_chunk( WC_Reepay_Discounts_And_Coupons::$apply_to, 2, true ) as $chunk ): ?>
		<?php foreach ( $chunk as $value => $label ): ?>
            <input disabled type="checkbox" id="<?php echo esc_attr( $value ) ?>"
                   name="_reepay_discount_apply_to_items[]"
                   required
				<?php echo $is_update ? 'disabled="disabled"' : '' ?>
                   value="<?php echo esc_attr( $value ) ?>" <?php checked( in_array( $value, $meta['_reepay_discount_apply_to_items'][0] ?? [] ), true ); ?>/> &nbsp<?php echo esc_html( $label, 'reepay-subscriptions' ); ?>
            &nbsp
		<?php endforeach; ?>
        <br>
	<?php endforeach; ?>
</p>
<!--End Apply to-->


<!--Duration-->
<p class="form-field">
    <label for="_reepay_discount_duration"><?php echo esc_html( 'Duration', 'reepay-subscriptions' ); ?></label>
    <input
            type="radio"
            id="_reepay_discount_duration"
            name="_reepay_discount_duration"
            class="reepay-required"
		<?php echo $is_update ? 'disabled="disabled"' : '' ?>
            value="forever" <?php checked( 'forever', esc_attr( $meta['_reepay_discount_duration'][0] ?? 'forever' ) ); ?> />
    &nbsp<?php echo esc_html( 'Forever', 'reepay-subscriptions' ); ?>
</p>
<p class="form-field">
    <input
            type="radio"
            id="_reepay_discount_duration"
            name="_reepay_discount_duration"
            class="reepay-required"
		<?php echo $is_update ? 'disabled="disabled"' : '' ?>
            value="fixed_number" <?php checked( 'fixed_number', $meta['_reepay_discount_duration'][0] ?? '' ); ?> />
    &nbsp<?php echo esc_html( 'Fixed Number', 'reepay-subscriptions' ); ?>
</p>
<p class="form-field show_if_fixed_number">
    <label for="_reepay_discount_fixed_count"><?php echo esc_html( 'Times', 'reepay-subscriptions' ); ?></label>
    <input
            type="number"
            min="1"
            id="_reepay_discount_fixed_count"
            name="_reepay_discount_fixed_count"
            class="reepay-required"
		<?php echo $is_update ? 'disabled="disabled"' : '' ?>
            value="<?php echo esc_attr( $meta['_reepay_discount_fixed_count'][0] ?? '1' ) ?>"
    />
</p>
<p class="form-field">
    <input
            type="radio"
            id="_reepay_discount_duration"
            name="_reepay_discount_duration"
		<?php echo $is_update ? 'disabled="disabled"' : '' ?>
            class="reepay-required"
            value="limited_time"
		<?php checked( 'limited_time', $meta['_reepay_discount_duration'][0] ?? '' ); ?>
		<?php checked( 'limited_duration', $meta['_reepay_discount_duration'][0] ?? '' ); ?>
    />
    &nbsp<?php echo esc_html( 'Limited Time', 'reepay-subscriptions' ); ?>
</p>
<p class="form-field show_if_limited_time">
    <label for="_reepay_discount_fixed_period"><?php echo esc_html( 'Period Length', 'reepay-subscriptions' ); ?></label>
    <input
            type="number"
            min="1"
            id="_reepay_discount_fixed_period"
            name="_reepay_discount_fixed_period"
		<?php echo $is_update ? 'disabled="disabled"' : '' ?>
            class="reepay-required"
            value="<?php echo esc_attr( $meta['_reepay_discount_fixed_period'][0] ?? '1' ) ?>"
    />
</p>
<p class="form-field show_if_limited_time">
    <label for="_reepay_discount_fixed_period_unit"><?php echo esc_html( 'Unit', 'reepay-subscriptions' ); ?></label>
    <select id="_reepay_discount_fixed_period_unit" name="_reepay_discount_fixed_period_unit" id="coupon_id"
            class="short"
		<?php echo $is_update ? 'disabled="disabled"' : '' ?>>
        <option value="days">Days</option>
        <option value="months">Months</option>
    </select>
</p>
<!--End Duration-->