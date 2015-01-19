<?php
/**
 * Novalnet Callback Script for WooCommerce shop system
 *
 * NOTICE
 *
 * This script is used for real time capturing of parameters passed
 * from Novalnet AG after Payment processing of customers.
 *
 * This script is only free to the use for Merchants of Novalnet AG
 *
 * If you have found this script useful a small recommendation as well
 * as a comment on merchant form would be greatly appreciated.
 *
 * Please contact sales@novalnet.de for enquiry or info
 *
 * ABSTRACT: This script is called from Novalnet, as soon as a payment
 * done for payment methods
 * An email will be sent if an error occurs
 *
 * @category   Wordpress_Woocommerce
 * @package    Novalnet
 * @version    2.0.0
 * @copyright  Copyright (c)  Novalnet AG. (https://www.novalnet.de)
 * @license    GPLv2
 */
 require_once( 'wp-load.php' );

 global $wpdb;
 $process_test_mode  = false; // false|true; adapt: set to false for go-live
 $process_debug_mode = false; # Update into true to debug mode
 $line_break = empty ( $_SERVER['HTTP_HOST'] )? PHP_EOL : '<br/>';
 $ary_capture_params = $_REQUEST;
 $new_line = "\n";

 //Reporting Email Addresses Settings (mandatory;adapt for your need)
 $shop_info		= ' - Woocommerce';	//adapt if necessary
 $email_from_address  = ''; //sender email address., mandatory, adapt it
 $email_to_address    = ''; //recipient email address., mandatory, adapt it
 $email_subject   = 'Novalnet Callback Script Access Report';  //adapt if necessary;

 if ( isset ( $ary_capture_params['debug_mode'] ) && $ary_capture_params['debug_mode'] ) {
	$process_test_mode  = true;
	$process_debug_mode = true;
	$email_from_address = 'testadmin@novalnet.de';
	$email_to_address = 'test@novalnet.de';
 }

 $woo_vendor_script = new novalnet_vendor_script( $ary_capture_params );

 if(isset($ary_capture_params['vendor_activation']) && $ary_capture_params['vendor_activation'] == 1) {
	$woo_vendor_script->update_aff_account_activation_detail( $ary_capture_params );
	return true;
 } else {

	$woo_trans_history = $woo_vendor_script->get_order_reference();
	$woo_capture_params = $woo_vendor_script->get_capture_params();

	if ( ! empty ( $woo_trans_history ) ) {

		$order_id 	= $woo_trans_history['order_no'];
		$woo_order 	= new WC_Order( $order_id);
		$woo_capture_params['currency'] = isset ( $woo_capture_params['currency'] ) ? $woo_capture_params['currency'] : 'EUR';

		if(isset($woo_trans_history['tid']) && !empty($woo_trans_history['tid']))
			$woo_tid_status = $woo_vendor_script->perform_transaction_status_call( $woo_trans_history['tid'], $order_id );
		$curr_payment_level = $woo_vendor_script->get_payment_type_level();
		if ( $curr_payment_level === 2 && $woo_capture_params['status'] == 100 ) {
			if ( $woo_capture_params['payment_type'] == 'INVOICE_CREDIT') {
				if ( $woo_tid_status['status'] == 100){
					if ( !empty ( $woo_trans_history['refunded_amount'] ) )
						$woo_trans_history['order_paid_amount'] = $woo_trans_history['order_paid_amount'] - $woo_trans_history['refunded_amount'];
						$sum_amount = ( $woo_trans_history['order_paid_amount'] + $woo_capture_params['amount'] ) ;

					if ( $woo_capture_params['subs_billing'] == 1 ) {
						#### Step1: THE SUBSCRIPTION IS RENEWED, PAYMENT IS MADE, SO JUST CREATE A NEW ORDER HERE WITHOUT A PAYMENT PROCESS AND SET THE ORDER STATUS AS PAID ####

						#### Step2: THIS IS OPTIONAL: UPDATE THE BOOKING REFERENCE AT NOVALNET WITH YOUR ORDER_NO BY CALLING NOVALNET GATEWAY, IF U WANT THE USER TO USE ORDER_NO AS PAYMENT REFERENCE ###

						#### Step3: ADJUST THE NEW ORDER CONFIRMATION EMAIL TO INFORM THE USER THAT THIS ORDER IS MADE ON SUBSCRIPTION RENEWAL ###
					}else{

						if ( $woo_trans_history['order_paid_amount'] < (int)$woo_trans_history['amount']) {

							$callback_comments = $new_line . 'Novalnet Callback Script executed successfully for the TID: ' . $woo_trans_history['tid']. ' with amount '. sprintf('%.2f',( $woo_capture_params['amount']/100)) . " " .$woo_capture_params['currency']. ' on '.date_i18n(get_option('date_format'), strtotime(date('Y-m-d'))).'. Please refer PAID transaction in our Novalnet Merchant Administration with the TID: '. $woo_capture_params['tid'] . $new_line  ;

							$woo_order->add_order_note( $callback_comments);

							if( $woo_order->customer_note )
								$woo_order->customer_note . $new_line;

							$woo_order->customer_note .= $callback_comments;

							$update_notes = array(
								'ID' 			=> $order_id,
								'post_excerpt' 	=> $woo_order->customer_note,
							);

							wp_update_post( $update_notes);

							if (  $sum_amount >= $woo_trans_history['amount']){
								if ( $sum_amount > $woo_trans_history['amount'])
									$callback_comments .= $line_break .'<strong> Customer paid amount is greater than order amount. </strong>'. $line_break;

								$new_line = "\n";
								$novalnet_comments  = $woo_order->payment_method_title . $new_line;
								$novalnet_comments .= __('Novalnet Transaction ID : ', 'novalnet') . $woo_trans_history['tid'] . $new_line;
								$novalnet_comments .= (isset ( $woo_capture_params['test_mode'])  && $woo_capture_params['test_mode']) ? __('Test order', 'novalnet') : '';


								$woo_order->customer_note = html_entity_decode( $novalnet_comments, ENT_QUOTES, 'UTF-8');
								// adds order note
								$woo_order->add_order_note( $woo_order->customer_note);

								/** Update Novalnet Transaction details into shop database	 */
								$nn_order_notes = array(
									'ID' => $order_id,
									'post_excerpt' => $woo_order->customer_note
								);
								wp_update_post( $nn_order_notes);

								$woo_order->update_status( $woo_trans_history['change_current_status']);
							}

							$woo_vendor_script->woo_callback_final_process( $woo_trans_history['tid'], $order_id, $callback_comments);
						}
						$woo_vendor_script->debug_error('Novalnet callback received. Novalnet callback script executed already.');
					}
				}else{
					$woo_vendor_script->debug_error('Novalnet callback received. Status Not Valid!!');
				}
			}

			//Subscription renewal of level 0 payments
			if (  $woo_capture_params['subs_billing'] == 1 ) {
				### Step1: THE SUBSCRIPTION IS RENEWED, PAYMENT IS MADE, SO JUST CREATE A NEW ORDER HERE WITHOUT A PAYMENT PROCESS AND SET THE ORDER STATUS AS PAID ####

				#### Step2: THIS IS OPTIONAL: UPDATE THE BOOKING REFERENCE AT NOVALNET WITH YOUR ORDER_NO BY CALLING NOVALNET GATEWAY, IF U WANT THE USER TO USE ORDER_NO AS PAYMENT REFERENCE ###

				#### Step3: ADJUST THE NEW ORDER CONFIRMATION EMAIL TO INFORM THE USER THAT THIS ORDER IS MADE ON SUBSCRIPTION RENEWAL ###
			}
			$error = 'Novalnet callback received. Payment type ( '.$woo_capture_params['payment_type'].' ) is not applicable for this process!';
			$woo_vendor_script->debug_error( $error);
		} else if ( $curr_payment_level === 1 ) { //level 1 payments - Type of Charge backs

			$callback_comments = $new_line . 'Novalnet callback received. Chargeback was executed successfully for the TID: '.$woo_capture_params['tid_payment'].' amount: '. sprintf('%.2f',( $woo_capture_params['amount']/100)).' ' .$woo_capture_params['currency']  .' on ' . date_i18n(get_option('date_format'), strtotime(date('Y-m-d'))). '. The subsequent TID: '.$woo_capture_params['tid'] . $new_line;

			$woo_order->add_order_note( $callback_comments);

			if( $woo_order->customer_note )
				$woo_order->customer_note . $new_line;

			$woo_order->customer_note .= $callback_comments;

			$update_notes = array(
				'ID' 			=> $order_id,
				'post_excerpt' 	=> $woo_order->customer_note,
			);

			wp_update_post( $update_notes);


			$woo_vendor_script->woo_callback_final_process( $woo_trans_history['tid'], $order_id, $callback_comments);

		} else if ( $curr_payment_level === 0 )  {

			if ( empty ( $woo_order->customer_note ) ) { //For Communication Failure

				$order_tid = (( $woo_capture_params['payment_type'] == 'INVOICE_START') ? $woo_capture_params['tid_payment'] : $woo_capture_params['tid'] );
				$test_mode = (isset ( $woo_capture_params['test_mode']) && $woo_capture_params['test_mode'] == 1) ? 1 : 0;

				$cur_lang = get_bloginfo('language');
				$txn_message = (substr($cur_lang, 0, 2) == 'en') ? 'Novalnet Transaction ID : ' : 'Novalnet Transaktions-ID : ';
				// Update comments in customer note
				$woo_order->customer_note  = "\n" . $woo_trans_history['payment_title'] ;
				$woo_order->customer_note  .= "\n" . $txn_message. $order_tid ;

				if ( isset($woo_tid_status['status']) && $woo_tid_status['status'] != '100'){
					$message = isset ( $woo_capture_params['status_text']) ? $woo_capture_params['status_text'] : (isset ( $woo_capture_params['status_desc']) ? $woo_capture_params['status_desc'] : (isset ( $woo_capture_params['status_message']) ? $woo_capture_params['status_message'] : ''));
					if ( $woo_capture_params['status'] != 100 && !empty ( $message))
						$woo_order->customer_note  .= "\n" . $message ;
				}
				$test_message = (substr($cur_lang, 0, 2) == 'en') ?  'Test order' : 'Testbestellung';
				$woo_order->customer_note  .= ( $test_mode) ? ("\n" .  $test_message . "\n") : '';
				$update_notes = array('ID' => $order_id, 'post_excerpt' => $woo_order->customer_note, 'post_status' => $woo_trans_history['change_current_status']);
				wp_update_post( $update_notes);

				// update in the order note
				$woo_order->add_order_note( $woo_order->customer_note);

				$options = get_option('nn_global_configurations');

				$key = array('novalnet_paypal' => 34, 'novalnet_banktransfer' => 33, 'novalnet_ideal' => 49, 'novalnet_cc' => 6, 'novalnet_sepa' => 37, 'novalnet_invoice' => 27, 'novalnet_prepayment' => 27 );

				$wpdb->insert( $wpdb->prefix .'novalnet_transaction_detail', array(
					'vendor_id' => get_option('novalnet_vendor_id'),
					'auth_code' => get_option('novalnet_auth_code'),
					'tid' => $order_tid,
					'gateway_status' => isset( $woo_tid_status['status'] ) ? $woo_tid_status['status'] : '',
					'subs_id' => (!empty ( $woo_tid_status['subs_id']) ? $woo_tid_status['subs_id'] : ''),
					'status' => $woo_capture_params['status'],
					'test_mode' => $test_mode,
					'active' => 1 ,
					'product_id' => get_option('novalnet_product_id'),
					'tariff_id' => get_option('novalnet_tariff_id'),
					'payment_id' => $key[ $woo_order->payment_method ],
					'payment_type' => $woo_order->payment_method,
					'amount' => $woo_order->order_total * 100,
					'callback_amount' => !in_array( $woo_capture_params['payment_type'], array('INVOICE_START', 'INVOICE_CREDIT')) ? $woo_order->order_total * 100 : 0,
					'refunded_amount' => 0,
					'currency' => get_woocommerce_currency(),
					'customer_id' => ( $woo_order->user_id ) ? $woo_order->user_id  : 0,
					'customer_email' => $woo_order->billing_email,
					'order_no' => $order_id,
					'date' => date('Y-m-d H:i:s') ,
					)
				);
				
				$callback_comments =  $new_line . ' Novalnet callback received. ';

				if( $woo_capture_params['status'] == 100 ) {
					$woo_order->update_status( $woo_trans_history['change_current_status']);
					$callback_comments .= $new_line . $woo_trans_history['payment_title'] . ' payment status updated' . $new_line;
					$woo_order->add_order_note( $callback_comments );
					
				} else {
					$woo_order->update_status( 'cancelled' );
					$callback_comments .= $new_line . $woo_trans_history['payment_title'] . ' payment cancelled ' . $new_line;
					$woo_order->add_order_note( $callback_comments );
				}

				$update_notes = array(
					'ID' 			=> $order_id,
					'post_excerpt' 	=> $woo_order->customer_note,
				);

				wp_update_post( $update_notes);

				$tid = (isset($woo_trans_history['tid'])) ? $woo_trans_history['tid'] : '';
				$woo_vendor_script->woo_callback_final_process( $tid, $order_id, $callback_comments);

			} else if (  $woo_capture_params['status'] == 100  ) {
				if (  $woo_capture_params['subs_billing'] == 1 ) { //Subscription renewal of level 0 payments

					### Step1: THE SUBSCRIPTION IS RENEWED, PAYMENT IS MADE, SO JUST CREATE A NEW ORDER HERE WITHOUT A PAYMENT PROCESS AND SET THE ORDER STATUS AS PAID ####

					#### Step2: THIS IS OPTIONAL: UPDATE THE BOOKING REFERENCE AT NOVALNET WITH YOUR ORDER_NO BY CALLING NOVALNET GATEWAY, IF U WANT THE USER TO USE ORDER_NO AS PAYMENT REFERENCE ###

					#### Step3: ADJUST THE NEW ORDER CONFIRMATION EMAIL TO INFORM THE USER THAT THIS ORDER IS MADE ON SUBSCRIPTION RENEWAL ###

				}else if ( $woo_capture_params['payment_type'] == 'PAYPAL' ) {  ### PayPal payment success ###

					if ( $woo_trans_history['order_paid_amount'] < (int)$woo_trans_history['amount'] ) {

						// Update callback comments in order status history table
						$callback_comments =  $new_line . "Novalnet Callback Script executed successfully for the TID: " .$woo_capture_params['shop_tid'] ." with amount: ". sprintf('%.2f',( $woo_capture_params['amount']/100)).' '.$woo_capture_params['currency'] . " on " . date('Y-m-d H:i:s') . " Please refer PAID transaction in our Novalnet Merchant Administration with the TID:" . $woo_capture_params['tid'] . $new_line;

						$woo_order->add_order_note( $callback_comments);

						if( $woo_order->customer_note )
							$woo_order->customer_note . $new_line;

						$woo_order->customer_note .=  $callback_comments;

						$update_notes = array(
							'ID' 			=> $order_id,
							'post_excerpt' 	=> $woo_order->customer_note,
						);

						wp_update_post( $update_notes);


						$woo_order->update_status( $woo_trans_history['change_current_status']);

						$woo_vendor_script->woo_callback_final_process( $woo_trans_history['tid'], $order_id, $callback_comments);
					}
					$woo_vendor_script->debug_error('Novalnet callback received. Order already Paid.');
				}
			} else if ( $woo_capture_params['status'] != 100 && in_array( $woo_capture_params['payment_type'], array( PAYPAL, IDEAL, ONLINE_TRANSFER ) )  ) {
				$callback_comments = '';
				if ( isset( $woo_capture_params['status_text']) ) {
					$callback_comments = $woo_capture_params['status_text'] . $new_line ;
					$woo_order->add_order_note( $new_line . 'Novalnet callback received.' .  $callback_comments);
				}
				$woo_order->update_status( 'cancelled' );
				$woo_vendor_script->debug_error( $new_line . 'Novalnet callback received.' . $callback_comments );
			}
			$woo_vendor_script->debug_error('Novalnet Callbackscript received. Payment type ( '.$woo_capture_params['payment_type'].' ) is not applicable for this process!');

		} else if ( $woo_capture_params['payment_type'] == 'SUBSCRIPTION_STOP' ) { //Cancellation of a Subscription

			 ### UPDATE THE STATUS OF THE USER SUBSCRIPTION ###

		}else{
			$woo_vendor_script->debug_error('Novalnet callback received. Callback Script executed already.');
		}
	}else {
		/* Error section : Due to order reference not found from the shop database  */
		$woo_vendor_script->debug_error('Novalnet callback received. Order Reference not exist!');
	}
 }

