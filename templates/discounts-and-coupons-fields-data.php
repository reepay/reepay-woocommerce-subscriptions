<!-- Name -->
<p class="form-field">
    <label for="_reepay_discount_name"><?php esc_html_e('Name', reepay_s()->settings('domain')); ?></label>
    <input
            type="text"
            id="_reepay_discount_name"
            name="_reepay_discount_name"
            value="<?= esc_attr($meta['_reepay_discount_name'][0] ?? '') ?>"
            class="reepay-required"
            required
    />
</p>
<!--End Name -->

<!-- Amount -->
<p class="form-field">
    <label for="_reepay_discount_amount"><?php esc_html_e('Amount', reepay_s()->settings('domain')); ?></label>
    <input
            type="number"
            id="_reepay_discount_amount"
            name="_reepay_discount_amount"
            value="<?= esc_attr($meta['_reepay_discount_amount'][0] ?? '0') ?>"
            class="reepay-required"
            <?= $is_update ? 'disabled="disabled"' : '' ?>
            required
    />
</p>
<!-- End Amount -->

<!--Discount type-->
<p class="form-field">
    <label for="_reepay_discount_type"><?php esc_html_e('Discount Type', reepay_s()->settings('domain')); ?></label>
    <input
            type="radio"
            id="_reepay_discount_type"
            name="_reepay_discount_type"
            class="reepay-required"
            required
        <?= $is_update ? 'disabled="disabled"' : '' ?>
            value="reepay_fixed_product" <?php checked('reepay_fixed_product', esc_attr($meta['_reepay_discount_type'][0] ?? 'reepay_fixed_product')); ?> />
    &nbsp<?php esc_html_e('Fixed amount', reepay_s()->settings('domain')); ?>
</p>
<p class="form-field">
    <input
            type="radio"
            id="_reepay_discount_type"
            name="_reepay_discount_type"
        <?= $is_update ? 'disabled="disabled"' : '' ?>
            value="reepay_percentage" <?php checked('reepay_percentage', $meta['_reepay_discount_type'][0] ?? ''); ?> />
    &nbsp<?php esc_html_e('Percentage', reepay_s()->settings('domain')); ?>
</p>
<!--End Discount type-->

<!--Apply to-->
<p class="form-field">
    <label for="_reepay_discount_apply_to"><?php esc_html_e('Apply to', reepay_s()->settings('domain')); ?></label>
    <input
            type="radio"
            id="_reepay_discount_apply_to"
            name="_reepay_discount_apply_to"
        <?= $is_update ? 'disabled="disabled"' : '' ?>
            value="all" <?php checked('all', esc_attr($meta['_reepay_discount_apply_to'][0] ?? 'all')); ?> />
    &nbsp<?php esc_html_e('All', reepay_s()->settings('domain')); ?>
</p>
<p class="form-field">
    <input
            type="radio"
            id="_reepay_discount_apply_to"
            name="_reepay_discount_apply_to"
            <?= $is_update ? 'disabled="disabled"' : '' ?>
            value="custom" <?php checked('custom', $meta['_reepay_discount_apply_to'][0] ?? 'all'); ?> />
    &nbsp<?php esc_html_e('Custom', reepay_s()->settings('domain')); ?>
</p>
<p class="form-field active_if_apply_to_custom" style="margin-left: 20px">
    <?php foreach (array_chunk(WC_Reepay_Discounts_And_Coupons::$apply_to, 2, true) as $chunk): ?>
        <?php foreach ($chunk as $value => $label): ?>
            <input disabled type="checkbox" id="<?= esc_attr($value) ?>"
                   name="_reepay_discount_apply_to_items[]"
                   <?= $is_update ? 'disabled="disabled"' : '' ?>
                   value="<?= esc_attr($value) ?>" <?php checked(in_array($value, $meta['_reepay_discount_apply_to_items'][0] ?? []), true); ?>/> &nbsp<?php esc_html_e($label, reepay_s()->settings('domain')); ?>
            &nbsp
        <?php endforeach; ?>
        <br>
    <?php endforeach; ?>
</p>
<!--End Apply to-->


<!--Availability to-->
<p class="form-field">
    <label for="_reepay_discount_all_plans"><?php esc_html_e('Availability', reepay_s()->settings('domain')); ?></label>
    <input type="radio" id="_reepay_discount_all_plans" name="_reepay_discount_all_plans"
           value="1" <?php checked('1', esc_attr($meta['_reepay_discount_all_plans'][0] ?? '1')); ?>
                   <?= $is_update ? 'disabled="disabled"' : '' ?>
    />
    &nbsp<?php esc_html_e('All plans', reepay_s()->settings('domain')); ?>
