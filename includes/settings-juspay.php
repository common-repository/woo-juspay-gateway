<?php
/**
 * Settings for Juspay Gateway.
 *
**/

defined( 'ABSPATH' ) || exit;

$environments = array();
$environment_fileds = array();
foreach( WC_Juspay::$environments as $environment_id => $environment ) {
	$environments[$environment_id] = $environment['name'];
	$environment_fileds[$environment_id . '_api_key'] = array(
		'title' => sprintf(__( '%s API Key:', 'juspay'), $environment['name'] ),
		'type' => 'text',
		'description' => __('API Key from "API Keys" section of Juspay dashboard.', 'juspay'),
		'desc_tip' => true
	);
}

return array_merge( array(
	'enabled' => array(
		'title' => __('Enable:', 'juspay'),
		'type' => 'checkbox',
		'label' => ' ',
		'description' => __('If you do not already have Juspay merchant account, <a href="https://merchant.juspay.in/register" target="_blank">please register in Production</a> or <a href="https://sandbox.juspay.in/register" target="_blank">please register in Sandbox</a>.', 'juspay'),
		'default' => 'no',
		'desc_tip' => true
	),
	'title' => array(
		'title' => __('Title:', 'juspay'),
		'type' => 'text',
		'description' => __('Title of Juspay Payment Gateway that users see on Checkout page.', 'juspay'),
		'default' => __('Credit & Debit Cards / NetBanking / Wallets / UPI', 'juspay'),
		'desc_tip' => true
	),
	'description' => array(
		'title' => __('Description:', 'juspay'),
		'type' => 'textarea',
		'description' => __('Description of Juspay Payment Gateway that users sees on Checkout page.', 'juspay'),
		'default' => __('Pay securely by Credit or Debit card or Internet Banking through Juspay.', 'juspay'),
		'desc_tip' => true
	),
	'advanced_settings'           => array(
		'title'       => __( 'Advanced options', 'juspay' ),
		'type'        => 'title'
	),
	'debug' => array(
		'title' => __('Debug log', 'juspay'),
		'type' => 'checkbox',
		'label' => 'Enable logging',
		'description' => sprintf( __('Log Juspay events, such as Webhook requests, inside %1$s. Note: this may log personal information. We recommend using this for debugging purposes only and deleting the logs when finished. <a href="%2$s">View logs here</a>', 'juspay'), '<code>' . WC_Log_Handler_File::get_log_file_path( 'juspay' ) . '</code>', admin_url('admin.php?page=wc-status&tab=logs') ),
		'default' => 'no',
	),
	'webhook_handler_enabled' => array(
		'title' => __('Enable webhook', 'juspay'),
		'type' => 'checkbox',
		'label' => 'Use Juspay Webhook?',
		'description' => sprintf (__(' Webhook automatically update your store order status. Howeever, you must setup <a href="%s">Webhook</a> from your juspay merchant account, otherwise Order status will not be updated.<br><br>Use the following information to configure:<br>Webhook URL: <code>%s</code>.<br>API Version: <code>%s</code>.', 'juspay'), 
		'https://merchant.juspay.in/settings/webhooks',
		WC()->api_request_url('wc_gateway_juspay_webhook'), 
		Juspay\JuspayEnvironment::getApiVersion()
		),
		'default' => 'yes',
	),
	'return_handler_enabled' => array(
		'title' => __('Return processing', 'juspay'),
		'type' => 'checkbox',
		'label' => 'Enable Return processing',
		'description' => __('If webhook is enabled, you do not need to enable return processing. Only enable this, if you face a conflict with AUTHENTICATION_FAILED webhook request, or you are unable to use webhook.', 'juspay'),
		'default' => 'yes',
	),
	'send_customer_id' => array(
		'title'       => __('Customer id', 'juspay'),
		'type'        => 'checkbox',
		'label'       => __('Yes, Send customer id while creating order.', 'juspay'),
		'description' => __('This would allow juspay to display save card option for user at checkout page.', 'juspay'),
		'default'     => 'yes',
	),
	'api_details'     => array(
		'title'       => __( 'API Settings', 'juspay' ),
		'type'        => 'title',
	),
	'environment'     => array(
		'title'       => __( 'Environment', 'juspay' ),
		'type'        => 'select',
		'default'     => 'staging',
		'options'	  => $environments
	)
), $environment_fileds );