class novalnet_vendor_script {

	/* Level - 0 Payment types */
	protected $ary_payments = array('CREDITCARD','INVOICE_START','DIRECT_DEBIT_SEPA','GUARANTEED_INVOICE_START','PAYPAL','ONLINE_TRANSFER','IDEAL','EPS','NOVALTEL_DE','PAYSAFECARD');

	/* Level - 1 Payment types */
	protected $ary_chargebacks = array('RETURN_DEBIT_SEPA','REVERSAL','CREDITCARD_BOOKBACK','CREDITCARD_CHARGEBACK','REFUND_BY_BANK_TRANSFER_EU','COLLECTION_REVERSAL_DE','COLLECTION_REVERSAL_AT','NOVALTEL_DE_CHARGEBACK');

	/* Level - 2 Payment types */
	protected $ary_collections = array('INVOICE_CREDIT','GUARANTEED_INVOICE_CREDIT','CREDIT_ENTRY_CREDITCARD','CREDIT_ENTRY_SEPA','DEBT_COLLECTION_SEPA','DEBT_COLLECTION_CREDITCARD','DEBT_COLLECTION_DE','NOVALTEL_DE_CB_REVERSAL');

	protected $ary_payment_groups = array(
		'novalnet_cc' => array('CREDITCARD', 'CREDITCARD_BOOKBACK', 'CREDITCARD_CHARGEBACK', 'CREDIT_ENTRY_CREDITCARD','DEBT_COLLECTION_CREDITCARD'),
		'novalnet_sepa' => array('DIRECT_DEBIT_SEPA', 'RETURN_DEBIT_SEPA','CREDIT_ENTRY_SEPA', 'DEBT_COLLECTION_SEPA'),
		'novalnet_ideal' => array('IDEAL'),
		'novalnet_banktransfer' => array('ONLINE_TRANSFER', 'REFUND_BY_BANK_TRANSFER_EU'),
		'novalnet_paypal' => array('PAYPAL'),
		'novalnet_prepayment' => array('INVOICE_START','GUARANTEED_INVOICE_START', 'INVOICE_CREDIT', 'GUARANTEED_INVOICE_CREDIT'),
		'novalnet_invoice' => array('INVOICE_START','GUARANTEED_INVOICE_START', 'INVOICE_CREDIT', 'GUARANTEED_INVOICE_CREDIT'),
   );

