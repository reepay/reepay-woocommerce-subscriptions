<?php


class WC_Reepay_Subscriptions_List {

	public function __construct() {

		add_action( 'admin_menu', [ $this, 'create_menu' ] );
		add_action( 'woocommerce_after_order_itemmeta', array( $this, 'add_item_link' ), 11, 3 );

	}

	public function add_item_link( $item_id, $item, $product ) {
		$order_id   = wc_get_order_id_by_order_item_id( $item_id );
		$order      = wc_get_order( $order_id );
		$sub_handle = $order->get_meta( '_reepay_subscription_handle' );

		if ( ! empty( $sub_handle ) ) {
			$admin_page = 'https://app.reepay.com/#/rp/';

			$link = $admin_page . 'subscriptions/subscription/' . $sub_handle;
			echo '&nbsp<a class="button capture-item-button" href="' . esc_url( $link ) . '" target="_blank">' . __( 'See subscription', 'reepay-subscriptions' ) . '</a>';
		}

	}

	function create_menu() {
		//create new top-level menu

		add_submenu_page(
			'woocommerce',
			'Subscriptions',
			'Subscriptions',
			'edit_pages',
			'reepay-subscriptions',
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