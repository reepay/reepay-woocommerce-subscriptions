<?php

class WC_RS_Log{
	/**
	 * @var WC_RS_Log
	 */
    private static $instance;

	/**
	 * @var bool
	 */
	private $test_mode;

	/**
	 * @var bool
	 */
	private $debug;

	/**
	 * Constructor
	 */
    private function __construct() {
        $this->test_mode = WooCommerce_Reepay_Subscriptions::settings('test_mode');
        $this->debug = WooCommerce_Reepay_Subscriptions::settings('debug');
    }

	/**
	 * @return WC_RS_Log
	 */
    public static function get_instance() {
	    if ( is_null( self::$instance ) ) {
		    self::$instance = new self();
	    }

        return self::$instance;
    }

    /**
     * Logging method.
     *
     * @param string $message Log message.
     * @param string $level   Optional. Default 'info'.
     *     emergency|alert|critical|error|warning|notice|info|debug
     *
     * @see WC_Log_Levels
     *
     * @return void
     */
    public function log( $message, $level = 'info' ) {
        // Is Enabled
	    if ( ! $this->debug ) {
            return;
        }

        // Get Logger instance
        $logger = wc_get_logger();

        // Write message to log
        if ( ! is_string( $message ) ) {
            $message = var_export( $message, TRUE );
        }

        $logger->log( $level, $message, array(
            'source'  => reepay_s()->settings('domain'),
            '_legacy' => TRUE
        ) );
    }
}