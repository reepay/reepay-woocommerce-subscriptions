<?php

$variable = $variable ?? false;

?>
<div class="options_group reepay_subscription_choose show_if_reepay_subscription">
    <p class="form-field choose-fields <?php echo $variable ? 'form-row' : '' ?> ">
        <label for="_subscription_price">
            <?php esc_html_e('Creation type', $domain); ?>
        </label>
        <?php esc_html_e('Create new plan', $domain); ?> &nbsp
        <input type="radio" id="_reepay_subscription_choose"
               name="_reepay_subscription_choose<?php echo $variable ? '[' . $loop . ']' : '' ?>"
               value="new" <?php checked('new', esc_attr($_reepay_subscription_choose), true); ?>>
        &nbsp&nbsp<?php esc_html_e('Choose existing plan', $domain); ?> &nbsp
        <input type="radio" id="_reepay_subscription_choose"
               name="_reepay_subscription_choose<?php echo $variable ? '[' . $loop . ']' : '' ?>"
               value="exist" <?php checked('exist', esc_attr($_reepay_subscription_choose), true); ?>>
    </p>
</div>

<div class="reepay_subscription_settings">
    <?php echo $settings ?>
</div>

<div class="reepay_subscription_choose_exist">
    <div class="options_group show_if_reepay_subscription">
        <p class="form-field exist-fields <?php echo $variable ? 'dimensions_field form-row' : '' ?> ">
            <label for="_subscription_price">
                <?php esc_html_e('Choose plan', $domain); ?>
            </label>
            <?php if (!empty($plans_list)): ?>
                <select id="_subscription_choose_exist"
                        name="_reepay_choose_exist<?php echo $variable ? '[' . $loop . ']' : '' ?>"
                        class="wc_input_subscription_period_interval">
                    <option value=""><?php esc_html_e('Select plan', $domain); ?></option>
                    <?php foreach ($plans_list as $plan): ?>
                        <option value="<?php esc_attr_e($plan['handle']) ?>" <?php echo $_reepay_subscription_choose == 'exist' ? selected($plan['handle'], $_reepay_choose_exist) : '' ?>><?php esc_attr_e($plan['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            <?php else: ?>
                <?php esc_html_e('Plans list is empty', $domain); ?>
            <?php endif; ?>
        </p>
    </div>
    <div class="reepay_subscription_settings_exist">
        <?php echo $settings_exist ?? '' ?>
    </div>
</div>

<div id="reepay_subscription_publish_btn"
     class="options_group reepay_subscription_publish_btn show_if_reepay_subscription">
    <p class="form-field">
        <input type="submit" name="save" id="reepay-publish" class="button button-primary button-large"
               value="<?php echo !$is_exist ? esc_html_e('Create plan', $domain) : esc_html_e('Update plan', $domain) ?>">
    </p>
</div>

<div class="options_group show_if_reepay_simple_subscriptions clear"></div>