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