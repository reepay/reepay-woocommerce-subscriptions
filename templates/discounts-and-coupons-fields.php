<div class="show_if_reepay hidden">
    <?php if (!$is_update): ?>
        <p class="form-field">
            <?php esc_html_e('Create new coupon', WC_Reepay_Subscriptions::$domain); ?> &nbsp<input type="radio"
                                                                                                    id="use_existing_coupon"
                                                                                                    name="use_existing_coupon"
                                                                                                    value="false" checked/>
            &nbsp&nbsp <?php esc_html_e('Use existing coupon', WC_Reepay_Subscriptions::$domain); ?> &nbsp<input
                    type="radio" id="use_existing_coupon"
                    name="use_existing_coupon"
                    value="true"/>
        </p>
        <p class="form-field show_if_use_existing_coupon">
            <select name="_reepay_discount_use_existing_coupon_id" id="coupon_id" class="short">
                <?php foreach ($coupons as $coupon): ?>
                    <option value="<?= $coupon['handle'] ?>"><?= $coupon['code'] ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (empty($plans)): ?>
                No plans found
            <?php endif; ?>
        </p>
    <?php endif; ?>
    <div class="hide_if_use_existing_coupon">
        <p class="form-field">
            <label for="_reepay_discount_name"><?php esc_html_e( 'Name', WC_Reepay_Subscriptions::$domain ); ?></label>
            <input
                    type="text"
                    id="_reepay_discount_name"
                    name="_reepay_discount_name"
                    value="<?= $meta['_reepay_discount_name'][0] ?? ''?>"
                    required
            />
        </p>

        <p class="form-field">
            <label for="_reepay_discount_apply_to"><?php esc_html_e( 'Apply to', WC_Reepay_Subscriptions::$domain ); ?></label>
            <input
                    type="radio"
                    id="_reepay_discount_apply_to"
                    name="_reepay_discount_apply_to"
                <?= $is_update ? 'disabled="disabled"' : '' ?>
                    value="all" <?php checked( 'all', $meta['_reepay_discount_apply_to'][0] ?? 'all' ); ?> /> &nbsp<?php esc_html_e( 'All', WC_Reepay_Subscriptions::$domain ); ?>
        </p>
        <p class="form-field">
            <input
                    type="radio"
                    id="_reepay_discount_apply_to"
                    name="_reepay_discount_apply_to"
                <?= $is_update ? 'disabled="disabled"' : '' ?>
                    value="custom" <?php checked( 'custom', $meta['_reepay_discount_apply_to'][0] ?? 'all'); ?> /> &nbsp<?php esc_html_e( 'Custom', WC_Reepay_Subscriptions::$domain ); ?>
        </p>
        <p class="form-field active_if_apply_to_custom" style="margin-left: 20px">
            <?php foreach(array_chunk(WC_Reepay_Discounts_And_Coupons::$apply_to, 2, true) as $chunk): ?>
                <?php foreach ($chunk as $value => $label): ?>
                    <input disabled type="checkbox" id="<?= $value ?>" name="_reepay_discount_apply_to_items[]" value="<?= $value ?>" <?php checked( in_array($value, $meta['_reepay_discount_apply_to_items'][0] ?? []), true ); ?>/> &nbsp<?php esc_html_e( $label, WC_Reepay_Subscriptions::$domain ); ?>
                    &nbsp
                <?php endforeach; ?>
                <br>
            <?php endforeach; ?>
        </p>
        <p class="form-field">
            <label for="_reepay_discount_all_plans"><?php esc_html_e( 'Availability', WC_Reepay_Subscriptions::$domain ); ?></label>
            <input type="radio" id="_reepay_discount_all_plans" name="_reepay_discount_all_plans" value="1" <?php checked( '1', $meta['_reepay_discount_all_plans'][0] ?? '1' ); ?>/> &nbsp<?php esc_html_e( 'All plans', WC_Reepay_Subscriptions::$domain ); ?>
        </p>
        <p class="form-field">
            <input type="radio" id="_reepay_discount_all_plans" name="_reepay_discount_all_plans" value="0" <?php checked( '0', $meta['_reepay_discount_all_plans'][0] ?? '1' ); ?>/> &nbsp<?php esc_html_e( 'Selected plans', WC_Reepay_Subscriptions::$domain ); ?>
        </p>
        <p class="form-field show_if_selected_plans">
            <?php if (!empty($plans)): ?>
                Select one or more plans
                <br>
                <select name="_reepay_discount_eligible_plans[]" id="_reepay_discount_eligible_plans" multiple="multiple" class="wc-enhanced-select short">
                    <?php foreach ($plans as $value => $label): ?>
                        <option value="<?= $value ?>" <?= checked(in_array($value, $meta['_reepay_discount_eligible_plans'][0] ?? [])) ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>
            <?php if (empty($plans)): ?>
                No plans found
            <?php endif; ?>
        </p>
    </div>
</div>