<?php
/**
 * @var Bool $is_update
 * @var Array $coupons
 * @var Array $meta
 * @var Array $plans
 */
?>
<div class="show_if_reepay hidden">
	<?php if ( ! $is_update ): ?>
        <p class="form-field" style="display: none">
            <label for="use_existing_coupon"><?php _e( 'Coupon creation type', 'reepay-subscriptions-for-woocommerce' ); ?></label>
			<?php _e( 'Create new coupon', 'reepay-subscriptions-for-woocommerce' ); ?> &nbsp
            <input type="radio"
                   id="use_existing_coupon"
                   name="use_existing_coupon"
                   value="false"/>
            &nbsp&nbsp
			<?php _e( 'Use existing coupon', 'reepay-subscriptions-for-woocommerce' ); ?> &nbsp
            <input
                    type="radio" id="use_existing_coupon" checked
                    name="use_existing_coupon"
                    value="true"/>
        </p>
        <div class="show_if_use_existing_coupon">
            <p class="form-field" style="display: flex">
                <select name="_reepay_discount_use_existing_coupon_id" id="coupon_id" class="short">
                    <option value=""><?php _e( 'Select coupon', 'reepay-subscriptions-for-woocommerce' ); ?></option>
					<?php foreach ( $coupons as $coupon ): ?>
                        <option value="<?php echo esc_attr( $coupon['handle'] ) ?>"><?php echo esc_attr( $coupon['name'] ) ?></option>
					<?php endforeach; ?>
                </select>
                <button class="button button-primary button-large js-refresh-coupons-list" style="margin-left: 5px;">
					<?php _e( 'Refresh list', 'reepay-subscriptions-for-woocommerce' ) ?>
                </button>
                <a class="button button-primary button-large"
                   style="margin-left: 5px;"
                   href="https://app.reepay.com/#/rp/config/coupons/create"
                   target="_blank">
					<?php
					_e( 'Create new coupon', 'reepay-subscriptions-for-woocommerce' ) ?>
                </a>
				<?php if ( empty( $coupons ) ):
					_e( 'No coupons found', 'reepay-subscriptions-for-woocommerce' );
				endif; ?>
            </p>
        </div>
	<?php endif; ?>
    <div class="hide_if_use_existing_coupon reepay_coupon_new">
		<?php
		wc_get_template(
			'discounts-and-coupons-fields-data-coupon.php',
			array(
				'meta'      => $meta,
				'plans'     => $plans,
				'is_update' => $is_update,
			),
			'',
			reepay_s()->settings( 'plugin_path' ) . 'templates/'
		);
		?>
		<?php if ( ! $is_update ): ?>
            <p class="form-field">
                <label for="use_existing_discount"><?php _e( 'Discount creation type', 'reepay-subscriptions-for-woocommerce' ); ?></label>
				<?php _e( 'Create new discount', 'reepay-subscriptions-for-woocommerce' ); ?> &nbsp
                <input type="radio"
                       id="use_existing_discount"
                       name="use_existing_discount"
                       value="false" checked/>
                &nbsp&nbsp
				<?php _e( 'Use existing discount', 'reepay-subscriptions-for-woocommerce' ); ?> &nbsp
                <input
                        type="radio" id="use_existing_discount"
                        name="use_existing_discount"
                        value="true"/>
            </p>
            <div class="show_if_use_existing_discount">
                <p class="form-field">
					<?php if ( ! empty( $discounts ) ): ?>
                        <select name="_reepay_discount_use_existing_discount_id" id="discount_id" class="short">
                            <option value=""><?php _e( 'Select discount', 'reepay-subscriptions-for-woocommerce' ); ?></option>
							<?php foreach ( $discounts as $discount ): ?>
                                <option value="<?php echo esc_attr( $discount['handle'] ) ?>"><?php echo esc_attr( $discount['name'] ) ?></option>
							<?php endforeach; ?>
                        </select>
					<?php endif; ?>
					<?php if ( empty( $discounts ) ):
						_e( 'No discounts found', 'reepay-subscriptions-for-woocommerce' );
					endif; ?>
                </p>
            </div>
		<?php endif; ?>
		<?php
		wc_get_template(
			'discounts-and-coupons-fields-data-discount.php',
			array(
				'meta'      => $meta,
				'plans'     => $plans,
				'is_update' => $is_update,
			),
			'',
			reepay_s()->settings( 'plugin_path' ) . 'templates/'
		);
		?>
    </div>
    <div class="show_if_use_existing_coupon reepay_coupon_settings_exist">

    </div>
</div>
