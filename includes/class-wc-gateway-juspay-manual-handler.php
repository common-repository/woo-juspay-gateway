<?php
/**
 * WooCommerce Juspay Manual Handler.
 *
 * @class       WC_Gateway_Juspay_Manual_Handler
 * @extends     WC_Gateway_Juspay_Response
**/


class WC_Gateway_Juspay_Manual_Handler extends WC_Gateway_Juspay_Response {

	protected $handler_name = 'Manual';

	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ), 10, 2 );
		add_action( 'admin_notices', array( $this, 'admin_notices' ), 10 );
		add_action( 'admin_action_juspay_update_payment_status', array( $this, 'check_response' ) );
		add_action( 'wc_juspay_valid_manual_request', array( $this, 'valid_response' ) );
	}

	public function add_meta_boxes( $post_type, $post ) {
		if ( 'shop_order' == $post_type && 'juspay' == get_post_meta($post->ID, '_payment_method', true) ) {
        	add_meta_box(
				'juspay_payment_status', 
				__('Juspay Payment', 'juspay'),
				array( $this, 'render_juspay_payment_status' ), 
				'shop_order', 
				'side', 
				'core' 
			);
		}
    }

	public function admin_notices() {
		if ( isset ( $_REQUEST['error'] ) ) {
			printf( '<div class="error"><p>%s</p></div>', $_REQUEST['error'] );
		} elseif ( isset ( $_REQUEST['message'] ) && ! is_numeric( $_REQUEST['message'] ) ) {
			printf( '<div id="message" class="updated notice notice-success is-dismissible"><p>%s.</p><button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button></div>', $_REQUEST['message'] );
		}
	}

	public function render_juspay_payment_status( $post ) {
		printf( '<a href="%s" class="button button-primary">Update payment status manually</a>', add_query_arg( array( 'action' => 'juspay_update_payment_status' ) ) );
	}

	public function check_response() {
		$posted = array ( 'order_id' => $_REQUEST['post'] );
		if ( ! empty( $posted ) && $this->validate_posted( $posted ) ) {
			do_action( 'wc_juspay_valid_manual_request', $posted );
			exit;
		}

		wp_die( 'Juspay Manual Request Failure', 'Juspay Manual', array( 'response' => 500 ) );
	}


	/**
	 * Check Juspay IPN validity.
	 */
	protected function validate_posted( $posted ) {
		wc_juspay()->log( __( 'Checking Manual response is valid' ) );

		if ( ! $this->get_order_id_from_posted( $posted ) ) {
			wc_juspay()->log( __('Missing order id in Manual') );
		}

		return true;
	}


	/**
	 * There was a valid response.
	 *
	 * @param  object $posted Juspay posted.
	 */
	public function valid_response( $posted ) {
		$order_id = $this->get_order_id_from_posted( $posted );
		$order = wc_get_order( $order_id );

		/* bail of this order is currently being processed by other handler */
		if ( $this->is_order_processing( $order_id ) ) {
			wp_redirect( admin_url('post.php?post='. $order_id .'&action=edit') );
			exit;
		}

		// Lock the update so that webhook does not overtake current process
		$this->lock_order_process( $order_id );

		$juspay_request = new WC_Gateway_Juspay_Request();
		$juspay_order = $juspay_request->get_order( $order_id );

		if ( ! is_wp_error( $juspay_order ) ) {
			// format status name
			$status = $this->format_status( $juspay_order->status );

			// $status = 'charged';

			// Processing all status through return url
			if ( method_exists( $this, 'juspay_status_' . $status ) ) {
				call_user_func( array( $this, 'juspay_status_' . $status ), $order, $juspay_order );
			}

			// Unlock, we are done processing
			$this->unlock_order_process( $order_id );

			wp_redirect( admin_url('post.php?post='. $order_id .'&action=edit&message=' . urlencode( 'Payment status updated' ) ) );
			exit;

		} else {
			// Unlock, we are done processing
			$this->unlock_order_process( $order_id );

			wp_redirect( admin_url('post.php?post='. $order_id .'&action=edit&error=' . urlencode( $juspay_order->get_error_message() ) ) );
			exit;
		}
	}

	/**
	 * Get WooCommerce order object from juspay return posted.
	 *
	 * @param  object $posted Juspay posted.
	 * @return object WooCommerce Order object || NULL
	 */
	public function get_order_id_from_posted( $posted ) {
		return (int) $posted['order_id'];
	}
}

