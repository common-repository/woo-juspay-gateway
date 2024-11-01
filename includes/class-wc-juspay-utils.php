<?php
/**
 * WooCommerce Juspay Utility Class.
 *
 * Provides static methods as helpers.
 *
 * @class       WC_Juspay_Utils
**/


class WC_Juspay_Utils {

	public static function p( $a ) {
		echo '<pre>';
		print_r($a);
		echo '</pre>';
	}

	public static function d( $a ) {
		self::p( $a );
		exit;
	}
}
