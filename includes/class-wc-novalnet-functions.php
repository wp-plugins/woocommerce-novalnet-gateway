<?php
 if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
 }

/**
 * Novalnet Functions
 *
 * General functions available on both the front-end and admin.
 *
 * Copyright (c) Novalnet
 *
 * This script is only free to the use for merchants of Novalnet. If
 * you have found this script useful a small recommendation as well as a
 * comment on merchant form would be greatly appreciated.
 *
 * @class       Novalnet_Functions
 * @version     10.1.0
 * @package     Novalnet/Classes
 * @category    Class
 * @author      Novalnet
 * @located at  /includes/
 *
 */

 class Novalnet_Functions {

    protected static $_instance = null;
    public $global_settings;

    /**
     * Returns instance of Novalnet_Functions class
     *
     * @param none
     * @return object
     */
    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public function __construct() {
        $this->global_settings = wc_novalnet_global_configurations();
    }

    /**
     * Get the plugin url.
     *
     * @param none
     * @return string
     */
    function nn_plugin_url() {
        return untrailingslashit( plugins_url( '/', NN_PLUGIN_FILE ) );
    }

    /**
     * Get the plugin path.
     *
     * @param none
     * @return string
     */
    function nn_plugin_path() {
        return untrailingslashit( plugin_dir_path( NN_PLUGIN_FILE ) );
    }
 }

 /**
  * Creates instance for the class Novalnet_Functions
  *
  * @param none
  * @return object
  */
 function NN_Fns() {
    return Novalnet_Functions::instance();
 }

 NN_Fns();

 /*********************** Common Functions ********************************************/

 //Filter the gateways displaying in the checkout
 add_filter( 'woocommerce_available_payment_gateways','wc_novalnet_filter_gateways' , 1 );

 // action on login and logout
 add_action( 'wp_logout', 'wc_novalnet_clear_sessions' );
 add_action( 'wp_login', 'wc_novalnet_clear_sessions' );


 if ( WOOCOMMERCE_VERSION < '2.3.0' ) {
    add_action( 'woocommerce_order_details_after_order_table','wc_novalnet_display_transaction_info' ) ;
 } else {
    add_action( 'woocommerce_order_items_table', 'wc_novalnet_align_transaction_info' );
 }

 /**
  * Calls from the hook "woocommerce_order_details_after_order_table"
  * To display the customer notes for the order in the success page
  *
  * @param object $order
  * @return void
  */
 function wc_novalnet_display_transaction_info( $order ) {
    global $novalnet_payments;
    if ( in_array( $order->payment_method, $novalnet_payments ) && $order->customer_note ) {
        echo wpautop( '<h2>' . __( 'Novalnet transaction details', 'novalnet' ) . '</h2>' );
        echo wpautop( wptexturize( $order->customer_note ) );
    }
 }

 /**
  * Calls from the hook "woocommerce_order_items_table"
  * To align the customer notes in the order success page
  *
  * @param object $order
  * @return void
  */
 function wc_novalnet_align_transaction_info( $order ) {
    global $novalnet_payments;
    if ( in_array( $order->payment_method, $novalnet_payments ) && $order->customer_note ) {
        $order->customer_note = wpautop( $order->customer_note );
    }
 }

 /**
  * Return the Wordpress language
  *
  * @param none
  * @return string
  */
 function wc_novalnet_shop_language() {
    return ( substr( get_bloginfo( 'language' ), 0, 2 ) == 'de' ) ? 'de' : 'en' ;
 }

 /**
  * Clears the Novalnet sessions
  *
  * @param none
  * @return void
  */
 function wc_novalnet_clear_sessions() {
    if ( isset( $_SESSION ) && $_SESSION ) {
        $_SESSION['novalnet'] = false;
    }

    if ( isset( $_SESSION['novalnet_cc'] ) )
        $_SESSION['novalnet_cc'] = false;
    if ( isset( $_SESSION['novalnet_invoice'] ) )
        $_SESSION['novalnet_invoice'] = false;
    if ( isset( $_SESSION['novalnet_sepa'] ) )
        $_SESSION['novalnet_sepa'] = false;

    if ( isset( WC()->session->novalnet_aff_id ) )
        unset(WC()->session->novalnet_aff_id);
 }

 /**
  * Clears the sessions on each page fragments
  *
  * @param none
  * @return void
  */
 function wc_novalnet_clear_session_fragments() {
    if ( isset( WC()->session->novalnet_thankyou_page ) )
        unset( WC()->session->novalnet_thankyou_page );
    if ( isset( WC()->session->novalnet_receipt_page ) )
        unset( WC()->session->novalnet_receipt_page );
    if ( isset( $_SESSION['novalnet_email_notes'] ) )
        unset( $_SESSION['novalnet_email_notes'] );
 }

 /**
  * Filters the Novalnet payment methods at checkout on Validation
  *
  * @param array $gateways
  * @return array $gateways
  */
 function wc_novalnet_filter_gateways( $gateways ) {
    global $novalnet_payments;
    $enabled_novalnet_payments = array();
    foreach ( $gateways as $k ) {

        if ( in_array( $k->id, $novalnet_payments ) ) {
            $enabled_novalnet_payments[] = $k->id;
        }
    }
    $validate = wc_novalnet_global_validation( NN_Fns()->global_settings );


    foreach ( $enabled_novalnet_payments as $payments ) {
        if ( $validate ) {
            unset( $gateways[ $payments ] );
        }
        if ( isset( $_SESSION[ $payments ]['invalid_count'] ) ) {
            if ( isset( $_SESSION[ $payments ]['time_limit'] ) && time() > $_SESSION[ $payments]['time_limit'] ) {
                unset( $_SESSION[ $payments ]['invalid_count'], $_SESSION[ $payments ]['time_limit'] );
            } else {
                unset( $gateways[ $payments ] );
            }
        }
    }

    return $gateways;
 }

 function wc_novalnet_get_woocommerce_order_status() {
    global $wpdb;
    if ( WOOCOMMERCE_VERSION >= '2.2.0' ) {
        $available_status = wc_get_order_statuses();
    }else {
        $sql = "SELECT slug, name FROM $wpdb->terms WHERE term_id in(SELECT term_id FROM $wpdb->term_taxonomy WHERE taxonomy='%s' )";
        $row = $wpdb->get_results( $wpdb->prepare( $sql,'shop_order_status' ) );

        for ( $i=0; $i < sizeof( $row ); $i++ ) {
            $available_status[ $row[ $i ]->slug ]=__( $row[$i]->name, 'woocommerce' );
        }
    }
    return $available_status;
 }

 /**
  * Customize the admin error messages on global validation
  *
  * @param none
  * @return void
  */
 function wc_novalnet_custom_admin_redirect() {
     $get = $_GET;
    $redirect = get_admin_url() . 'admin.php?' . http_build_query( $get );
    if ( isset( $get['wc_error'] ) ) {
        $redirect = remove_query_arg( 'wc_error' );
        echo '<script type="text/javascript">
            var url = "'.$redirect.'"
            jQuery("form").attr("action",url);
          </script>';
    }
 }

 /**
  * Redirects to the given url
  *
  * @param string $type
  * @param string $url
  * @return array
  */
 function wc_novalnet_redirect( $url = '' ) {
    if ( empty( $url ) ) {
        $url = WC()->cart->get_checkout_url();
    }
    return array(
        'result'   => 'success',
        'redirect' => $url
    );
 }

 /**
  * To display the success and failure messages from woocommerce session
  *
  * @param string $message
  * @param string $message_type
  * @return void
  */
 function wc_novalnet_display_info( $message, $message_type = 'error' ) {

    switch ( $message_type ) {
        case 'error':

            $_SESSION['errors'][] = $message;
            wc_add_notice( $message, 'error' );
            break;
        case 'message':
            $_SESSION['messages'][] = $message;
            wc_add_notice( $message );
            break;
    }
 }

 /**
  * Validates the Novalnet global configuration
  *
  * @param array $options
  * @return boolean
  */
 function wc_novalnet_global_validation( $option ) {
    $is_error = false;
    unset( $option['subs_payments'] );
    $options = array_map( "trim" , $option );

    $pattern = "/^\d+\|\d+\|\d+\|\w+\|\w+$/";
    $value = $options['vendor_id'].'|'.$options['product_id'].'|'.$options['tariff_id'].'|'. $options['auth_code'] . '|' .$options['key_password'];
    preg_match( $pattern, $value, $match );

    if ( empty( $match ) ) {
      $is_error = true;
    }
    if( ! $is_error && ( $options['enable_subs'] && ! wc_novalnet_digits_check( $options['subs_tariff_id'] ) ) || ( $options['callback_emailtoaddr'] && ! is_email( $options['callback_emailtoaddr'] ) ) || ( $options['manual_limit'] && ! wc_novalnet_digits_check( $options['manual_limit'] ) ) || ( $options['referrer_id'] && ! wc_novalnet_digits_check( $options['referrer_id'] ) ) || ( $options['gateway_timeout'] && ! wc_novalnet_digits_check( $options['gateway_timeout'] ) ) )
        $is_error = true;

    return $is_error;
 }

 /**
  * Validates the reference fields for Invoice and Prepayment
  *
  * @param array $option
  * @return boolean
  */
 function wc_novalnet_reference_validation( $option ) {
    $payment_type = ( 'wc_gateway_novalnet_invoice'== $option['section'] ) ? 'novalnet_invoice' : 'novalnet_prepayment';
    if( empty( $option['woocommerce_'. $payment_type .'_payment_reference_1'] ) && empty( $option['woocommerce_'. $payment_type .'_payment_reference_2'] ) && empty( $option['woocommerce_'. $payment_type .'_payment_reference_3'] ) ) {

        return true;
    }
    return false;
 }

 /**
  * Validates the given input data is numeric or not
  *
  * @param integer $input
  * @return boolean
  */
 function wc_novalnet_digits_check( $input ) {
    $input = trim( $input );
    return ( preg_match ("/^[0-9]+$/" , $input ) );
 }

 /**
  * Validates the given input data is alpha-numeric or not
  *
  * @param string $input
  * @return boolean
  */
 function wc_novalnet_alphanumeric_check( $input ) {
    $input  = trim( $input );
    return preg_match ("/^[0-9a-zA-Z]+$/" , $input );
 }

 /**
  * Perform encoding process for Novalnet parameters
  *
  * @param array $payment_parameters
  * @param array $encoded_values
  * @return void
  */
 function wc_novalnet_encode_data( &$payment_parameters, $encoded_values ) {
    $aff_details = wc_process_affiliate_action();
    $key = ( !empty( $aff_details['aff_accesskey'] ) ) ? $aff_details['aff_accesskey'] : NN_Fns()->global_settings['key_password'] ;

    foreach ( $encoded_values as $v ) {
        $data = $payment_parameters[ $v ];
        try {
            $crc = sprintf( '%u', crc32( $data ) );
            $data = $crc . "|" . $data;
            $data = bin2hex( $data . trim( $key ) );
            $data = strrev( base64_encode( $data) );
        } catch ( Exception $e ) {
            echo( 'Error: ' . $e );
        }
        $payment_parameters[ $v ] = $data;
    }
 }

 /**
  * Perform decoding process for the given data
  *
  * @param string $data
  * @return mixed $data | $value
  */
 function wc_novalnet_decode_data( $data = '' ) {
    $aff_details = wc_process_affiliate_action();
    $key = ( !empty( $aff_details['aff_accesskey'] ) ) ? $aff_details['aff_accesskey'] : NN_Fns()->global_settings['key_password'] ;
    try {
        $data = base64_decode( strrev( $data ) );
        $data = pack("H" . strlen( $data ), $data );
        $data = substr( $data, 0, stripos( $data, trim( $key ) ) );
        $pos = strpos( $data, "|" );
        if ( $pos === false ) {
                return ( "Error: CKSum not found!" );
        }
        $crc = substr( $data, 0, $pos );
        $value = trim(substr( $data, $pos + 1 ) );
        if ( $crc != sprintf( '%u', crc32( $value ) ) ) {
                return("Error; CKSum invalid!");
        }
        return $value;
    } catch ( Exception $e ) {
            echo( 'Error: ' . $e );
    }

    return $data;
 }

 /**
  * Generate hash with the given Novalnet parameters
  *
  * @param array $payment_parameters
  * @return string
  */
 function wc_novalnet_generate_hash( $payment_parameters = array() ) {
    $aff_details = wc_process_affiliate_action();
    $key = ( !empty( $aff_details['aff_accesskey'] ) ) ? $aff_details['aff_accesskey'] : NN_Fns()->global_settings['key_password'] ;
    return md5( $payment_parameters['auth_code'] . $payment_parameters['product'] . $payment_parameters['tariff'] . $payment_parameters['amount'] . $payment_parameters['test_mode'] . $payment_parameters['uniqid'] . strrev( trim( $key ) ) );
 }

 /**
  * Check the generated hash value with the hash from Novalnet response
  *
  * @param array $request
  * @return boolean
  */
 function wc_novalnet_check_hash( $request ) {
    return ( $request['hash2'] == wc_novalnet_generate_hash( $request ) );
 }

 /**
  * Checks the cart amount with the manual limit check value
  * to process the payment as on-hold transaction
  *
  * @param integer $order_amount
  * @return boolean
  */
 function wc_novalnet_manual_limit_check( $order_amount ) {
    $manual_limit_check = NN_Fns()->global_settings['manual_limit'] ;
    if ( $manual_limit_check != '' && intval( $manual_limit_check ) > 0 && (string) $order_amount >= $manual_limit_check ) {
        return true;
    }
    return false;
 }

 /**
  * submit the given request to the given url
  *
  * @param array $request
  * @param string $url
  * @return array $ary_response
  */
 function wc_novalnet_submit_request( $request, $url = '' ) {
    if ( empty( $url ) ) {
        $url = 'https://payport.novalnet.de/paygate.jsp';
    }
    $data = wc_novalnet_perform_curl_request( $url, $request );
    wp_parse_str( $data, $ary_response );
    return $ary_response;
 }

 /**
  * Generate the unique random string
  *
  * @param none
  * @return string
  */
 function wc_novalnet_random_string() {
    $randomwordarray=array( "a","b","c","d","e","f","g","h","i","j","k","l","m","1","2","3","4","5","6","7","8","9","0");
    shuffle( $randomwordarray );
    return substr( implode( $randomwordarray,"" ), 0, 30 );
 }

 /**
  * Retrieves the customer details if available in the database
  *
  * @param none
  * @return string
  */
 function wc_novalnet_get_existing_customer_data() {
    global $wp;
    $order_number = ( isset( $_REQUEST['order-pay'] ) ? $_REQUEST['order-pay'] : isset( $wp->query_vars['order-pay'] ) ? $wp->query_vars['order-pay'] : '' );
    if ( ! empty( $order_number ) && isset( $_REQUEST['pay_for_order'] ) ) {
        $order = new WC_Order( $order_number );

        return ( 'first_name=' . trim( $order->billing_first_name ) . '|last_name=' . trim( $order->billing_last_name ) . '|company=' . trim( $order->billing_company ) . '|city=' . trim( $order->billing_city ) . '|email=' . trim( $order->billing_email ) . '|zip=' . trim( $order->billing_postcode ) . '|address=' . trim( $order->billing_address_1 ) . '|address2=' . trim( $order->billing_address_2 ) );
    }
    return '';
 }

 /**
  * Returns the hash value from the database for Direct Debit SEPA payment
  *
  * @param none
  * @return void | string $process_key
  */
 function wc_novalnet_sepa_last_payment_refill( $last_succ_refill ) {
    global $current_user, $wpdb;
    if ( $current_user && !empty( $current_user->ID ) ) {
        $db_data = $wpdb->get_row( $wpdb->prepare( "SELECT payment_type, process_key FROM {$wpdb->prefix}novalnet_transaction_detail WHERE customer_id=%d and status=100 and active=1 order by id desc limit 1", $current_user->ID ), ARRAY_A );
        if ( $db_data['payment_type'] == 'novalnet_sepa' && !empty( $db_data['process_key'] ) && $last_succ_refill )
            return $db_data['process_key'];
    }
    return '';
 }

 /**
  * Transfer data via curl library (consists of various protocols)
  *
  * @param string $url
  * @param array | string $request_data
  * @return void | string $response
  */
 function wc_novalnet_perform_curl_request( $url, $request_data ) {
      if ( ! empty( $url ) && ! empty( $request_data ) ) {
        $curl_timeout = wc_novalnet_digits_check( NN_Fns()->global_settings['gateway_timeout'] ) ? NN_Fns()->global_settings['gateway_timeout'] : 0;
        $ch = curl_init( $url );
        curl_setopt( $ch, CURLOPT_POST, 1 );
        curl_setopt( $ch, CURLOPT_POSTFIELDS, $request_data );
        curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, 0 );
        curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, false );
        curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
        curl_setopt( $ch, CURLOPT_TIMEOUT, ( empty( $curl_timeout ) ? 240 : $curl_timeout ) );
        if ( ! empty( NN_Fns()->global_settings['proxy'] ) ) {
            curl_setopt( $ch, CURLOPT_PROXY, NN_Fns()->global_settings['proxy'] );
        }
        $response = curl_exec( $ch );
        curl_close( $ch );
        return $response;
    }
    return array();
 }

 /**
  * Perform the basic validation and process the XML request call to Novalnet server.
  *
  * @param array $request_param
  * @return void | array $data
  */
 function wc_novalnet_perform_xmlrequest( $request_param ) {

    $validate_data = wc_novalnet_is_basic_validation( $request_param , true);
    if ( $validate_data ) {
        $data_xml = wc_novalnet_perform_curl_request( 'https://payport.novalnet.de/nn_infoport.xml', wc_novalnet_form_xmlrequest( $request_param ) );
        $data = json_decode( json_encode( (array) simplexml_load_string( $data_xml ) ),1 );
        return $data;
    } else
        return array();
 }

 /**
  * Forms the XML request
  *
  * @param array $request_param
  * @return string $urlparam
  */
 function wc_novalnet_form_xmlrequest( $request_param ) {

    $urlparam = '<?xml version="1.0" encoding="UTF-8"?><nnxml><info_request>';
    foreach ( $request_param as $key => $value ) {
        $urlparam .= '<' . $key . '>' . $value . '</' . $key . '>';
    }
    $urlparam .='</info_request></nnxml>';

    return $urlparam;
 }

 /**
  * Perform basic validation while on payment parameters
  *
  * @param array $api_params
  * @param boolean $type
  * @return boolean
  */
 function wc_novalnet_is_basic_validation( $api_params, $type = false ) {

    if ( $type ) {
        if ( ! empty( $api_params['vendor_id'] ) && ! empty( $api_params['vendor_authcode'] ) && ! empty( $api_params['product_id'] ) && ! empty( $api_params['tid'] ) ) {
            return true;
        }
    } else {
        if ( ! empty( $api_params['vendor'] ) && ! empty( $api_params['auth_code'] ) && ! empty( $api_params['product'] ) &&  ! empty( $api_params['tariff'] ) && ! empty( $api_params['tid'] ) ) {
            return true;
        }
    }
    return false;
 }

 /**
  * Retrieves the messages from server response
  *
  * @param array $response
  * @return string
  */
 function wc_novalnet_response_text( $response ) {

    return ( isset( $response['status_text'] ) ? $response['status_text'] : ( isset( $response['status_desc'] ) ? $response['status_desc'] : ( isset( $response['status_message'] ) ? $response['status_message'] : ( isset(  $response['subscription_pause'] ) ? $response['subscription_pause']['status_message'] : ( isset(  $response['subscription_update'] ) ? $response['subscription_update']['status_message'] : ( isset(  $response['pin_status'] ) ? $response['pin_status']['status_message'] : '' ) ) ) ) ) );
 }

 /**
  * Check the test mode value to display test order text in comments
  *
  * @param boolean $response_mode
  * @param boolean $shop_mode
  * @return boolean
  */
 function wc_novalnet_check_test_mode_status( $response_mode, $shop_mode = false ) {
    return ( ( ( isset( $response_mode ) && $response_mode ) ||  $shop_mode ) ? 1 : 0 );
 }

 /**
  * Fetch the global configuration values from the database
  *
  * @param boolean $return_data
  * @return array
  */
 function wc_novalnet_global_configurations( $return_data = false ) {

    $configuration_data =  array(
        'vendor_id'     => get_option( 'novalnet_vendor_id' ),
        'auth_code'     => get_option( 'novalnet_auth_code' ),
        'product_id'    => get_option( 'novalnet_product_id' ),
        'tariff_id'     => get_option( 'novalnet_tariff_id' ),
        'enable_subs'   => get_option( 'novalnet_enable_subs' ),
        'subs_tariff_id'=> get_option( 'novalnet_subs_tariff_id' ),
        'subs_payments' => get_option( 'novalnet_subs_payments' ),
    );

    if ( ! $return_data ) {
        $configuration_data['key_password']         = get_option( 'novalnet_key_password' );
        $configuration_data['manual_limit']         = get_option( 'novalnet_manual_limit' );
        $configuration_data['auto_refill']          = get_option( 'novalnet_auto_refill' );
        $configuration_data['proxy']                = get_option( 'novalnet_proxy' );
        $configuration_data['referrer_id']          = get_option( 'novalnet_referrer_id' );
        $configuration_data['debug_log']            = get_option( 'novalnet_debug_log' );
        $configuration_data['gateway_timeout']      = get_option( 'novalnet_gateway_timeout' );
        $configuration_data['onhold_cancel_status'] = get_option( 'novalnet_onhold_cancel_status' );
        $configuration_data['onhold_success_status']= get_option( 'novalnet_onhold_success_status' );
        $configuration_data['callback_emailtoaddr'] = get_option( 'novalnet_callback_emailtoaddr' );
        $configuration_data['callback_emailbccaddr']= get_option( 'novalnet_callback_emailbccaddr' );
    }

    return $configuration_data;
 }

 /**
  * Return the server / remote address
  *
  * @param string $type
  * @return string
  */
 function wc_novalnet_server_addr( $type = 'REMOTE_ADDR' ) {
    return ( ( $_SERVER[ $type] == '::1' ) ? '127.0.0.1' : $_SERVER[ $type ] );
 }

 /**
  * Validates the datas required to display the fraud prevention option
  *
  * @param array $option
  * @return boolean
  */
 function wc_novalnet_fraud_prevention_option( $option ) {
    $current_amount = WC()->session->total;
    $current_amount = wc_novalnet_currency_format( $current_amount );

    if ( in_array( WC()->customer->country , array( 'AT', 'DE', 'CH' ) ) && !empty( $option['pin_by_callback'] ) && ( isset( $option['pin_amt_limit'] ) && ( empty( $option['pin_amt_limit'] ) || $option['pin_amt_limit'] <= (string) $current_amount ) && ! isset( $_REQUEST['pay_for_order'] ) ) ) {
            return true;
    }
    return false;
 }

 /**
  * Get the order number from the GLOBAL values
  *
  * @param none
  * @return integer $post_no
  */
 function wc_novalnet_get_order_no() {
    global $wpdb, $wp;
    $post_no = ( ( isset( $_REQUEST['order-pay'] ) && $_REQUEST['order-pay'] ) ? $_REQUEST['order-pay'] : ( ( isset( $_REQUEST['order_id'] ) && $_REQUEST['order_id'] ) ? $_REQUEST['order_id'] : ( ( isset( $wp->query_vars['order-pay'] ) && $wp->query_vars['order-pay'] ) ? $wp->query_vars['order-pay'] : '' ) ) );

    if ( empty( $post_no ) && isset( $_REQUEST['key'] ) ) {
        $post_no = $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM {$wpdb->prefix}postmeta WHERE meta_key='_order_key' AND meta_value=%s", esc_attr( $_REQUEST['key'] ) ) );
    }
    return $post_no;
 }

 /**
  * Restrict to re-pay for the paid order in Novalnet when the order-status is in pending
  * and payment method change for subscription if gateway status != 100
  *
  * @param none
  * @return void
  */
 function wc_novalnet_check_pay_status() {

    global $wpdb;

    $post_no = wc_novalnet_get_order_no();

    if ( ! empty( $post_no ) && isset( $_REQUEST['pay_for_order'] ) && ! empty( $_REQUEST['pay_for_order'] ) ) {

		$nn_version_check = get_post_meta( $post_no,'_nn_version',true );

		$nov_payment = get_post_meta( $post_no,'_payment_method',true );

        $gateway_status = $wpdb->get_var( $wpdb->prepare( "SELECT gateway_status FROM {$wpdb->prefix}novalnet_transaction_detail WHERE order_no=%d", $post_no ) );

		$nn_status_check = get_post_meta( $post_no,'_nn_status_code', true );

        if ( ! isset( $_REQUEST['change_payment_method'] ) && ! empty( $post_no ) && ! empty( $gateway_status ) ) {
            $message = 'Novalnet Transaction for the Order has been executed / cancelled already.';
            if ( substr( get_bloginfo('language'), 0, 2 ) == 'de' )
                $message = 'Die Novalnet-Buchung für die Bestellung wurde schon ausgeführt / abgeschlossen.';

        } else if ( isset( $_REQUEST['change_payment_method'] ) && $gateway_status && $gateway_status != 100 ) {
            $message = 'Your order is not confirmed, kindly contact the merchant.';
            if ( substr( get_bloginfo('language'), 0, 2 ) == 'de' )
                    $message = 'Ihre Bestellung wurde nicht bestätigt; kontaktieren Sie bitte den Händler.';
        } else if ( !$nn_version_check && $nn_status_check && $nov_payment && substr( $nov_payment, 0, 3 ) == 'nov' ) {
			$message = 'This operation is not possible for this order.';
			if ( substr( get_bloginfo('language'), 0, 2 ) == 'de' )
                    $message = 'Dieser Vorgang ist für diese Bestellung nicht möglich.';
		}

        if ( ! empty( $message ) ) {  ?>
            <style>
            .woocommerce{
                display : none;
            }
            </style>
            <?php
            echo '<script src="' . NN_Fns()->nn_plugin_url() . '/assets/js/jquery-min.js"></script>';
            echo '<script type="text/javascript">
                   $( document ).ready(function() {
                   $(".entry-content").html("<p style=font-size:17px><b>' . $message .'</p></b>");
                   });
                  </script>';

        }
    }
 }

 /**
  * Forms the Novalnet payment details
  *
  * @param none
  * @return array
  */
 function wc_novalnet_payment_details() {
    $plugins_url = NN_Fns()->nn_plugin_url();

    return array(
        'novalnet_cc' => array(
            'payment_key'       => 6,
            'payment_name_en'   => 'Credit Card',
            'payment_name_de'   => 'Kreditkarte',
            'description_en'    => 'The amount will be debited from your credit card once the order is submitted',
            'description_de'    =>'Der Betrag wird von Ihrer Kreditkarte abgebucht, sobald die Bestellung abgeschickt wird.',
            'payment_type'      => 'CREDITCARD',
            'paygate_url'       => 'https://payport.novalnet.de/paygate.jsp',
            'payment_logo'      => $plugins_url . '/assets/images/novalnet_cc.png'
        ),
        'novalnet_eps' => array(
            'payment_key'       => 50,
            'payment_name_en'   => 'EPS',
            'payment_name_de'   => 'EPS',
            'description_en'    => 'After the successful verification, you will be redirected to Novalnet secure order page to proceed with the payment',
            'description_de'    => 'Nach der erfolgreichen Überprüfung werden Sie auf die abgesicherte Novalnet-Bestellseite umgeleitet, um die Zahlung fortzusetzen.',
            'payment_type'      => 'EPS',
            'paygate_url'       => 'https://payport.novalnet.de/eps_payport',
            'payment_logo'      => $plugins_url . '/assets/images/novalnet_eps.png'

        ),
        'novalnet_ideal' => array(
            'payment_key'       => 49,
            'payment_name_en'   => 'iDEAL',
            'payment_name_de'   => 'iDEAL',
            'description_en'    => 'After the successful verification, you will be redirected to Novalnet secure order page to proceed with the payment',
            'description_de'    => 'Nach der erfolgreichen Überprüfung werden Sie auf die abgesicherte Novalnet-Bestellseite umgeleitet, um die Zahlung fortzusetzen.',
            'payment_type'      => 'IDEAL',
            'paygate_url'       => 'https://payport.novalnet.de/online_transfer_payport',
            'payment_logo'      => $plugins_url . '/assets/images/novalnet_ideal.png'
        ),
        'novalnet_invoice' => array(
            'payment_key'       => 27,
            'payment_name_en'   => 'Invoice',
            'payment_name_de'   => 'Kauf auf Rechnung',
            'description_en'    =>  'Once you\'ve submitted the order, you will receive an e-mail with account details to make payment',
            'description_de'    => 'Nachdem Sie die Bestellung abgeschickt haben, erhalten Sie eine Email mit den Bankdaten, um die Zahlung durchzuführen.',
            'payment_type'      => 'INVOICE',
            'paygate_url'       => 'https://payport.novalnet.de/paygate.jsp',
            'payment_logo'      => $plugins_url . '/assets/images/novalnet_invoice.png'
        ),
        'novalnet_paypal' => array(
            'payment_key'       => 34,
            'payment_name_en'   => 'PayPal',
            'payment_name_de'   => 'PayPal',
            'description_en'    => 'After the successful verification, you will be redirected to Novalnet secure order page to proceed with the payment',
            'description_de'    => 'Nach der erfolgreichen Überprüfung werden Sie auf die abgesicherte Novalnet-Bestellseite umgeleitet, um die Zahlung fortzusetzen.',
            'payment_type'      => 'PAYPAL',
            'paygate_url'       => 'https://payport.novalnet.de/paypal_payport',
            'payment_logo'      => $plugins_url . '/assets/images/novalnet_paypal.png'
        ),
        'novalnet_prepayment' => array(
            'payment_key'       => 27,
            'payment_name_en'   => 'Prepayment',
            'payment_name_de'   => 'Vorauskasse',
            'description_en'    =>  'Once you\'ve submitted the order, you will receive an e-mail with account details to make payment',
            'description_de'    => 'Nachdem Sie die Bestellung abgeschickt haben, erhalten Sie eine Email mit den Bankdaten, um die Zahlung durchzuführen.',
            'payment_type'      => 'PREPAYMENT',
            'paygate_url'       => 'https://payport.novalnet.de/paygate.jsp',
            'payment_logo'      => $plugins_url . '/assets/images/novalnet_prepayment.png'
        ),
        'novalnet_sepa' => array(
            'payment_key'       => 37,
            'payment_name_en'   => 'Direct Debit SEPA',
            'payment_name_de'   => 'Lastschrift SEPA',
            'description_en'    => 'Your account will be debited upon the order submission',
            'description_de'    => 'Ihr Konto wird nach Abschicken der Bestellung belastet.',
            'payment_type'      => 'DIRECT_DEBIT_SEPA',
            'paygate_url'       => 'https://payport.novalnet.de/paygate.jsp',
            'payment_logo'      => $plugins_url . '/assets/images/novalnet_sepa.png'
        ),
        'novalnet_instantbank' => array(
            'payment_key'       => 33,
            'payment_name_en'   => 'Instant Bank Transfer',
            'payment_name_de'   => 'Sofortüberweisung',
            'description_en'    => 'After the successful verification, you will be redirected to Novalnet secure order page to proceed with the payment',
            'description_de'    => 'Nach der erfolgreichen Überprüfung werden Sie auf die abgesicherte Novalnet-Bestellseite umgeleitet, um die Zahlung fortzusetzen.',
            'payment_type'      => 'ONLINE_TRANSFER',
            'paygate_url'       => 'https://payport.novalnet.de/online_transfer_payport',
            'payment_logo'      => $plugins_url . '/assets/images/novalnet_instantbank.png'
        )
    );
 }

 /************* Affiliate Function ********************************/

 function wc_process_affiliate_action() {
    global $wpdb, $current_user;

    $session_aff_data =  WC()->session->get( 'novalnet_aff_id' ) ;

    if ( ! empty( $session_aff_data ) ) {

        $aff_acc_details = $wpdb->get_row( $wpdb->prepare( "SELECT aff_authcode,aff_accesskey FROM {$wpdb->prefix}novalnet_aff_account_detail WHERE aff_id=%d", $session_aff_data ), ARRAY_A );

        if ( ! empty( $aff_acc_details ) )
            $aff_acc_details['aff_id'] = $session_aff_data;

        return $aff_acc_details;

    } else if ( ! empty( $current_user->ID ) ) {
        $aff_acc_details = $wpdb->get_row( $wpdb->prepare( "SELECT aff_id, aff_authcode, aff_accesskey FROM {$wpdb->prefix}novalnet_aff_account_detail WHERE aff_id=(SELECT aff_id FROM {$wpdb->prefix}novalnet_aff_user_detail WHERE customer_id=%d)", $current_user->ID ), ARRAY_A );

        return $aff_acc_details;
    }

    return array();
 }

 /************* Subscription Functions ****************************/

 /**
  * Assign the tariff id for the payment process
  *
  * @param object $order
  * @param string $payment_id
  * @param boolean $exists
  * @return integer
  */
 function wc_assign_payment_tariff( $order ) {
    if ( in_array( $order->payment_method, NN_Fns()->global_settings['subs_payments'] ) && class_exists( 'WC_Subscriptions' ) && WC_Subscriptions_Order::order_contains_subscription( $order ) && NN_Fns()->global_settings['enable_subs'] ) {
        return NN_Fns()->global_settings['subs_tariff_id'];
    } else {
        return NN_Fns()->global_settings['tariff_id'];
    }
 }

 /**
  * Retreives the subscription cancel reasons
  *
  * @param boolean $merge
  * @return string | array $cancel_arrs
  */
 function wc_novalnet_subscription_cancel_list( $merge = false ) {
    $cancel_arrs = array( '--' . __( 'Select','wc-novalnet' ) . '--', __( 'Product is costly','wc-novalnet' ), __( 'Cheating','wc-novalnet' ), __( 'Partner interfered','wc-novalnet' ), __( 'Financial problem','wc-novalnet' ), __( 'Content does not match my likes','wc-novalnet' ), __( 'Content is not enough','wc-novalnet' ), __( 'Interested only for a trial','wc-novalnet' ), __( 'Page is very slow','wc-novalnet' ), __( 'Satisfied customer','wc-novalnet' ), __( 'Logging in problems','wc-novalnet' ), __( 'Other','wc-novalnet' ) ) ;

    return ( ( $merge == true ) ? implode( "|", $cancel_arrs ) : $cancel_arrs );
 }

 /**
  * Authenticates the order processed by Novalnet
  *
  * @param integer $order_id
  * @return string
  */
 function wc_novalnet_authenticate_subs_cancel( $order_id ) {
    global $wpdb;
    $trans_details = $wpdb->get_row( $wpdb->prepare( "SELECT id, subs_id FROM {$wpdb->prefix}novalnet_transaction_detail WHERE order_no=%d ORDER BY id DESC limit 1", $order_id ), ARRAY_A);
	if ( empty( $trans_details ) ) {
		$order_comments = $wpdb->get_var( $wpdb->prepare( "SELECT post_excerpt FROM $wpdb->posts where ID='%s'", $order_id ) );
		preg_match( '/ID[\s]*:[\s]*([0-9]{17})/', $order_comments, $nn_tid );
		$config_data = wc_novalnet_global_configurations( true );

		$transaction_status_request = array(
			'vendor_id'      => $config_data['vendor_id'],
			'vendor_authcode'=> $config_data['auth_code'],
			'product_id'     => $config_data['product_id'],
			'request_type'   => 'TRANSACTION_STATUS',
			'tid'            => $nn_tid[1]
		);

		$trans_details = wc_novalnet_perform_xmlrequest( $transaction_status_request );
	}

    return ( ! empty( $trans_details['subs_id'] ) ? 'success' : 'failure' );
 }

 /**
  * Displays the messages for subscription actions
  *
  * @param string $message
  * @param string $type
  * @return void
  */
 function wc_novalnet_subs_admin_messages( $message, $type = 'message' ) {
    if ( is_admin() ) {
        global $msg_action;
        $msg_action['custom_action'] = true;

        $action = ( $type == 'error' ) ? 'error_messages' : 'messages';
        $msg_action[ $action ] = array( $message );

        add_filter( 'woocommerce_subscriptions_list_table_pre_process_actions', 'wc_novalnet_admin_messages' );
    } else {
        wc_novalnet_display_info( $message, $type );
        wp_safe_redirect( get_permalink( woocommerce_get_page_id( 'myaccount' ) ) );
        exit;
    }
 }

 /**
  * Filters used to display the subscription admin releted messages
  *
  * @param array $msg_action
  * @return array $msg_action
  */
 function wc_novalnet_admin_messages() {
    global $msg_action;
    return $msg_action;
 }

 /**
  * Formats amount
  *
  * @param $amount
  * @return integer
  */
 function wc_novalnet_currency_format( $amount ) {
    return str_replace( ',', '', sprintf( "%0.2f", $amount ) ) * 100;
 }
 /**
  * Check for 3d secure enable for fraud module
  *
  * @param $amount
  * @return integer
  */
 function wc_novalnet_check_3d_secure( $current_payment_id, $settings  ) {
    return ( 'novalnet_cc' == $current_payment_id && $settings['cc_secure_enabled'] ) ? false : true;
 }

 /**
  * Calls from the hook "woocommerce_before_my_account"
  * Add the custom fields used for subscription cancel option
  *
  * @param none
  * @return void
  */
 function wc_novalnet_customize_subscription_cancel() {
    $reasons = wc_novalnet_subscription_cancel_list( true );
    echo '<div class="nov_loader" id="subs_loader" style="display:none"></div>
    <link rel="stylesheet" type="text/css" media="all" href="' . NN_Fns()->nn_plugin_url() . '/assets/css/novalnet_checkout.css">
    <input type="hidden" id="novalnet_url" value="' . site_url() . '" />
    <input type="hidden" id="novalnet_shop_admin" value="' . is_admin() . '" />
    <input type="hidden" id="avail_reasons" value="' . $reasons . '" />
    <input type="hidden" id="subs_cancel_button" value="' . __( 'Confirm', 'wc-novalnet' ) . '" />
    <script src="' . NN_Fns()->nn_plugin_url() . '/assets/js/jquery-min.js" type="text/javascript"></script>
    <script src="' . NN_Fns()->nn_plugin_url() . '/assets/js/novalnet_subscription.js" type="text/javascript"></script> ';
 }

 /**
  * Update the payment comments to the order information on shop table
  *
  * @param integer $post_id
  * @param string $comments
  * @param boolean $append
  * @return boolean
  */
 function wc_novalnet_update_customer_notes( $post_id, $comments, $append = true ) {
    $new_line = "\n";
    $woo_order = new WC_Order( $post_id );
    if ( $append ) {
        if ( $woo_order->customer_note )
            $woo_order->customer_note .= $new_line;
        $woo_order->customer_note .=  $new_line . $comments;
    } else {
        $woo_order->customer_note = $new_line . $comments;
    }
    $woo_order->customer_note = html_entity_decode( $woo_order->customer_note, ENT_QUOTES, 'UTF-8' );
    $update_notes = array(
        'ID'            => $woo_order->id,
        'post_excerpt'  => $woo_order->customer_note,
    );
    wp_update_post( $update_notes);
    $woo_order->add_order_note( $comments);
    return true;
 }

?>
