<div class="wrap">
	<h1><?php echo get_admin_page_title() ?></h1>
	<form method="post" action="options.php">
		<ul class="subsubsub">
			<li><a href="<?php echo get_admin_url() ?>admin.php?page=wc-settings&tab=reepay_subscriptions"
			       class="">General</a> |
			</li>
			<li>
				<a href="<?php echo get_admin_url() ?>tools.php?page=reepay_import"
				   class="current">Import tools</a>
			</li>
		</ul>
		<?php
		settings_fields( 'reepay_import_settings' );
		do_settings_sections( 'reepay_import' );
		submit_button( 'Save and import' );
		?>
	</form>
</div>