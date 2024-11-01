<?php
/**
 * WooCommerce Juspay Response.
 *
 * @class       WC_Gateway_Juspay_Response
**/


class WC_Gateway_Juspay_Response {

	protected $handler_name = '';


	/**
	 * Handle a completed payment.
	 *
	 * @param WC_Order $order  Order object.
	 * @param array    $juspay_order Juspay Order object
	 */
	public function juspay_status_completed( $order, $juspay_order ) {
		if ( $order->has_status( wc_get_is_paid_statuses() ) ) {
			wc_juspay()->log( 'Aborting, Order #' . $order->get_id() . ' is already complete.' );
			return true;
		}

		if ( ! $this->validate_amount( $order, $juspay_order->amount ) ) {
			return new WP_Error('juspay_status_error', __('Amount miss-matched'));
		}

		if ( ! $this->validate_currency( $order, $juspay_order->currency ) ) {
			return new WP_Error('juspay_status_error', __('Currency miss-matched'));
		}


		$order->add_order_note( sprintf( __('Payment Completed via %s.', 'juspay' ), $this->handler_name ) );
		$order->payment_complete( $juspay_order->txnId );
		$this->add_payment_method_info( $order, $juspay_order );
		$this->add_payment_epg_info( $order, $juspay_order );

		if ( ! is_admin() ) {
			WC()->cart->empty_cart();
		}
	}


	/**
	 * Handle a pending payment.
	 *
	 * @param WC_Order $order  Order object.
	 * @param array    $juspay_order Juspay Order object
	 */
	public function juspay_status_pending( $order, $juspay_order ) {
		if ( $order->has_status( wc_get_is_paid_statuses() ) ) {
			wc_juspay()->log( 'Aborting, Order #' . $order->get_id() . ' is already complete.' );
			return;
		}

		$order->update_status( 
			'on-hold', 
			sprintf( __( 'Payment %s via %s.', 'juspay' ), $this->format_status_readable( $juspay_order->status ), $this->handler_name )
		);

		if ( ! is_admin() ) {
			WC()->cart->empty_cart();
		}
	}


	/**
	 * Handle a failed payment.
	 *
	 * @param WC_Order $order  Order object.
	 * @param array    $juspay_order Juspay Order object
	 */
	public function juspay_status_failed( $order, $juspay_order ) {
		$order->update_status( 
			'failed', 
			sprintf( __( 'Payment %s via %s.', 'juspay' ), $this->format_status_readable( $juspay_order->status ), $this->handler_name )
		);

		if ( ! empty( $juspay_order->bankErrorMessage ) ) {
			$order->add_order_note( sprintf( __('Payment Error: %s.', 'juspay' ), $juspay_order->bankErrorMessage ) );
		}
	}


	/**
	 * Handle a charged payment.
	 *
	 * @param WC_Order $order  Order object.
	 * @param array    $juspay_order Juspay Order object
	 */
	public function juspay_status_charged( $order, $juspay_order ) {
		return $this->juspay_status_completed( $order, $juspay_order );
	}


	/**
	 * Handle a pending payment.
	 *
	 * @param WC_Order $order  Order object.
	 * @param array    $juspay_order Juspay Order object
	 */
	public function juspay_status_pending_vbv( $order, $juspay_order ) {
		return $this->juspay_status_pending( $order, $juspay_order );
	}


	/**
	 * Handle a authorizing payment.
	 *
	 * @param WC_Order $order  Order object.
	 * @param array    $juspay_order Juspay Order object
	 */
	public function juspay_status_authorizing( $order, $juspay_order ) {
		return $this->juspay_status_pending( $order, $juspay_order );
	}


	/**
	 * Handle a new order.
	 *
	 * @param WC_Order $order  Order object.
	 * @param array    $juspay_order Juspay Order object
	 */
	public function juspay_status_new( $order, $juspay_order ) {
		return $this->juspay_status_pending( $order, $juspay_order );
	}


	/**
	 * Handle a failed payment (User did not complete authentication).
	 *
	 * @param WC_Order $order  Order object.
	 * @param array    $juspay_order Juspay Order object
	 */
	public function juspay_status_authentication_failed( $order, $juspay_order ) {
		$this->juspay_status_failed( $order, $juspay_order );
	}


	/**
	 * Handle a failed payment (User completed authentication, but bank refused the transaction).
	 *
	 * @param WC_Order $order  Order object.
	 * @param array    $juspay_order Juspay Order object
	 */
	public function juspay_status_authorization_failed( $order, $juspay_order ) {
		$this->juspay_status_failed( $order, $juspay_order );
	}


	/**
	 * Handle a failed payment (User input is not accepted by the underlying PG).
	 *
	 * @param WC_Order $order  Order object.
	 * @param array    $juspay_order Juspay Order object
	 */
	public function juspay_status_juspay_declined( $order, $juspay_order ) {
		$this->juspay_status_failed( $order, $juspay_order );
	}


