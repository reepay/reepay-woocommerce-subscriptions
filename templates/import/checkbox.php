<?php
/**
 * @var array  $args
 */

?>

<label>
    <input type="<?php echo esc_attr( $args['input_type'] ?? 'checkbox' ) ?>"
           name="reepay_import<?php echo esc_attr( $args['option_name'] ) ?>"/>
</label>