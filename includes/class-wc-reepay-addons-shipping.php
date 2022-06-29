<?php

class WC_Reepay_Subscription_Addons_Shipping extends WC_Reepay_Subscription_Addons {

	/**
	 * Constructor
	 */
	public function __construct() {
		// Add shipping method addons
		add_action( 'woocommerce_init', array( $this, 'reepay_shipping_instance_form_fields_filters' ) );
	}

	/**
	 * Shipping instance form fields.
	 */
	function reepay_shipping_instance_form_fields_filters() {
		$shipping_methods = WC()->shipping->get_shipping_methods();
		foreach ( $shipping_methods as $shipping_method ) {
			add_filter( 'woocommerce_shipping_instance_form_fields_' . $shipping_method->id, array( $this, 'reepay_shipping_instance_form_add_extra_fields' ) );
			add_filter( 'woocommerce_shipping_' . $shipping_method->id . '_instance_settings_values', array( $this, 'save_options' ), 100, 2 );
		}
	}

	/**
	 * Shipping Instance form add extra fields.
	 *
	 * @param  array  $settings  Settings.
	 *
	 * @return array
	 */
	function reepay_shipping_instance_form_add_extra_fields( $settings ) {
		$options['']    = __( 'Choose add-on', reepay_s()->settings( 'domain' ) );
		$options['new'] = __( 'New add-on', reepay_s()->settings( 'domain' ) );

		$addons_list = $this->get_reepay_addons_list();
		if ( ! empty( $addons_list["content"] ) ) {
			foreach ( $addons_list["content"] as $addon ) {
                if($addon['all_plans']){
                    $options[ $addon['handle'] ] = $addon['name'];
                }
			}
		}

		$settings['reepay_shipping_addon'] = array(
			'title'   => esc_html__( 'Reepay Add-on', reepay_s()->settings( 'domain' ) ),
			'type'    => 'select',
			'default' => '',
			'class'   => 'wc-enhanced-select addon-shipping-choose',
			'options' => $options,
            'description' => __('Only addons not related to plans are available.', reepay_s()->settings('domain')),
            'desc_tip' => true,
		);

		$settings['reepay_shipping_addon_name'] = array(
			'title'   => esc_html__( 'Add-on name', reepay_s()->settings( 'domain' ) ),
			'type'    => 'text',
			'default' => '',
			'class'   => 'addon-shipping-new',
		);

		$settings['reepay_shipping_addon_description'] = array(
			'title'   => esc_html__( 'Add-on description', reepay_s()->settings( 'domain' ) ),
			'type'    => 'textarea',
			'default' => '',
			'class'   => 'addon-shipping-new',
		);

		$settings['reepay_shipping_addon_amount'] = array(
			'title'   => esc_html__( 'Add-on amount', reepay_s()->settings( 'domain' ) ),
			'type'    => 'price',
			'default' => '',
			'class'   => 'addon-shipping-new',
			'custom_attributes' => array('readonly' => 'readonly'),
		);

		$settings['reepay_shipping_addon_vat'] = array(
			'title'       => esc_html__( 'Add-on VAT %', reepay_s()->settings( 'domain' ) ),
			'type'        => 'price',
			'default'     => '',
			'placeholder' => '%',
			'class'       => 'addon-shipping-new',
		);

		$settings['reepay_shipping_addon_vat_type'] = array(
			'title'   => esc_html__( 'Add-on VAT type', reepay_s()->settings( 'domain' ) ),
			'type'    => 'select',
			'default' => '',
			'class'   => 'addon-shipping-new',
			'options' => array(
				'include' => 'Include VAT',
				'exclude' => 'Exclude VAT'
			),
		);

		return $settings;
	}

	/**
	 * @param  array  $instance_settings
	 * @param  WC_Shipping_Method  $shipping_method
	 *
	 * @return array
	 */
	function save_options( $instance_settings, $shipping_method ) {
		if ( ! isset( $instance_settings['reepay_shipping_addon'] ) ) {
			return $instance_settings;
		}

		if ( empty( $instance_settings['reepay_shipping_addon'] ) ) {
			//clear data
			unset( $instance_settings['reepay_shipping_addon'] );
			unset( $instance_settings['reepay_shipping_addon_name'] );
			unset( $instance_settings['reepay_shipping_addon_description'] );
			unset( $instance_settings['reepay_shipping_addon_amount'] );
			unset( $instance_settings['reepay_shipping_addon_vat'] );
			unset( $instance_settings['reepay_shipping_addon_vat_type'] );
		} else {
			if ( $instance_settings['reepay_shipping_addon'] == 'new' ) {
				//add new method
				$created_addon = $this->save_to_reepay( [
					'name'        => $instance_settings['reepay_shipping_addon_name'],
					'description' => $instance_settings['reepay_shipping_addon_description'],
					'amount'      => $instance_settings['cost'],
					'vat'         => $instance_settings['reepay_shipping_addon_vat'],
					'type'        => 'on_off',
					'vat_type'    => $instance_settings['reepay_shipping_addon_vat_type'],
				], $shipping_method->get_instance_option_key() );

				$instance_settings['reepay_shipping_addon_amount'] = $created_addon['cost'];
				$instance_settings['reepay_shipping_addon'] = $created_addon['handle'];
			} else {
				//get existing method
				$addon_data = $this->get_reepay_addon_data( $instance_settings['reepay_shipping_addon'] );

				$instance_settings['reepay_shipping_addon_name']        = $addon_data['name'];
				$instance_settings['reepay_shipping_addon_description'] = $addon_data['description'];
				$instance_settings['reepay_shipping_addon_amount']      = $instance_settings['cost'];
				$instance_settings['reepay_shipping_addon_vat']         = $addon_data['vat'] / 100;
				$instance_settings['reepay_shipping_addon_vat_type']    = $addon_data['type'];
			}
		}

		return $instance_settings;
	}

	public function save_to_reepay( $product_addon, $option_key, $i = 1 ) {
		$params = [
			'name'            => ! empty( $product_addon['name'] ) ? $product_addon['name'] : '',
			'description'     => ! empty( $product_addon['description'] ) ? $product_addon['description'] : '',
			'amount'          => ! empty( $product_addon['amount'] ) ? floatval( $product_addon['amount'] ) * 100 : 0,
			'vat'             => ! empty( $product_addon['vat'] ) ? floatval( $product_addon['vat'] ) / 100 : 0,
			'type'            => $product_addon['type'],
			'amount_incl_vat' => $product_addon['vat_type'] == 'include',
			'all_plans'       => true,
		];

		if ( ! empty( $product_addon['handle'] ) ) { //Update
			$handle = $product_addon['handle'];
			try {
				reepay_s()->api()->request( "add_on/$handle", 'PUT', $params );
			} catch ( Exception $e ) {
				WC_Reepay_Subscription_Admin_Notice::add_notice( $e->getMessage() );
			}
		} else { //Create
			$addon_handle     = 'Woocommerce_' . $option_key . '_' . $i;
			$params['handle'] = $addon_handle;
			try {
				reepay_s()->api()->request( 'add_on', 'POST', $params );
				$product_addon['handle'] = $addon_handle;
			} catch ( Exception $e ) {
				WC_Reepay_Subscription_Admin_Notice::add_notice( $e->getMessage() );
			}
		}

		return $product_addon;
	}
}

new WC_Reepay_Subscription_Addons_Shipping();

