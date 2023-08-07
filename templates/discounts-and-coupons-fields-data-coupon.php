<?php
/** @var Bool $is_update */


?>

<!-- Name -->
<p class="form-field">
    <label for="_reepay_discount_name"><?php
		_e( 'Name', 'reepay-subscriptions-for-woocommerce' ); ?></label>
    <span><?php
		echo $meta['_reepay_discount_name'][0] ? esc_attr( $meta['_reepay_discount_name'][0] ) : esc_attr( $meta['_reepay_coupon_handle'][0] ) ?></span>

</p>
<!--End Name -->
<!--Availability-->
<p class="form-field">
    <label for="_reepay_discount_all_plans"><?php
		_e( 'Availability', 'reepay-subscriptions-for-woocommerce' ); ?></label>
	<?php
	if ( $meta['_reepay_discount_all_plans'][0] == '1' ): ?>
        <span><?php
			_e( 'All plans', 'reepay-subscriptions-for-woocommerce' ); ?></span>
	<?php
	else: ?>
        <span><?php
			_e( 'Selected plans', 'reepay-subscriptions-for-woocommerce' ); ?></span>
	<?php
	endif; ?>
	<?php
	if ( $meta['_reepay_discount_all_plans'][0] == '0' ): ?>
		<?php
		if ( ! empty( $meta['_reepay_discount_eligible_plans'] ) ): ?>
			<?php
			foreach ( $meta['_reepay_discount_eligible_plans'][0] as $eligible_plan ) {
				echo '<br>' . $eligible_plan;
			} ?>
		<?php
		endif; ?>
	<?php
	endif; ?>
</p>

<!--End Availability to-->
