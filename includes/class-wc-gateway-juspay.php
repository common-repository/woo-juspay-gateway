<?php
/**
 * WooCommerce Juspay Payment Gateway.
 *
 * @class       WC_Gateway_Juspay
 * @extends     WC_Payment_Gateway
**/


class WC_Gateway_Juspay extends WC_Payment_Gateway {

	public function __construct() {
		$this->id                 = 'juspay';
		$this->has_fields         = false;
		$this->order_button_text  = __('Proceed to Juspay', 'juspay');
		$this->method_title       = __('Juspay', 'juspay');
		$this->method_description = __('Official Juspay payment gateway for WooCommerce.', 'juspay');
		$this->supports           = array(
			'products',
			'refunds',
		);
		$this->icon               = WC_JUSPAY_URL . '/assets/images/juspay-logo.png';

		// Load the form fields
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		// Get setting values
		$this->title              = $this->get_option('title');
		$this->description        = $this->get_option( 'description' );
		$this->environment        = $this->get_option( 'environment' );
		$this->api_key            = $this->get_option( $this->environment . '_api_key' );

		/* display a message if staging environment is used */
		if ( 'staging' == $this->environment ) {
			/* translators: %s: Link to Juspay documentation page */
			$this->description .= ' ' . sprintf( __( 'SANDBOX ENABLED. You can use sandbox testing accounts only. See the <a href="%s">Juspay Docs</a> for more details.', 'juspay' ), 'https://www.juspay.in/docs/advanced/ec/account/index.html' );
			$this->description  = trim( $this->description );
		}

		add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );
		add_action( 'woocommerce_update_options_payment_gateways_'. $this->id, array( $this, 'process_admin_options' ) );

		if ( ! $this->is_available() ) {
			$this->enabled = 'no';
		} else {
			/* enable webhook processing */
			if ( 'yes' === $this->get_option( 'webhook_handler_enabled' ) ) {
				new WC_Gateway_Juspay_Webhook_Handler();
			}
			/* enable return url processing */
			if ( 'yes' === $this->get_option( 'return_handler_enabled' ) ) {
				new WC_Gateway_Juspay_Return_Handler();
			}
		}
	}


	/**
	 * Check if the payment gateway is available to be used.
	 *
	 */
	public function is_available() {
		if ( ! $this->api_key ) {
			return false;
		}

		return parent::is_available();
	}


	/**
	 * Return whether or not this gateway still requires setup to function.
	 *
	 * @return bool
	 */
	public function needs_setup() {
		if ( ! $this->api_key ) {
			return true;
		}

		return false;
	}


	/**
	 * Get after payment return url
	 *
	 * @methodExtended
	 */
	public function get_return_url( $order = null ) {
		if ( 'yes' === $this->get_option('return_handler_enabled') ) {
			return WC()->api_request_url('wc_gateway_juspay_return');
		}

		return parent::get_return_url( $order );
	}


	/**
	 * Process payment
	 *
	 * @param  $order_id Order id.
	 * @return bool|array
	 */
	public function process_payment( $order_id ) {
		$order          = wc_get_order( $order_id );
		$juspay_request = new WC_Gateway_Juspay_Request();
		$payment_url    = $juspay_request->get_payment_url( $order );

		if ( is_wp_error( $payment_url ) ) {
			wc_juspay()->log( 'Juspay Api Error: '. $payment_url->get_error_message() );

			if ( in_array( $payment_url->get_error_code(), array('juspayAuthenticationException', 'juspayAuthenticationException') ) ) {
				wc_add_notice( __('Internal Error: Please try later, or use other payment gateway.'), 'error' );
			} else if ( in_array( $payment_url->get_error_code(), array('juspayAPIConnectionException') ) ) {
				wc_add_notice( __('Could not communicate with Juspay. Please try later, or use other payment gateway.'), 'error' );
			} else if ( in_array( $payment_url->get_error_code(), array('juspayInvalidPaymentLink') ) ) {
				wc_add_notice( __( 'Juspay Error: Invalid payment link.', 'juspay' ), 'error' );
			} else {
				wc_add_notice( __('Could not process your request. Please try later, or use other payment gateway.'), 'error' );
			}
			return false;
		}

		wc_juspay()->log( 'Juspay processing payment for order # '. $order_id );

		return array(
			'result'   => 'success',
			'redirect' => $payment_url,
		);
	}


	/**
	 * Process a refund if supported.
	 *
	 * @param  int    $order_id Order ID.
	 * @param  float  $amount Refund amount.
	 * @param  string $reason Refund reason.
	 * @return bool|WP_Error
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		$order = wc_get_order( $order_id );

		if ( ! $this->can_refund_order( $order ) ) {
			return new WP_Error( 'error', __( 'Refund failed.', 'juspay' ) );
		}

		$refnd_ids = wc_get_orders( array(
			'type'   => 'shop_order_refund',
			'parent' => $order->get_id(),
			'limit'  => 1,
			'orderby' => 'date',
		    'order' => 'DESC',
			'return' => 'ids'
		));
		if ( ! empty( $refnd_ids ) ) {
			$refund_id = array_shift( $refnd_ids );
		} else {
			$refund_id = 0;
		}

		$juspay_request = new WC_Gateway_Juspay_Request();
		$result = $juspay_request->create_refund( $order_id, $amount, $refund_id );

		if ( is_wp_error( $result ) ) {
			$this->log( 'Refund Failed: ' . $result->get_error_message(), 'error' );
			return $result;
		}

		/*
		if ( $reason ) {
			$order->add_order_note( sprintf( __( 'Refunded %1$s for %2$s', 'juspay' ), $amount, $reason ) );
		} else {
			$order->add_order_note( sprintf( __( 'Refunded %1$s', 'juspay' ), $amount ) );
		}*/

		return true;
	}

	/**
	 * Can the order be refunded via Juspay?
	 *
	 * @param  WC_Order $order Order object.
	 * @return bool
	 */
	public function can_refund_order( $order ) {
		return $order && $order->has_status( wc_get_is_paid_statuses() ) && $order->get_transaction_id() && $this->api_key;
	}


	/**
	 * Intialize form fields
	 *
	 */
	public function init_form_fields() {
		$this->form_fields = include WC_JUSPAY_DIR . 'includes/settings-juspay.php';
	}


	/**
	 * Payment_scripts function.
	 *
	 * Outputs styles/scripts used for juspay payment
	 *
	 */
	public function payment_scripts() {
		if ( ! is_cart() && ! is_checkout() && ! isset( $_GET['pay_for_order'] ) ) {
			return;
		}

		// If Stripe is not enabled bail.
		if ( ! $this->is_available() ) {
			return;
		}

		wp_register_style( 'juspay_styles', WC_JUSPAY_URL . '/assets/css/juspay-styles.css', array(), WC_JUSPAY_VERSION );
		wp_enqueue_style( 'juspay_styles' );
	}


	/**
	 * Load admin scripts.
	 *
	 */
	public function admin_scripts() {
		$screen    = get_current_screen();
		$screen_id = $screen ? $screen->id : '';

		if ( 'woocommerce_page_wc-settings' !== $screen_id ) {
			return;
		}

		wp_enqueue_script( 'woocommerce_juspay_admin', WC_JUSPAY_URL . '/assets/js/juspay-admin-scripts.js', array(), WC_JUSPAY_VERSION, true );
	}
}
