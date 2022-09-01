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
        <div class="show_if_use_existing_coupon">
            <p class="form-field">
                <select name="_reepay_discount_use_existing_coupon_id" id="coupon_id" class="short">
                    <option value="">Select coupon</option>
                    <?php foreach ($coupons as $coupon): ?>
                        <option value="<?= esc_attr($coupon['handle']) ?>"><?= esc_attr($coupon['code']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (empty($coupons)):
                    _e('No coupons found', reepay_s()->settings('domain'));
                endif; ?>
            </p>
        </div>
    <?php endif; ?>
    <div class="hide_if_use_existing_coupon">
        <?php
        wc_get_template(
            'discounts-and-coupons-fields-data.php',
            array(
                'meta' => $meta,
                'plans' => $plans,
                'is_update' => false,
                'domain' => reepay_s()->settings( 'domain' )
            ),
            '',
            reepay_s()->settings('plugin_path').'templates/'
        );
        ?>
    </div>
    <div class="reepay_coupon_settings_exist">

    </div>
</div>