</p>
<p class="form-field">
    <input type="radio" id="_reepay_discount_all_plans" name="_reepay_discount_all_plans"
           value="0" <?php checked('0', esc_attr($meta['_reepay_discount_all_plans'][0] ?? '1')); ?>
                   <?= $is_update ? 'disabled="disabled"' : '' ?>
    />
    &nbsp<?php esc_html_e('Selected plans', reepay_s()->settings('domain')); ?>
</p>
<p class="form-field show_if_selected_plans">
    <?php if (!empty($plans)): ?>
        <?php esc_html_e('Select one or more plans', reepay_s()->settings('domain')); ?>
        <br>
        <select name="_reepay_discount_eligible_plans[]" id="_reepay_discount_eligible_plans"
                multiple="multiple" class="wc-enhanced-select short">
            <?php foreach ($plans as $value => $label): ?>
                <option value="<?= esc_attr($value) ?>" <?= selected(in_array($value, $meta['_reepay_discount_eligible_plans'][0] ?? [])) ?>><?= esc_attr($label) ?></option>
            <?php endforeach; ?>
        </select>
    <?php endif; ?>
    <?php if (empty($plans)): ?>
        <?php esc_html_e('No plans found', reepay_s()->settings('domain')); ?>
    <?php endif; ?>
</p>
<!--End Availability to-->

<!--Duration-->
<p class="form-field">
    <label for="_reepay_discount_duration"><?php esc_html_e('Duration', reepay_s()->settings('domain')); ?></label>
    <input
            type="radio"
            id="_reepay_discount_duration"
            name="_reepay_discount_duration"
        <?= $is_update ? 'disabled="disabled"' : '' ?>
            value="forever" <?php checked('forever', esc_attr($meta['_reepay_discount_duration'][0] ?? 'forever')); ?> />
    &nbsp<?php esc_html_e('Forever', reepay_s()->settings('domain')); ?>
</p>
<p class="form-field">
    <input
            type="radio"
            id="_reepay_discount_duration"
            name="_reepay_discount_duration"
            <?= $is_update ? 'disabled="disabled"' : '' ?>
            value="fixed_number" <?php checked('fixed_number', $meta['_reepay_discount_duration'][0] ?? ''); ?> />
    &nbsp<?php esc_html_e('Fixed Number', reepay_s()->settings('domain')); ?>
</p>
<p class="form-field show_if_fixed_number">
    <label for="_reepay_discount_fixed_count"><?php esc_html_e('Times', reepay_s()->settings('domain')); ?></label>
    <input
            type="number"
            min="1"
            id="_reepay_discount_fixed_count"
            name="_reepay_discount_fixed_count"
            <?= $is_update ? 'disabled="disabled"' : '' ?>
            value="<?= esc_attr($meta['_reepay_discount_fixed_count'][0] ?? '1') ?>"
            <?= $is_update ? 'disabled="disabled"' : '' ?>
    />
</p>
<p class="form-field">
    <input
            type="radio"
            id="_reepay_discount_duration"
            name="_reepay_discount_duration"
        <?= $is_update ? 'disabled="disabled"' : '' ?>
            value="limited_time" <?php checked('limited_time', $meta['_reepay_discount_duration'][0] ?? ''); ?> />
    &nbsp<?php esc_html_e('Limited Time', reepay_s()->settings('domain')); ?>
</p>
<p class="form-field show_if_limited_time">
    <label for="_reepay_discount_fixed_period"><?php esc_html_e('Period Length', reepay_s()->settings('domain')); ?></label>
    <input
            type="number"
            min="1"
            id="_reepay_discount_fixed_period"
            name="_reepay_discount_fixed_period"
            <?= $is_update ? 'disabled="disabled"' : '' ?>
            value="<?= esc_attr($meta['_reepay_discount_fixed_period'][0] ?? '1') ?>"
    />
</p>
<p class="form-field show_if_limited_time">
    <label for="_reepay_discount_fixed_period_unit"><?php esc_html_e('Unit', reepay_s()->settings('domain')); ?></label>
    <select name="_reepay_discount_fixed_period_unit" id="coupon_id"
            class="short"
            <?= $is_update ? 'disabled="disabled"' : '' ?>>
        <option value="days">Days</option>
        <option value="months">Months</option>
    </select>
</p>
<!--End Duration-->