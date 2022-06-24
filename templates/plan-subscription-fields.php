<?php

$variable = $variable ?? false;

?>
<div class="options_group reepay_subscription_choose show_if_reepay_subscription">
    <p class="form-field choose-fields <?= $variable ? 'form-row' : '' ?> ">
        <label for="_subscription_price">
            <?php esc_html_e( 'Creation type', $domain ); ?>
        </label>
        <?php esc_html_e( 'Create new plan', $domain ); ?> &nbsp
        <input type="radio" id="_reepay_subscription_choose" name="_reepay_subscription_choose<?= $variable ? '['.$loop.']' : '' ?>" value="new" <?php checked( 'new', $_reepay_subscription_choose, true ); ?>>
        &nbsp&nbsp<?php esc_html_e( 'Choose existing plan', $domain ); ?> &nbsp
        <input type="radio" id="_reepay_subscription_choose" name="_reepay_subscription_choose<?= $variable ? '['.$loop.']' : '' ?>" value="exist" <?php checked( 'exist', $_reepay_subscription_choose, true ); ?>>
    </p>
</div>

<div class="reepay_subscription_choose_exist">
    <div class="options_group reepay_subscription_choose_exist show_if_reepay_subscription">
        <p class="form-field exist-fields <?= $variable ? 'dimensions_field form-row' : '' ?> ">
            <?php if(!empty($plans_list)):?>
            <select id="_subscription_choose_exist"  name="_reepay_choose_exist<?= $variable ? '['.$loop.']' : '' ?>" class="wc_input_subscription_period_interval">
                <option value=""><?php esc_html_e( 'Select plan', $domain ); ?></option>
                <?php foreach ($plans_list as $plan):?>
                    <option value="<?=$plan['handle']?>" <?php selected( $plan['handle'], $_reepay_choose_exist, true ) ?>><?=$plan['name']?></option>
                <?php endforeach; ?>
            </select>
            <?php else: ?>
                <?php esc_html_e( 'Plans list is empty', $domain ); ?>
            <?php endif; ?>
        </p>
    </div>
    <?php if(!empty($settings_exist)):?>
        <div class="reepay_subscription_settings_exist">
            <?= $settings_exist ?>
        </div>
    <?php endif; ?>
</div>

<div class="reepay_subscription_settings">
    <?= $settings ?>
</div>

<div class="options_group show_if_reepay_simple_subscriptions clear"></div>