	/** @Array Callback Capture parameters */
	protected $woo_capture_params = array();

	/* @IP-ADDRESS Novalnet IP, is a fixed value, DO NOT CHANGE!!!!! */
	protected $ip_allowed = array( '195.143.189.210' , '195.143.189.214' );

	protected $required_params = array( 'vendor_id', 'status', 'amount', 'payment_type', 'tid' );

	function __construct( $ary_capture = array() ) {

		if ( in_array( $ary_capture['payment_type'], array_merge( $this->ary_chargebacks, array('INVOICE_CREDIT') ) ) ) {
				array_push( $this->required_params, 'tid_payment' );
		}

		$ary_capture = $this->sanitize_capture_params( $ary_capture );
		$this->woo_capture_params = self::validate_capture_params( $ary_capture );
	}

	public function sanitize_capture_params( $captureparams = array() ) {
		foreach( $captureparams as $key => $value ) {
			$ary_capture[ $key] = trim( $value );
		}
		return $ary_capture;
	}

	/* Perform parameter validation process
	* Set empty value id not exists in ary_capture
	* @return array
	*/
	public function validate_capture_params( $ary_capture = array() ) {
		global $process_test_mode, $line_break;
		$error = '';
		//Validate Authenticated IP
		if ( !in_array( self::get_client_ip(), $this->ip_allowed ) && $process_test_mode == false ) {

			echo "Novalnet callback received. Unauthorised access from the IP " . self::get_client_ip();
			exit;
		}

		if ( $this->required_params ) {
			foreach ( $this->required_params as $k => $v ) {
				if ( empty ( $ary_capture[ $v ] ) ) {
					$error .= 'Required param ( ' . $v . '  ) missing!' . $line_break;
				}
			}
			if ( !empty ( $error ) ) {
				self::debug_error( $error );
			}
		}

		if ( !in_array( $ary_capture['payment_type'], array_merge( $this->ary_payments, $this->ary_chargebacks, $this->ary_collections) ) ) {
			$error = 'Novalnet callback received. Payment type ( ' . $ary_capture['payment_type'] . ' ) is mismatched!';
			self::debug_error( $error );
		}

		if ( isset ( $ary_capture['status'] ) && $ary_capture['status'] < 0 )  {
			self::debug_error('Novalnet callback received. Status is not valid');
		}

		if ( in_array( $ary_capture['payment_type'], array_merge( array( 'INVOICE_CREDIT' ) , $this->ary_chargebacks ) ) ) {
			if ( !is_numeric( $ary_capture['tid_payment'] ) || strlen( $ary_capture['tid_payment'] ) != 17 ) {
				$error = 'Novalnet callback received. Invalid TID ['. $ary_capture['tid_payment'] . '] for Order.';
				self::debug_error( $error );
			}
		}

		if ( strlen( $ary_capture['tid'] ) != 17 || !is_numeric( $ary_capture['tid'] ) ) {
			$error = 'Novalnet callback received. TID [' . $ary_capture['tid'] . '] is not valid.';
			self::debug_error( $error );
		}

		if ( ! $ary_capture['amount'] || !is_numeric( $ary_capture['amount'] ) || $ary_capture['amount'] < 0) {
		  $error = 'Novalnet callback received. The requested amount ('. $ary_capture['amount'] .') is not valid';
		  self::debug_error( $error );
		}

		if ( isset ( $ary_capture['signup_tid'] ) && $ary_capture['signup_tid'] != '' ) {
			$ary_capture['shop_tid'] = $ary_capture['signup_tid'];
		}  else if ( in_array( $ary_capture['payment_type'], array_merge( $this->ary_chargebacks, array( 'INVOICE_START', 'INVOICE_CREDIT' ) ) ) ) { #Invoice
			$ary_capture['shop_tid'] = $ary_capture['tid_payment'];
		} else if ( isset ( $ary_capture['tid'] ) && $ary_capture['tid'] != '' ) {
			$ary_capture['shop_tid'] = $ary_capture['tid'];
		}

		return $ary_capture;
	}

