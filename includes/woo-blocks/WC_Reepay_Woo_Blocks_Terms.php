<?php
/**
 * Reepay subscription terms and conditions checkbox for WC checkout block.
 */
class WC_Reepay_Woo_Blocks_Terms {

    /**
	 * Constructor.
	 */
    public function __construct(){
        if ( did_action( 'woocommerce_blocks_loaded' ) ) {
            $this->wc_reepay_woo_blocks_terms_init();
        } else {
            add_action( 'woocommerce_blocks_loaded', array( $this, 'wc_reepay_woo_blocks_terms_init' ) );
        }
    }

    /**
	 * Checks if the WooCommerce Blocks is active.
	 * Note: Must be run after the "plugins_loaded" action fires.
	 *
	 * @return bool
	 */
	public function is_woocommerce_blocks_active() {
		return class_exists( 'Automattic\WooCommerce\Blocks\Package' );
	}

    /**
	 * Checks if the current WooCommerce Blocks version is supported.
	 * Note: Must be run after the "plugins_loaded" action fires.
	 *
	 * @return bool
	 */
	public function is_woocommerce_blocks_version_supported() {
		return version_compare(
			\Automattic\WooCommerce\Blocks\Package::get_version(),
			'7.3.0',
			'>='
		);
	}

    /**
	 * Registers block type and registers to WC Blocks Integration Interface.
	 */
    public function wc_reepay_woo_blocks_terms_init(){
        if ( $this->is_woocommerce_blocks_active() && $this->is_woocommerce_blocks_version_supported() ) {
            require_once __DIR__ . '/terms-checkbox/WC_Reepay_Woo_Blocks_Terms_Integration.php';
            require_once __DIR__ . '/terms-checkbox/WC_Reepay_Woo_Blocks_Terms_Extend_Store_Endpoint.php';
            require_once __DIR__ . '/terms-checkbox/WC_Reepay_Woo_Blocks_Terms_Extend_Woo_Core.php';
            add_action(
                'woocommerce_blocks_checkout_block_registration',
                function( $integration_registry ) {
                    $integration_registry->register( new WC_Reepay_Woo_Blocks_Terms_Integration() );
                },
                10,
                1
            );

            // Initialize our store endpoint extension when WC Blocks is loaded.
	        WC_Reepay_Woo_Blocks_Terms_Extend_Store_Endpoint::init();

            // Add hooks relevant to extending the Woo core experience.
            $extend_core = new WC_Reepay_Woo_Blocks_Terms_Extend_Woo_Core();
            $extend_core->init();

            register_block_type( 'wc-reepay-woo-blocks-terms/checkbox', array(
                'api_version'            => 3,
                'title'                  => 'Subscription terms checkbox',
                'parent'                 => array( 'woocommerce/checkout-fields-block' ),
                'category'               => 'woocommerce',
                'supports'               => array(
                    'html'     => false,
                    'align'    => false,
                    'multiple' => false,
                    'reusable' => false,
                ),
                'attributes'             => array(
                    'lock' => array(
                        'type'    => 'object',
                        'default' => array(
                            'remove' => true,
                            'move'   => true,
                        ),
                    ),
                ),
                'textdomain'             => 'reepay-subscriptions-for-woocommerce',
                'editor_script_handles'  => array( 'wc-reepay-woo-blocks-terms-editor' ),
                'style_handles'          => array( 'wc-reepay-woo-blocks-terms-style' ),
            ) );

            add_action( 'admin_enqueue_scripts', array( $this, 'wc_reepay_woo_blocks_terms_style' ) );
            add_action( 'wp_enqueue_scripts', array( $this, 'wc_reepay_woo_blocks_terms_style' ) );
        }
    }

    /**
	 * Registers style to admin and front end.
	 */
    public function wc_reepay_woo_blocks_terms_style(){
        $style_path = plugin_dir_url( __FILE__ ).'terms-checkbox/build/style-index.css';
        wp_enqueue_style(
            'wc-reepay-woo-blocks-terms-style',
            $style_path,
            array(),
            false
        );
    }
}
?>
