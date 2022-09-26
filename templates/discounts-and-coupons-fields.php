<div class="show_if_reepay hidden">
	<?php if ( ! $is_update ): ?>
        <p class="form-field">
            <label for="use_existing_coupon"><?php esc_html_e( 'Coupon creation type', 'reepay-subscriptions' ); ?></label>
			<?php esc_html_e( 'Create new coupon', 'reepay-subscriptions' ); ?> &nbsp
            <input type="radio"
                   id="use_existing_coupon"
                   name="use_existing_coupon"
                   value="false" checked/>
            &nbsp&nbsp
			<?php esc_html_e( 'Use existing coupon', 'reepay-subscriptions' ); ?> &nbsp
            <input
                    type="radio" id="use_existing_coupon"
                    name="use_existing_coupon"
                    value="true"/>
        </p>
        <div class="show_if_use_existing_coupon">
            <p class="form-field">
                <select name="_reepay_discount_use_existing_coupon_id" id="coupon_id" class="short">
                    <option value="">Select coupon</option>
					<?php foreach ( $coupons as $coupon ): ?>
                        <option value="<?php esc_attr_e( $coupon['handle'] ) ?>"><?php esc_attr_e( $coupon['code'] ) ?></option>
					<?php endforeach; ?>
                </select>
				<?php if ( empty( $coupons ) ):
					_e( 'No coupons found', 'reepay-subscriptions' );
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
				'domain'    => 'reepay-subscriptions'
			),
			'',
			reepay_s()->settings( 'plugin_path' ) . 'templates/'
		);
		?>
		<?php if ( ! $is_update ): ?>
            <p class="form-field">
                <label for="_reepay_discount_all_plans"><?php esc_html_e( 'Discount creation type', 'reepay-subscriptions' ); ?></label>
				<?php esc_html_e( 'Create new discount', 'reepay-subscriptions' ); ?> &nbsp
                <input type="radio"
                       id="use_existing_discount"
                       name="use_existing_discount"
                       value="false" checked/>
                &nbsp&nbsp
				<?php esc_html_e( 'Use existing discount', 'reepay-subscriptions' ); ?> &nbsp
                <input
                        type="radio" id="use_existing_discount"
                        name="use_existing_discount"
                        value="true"/>
            </p>
            <div class="show_if_use_existing_discount">
                <p class="form-field">
					<?php if ( ! empty( $discounts ) ): ?>
                        <select name="_reepay_discount_use_existing_discount_id" id="discount_id" class="short">
                            <option value="">Select discount</option>
							<?php foreach ( $discounts as $discount ): ?>
                                <option value="<?php echo esc_attr( $discount['handle'] ) ?>"><?php echo esc_attr( $discount['name'] ) ?></option>
							<?php endforeach; ?>
                        </select>
					<?php endif; ?>
					<?php if ( empty( $discounts ) ):
						_e( 'No discounts found', 'reepay-subscriptions' );
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
				'domain'    => 'reepay-subscriptions'
			),
			'',
			reepay_s()->settings( 'plugin_path' ) . 'templates/'
		);
		?>
    </div>
    <div class="show_if_use_existing_coupon reepay_coupon_settings_exist">

    </div>
</div>