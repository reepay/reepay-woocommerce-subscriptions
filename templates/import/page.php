<div class="wrap">
    <h1><?php echo get_admin_page_title() ?></h1>

    <?php
    wc_get_template(
	    'admin-list-menu.php',
	    [
		    'active_item' => 1,
	    ],
	    '',
	    reepay_s()->settings( 'plugin_path' ) . 'templates/'
    );
    ?>

    <form class="js-reepay-import-form">
		<?php
		do_settings_sections( 'reepay_import' );
		submit_button( 'View details' );
		?>
    </form>

    <form class="js-reepay-import-form-view" style="display:none">
        <p class="submit">
	        <?php submit_button(
		        __( 'Import selected', 'reepay-subscriptions-for-woocommerce' ),
		        'primary',
		        'submit',
		        false
	        ); ?>
            <a href="#" class="button button-secondary js-back">
                <?php _e('Back', 'reepay-subscriptions-for-woocommerce') ?>
            </a>
        </p>

        <div class="js-reepay-import-table-container"></div>

        <p class="submit">
	        <?php submit_button(
		        __( 'Import selected', 'reepay-subscriptions-for-woocommerce' ),
		        'primary',
		        'submit',
		        false
	        ); ?>
            <a href="#" class="button button-secondary js-back"><?php _e('Back', 'reepay-subscriptions-for-woocommerce') ?></a>
        </p>
    </form>
</div>