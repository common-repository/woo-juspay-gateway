<?php
/**
 * WooCommerce Juspay Return Handler.
 *
 * @class       WC_Gateway_Juspay_Return_Handler
 * @extends     WC_Gateway_Juspay_Response
**/


class WC_Gateway_Juspay_Return_Handler extends WC_Gateway_Juspay_Response {

	protected $handler_name = 'Return';

	public function __construct() {
		add_action( 'woocommerce_api_wc_gateway_juspay_return', array( $this, 'check_response' ) );
		add_action( 'wc_juspay_valid_return_request', array( $this, 'valid_response' ) );
	}

	public function check_response() {
		$posted = array_filter ( $_REQUEST );
		if ( ! empty( $posted ) && $this->validate_posted( $posted ) ) {
			do_action( 'wc_juspay_valid_return_request', $posted );
			exit;
		}

		wp_die( 'Juspay Return Request Failure', 'Juspay Return', array( 'response' => 500 ) );
	}


	/**
	 * Check Juspay IPN validity.
	 */
	protected function validate_posted( $posted ) {
		wc_juspay()->log( __( 'Checking Return response is valid' ) );

		if ( ! $this->get_order_key_from_posted( $posted ) ) {
			wc_juspay()->log( __('Missing order key in Return') );
		}

		return true;
	}


	/**
	 * There was a valid response.
	 *
	 * @param  object $posted Juspay posted.
	 */
	public function valid_response( $posted ) {
		wc_juspay()->log( 'Juspay posted: ' . wc_print_r( $posted, true ) );

		$order_key = $this->get_order_key_from_posted( $posted );

		/* bail of this order is currently being processed by other handler */
		if ( $this->is_order_processing( $order_key ) ) {
			$order_id = wc_get_order_id_by_order_key( $order_key );
			$order = wc_get_order( $order_id );
			if ( $order ) {
				wp_redirect( $order->get_checkout_order_received_url() );
			}
			exit;
		}

		// Lock the update so that webhook does not overtake current process
		$this->lock_order_process( $order_key );


		$order_id = wc_get_order_id_by_order_key( $order_key );
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wc_juspay()->log( 'Order not found' );
			wc_add_notice( __('Requested order is unavailable. Start a new order', 'juspay'), 'error' );
			wp_redirect( get_permalink( wc_get_page_id( 'shop' ) ) );
			exit;
		}

		wc_juspay()->log( 'Found order #' . $order->get_id() );

		$juspay_request = new WC_Gateway_Juspay_Request();
		$juspay_order = $juspay_request->get_order( $order_id );

		wc_juspay()->log( 'Juspay order: ' . wc_print_r( $juspay_order, true ) );

		if ( ! is_wp_error( $juspay_order ) ) {
			// format status name
			$status = $this->format_status( $juspay_order->status );

			wc_juspay()->log( 'Juspay status: ' . $juspay_order->status );

			// Processing all status through return url
			if ( method_exists( $this, 'juspay_status_' . $status ) ) {
				call_user_func( array( $this, 'juspay_status_' . $status ), $order, $juspay_order );
			}

			// Add a screen message for failed payments
			if ( in_array( $status, array( 'authentication_failed', 'authorization_failed', 'juspay_declined' ) ) ) {
				$error = __( 'Payment failed.', 'juspay' );
				if ( ! empty( $juspay_order->bankErrorMessage ) ) {
					$error = 'Payment failed: '. $juspay_order->bankErrorMessage . '.';
				}

				wc_add_notice( $error, 'error' );
			}

			// Unlock, we are done processing
			$this->unlock_order_process( $order_key );

			wp_redirect( $order->get_checkout_order_received_url() );

		} else {
			wc_juspay()->log( 'Error #' . $juspay_order->get_error_message() );

			// Unlock, we are done processing
			$this->unlock_order_process( $order_key );

			wc_add_notice( sprintf( __( 'Juspay Error: %s', 'juspay' ), $juspay_order->getMessage() ), 'error' );
			wp_redirect( get_permalink( wc_get_page_id( 'cart' ) ) );
		}

		exit;
	}

	/**
	 * Get WooCommerce order object from juspay return posted.
	 *
	 * @param  object $posted Juspay posted.
	 * @return object WooCommerce Order object || NULL
	 */
	public function get_order_key_from_posted( $posted ) {
		return (string) $posted['order_id'];
	}
}
