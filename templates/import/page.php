<div class="wrap">
    <h1><?php echo get_admin_page_title() ?></h1>

    <ul class="subsubsub" style="float:unset">
        <li><a href="<?php echo get_admin_url() ?>admin.php?page=wc-settings&tab=reepay_subscriptions"
               class="">General</a> |
        </li>
        <li>
            <a href="<?php echo get_admin_url() ?>tools.php?page=reepay_import"
               class="current">Import tools</a>
        </li>
    </ul>

    <form class="js-reepay-import-form">
		<?php
		do_settings_sections( 'reepay_import' );
		submit_button( 'View details' );
		?>
    </form>

    <form class="js-reepay-import-form-view" style="display:none">
        <p class="submit">
		    <?php submit_button( 'Import selected', 'primary', 'submit', false ); ?>
            <a href="#" class="button button-secondary js-back">Back</a>
        </p>

        <div class="js-reepay-import-table-container"></div>

        <p class="submit">
	        <?php submit_button( 'Import selected', 'primary', 'submit', false ); ?>
            <a href="#" class="button button-secondary js-back">Back</a>
        </p>
    </form>
</div>