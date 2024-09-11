<?php
use Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface;

define( 'WC_REEPAY_BLOCKSTERMS_VERSION', '1.0.0' );

/**
 * Class WC_Reepay_Woo_Blocks_Terms_Integration
 *
 * Class for integrating marketing optin block with WooCommerce Checkout
 *
 */
class WC_Reepay_Woo_Blocks_Terms_Integration implements IntegrationInterface {

    /**
	 * The name of the integration.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'wc-reepay-woo-block-terms';
	}

	/**
	 * When called invokes any initialization/setup for the integration.
	 */
	public function initialize() {
        $this->register_block_frontend_scripts();
        $this->register_block_editor_scripts();
        add_filter( '__experimental_woocommerce_blocks_add_data_attributes_to_block', [ $this, 'add_attributes_to_frontend_blocks' ], 10, 1 );
	}

	/**
	 * Returns an array of script handles to enqueue in the frontend context.
	 *
	 * @return string[]
	 */
	public function get_script_handles() {
		return array( 'wc-reepay-woo-blocks-terms' );
	}

	/**
	 * Returns an array of script handles to enqueue in the editor context.
	 *
	 * @return string[]
	 */
	public function get_editor_script_handles() {
		return array( 'wc-reepay-woo-blocks-terms-editor' );
	}

	/**
	 * Register scripts for delivery date block editor.
	 *
	 * @return void
	 */
	public function register_block_editor_scripts() {
		$script_path       = 'build/index.js';
		$script_url 	   = plugin_dir_url( __FILE__ ).$script_path;
		$script_asset_path = plugin_dir_path( __FILE__ ).'build/index.asset.php';

		$script_asset      = file_exists( $script_asset_path )
			? require $script_asset_path
			: array(
				'dependencies' => array(),
				'version'      => $this->get_file_version( $script_asset_path ),
			);

		wp_register_script(
			'wc-reepay-woo-blocks-terms-editor',
			$script_url,
			$script_asset['dependencies'],
			$script_asset['version'],
			true
		);
	}

	/**
	 * Register scripts for frontend block.
	 *
	 * @return void
	 */
	public function register_block_frontend_scripts() {
		$script_path       = 'build/wc-reepay-woo-blocks-terms.js';
		$script_url 	   = plugin_dir_url( __FILE__ ).$script_path;
		$script_asset_path = plugin_dir_path( __FILE__ ).'build/wc-reepay-woo-blocks-terms.asset.php';

		$script_asset = file_exists( $script_asset_path )
			? require $script_asset_path
			: array(
				'dependencies' => array(),
				'version'      => $this->get_file_version( $script_asset_path ),
			);

		wp_register_script(
			'wc-reepay-woo-blocks-terms',
			$script_url,
			$script_asset['dependencies'],
			$script_asset['version'],
			true
		);
	}

    /**
	 * This allows dynamic (JS) blocks to access attributes in the frontend.
	 *
	 * @param string[] $allowed_blocks
	 */
    public function add_attributes_to_frontend_blocks( $allowed_blocks ){
        $allowed_blocks[] = 'wc-reepay-woo-blocks-terms/checkbox';
        return $allowed_blocks;
    }

    /**
	 * An array of key, value pairs of data made available to the block on the client side.
	 *
	 * @return array
	 */
	public function get_script_data() {
        $data = array(
            'has_reepay_subscription' => $this->check_reepay_product_in_cart(),
            'repay_subscription_terms_label' => WooCommerce_Reepay_Subscriptions::subscription_terms_checkbox_label(),
        );
		return $data;
	}

    /**
     * Check cart items has reepay subscription product
     * @return boolean
     */
    public function check_reepay_product_in_cart(){
		if(is_admin()){
			return false;
		}

		if(get_option('_reepay_enable_subscription_terms') === 'yes'){
			$has_reepay_product = WC_Reepay_Checkout::is_reepay_product_in_cart();
			return $has_reepay_product;
		}
        
        return false;
    }

	/**
	 * Get the file modified time as a cache buster if we're in dev mode.
	 *
	 * @param string $file Local path to the file.
	 * @return string The cache buster value to use for the given file.
	 */
	protected function get_file_version( $file ) {
		if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG && file_exists( $file ) ) {
			return filemtime( $file );
		}
		return WC_REEPAY_BLOCKSTERMS_VERSION;
	}
}
?>
