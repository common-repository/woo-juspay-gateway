<?php
/**
 * WooCommerce Juspay Webhook Handler.
 *
 * @class       WC_Gateway_Juspay_Webhook_Handler
 * @extends     WC_Gateway_Juspay_Response
**/


class WC_Gateway_Juspay_Webhook_Handler extends WC_Gateway_Juspay_Response {

	protected $handler_name = 'Webhook';

	protected $available_events = array(
		'TXN_CREATED',
		'ORDER_SUCCEEDED',
		'ORDER_FAILED',
		'ORDER_REFUNDED',
		'ORDER_REFUND_FAILED',
		'REFUND_MANUAL_REVIEW_NEEDED',
	);

	public function __construct() {
		add_action( 'woocommerce_api_wc_gateway_juspay_webhook', array( $this, 'check_response' ) );
		add_action( 'wc_juspay_valid_webhook_request', array( $this, 'valid_response' ) );
	}

	public function check_response() {
		/* delay five second to allow return handler to work */
		// sleep(5);

		$payload = file_get_contents( 'php://input' );
		if ( ! empty( $payload ) ) {
			$payload = json_decode( $payload, true );
		}

		if ( ! empty($payload) && $this->validate_payload( $payload ) ) {
			do_action( 'wc_juspay_valid_webhook_request', $payload );
			exit;
		}

		wp_die( 'Juspay Webhook Request Failure', 'Juspay Webhook', array( 'response' => 500 ) );
	}


	/**
	 * Check Juspay IPN validity.
	 */
	protected function validate_payload( $payload ) {
		wc_juspay()->log( __( 'Checking Webhook response is valid' ) );

		if ( ! $this->get_order_key_from_payload( $payload ) ) {
			wc_juspay()->log( __('Missing order key in Webhook') );
		}

		return true;
	}


	/**
	 * There was a valid response.
	 *
	 * @param  object $payload Juspay payload.
	 */
	public function valid_response( $payload ) {
		wc_juspay()->log( 'Juspay payload: ' . wc_print_r( $payload, true ) );

		$order_key = $this->get_order_key_from_payload( $payload );

		/* bail of this order is currently being processed by other handler */
		if ( $this->is_order_processing( $order_key ) ) {
			wc_juspay()->log( 'Webhook request ignored as the order is being processed by other handler. key - ' . $order_key );
			wp_die( 'Juspay Webhook Request Ignored', 'Juspay Webhook', array( 'response' => 200 ) );
		}

		// Lock the update so that webhook does not overtake current process
		$this->lock_order_process( $order_key );


		$order_id = wc_get_order_id_by_order_key( $order_key );
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			// Unlock, we are done processing
			$this->unlock_order_process( $order_key );
			wc_juspay()->log( 'Order not found' );
			wp_die( 'Juspay Webhook Request Processed', 'Juspay Webhook', array( 'response' => 200 ) );
		}

		wc_juspay()->log( 'Found order #' . $order->get_id() );

		$juspay_request = new WC_Gateway_Juspay_Request();
		$juspay_order = $juspay_request->get_order( $order_id );

		if ( ! is_wp_error( $juspay_order ) ) {
			// format status name
			$status = $this->format_status( $juspay_order->status );

			/* Override status to perform refunds */
			if ( 'ORDER_REFUNDED' === $payload['event_name'] ) {
				$status = 'refunded';
			} else if ( 'ORDER_REFUND_FAILED' === $payload['event_name'] ) {
				$status = 'refund_failed';
			}

			wc_juspay()->log( 'Juspay event: ' . $payload['event_name'] );
			wc_juspay()->log( 'Juspay status: ' . $status );

			if ( method_exists( $this, 'juspay_status_' . $status ) ) {
				call_user_func( array( $this, 'juspay_status_' . $status ), $order, $juspay_order );
			}

		} else {
			wc_juspay()->log( 'Error #' . $juspay_order->get_error_message() );
		}

		// Unlock, we are done processing
		$this->unlock_order_process( $order_key );

		/* Alway return a status header 200 to notify juspay that we have received their request */
		wp_die( 'Juspay Webhook Request Processed', 'Juspay Webhook', array( 'response' => 200 ) );
	}

	/**
	 * Get WooCommerce order object from juspay webhook payload.
	 *
	 * @param  object $payload Juspay payload.
	 * @return object WooCommerce Order object || NULL
	 */
	public function get_order_key_from_payload( $payload ) {
		return (string) $payload['content']['order']['order_id'];
	}
}
