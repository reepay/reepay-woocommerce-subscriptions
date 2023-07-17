<?php
/**
 * @var Array $product_addons
 * @var Array $addons_list
 */

?>
<div id="product_addons_data" class="panel woocommerce_options_panel wc-metaboxes-wrapper">
	<?php do_action( 'woocommerce-product-addons_panel_start' ); ?>

    <p class="woocommerce-product-add-ons-toolbar woocommerce-product-add-ons-toolbar--open-close toolbar">
        <a href="#" class="close_all"><?php _e( 'Close all', 'reepay-subscriptions-for-woocommerce' ); ?></a> / <a href="#"
                                                                                                   class="expand_all"><?php _e( 'Expand all', 'reepay-subscriptions-for-woocommerce' ); ?></a>
    </p>

    <div class="woocommerce_product_addons wc-metaboxes">

		<?php
		$loop = 0;

		foreach ( $product_addons as $addon ) {
			wc_get_template(
				'admin-addon-single.php',
				array(
					'addon'       => $addon,
					'loop'        => $loop,
					'addons_list' => $addons_list['content']
				),
				'',
				reepay_s()->settings( 'plugin_path' ) . 'templates/'
			);

			$loop ++;
		}
		?>

    </div>

    <div class="woocommerce-product-add-ons-toolbar woocommerce-product-add-ons-toolbar--add-import-export toolbar">
        <button type="submit"
                class="button button-primary save-addons-button hidden"><?php _e( 'Save changes', 'reepay-subscriptions-for-woocommerce' ); ?></button>
        <button type="button" class="button add_new_addon"><?php _e( 'New add-on', 'reepay-subscriptions-for-woocommerce' ); ?></button>
    </div>

</div>


<script type="text/javascript">
    jQuery(function ($) {
        const $addons_tab = $('#product_addons_data');

        $addons_tab
            .on('click', '.add_new_addon', function () {
                const $new_addon = $('.woocommerce_product_addons .woocommerce_product_addon');

                const loop = $new_addon.size();
                const total_add_ons = $new_addon.length;

                if (total_add_ons >= 1) {
                    $('.woocommerce-product-add-ons-toolbar--open-close').show();
                }

                let html = '<?php
					ob_start();

					$addon['name'] = '';
					$addon['handle'] = '';
					$addon['description'] = '';
					$addon['type'] = 'on-off';
					$addon['amount'] = '';
					$addon['avai'] = '';
					$addon['vat'] = 25;
					$addon['vat_type'] = 'include';

					$loop = "{loop}";

					wc_get_template(
						'admin-addon-single.php',
						array(
							'addon'       => $addon,
							'loop'        => $loop,
							'addons_list' => $addons_list['content']
						),
						'',
						reepay_s()->settings( 'plugin_path' ) . 'templates/'
					);

					$html = ob_get_clean();
					echo str_replace( array( "\n", "\r" ), '', str_replace( "'", '"', $html ) );
					?>';

                html = html.replace(/{loop}/g, loop);

                $('.woocommerce_product_addons').append(html);
                $('.save-addons-button').show();
                $('select.product_addon_type').change();

                return false;
            })
            .on('click', '.remove_addon', function () {
                const answer = confirm('<?php _e( 'Are you sure you want remove this add-on?', 'reepay-subscriptions-for-woocommerce' ); ?>');

                if (answer) {
                    const addon = $(this).closest('.woocommerce_product_addon');
                    $(addon).find('input').val('');
                    $(addon).hide();
                }

                return false;
            })
            .on('change', '.js-subscription_choose_exist', function () {
                const $this = $(this);
                const handle = $this.val();

                if (!handle) {
                    return
                }

                $this
                    .parent()
                    .block({
                        message: null,
                        overlayCSS: {
                            background: '#fff',
                            opacity: 0.6
                        }
                    });

                const $container = $this
                    .parents('.wc-metabox-content')
                    .find('.js-exist-addon-data')
                    .html('');

                $.ajax({
                    url: '<?php echo get_rest_url( 0, reepay_s()->settings( 'rest_api_namespace' ) . "/addon/" ) . '?product_id=' . ( intval( $_GET['post'] ?? 0) ) ?>' + `&handle=${handle}`,
                    method: 'GET',
                    beforeSend: function (xhr) {

                    },
                    success: function (response_data) {
                        if (!response_data.success) {
                            return;
                        }

                        $container
                            .append($(`<table style="width: 100%;padding:0;">${response_data.html}</table>`))
                            .find('.woocommerce-help-tip')
                            .tipTip({
                                'attribute': 'data-tip',
                                'fadeIn': 50,
                                'fadeOut': 50,
                                'delay': 200
                            });
                    },
                    error: function (request, status, error) {

                    },
                    complete: function () {
                        $this.parent().unblock();
                    },
                })
            });

        $addons_tab.find('select.product_addon_type').change();
        $addons_tab.find('.js-subscription_choose_exist').change();
    });
</script>