	public function get_order_reference() {
		global $wpdb;

		$org_tid = $this->woo_capture_params['shop_tid'];

		$txn_details = $wpdb->get_row( 'SELECT order_no,payment_type,amount,tid,callback_amount,refunded_amount FROM ' . $wpdb->prefix . 'novalnet_transaction_detail WHERE tid=' . $org_tid ,ARRAY_A );

		if ( empty ( $txn_details ) ) {
			$woo_order_id = ( ! empty ( $this->woo_capture_params['order_no'] ) ) ? $this->woo_capture_params['order_no'] : ( ! empty ( $this->woo_capture_params['order_id'] ) ? $this->woo_capture_params['order_id'] : '' );

			if (!empty( $woo_order_id ) ) {

				$table_name = $wpdb->postmeta;

				$post_id = $wpdb->get_var( "SELECT post_id FROM `$table_name` where meta_value='" . $woo_order_id . "' AND (meta_key='_order_number' OR meta_key='_order_number_formatted')");

				$order_id = ! empty ( $post_id ) ?  $post_id : $woo_order_id;

				$txn_details = $wpdb->get_row( 'SELECT order_no,payment_type,amount,tid FROM ' . $wpdb->prefix . 'novalnet_transaction_detail WHERE order_no=' . $order_id ,ARRAY_A );

				if ( empty ( $txn_details ) ) {

					$wc_order = new WC_Order( $order_id );

					if ( ! empty ( $wc_order->id ) ) {
						if ( empty ( $wc_order->customer_note ) ) {
							$collective_order_details = $this->collect_relavent_order_datas( $wc_order );
							$collective_order_details['order_no'] = $wc_order->id;
							return ( ( ! empty ( $collective_order_details ) ) ? $collective_order_details : array() );
						}
					}else{
						self::debug_error('Novalnet callback received. Transaction mapping failed');
					}
				}else{
					self::debug_error('Novalnet callback received. Transaction mapping failed');
				}
			}
		}

		if ( !empty ( $txn_details ) ) {

			$wc_order = new WC_Order( $txn_details['order_no'] );

			$collective_order_details = $this->collect_relavent_order_datas( $wc_order, $txn_details );

			if ( ! in_array( $this->woo_capture_params['payment_type'], $this->ary_payment_groups[ $collective_order_details['payment_type'] ] ) ) {
				$error = 'Novalnet callback received. Payment Type [' . $this->woo_capture_params['payment_type'] . '] is not valid.';
				self::debug_error( $error );
			}

			if ( isset ( $this->woo_capture_params['order_no']) && !empty ( $this->woo_capture_params['order_no'])){
				$org_id = !empty ( $collective_order_details['seq_nr']) ? $collective_order_details['seq_nr'] : $collective_order_details['order_no'];
				if ( $org_id != $this->woo_capture_params['order_no']){
					self::debug_error('Novalnet callback received. Order no is not valid.');
				}
			}

			if ( ! empty ( $this->woo_capture_params['shop_tid'] ) && $this->woo_capture_params['shop_tid'] != $collective_order_details['tid'] ) {
				self::debug_error('Novalnet callback received. TID is not valid.');
			}
		}

		return ( ( ! empty ( $collective_order_details) ) ? $collective_order_details : array() );
	}

