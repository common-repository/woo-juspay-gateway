<?php
/*
 * Plugin Name: WooCommerce Juspay Gateway
 * Description: Take Credit card, Debit card, NetBanking, Wallets, UPI payments on your store using Juspay.
 * Version: 1.0.5
 * Author: Team Juspay
 * Requires at least: 4.4
 * Tested up to: 4.9
 * WC requires at least: 3.0.0
 * WC tested up to: 3.4
 * Text Domain: juspay
 * Domain Path: /languages/
*/

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define base file
if ( ! defined( 'WC_JUSPAY_PLUGIN_FILE' ) ) {
	define( 'WC_JUSPAY_PLUGIN_FILE', __FILE__ );
}


/**
 * WooCommerce missing fallback notice.
 *
 * @return string
 */
function wc_juspay_missing_wc_notice() {
	/* translators: 1. URL link. */
	echo '<div class="error"><p><strong>' . sprintf( esc_html__( 'Juspay requires WooCommerce to be installed and active. You can download %s here.', 'juspay' ), '<a href="https://woocommerce.com/" target="_blank">WooCommerce</a>' ) . '</strong></p></div>';
}


/**
 * WooCommerce version fallback notice.
 *
 * @return string
 */
function wc_juspay_version_wc_notice() {
	echo '<div class="error"><p><strong>' . esc_html__( 'Juspay requires mimumum WooCommerce 3.0. Please upgrade.', 'juspay' ) . '</strong></p></div>';
}


/**
 * Intialize everything after plugins_loaded action
 */
add_action( 'plugins_loaded', 'wc_juspay_init', 5 );
function wc_juspay_init() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'wc_juspay_missing_wc_notice' );
		return;
	}

	if ( version_compare( WC_VERSION, '3.0', '<') ) {
		add_action( 'admin_notices', 'wc_juspay_version_wc_notice' );
		return;
	}

	// Load the main plug class
	if ( ! class_exists( 'WC_Juspay' ) ) {
		require dirname( __FILE__ ) . '/includes/class-wc-juspay.php';
	}

	wc_juspay();
}

/* Plugin instance */
function wc_juspay() {
	return WC_Juspay::get_instance();
}