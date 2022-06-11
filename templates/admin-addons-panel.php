<?php
?>
<div id="product_addons_data" class="panel woocommerce_options_panel wc-metaboxes-wrapper">
    <?php do_action( 'woocommerce-product-addons_panel_start' ); ?>

    <p class="woocommerce-product-add-ons-toolbar woocommerce-product-add-ons-toolbar--open-close toolbar">
        <a href="#" class="close_all"><?php _e( 'Close all', $domain ); ?></a> / <a href="#" class="expand_all"><?php _e( 'Expand all', $domain ); ?></a>
    </p>

    <div class="woocommerce_product_addons wc-metaboxes">

        <?php
        $loop = 0;

        foreach ( $product_addons as $addon ) {
            wc_get_template(
                'admin-addon-single.php',
                array(
                    'addon' => $addon,
                    'loop' => $loop,
                    'domain' => $domain,
                    'addons_list' => $addons_list['content']
                ),
                '',
                reepay_s()->settings('plugin_path').'templates/'
            );

            $loop++;
        }
        ?>

    </div>

    <div class="woocommerce-product-add-ons-toolbar woocommerce-product-add-ons-toolbar--add-import-export toolbar">
        <button type="button" class="button add_new_addon"><?php _e( 'New add-on', 'lfc-product-addons' ); ?></button>
    </div>

</div>



<script type="text/javascript">
    jQuery(function($) {
        $('#product_addons_data')
            .on( 'click', '#_reepay_subscription_choose', function() {
                var addon = $(this).closest('.woocommerce_product_addon');
                var table = addon.find('table.wc-metabox-content')
                if($(this).val() == 'new'){
                    table.find('tbody.exist').addClass('hidden');
                    table.find('tbody.new-addon').removeClass('hidden');
                    addon.find('.product_addon_type').attr('disabled', false)
                }else{
                    table.find('tbody.exist').removeClass('hidden');
                    table.find('tbody.new-addon').addClass('hidden');
                    addon.find('.product_addon_type').attr('disabled', 'disabled')
                }
                $('.active_if_apply_to_custom input').attr('disabled', false)
            })
            .on( 'click', '.add_new_addon', function() {

                var loop = $('.woocommerce_product_addons .woocommerce_product_addon').size();
                var option_count = 0;
                var total_add_ons = $( '.woocommerce_product_addons .woocommerce_product_addon' ).length;

                if ( total_add_ons >= 1 ) {
                    $( '.woocommerce-product-add-ons-toolbar--open-close' ).show();
                }

                var html = '<?php
                    ob_start();

                    $addon['name'] = '';
                    $addon['handle'] = '';
                    $addon['description'] = '';
                    $addon['type'] = 'on-off';
                    $addon['amount'] = '';
                    $addon['vat'] = 25;
                    $addon['vat_type'] = 'include';

                    $loop = "{loop}";

                    wc_get_template(
                        'admin-addon-single.php',
                        array(
                            'addon' => $addon,
                            'loop' => $loop,
                            'domain' => $domain,
                            'addons_list' => $addons_list['content']
                        ),
                        '',
                        reepay_s()->settings('plugin_path').'templates/'
                    );

                    $html = ob_get_clean();
                    echo str_replace( array( "\n", "\r" ), '', str_replace( "'", '"', $html ) );
                    ?>';

                html = html.replace( /{loop}/g, loop );

                $('.woocommerce_product_addons').append( html );

                $('select.product_addon_type').change();

                return false;
            })
            .on( 'click', '.remove_addon', function() {
                var answer = confirm('<?php _e('Are you sure you want remove this add-on?', $domain); ?>');

                if (answer) {
                    var addon = $(this).closest('.woocommerce_product_addon');
                    $(addon).find('input').val('');
                    $(addon).hide();
                }

                return false;
            })
            .find('select.product_addon_type').change();
    });
</script>