	public function collect_relavent_order_datas( $wc_order, $txn_details = array() ) {
		global $wpdb;
		$txn_details['seq_nr'] = ! empty ( $wc_order->order_number_formatted) ? $wc_order->order_number_formatted : $wc_order->order_number;
		$txn_details['post_status'] = $wc_order->post_status;
		$txn_details['status'] = $wc_order->status;
		$txn_details['payment_title'] = $wc_order->payment_method_title;
		$txn_details['org_payment_method'] = $wc_order->payment_method;

		$tmp_payment = empty ( $txn_details['payment_type'] ) ? $wc_order->payment_method : $txn_details['payment_type'];

		$payment_settings = get_option( 'woocommerce_' . $tmp_payment . '_settings' );

		$txn_details['change_current_status'] = ( isset ( $payment_settings['callback_order_status'] ) && $this->woo_capture_params['payment_type'] == 'INVOICE_CREDIT') ? $payment_settings['callback_order_status'] : $payment_settings['set_order_status'];

		$txn_details['order_paid_amount'] = 0;
		$payment_type_level = self::get_payment_type_level();
		if ( in_array( $payment_type_level, array( 0 , 2 ) ) ) {
			$callback_amount = $wpdb->get_var( 'SELECT sum(amount) FROM ' . $wpdb->prefix . 'novalnet_callback_history WHERE order_no=' . $wc_order->id );
			$txn_details['order_paid_amount'] = !empty ( $callback_amount ) ?  $callback_amount : 0;
		}
		return ( ( ! empty ( $txn_details) ) ? $txn_details : array() );
	}

