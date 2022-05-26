<?php

class WC_Reepay_Subscription_API{
	/**
	 * @var WC_Reepay_Subscription_API
	 */
	private static $instance;

	/**
	 * @var string
	 */
	private $url;

	/**
	 * @var string
	 */
	private $private_key;

	/**
	 * @var string
	 */
	private $private_key_test;

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
	    $this->url = 'https://api.reepay.com/v1/';

	    $this->private_key = 'priv_3728a84bd1d89da26f4da17a75aa81c3';

        $this->private_key_test = 'priv_3728a84bd1d89da26f4da17a75aa81c3';
        $this->test_mode = 'yes' === 'yes'; //ToDo replace with settings;
        $this->debug = 'yes' === 'yes'; //ToDo replace with settings;
    }

	/**
	 * @return WC_Reepay_Subscription_API
	 */
	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

    /**
     * Request
     * @param string $method
     * @param string $endpoint
     * @param array $params
     * @return array|mixed|object
     * @throws Exception
     */
    public function request($endpoint, $method = 'GET', $params = []) {
        $start = microtime(true);
		$url = $this->url . $endpoint;

        WC_RS_Log::i()->log(sprintf('Request: %s %s %s', $method, $url, json_encode( $params, JSON_PRETTY_PRINT ) ) ); ;


        $key = $this->test_mode ? $this->private_key_test : $this->private_key;
        $key = base64_encode($key.':');

        $args = [
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'Authorization' => "Basic $key"
            ],
            'method' => $method,
            'timeout' => 60,
        ];
        if (count($params) > 0) {
            $args['body'] = json_encode($params, JSON_PRETTY_PRINT);
            $args['headers']['Content-Length'] = strlen(json_encode($params, JSON_PRETTY_PRINT));
        }

        $response = wp_remote_request( $url, $args );
        $body = wp_remote_retrieve_body( $response );
        $http_code = wp_remote_retrieve_response_code( $response );
        $code = round($http_code / 100);

        if ( $this->debug ) {
            $time = microtime(true) - $start;
            WC_RS_Log::i()->log( sprintf( '[%.4F] HTTP Code: %s. Response: %s', $time, wp_remote_retrieve_response_code( $response ), $body ) );
        }

        switch ($code) {
            case 0:
                if ( is_wp_error($response) ){
                    wc_add_notice($response->get_error_message(), 'error');
                    throw new Exception(sprintf(__('Error: %s. Code: %s.', WC_Reepay_Subscriptions::$domain ), $response->get_error_message(), $code));
                }
            case 1:
                throw new Exception(sprintf(__('Invalid HTTP Code: %s', WC_Reepay_Subscriptions::$domain ), $http_code));
            case 2:
            case 3:
                return json_decode($body, true);
            case 4:
            case 5:
                if ( mb_strpos( $body, 'Request rate limit exceeded', 0, 'UTF-8' ) !== false ) {
                    global $request_retry;
                    if ($request_retry) {
                        throw new Exception( __('Reepay: Request rate limit exceeded', WC_Reepay_Subscriptions::$domain ) );
                    }

                    sleep(10);
                    $request_retry = true;
                    $result = $this->request($method, $url);
                    $request_retry = false;

                    return  $result;
                }

                $error = json_decode($body)->error;
                $message = json_decode($body)->message;
                if(!empty($message)){
                    $error .= ' - '.$message;
                }
                throw new Exception(sprintf(__('API Error (request): %s. HTTP Code: %s', WC_Reepay_Subscriptions::$domain ), $error, $http_code));
            default:
                if ( $this->debug ) {
                    throw new Exception($body);
                }else{
                    throw new Exception(sprintf(__('Invalid HTTP Code: %s', WC_Reepay_Subscriptions::$domain ), $http_code));
                }

        }
    }
}
