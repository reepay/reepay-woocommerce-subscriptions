<?php

/**
 * Class WC_Reepay_Sync
 *
 * @since 1.0.4
 */
class WC_Reepay_Sync {
	/**
	 * Constructor
	 */
	public function __construct() {
		new WC_Reepay_Sync_Customer();
	}
}