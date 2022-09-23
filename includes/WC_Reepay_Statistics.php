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
        $params = [
            'plugin' => 'WOOCOMMERCE-SUBSCRIPTION',
            'version' => reepay_s()->settings('version'),
            'privatekey' => reepay_s()->settings('api_private_key'),
            'url' => home_url(),
            'event' => $event,
        ];

        $url = 'https://hook.reepay.integromat.celonis.com/1dndgwx6cwsvl3shsee29yyqf4d648xf';
        $res = wp_remote_post($url, [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode($params, JSON_PRETTY_PRINT),
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

    public static function private_key_activated()
    {
        static::send_event('activated');
    }

    public static function upgrade_completed($upgrader_object, $options)
    {
        if (!empty($options['plugins'])) {
            foreach ($options['plugins'] as $plugin) {
                if (strpos($plugin, REEPAY_PLUGIN_FILE)) {
                    static::send_event('updated');
                }
            }
        }
    }
}