<?php

if (!defined('ABSPATH')) {
    exit;
}

class WC_Reepay_Statistics
{

    /**
     * Constructor
     */
    public function __construct()
    {
        register_deactivation_hook(REEPAY_PLUGIN_FILE, [static::class, 'plugin_deactivated']);
        register_uninstall_hook(REEPAY_PLUGIN_FILE, [static::class, 'plugin_deleted']);
        add_action('upgrader_process_complete', [static::class, 'upgrade_completed'], 10, 2);
    }

    public static function send_event($event)
    {
        // Read the option directly: on uninstall the plugin settings are not loaded.
        if (get_option('_reepay_statistics') !== 'yes') {
            return null;
        }

        $params = [
            'plugin' => 'WOOCOMMERCE-SUBSCRIPTION',
            'version' => reepay_s()->settings('version'),
            'url' => home_url(),
            'event' => $event,
        ];

        $url = 'https://hook.reepay.integromat.celonis.com/1dndgwx6cwsvl3shsee29yyqf4d648xf';
        $res = wp_remote_post($url, [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode($params),
            'timeout' => 3,
            'blocking' => false,
        ]);

        return $res;
    }

    public static function plugin_deactivated()
    {
        static::send_event('deactivated');
    }

    public static function plugin_deleted()
    {
        static::send_event('deleted');
    }

    public static function upgrade_completed($upgrader_object, $options)
    {
        if (!empty($options['plugins'])) {
            foreach ($options['plugins'] as $plugin) {
                if ($plugin === plugin_basename(REEPAY_PLUGIN_FILE)) {
                    static::send_event('updated');
                }
            }
        }
    }
}