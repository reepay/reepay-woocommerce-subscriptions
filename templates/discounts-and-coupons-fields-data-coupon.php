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
<!--Availability-->
<p class="form-field">
    <label for="_reepay_discount_all_plans"><?php esc_html_e('Availability', reepay_s()->settings('domain')); ?></label>
    <input type="radio" id="_reepay_discount_all_plans" name="_reepay_discount_all_plans"
           class="reepay-required"
           value="1" <?php checked('1', esc_attr($meta['_reepay_discount_all_plans'][0] ?? '1')); ?>
                   <?= $is_update ? 'disabled="disabled"' : '' ?>
    />
    &nbsp<?php esc_html_e('All plans', reepay_s()->settings('domain')); ?>
</p>
<p class="form-field">
    <input type="radio" id="_reepay_discount_all_plans" name="_reepay_discount_all_plans"
           class="reepay-required"
           value="0" <?php checked('0', esc_attr($meta['_reepay_discount_all_plans'][0] ?? '')); ?>
                   <?= $is_update ? 'disabled="disabled"' : '' ?>
    />
    &nbsp<?php esc_html_e('Selected plans', reepay_s()->settings('domain')); ?>
</p>
<p class="form-field show_if_selected_plans">
    <?php if (!empty($plans)): ?>
        <?php esc_html_e('Select one or more plans', reepay_s()->settings('domain')); ?>
        <br>
        <select name="_reepay_discount_eligible_plans[]" id="_reepay_discount_eligible_plans"
                multiple="multiple" class="wc-enhanced-select short reepay-required"
                required>
            <?php foreach ($plans as $value => $label): ?>
                <?php if ($is_update && in_array($value, $meta['_reepay_discount_eligible_plans'][0] ?? [])): ?>
                    <option value="<?= esc_attr($value) ?>" <?= selected(in_array($value, $meta['_reepay_discount_eligible_plans'][0] ?? [])) ?>><?= esc_attr($label) ?></option>
                <?php elseif(!$is_update): ?>
                    <option value="<?= esc_attr($value) ?>" <?= selected(in_array($value, $meta['_reepay_discount_eligible_plans'][0] ?? [])) ?>><?= esc_attr($label) ?></option>
                <?php endif; ?>
            <?php endforeach; ?>
        </select>
    <?php endif; ?>
    <?php if (empty($plans)): ?>
        <?php esc_html_e('No plans found', reepay_s()->settings('domain')); ?>
    <?php endif; ?>
</p>
<!--End Availability to-->
