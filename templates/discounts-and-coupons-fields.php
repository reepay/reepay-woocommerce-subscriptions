<p class="form-field">
    <label for="_discounts_apply_to"><?php esc_html_e( 'Apply to', WC_Reepay_Subscriptions::$domain ); ?></label>
    <input type="radio" id="_reepay_discount_apply_to" name="_reepay_discount_apply_to" value="all" <?php checked( 'all', $meta['_reepay_discount_apply_to'][0] ); ?>/> &nbsp<?php esc_html_e( 'All', WC_Reepay_Subscriptions::$domain ); ?>
</p>
<p class="form-field">
    <input type="radio" id="_reepay_discount_apply_to" name="_reepay_discount_apply_to" value="custom" <?php checked( 'custom', $meta['_reepay_discount_apply_to'][0] ); ?>/> &nbsp<?php esc_html_e( 'Custom', WC_Reepay_Subscriptions::$domain ); ?>
</p>
<p class="form-field active_if_apply_to_custom" style="margin-left: 20px">
    <?php foreach(array_chunk(WC_Reepay_Discounts_And_Coupons::$apply_to, 2, true) as $chunk): ?>
        <?php foreach ($chunk as $value => $label): ?>
                <input disabled type="checkbox" id="<?= $value ?>" name="_reepay_discount_apply_to_items[]" value="<?= $value ?>" <?php checked( in_array($value, $meta['_reepay_discount_apply_to_items'][0]), true ); ?>/> &nbsp<?php esc_html_e( $label, WC_Reepay_Subscriptions::$domain ); ?>
            &nbsp
        <?php endforeach; ?>
        <br>
    <?php endforeach; ?>
</p>
<p class="form-field">
    <label for="_reepay_discount_all_plans"><?php esc_html_e( 'Availability', WC_Reepay_Subscriptions::$domain ); ?></label>
    <input type="radio" id="_reepay_discount_all_plans" name="_reepay_discount_all_plans" value="1" <?php checked( '1', $meta['_reepay_discount_all_plans'][0] ); ?>/> &nbsp<?php esc_html_e( 'All plans', WC_Reepay_Subscriptions::$domain ); ?>
</p>
<p class="form-field">
    <input type="radio" id="_reepay_discount_all_plans" name="_reepay_discount_all_plans" value="0" <?php checked( '0', $meta['_reepay_discount_all_plans'][0] ); ?>/> &nbsp<?php esc_html_e( 'Selected plans', WC_Reepay_Subscriptions::$domain ); ?>
</p>
<p class="form-field show_if_all_plans">
    <?php if (!empty($plans)): ?>
        <select name="_reepay_discount_eligible_plans[]" id="_reepay_discount_eligible_plans" multiple="multiple">
            <?php foreach ($plans as $value => $label): ?>
                <option value="<?= $value ?>"><?= $label ?></option>
            <?php endforeach; ?>
        </select>
    <?php endif; ?>
    <?php if (empty($plans)): ?>
        No plans found
    <?php endif; ?>
</p>