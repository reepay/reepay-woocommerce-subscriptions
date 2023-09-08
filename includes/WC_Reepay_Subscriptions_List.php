<?php


class WC_Reepay_Subscriptions_List {

	public function __construct() {
		add_action( 'admin_menu', [ $this, 'create_menu' ] );
	}

	function create_menu() {
		add_submenu_page(
			'woocommerce',
			'Subscriptions',
			'Subscriptions',
			'edit_pages',
			'reepay-subscriptions-for-woocommerce',
			[ $this, 'render_page' ]
		);
	}

	public function render_page() {
		?>

        <form action="" method="get" class="reepay-subscriptions-page">
            <input type="hidden" name="page" value="reepay-subscriptions"/>
			<?php
			$drafts_table = new WC_Reepay_Subscriptions_Table();
			$drafts_table->prepare_items();
			$drafts_table->search_box( 'Search', 'search' );
			$drafts_table->display();
			?>
        </form>

		<?php
	}

}