	public function get_capture_params() {
		return $this->woo_capture_params;
	}

	/*
	* Get given payment_type level for process
	*
	* @return Integer
	*/
	function get_payment_type_level() {
		if ( ! empty ( $this->woo_capture_params ) ) {
			if ( in_array( $this->woo_capture_params['payment_type'], $this->ary_payments ) ) {
				return 0;
			}  else if ( in_array( $this->woo_capture_params['payment_type'], $this->ary_chargebacks ) ) {
				return 1;
			} else if ( in_array( $this->woo_capture_params['payment_type'], $this->ary_collections ) ) {
				return 2;
			}
		}
		return 0;
	}

	public function debug_error( $error_msg ) {
		global $process_debug_mode;
		if ( $process_debug_mode ) {
			echo $error_msg;
		}
		exit;
	}

	public function update_aff_account_activation_detail( $capture_params ) {
		global $wpdb;

		$wpdb->insert( $wpdb->prefix . 'novalnet_aff_account_detail',
			array(
				'vendor_id' 		=> $capture_params['vendor_id'],
				'vendor_authcode' 	=> $capture_params['vendor_authcode'],
				'product_id' 		=> $capture_params['product_id'],
				'product_url' 		=> $capture_params['product_url'],
				'activation_date' 	=> isset( $capture_params['activation_date'] ) ? date( 'Y-m-d H:i:s', strtotime($capture_params['activation_date']) ) : '',
				'aff_id' 			=> $capture_params['aff_id'],
				'aff_authcode' 		=> $capture_params['aff_authcode'],
				'aff_accesskey' 	=> $capture_params['aff_accesskey'],
			)
		);

		$callback_comments =  $new_line . 'Novalnet callback script executed successfully with Novalnet account activation information.' . $new_line;

		//Send notification mail to Merchant
		$this->send_notification_mail( array(
			'comments' => $callback_comments,
			'order_no' => '-',
		) );
		$this->debug_error($callback_comments);

		return true;
	}

