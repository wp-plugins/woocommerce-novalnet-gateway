<?php

 if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
 }

/**
 * Novalnet Admin Global Settings Payment
 *
 * This file is used for creating the Novalnet global configuration and Novalnet
 * administration portal in shop backend.
 *
 * Copyright (c) Novalnet
 *
 * This script is only free to the use for merchants of Novalnet. If
 * you have found this script useful a small recommendation as well as a
 * comment on merchant form would be greatly appreciated.
 *
 * @class       Novalnet_Settings
 * @package     Novalnet/Classes
 * @category    Class
 * @author      Novalnet
 * @located at  /includes/admin/
 */

 class Novalnet_Settings {

    public static $tab_name         = 'novalnet_settings';
    public static $option_prefix    = 'novalnet';
    public static function init() {
        $request = $_REQUEST;
        if ( isset( $request['page'] ) && 'wc-settings' == $request['page'] && isset( $request['tab'] ) && 'novalnet_settings' == $request['tab'] ) {
            self::perform_global_validation();
        }
        if ( isset( $request['page'] ) && 'wc-settings' == $request['page'] && isset( $request['tab'] ) && 'checkout' == $request['tab'] && isset( $request['section'] ) && ( 'wc_gateway_novalnet_invoice' == $request['section'] || 'wc_gateway_novalnet_prepayment' == $request['section'] ) ) {
            self::perform_reference_validation();
        }
        add_filter( 'woocommerce_settings_tabs_array',array( __CLASS__, 'add_novalnet_settings_tab' ), 50 );
        add_action( 'woocommerce_settings_tabs_novalnet_settings', array( __CLASS__, 'novalnet_settings_page' ));
        add_action( 'woocommerce_update_options_novalnet_settings', array(__CLASS__, 'update_novalnet_settings' ) );
        add_action( 'admin_enqueue_scripts', array(__CLASS__ , 'enqueue_scripts' ) );
    }

    /**
     * Redirects to the settings page based on error triggered
     *
     * @param none
     * @return void
     */
    public static function perform_global_validation() {
        $request  = $_REQUEST;
        if ( isset( $request['save'] ) ) {
            $is_backend_error = self::validate_configuration( $request );
            if ( $is_backend_error != '' ) {
                $redirect = get_admin_url() . 'admin.php?' . http_build_query( $_GET );
                $redirect = remove_query_arg( 'save' );
                $redirect = add_query_arg( 'wc_error', urlencode( esc_attr( $is_backend_error ) ), $redirect );
                wp_safe_redirect( $redirect );
                exit();
            }
        }
    }

    /**
     * Validate the data for Novalnet global configuration
     *
     * $param array $request
     * @return mixed
     */
    public static function validate_configuration( $request ) {
        foreach( $request as $k => $v ) {
            $key = str_replace( 'novalnet_','',$k);
                $options[ $key ] = $v;
        }
        if ( wc_novalnet_global_validation( $options ) )
            return __( 'Please fill in all the mandatory fields', 'wc-novalnet' );
        return '';
    }

    /**
     * Enqueue the Novalnet script and style files using in the shop admin
     * Calls from the hook "admin_enqueue_scripts"
     *
     * @param none
     * @return void
     */
    public static function enqueue_scripts() {
        wp_register_script( 'novalnet_admin', NN_Fns()->nn_plugin_url() . '/assets/js/novalnet_admin.js', '', NN_VERSION );
        wp_enqueue_script( 'novalnet_admin' );

        wp_register_style( 'novalnet_admin', NN_Fns()->nn_plugin_url() . '/assets/css/novalnet_admin.css', '', NN_VERSION );
        wp_enqueue_style( 'novalnet_admin' );
        if ( isset( $_REQUEST['page'] ) && $_REQUEST['page'] == 'subscriptions' ) {
            wc_novalnet_customize_subscription_cancel();
        }
    }

    /**
     * Adds Novalnet Tab to WooCommerce
     * Calls from the hook "woocommerce_settings_tabs_array"
     *
     * @param array $woocommerce_tab
     * @return array $woocommerce_tab
     */
    public static function add_novalnet_settings_tab( $woocommerce_tab ) {
        $woocommerce_tab[ self::$tab_name ] = 'Novalnet '. __( 'Global Configuration', 'wc-novalnet' );
        return $woocommerce_tab;
    }

    /**
     * Adds setting fields for Novalnet global configuration

     * @param none
     * @return void
     */
    public static function novalnet_settings_fields() {
        global $novalnet_payments;
        add_action( 'admin_footer', 'wc_novalnet_custom_admin_redirect' );
        $admin_url = admin_url( 'admin.php?page=wc-novalnet-admin' );
        $logpath = ((WOOCOMMERCE_VERSION > '2.2.0' ) ? wc_get_log_file_path( 'novalnetpayments' ) : "woocommerce/logs/novalnetpayments-".sanitize_file_name( wp_hash( 'novalnetpayments' )));
        $settings =  apply_filters( 'woocommerce_' . self::$tab_name, array(
            array(
                'title' => 'Novalnet ' . __( 'Global Configuration', 'wc-novalnet' ),
                'id'    => self::$option_prefix . '_global_settings',
                'desc'  => sprintf(__( 'For additional configurations login to %sNovalnet Administration Portal%s. To login to the Portal you need to have an account at Novalnet. If you don\'t have one yet, please contact <a href="mailto:sales@novalnet.de">sales@novalnet.de</a> / tel. +49 (089) 923068320<br/>To use the PayPal payment method please enter your PayPal API details in %sNovalnet Merchant Administration portal%s', 'wc-novalnet' ), '<a href="'.$admin_url.'" target="_new">', '</a>','<a href="'.$admin_url.'" target="_new">', '</a>' ),
                'type'  => 'title'
            ),
            array(
                'title' => __( 'Merchant ID','wc-novalnet' ),
                'desc'  => '<br/>' . __( 'Enter Novalnet merchant ID','wc-novalnet' ),
                'id'    => self::$option_prefix . '_vendor_id',
                'css'   => 'width:25em;',
                'type'  => 'text'
            ),
            array(
                'title' => __( 'Authentication code','wc-novalnet' ),
                'desc'  => '<br/>' .  __( 'Enter Novalnet authentication code','wc-novalnet' ),
                'id'    => self::$option_prefix . '_auth_code',
                'css'   => 'width:25em;',
                'type'  => 'text'
            ),
            array(
                'title'  => __( 'Project ID','wc-novalnet' ),
                'desc'   => '<br/>' . __( 'Enter Novalnet project ID','wc-novalnet' ),
                'id'     => self::$option_prefix . '_product_id',
                'css'    => 'width:25em;',
                'type'   => 'text'
            ),
            array(
                'title' => __( 'Tariff ID','wc-novalnet' ),
                'desc'  => '<br/>' . __( 'Enter Novalnet tariff ID','wc-novalnet' ),
                'id'    => self::$option_prefix . '_tariff_id',
                'css'   => 'width:25em;',
                'type'  => 'text'

            ),
            array(
                'title' => __( 'Payment access key','wc-novalnet' ),
                'desc'  => '<br/>' . __( 'Enter the Novalnet payment access key','wc-novalnet' ),
                'id'    => self::$option_prefix . '_key_password',
                'css'   => 'width:25em;',
                'type'  => 'text'
            ),
            array(
                'title' => __( 'Set a limit for on-hold transaction (in cents)','wc-novalnet' ),
                'desc'  => '<br/>' . __( 'In case the order amount exceeds mentioned limit, the transaction will be set on hold till your confirmation of transaction','wc-novalnet' ),
                'id'    => self::$option_prefix . '_manual_limit',
                'css'   => 'width:25em;',
                'type'  => 'text'
            ),
            array(
                'title' => __( 'Enable auto-fill','wc-novalnet' ),
                'desc'  => '<br/>' . __( 'The payment details will be filled automatically in the payment form during the checkout process', 'wc-novalnet' ),
                'id'    => self::$option_prefix . '_auto_refill',
                'type'  => 'select',
                'options' => array( '0' => __( 'No', 'wc-novalnet' ), '1' => __( 'Yes', 'wc-novalnet' ) )
            ),
            array(
                'title' => 'Proxy-Server',
                'desc'  => '<br/>' . __( 'Enter the IP address of your proxy server along with the port number in the following format IP Address : Port Number (if applicable)', 'wc-novalnet' ),
                'id'    => self::$option_prefix . '_proxy',
                'css'   => 'width:25em;',
                'type'  => 'text'
            ),
            array(
                'title' => __( 'Gateway timeout (in seconds)', 'wc-novalnet' ),
                'desc'  => '<br/>' . __( 'In case the order processing time exceeds the gateway timeout, the order will not be placed' , 'wc-novalnet' ),
                'id'    => self::$option_prefix . '_gateway_timeout',
                'css'   => 'width:25em;',
                'type'  => 'text',
                'default' => '240'
            ),
            array(
                'title'  => __( 'Debug log', 'wc-novalnet' ),
                'type'   => 'select',
                 'id'    => self::$option_prefix . '_debug_log',
                'options'=> array( '0' => __( 'No', 'wc-novalnet' ), '1' => __( 'Yes', 'wc-novalnet' ) ),
                'default'=> '0',
                'desc'   => sprintf( __( 'Novalnet payment events log in the mentioned path <code>%s.txt</code>', 'wc-novalnet' ), $logpath ),
            ),
            array(
                'title' => __( 'Referrer ID','wc-novalnet' ),
                'desc'  => '<br/>' . __( 'Enter the referrer ID of the person/company who recommended you Novalnet', 'wc-novalnet' ),
                'id'    => self::$option_prefix . '_referrer_id',
                'css'   => 'width:25em;',
                'type'  => 'text'
            ),
            array( 'type' => 'sectionend', 'id' => self::$option_prefix . '_global_settings' ),
            array( 'title' => __( 'Order status management for on-hold transaction(-s)', 'wc-novalnet' ), 'type' => 'title', 'desc' => '', 'id' => self::$option_prefix . '_status_mgmt' ),
            array(
                'title' => __( 'Confirmation order status','wc-novalnet' ),
                'id'    => self::$option_prefix . '_onhold_success_status',
                'type'  => 'select',
                'options' => wc_novalnet_get_woocommerce_order_status()
            ),
            array(
                'title' => __( 'Cancellation order status','wc-novalnet' ),
                'id'    => self::$option_prefix . '_onhold_cancel_status',
                'type'  => 'select',
                'options' => wc_novalnet_get_woocommerce_order_status()
            ),
            array( 'type' => 'sectionend', 'id' => self::$option_prefix . '_status_mgmt' ),
            array( 'title' => __( 'Subscription configuration', 'wc-novalnet' ), 'type' => 'title', 'desc' => '', 'id' => self::$option_prefix . '_subs_mgmt' ),
            array(
                'title' => __( 'Enable subscription','wc-novalnet' ),
                'id'    => self::$option_prefix . '_enable_subs',
                'type'  => 'select',
                'options' => array( '0' => __( 'No', 'wc-novalnet' ), '1' => __( 'Yes', 'wc-novalnet' ) ),
                'default' => '0'
            ),
            array(
                'title' => __( 'Subscription payments','wc-novalnet' ),
                'id'    => self::$option_prefix . '_subs_payments',
                'type'  => 'multiselect',
                'options' => array(
                                'novalnet_cc' => __( 'Credit card', 'wc-novalnet' ),
                                'novalnet_sepa' => __( 'Direct Debit SEPA', 'wc-novalnet' ),
                                'novalnet_invoice' => __( 'Invoice', 'wc-novalnet' ),
                                'novalnet_prepayment' => __( 'Prepayment', 'wc-novalnet' ),
                                'novalnet_paypal' => 'PayPal'
                            ),
                'default' => array( 'novalnet_cc', 'novalnet_sepa', 'novalnet_invoice', 'novalnet_prepayment', 'novalnet_paypal' ),
            ),
            array(
                'title' => __( 'Subscription Tariff ID','wc-novalnet' ),
                'desc'  => '<br/>' . __( 'Enter Novalnet subscription tariff ID','wc-novalnet' ),
                'id'    => self::$option_prefix . '_subs_tariff_id',
                'css'   => 'width:25em;',
                'type'  => 'text'
            ),
            array( 'type' => 'sectionend', 'id' => self::$option_prefix . '_subs_mgmt' ),
            array( 'title' => __( 'Merchant script management', 'wc-novalnet' ), 'type' => 'title', 'desc' => '', 'id' => self::$option_prefix . '_vendor_script' ),
            array(
                'title'     => __( 'Enable debug mode','wc-novalnet' ),
                'id'        => self::$option_prefix . '_callback_debug_mode',
                'desc'      => '<br/>' . __( 'Set the debug mode to execute the merchant script in debug mode', 'wc-novalnet' ),
                'type'      => 'select',
                'options'   => array( '0' => __( 'No', 'wc-novalnet' ), '1' => __( 'Yes', 'wc-novalnet' ) )
            ),
            array(
                'title'     => __( 'Enable test mode','wc-novalnet' ),
                'id'        => self::$option_prefix . '_callback_test_mode',
                'type'      => 'select',
                'options'   => array( '0' => __( 'No', 'wc-novalnet' ), '1' => __( 'Yes', 'wc-novalnet' ) )
            ),
            array(
                'title'     => __( 'Enable E-mail notification for callback','wc-novalnet' ),
                'id'        => self::$option_prefix . '_enable_callback',
                'type'      => 'select',
                'options'   => array( '0' => __( 'No', 'wc-novalnet' ), '1' => __( 'Yes', 'wc-novalnet' ) ),
                'default'   => '1'
            ),
            array(
                'title'     => __( 'E-mail address (To)','wc-novalnet' ),
                'id'        => self::$option_prefix . '_callback_emailtoaddr',
                'desc'      => '<br/>' . __( 'E-mail address of the recipient', 'wc-novalnet' ),
                'type'      => 'text',
                'css'   => 'width:25em;'
            ),
            array(
                'title'     => __( 'E-mail address (Bcc)','wc-novalnet' ),
                'id'        => self::$option_prefix . '_callback_emailbccaddr',
                'desc'      => '<br/>' . __( 'E-mail address of the recipient for BCC', 'wc-novalnet' ),
                'type'      => 'text',
                'css'   => 'width:25em;'
            ),
            array( 'type' => 'sectionend', 'id' => self::$option_prefix . '_vendor_script' )
        ) ) ;
        return apply_filters( 'woocommerce_' . self::$tab_name, $settings );
    }

    /**
     * Adds settings fields to the individual sections
     * Calls from the hook "woocommerce_settings_tabs_" {tab_name}
     *
     * @param none
     * @return void
     */
    public static function novalnet_settings_page() {
        woocommerce_admin_fields( self::novalnet_settings_fields() );
    }

    /**
     * Updates settings fields from individual sections
     * Calls from the hook "woocommerce_update_options_" {tab_name}
     *
     * @param none
     * @return void
     */
    public static function update_novalnet_settings(){
        woocommerce_update_options( self::novalnet_settings_fields() );
    }

    /**
     * Validate payment reference
     *
     * @param none
     * @return void
     */
    public static function perform_reference_validation () {
        $request = $_REQUEST;
        if ( isset( $request['save'] ) ) {
            $is_backend_error = wc_novalnet_reference_validation( $request ) ? __( 'Please select atleast one payment reference', 'wc-novalnet' ) : '';
			
            if ( $is_backend_error != '' ) {
                $redirect = get_admin_url() . 'admin.php?' . http_build_query( $request );
                $redirect = remove_query_arg( 'save' );
                $redirect = add_query_arg( 'wc_error', urlencode( esc_attr( $is_backend_error ) ), $redirect );
                wp_safe_redirect( $redirect );
                exit();
            }else{
				if(isset($_GET['wc_error'])) {
					unset($_GET['wc_error']);
				}
			}
        }
    }
 }

 Novalnet_Settings::init();
 ?>
