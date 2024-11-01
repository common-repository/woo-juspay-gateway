# WooCommerce Gateway Juspay

###  Description

Juspay's is a SaaS layer on top of Payment gateways to simplify integration, improve robustness and ease the management of diverse and rapidly evolving payment options in India.

### Features

WooCommerce Gateway Juspay adds multiple payment options using redirect based payment flow and enables the following features for your shop :

* Smart transaction routing and retries across PGs based on system health and other custom rules.
* Integration with all major Payment Gateways and Aggregators.
* Online Reports on Payment gateway performance, User behaviour analysis and Payments success rates across various dimensions.


### Installation

In the Wordpress administration panel:

1. Go to **WooCommerce** -> **Settings section**
2. Choose **Checkout** tab and scroll down to the **"Payment Gateways"** section
3. Choose **Settings** option next to the **Juspay** name
4. Enable and configure the plugin


### Important Notes

1. In Sandbox mode, if a refund is created - `"Refund via Juspay"`, then Juspay will notify back after 10-12 minutes through Webhook.
2. `AUTHENTICATION_FAILED` Webhook only fires if user click cancel instantly on the security code page. If the customer delays few seconds to cancel, a second PENDING_VBV Webhook is sent. This problem is automatically taken care of if *Return Processing* option is checked at gateway settings page. 


### Testing Manual Payment Update Button
1. Disable both Webhook and Return Processing from payment gateway settings page.
2. Create a order through wp frontend shop, complete the payment on juspay.
3. Login to Wp Admin -> WooCommerce -> Orders. Find your last order. The order should be at `Pending Payment` status. Click to view edit page, find the "Update payment status manually" button and click to update status.
4. Order should have the synchronized status as of Juspay. Look into order note for more info.
5. Do the same 2-4 process with different order total amount, specially with `RS 5551`, `RS 5552`, `RS 5553` to see failures, and any other value for success. 


### Support

If you have any questions or issues, feel free to contact our technical support: ** support@juspay.in **

Contributors: juspay.in

Tags: woocommerce, juspay, payment, payment gateway
