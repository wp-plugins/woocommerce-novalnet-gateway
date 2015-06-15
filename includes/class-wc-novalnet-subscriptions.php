<?php

 // Exit if accessed directly
 if ( ! defined('ABSPATH') )
    exit;

/**
 * Novalnet Subscription Actions
 *
 * This file is used for handling the subscription actions under
 * woocommerce subscription plugins.
 *
 * Copyright (c) Novalnet
 *
 * This script is only free to the use for merchants of Novalnet. If
 * you have found this script useful a small recommendation as well as a
 * comment on merchant form would be greatly appreciated.
 *
 * @class       Novalnet_Subscriptions
 * @version     10.0.0
 * @package     Novalnet/Classes
 * @category    Class
 * @author      Novalnet
 * @located at  /includes/
 *
 */

 header("Access-Control-Allow-Origin: *");
 class Novalnet_Subscriptions {

    function __construct() {
		
		
        $this->query_params = isset( $_REQUEST ) ? $_REQUEST : '';
        $this->subscription_key = isset( $this->query_params['subscription_key'] ) ? $this->query_params['subscription_key'] : ( isset( $this->query_params['subscription'] ) ? $this->query_params['subscription'] : ( isset( $this->query_params['wcs_subscription_key'] ) ? $this->query_params['wcs_subscription_key'] : '' ) );

        add_action( 'init', array( &$this, 'initialize_novalnet_subscription' ) , 10 );
        add_action( 'woocommerce_before_my_account', 'wc_novalnet_customize_subscription_cancel' );
        add_action( 'wp_ajax_wcs_update_next_payment_date', array( &$this, 'perform_subscription_recurring_date_extension' ), 9 );
        add_action( 'woocommerce_process_shop_order_meta',array( $this, 'perform_subscription_recurring_amount_update'), 11, 2 );
    }

    /**
     * call from the hook "init"
     *
     * @param none
     * @return void
     */
    public function initialize_novalnet_subscription() {
        if ( ! empty( $this->query_params ) ) {
            if ( isset( $this->query_params['nov_action'] ) && $this->query_params['nov_action'] == 'subs_cancel' && ! empty( $this->query_params['nov_cus_subs_key'] )) {
                $cus_order_id = explode( '_' , $this->query_params['nov_cus_subs_key'] );
                echo wc_novalnet_authenticate_subs_cancel( $cus_order_id[0] );
                exit;
            } else {
                $subs_status = ( is_admin() && isset( $this->query_params['new_status'] ) ) ? $this->query_params['new_status'] : ( isset( $this->query_params[ 'change_subscription_to' ] ) ? $this->query_params[ 'change_subscription_to' ] : '' );
                if ( ! empty( $subs_status ) ) {
                    if ( $subs_status == 'cancelled' ) {
                        $this->perform_subscription_cancel();
                    } else if ( $subs_status == 'on-hold' ) {
                        $this->perform_subscription_suspend();
                    } else if ( $subs_status == 'active' ) {
                        $this->perform_subscription_active();
                    }
                }
            }
        }
    }

    /**
     * Perform Subscription cancel process
     *
     * @param none
     * @return string
     */
    public function perform_subscription_cancel() {
        global $wpdb;
        $subscription = WC_Subscriptions_Manager::get_subscription( $this->subscription_key );
        $order = new WC_Order( $subscription['order_id'] );
        $reason_id = isset( $this->query_params['nov_reason'] ) ? $this->query_params['nov_reason'] : '';
        if ( empty( $reason_id ) ) {
            wc_novalnet_subs_admin_messages( __('Please select the reason of subscription cancellation','wc-novalnet'), 'error' );
        } else {
            $cancel_reason = wc_novalnet_subscription_cancel_list();
            $result_set = $wpdb->get_row( $wpdb->prepare( "SELECT vendor_id, auth_code, product_id, tariff_id, payment_id,tid FROM {$wpdb->prefix}novalnet_transaction_detail WHERE order_no=%d" , $subscription['order_id'] ) );
            $language = wc_novalnet_shop_language();
            if ( ! empty( $result_set->vendor_id ) && ! empty( $result_set->auth_code ) && ! empty( $result_set->product_id ) && ! empty( $result_set->tariff_id ) && ! empty( $result_set->tid ) && ! empty( $result_set->payment_id ) ) {
                $cancel_requset = array(
                    'vendor'    => $result_set->vendor_id,
                    'auth_code' => $result_set->auth_code,
                    'product'   => $result_set->product_id,
                    'tariff'    => $result_set->tariff_id,
                    'tid'       => $result_set->tid,
                    'key'       => $result_set->payment_id,
                    'cancel_sub'=> 1,
                    'cancel_reason' => $cancel_reason[ $reason_id ],
                    'lang' => $language
                );
                $cancel_response =  wc_novalnet_submit_request( $cancel_requset );

                if ( 100 == $cancel_response['status'] ) {

                    $cancel_msg = sprintf( __('Subscription has been canceled due to: %s','wc-novalnet' ), $cancel_reason[ $reason_id ] );

                    wc_novalnet_update_customer_notes( $subscription['order_id'], $cancel_msg );

                    $users_subscriptions = WC_Subscriptions_Manager::update_users_subscriptions( $order->user_id, array( $this->subscription_key => array( 'status' => 'cancelled', 'end_date' => gmdate( 'Y-m-d H:i:s' ) ) ) );

                    $wpdb->update(
                        $wpdb->prefix . 'novalnet_subscription_details',
                        array(
                            'termination_reason' => $cancel_reason[ $reason_id ],
                            'termination_at' => date( 'Y-m-d H:i:s' )
                        ),
                        array(
                            'order_no' => $subscription['order_id']
                        )
                    );
                    wc_novalnet_subs_admin_messages( $cancel_msg );
                } else {
                    $response_text = wc_novalnet_response_text( $cancel_response );
                    if ( $response_text ) {
                        $order->add_order_note( $response_text );
                        wc_novalnet_subs_admin_messages( $response_text, 'error' );
                    }

                }
            }
        }
    }

    /**
     * Perform Subscription suspension process
     *
     * @param none
     * @return string
     */
    public function perform_subscription_suspend() {
        global $wpdb;
        $subscription = WC_Subscriptions_Manager::get_subscription( $this->subscription_key );
        $order = new WC_Order( $subscription['order_id'] );

        $result_set = $wpdb->get_row( $wpdb->prepare( "SELECT vendor_id, auth_code, product_id, tid, subs_id FROM {$wpdb->prefix}novalnet_transaction_detail WHERE order_no=%d" , $subscription['order_id'] ) );

        $suspend_response = wc_novalnet_perform_xmlrequest( array(
                'vendor_id'         => $result_set->vendor_id,
                'vendor_authcode'   => $result_set->auth_code,
                'product_id'        => $result_set->product_id,
                'request_type'      => 'SUBSCRIPTION_PAUSE',
                'tid'               => $result_set->tid,
                'subs_id'           => $result_set->subs_id,
                'suspend'           => 1,
                'pause_period'      => 1,
                'pause_time_unit'   => 'd'
            )
        );

        if ( 100 == $suspend_response['status'] ) {

            $suspension_count = 1 + ( isset( $subscription['suspension_count'] ) ? $subscription['suspension_count'] : 0 );

            $users_subscriptions = WC_Subscriptions_Manager::update_users_subscriptions( $order->user_id, array( $this->subscription_key => array( 'status' => 'on-hold', 'suspension_count' => $suspension_count ) ) );

            $suspend_msg = sprintf(__('This subscription transaction has been suspended on %s','wc-novalnet'), date_i18n( get_option('date_format'), strtotime( date( 'd-m-Y' ) ) ) );

            wc_novalnet_update_customer_notes( $subscription['order_id'], $suspend_msg );

            $wpdb->update( "{$wpdb->prefix}novalnet_subscription_details", array(  'suspended_date' => date('Y-m-d H:i:s') ),  array( 'order_no' => $subscription['order_id'] ) );

            wc_novalnet_subs_admin_messages( $suspend_msg );
        } else {
            $response_text = wc_novalnet_response_text( $suspend_response );
            if ( $response_text ) {
                $order->add_order_note( $response_text );
                wc_novalnet_subs_admin_messages( $response_text, 'error' );
            }
        }
    }

    /**
     * Perform Subscription reactive process after suspend action
     *
     * @param none
     * @return void
     */
    public function perform_subscription_active() {
        global $wpdb;
        $subscription = WC_Subscriptions_Manager::get_subscription( $this->subscription_key );
        $order = new WC_Order( $subscription['order_id'] );

        $result_set = $wpdb->get_row( $wpdb->prepare("SELECT vendor_id, auth_code, product_id, tid, subs_id FROM {$wpdb->prefix}novalnet_transaction_detail WHERE order_no=%d" , $subscription['order_id'] ) );

        $interval = $subscription['interval'];
        $period =  substr( $subscription['period'], 0, 1 );

        if ( $period == 'w' ) {
            $period = 'd';
            $interval = $interval * 7;
        }

        $reactive_response = wc_novalnet_perform_xmlrequest( array(
                'vendor_id'         => $result_set->vendor_id,
                'vendor_authcode'   => $result_set->auth_code,
                'product_id'        => $result_set->product_id,
                'request_type'      => 'SUBSCRIPTION_PAUSE',
                'tid'               => $result_set->tid,
                'subs_id'           => $result_set->subs_id,
                'suspend'           => 0,
                'pause_period'      => $interval,
                'pause_time_unit'   => $period
            )
        );
        if ( isset( $reactive_response['status'] ) && 100 == $reactive_response['status'] ) {

            $reactive_gateway_response = wc_novalnet_perform_xmlrequest( array(
                    'vendor_id'         => $result_set->vendor_id,
                    'vendor_authcode'   => $result_set->auth_code,
                    'product_id'        => $result_set->product_id,
                    'request_type'      => 'TRANSACTION_STATUS',
                    'tid'               => $result_set->tid
                )
            );
            $reactive_msg = sprintf( __( 'Subscription has been successfully activated on %s','wc-novalnet' ), date_i18n( get_option( 'date_format' ), strtotime( date( 'd-m-Y' ) ) ) );

            wc_novalnet_update_customer_notes( $subscription['order_id'], $reactive_msg );

            // Make subscription as active
            $users_subscriptions = WC_Subscriptions_Manager::update_users_subscriptions( $order->user_id, array( $this->subscription_key => array( 'status' => 'active', 'end_date' => 0 ) ) );

            $wpdb->update( "{$wpdb->prefix}novalnet_subscription_details", array( 'suspended_date' => ' ', 'next_payment_date' => $reactive_gateway_response['next_subs_cycle'] ),  array( 'order_no' => $subscription['order_id'] ) );
            wc_novalnet_subs_admin_messages( $reactive_msg );
        } else {
            $response_text = wc_novalnet_response_text( $reactive_response );
            if ( $response_text ) {
                $order->add_order_note( $response_text );
                wc_novalnet_subs_admin_messages( $response_text, 'error' );
            }
        }
    }

    /**
     * Calls from the hook "wp_ajax_wcs_update_next_payment_date"
     * Perform Subscription recurring date extension option
     *
     * @param none
     * @return void
     */
    public function perform_subscription_recurring_date_extension() {
        global $wpdb;
        if ( isset( $this->query_params['action'] ) && $this->query_params['action'] == 'wcs_update_next_payment_date' ) {
            $new_recurring_date = $this->query_params['wcs_year'] . '-' . $this->query_params['wcs_month'] . '-' . $this->query_params['wcs_day'];

            $subscription = WC_Subscriptions_Manager::get_subscription( $this->subscription_key );

            $order = new WC_Order( $subscription['order_id'] );

            $result_set = $wpdb->get_row( $wpdb->prepare( "SELECT vendor_id, auth_code, product_id, tid, subs_id FROM {$wpdb->prefix}novalnet_transaction_detail  WHERE order_no=%d" , $subscription['order_id'] ) );

            $nextpayment_date = $wpdb->get_var( $wpdb->prepare( "SELECT next_payment_date FROM {$wpdb->prefix}novalnet_subscription_details  WHERE order_no=%d" , $subscription['order_id'] ) );

            $updated_recurring_date = date( 'Y-m-d', strtotime( $nextpayment_date ) );
             if ( ! empty( $subscription['expiry_date'] ) )
                $expiry_date = date( 'Y-m-d', strtotime( $subscription['expiry_date'] ) );

            $new_date_obj_value = new DateTime( $new_recurring_date );
            $next_payment_obj_value = new DateTime( $updated_recurring_date );
            $date_difference = $next_payment_obj_value->diff( $new_date_obj_value );
            if ( ( strtotime( $new_recurring_date ) <  strtotime( $updated_recurring_date ) ) || ( $date_difference->invert ) ) {
                $this->query_params['wcs_day'] = $this->query_params['wcs_month'] = $this->query_params['wcs_year'] = '';
                $response['message'] = sprintf( '<div class="error">%s</div>', __( 'The date should be in future', 'wc-novalnet' ) );
                echo json_encode( $response );
                exit();
            } else if ( ! empty( $expiry_date  ) && ( strtotime( $new_recurring_date ) >= strtotime( $expiry_date ) ) ) {
                $this->query_params['wcs_day'] = $this->query_params['wcs_month'] = $this->query_params['wcs_year'] = '';
                $response['message'] = sprintf( '<div class="error">%s</div>', __( 'Please enter a date before the expiration date.', 'woocommerce-subscriptions' ) );
                echo json_encode( $response );
                exit();
            } else {
                $date_extension_response = wc_novalnet_perform_xmlrequest( array(
                    'vendor_id'         => $result_set->vendor_id,
                    'vendor_authcode'   => $result_set->auth_code,
                    'product_id'        => $result_set->product_id,
                    'request_type'      => 'SUBSCRIPTION_PAUSE',
                    'tid'               => $result_set->tid,
                    'subs_id'           => $result_set->subs_id,
                    'pause_period'      => $date_difference->days,
                    'pause_time_unit'   => 'd',
                ) );
                if ( isset( $date_extension_response['status'] ) && 100 == $date_extension_response['status'] ) {
                    $date_extension_gateway_response = wc_novalnet_perform_xmlrequest( array(
                            'vendor_id'         => $result_set->vendor_id,
                            'vendor_authcode'   => $result_set->auth_code,
                            'product_id'        => $result_set->product_id,
                            'request_type'      => 'TRANSACTION_STATUS',
                            'tid'               => $result_set->tid
                        )
                    );
                    $date_update_msg = sprintf( __( 'Subscription renewal date has been successfully changed on %s','wc-novalnet'),  date_i18n( get_option('date_format'), strtotime( $date_extension_gateway_response['next_subs_cycle'] ) ) );

                    wc_novalnet_update_customer_notes( $subscription['order_id'], $date_update_msg );

                    $wpdb->update( "{$wpdb->prefix}novalnet_subscription_details", array( 'next_payment_date' => date('Y-m-d', strtotime( $date_extension_gateway_response[ 'next_subs_cycle' ] ) ) ),  array( 'order_no' => $subscription['order_id'] ) );
                    wc_novalnet_subs_admin_messages( $date_update_msg );

                } else {

                    $response_text = wc_novalnet_response_text( $date_extension_response );
                    update_post_meta( $post_id, '_order_recurring_total', WC_Subscriptions::format_total( $recurring_amount /100 ) );
                    if ( $response_text ) {
                        $order->add_order_note( $response_text );
                        wc_novalnet_subs_admin_messages( $response_text, 'error' );
                    }
                }
            }
        }
    }

    /**
     * Calls from the hook "woocommerce_process_shop_order_meta"
     * Perform Subscription recurring amount change option
     *
     * @param integer $post_id
     * @param object $post
     * @return void
     */
    public function perform_subscription_recurring_amount_update( $post_id, $post ) {
        global $wpdb;
        $order = new WC_Order( $post_id );

        $result_set = $wpdb->get_row( $wpdb->prepare( "SELECT vendor_id, auth_code, product_id, tid, subs_id FROM  {$wpdb->prefix}novalnet_transaction_detail WHERE order_no=%d" , $post_id ) );

        $recurring_amount = $wpdb->get_var( $wpdb->prepare("SELECT recurring_amount from {$wpdb->prefix}novalnet_subscription_details WHERE order_no=%d", $post_id ) );

        $temp_amount = ( isset( $this->query_params['_order_recurring_total'] ) ? $this->query_params['_order_recurring_total'] * 100  : '' );

        if ( ! empty( $temp_amount ) && ! empty( $recurring_amount ) ) {
            if ( $temp_amount != $recurring_amount )  {

                $amount_update_response = wc_novalnet_perform_xmlrequest( array(
                        'vendor_id'         => $result_set->vendor_id,
                        'vendor_authcode'   => $result_set->auth_code,
                        'product_id'        => $result_set->product_id,
                        'request_type'      => 'SUBSCRIPTION_UPDATE',
                        'subs_tid'          => $result_set->tid,
                        'payment_ref'       => $result_set->tid,
                        'subs_id'           => $result_set->subs_id,
                        'tid'               => $result_set->tid,
                        'amount'            => $temp_amount,
                        'update_flag'       => 'amount'
                    )
                );

                if ( isset( $amount_update_response['status'] ) && 100 == $amount_update_response['status'] ) {

                    $amount_update_msg = sprintf( __( 'Subscription recurring amount %s has been updated successfully','wc-novalnet' ), strip_tags( woocommerce_price( $this->query_params['_order_recurring_total']  ) ) );

                    wc_novalnet_update_customer_notes( $subscription['order_id'], $amount_update_msg );

                    $wpdb->update( "{$wpdb->prefix}novalnet_transaction_detail", array( 'recurring_amount' => $temp_amount ),  array( 'order_no' => $this->query_params['post_ID'] ) );
                    wc_novalnet_subs_admin_messages( $amount_update_msg );

                } else {

                    $response_text = wc_novalnet_response_text( $amount_update_response );
                    update_post_meta( $post_id, '_order_recurring_total', WC_Subscriptions::format_total( $result_set->recurring_amount /100 ) );
                    if ( $response_text ) {
                        $order->add_order_note( $response_text );
                        wc_novalnet_subs_admin_messages( $response_text, 'error' );
                    }
                }
            }
        }
    }
 }
 return new Novalnet_Subscriptions();
?>
