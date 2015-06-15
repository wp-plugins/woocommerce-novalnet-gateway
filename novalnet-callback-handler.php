<?php

 if ( ! defined('ABSPATH') ) {
    exit; // Exit if accessed directly
 }

/**
 * Novalnet Vendor script
 *
 * This file is used for real time capturing of parameters passed from
 * Novalnet after payment processing of customers
 *
 * Copyright (c) Novalnet
 *
 * This script is only free to the use for merchants of Novalnet. If
 * you have found this script useful a small recommendation as well as a
 * comment on merchant form would be greatly appreciated.
 *
 * @package		Novalnet
 * @author 		Novalnet
 */

 add_action('woocommerce_api_novalnet_callback', 'handle_callback_request' );

 /**
  * Handle the callback request triggered from Novalnet
  * calls from "woocommerce_api_novalnet_callback"
  *
  * @param $ary_capture_params
  * @return boolean
  */
 function handle_callback_request( $ary_capture_params ) {
	global $wpdb, $process_test_mode, $process_debug_mode, $line_break;
	$process_test_mode  = get_option('novalnet_callback_test_mode');
	$process_debug_mode = get_option('novalnet_callback_debug_mode');
	$line_break 		= empty($_SERVER['HTTP_HOST']) ? PHP_EOL : '<br />';

	unset( $ary_capture_params['wc_api'] );

	if ( isset( $ary_capture_params['debug_mode'] ) && $ary_capture_params['debug_mode'] ) {
		$process_test_mode  = true;
		$process_debug_mode = true;
	}

	$novalnet_handler_obj = new Novalnet_Callback_handler( $ary_capture_params );

	if ( isset( $ary_capture_params['vendor_activation'] ) && $ary_capture_params['vendor_activation'] ) {
		$novalnet_handler_obj->update_aff_account_activation_detail( $ary_capture_params );
	} else {

		$novalnet_order_info = $novalnet_handler_obj->get_order_reference();
		$novalnet_params = $novalnet_handler_obj->get_capture_params();

		if ( ! empty( $novalnet_order_info ) ) {

			$order_id 	= $novalnet_order_info['sequence_order_no'];
			$post_id 	= $novalnet_order_info['order_no'];
			$woo_order 	= new WC_Order( $post_id );
			$new_line = "\n";

			$novalnet_params['currency'] = isset( $novalnet_params['currency'] ) ? $novalnet_params['currency'] : $novalnet_order_info['currency'];

			$curr_payment_level = $novalnet_handler_obj->get_payment_type_level();

			if ( $curr_payment_level === 2 && 100 == $novalnet_params['status'] ) { //level 2 payments - Type of credit entry

				if ( $novalnet_params['payment_type'] == 'INVOICE_CREDIT' ) {

					$callback_paid_amount = ( ! empty( $novalnet_order_info['refunded_amount'] )  ? ( $novalnet_order_info['callback_paid_amount'] - $novalnet_order_info['refunded_amount'] ) : $novalnet_order_info['callback_paid_amount'] );

					$sum_amount = $callback_paid_amount+ $novalnet_params['amount'] ;

					if ( $novalnet_params['subs_billing'] == 1 ) {

						### Step1: THE SUBSCRIPTION IS RENEWED, PAYMENT IS MADE, SO JUST CREATE A NEW ORDER HERE WITHOUT A PAYMENT PROCESS AND SET THE ORDER STATUS AS PAID ###

						### Step2: THIS IS OPTIONAL: UPDATE THE BOOKING REFERENCE AT NOVALNET WITH YOUR ORDER_NO BY CALLING NOVALNET GATEWAY, IF U WANT THE USER TO USE ORDER_NO AS PAYMENT REFERENCE ###

						### Step3: ADJUST THE NEW ORDER CONFIRMATION EMAIL TO INFORM THE USER THAT THIS ORDER IS MADE ON SUBSCRIPTION RENEWAL ###

					}else{

						if ( $callback_paid_amount < (int)$novalnet_order_info['amount'] ) {

							$callback_comments = $new_line . sprintf( __('Novalnet Callback Script executed successfully for the TID: %s with amount %s %s on %s. Please refer PAID transaction in our Novalnet Merchant Administration with the TID: %s', 'wc-novalnet' ), $novalnet_order_info['tid'], sprintf('%0.2f',( $novalnet_params['amount']/100)), $novalnet_params['currency'], date_i18n( get_option('date_format'), strtotime(date('Y-m-d'))), $novalnet_params['tid'] ) . $new_line  ;

							wc_novalnet_update_customer_notes( $post_id, $callback_comments );

							if (  $sum_amount >= $novalnet_order_info['amount'] ) {

								$novalnet_comments  = $woo_order->payment_method_title . $new_line;
								$novalnet_comments .= __('Novalnet transaction ID : ', 'wc-novalnet') . $novalnet_order_info['tid'] . $new_line;

								$novalnet_comments .= ( isset( $novalnet_params['test_mode'] )  && $novalnet_params['test_mode'] ) ? __('Test order', 'wc-novalnet') : '';

								if ( $sum_amount > $novalnet_order_info['amount'] )
									$callback_comments .= $line_break .'<strong> Customer paid amount is greater than order amount. </strong>'. $line_break;
								$novalnet_comments = $new_line . $novalnet_comments;
								update_post_meta( $post_id, '_nn_invoice_comments', $novalnet_comments );
								$woo_order->update_status( $novalnet_order_info['new_order_status']);
							}

							$novalnet_handler_obj->send_notification_mail( array(
								'comments' => $callback_comments,
								'order_no' => $order_id,
							) );

							$novalnet_handler_obj->log_callback_details( $novalnet_params, $post_id );
							$novalnet_handler_obj->debug_error( $callback_comments );
						}
						$novalnet_handler_obj->debug_error('Novalnet callback script executed already.');
					}
				}

				if ( $novalnet_params['subs_billing'] == 1 ) {
					### Step1: THE SUBSCRIPTION IS RENEWED, PAYMENT IS MADE, SO JUST CREATE A NEW ORDER HERE WITHOUT A PAYMENT PROCESS AND SET THE ORDER STATUS AS PAID ###

					### Step2: THIS IS OPTIONAL: UPDATE THE BOOKING REFERENCE AT NOVALNET WITH YOUR ORDER_NO BY CALLING NOVALNET GATEWAY, IF U WANT THE USER TO USE ORDER_NO AS PAYMENT REFERENCE ###

					### Step3: ADJUST THE NEW ORDER CONFIRMATION EMAIL TO INFORM THE USER THAT THIS ORDER IS MADE ON SUBSCRIPTION RENEWAL ###
				}

				$novalnet_handler_obj->debug_error( 'Novalnet callback received. Payment type ( '.$novalnet_params['payment_type'].') is not applicable for this process!' );

			} else if ( $curr_payment_level === 1 ) { //level 1 payments - Type of charge backs

				$callback_comments = $new_line . sprintf( __(' Novalnet callback received. Chargeback executed successfully for the TID: %s amount: %s %s on %s. The subsequent TID: %s.', 'wc-novalnet' ), $novalnet_order_info['tid'], sprintf('%0.2f',( $novalnet_params['amount']/100)) ,$novalnet_params['currency'], date_i18n( get_option('date_format'), strtotime(date('Y-m-d'))), $novalnet_params['tid'] ) . $new_line  ;

				wc_novalnet_update_customer_notes( $post_id, $callback_comments );
				$novalnet_handler_obj->log_callback_details( $novalnet_params, $post_id );
				$novalnet_handler_obj->send_notification_mail( array(
					'comments' => $callback_comments,
					'order_no' => $order_id,
				) );
				$novalnet_handler_obj->debug_error( $callback_comments );

			} else if ( $curr_payment_level === 0 )  {

				$response_text = wc_novalnet_response_text( $novalnet_params );

				if ( empty( $woo_order->customer_note ) ) { //For communication failure
					
					$config_data = wc_novalnet_global_configurations( true );
					$aff_acc_details = array();
					
					$aff_acc_details = $wpdb->get_row( $wpdb->prepare( "SELECT aff_id, aff_authcode, aff_accesskey FROM {$wpdb->prefix}novalnet_aff_account_detail WHERE aff_id=(SELECT aff_id FROM {$wpdb->prefix}novalnet_aff_user_detail WHERE customer_id=%d)", $woo_order->user_id ), ARRAY_A );

					if ( !empty( $aff_acc_details ) ) {
						$config_data['vendor_id'] = $aff_acc_details['aff_id'];
						$config_data['auth_code'] = $aff_acc_details['aff_authcode'];
					} 

					$novalnet_tid_status = $novalnet_handler_obj->perform_transaction_status_call( $post_id, $novalnet_order_info['tid'], $aff_acc_details );

					$test_mode = wc_novalnet_check_test_mode_status( $novalnet_params['test_mode'] );

					$novalnet_comments = $new_line . $woo_order->payment_method_title ;
					$novalnet_comments .= $new_line . __('Novalnet Transaction ID : ', 'wc-novalnet') . $novalnet_order_info['tid'];
					$novalnet_comments .= ( $test_mode ) ? $new_line . __('Test order', 'wc-novalnet') : '' . $new_line;

					if ( $novalnet_params['status'] != 100 && ! empty( $response_text ) ) {
							$novalnet_comments  .= $new_line . $response_text ;
					}

					wc_novalnet_update_customer_notes( $post_id, $novalnet_comments );

					
					$callback_comments =  $new_line . ' Novalnet callback received. ';

					if ( 100 == $novalnet_params['status'] ) {

						$wpdb->insert( "{$wpdb->prefix}novalnet_transaction_detail",
							array(
								'order_no' 				=> $post_id,
								'vendor_id'      		=> $config_data['vendor_id'],
								'auth_code'				=> $config_data['auth_code'],
								'product_id'     		=> $config_data['product_id'],
								'tariff_id'     		=> $config_data['tariff_id'],
								'payment_id' 			=> $novalnet_tid_status['payment_id'],
								'payment_type' 			=> $woo_order->payment_method,
								'tid' 					=> $novalnet_order_info['tid'],
								'subs_id' 				=> $novalnet_tid_status['subs_id'],
								'amount' 				=> $woo_order->order_total * 100,
								'callback_amount' 		=> $novalnet_params['amount'] * 100 ,
								'currency' 				=> $woo_order->order_currency,
								'status' 				=> 100,
								'gateway_status' 		=> $novalnet_tid_status['status'],
								'test_mode' 			=> $test_mode,
								'customer_id' 			=> $woo_order->user_id,
								'customer_email' 		=> $woo_order->billing_email,
								'date' 					=> date('Y-m-d H:i:s'),
								'active' 				=> 1
							)
						);
						$status = $novalnet_order_info['new_order_status'];
						$callback_comments .= $new_line . $novalnet_order_info['payment_title'] . ' payment status updated.' . $new_line;
					} else {
						$status = 'cancelled';
						$callback_comments .= $new_line . $novalnet_order_info['payment_title'] . ' payment cancelled.' . $new_line;
					}

					$woo_order->update_status( $status );
					$woo_order->add_order_note( $callback_comments );

					// Empty awaiting payment session
					if ( ! empty(  WC()->session->order_awaiting_payment ) ) {
						unset( WC()->session->order_awaiting_payment );
					}

					$novalnet_handler_obj->send_notification_mail( array(
						'comments' => $callback_comments,
						'order_no' => $order_id,
					) );

					$novalnet_handler_obj->log_callback_details( $novalnet_params, $post_id );
					$novalnet_handler_obj->debug_error( $callback_comments );

				} else if ( 100 == $novalnet_params['status'] ) {

					if (  $novalnet_params['subs_billing'] == 1 ) {

						$novalnet_tid_status = $novalnet_handler_obj->perform_transaction_status_call( $post_id, $novalnet_order_info['tid'] );

						$subscription_details = $novalnet_handler_obj->get_subscription_details( $post_id );
						if ( isset( $subscription_details['subs_plugin_enabled'] ) ) {
							$response = $novalnet_handler_obj->recurring_order_creation($subscription_details['subscription_key'], $woo_order, $novalnet_order_info, $novalnet_tid_status['next_subs_cycle'] );

							if ( ! empty( $subscription_details['subscription_length'] ) ) {
								$wpdb->update( "{$wpdb->prefix}novalnet_subscription_details", array('subscription_length' => $subscription_details['subscription_length'] - 1 ), array( 'order_no' => $post_id ) );
								if ( $subscription_details['subscription_length'] == 1 )
									$novalnet_handler_obj->perform_subscription_cancel( $post_id );
                            }

                            $novalnet_handler_obj->debug_error( $response );
						}
					} else if ( $novalnet_params['payment_type'] == 'PAYPAL' && 100 == $novalnet_params['status'] ) {

						if ( $novalnet_order_info['callback_paid_amount'] < (int)$novalnet_order_info['amount'] ) {

							$callback_comments = $new_line . sprintf( __('Novalnet Callback Script executed successfully for the TID: %s with amount %s %s on %s.', 'wc-novalnet' ), $novalnet_order_info['tid'], sprintf('%0.2f',( $novalnet_params['amount']/100)) , $novalnet_params['currency'], date_i18n( get_option('date_format'), strtotime(date('Y-m-d'))) ) . $new_line  ;

							wc_novalnet_update_customer_notes( $post_id, $callback_comments );
							$woo_order->update_status( $novalnet_order_info['new_order_status']);
							$novalnet_handler_obj->log_callback_details( $novalnet_params, $post_id );
							$novalnet_handler_obj->send_notification_mail( array(
								'comments' => $callback_comments,
								'order_no' => $order_id,
							) );
							$novalnet_handler_obj->debug_error( $callback_comments );

						}
						$novalnet_handler_obj->debug_error('Novalnet callback received. Order already Paid.');
					}
				}
				$novalnet_handler_obj->debug_error('Novalnet callback received. Payment type ( '.$novalnet_params['payment_type'].' ) is not applicable for this process!');
			}

			if ( $novalnet_params['payment_type'] == 'SUBSCRIPTION_STOP' ) { //Cancellation of a Subscription

				$callback_comments = $new_line . sprintf( __('Novalnet callback script received. Subscription has been stopped for the TID: %s on %s. Subscription has been canceled due to: %s', 'wc-novalnet' ), $novalnet_order_info['tid'], date_i18n( get_option('date_format'), strtotime(date('Y-m-d'))),$novalnet_params['termination_reason'] ) . $new_line  ;

				wc_novalnet_update_customer_notes( $post_id, $callback_comments );

				$subscription_details = $novalnet_handler_obj->get_subscription_details( $post_id );

				if ( isset( $subscription_details['subs_plugin_enabled'] ) ) {
					$users_subscriptions = WC_Subscriptions_Manager::update_users_subscriptions( $woo_order->user_id, array( $subscription_details['subscription_key'] => array( 'status' => 'cancelled', 'end_date' => gmdate( 'Y-m-d H:i:s' ) ) ) );
				}
				$novalnet_handler_obj->send_notification_mail( array(
					'comments' => $callback_comments,
					'order_no' => $order_id,
				) );
				$novalnet_handler_obj->debug_error( $callback_comments );

			}
		} else {
			/* Error section : Due to order reference not found from the shop database  */
			$novalnet_handler_obj->debug_error('Novalnet callback received. Order Reference not exist!');
		}
	}
	return true;
 }

 /**
  * Novalnet Vendor Script
  *
  * This script is uesd to handle the asynchronous action triggers from Novalnet
  *
  * @class Novalnet_Callback_handler
  * @version 10.0.0
  * @package Novalnet/Callback
  * @author Novalnet
  */
 class Novalnet_Callback_handler {

	protected $ary_payments = array();
	protected $ary_chargebacks = array();
	protected $ary_collections = array();
	protected $ary_subscriptions = array();
	protected $ary_payment_groups = array();
	protected $woo_capture_params = array();
	protected $required_params = array();
	protected $affilate_activation_params = array();

	/* @IP-ADDRESS Novalnet IP, is a fixed value, DO NOT CHANGE!!!!! */
	protected $ip_allowed = array( '195.143.189.210' , '195.143.189.214' );

	function __construct( $ary_capture = array() ) {

		self::check_ip_address();
		if ( empty( $ary_capture ) ) {
			$this->debug_error( 'Novalnet callback received. No params passed over!' );
		}

		if ( isset( $ary_capture['vendor_activation'] ) && $ary_capture['vendor_activation'] == 1 ) {

			$this->affilate_activation_params = array('vendor_id', 'vendor_authcode', 'product_id', 'aff_id', 'aff_authcode', 'aff_accesskey' );
		} else {
			$this->assign_vendor_configuration_params();
			$this->required_params = array( 'vendor_id', 'status', 'amount', 'payment_type', 'tid' );

			if ( isset( $ary_capture['subs_billing'] ) && $ary_capture['subs_billing'] ==1 ) {
				array_push($this->required_params, 'signup_tid');
			} else if (isset( $ary_capture['payment_type'] ) && in_array( $ary_capture['payment_type'], array_merge( $this->ary_chargebacks, array('INVOICE_CREDIT') ) ) ) {
				array_push($this->required_params, 'tid_payment');
			}
		}

		$this->woo_capture_params = self::validate_capture_params( $ary_capture );
	}

	/**
	 * Checks the client IP address
	 *
	 * @access public
	 * @param none
	 * @return void
	 */
	public function check_ip_address() {
		global $process_test_mode;
		$client_ip_addr = wc_novalnet_server_addr();
		if ( ! in_array( $client_ip_addr, $this->ip_allowed ) && ! $process_test_mode ) {
			echo "Novalnet callback received. Unauthorised access from the IP " . $client_ip_addr; exit;
		}
	}

	/**
	 * Assigns the vendor configuration params
	 *
	 * @access public
	 * @param none
	 * @return void
	 */
	public function assign_vendor_configuration_params() {
		// Level - 0 Payment types
		$this->ary_payments = array(
			'CREDITCARD',
			'INVOICE_START',
			'DIRECT_DEBIT_SEPA',
			'GUARANTEED_INVOICE_START',
			'PAYPAL',
			'ONLINE_TRANSFER',
			'IDEAL',
			'EPS',
			'PAYSAFECARD'
		);

		// Level - 1 Payment types
		$this->ary_chargebacks = array(
			'RETURN_DEBIT_SEPA',
			'REVERSAL',
			'CREDITCARD_BOOKBACK',
			'CREDITCARD_CHARGEBACK',
			'REFUND_BY_BANK_TRANSFER_EU'
		);

		// Level - 2 Payment types
		$this->ary_collections = array(
			'INVOICE_CREDIT',
			'GUARANTEED_INVOICE_CREDIT',
			'CREDIT_ENTRY_CREDITCARD',
			'CREDIT_ENTRY_SEPA',
			'DEBT_COLLECTION_SEPA',
			'DEBT_COLLECTION_CREDITCARD'
		);

		$this->ary_subscriptions = array(
			'SUBSCRIPTION_STOP'
		);

		$this->ary_payment_groups = array(
			'novalnet_cc' => array(
				'CREDITCARD',
				'CREDITCARD_BOOKBACK',
				'CREDITCARD_CHARGEBACK',
				'CREDIT_ENTRY_CREDITCARD',
				'DEBT_COLLECTION_CREDITCARD',
				'SUBSCRIPTION_STOP'
			),
			'novalnet_sepa' => array(
				'DIRECT_DEBIT_SEPA',
				'RETURN_DEBIT_SEPA',
				'CREDIT_ENTRY_SEPA',
				'DEBT_COLLECTION_SEPA',
				'SUBSCRIPTION_STOP'
			),
			'novalnet_ideal' => array('IDEAL'),
			'novalnet_eps' => array('EPS'),
			'novalnet_instantbank' => array( 'ONLINE_TRANSFER' ),
			'novalnet_paypal' => array(
				'PAYPAL',
				'SUBSCRIPTION_STOP'
			),
			'novalnet_prepayment' => array(
				'INVOICE_START',
				'INVOICE_CREDIT',
				'SUBSCRIPTION_STOP'
			),
			'novalnet_invoice'	=> array(
				'INVOICE_START',
				'GUARANTEED_INVOICE_START',
				'INVOICE_CREDIT',
				'GUARANTEED_INVOICE_CREDIT',
				'SUBSCRIPTION_STOP'
			),
		);
	}

	/**
	 * Perform parameter validation process
	 *
	 * @access public
	 * @param array $ary_capture
	 * @return array
	 */
	public function validate_capture_params( $ary_capture = array() ) {
		global $process_test_mode, $line_break;
		$error = '';
		$ary_set_null_value = array( 'reference', 'vendor_id', 'tid', 'status', 'status_messge', 'payment_type', 'signup_tid' );

		foreach ( $ary_set_null_value as $value ) {
			if ( ! isset( $ary_capture[ $value ] ) ) {
				$ary_capture[ $value ] = '';
			}
		}

		if ( isset( $ary_capture['vendor_activation'] ) && $ary_capture['vendor_activation'] == 1 ) {
			foreach ( $this->affilate_activation_params as $v ) {
                if ( empty( $ary_capture[$v] ) ) {
                    $error .= 'Required param ( ' . $v . '  ) missing!' ;
                }
            }

            if ( ! empty( $error ) ) {
				$this->debug_error( $error );
			}
		} else {
			if ( $this->required_params ) {
				foreach ( $this->required_params as $v ) {
					if ( empty( $ary_capture[ $v ] ) ) {
						$error .= 'Required param ( ' . $v . '  ) missing!' . $line_break;
					}
				}
				if ( ! empty( $error ) ) {
					$this->debug_error( $error );
				}
			}

			if ( !in_array( $ary_capture['payment_type'], array_merge( $this->ary_payments, $this->ary_chargebacks, $this->ary_collections, $this->ary_subscriptions ) ) ) {
				$error = 'Novalnet callback received. Payment type ( ' . $ary_capture['payment_type'] . ' ) is mismatched!';
				$this->debug_error( $error );
			}

			if ( $ary_capture['status'] < 0 )  {
				$this->debug_error('Novalnet callback received. Status is not valid');
			}

			if ( !is_numeric( $ary_capture['amount'] ) || $ary_capture['amount'] < 0) {
				$error = 'Novalnet callback received. The requested amount ('. $ary_capture['amount'] .') is not valid';
				$this->debug_error( $error );
			}

			if ( ! empty( $ary_capture['signup_tid'] ) && $ary_capture['subs_billing'] == 1 ) {
				if ( ! is_numeric( $ary_capture['signup_tid'] ) || strlen( $ary_capture['signup_tid'] ) != 17 ) {
					$this->debug_error('Novalnet callback received. Invalid TID ['. $ary_capture['signup_tid'] . '] for Order.');
				}
				if ( $ary_capture['subs_billing'] == 1 && ( strlen( $ary_capture['tid'] ) != 17
                || ! is_numeric($ary_capture['tid'] ) ) ) {
					$this->debug_error('Novalnet callback received. TID [' . $ary_capture['tid'] . '] is not valid.');
                }
            } else {
                if ( in_array( $ary_capture['payment_type'], array_merge( $this->ary_chargebacks, array( 'INVOICE_CREDIT' ) ) ) ) {
					if ( ! is_numeric( $ary_capture['tid_payment'] ) || strlen($ary_capture['tid_payment'] ) != 17 ) {
                        $this->debug_error( 'Novalnet callback received. Invalid TID ['. $ary_capture['tid_payment'] . '] for Order.' );
                    }
                }
                if ( strlen( $ary_capture['tid'] ) != 17 || ! is_numeric( $ary_capture['tid'] ) ) {
					$this->debug_error('Novalnet callback received. TID [' . $ary_capture['tid'] . '] is not valid.');
                }
            }

            if ( $ary_capture['signup_tid'] != '' ) {
				$ary_capture['shop_tid'] = $ary_capture['signup_tid'];
			}  else if ( in_array( $ary_capture['payment_type'], array_merge( $this->ary_chargebacks, array( 'INVOICE_CREDIT' ) ) ) ) {
				$ary_capture['shop_tid'] = $ary_capture['tid_payment'];
			} else if ( $ary_capture['tid'] != '' ) {
				$ary_capture['shop_tid'] = $ary_capture['tid'];
			}
		}

		return $ary_capture;
	}

	/**
	 * Fetches the order reference from the novalnet_transaction_detail
	 * table on shop database
	 *
	 * @access public
	 * @param none
	 * @return array $trans_details
	 */
	public function get_order_reference() {
		global $wpdb;

		$org_tid = $this->woo_capture_params['shop_tid'];

		$payment_type_level = self::get_payment_type_level();
		$order_details_exists = false;

		$woo_order_id = ( ! empty( $this->woo_capture_params['order_no'] ) ) ? $this->woo_capture_params['order_no'] : ( ! empty( $this->woo_capture_params['order_id'] ) ? $this->woo_capture_params['order_id'] : '' );

		if ( ! empty( $woo_order_id ) ) {

			$tmp_post_id = $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM $wpdb->postmeta where meta_value='%d' AND (meta_key='_order_number' OR meta_key='_order_number_formatted')", $woo_order_id ) );

			$post_id = ! empty( $tmp_post_id ) ?  $tmp_post_id : $woo_order_id;
		}

		$sql_query = "SELECT order_no, payment_type, amount, callback_amount, refunded_amount, tid, currency FROM {$wpdb->prefix}novalnet_transaction_detail WHERE tid=%s";

		if ( isset( $post_id ) )
			$sql_query .= ' OR order_no=' . $post_id;

		$trans_details = $wpdb->get_row( $wpdb->prepare( $sql_query,  $org_tid ), ARRAY_A );

		if ( empty( $trans_details ) ) {

			if ( isset( $post_id ) ) {
				$wc_order = new WC_Order( $post_id );
				if ( ! empty( $wc_order->id ) ) {

					$trans_details['tid'] 				= $org_tid;
					$trans_details['order_no']			= $post_id;
					$trans_details['sequence_order_no']	= $woo_order_id;
					$trans_details['payment_type']		= $wc_order->payment_method;
					$trans_details['payment_title']		= $wc_order->payment_method_title;
					$trans_details['currency']			= $wc_order->order_currency;
					$trans_details['amount']			= $wc_order->nn_order_amount;

					if ( empty( $wc_order->customer_note ) ) {
						$payment_settings = get_option( 'woocommerce_' .$trans_details['payment_type'] . '_settings' );
						$trans_details['new_order_status'] = $payment_settings['order_success_status'];
						$trans_details['callback_paid_amount'] = 0;

						if ( in_array( $payment_type_level, array( 0 , 2 ) ) ) {
							$callback_amount = $wpdb->get_var( $wpdb->prepare( "SELECT sum(amount) FROM {$wpdb->prefix}novalnet_callback_history WHERE order_no=%s" ,$wc_order->id ) );
							$txn_details['callback_paid_amount'] = ! empty( $callback_amount ) ?  $callback_amount : 0;
						}

						if ( ! in_array( $this->woo_capture_params['payment_type'], $this->ary_payment_groups[ $trans_details['payment_type'] ] ) ) {
							$this->debug_error( 'Novalnet callback received. Payment Type [' . $this->woo_capture_params['payment_type'] . '] is not valid.' );
						}

						return $trans_details;
					}else $order_details_exists = true;
				}
			}
		} else $order_details_exists = true;


		if ( $order_details_exists ) {
			$trans_details['sequence_order_no']	= $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM $wpdb->postmeta where post_id=%d AND (meta_key='_order_number' OR meta_key='_order_number_formatted')", $trans_details['order_no'] ) );

			$wc_order = new WC_Order( $trans_details['order_no'] );
			$trans_details['payment_title']	= $wc_order->payment_method_title;
			if ( ! empty( $this->woo_capture_params['signup_tid'] ) ) {
				$trans_details['payment_type'] = get_post_meta($trans_details['order_no'],'_recurring_payment_method',true);
			}
			$payment_settings = get_option( 'woocommerce_' .$trans_details['payment_type'] . '_settings' );

			$trans_details['new_order_status'] = ( ( $this->woo_capture_params['payment_type'] == 'INVOICE_CREDIT' ) ? ( isset( $payment_settings['callback_status'] ) ? $payment_settings['callback_status'] :  $payment_settings['order_success_status'] ) : $payment_settings['order_success_status'] );

			$trans_details['callback_paid_amount'] = 0;
			if ( in_array( $payment_type_level, array( 0 , 2 ) ) ) {
				$callback_amount = $wpdb->get_var( $wpdb->prepare( "SELECT sum(amount) FROM {$wpdb->prefix}novalnet_callback_history WHERE order_no=%d" ,$wc_order->id ) );
				$trans_details['callback_paid_amount'] = ! empty( $callback_amount ) ?  $callback_amount : 0;
			}

			$seq_number = isset($trans_details['sequence_order_no']) ? $trans_details['sequence_order_no'] : $trans_details['order_no'];
			if ( ! empty( $woo_order_id ) && $woo_order_id != $seq_number ) {
				$this->debug_error('Novalnet callback received. Order no is not valid.');
			}

			if ( $org_tid != $trans_details['tid'] ) {
				$this->debug_error( 'Novalnet callback received. TID [' . $this->woo_capture_params['payment_type'] . '] is not valid.' );
			}

			if ( ! in_array( $this->woo_capture_params['payment_type'], $this->ary_payment_groups[ $trans_details['payment_type'] ] ) ) {
				$this->debug_error( 'Novalnet callback received. Payment Type [' . $this->woo_capture_params['payment_type'] . '] is not valid.' );
			}
			return $trans_details;
		} else {
			$this->debug_error('Novalnet callback received. Transaction mapping failed');
		}
	}

	/**
	 * Returns the capture params
	 *
	 * @access public
	 * @param none
	 * @return array
	 */
	public function get_capture_params() {
		return $this->woo_capture_params;
	}

	/**
	 * Get given payment_type level for process
	 *
	 * @access public
	 * @param none
	 * @return Integer | boolean
	 */
	function get_payment_type_level() {
		if ( ! empty( $this->woo_capture_params['payment_type'] ) ) {
			if ( in_array( $this->woo_capture_params['payment_type'], $this->ary_payments ) ) {
				return 0;
			}  else if ( in_array( $this->woo_capture_params['payment_type'], $this->ary_chargebacks ) ) {
				return 1;
			} else if ( in_array( $this->woo_capture_params['payment_type'], $this->ary_collections ) ) {
				return 2;
			}
		}
		return false;
	}

	/**
	 * Display the callback messages
	 *
	 * @access public
	 * @param string $error_msg
	 * @return none
	 */
	public function debug_error( $error_msg ) {
		global $process_debug_mode;
		if ( $process_debug_mode ) {
			echo utf8_decode($error_msg);
		}
		exit;
	}

    /**
     * Update affiliate account activation details in novalnet_aff_account_detail table
     *
     * @access public
     * @param array $ary_activation_params
     * @return boolean
     */
	public function update_aff_account_activation_detail( $ary_activation_params ) {
		global $wpdb;
		$new_line = "\n";
		$wpdb->insert( "{$wpdb->prefix}novalnet_aff_account_detail",
			array(
				'vendor_id' 		=> $ary_activation_params['vendor_id'],
				'vendor_authcode' 	=> $ary_activation_params['vendor_authcode'],
				'product_id' 		=> $ary_activation_params['product_id'],
				'product_url' 		=> $ary_activation_params['product_url'],
				'activation_date' 	=> isset( $ary_activation_params['activation_date'] ) ? date( 'Y-m-d H:i:s', strtotime($ary_activation_params['activation_date']) ) : '',
				'aff_id' 			=> $ary_activation_params['aff_id'],
				'aff_authcode' 		=> $ary_activation_params['aff_authcode'],
				'aff_accesskey' 	=> $ary_activation_params['aff_accesskey'],
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

    /**
     * Process the transaction status call
     *
     * @access public
     * @param integer $post_id
     * @param integer $tid
     * @return array
     */
	public function perform_transaction_status_call( $post_id , $tid, $aff_details = '' ) {
		global $wpdb;

		$config_values = $wpdb->get_row( $wpdb->prepare( "SELECT vendor_id, auth_code, product_id, tariff_id FROM {$wpdb->prefix}novalnet_transaction_detail WHERE order_no=%d" , $post_id ), ARRAY_A );

		$config_data = ( empty( $config_values ) ? wc_novalnet_global_configurations( true ) :  $config_values );
		
		if ( !empty( $aff_details ))  {
			$config_data['vendor_id'] = $aff_details['aff_id'];
			$config_data['auth_code'] = $aff_details['aff_authcode'];
		}
			
		$transaction_status_request = array(
			'vendor_id'      => $config_data['vendor_id'],
			'vendor_authcode'=> $config_data['auth_code'],
			'product_id'     => $config_data['product_id'],
			'request_type'   => 'TRANSACTION_STATUS',
			'tid'            => $tid,
		);

		return wc_novalnet_perform_xmlrequest( $transaction_status_request );
	}

    /**
     * Fetch subscription details
     *
     * @access public
     * @param integer $post_id
     * @return array $subs_details
     */
	public function get_subscription_details( $post_id ) {
		global $wpdb;
		$subs_details = array();

		$subs_details['subscription_length'] = $wpdb->get_var( $wpdb->prepare( "SELECT subscription_length FROM {$wpdb->prefix}novalnet_subscription_details WHERE order_no=%d ", $post_id ) );

		if ( class_exists('WC_Subscriptions') ) {
			$subs_details['subs_plugin_enabled'] = true;
			$subs_details['subscription_key'] =  WC_Subscriptions_Manager::get_subscription_key( $post_id );
		}

		return $subs_details;
	}

    /**
     * Process subscription cancel option
     *
     * @access public
     * @param integer $post_id
     * @return void
     */
	public function perform_subscription_cancel( $post_id ) {
		global $wpdb;

        $result_set = $wpdb->get_row( $wpdb->prepare( "SELECT vendor_id, auth_code, product_id, tariff_id, payment_id,tid FROM {$wpdb->prefix}novalnet_transaction_detail WHERE order_no=%d", $post_id ) );

        if ( ! empty( $result_set->vendor_id ) && ! empty( $result_set->auth_code ) && ! empty( $result_set->product_id ) && ! empty( $result_set->tariff_id ) && ! empty( $result_set->tid ) ) {
			$cancel_requset = array(
				'vendor' 	=> $result_set->vendor_id,
				'auth_code' => $result_set->auth_code,
				'product'	=> $result_set->product_id,
				'tariff' 	=> $result_set->tariff_id,
				'tid' 		=> $result_set->tid,
				'key' 		=> $result_set->payment_id,
				'cancel_sub'=> 1,
				'cancel_reason' => 'others',
			);

			wc_novalnet_submit_request( $cancel_requset );
        }
	}

	/**
	 * Log callback data in novalnet_callback_history table
	 *
	 * @access public
	 * @param array $data
	 * @param integer $post_id
	 * @return boolean
	 */
	function log_callback_details( $datas, $post_id ) {
		global $wpdb;

		if ( !empty( $datas ) ) {
			$table_exists = false;

			$update_amount = $wpdb->get_row( $wpdb->prepare( "SELECT amount, callback_amount FROM {$wpdb->prefix}novalnet_transaction_detail WHERE order_no=%d" , $post_id ), ARRAY_A );

			$datas_amount = $datas['amount'];
				if ( $datas['payment_type'] == 'INVOICE_START' )
					$datas_amount = 0;
			if ( ! empty( $update_amount ) ) {
				$table_exists = true;
			} else {
				$update_amount = array(
					'amount' => get_post_meta( $post_id, '_nn_order_amount', true ),
					'callback_amount' => get_post_meta( $post_id, '_nn_callback_amount', true )
				);
			}

			$callback_amount = ($datas['payment_type'] == 'PAYPAL') ? $update_amount['amount'] :  $datas_amount;

			$wpdb->insert( "{$wpdb->prefix}novalnet_callback_history", array( 'payment_type' => $datas['payment_type'], 'status' => $datas['status'], 'callback_tid' => $datas['tid'], 'org_tid' => $datas['shop_tid'], 'amount' => $callback_amount, 'currency' => $datas['currency'], 'product_id' => $datas['product_id'], 'order_no' => $post_id , 'date' => date('Y-m-d H:i:s') ) );

			if ( $table_exists ) {
				$wpdb->update( "{$wpdb->prefix}novalnet_transaction_detail", array( 'callback_amount' => ( $update_amount['callback_amount'] + $datas_amount ) ), array( 'order_no' => $post_id ) );
			} else {
				update_post_meta( $post_id, '_nn_callback_amount', $update_amount['callback_amount'] + $datas_amount );
			}
			return true;
		}
		return false;
	}

	/**
	 * Send notification mail to Merchant
	 *
	 * @access public
	 * @param array $datas
	 * @return boolean
	 */
	public function send_notification_mail( $datas = array() ) {
		global $process_debug_mode;

		$receipient_addr 	 = ( get_option('novalnet_callback_emailtoaddr') != '' ) ? get_option('novalnet_callback_emailtoaddr') : get_bloginfo('admin_email');
		$bcc_receipient_addr = get_option('novalnet_callback_emailbccaddr');
		$email_subject       = 'Novalnet Callback Script Access Report';
		$email_body          = get_option('novalent_callback_emailbody');
		$headers 			 = '';

		$email_subject .= ' - WooCommerce';
		if( isset( $this->woo_capture_params['debug_mode'] ) && $this->woo_capture_params['debug_mode'] == 1 ) {
			$receipient_addr       = 'test@novalnet.de';
			$bcc_receipient_addr      = '';
			$email_subject .= ' - TEST';
		}

		$email_body .= ( empty( $datas['order_no'] ) ? $datas['comments'] : ( "Order id : " . $datas['order_no'] . "\n" . $datas['comments'] ) );

		if ( is_email( $bcc_receipient_addr ) ) {
			$headers .= 'Bcc: '. $bcc_receipient_addr . " \r\n";
		}

		if ( $receipient_addr ) {
			$mail_check = wp_mail( $receipient_addr, $email_subject, $email_body , $headers);
			echo ( $mail_check ) ? 'Mail Sent!' : 'Mail not sent!';

		} else {
			echo 'Mail not sent!';
		}
		return true;
	}

	/**
	 * Creation for order for each recurring process
	 *
	 * @access public
	 * @param string $subscription_key
	 * @param object $parent_order
	 * @param array $order_info
	 * @param date $parent_next_cycle
	 * @return boolean
	 */
	public function recurring_order_creation($subscription_key, $parent_order, $order_info , $parent_next_cycle ) {
		global $wpdb;

		$new_line = "\n";
		$recurring_order_id = WC_Subscriptions_Renewal_Order::generate_paid_renewal_order( $parent_order->user_id , $subscription_key );
		$recurring_order = new WC_Order( $recurring_order_id );

		$trans_details = $wpdb->get_row( $wpdb->prepare( "SELECT vendor_id, auth_code, product_id, tariff_id, payment_id, callback_amount, currency, payment_type FROM {$wpdb->prefix}novalnet_transaction_detail WHERE order_no=%d ORDER BY id DESC", $parent_order->id ), ARRAY_A );

		$transaction_status_request = array(
			'vendor_id'      => $trans_details['vendor_id'],
			'vendor_authcode'=> $trans_details['auth_code'],
			'product_id'     => $trans_details['product_id'],
			'request_type'   => 'TRANSACTION_STATUS',
			'tid'            => $order_info['tid'],
		);

		update_post_meta($recurring_order->id,'_order_total',$this->woo_capture_params['amount']/100);

		$wpdb->query(
			$wpdb->prepare("
				UPDATE {$wpdb->prefix}woocommerce_order_itemmeta
				SET  meta_value=%f
				WHERE order_item_id=(SELECT order_item_id FROM {$wpdb->prefix}woocommerce_order_items where order_id=$recurring_order->id and order_item_type='line_item') AND (meta_key='_line_subtotal' OR meta_key='_line_total');",
				sprintf( "%0.2f", $this->woo_capture_params["amount"]/100 )
			)
		);
		$novalnet_tid_status = wc_novalnet_perform_xmlrequest( $transaction_status_request );
		$test_mode = wc_novalnet_check_test_mode_status( $this->woo_capture_params['test_mode'] );
		$wpdb->insert( "{$wpdb->prefix}novalnet_transaction_detail",
			array(
				'order_no' 				=> $recurring_order_id,
				'vendor_id'      		=> $trans_details['vendor_id'],
				'auth_code'				=> $trans_details['auth_code'],
				'product_id'     		=> $trans_details['product_id'],
				'tariff_id'     		=> $trans_details['tariff_id'],
				'payment_id' 			=> $trans_details['payment_id'],
				'payment_type' 			=> $recurring_order->payment_method,
				'tid' 					=> $this->woo_capture_params['tid'],
				'subs_id' 				=> $novalnet_tid_status['subs_id'],
				'amount' 				=> $this->woo_capture_params['amount'],
				'callback_amount' 		=> ( $this->woo_capture_params['payment_type'] == 'INVOICE_START') ? 0 : $this->woo_capture_params['amount'],
                'refunded_amount'		=> 0,
				'currency' 				=> $trans_details['currency'],
				'status' 				=> 100,
				'gateway_status' 		=> $novalnet_tid_status['status'],
				'test_mode' 			=> $test_mode,
				'customer_id' 			=> $recurring_order->user_id,
				'customer_email' 		=> $recurring_order->billing_email,
				'date' 					=> date('Y-m-d H:i:s'),
				'active' 				=> 1
			)
		);
		$novalnet_comments = $new_line . $recurring_order->payment_method_title ;
		$novalnet_comments .= $new_line . __('Novalnet transaction ID : ', 'wc-novalnet') . $this->woo_capture_params['tid'];
		$novalnet_comments .= ( $test_mode ) ? $new_line . __('Test order', 'wc-novalnet') : '' . $new_line;

		if ( $this->woo_capture_params['payment_type'] == 'INVOICE_START' ) {

			$sequence_order_no	= $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM $wpdb->postmeta where post_id=%d AND (meta_key='_order_number' OR meta_key='_order_number_formatted')", $recurring_order_id ) );

			$order_no = ( $sequence_order_no ) ? $sequence_order_no : $recurring_order_id;

		   $novalnet_comments .= $new_line . __('Please transfer the amount to the below mentioned account details of our payment processor Novalnet', 'wc-novalnet') . $new_line;
			if ( isset( $this->woo_capture_params['due_date'] ) && $this->woo_capture_params['due_date'] != '' ) {
				$novalnet_comments.= __('Due date : ', 'wc-novalnet') . date_i18n(get_option('date_format'), strtotime( $this->woo_capture_params['due_date'])) . $new_line;
			}
			$novalnet_comments .= __('Account holder : Novalnet AG', 'wc-novalnet') . $new_line;

			$novalnet_comments .= 'IBAN : ' . ( isset( $this->woo_capture_params['invoice_iban'] ) ? $this->woo_capture_params['invoice_iban'] : '' ) . $new_line;
			$novalnet_comments .= 'BIC : ' . ( isset( $this->woo_capture_params['invoice_bic'] ) ? $this->woo_capture_params['invoice_bic'] : '' ) . $new_line;
			if ( isset( $this->woo_capture_params['invoice_bankname'] ) || isset( $this->woo_capture_params['invoice_bankplace'] ) ) {
				$novalnet_comments .= 'Bank : ' . $this->woo_capture_params['invoice_bankname'] . ' ' . trim( $this->woo_capture_params['invoice_bankplace']) . $new_line;
			}
			$novalnet_comments .= __('Amount : ', 'wc-novalnet') . strip_tags( woocommerce_price( sprintf( "%0.2f", $this->woo_capture_params['amount']/100) ) ) . $new_line;
			$novalnet_comments .= __('Reference 1 : ', 'wc-novalnet') . 'BNR-' . $trans_details['product_id'] . '-' . $order_no  . $new_line ;
			$novalnet_comments .= __('Reference 2 : TID ', 'wc-novalnet') . $this->woo_capture_params['tid'] . $new_line;
			$novalnet_comments .= __('Reference 3 : Order number ', 'wc-novalnet') . $order_no  . $new_line . $new_line;

			$ary_set_null_value = array( 'invoice_bankname', 'due_date', 'invoice_bankplace', 'invoice_iban', 'invoice_bic');
			$invoice_bank_details = array();
			foreach ( $ary_set_null_value as $value ) {
				if ( ! isset( $this->woo_capture_params[ $value ] ) ) {
					$this->woo_capture_params[ $value ] = '';
				}
			}

			$wpdb->insert( "{$wpdb->prefix}novalnet_invoice_details",
				array(
					'order_no' => $recurring_order->id,
					'payment_type' => $recurring_order->payment_method,
					'amount' => $this->woo_capture_params['amount'] ,
					'invoice_due_date' => !empty( $this->woo_capture_params['due_date'] ) ? $this->woo_capture_params['due_date'] : '',
					'invoice_bank_details' => serialize(array(
						'test_mode'			=> $test_mode,
						'bank_name'     	=> $this->woo_capture_params['invoice_bankname'],
						'bank_city'     	=> $this->woo_capture_params['invoice_bankplace'],
						'bank_iban'		    => $this->woo_capture_params['invoice_iban'],
						'bank_bic'      	=> $this->woo_capture_params['invoice_bic'],
						'sequence_order_no'	=> $order_no,
						'invoice_ref'		=> 'BNR-' . $trans_details['product_id'] . '-' . $order_no

					)
				)
			) );
		}

		wc_novalnet_update_customer_notes( $recurring_order_id, $novalnet_comments, false );
		if ( ! empty( $novalnet_tid_status['next_subs_cycle'] ) ) {
			$next_payment_date = __( 'Next charging date : ', 'wc-novalnet') . date_i18n( get_option('date_format'), strtotime($novalnet_tid_status['next_subs_cycle'])) ;
			wc_novalnet_update_customer_notes( $recurring_order_id, $next_payment_date );
		}

		$callback_comments = $new_line . sprintf( __('Novalnet Callback Script executed successfully for the subscription TID: %s with amount %s %s on %s. Please refer PAID transaction in our Novalnet Merchant Administration with the TID: %s', 'wc-novalnet' ), $this->woo_capture_params['shop_tid'], sprintf('%0.2f',( $this->woo_capture_params['amount']/100)), $this->woo_capture_params['currency'], date_i18n( get_option('date_format'), strtotime(date('Y-m-d'))), $this->woo_capture_params['tid'] ) . $new_line  ;
		if ( ! empty( $novalnet_tid_status['next_subs_cycle'] ) ) {
			$callback_comments .= $next_payment_date;
		}

		$this->send_notification_mail( array(
			'comments' => $callback_comments,
			'order_no' => $recurring_order_id,
		) );

		$this->log_callback_details( $this->woo_capture_params, $recurring_order_id );
		$recurring_order->update_status( $order_info['new_order_status'] );
		return $callback_comments;
	}
 }
?>
