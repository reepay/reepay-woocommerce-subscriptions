<div class="show_if_reepay hidden">
    <?php if (!$is_update): ?>
        <p class="form-field">
            <?php esc_html_e('Create new coupon', reepay_s()->settings('domain')); ?> &nbsp
            <input type="radio"
                   id="use_existing_coupon"
                   name="use_existing_coupon"
                   value="false" checked/>
            &nbsp&nbsp
            <?php esc_html_e('Use existing coupon', reepay_s()->settings('domain')); ?> &nbsp
            <input
                    type="radio" id="use_existing_coupon"
                    name="use_existing_coupon"
                    value="true"/>
        </p>
        <p class="form-field show_if_use_existing_coupon">
            <select name="_reepay_discount_use_existing_coupon_id" id="coupon_id" class="short">
                <?php foreach ($coupons as $coupon): ?>
                    <option value="<?= esc_attr($coupon['handle']) ?>"><?= esc_attr($coupon['code']) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (empty($coupons)):
                _e('No coupons found', reepay_s()->settings('domain'));
            endif; ?>
        </p>
    <?php endif; ?>
    <div class="hide_if_use_existing_coupon">
        <p class="form-field">
            <label for="_reepay_discount_name"><?php esc_html_e('Name', reepay_s()->settings('domain')); ?></label>
            <input
                    type="text"
                    id="_reepay_discount_name"
                    name="_reepay_discount_name"
                    value="<?= esc_attr(!empty($meta['_reepay_discount_name'][0]) ?? '') ?>"
                    class="reepay-required"
                    required
            />
        </p>

        <p class="form-field">
            <label for="_reepay_discount_apply_to"><?php esc_html_e('Apply to', reepay_s()->settings('domain')); ?></label>
            <input
                    type="radio"
                    id="_reepay_discount_apply_to"
                    name="_reepay_discount_apply_to"
                <?= $is_update ? 'disabled="disabled"' : '' ?>
                    value="all" <?php checked('all', esc_attr(!empty($meta['_reepay_discount_apply_to'][0]) ?? 'all')); ?> />
            &nbsp<?php esc_html_e('All', reepay_s()->settings('domain')); ?>
        </p>
        <p class="form-field">
            <input
                    type="radio"
                    id="_reepay_discount_apply_to"
                    name="_reepay_discount_apply_to"
                <?= $is_update ? 'disabled="disabled"' : '' ?>
                    value="custom" <?php checked('custom', !empty($meta['_reepay_discount_apply_to'][0]) ?? 'all'); ?> />
            &nbsp<?php esc_html_e('Custom', reepay_s()->settings('domain')); ?>
        </p>
        <p class="form-field active_if_apply_to_custom" style="margin-left: 20px">
            <?php foreach (array_chunk(WC_Reepay_Discounts_And_Coupons::$apply_to, 2, true) as $chunk): ?>
                <?php foreach ($chunk as $value => $label): ?>
                    <input disabled type="checkbox" id="<?= esc_attr($value) ?>"
                           name="_reepay_discount_apply_to_items[]"
                           value="<?= esc_attr($value) ?>" <?php checked(in_array($value, $meta['_reepay_discount_apply_to_items'][0] ?? []), true); ?>/> &nbsp<?php esc_html_e($label, reepay_s()->settings('domain')); ?>
                    &nbsp
                <?php endforeach; ?>
                <br>
            <?php endforeach; ?>
        </p>
        <p class="form-field">
            <label for="_reepay_discount_all_plans"><?php esc_html_e('Availability', reepay_s()->settings('domain')); ?></label>
            <input type="radio" id="_reepay_discount_all_plans" name="_reepay_discount_all_plans"
                   value="1" <?php checked('1', esc_attr(!empty($meta['_reepay_discount_all_plans'][0]) ?? '1')); ?>/>
            &nbsp<?php esc_html_e('All plans', reepay_s()->settings('domain')); ?>
        </p>
        <p class="form-field">
            <input type="radio" id="_reepay_discount_all_plans" name="_reepay_discount_all_plans"
                   value="0" <?php checked('0', esc_attr(!empty($meta['_reepay_discount_all_plans'][0]) ?? '1')); ?>/>
            &nbsp<?php esc_html_e('Selected plans', reepay_s()->settings('domain')); ?>
        </p>
        <p class="form-field show_if_selected_plans">
            <?php if (!empty($plans)): ?>
                <?php esc_html_e('Select one or more plans', reepay_s()->settings('domain')); ?>
                <br>
                <select name="_reepay_discount_eligible_plans[]" id="_reepay_discount_eligible_plans"
                        multiple="multiple" class="wc-enhanced-select short">
                    <?php foreach ($plans as $value => $label): ?>
                        <option value="<?= esc_attr($value) ?>" <?= selected(in_array($value, $meta['_reepay_discount_eligible_plans'][0]) ?? []) ?>><?= esc_attr($label) ?></option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>
            <?php if (empty($plans)): ?>
                <?php esc_html_e('No plans found', reepay_s()->settings('domain')); ?>
            <?php endif; ?>
        </p>
    </div>
</div>