	public function woo_callback_final_process( $tid, $order_id, $comments ) {

		//Send notification mail to Merchant
		$this->send_notification_mail( array(
			'comments' => $comments,
			'order_no' => $order_id,
		) );

		$this->log_callback_details( $this->woo_capture_params, $tid, $order_id );

		$this->debug_error( $comments );

	}

	public function do_curl( $url, $data ){

		$ch = curl_init( $url  );
		curl_setopt( $ch, CURLOPT_POST, 1 );
		curl_setopt( $ch, CURLOPT_POSTFIELDS, $data );
		curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, 0 );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, false );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER,1 );

		$response = curl_exec( $ch );
		curl_close( $ch );
		return $response;
	}

	public function perform_transaction_status_call( $tid = null, $order_id ) {
		global $wpdb;

		$txn_datas = $wpdb->get_row( 'SELECT vendor_id,auth_code,product_id,tid FROM ' . $wpdb->prefix . 'novalnet_transaction_detail WHERE order_no=' . $order_id, ARRAY_A );

		if ( empty ( $txn_datas['vendor_id']) || empty ( $txn_datas['auth_code']) || empty ( $txn_datas['product_id']) || empty ( $txn_datas['tid'])){

			$txn_datas = array(
				'vendor_id' => get_option('novalnet_vendor_id'),
				'auth_code' => get_option('novalnet_auth_code'),
				'product_id'=> get_option('novalnet_product_id'),
				'tid' 		=> $tid
			);
		}

		$req_data = array('vendor_id' => $txn_datas['vendor_id'], 'vendor_authcode' => $txn_datas['auth_code'], 'product_id' => $txn_datas['product_id'], 'request_type' => 'TRANSACTION_STATUS', 'tid' => $txn_datas['tid']);

		if ( $txn_datas['vendor_id'] != '' && $txn_datas['auth_code'] != '' && $txn_datas['product_id'] != '' && $txn_datas['tid'] != ''){

			$xml_request = '<?xml version="1.0" encoding="UTF-8"?><nnxml><info_request>';
			foreach ( $req_data as $k => $v){
				$xml_request .= '<' . $k . '>' . $v . '</' . $k . '>';
			}
			$xml_request .= '</info_request></nnxml>';

			$paygate_url = ( ( ! is_ssl() ) ? 'http://' : 'https://' ) . 'payport.novalnet.de/nn_infoport.xml';

			$response = $this->do_curl( $paygate_url, $xml_request );

			$data = json_decode( json_encode( (array)simplexml_load_string( $response ) ),1 );
			return $data;

		}
		return array();

	}

	/*
	* Log callback process in novalnet_callback_history table
	*
	* @return boolean
	*/
	function log_callback_details( $datas, $org_tid, $order_no ) {
		global $wpdb;

		if ( !empty ( $datas ) ) {

			$update_amount = $wpdb->get_row( 'SELECT amount, callback_amount FROM ' . $wpdb->prefix.'novalnet_transaction_detail WHERE order_no=' . $order_no );

			$callback_amount = ($datas['payment_type'] == 'PAYPAL') ? $update_amount->amount :  $datas['amount'];

			$wpdb->insert( $wpdb->prefix . 'novalnet_callback_history', array( 'payment_type' => $datas['payment_type'], 'status' => $datas['status'], 'callback_tid' => $datas['tid'], 'org_tid' => $org_tid, 'amount' => $callback_amount, 'currency' => $datas['currency'], 'product_id' => $datas['product_id'], 'order_no' => $order_no, 'date' => date('Y-m-d H:i:s') ) );

			$wpdb->update( $wpdb->prefix . 'novalnet_transaction_detail', array( 'callback_amount' => ( $update_amount->callback_amount + $datas['amount'] ) ), array( 'order_no' => $order_no ) );
			return true;

		}
		return false;
	}

	/*
	* Function to return the client IP Address
	*
	* @return Array
	*/
	public function get_client_ip() {

		$ipaddress = '';
		if (isset ( $_SERVER['HTTP_CLIENT_IP'] ) && $_SERVER['HTTP_CLIENT_IP'] )
			$ipaddress = $_SERVER['HTTP_CLIENT_IP'];
		else if ( isset ( $_SERVER['HTTP_X_FORWARDED_FOR'] ) && $_SERVER['HTTP_X_FORWARDED_FOR'] )
			$ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
		else if ( isset ( $_SERVER['HTTP_X_FORWARDED'] ) && $_SERVER['HTTP_X_FORWARDED'] )
			$ipaddress = $_SERVER['HTTP_X_FORWARDED'];
		else if ( isset ( $_SERVER['HTTP_FORWARDED_FOR'] ) && $_SERVER['HTTP_FORWARDED_FOR'] )
			$ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
		else if ( isset ( $_SERVER['HTTP_FORWARDED'] ) && $_SERVER['HTTP_FORWARDED'] )
			$ipaddress = $_SERVER['HTTP_FORWARDED'];
		else if ( isset ( $_SERVER['REMOTE_ADDR'] ) && $_SERVER['REMOTE_ADDR'] )
			$ipaddress = $_SERVER['REMOTE_ADDR'];
		else
			$ipaddress = 'UNKNOWN';

		return $ipaddress;
	}

	/*
	* Send notification mail to Merchant
	*
	* @return boolean
	*/
	public function send_notification_mail( $datas = array() ) {
		global $process_test_mode, $email_from_address, $email_to_address, $email_subject, $shop_info;

		$email_subject .= $shop_info;
		if( $process_test_mode ) {
			$email_subject .= ' - TEST';
		}

		$headers  = 'Content-Type: text/html; charset=iso-8859-1' . "\r\n";
		if( !empty ( $email_from_address ) )

			$headers .= 'From: ' . $email_from_address . "\r\n";

		$email_content = $datas['comments'];

		if ( $email_to_address != '' ) {

			$ack = wp_mail( $email_to_address, $email_subject, $email_content, $headers ); # WordPress Sending Mail Function
			if( $ack )
				echo 'Mail Sent!.';
			else
				echo 'mail not sent!';
		} else {
			echo 'mail not sent!';
		}
		return true;

	}
}
?>
