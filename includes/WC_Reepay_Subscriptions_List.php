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
			'manage_woocommerce',
			'reepay-subscriptions-for-woocommerce',
			[ $this, 'render_page' ]
		);
	}

	public function render_page() {
		// The table below calls the Frisbii API with the merchant's private key.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to access this page.' ), 403 );
		}
		?>

        <form action="" method="get" class="reepay-subscriptions-page">
            <input type="hidden" name="page" value="reepay-subscriptions-for-woocommerce"/>
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
