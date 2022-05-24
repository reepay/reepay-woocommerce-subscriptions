<div class="show_if_reepay hidden">
    <p class="form-field">
        <?php esc_html_e('Create new coupon', WC_Reepay_Subscriptions::$domain); ?> &nbsp<input type="radio"
                                                                                                id="use_existing_coupon"
                                                                                                name="use_existing_coupon"
                                                                                                value="false" <?php checked('false', $meta['use_existing_coupon'][0] ?? 'false', true); ?>/>
        &nbsp&nbsp <?php esc_html_e('Use existing coupon', WC_Reepay_Subscriptions::$domain); ?> &nbsp<input
                type="radio" id="use_existing_coupon"
                name="use_existing_coupon"
                value="true" <?php checked('true', $meta['use_existing_coupon'][0] ?? 'false', true); ?>/>
    </p>
    <p class="form-field show_if_use_existing_coupon">
        <select name="coupon_id" id="coupon_id" class="short">
            <?php foreach ($coupons as $coupon): ?>
                <option value="<?= $coupon['handle'] ?>"><?= $coupon['code'] ?></option>
            <?php endforeach; ?>
        </select>
        <?php if (empty($plans)): ?>
            No plans found
        <?php endif; ?>
    </p>
    <p class="form-field">
        <label for="_discounts_apply_to"><?php esc_html_e( 'Apply to', WC_Reepay_Subscriptions::$domain ); ?></label>
        <input type="radio" id="_reepay_discount_apply_to" name="_reepay_discount_apply_to" value="all" <?php checked( 'all', $meta['_reepay_discount_apply_to'][0] ?? 'all' ); ?>/> &nbsp<?php esc_html_e( 'All', WC_Reepay_Subscriptions::$domain ); ?>
    </p>
    <p class="form-field">
        <input type="radio" id="_reepay_discount_apply_to" name="_reepay_discount_apply_to" value="custom" <?php checked( 'custom', $meta['_reepay_discount_apply_to'][0] ?? 'all'); ?>/> &nbsp<?php esc_html_e( 'Custom', WC_Reepay_Subscriptions::$domain ); ?>
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
                    <option value="<?= $value ?>"><?= $label ?></option>
                <?php endforeach; ?>
            </select>
        <?php endif; ?>
        <?php if (empty($plans)): ?>
            No plans found
        <?php endif; ?>
    </p>
</div>