	/**
	 * Handle refund.
	 *
	 * @param WC_Order $order  Order object.
	 * @param array    $juspay_order Juspay Order object
	 */
	public function juspay_status_refunded( $order, $juspay_order ) {
		// Only handle full refunds, not partial.
		if ( isset( $juspay_order->refunded ) && $juspay_order->refunded ) {
			$order->update_status( 'refunded', sprintf( __('FULL Refund processed via %s.', 'juspay' ), $this->handler_name ) );
		} else {
			$latest_refund = false;
			foreach ( $juspay_order->refunds as $refund ) {
				if ( $refund->status == 'SUCCESS' ) {
					if ( ! $latest_refund || $latest_refund->ref < $refund->ref ) {
						$latest_refund = $refund;
					}
				}
			}

			if ( $latest_refund ) {
				$order->add_order_note( sprintf( __('Refund Successful via %s. Amount: %s, Id: %s.', 'juspay' ), $this->handler_name, $latest_refund->amount, $latest_refund->id ) );
			}
		}
	}


	/**
	 * Handle failed refund.
	 *
	 * @param WC_Order $order  Order object.
	 * @param array    $juspay_order Juspay Order object
	 */
	public function juspay_status_refund_failed( $order, $juspay_order ) {
		$latest_refund = false;
		foreach ( $juspay_order->refunds as $refund ) {
			if ( $refund->status == 'FAILURE' ) {
				if ( ! $latest_refund || $latest_refund->ref < $refund->ref ) {
					$latest_refund = $refund;
				}
			}
		}

		if ( $latest_refund ) {
			$order->add_order_note( sprintf( __('Refund failed via %s. Amount: %s, Reason: %s, Id: %s.', 'juspay' ), $this->handler_name, $latest_refund->amount, $latest_refund->errorMessage, $latest_refund->id ) );
		}
	}


	public function add_payment_method_info( $order, $juspay_order ) {
		$order->add_order_note( sprintf( __('Juspay payment method: %s.', 'juspay' ), $juspay_order->paymentMethod ) );
	}


	public function add_payment_epg_info( $order, $juspay_order ) {
		if ( isset( $juspay_order->paymentGatewayResponse ) && isset( $juspay_order->paymentGatewayResponse->epgTxnId ) ) {
			$order->add_order_note( sprintf( __('epgTxnId: %s.', 'juspay' ), $juspay_order->paymentGatewayResponse->epgTxnId ) );
		}
	}


	public function format_status( $status ) {
		return strtolower( $status );
	}


	public function format_status_readable( $status ) {
		return ucwords( strtolower( str_replace( '_', ' ', $status ) ) );
	}

	public function verify_hmac( $params, $secret ) {
		$receivedHmac = $params['signature'];
		// UrlEncode key/value pairs
		$encoded_params;
		foreach ( $params as $key => $value ) {
			if ( $key != 'signature' && $key != 'signature_algorithm') {
				$encoded_params[urlencode($key)] = urlencode($value);
			}
		}

		ksort( $encoded_params );
		$serialized_params = "";
		foreach ( $encoded_params as $key => $value ) {
			$serialized_params = $serialized_params . $key . "=" . $value . "&";
		}

		$serialized_params = urlencode(substr($serialized_params, 0, -1));
		$computedHmac = base64_encode(hash_hmac('sha256', $serialized_params, $secret, true));
		$receivedHmac = urldecode($receivedHmac);
		return urldecode($computedHmac) == $receivedHmac;
	}

	public function get_juspay_response_key() {
		$settings = get_option( 'woocommerce_juspay_settings' );
		if ( isset( $settings['sandbox'] ) && $settings['sandbox'] === 'yes' ) {
			if ( ! empty( $settings['sandbox_response_key'] ) ) {
				return $settings['sandbox_response_key'];
			}
		} else {
			if ( ! empty( $settings['response_key'] ) ) {
				return $settings['response_key'];
			}
		}
	}


	/**
	 * Check payment amount from IPN matches the order.
	 *
	 * @param WC_Order $order  Order object.
	 * @param int      $amount Amount to validate.
	 */
	protected function validate_amount( $order, $amount ) {
		if ( number_format( $order->get_total(), 2, '.', '' ) !== number_format( $amount, 2, '.', '' ) ) {
			wc_juspay()->log( 'Payment error: Amounts do not match (gross ' . $amount . ')' );

			/* translators: %s: Amount. */
			$order->update_status( 'on-hold', sprintf( __( 'Validation error: Juspay amounts do not match (amount %s).', 'juspay' ), $amount ) );
			return false;
		}

		return true;
	}


	/**
	 * Check currency from WC Order matches Juspay order.
	 *
	 * @param WC_Order $order    Order object.
	 * @param string   $currency Currency code.
	 */
	protected function validate_currency( $order, $currency ) {
		if ( $order->get_currency() !== $currency ) {
			wc_juspay()->log( 'Payment error: Currencies do not match (sent "' . $order->get_currency() . '" | returned "' . $currency . '")' );

			/* translators: %s: currency code. */
			$order->update_status( 'on-hold', sprintf( __( 'Validation error: Juspay currencies do not match (code %s).', 'juspay' ), $currency ) );

			return false;
		}

		return true;
	}

	protected function is_order_processing( $order_id ) {
		if ( get_transient( 'juspay_processing_'. $order_id ) ) {
			return true;
		}

		return false;
	}

	protected function lock_order_process( $order_id ) {
		wc_juspay()->log( 'Locking order process for ' . $order_id );
		set_transient( 'juspay_processing_'. $order_id, true );
	}


	protected function unlock_order_process( $order_id ) {
		wc_juspay()->log( 'Unlocking order process for ' . $order_id );
		delete_transient( 'juspay_processing_'. $order_id );
	}
}
