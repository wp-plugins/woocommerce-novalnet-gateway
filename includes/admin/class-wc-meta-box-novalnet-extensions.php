<?php

 if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
 }

/**
 * Novalnet API extensions
 *
 * This file is used for processing the Novalnet api operations like
 * Capture, Void, Refund, Amount change etc.,
 *
 * Copyright (c) Novalnet
 *
 * This script is only free to the use for merchants of Novalnet. If
 * you have found this script useful a small recommendation as well as a
 * comment on merchant form would be greatly appreciated.
 *
 * @class 		Novalnet_Extensions
 * @package		Novalnet/Classes
 * @category	Class
 * @author 		Novalnet
 * @located at 	/includes/admin/
 */

 class Novalnet_Extensions {
	private $allowed_actions = array( 'manage_transaction', 'amount_change', 'trans_refund' );
	public $invoice_payments = array( 'novalnet_invoice', 'novalnet_prepayment');
	public $novalnet_log;

	function __construct() {
		add_action( 'wp_before_admin_bar_render', array( $this, 'call_custom_functions' ) );
        add_action( 'add_meta_boxes', array( &$this, 'novalnet_meta_boxes' ), 10, 2 );
        if ( NN_Fns()->global_settings['debug_log'] ) {
			$this->novalnet_log = new WC_Logger();
		}
	}

	/**
	 * Used to call the custom functions
	 * Calls from the hook "wp_before_admin_bar_render"
	 *
	 * @access public
	 * @param none
	 * @return void
	 */
	public function call_custom_functions() {
		global $novalnet_payments;
		if ( is_admin() ) {
			if ( isset( $_GET['novalnet_action'] ) && in_array( $_GET['novalnet_action'], $this->allowed_actions ) && isset( $_GET['post'] ) ) {
				$wc_order = new WC_Order( intval ( $_GET['post'] ) );
				if ( in_array( $wc_order->payment_method, $novalnet_payments ) ) {
					call_user_func_array( array( $this, 'perform_extension_actions' ), array($_REQUEST) );
				}
			}
		}
	}

	/**
	 * To add meta boxes for Extension process in shop-orders
	 * Calls from the hook "add_meta_boxes"
	 *
	 * @access public
	 * @param string $post_type
	 * @param object $post
	 * @return void
	 */
	public function novalnet_meta_boxes( $post_type, $post ) {
		global $wpdb, $wc_order, $data_exists_in_novalnet_table;
		$wc_order = new WC_Order( $post->ID );
		$data_exists_in_novalnet_table = true;

		$trans_details = $wpdb->get_row( $wpdb->prepare( "SELECT gateway_status, amount, callback_amount, refunded_amount FROM {$wpdb->prefix}novalnet_transaction_detail WHERE order_no=%d", $post->ID ), ARRAY_A );

		if ( empty( $trans_details ) ) {
			$data_exists_in_novalnet_table = false;
			$trans_details['gateway_status'] = get_post_meta( $wc_order->id, '_nn_status_code', true);
			$tmp_amount = get_post_meta( $wc_order->id, '_nn_order_amount', true );
			$ref_amount = get_post_meta( $wc_order->id, '_nn_ref_amount', true );
			$trans_details['amount'] = ( ( empty($tmp_amount ) && empty( $ref_amount ) ) ? $wc_order->order_total * 100 :$tmp_amount);
			$trans_details['callback_amount'] = get_post_meta( $wc_order->id, '_nn_callback_amount', true);
		}

		if ( ! empty( $trans_details['gateway_status'] ) ) {

			if ( in_array( $wc_order->payment_method , array('novalnet_cc', 'novalnet_sepa', 'novalnet_invoice', 'novalnet_prepayment') ) && in_array( $trans_details['gateway_status'] , array(99, 98, 91 ) ) ) {
				add_meta_box(
					'novalnet_trans_confirm_process',
					__('Manage transaction process', 'wc-novalnet'),
					array( $this, 'novalnet_trans_confirm_process'),
					'shop_order',
					'side',
					'default'
				);
			}


			$paid_amount = in_array( $wc_order->payment_method, $this->invoice_payments ) ? $trans_details['callback_amount'] : $trans_details['amount'];
			if ( 100 == $trans_details['gateway_status'] && $paid_amount > 0  ) {
				add_meta_box(
					'novalnet_trans_refund_process',
					__('Refund process', 'wc-novalnet'),
					array( $this, 'novalnet_trans_refund_process'),
					'shop_order',
					'side',
					'default'
				);
			}

			$nn_flag = get_post_meta($wc_order->id, '_nn_version',true);

			$callback_amount = $wpdb->get_var( $wpdb->prepare( "SELECT sum(amount) FROM {$wpdb->prefix}novalnet_callback_history WHERE order_no=%s" ,$wc_order->id ) );
			$trans_details['callback_amount'] = ! empty( $callback_amount ) ?  $callback_amount : 0;
			if ( !empty ($trans_details['refunded_amount'] ) )
				$trans_details['amount'] += $trans_details['refunded_amount'];

			if ( $data_exists_in_novalnet_table && ! empty( $nn_flag ) &&( ( in_array( $wc_order->payment_method , $this->invoice_payments ) && 100 == $trans_details['gateway_status'] && $trans_details['callback_amount'] < $trans_details['amount']   ) || ( $wc_order->payment_method == 'novalnet_sepa' && 99 == $trans_details['gateway_status'] ) ) ) {
				add_meta_box(
					'novalnet_trans_amount_change_process',
					in_array($wc_order->payment_method, $this->invoice_payments) ?  __('Change the amount / due date ', 'wc-novalnet') : __('Amount update', 'wc-novalnet'),
					array( $this, 'novalnet_trans_amount_change_process'),
					'shop_order',
					'side',
					'default'
				);
			}
		}
	}

	/**
	 * Add template for managing the transaction (VOID / CAPTURE )
	 *
	 * @access public
	 * @param object $post
	 * @return void
	 */
	public function novalnet_trans_confirm_process( $post ) {

		echo '<div id="novalnet_loading_div"></div>';
		woocommerce_wp_select( array( 'id' => 'txn_status', 'label' => __( 'Please select status', 'wc-novalnet' ) . ' : ', 'options' => array( '' => '--' . __( 'Select', 'wc-novalnet' ) . '--', '100' => __( 'Confirm', 'wc-novalnet' ), '103' => __( 'Cancel', 'wc-novalnet' ) ) ) );

		woocommerce_wp_hidden_input( array( 'id' => 'nn_manage_txn_url', 'value' => admin_url( 'post.php?post=' . $post->ID . '&action=edit&novalnet_action=manage_transaction' ) ) );

		woocommerce_wp_hidden_input( array( 'id' => 'nn_manage_txn_err', 'value' => __('Please select status!', 'wc-novalnet') ) );

		?>
		<p> <a id="nn_manage_transaction" class="button button-primary tips" data-tip=""><?php echo __( 'Update', 'wc-novalnet' ); ?></a> </p>
		<?php
	}

	/**
	 * Add template for transaction amount / due date update option
	 *
	 * @access public
	 * @param object $post
	 * @return void
	 */
	public function novalnet_trans_amount_change_process( $post ) {
		global $wpdb, $wc_order;

		$trans_details =  $wpdb->get_row( $wpdb->prepare( "SELECT amount,refunded_amount FROM {$wpdb->prefix}novalnet_transaction_detail WHERE order_no=%d", $post->ID ), ARRAY_A );

		$callback_amount = $wpdb->get_var( $wpdb->prepare( "SELECT sum(amount) FROM {$wpdb->prefix}novalnet_callback_history WHERE order_no=%s" ,$wc_order->id ) );
		$trans_details['callback_amount'] = ! empty( $callback_amount ) ?  $callback_amount : 0;

		if ( !empty ($trans_details['refunded_amount'] ) )
			$trans_details['amount'] += $trans_details['refunded_amount'];

		$invoice_due_date = $wpdb->get_var( $wpdb->prepare( "SELECT invoice_due_date FROM {$wpdb->prefix}novalnet_invoice_details WHERE order_no=%d", $post->ID ) );

		$amount = in_array( $wc_order->payment_method, $this->invoice_payments ) ? ( $trans_details['amount'] - $trans_details['callback_amount'] ): $trans_details['amount'];

		echo '<div id="novalnet_loading_div"></div>';

		woocommerce_wp_hidden_input( array( 'id' => 'nn_amt_change_url', 'value' => admin_url( 'post.php?post=' . $post->ID . '&action=edit&novalnet_action=amount_change' ) ) );

		woocommerce_wp_hidden_input( array( 'id' => 'nn_amt_change_err', 'value' => __('The amount is invalid', 'wc-novalnet') ) );

		woocommerce_wp_text_input( array( 'id' => 'nn_txn_amount', 'label' => __( 'Update transaction amount', 'wc-novalnet' ) . ' : ', 'description' => __('(in cents)','wc-novalnet'), 'value' => $amount ) );

		$message =  __('Are you sure you want to change the order amount?', 'wc-novalnet');

		if ( in_array( $wc_order->payment_method, $this->invoice_payments ) )  :

			woocommerce_wp_hidden_input( array( 'id' => 'nn_due_date_err', 'value' => __('Due date is not valid', 'wc-novalnet') ) );
			woocommerce_wp_hidden_input( array( 'id' => 'nn_past_date_err', 'value' => __('The date should be in future', 'wc-novalnet') ) );
			woocommerce_wp_hidden_input( array( 'id' => 'nn_current_date', 'value' => date( 'Y-m-d') ) );

			woocommerce_wp_text_input( array( 'id' => 'nn_due_date', 'label' => __( 'Transaction due date', 'wc-novalnet' ) . ' : ', 'placeholder' => 'YYYY-MM-DD', 'value' => date( 'Y-m-d',strtotime( $invoice_due_date ) ) ) );

			$message =  __('Are you sure you want to change the order amount or due date?', 'wc-novalnet');

		endif;
		woocommerce_wp_hidden_input( array( 'id' => 'nn_amt_upd_succ_msg', 'value' => $message ) );
		?>
		<p> <a id="nn_amount_change" class="button button-primary tips" data-tip=""><?php echo __( 'Update', 'wc-novalnet' ); ?></a> </p>
		<?php
	}

	/**
	 * Add template for transaction refund process
	 *
	 * @access public
	 * @param object $post
	 * @return void
	 */
	public function novalnet_trans_refund_process( $post ) {
		global $wc_order, $wpdb, $data_exists_in_novalnet_table;

		if ( $data_exists_in_novalnet_table ) {
			$trans_details = $wpdb->get_row( $wpdb->prepare ("SELECT amount, callback_amount, refunded_amount, date FROM {$wpdb->prefix}novalnet_transaction_detail WHERE order_no=%d" , $post->ID ), ARRAY_A );
			/*$callback_amount = $wpdb->get_var( $wpdb->prepare( "SELECT sum(amount) FROM {$wpdb->prefix}novalnet_callback_history WHERE order_no=%s" ,$wc_order->id ) );
			$trans_details['callback_amount'] = ! empty( $callback_amount ) ?  $callback_amount : 0;
			if ( !empty ($trans_details['refunded_amount'] ) )
				$trans_details['amount'] += $trans_details['refunded_amount'];*/

		} else {

			$trans_details = array(
				'callback_amount' => get_post_meta( $post->ID, '_nn_callback_amount', true ),
				'date'			  => $wc_order->order_date
			);
			$tmp_amount = get_post_meta( $wc_order->id, '_nn_order_amount', true );
			$ref_amount = get_post_meta( $wc_order->id, '_nn_ref_amount', true );
			$trans_details['amount'] = ( empty($tmp_amount ) ? $wc_order->order_total * 100 :$tmp_amount);
		}

		$amount = in_array( $wc_order->payment_method, $this->invoice_payments ) ? (int)$trans_details['callback_amount'] : (int)$trans_details['amount'];

		echo '<div id="novalnet_loading_div"></div>';

		woocommerce_wp_hidden_input( array( 'id' => 'nn_ref_url', 'value' => admin_url( 'post.php?post=' . $post->ID . '&action=edit&novalnet_action=trans_refund' ) ) );

		woocommerce_wp_hidden_input( array( 'id' => 'nn_invalid_err', 'value' => __('The amount is invalid', 'wc-novalnet') ) );

		woocommerce_wp_hidden_input( array( 'id' => 'nn_exceeds_err', 'value' => __('Refund cannot be processed, as the amount exceeds the paid amount!', 'wc-novalnet') ) );

		woocommerce_wp_hidden_input( array( 'id' => 'nn_paid_amt', 'value' => $amount 	) );

		if ( in_array( $wc_order->payment_method, array( 'novalnet_invoice', 'novalnet_prepayment', 'novalnet_ideal', 'novalnet_instantbank' ) ) ) :

			woocommerce_wp_select( array( 'id' => 'nn_ref_type', 'label' => __( 'Select the refund option', 'wc-novalnet' ) , 'options' => array( 'none'  => __( 'None', 'wc-novalnet' ), 'sepa'  => 'Novalnet ' . __( 'Direct Debit SEPA', 'wc-novalnet' ) ) ) );

		endif;

		woocommerce_wp_text_input( 	array( 'id' => 'nn_refund_amount', 'label' => __( 'Please enter the refund amount', 'wc-novalnet' ), 'description' => __('(in cents)','wc-novalnet'), 'value' => $amount ) );

		if ( time() > strtotime( "+1 days" , strtotime( $trans_details['date'] ) )  ) :

			woocommerce_wp_text_input( 	array( 'id' => 'nn_refund_ref', 'label' => __( 'Refund reference : ', 'wc-novalnet' ), 'value' => '' ) );

		endif;


		echo '<div id="nn_ref_sepa_form" style="display:none;" >';

			woocommerce_wp_hidden_input( array( 'id' => 'nn_sepa_err', 'value' =>  __('Your account details are invalid', 'wc-novalnet') ) );

			woocommerce_wp_text_input( array( 'id' => 'sepa_acc_holder', 'label' => __( 'Account holder : ', 'wc-novalnet' ), 'value' => $wc_order->billing_first_name . " " . $wc_order->billing_last_name ) );

			woocommerce_wp_text_input( array( 'id' => 'sepa_iban', 'label' => 'IBAN' ) );

			woocommerce_wp_text_input( array( 'id' => 'sepa_bic', 'label' => 'BIC' ) );

		echo '</div>';
		?>
		<p><a id="nn_refund" class="button button-primary tips" data-tip=""><?php echo __('Confirm', 'wc-novalnet'); ?></a></p>
		<?php
	}

	/**
	 * Perform the Extension actions and update the transaction information
	 *
	 * @access public
	 * @param array $request
	 * @return void
	 */
	public function perform_extension_actions( $request ) {
		global $wc_order, $wpdb, $data_exists_in_novalnet_table;
		$new_line = "\n";

		if ( $data_exists_in_novalnet_table ) {
			$input = 'vendor_id, auth_code, product_id, tariff_id, payment_id, tid, test_mode';
			if ( $request['novalnet_action'] == 'trans_refund' )
				$input .= ', amount, callback_amount, refunded_amount';

			$trans_details = $wpdb->get_row( $wpdb->prepare( "SELECT $input FROM {$wpdb->prefix}novalnet_transaction_detail WHERE order_no=%d" , $request['post'] ), ARRAY_A );

			$api_params = array(
				'vendor' 	=> $trans_details['vendor_id'],
				'auth_code'	=> $trans_details['auth_code'],
				'product' 	=> $trans_details['product_id'],
				'tariff' 	=> $trans_details['tariff_id'],
				'key'		=> $trans_details['payment_id'],
				'tid'		=> $trans_details['tid']
			);

		} else {

			$api_params = get_post_meta( $wc_order->id, '_nn_config_values', true);
			$api_params['tid'] = get_post_meta($wc_order->id, '_nn_order_tid', true);

			if ( $request['novalnet_action'] == 'trans_refund' ) {
				$tmp_amount = get_post_meta( $wc_order->id, '_nn_order_amount', true );
				$trans_details['amount'] = ( empty($tmp_amount ) ? $wc_order->order_total * 100 :$tmp_amount);
				$trans_details['callback_amount'] = get_post_meta( $wc_order->id, '_nn_callback_amount', true );
				$trans_details['refunded_amount'] = get_post_meta( $wc_order->id, '_nn_ref_amount', true );
			}

		}

		$api_params['status'] = isset( $request['confirm_status'] ) ? $request['confirm_status'] : 100 ;

		$validate = wc_novalnet_is_basic_validation( $api_params );
		if ( $validate ) {
			switch( $request['novalnet_action'] ) {
				case 'manage_transaction':
					$api_params['edit_status'] = 1;

					$ary_capt_response = wc_novalnet_submit_request( $api_params );
					$response_text = wc_novalnet_response_text( $ary_capt_response );

					if ( isset( $ary_capt_response['status'] ) && 100 == $ary_capt_response['status'] ) {
						if ( 100 == $request['confirm_status'] ) {
							if ( NN_Fns()->global_settings['debug_log'] ) {
								$this->novalnet_log->add('novalnetpayments', 'Transaction has been confirmed successfully for the order : ' . $wc_order->id );
							}
							$nn_confirm_message = sprintf( __('The transaction has been confirmed on %s','wc-novalnet'), date_i18n( get_option( 'date_format' ), strtotime( date( 'd-m-Y' ) ) ) );
							$status = NN_Fns()->global_settings['onhold_success_status'];
						}else{
							if ( NN_Fns()->global_settings['debug_log'] ) {
								$this->novalnet_log->add('novalnetpayments', 'Transaction has been cancelled for the order : ' . $wc_order->id );
							}
							$nn_confirm_message = sprintf( __('The transaction has been canceled on %s','wc-novalnet'), date_i18n( get_option( 'date_format' ), strtotime( date( 'd-m-Y' ) ) ) );
							$status = NN_Fns()->global_settings['onhold_cancel_status'];
							do_action( 'woocommerce_cancelled_order', $request['post'] );
							if (class_exists( 'WC_Subscriptions' ) ) {
								$subscription_key  = WC_Subscriptions_Manager::get_subscription_key( $wc_order->id, '' );
								$users_subscriptions = WC_Subscriptions_Manager::update_users_subscriptions( $wc_order->user_id, array( $subscription_key => array( 'status' => 'cancelled', 'end_date' => 0 ) ) );
							}
						}

						if ( $data_exists_in_novalnet_table ) {
							$wpdb->update( "{$wpdb->prefix}novalnet_transaction_detail",
								array(
									'gateway_status' => $request['confirm_status'],
									'active'	=> ( 103 == $request['confirm_status'] ) ? 0 : 1
								),
								array( 'order_no' => $request['post'] )
							);
						} else {
							update_post_meta( $wc_order->id, '_nn_order_amount', $wc_order->order_total * 100);
							update_post_meta( $wc_order->id, '_nn_callback_amount', (in_array( $wc_order->payment_method, $this->invoice_payments ) ? 0 : $wc_order->order_total * 100) );
							update_post_meta( $wc_order->id, '_nn_status_code',  $api_params['status']);
						}

						wc_novalnet_update_customer_notes( $request['post'], $nn_confirm_message );

						$wc_order->update_status( $status );



					} else {
						if ( !empty( $response_text ) )
							$wc_order->add_order_note( $response_text );
					}

				break;
				case 'amount_change':
					$api_params['edit_status'] = 1;
					$api_params['update_inv_amount'] = 1;
					$api_params['amount'] = $request['nn_upd_amount'];
					$api_params['due_date'] = ( isset( $request['invoice_due_date'] ) && $request['invoice_due_date'] != '') ? $request['invoice_due_date'] : '0000-00-00';

					$ary_upd_response = wc_novalnet_submit_request( $api_params );

					$response_text = wc_novalnet_response_text( $ary_upd_response );

					$ary_upd_status_response = wc_novalnet_perform_xmlrequest( array(
							'vendor_id' 	 => $api_params['vendor'],
							'vendor_authcode'=> $api_params['auth_code'],
							'product_id' 	 => $api_params['product'],
							'request_type'	 => 'TRANSACTION_STATUS',
							'tid' 			 => $api_params['tid']
						)
					);

					if ( 100 == $ary_upd_response['status'] ) {
						if ( in_array ( $wc_order->payment_method , $this->invoice_payments ) ) {
							$invoice_details = $wpdb->get_var( $wpdb->prepare( "SELECT invoice_bank_details FROM {$wpdb->prefix}novalnet_invoice_details WHERE order_no=%d", $request['post'] ) );

							$invoice_details = unserialize( $invoice_details );

							$novalnet_comments = $wc_order->payment_method_title . $new_line;
							$novalnet_comments .= __('Novalnet transaction ID : ', 'wc-novalnet') . $api_params['tid'] . $new_line;
							$novalnet_comments .= ( $invoice_details['test_mode'] )? __('Test order', 'wc-novalnet') : '' . $new_line;

							$novalnet_comments .= $new_line . __('Please transfer the amount to the below mentioned account details of our payment processor Novalnet', 'wc-novalnet') . $new_line;
							$novalnet_comments.= __('Due date : ', 'wc-novalnet') . date_i18n( get_option('date_format'), strtotime( $api_params['due_date'] ) ) . $new_line;
							$novalnet_comments .= __('Account holder : Novalnet AG', 'wc-novalnet') . $new_line;
							$novalnet_comments .= 'IBAN : ' . $invoice_details['bank_iban'] . $new_line;
							$novalnet_comments .= 'BIC : ' . $invoice_details['bank_bic'] . $new_line;
							$novalnet_comments .= 'Bank : ' . $invoice_details['bank_name'] . ' ' . $invoice_details['bank_city'] . $new_line;
							$novalnet_comments .= __('Amount : ', 'wc-novalnet') . strip_tags( woocommerce_price( sprintf( "%0.2f", $request['nn_upd_amount']/100 ) ) ) . $new_line;
							$novalnet_comments .= __('Reference 1 : ', 'wc-novalnet') . $invoice_details['invoice_ref'] . $new_line ;
							$novalnet_comments .= __('Reference 2 : TID ', 'wc-novalnet') . $api_params['tid'] . $new_line;
							$novalnet_comments .= __('Reference 3 : Order number ', 'wc-novalnet') . $invoice_details['seq_nr'] . $new_line . $new_line;

							wc_novalnet_update_customer_notes( $request['post'], $novalnet_comments, false );

							$wpdb->update( "{$wpdb->prefix}novalnet_invoice_details", array( 'amount' => $api_params['amount'], 'invoice_due_date' => $api_params['due_date'] ), array( 'order_no' => $request['post'] ) );
						}

						$nn_update_message = sprintf( __( 'The transaction amount %s has been updated successfully on %s','wc-novalnet' ), strip_tags( woocommerce_price( sprintf( "%0.2f", $request['nn_upd_amount']/100 ) ) )  ,date_i18n( get_option( 'date_format' ), strtotime( date('Y-m-d H:i:s') ) ) );

						$wpdb->update( "{$wpdb->prefix}novalnet_transaction_detail", array( 'gateway_status' => $ary_upd_status_response['status'], 'amount' => $api_params['amount'], 'refunded_amount'=> 0 ), array( 'order_no' => $request['post'] ) );

						wc_novalnet_update_customer_notes( $request['post'], $nn_update_message );

					} else {
						if ( !empty( $response_text ) )
							$wc_order->add_order_note( $response_text );
					}

				break;
				case 'trans_refund':
					$api_params['refund_request'] = 1;
					$api_params['refund_param'] = $request['nn_refund_amount'] ;
					if ( isset( $request['nn_refund_reference'] ) )
					$api_params['refund_ref'] = $request['nn_refund_reference'] ;
					if ( isset( $request['ref_type'] ) && $request['ref_type'] == 'sepa' ) {
						$api_params['account_holder'] 	= $request['sepa_holder'];
						$api_params['iban'] 			= $request['sepa_iban'];
						$api_params['bic'] 				= $request['sepa_bic'];
					}
					if ( isset( $request['refund_ref'] ) && $request['refund_ref'] != '' ) {
						$api_params['refund_ref'] = $request['refund_ref'];
					}
					$ary_ref_response = wc_novalnet_submit_request( $api_params );

					$response_text = wc_novalnet_response_text( $ary_ref_response );

					$ary_ref_status_response = wc_novalnet_perform_xmlrequest( array(
							'vendor_id' 	 => $api_params['vendor'],
							'vendor_authcode'=> $api_params['auth_code'],
							'product_id' 	 => $api_params['product'],
							'request_type'	 => 'TRANSACTION_STATUS',
							'tid' 			 => $api_params['tid']
						)
					);

					if ( 100 == $ary_ref_response['status'] ) {
						$refunded_amount_price =  strip_tags( woocommerce_price( sprintf( "%0.2f", $request['nn_refund_amount']/100 ) ) );

						$nn_ref_message =  sprintf( __('The refund has been executed for the TID: %s with the amount of %s.', 'wc-novalnet'), $api_params['tid'], $refunded_amount_price );

						if(  ! empty( $ary_ref_response['tid'] ) )
							$nn_ref_message .= sprintf( __('Your new TID for the refund amount: %s', 'wc-novalnet'), ( ( $wc_order->payment_method == 'novalnet_paypal' && !empty( $ary_ref_response['paypal_refund_tid'] ) )  ? $ary_ref_response['paypal_refund_tid'] : $ary_ref_response['tid'] ) );

						$update_data = array(
							'gateway_status' => $ary_ref_status_response['status'],
							'amount'		 => $trans_details['amount'] - $request['nn_refund_amount'],
							'callback_amount'=> $trans_details['callback_amount'] - $request['nn_refund_amount'],
							'refunded_amount'=> $trans_details['refunded_amount'] + $request['nn_refund_amount']
						);

						if ( $data_exists_in_novalnet_table ) {
							$wpdb->update( $wpdb->prefix . 'novalnet_transaction_detail', $update_data, array( 'order_no' => $request['post'] ) );
						} else {
							update_post_meta( $wc_order->id, '_nn_status_code', $update_data['gateway_status'] );
							update_post_meta( $wc_order->id, '_nn_order_amount', $update_data['amount'] );
							update_post_meta( $wc_order->id, '_nn_callback_amount', $update_data['callback_amount'] );
							update_post_meta( $wc_order->id, '_nn_ref_amount', $update_data['refunded_amount'] );
						}

						wc_novalnet_update_customer_notes( $request['post'], $nn_ref_message );

						if ( $data_exists_in_novalnet_table ) {
							$updated_amount = $wpdb->get_var( $wpdb->prepare( "SELECT amount FROM {$wpdb->prefix}novalnet_transaction_detail WHERE order_no=%d" , $request['post'] ) );
						} else {
							$updated_amount = get_post_meta( $wc_order->id, '_nn_order_amount', true );
						}

						if ( empty( $updated_amount ) || 103 == $ary_ref_status_response['status'] ) {
							$wc_order->update_status( 'refunded' );
							if (class_exists( 'WC_Subscriptions' ) ) {
								$subscription_key  = WC_Subscriptions_Manager::get_subscription_key( $wc_order->id, '' );
								$users_subscriptions = WC_Subscriptions_Manager::update_users_subscriptions( $wc_order->user_id, array( $subscription_key => array( 'status' => 'cancelled', 'end_date' => 0 ) ) );
							}
						}
					} else {
						if ( !empty( $response_text ) )
							$wc_order->add_order_note( $response_text );
 					}
				break;
			}
		}
	}
 }
 return new Novalnet_Extensions();
?>