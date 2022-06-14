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
     * Shipping Instance form add extra fields.
     *
     * @param array $settings Settings.
     * @return array
     */
    function reepay_shipping_instance_form_add_extra_fields( $settings ) {
        $options[''] =  __('Choose add-on', reepay_s()->settings('domain'));
        $options['new'] =  __('New add-on', reepay_s()->settings('domain'));

        $addons_list = $this->get_reepay_addons_list();
        if(!empty($addons_list["content"])){
            foreach ($addons_list["content"] as $addon){
                $options[$addon['handle']] = $addon['name'];
            }
        }

        $settings['reepay_shipping_addon'] = array(
            'title' => esc_html__('Reepay Add-on', reepay_s()->settings('domain')),
            'type' => 'select',
            'default' => '',
            'class' => 'wc-enhanced-select addon-shipping-choose',
            'options' => $options,
        );

        $settings['reepay_shipping_addon_name'] = array(
            'title' => esc_html__('Add-on name', reepay_s()->settings('domain')),
            'type' => 'text',
            'default' => '',
            'class' => 'addon-shipping-new',
        );

        $settings['reepay_shipping_addon_description'] = array(
            'title' => esc_html__('Add-on description', reepay_s()->settings('domain')),
            'type' => 'textarea',
            'default' => '',
            'class' => 'addon-shipping-new',
        );

        $settings['reepay_shipping_addon_amount'] = array(
            'title' => esc_html__('Add-on amount', reepay_s()->settings('domain')),
            'type' => 'price',
            'default' => '',
            'class' => 'addon-shipping-new',
        );

        $settings['reepay_shipping_addon_vat'] = array(
            'title' => esc_html__('Add-on VAT %', reepay_s()->settings('domain')),
            'type' => 'price',
            'default' => '',
            'placeholder' => '%',
            'class' => 'addon-shipping-new',
        );

        $settings['reepay_shipping_addon_vat_type'] = array(
            'title' => esc_html__('Add-on VAT type', reepay_s()->settings('domain')),
            'type' => 'select',
            'default' => '',
            'class' => 'addon-shipping-new',
            'options' => array(
                'include' => 'Include VAT',
                'exclude' => 'Exclude VAT'
            ),
        );

        return $settings;
    }

    /**
     * Shipping instance form fields.
     */
    function reepay_shipping_instance_form_fields_filters() {
        $shipping_methods = WC()->shipping->get_shipping_methods();
        foreach ( $shipping_methods as $shipping_method ) {
            add_filter( 'woocommerce_shipping_instance_form_fields_' . $shipping_method->id, array( $this, 'reepay_shipping_instance_form_add_extra_fields' ) );
        }
    }
}

new WC_Reepay_Subscription_Addons_Shipping();

