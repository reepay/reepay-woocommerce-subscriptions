<?php
/**
 * @var array $args
 * @var string $value
 */
?>

<label>
	<input type="checkbox"
	       name="reepay_import[<?php echo $args['option_name'] ?>]"
		<?php checked( $value, 'yes' ) ?> />
</label>