<?php
/*
 * Plugin Name: Novalnet Payment Gateway for WooCommerce
 * Plugin URI:  http://www.novalnet.com/modul/woocommerce
 * Description: Extends WooCommerce to process payment service with Novalnet.
 * Author:      Novalnet
 * Author URI:  https://www.novalnet.de
 * Version:     10.0.0
 * Text Domain: wc-novalnet
 * Domain Path: /languages/
 * License:     GPLv2
 */

/**
 * Copyright (c) Novalnet
 *
 * This script is only free to the use for merchants of Novalnet. If
 * you have found this script useful a small recommendation as well as a
 * comment on merchant form would be greatly appreciated.
 */

 // Exit if accessed directly
 if ( ! defined('ABSPATH') )
    exit;

 include_once( dirname( __FILE__ ) .'/novalnet-install.php' );
 register_activation_hook( __FILE__ , 'novalnet_activation_process' );
 register_deactivation_hook( __FILE__ , 'novalnet_uninstallation_process' );
 add_action('plugins_loaded', 'init_wc_novalnet_payment_gateway', 0);

 function novalnet_api_gateway_response() {
    if ( ( isset( $_POST['input2'] ) && $_POST['input2'] == 'novalnet_class_name' && !empty( $_POST['inputval2']  ) ) || ( isset( $_REQUEST['payment_error'] ) ) ) {
        do_action( 'novalnet_api_gateway_process' , $_REQUEST );
    } else if ( isset( $_REQUEST['wc_api'] ) && $_REQUEST['wc_api'] == 'novalnet_callback' ) {
        include_once( dirname( __FILE__ ) . '/novalnet-callback-handler.php');
        do_action( 'woocommerce_api_novalnet_callback', $_REQUEST );
    } else if ( isset( $_REQUEST['nn_aff_id'] ) ) {
		WC()->session->set( 'novalnet_aff_id', $_REQUEST['nn_aff_id'] );
	} else if( isset( $_REQUEST['pay_for_order'] ) && $_REQUEST['pay_for_order'] ) {
		wc_novalnet_check_pay_status();
	}
 }

 add_action( 'init', 'novalnet_api_gateway_response');

 /**
  * Check if WooCommerce is active, and if it isn't, disable Novalnet payments.
  */
 function wc_novalnet_checks_woocommerce_active() {
        echo '<div id="notice" class="error"><p>';
        echo sprintf( __('WooCommerce plugin must be active for the plugin <b>Novalnet Payment Gateway for WooCommerce</b>.Kindly %s install & activate it %s ', 'wc-novalnet'), '<a href="http://www.woothemes.com/woocommerce/" target="_new">', '</a>' );
        echo '</p></div>';
 }

 /*
  * Adds separate sub-menu for Novalnet administration portal under WooCommerce
  */
 function add_nn_admin_menus() {
    add_submenu_page( 'woocommerce' , __('Novalnet Administration Portal', 'wc-novalnet'), __('Novalnet Administration Portal', 'wc-novalnet') , 'manage_options', 'wc-novalnet-admin', 'novalnet_admin_information'  );
 }

 /*
  * Novalnet merchant administration portal will be been loaded
  */
 function novalnet_admin_information(){
    ?>
    <h2> <?php echo __('Novalnet Administration Portal', 'wc-novalnet'); ?></h2>
    <div class="nn_map_header"><?php echo __( 'Login here with Novalnet merchant credentials. For the activation of new payment methods please contact <a href="mailto:support@novalnet.de">support@novalnet.de</a>', 'wc-novalnet' ); ?> </div>
        <iframe frameborder="0" width="100%" height="600px" border="0" src="https://admin.novalnet.de/">
    </iframe>
    <?php
 }

 $novalnet_payments = array( 'novalnet_cc', 'novalnet_sepa', 'novalnet_eps', 'novalnet_ideal', 'novalnet_invoice', 'novalnet_instantbank', 'novalnet_paypal', 'novalnet_prepayment' );

 add_action( 'admin_menu',  'add_nn_admin_menus' );

 function novalnet_action_links( $links ) {
   $add_links = array( '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=novalnet_settings' ) . '">' . __('Configuration','wc-novalnet') . '</a>' );
   return array_merge( $add_links , $links );
 }

 add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), 'novalnet_action_links' );

 define( 'NN_VERSION', '10.0.0' );
 define( 'NN_PLUGIN_FILE', __FILE__ );

 function init_wc_novalnet_payment_gateway() {

	/* loads the Novalnet language translation strings */
    load_plugin_textdomain( 'wc-novalnet', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

    if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
        add_action( 'admin_notices', 'wc_novalnet_checks_woocommerce_active' );
        return;
    }

    // include all the payment files
    include_once( dirname( __FILE__ ) . '/includes/class-wc-novalnet-functions.php' );
    include_once( dirname( __FILE__ ) . '/includes/class-wc-novalnet-subscriptions.php' );
    include_once( dirname( __FILE__ ) . '/includes/admin/class-wc-novalnet-settings.php' );
    include_once( dirname( __FILE__ ) . '/includes/admin/class-wc-meta-box-novalnet-extensions.php' );

    if ( ! class_exists( 'Novalnet_Payment_Gateway' ) ) {

		class Novalnet_Payment_Gateway extends WC_Payment_Gateway {
			var $language;
			var $novalnet_log;
			var $current_payment_id;
			var $payment_details      = array();
			var $invoice_payments     = array( 'novalnet_invoice', 'novalnet_prepayment' );
			var $supports_fraud_check = array( 'novalnet_cc', 'novalnet_sepa', 'novalnet_invoice' );
			var $redirect_payments    = array( 'novalnet_eps', 'novalnet_ideal', 'novalnet_paypal', 'novalnet_instantbank' );

			public function __construct() {

				if ( ! isset( $_SESSION ) )
					session_start();

				$this->current_payment_id = $this->id;
				$this->language           = wc_novalnet_shop_language();
				$this->payment_details    = wc_novalnet_payment_details();
				$this->global_settings    = wc_novalnet_global_configurations();
				if ( in_array( $this->current_payment_id, $this->supports_fraud_check ) )
					$this->has_fields = true;
				$this->novalnet_admin_payment_settings();
				$this->init_settings();
				$this->assign_payment_configuration_data();
				if ( ! empty( $this->global_settings['subs_payments'] ) && in_array( $this->current_payment_id , $this->global_settings['subs_payments'] ) ) {
					$this->supports = array( 'products', 'subscriptions', 'subscription_cancellation', 'subscription_suspension', 'subscription_reactivation', 'subscription_date_changes', 'subscription_amount_changes' );
					
					if ( !in_array( $this->current_payment_id, $this->redirect_payments ) )
						$this->supports[] = 'subscription_payment_method_change';
				}

				if ( $this->global_settings['debug_log'] ) {
					$this->novalnet_log = new WC_Logger();
				}
				add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
				add_action( 'woocommerce_thankyou_' . $this->current_payment_id, array(&$this, 'thankyou_page'));

				add_action( 'woocommerce_email_after_order_table', array( $this, 'novalnet_email_instructions' ), 15, 2 );
				add_action( 'woocommerce_receipt_' . $this->current_payment_id, array( &$this, 'receipt_page' ) );
				add_action( 'novalnet_api_gateway_process', array( $this, 'check_novalnet_payment_response' ) );

				wc_novalnet_clear_session_fragments();
			}

			/**
			 * Gateway configurations in shop backend
			 *
			 * @access public
			 * @param none
			 * @return void
			 */
			public function novalnet_admin_payment_settings() {

				$this->form_fields = array(
					'enabled'       => array(
						'title'     => __( 'Enable payment method', 'wc-novalnet' ),
						'type'      => 'checkbox',
						'label'     => ' ',
						'default'   => ''
					),
					'title_en'      => array(
						'title'     => __( 'Payment title in English', 'wc-novalnet' ),
						'type'      => 'text',
						'description'=> '',
						'default'   => $this->payment_details[ $this->current_payment_id ]['payment_name_en']
					),
					'description_en'    => array(
						'title'     => __( 'Description in English', 'wc-novalnet' ),
						'type'      => 'textarea',
						'description'=> '',
						'default'   => $this->payment_details[ $this->current_payment_id ]['description_en']
					),
					'title_de'      => array(
						'title'     => __( 'Payment title in German', 'wc-novalnet' ),
						'type'      => 'text',
						'description'=> '',
						'default'   => $this->payment_details[ $this->current_payment_id ]['payment_name_de']
					),
					'description_de'    => array(
						'title'     => __( 'Description in German', 'wc-novalnet' ),
						'type'      => 'textarea',
						'description'=> '',
						'default'   => $this->payment_details[ $this->current_payment_id ]['description_de']
					),
					'test_mode'     => array(
						'title'     => __( 'Enable test mode', 'wc-novalnet' ),
						'type'      => 'select',
						'options'   => array( '0' => __( 'No', 'wc-novalnet' ), '1' => __( 'Yes', 'wc-novalnet' ) ),
						'description'=> __('The payment will be processed in the test mode therefore amount for this transaction will not be charged', 'wc-novalnet'),
						'default'   => '0'
					)
				);

				if ( $this->current_payment_id == 'novalnet_cc' ) {

					$this->form_fields['cc_secure_enabled'] = array(
						'title'     => __( 'Enable 3D secure', 'wc-novalnet' ),
						'type'      => 'select',
						'options'   => array( '0' => __( 'No', 'wc-novalnet' ), '1' => __( 'Yes', 'wc-novalnet' ) ),
						'description'=> __('The 3D-Secure will be activated for credit cards. The issuing bank prompts the buyer for a password what, in turn, help to prevent a fraudulent payment. It can be used by the issuing bank as evidence that the buyer is indeed their card holder. This is intended to help decrease a risk of charge-back.', 'wc-novalnet')
					);
					$this->form_fields['enable_amex_type'] = array(
						'title'     => __( 'Enable AMEX', 'wc-novalnet' ),
						'type'      => 'select',
						'options'   => array( '0' => __( 'No', 'wc-novalnet' ), '1' => __( 'Yes', 'wc-novalnet' ) ),
						'description'=> ''
					);
					$this->form_fields['exp_year_limit'] = array(
						'title'     => __( 'Limit for expiry year', 'wc-novalnet' ),
						'type'      => 'text',
						'default'   => '25',
						'description' => __('Enter the number for the maximum limit of credit card expiry year. In case if the field is empty, limit of 25 years from the current year will be set by default', 'wc-novalnet')
					);

				} else if ( $this->current_payment_id == 'novalnet_invoice' ) {
					$this->form_fields['payment_duration'] = array(
						'title'     => __( 'Payment due date (in days)', 'wc-novalnet' ),
						'type'      => 'text',
						'default'   => '',
						'description' => __('Enter the number of days to transfer the payment amount to Novalnet (must be greater than 7 days). In case if the field is empty, 14 days will be set as due date by default', 'wc-novalnet')
					);
				} else if ( $this->current_payment_id == 'novalnet_sepa' ) {
					$this->form_fields['sepa_payment_duration'] = array(
						'title'     => __( 'SEPA payment duration (in days)', 'wc-novalnet' ),
						'type'      => 'text',
						'default'   => '',
						'description' => __('Enter the number of days after which the payment should be processed (must be greater than 6 days)', 'wc-novalnet')
					);
					$this->form_fields['last_succ_refill'] = array(
						'title'     => __( 'Enable auto-fill for payment data', 'wc-novalnet' ),
						'type'      => 'select',
						'options'   => array( '0' => __( 'No', 'wc-novalnet' ), '1' => __( 'Yes', 'wc-novalnet' ) ),
						'description'=> __('For the registered users SEPA direct debit details will be filled automatically in the payment form', 'wc-novalnet')
					);
				}
				if ( in_array( $this->current_payment_id , $this->supports_fraud_check ) ) {
					$this->form_fields['pin_by_callback']   = array(
						'title'     => __( 'Enable fraud prevention', 'wc-novalnet' ),
						'type'      => 'select',
						'options'   => array( '' => __( 'None', 'wc-novalnet' ), 'tel' => __( 'PIN by callback', 'wc-novalnet' ), 'mobile' => __( 'PIN by SMS', 'wc-novalnet' ), 'email' => __( 'Reply via E-mail', 'wc-novalnet' ) ),
						'description'=> __('To authenticate the buyer for a transaction, the PIN or E-Mail will be automatically generated and sent to the buyer. This service is only available for customers from DE, AT, CH','wc-novalnet'),
						'default'   => '0'
					);
					$this->form_fields['pin_amt_limit'] = array(
						'title'      => __( 'Minimum value of goods for the fraud module (in cents)', 'wc-novalnet' ),
						'type'       => 'text',
						'description'    => __('Enter the minimum value of goods from which the fraud module should be activated', 'wc-novalnet')
					);
				}

				$this->form_fields['payment_instruction'] = array(
					'title'       => __( 'Notification for the buyer', 'wc-novalnet' ),
					'type'        => 'textarea',
					'description' => __('The entered text will be displayed on the checkout page', 'wc-novalnet')
				);
				$this->form_fields['instructions']  = array(
					'title'     => __( 'Thank you page instructions', 'wc-novalnet' ),
					'type'      => 'textarea',
					'default'   => ''
				);
				$this->form_fields['email_notes']   = array(
					'title'     => __( 'E-mail instructions', 'wc-novalnet' ),
					'type'      => 'textarea',
				);
				$this->form_fields['payment_logo']  = array(
					'title'     => __( 'Display payment method logo', 'wc-novalnet' ),
					'type'      => 'select',
					'options'   => array( '0' => __( 'No', 'wc-novalnet' ), '1' => __( 'Yes', 'wc-novalnet' ) ),
					'description'=> __( 'The payment method logo will be displayed on the checkout page', 'wc-novalnet' ),
					'default'   => '1'
				);
				$this->form_fields['novalnet_logo'] = array(
					'title'     => __( 'Display Novalnet logo', 'wc-novalnet' ),
					'type'      => 'select',
					'options'   => array( '0' => __( 'No', 'wc-novalnet' ), '1' => __( 'Yes', 'wc-novalnet' ) ),
					'description' => __( 'The Novalnet logo will be displayed on the checkout page', 'wc-novalnet' ),
					'default'   => '1'
				);

				if ( $this->current_payment_id == 'novalnet_paypal' ) {
					$this->form_fields['pending_status'] = array(
						'title'     => __( 'Order status for the pending payment', 'wc-novalnet' ),
						'type'      => 'select',
						'options'   => wc_novalnet_get_woocommerce_order_status(),
						'description'=> ''
					);
				}
				$this->form_fields['order_success_status'] = array(
					'title'     => __( 'Order completion status ', 'wc-novalnet' ),
					'type'      => 'select',
					'options'   => wc_novalnet_get_woocommerce_order_status(),
					'description'=> ''
				);
				if ( in_array( $this->current_payment_id, $this->invoice_payments ) ) {
					$this->form_fields['callback_status'] = array(
						'title'     => __( 'Callback order status', 'wc-novalnet' ),
						'type'      => 'select',
						'options'   => wc_novalnet_get_woocommerce_order_status(),
						'description'=> ''
					);
				}
				$this->form_fields['reference1']    = array(
					'title'     => __( 'Transaction reference 1', 'wc-novalnet' ),
					'type'      => 'text',
					'default'   => '',
					'description'=> __( 'This reference will appear in your bank account statement', 'wc-novalnet' )
				);
				$this->form_fields['reference2']    = array(
					'title'     => __( 'Transaction reference 2', 'wc-novalnet' ),
					'type'      => 'text',
					'default'   => '',
					'description'=> __( 'This reference will appear in your bank account statement', 'wc-novalnet' )
				);
			}

			/**
			 * Assign the Configuration data's to its member functions
			 *
			 * @access public
			 * @param none
			 * @return void
			 */
			public function assign_payment_configuration_data() {

				$this->settings = array_map( "trim", $this->settings );

				$this->method_title  =  'Novalnet ' . $this->payment_details[ $this->current_payment_id ]['payment_name_'. $this->language ] ;

				$this->title 		= $this->settings['title_' . $this->language];
				$this->description 	= $this->settings['description_' . $this->language];

				if ( $this->current_payment_id == 'novalnet_cc' && $this->settings['cc_secure_enabled'] ) {
					array_push( $this->redirect_payments, $this->current_payment_id );
					$this->payment_details[ $this->current_payment_id ]['paygate_url'] =  ( is_ssl() ? 'https://' : 'http://' ) . 'payport.novalnet.de/global_pci_payport';
				}
			}

			/**
			 * Returns the gateway title
			 *
			 * @access public
			 * @param none
			 * @return string
			 */
			public function get_title() {
				$novalnet_logo_html = '';
				if ( $this->settings['novalnet_logo'] && isset( $_SERVER['HTTP_REFERER'] ) && ! strstr( $_SERVER['HTTP_REFERER'] , 'wp-admin' ) && ( ( isset( $_REQUEST['action'] ) && $_REQUEST['action'] == 'woocommerce_update_order_review' && isset( $_SERVER['HTTP_X_REQUESTED_WITH'] ) && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest' ) || ( ! isset( $_REQUEST['woocommerce_pay'] ) && isset( $_REQUEST['pay_for_order'] ) && isset( $_REQUEST['order_id'] ) && ! isset( $_REQUEST['change_payment_method'] ) ) ) ) {
					$novalnet_logo_html = '<img width="90px" src="' . NN_Fns()->nn_plugin_url() .'/assets/images/novalnet_logo.png" alt="Novalnet AG" title="Novalnet AG" />';
				}
				return apply_filters( 'woocommerce_gateway_title', $novalnet_logo_html . $this->title , $this->id );
			}

			/**
			 * Returns the gateway icon.
			 *
			 * @access public
			 * @param none
			 * @return string
			 */
			public function get_icon() {

				$icon = ( $this->current_payment_id == 'novalnet_cc' && $this->settings['enable_amex_type'] ) ? NN_Fns()->nn_plugin_url() . '/assets/images/novalnet_cc_amex.png' : $this->payment_details[ $this->current_payment_id ]['payment_logo'];

				$icon_html = ( $this->settings['payment_logo'] ) ? '<img src="' . $icon . '" alt="' . $this->title  . '" title="' . $this->title . '" />' : '';
				return apply_filters('woocommerce_gateway_icon', $icon_html, $this->id);
			}

			/**
			 * Set this as the current gateway.
			 *
			 * @access public
			 * @param none
			 * @return void
			 */
			public function set_current() {
				$this->chosen = true;
			}

			/**
			 * Displays the payment form, payment description on checkout
			 *
			 * @access public
			 * @param none
			 * @return void
			 */
			public function payment_fields() {

				if ( $this->description )
					echo wpautop( $this->description );

				if ( $this->settings['test_mode'] )
					echo wpautop( '<strong><font color="red">' . __('Please note: The payment will be processed in the test mode therefore amount for this transaction will not be charged', 'wc-novalnet') . '</font></strong>' );

				if ( $this->settings['payment_instruction'] )
					echo wpautop( $this->settings['payment_instruction'] );
					
				if ( WC()->session->chosen_payment_method != $this->current_payment_id &&  isset( $_SESSION['novalnet'][ $this->current_payment_id ] ) ) {
					$_SESSION['novalnet'][ $this->current_payment_id ] = false;
				}

				if ( in_array( $this->current_payment_id , $this->supports_fraud_check ) ) {
					$aff_details = wc_process_affiliate_action();
					$vendor_id = $this->global_settings['vendor_id'];
					$vendor_authcode = $this->global_settings['auth_code'];

					if ( ! empty( $aff_details ) ) {
						$vendor_id = $aff_details['aff_id'];
						$vendor_authcode = $aff_details['aff_authcode'];
					}

					echo '<link rel="stylesheet" type="text/css" media="all" href="' . NN_Fns()->nn_plugin_url() . '/assets/css/novalnet_checkout.css">';
					echo '<script src="' . NN_Fns()->nn_plugin_url() . '/assets/js/novalnet_checkout.js" type="text/javascript"></script>';

					echo '<input type="hidden" id="nn_vendor"  value="' . $vendor_id . '" /><input type="hidden" id="nn_auth_code" value="' . $vendor_authcode . '" /><input type="hidden" id="shop_version" value="' . WOOCOMMERCE_VERSION . '" />';
					
					if ( isset( $_REQUEST['pay_for_order'] ) && $_REQUEST['pay_for_order'] )
						echo '<input type="hidden" id="novalnet_customer_info" value="' . wc_novalnet_get_existing_customer_data() . '" />';

					if ( $this->current_payment_id == 'novalnet_cc' ) {
						echo '<script src="' . NN_Fns()->nn_plugin_url() . '/assets/js/novalnet_cc.js" type="text/javascript"></script>';
						include( dirname( __FILE__ ) .'/templates/checkout/render-cc-payment-form.php');
					} else if ( $this->current_payment_id == 'novalnet_sepa' ) {
						echo '<script src="' . NN_Fns()->nn_plugin_url() . '/assets/js/novalnet_sepa.js" type="text/javascript"></script>';
						include( dirname( __FILE__ ) .'/templates/checkout/render-sepa-payment-form.php');
					} else if ( $this->current_payment_id == 'novalnet_invoice' ) {
						include( dirname( __FILE__ ) .'/templates/checkout/render-invoice-form.php');
					}
				}
			}

			/**
			 * Validate payment fields on the frontend.
			 *
			 * @access public
			 * @param none
			 * @return
			 */
			public function validate_fields() {
				$error = '';
				if ( ! function_exists('curl_init') ) {
					$error = __('You need to activate the CURL function on your server, please check with your hosting provider.', 'wc-novalnet');
				}

				if ( wc_novalnet_global_validation( $this->global_settings ) ) {
					$error = __('Please fill in all the mandatory fields', 'wc-novalnet');
				}

				if ( isset( $_SESSION['novalnet'][ $this->current_payment_id ]['tid'] ) ) {
					if ( ! empty( $this->settings['pin_by_callback'] ) && wc_novalnet_fraud_prevention_option( $this->settings ) ) {
						if ( ! isset( $_POST[ $this->current_payment_id . '_new_pin'] ) && $this->settings['pin_by_callback'] != 'email' ) {
							$pin = trim( $_POST[ $this->current_payment_id . '_pin'] );
							$error = ( empty( $pin ) ? __('Enter your PIN', 'wc-novalnet') : ( ! wc_novalnet_alphanumeric_check( $pin ) ? __('The PIN you entered is incorrect', 'wc-novalnet') : '' ) );
							if ( $error != '' ) {
								if ( $this->global_settings['debug_log'] ) {
									$this->novalnet_log->add('novalnetpayments', 'Fraud check validation error for payment :' . $this->current_payment_id . '. Error : ' . $error);
								}
								wc_novalnet_display_info( $error );
								return wc_novalnet_redirect();
							}
						}
					}
				} else {
					if ( $this->current_payment_id == 'novalnet_cc' ) {
						if ( ( empty( $_POST['novalnet_cc_cvc'] ) || empty( $_POST['nn_cc_hash'] ) || empty( $_POST['nn_cc_uniqueid']  ) ) ) {
							$error = __('Your credit card details are invalid', 'wc-novalnet');
						}
					} else if ( $this->current_payment_id == 'novalnet_sepa' ) {

						if ( empty( $_POST['novalnet_sepa_account_holder'] ) ||  empty( $_POST['nn_sepa_hash'] ) || empty( $_POST['nn_sepa_uniqueid'] ) ) {
							$error =  __( 'Your account details are invalid', 'wc-novalnet' );
						}


						if ( $this->settings['sepa_payment_duration'] != '' && wc_novalnet_digits_check( $this->settings['sepa_payment_duration'] )  && $this->settings['sepa_payment_duration'] < 7  ) {
							$error =  __( 'SEPA Due date is not valid', 'wc-novalnet' );
						}
					}

					if ( ! empty( $this->settings['pin_by_callback'] ) &&  isset(  $_POST[ $this->current_payment_id . '_pin_by_' . $this->settings['pin_by_callback'] ] ) && wc_novalnet_fraud_prevention_option( $this->settings ) ) {

						$_pin_by_callback =  trim( $_POST[ $this->current_payment_id . '_pin_by_' . $this->settings['pin_by_callback'] ] );

						$condition_error = ( $this->settings['pin_by_callback'] == 'email') ? !is_email( $_pin_by_callback ) : !wc_novalnet_digits_check( $_pin_by_callback );
						if ( $condition_error ) {
						   $error = ( $this->settings['pin_by_callback'] == 'email' ) ? __('Your E-mail address is invalid', 'wc-novalnet') : sprintf( __('Please enter your %s', 'wc-novalnet'), ( ( $this->settings['pin_by_callback'] == 'mobile' ) ? __('mobile number','wc-novalnet') : __('telephone number', 'wc-novalnet') ) ) ;
						}
					}

					if ( $error ) {
						if ( $this->global_settings['debug_log'] ) {
							$this->novalnet_log->add('novalnetpayments', 'Validation error for payment :' . $this->current_payment_id . '. Error : ' . $error);
						}
						wc_novalnet_display_info( __( $error ,'wc-novalnet') );
						return wc_novalnet_redirect();
					} else {
						if ( $this->current_payment_id == 'novalnet_cc' ) {

							$_SESSION['novalnet'][ $this->current_payment_id ]['pseudo_hash'] = ( $this->global_settings['auto_refill'] ) ? $_POST['nn_cc_hash'] : '';
							$this->card_details = array(
								'cvc'       => $_POST['novalnet_cc_cvc'],
								'panhash'   => $_POST['nn_cc_hash'],
								'uniqid'    => $_POST['nn_cc_uniqueid'],
							);
							if ( $this->settings['cc_secure_enabled'] ) {
								WC()->session->set( $this->current_payment_id, $this->card_details);
							}
						} else if ( $this->current_payment_id == 'novalnet_sepa' ) {
							$auto_refill_enabled = ( $this->global_settings['auto_refill'] || $this->settings['last_succ_refill'] );

							$_SESSION['novalnet'][ $this->current_payment_id ]['pseudo_hash'] = ( $auto_refill_enabled ) ? $_POST['nn_sepa_hash'] : '';

							$this->sepa_details = array(
								'sepa_holder' => $_POST['novalnet_sepa_account_holder'],
								'sepa_panhash'=> $_POST['nn_sepa_hash'],
								'sepa_uniqid' => $_POST['nn_sepa_uniqueid']
							);
						}

						if ( $this->global_settings['debug_log'] ) {
							$this->novalnet_log->add('novalnetpayments', 'Basic validation Passed for payment : ' . $this->current_payment_id );
						}
					}

				}
				return true;
			}

			/**
			 * Process the payment and return the result
			 *
			 * @param int $order_id
			 * @return array
			 */
			public function process_payment( $order_id ) {

				$wc_order = new WC_Order( $order_id );

				if ( $this->global_settings['debug_log'] ) {
					$this->novalnet_log->add('novalnetpayments', 'Order generated for the payment : ' . $this->current_payment_id . '. Order no : ' . $order_id );
				}

				if ( in_array( $this->current_payment_id, $this->redirect_payments ) ) {
					return wc_novalnet_redirect( $wc_order->get_checkout_payment_url( true ) );
				} else {
					if ( in_array( $this->current_payment_id, $this->supports_fraud_check) && isset( $_SESSION['novalnet'][ $this->current_payment_id ]['tid'] ) ) {
						return $this->perform_fraudcheck_pinstatus_call( $order_id );
					} else {
						$this->generate_payment_parameters( $order_id );
						$ary_response = wc_novalnet_submit_request( $this->payment_parameters );
						if ( in_array( $this->current_payment_id, $this->supports_fraud_check ) && wc_novalnet_fraud_prevention_option( $this->settings ) ) {
							return $this->validate_novalnet_fraudcheck_status( $ary_response, $order_id );
						} else {
							return $this->validate_novalnet_status( $ary_response, $order_id );
						}
					}
				}
			}

			/**
			 * Forms the Novalnet payment parameters
			 *
			 * @access public
			 * @param integer $order_id
			 * @return void
			 */
			public function generate_payment_parameters( $order_id ) {
				global $wpdb;
				$wc_order = new WC_Order( $order_id );
				$is_redirect_method = 0;

				list( $this->first_name, $this->last_name, $this->email, $this->address ) = $this->check_customer_details( $wc_order );

				if ( $this->global_settings['debug_log'] ) {
					$this->novalnet_log->add('novalnetpayments', 'Validation passed for the payment : ' . $this->current_payment_id . ' ' . $order_id );
				}
				$this->currency_format( $wc_order->order_total );

				$this->payment_parameters = array (
					'vendor'        => $this->global_settings['vendor_id'],
					'auth_code'     => $this->global_settings['auth_code'],
					'product'       => $this->global_settings['product_id'],
					'tariff'        => wc_assign_payment_tariff( $wc_order ),
					'key'           => $this->payment_details[ $this->current_payment_id ]['payment_key'],
					'payment_type'  => $this->payment_details[ $this->current_payment_id ]['payment_type'],
					'test_mode'     => $this->settings['test_mode'],
					'currency'      => get_woocommerce_currency(),
					'first_name'    => $this->first_name,
					'last_name'     => $this->last_name,
					'gender'        => 'u',
					'email'         => $this->email,
					'street'        => $this->address,
					'search_in_street'=> 1,
					'city'          => $wc_order->billing_city,
					'zip'           => $wc_order->billing_postcode,
					'lang'          => strtoupper( $this->language ),
					'language'      => $this->language,
					'country'       => $wc_order->billing_country,
					'country_code'  => $wc_order->billing_country,
					'tel'           => $wc_order->billing_phone,
					'remote_ip'     => wc_novalnet_server_addr(),
					'customer_no'   => $wc_order->user_id > 0 ? $wc_order->user_id : 'guest',
					'use_utf8'      => 1,
					'amount'        => isset( $_REQUEST['change_payment_method'] ) ? 0 : $this->amount,
					'order_no'      => ltrim($wc_order->get_order_number(), _x( '#', 'hash before order number', 'woocommerce' )),
					'system_name'   => 'wordpress-woocommerce',
					'system_version'=> get_bloginfo('version'). '-'.WOOCOMMERCE_VERSION . '-NN'. NN_VERSION,
					'system_url'    => site_url(),
					'system_ip'     => wc_novalnet_server_addr('SERVER_ADDR')
				);

				$aff_details = wc_process_affiliate_action();

				if ( !empty( $aff_details ) ) {
					$this->payment_parameters['vendor'] = $aff_details['aff_id'];
					$this->payment_parameters['auth_code'] = $aff_details['aff_authcode'];
				}

				if ( wc_novalnet_digits_check( $this->global_settings['referrer_id'] ) ) {
					$this->payment_parameters['referrer_id'] = $this->global_settings['referrer_id'];
				}

				if ( in_array( $this->current_payment_id, $this->supports_fraud_check ) && wc_novalnet_manual_limit_check( $this->amount ) ) {
					$this->payment_parameters['on_hold'] = 1;
				}

				if ( $this->current_payment_id == 'novalnet_cc' ) {

					$this->payment_parameters['cc_holder'] = $this->payment_parameters['cc_no'] = $this->payment_parameters['cc_exp_month'] = $this->payment_parameters['cc_exp_year'] = '';
					$this->payment_parameters['cc_cvc2']   = $this->card_details['cvc'];
					$this->payment_parameters['pan_hash']  = $this->card_details['panhash'];
					$this->payment_parameters['unique_id'] = $this->card_details['uniqid'];

					if ( $this->settings['cc_secure_enabled'] ) {
						$this->payment_parameters['encoded_amount'] = $this->amount;
						wc_novalnet_encode_data(  $this->payment_parameters, array( 'encoded_amount' ) );
						$is_redirect_method = 1;
					}
				} else if ( $this->current_payment_id == 'novalnet_sepa' ) {

					$this->payment_parameters['bank_account'] = $this->payment_parameters['bank_code'] = $this->payment_parameters['bic'] = $this->payment_parameters['iban'] = '';
					$this->payment_parameters['iban_bic_confirmed'] = 1;
					$this->payment_parameters['bank_account_holder'] = trim( $this->sepa_details['sepa_holder'] );
					$this->payment_parameters['sepa_hash'] = $this->sepa_details['sepa_panhash'];
					$this->payment_parameters['sepa_unique_id'] = $this->sepa_details['sepa_uniqid'];
					$sepa_due_date_limit = ( ( $this->settings['sepa_payment_duration'] != '' && $this->settings['sepa_payment_duration'] >= 7 ) ? $this->settings['sepa_payment_duration'] : 7 );
					$this->payment_parameters['sepa_due_date'] = (date('Y-m-d', strtotime('+'.$sepa_due_date_limit.' days')));

				} else if (in_array( $this->current_payment_id, $this->invoice_payments)) {

					if ( $this->current_payment_id ==  'novalnet_invoice' && wc_novalnet_digits_check( $this->settings['payment_duration'] ) ) {
						$this->payment_parameters['due_date'] = date('Y-m-d', mktime(0, 0, 0, date('m'), (date('d') + $this->settings['payment_duration'] ), date('Y')));
					}

					$this->payment_parameters['invoice_type'] = $this->payment_details[ $this->current_payment_id ]['payment_type'];
					$this->payment_parameters['invoice_ref'] = 'BNR-' . $this->payment_parameters['product'] . '-' . ltrim($wc_order->get_order_number(), _x( '#', 'hash before order number', 'woocommerce' )) ;

				} else if (in_array( $this->current_payment_id, $this->redirect_payments)) {

					$this->payment_parameters['user_variable_0']= site_url();
					$this->payment_parameters['uniqid']         = uniqid();
					wc_novalnet_encode_data($this->payment_parameters, array('auth_code', 'product', 'tariff', 'amount', 'test_mode', 'uniqid'));
					$this->payment_parameters['hash'] = wc_novalnet_generate_hash( $this->payment_parameters );
					$this->payment_parameters['implementation'] = 'PHP';
					$is_redirect_method = 1;

				}

				if ( $is_redirect_method ) {
					$this->payment_parameters['return_url']      	 = esc_url( $this->get_return_url( $wc_order ) );
					$this->payment_parameters['error_return_url']	 = esc_url( $wc_order->get_cancel_order_url() );
					$this->payment_parameters['return_method']   	 = 'POST' ;
					$this->payment_parameters['error_return_method'] = 'POST' ;
					$this->payment_parameters['input2']     		 = 'novalnet_class_name';
					$this->payment_parameters['inputval2']  		 = 'class-wc-gateway-' . str_replace( '_', '-', $this->current_payment_id) . '.php';
				}

				if ( !$is_redirect_method && in_array( $this->current_payment_id, $this->supports_fraud_check ) && wc_novalnet_fraud_prevention_option( $this->settings ) ) {

					$request_param = ( $this->settings['pin_by_callback'] == 'email' )? 'reply_email_check' : ( ( $this->settings['pin_by_callback'] == 'tel') ? 'pin_by_callback' : 'pin_by_sms' );

					$this->payment_parameters[ $request_param ] = '1';
					$this->payment_parameters[ $this->settings['pin_by_callback'] ] = trim( $_POST[ $this->current_payment_id . '_pin_by_' . $this->settings['pin_by_callback'] ]) ;
				}

				if ( class_exists('WC_Subscriptions') && WC_Subscriptions_Order::order_contains_subscription( $wc_order ) ) {
					// For Free trial period
					$free_length = WC_Subscriptions_Order::get_subscription_trial_length( $wc_order );
					$free_period =  substr( WC_Subscriptions_Order::get_subscription_trial_period( $wc_order ), 0, 1 );

					if ( $free_period == 'w' ) {
						$free_period = 'd'; $free_length = $free_length * 7;
					}

					//To calculate recurring total
					$subs_amount = WC_Subscriptions_Order::get_recurring_total( $wc_order, '' );

					// To calculate recurring period
					$interval = WC_Subscriptions_Order::get_subscription_interval( $wc_order );
					$period =  substr( WC_Subscriptions_Order::get_subscription_period( $wc_order ), 0, 1 );

					if ( $period == 'w' ) {
						$period = 'd'; $interval = $interval * 7;
					}

					$this->payment_parameters['tariff_period'] = ( ! empty( $free_length) ) ? $free_length .  $free_period : $interval . $period;
					$this->payment_parameters['tariff_period2'] = $interval . $period;
					$this->payment_parameters['tariff_period2_amount'] = str_replace(',' , '',  sprintf( "%0.2f", $subs_amount ) ) * 100;
				}

				$this->payment_parameters['input1']     = 'nn_shopnr';
				$this->payment_parameters['inputval1']  = $wc_order->id;

				if ( ! empty( $this->settings['reference1'] ) ) {
					$this->payment_parameters['input3']   = 'Reference1';
					$this->payment_parameters['inputval3']= strip_tags( $this->settings['reference1'] );
				}
				if ( ! empty( $this->settings['reference2'] ) ) {
					$this->payment_parameters['input4']   = 'Reference2';
					$this->payment_parameters['inputval4']= strip_tags( $this->settings['reference2'] );
				}
			}

			/**
			 * Segregates the Novalnet response to success and failure
			 *
			 * @access public
			 * @param array $response
			 * @param integer $order_id
			 * @return
			 */
			public function validate_novalnet_status( $response, $order_id ) {
				global $wpdb;
				$message = wc_novalnet_response_text( $response );
				$check_status = true;

				if ( $this->check_response_status( $response ) ) {
					if ( isset( $_REQUEST['change_payment_method'] ) ) {
						$post_id = wc_novalnet_get_order_no();

						$order_info = $wpdb->get_row( $wpdb->prepare( "SELECT vendor_id, auth_code, product_id, tariff_id, tid FROM {$wpdb->prefix}novalnet_transaction_detail WHERE order_no=%d", $post_id ) );

						$transaction_response =  wc_novalnet_perform_xmlrequest( array(
							'vendor_id'      => $order_info->vendor_id,
							'vendor_authcode'=> $order_info->auth_code,
							'product_id'     => $order_info->product_id,
							'request_type'   => 'TRANSACTION_STATUS',
							'tid'            => $response['tid'],
						) );
						if ( $transaction_response['status'] != 100 ) {

							$capture_request = array(
								'vendor' 	=> $order_info->vendor_id,
								'product' 	=> $order_info->product_id,
								'tariff' 	=> $order_info->tariff_id,
								'auth_code' => $order_info->auth_code,
								'key'		=> $this->payment_parameters['key'],
								'tid'		=> $response['tid'],
								'status'	=> 100,
								'edit_status' => 1
							);
							if ( wc_novalnet_is_basic_validation( $capture_request ) ) {
								$capture_response = wc_novalnet_submit_request( $capture_request );
								if ( 100 == $capture_response['status'] ) {

									$subscription_update_request = wc_novalnet_perform_xmlrequest( array(
											'vendor_id'      => $order_info->vendor_id,
											'vendor_authcode'=> $order_info->auth_code,
											'product_id'     => $order_info->product_id,
											'request_type'   => 'SUBSCRIPTION_UPDATE',
											'tid'            => $response['tid'],
											'subs_tid'       => $response['tid'],
											'payment_ref'    => $order_info->tid,
										)
									);

									$check_status = ( isset( $subscription_update_request['status'] ) && 100 == $subscription_update_request['status'] ) ? true : false;
								} else $check_status = false;
							}
						}
					}
				} else $check_status = false;
				return  ( ( $check_status ) ? $this->transaction_success( $response, $message, $order_id ) : $this->transaction_failure( $response, $message, $order_id ) );
			}

			/**
			 * Validates the First call response for fraud prevention check
			 *
			 * @access public
			 * @param array $response
			 * @return array
			 */
			public function validate_novalnet_fraudcheck_status( $response, $order_id ) {
				$wc_order = new WC_Order( $order_id );
				if ( 100 == $response['status'] ) {

					if ( $this->global_settings['debug_log'] ) {
						$this->novalnet_log->add('novalnetpayments', 'Fraud check has been succeeded for the order : ' . $order_id );
					}
					if ( isset( $_SESSION['novalnet'][ $this->current_payment_id ]['invalid_pin']) && isset( $_SESSION['novalnet'][  $this->current_payment_id ]['errno'] ) ) {
						unset( $_SESSION['novalnet'][ $this->current_payment_id ]['invalid_pin'] , $_SESSION['novalnet'][ $this->current_payment_id ]['errno'] );
					}

					$session_details = array(
						'tid'       => $response['tid'],
						'amount'    => $this->amount,
						'time_limit'=> time()+(30*60),
						'test_mode' => $response['test_mode'],
						'inputval1' => $response['inputval1'],
						'order_no'  => $response['order_no']
					);
					if ( $_SESSION['novalnet'][ $this->current_payment_id ] != '' )
						$_SESSION['novalnet'][ $this->current_payment_id ] = array_merge( $_SESSION['novalnet'][ $this->current_payment_id ], $session_details );
					else
						$_SESSION['novalnet'][ $this->current_payment_id ]  = $session_details;

					if ( $this->current_payment_id == 'novalnet_invoice' ) {
						$inv_array = array( 'due_date', 'invoice_iban','invoice_bic','invoice_bankname','invoice_bankplace' );

						foreach ( $inv_array as $value ) {
							$_SESSION['novalnet'][ $this->current_payment_id ][ $value ] = $response[ $value ];
						}

						$_SESSION['novalnet'][ $this->current_payment_id ]['invoice_ref'] = $this->payment_parameters['invoice_ref'];
					}

					$msg =  ( $this->settings['pin_by_callback'] == 'email') ?  __('You will shortly receive an information e-mail, please send the empty reply incl. the original e-mail', 'wc-novalnet') : ( ($this->settings['pin_by_callback'] == 'mobile') ? __('You will shortly receive an SMS containing your transaction PIN to complete the payment', 'wc-novalnet'): __('You will shortly receive a transaction PIN through phone call to complete the payment', 'wc-novalnet'));

					wc_novalnet_display_info( $msg, 'message');
					return wc_novalnet_redirect();
				}else{
					if ( $this->global_settings['debug_log'] ) {
						$this->novalnet_log->add('novalnetpayments', 'Fraud check has been failed for the order : ' . $order_id );
					}
					return $this->transaction_failure( $response, wc_novalnet_response_text( $response ), $order_id );
				}
			}

			/**
			 * Perform the request for PIN Submission / New PIN Generation and Email reply check on Novalnet Server.
			 *
			 * @access public
			 * @param integer $order_id
			 * @return void
			 */
			public function perform_fraudcheck_pinstatus_call( $order_id ) {

				$order = new WC_Order( $order_id);

				$request_type = ( isset( $_POST[ $this->current_payment_id . '_new_pin'] ) ) ? 'TRANSMIT_PIN_AGAIN' : ( $this->settings['pin_by_callback'] == 'email' ? 'REPLY_EMAIL_STATUS' : 'PIN_STATUS' );

				if ( $_SESSION['novalnet'][ $this->current_payment_id ]['amount'] != WC()->session->total*100 ) {
					unset( $_SESSION['novalnet'][ $this->current_payment_id ]);
					$error = __('The order amount has been changed, please proceed with the new order', 'wc-novalnet');
					if ( $this->global_settings['debug_log'] ) {
						$this->novalnet_log->add('novalnetpayments', 'Fraud check validation error for payment : ' . $this->current_payment_id . 'with reason : ' . $error );
					}
					wc_novalnet_display_info( $error );
					return wc_novalnet_redirect();
				}

				if ( $this->global_settings['debug_log'] ) {
					$this->novalnet_log->add('novalnetpayments', 'Fraud check second call is being processed  for the order : ' . $order->id );
				}

				$pin_second_call = array(
					'vendor_id'      => $this->global_settings['vendor_id'],
					'vendor_authcode'=> $this->global_settings['auth_code'],
					'product_id'     => $this->global_settings['product_id'],
					'request_type'   => $request_type,
					'tid'            => $_SESSION['novalnet'][ $this->current_payment_id ]['tid'],
					'lang'           => strtoupper( $this->language ),
				);

				$aff_details = wc_process_affiliate_action();

				if ( !empty( $aff_details ) ) {
					$pin_second_call['vendor_id'] = $aff_details['aff_id'];
					$pin_second_call['vendor_authcode'] = $aff_details['aff_authcode'];
				}

				if ( $request_type == 'PIN_STATUS' )
					$pin_second_call['pin'] = trim( $_POST[ $this->current_payment_id . '_pin'] );

				$data = wc_novalnet_perform_xmlrequest( $pin_second_call );

				if ( isset( $data['status'] ) && $data['status'] == '100') {
					if ( $this->global_settings['debug_log'] ) {
						$this->novalnet_log->add('novalnetpayments', 'Fraud check second call succeeded for the order : ' . $order->id );
					}
					$data = array_merge( $data,  $_SESSION['novalnet'][ $this->current_payment_id ] );
					return( $this->validate_novalnet_status( $data, $order_id ) );
				} else {
					if ( isset( $data['status'] ) && $data['status'] == '0529006' ) {
						$_SESSION[  $this->current_payment_id ]['invalid_count'] = true;
						$_SESSION[  $this->current_payment_id ]['time_limit'] = time()+(30*60);
					}
					$type = $data['status'] == '0529009' ? 'message' : 'error';
					$response_text = wc_novalnet_response_text( $data );
					if ( $this->global_settings['debug_log'] ) {
						$this->novalnet_log->add('novalnetpayments', 'Fraud check second call has been failed due to : ' . $response_text );
					}
					wc_novalnet_display_info( $response_text, $type );
					return wc_novalnet_redirect();
				}
			}

			/**
			 * checks the novalnet status response
			 *
			 * @access public
			 * @param array $response
			 * @return boolean
			 */
			public function check_response_status( $response ) {
				if ( 100 == $response['status'] || ( $this->current_payment_id == 'novalnet_paypal' && 90 == $response['status'] ) ) {
					return true;
				}
				else {
					return false;
				}
			}

			/**
			 * Formats amount
			 *
			 * @access public
			 * @param double $amount
			 * @return void
			 */
			public function currency_format($amount) {
				$this->amount = str_replace(',', '', sprintf( "%0.2f", $amount ) ) * 100;
			}

			/**
			 * Forms the first name & last name value if any one not exists
			 *
			 * @access public
			 * @param object $order
			 * @return array
			 */
			private function _form_username_param( $order ) {

				$billing_first_name = trim( $order->billing_first_name );
				$billing_last_name = trim( $order->billing_last_name );

				/* Get customer first and last name */
				if ( empty( $billing_first_name ) || empty( $billing_last_name ) ) {
					$full_name = $billing_first_name . $billing_last_name;
					return preg_match( '/\s/', $full_name ) ? explode( " ", $full_name, 2 ) : array($full_name, $full_name );
				}
				return array( $billing_first_name, $billing_last_name );
			}

			/**
			 * Validates the customer details
			 *
			 * @access public
			 * @param object $order
			 * @return array
			 */
			public function check_customer_details( $order ) {
				list( $fname, $lname ) = $this->_form_username_param( $order );
				if ( $fname == '' || $lname == '' || !is_email( $order->billing_email ) ) {
					$error = __('Customer name/email fields are not valid', 'wc-novalnet');
					if ( $this->global_settings['debug_log'] ) {
						$this->novalnet_log->add('novalnetpayments', 'Validation error for the payment : ' . $this->current_payment_id . '. Error : ' . $error );
					}
					wc_novalnet_display_info( $error );
					return wc_novalnet_redirect();
				}
				$address = '';
				if ( ! empty( $order->billing_address_2 ) )
					$address = ', '. $order->billing_address_2;
				return array( $fname, $lname, $order->billing_email, $order->billing_address_1 . $address );
			}

			/**
			 * Prepare the Novalnet transaction comments from the server response
			 *
			 * @access public
			 * @param array $response
			 * @param boolean $test_mode_value
			 * @param object $order
			 * @return string $novalnet_comments
			 */
			public function prepare_payment_comments( $response, $test_mode_message, $order ) {
				global $wpdb;
				$new_line = "\n";

				$novalnet_comments  = $new_line . __( $this->title, 'wc-novalnet') . $new_line;
				$novalnet_comments .= __('Novalnet transaction ID : ', 'wc-novalnet') . $response['tid'];
				$novalnet_comments .= $test_mode_message ? $new_line . __('Test order', 'wc-novalnet') . $new_line : '' . $new_line;

				if ( ! isset( $_REQUEST['change_payment_method'] ) && in_array( $this->current_payment_id, $this->invoice_payments ) && isset( $response['invoice_iban'] ) ) {
					$invoice_ref = isset( $response['invoice_ref'] ) ?  $response['invoice_ref'] : $this->payment_parameters['invoice_ref'];
					$novalnet_comments .= $new_line . __('Please transfer the amount to the below mentioned account details of our payment processor Novalnet', 'wc-novalnet') . $new_line;
					if ( $response['due_date'] != '' ) {
						$novalnet_comments.= __('Due date : ', 'wc-novalnet') . date_i18n(get_option('date_format'), strtotime( $response['due_date'] ) ) . $new_line;
					}
					$novalnet_comments .= __('Account holder : Novalnet AG', 'wc-novalnet') . $new_line;
					$novalnet_comments .= 'IBAN : ' . $response['invoice_iban'] . $new_line;
					$novalnet_comments .= 'BIC : ' . $response['invoice_bic'] . $new_line;
					$novalnet_comments .= 'Bank : ' . $response['invoice_bankname'] . ' ' . trim( $response['invoice_bankplace']) . $new_line;
					$novalnet_comments .= __('Amount : ', 'wc-novalnet') . strip_tags( $order->get_formatted_order_total()) . $new_line;
					$novalnet_comments .= __('Reference 1 : ', 'wc-novalnet') .$invoice_ref . $new_line ;
					$novalnet_comments .= __('Reference 2 : TID ', 'wc-novalnet') . $response['tid'] . $new_line;
					$novalnet_comments .= __('Reference 3 : Order number ', 'wc-novalnet') . $response['order_no'] . $new_line . $new_line;

					$wpdb->insert( "{$wpdb->prefix}novalnet_invoice_details",
						array(
							'order_no' => $order->id,
							'payment_type' => $this->current_payment_id,
							'amount' => $order->order_total * 100,
							'invoice_due_date' => $response['due_date'],
							'invoice_bank_details' => serialize(array(
								'test_mode'		=> $test_mode_message,
								'bank_name'     => $response['invoice_bankname'],
								'bank_city'     => $response['invoice_bankplace'],
								'bank_iban'     => $response['invoice_iban'],
								'bank_bic'      => $response['invoice_bic'],
								'invoice_ref'   => $invoice_ref,
								'seq_nr'		=> $response['order_no']
							)
						)
					) );
				}

				if ( $this->global_settings['debug_log'] ) {
					$this->novalnet_log->add('novalnetpayments', 'Novalnet Transaction Details for the Order : ' . $order->id . ' Payment comments : ' . $novalnet_comments );
				}
				return $novalnet_comments;
			}

			/**
			 * Handles the transaction success process
			 *
			 * @access public
			 * @param array $response
			 * @param string $message
			 * @param integer $order_id
			 * @return array
			 */
			public function transaction_success( $response, $message, $order_id ) {
				global $wpdb;
				$order_no   = $response['inputval1'];
				$woo_seq_no = $response['order_no'];
				$new_line = "\n";

				$order = new WC_Order( $order_no );
				$test_mode_value = wc_novalnet_check_test_mode_status( $response['test_mode'], $this->settings['test_mode'] );
				$novalnet_comments = $this->prepare_payment_comments( $response, $test_mode_value, $order );

				$post_back_param = array(
					'vendor'    => $this->global_settings['vendor_id'],
					'product'   => $this->global_settings['product_id'],
					'key'       => $this->payment_details[ $this->current_payment_id ]['payment_key'],
					'tariff'    => wc_assign_payment_tariff( $order ),
					'auth_code' => $this->global_settings['auth_code'],
					'status'    => 100,
					'tid'       => $response['tid'],
					'order_no'  => $woo_seq_no,
				);

				$aff_details = wc_process_affiliate_action();

				if ( !empty( $aff_details) ) {
					$post_back_param['vendor'] = $aff_details['aff_id'];
					$post_back_param['auth_code'] = $aff_details['aff_authcode'];
				}

				if ( wc_novalnet_is_basic_validation( $post_back_param ) ) {
					wc_novalnet_submit_request( $post_back_param );
				}
				$transaction_status_request = array(
					'vendor_id'      => $post_back_param['vendor'],
					'vendor_authcode'=> $post_back_param['auth_code'],
					'product_id'     => $post_back_param['product'],
					'request_type'   => 'TRANSACTION_STATUS',
					'tid'            => $response['tid'],
				);
				$gateway_status = wc_novalnet_perform_xmlrequest( $transaction_status_request );

				if ( isset( WC()->session->novalnet_aff_id ) && !empty( WC()->session->novalnet_aff_id ) ) {
					$wpdb->insert(
						"{$wpdb->prefix}novalnet_aff_user_detail",
						array(
							'aff_id' => $aff_details['aff_id'],
							'customer_id' => $order->user_id,
							'aff_shop_id' => $order->id,
							'aff_order_no'=> $woo_seq_no
						)
					);
				}

				$paymentmethod = ( ! isset( $_REQUEST['change_payment_method'] ) ) ? $order->payment_method : $order->recurring_payment_method;

				$wpdb->insert( "{$wpdb->prefix}novalnet_transaction_detail", array(
					'order_no'           => $order->id,
					'vendor_id' 		=> $post_back_param['vendor'],
					'auth_code' 		=> $post_back_param['auth_code'],
					'product_id' 		=> $post_back_param['product'],
					'tariff_id'			=> $post_back_param['tariff'] ,
					'subs_id' 	 		=> $gateway_status['subs_id'],
					'payment_id'        => $this->payment_details[ $paymentmethod ]['payment_key'],
					'payment_type'      => $paymentmethod,
					'tid' 				=> $response['tid'],
					'status' 			=> $response['status'],
					'gateway_status' 	=> $gateway_status['status'],
					'amount'            => $order->order_total * 100,
					'callback_amount'   => ( ! in_array( $paymentmethod, $this->invoice_payments ) ) ? $order->order_total * 100 : 0,
					'currency'          => get_woocommerce_currency(),
					'test_mode'         => $test_mode_value,
					'customer_id'       => $order->user_id,
					'customer_email'    => $order->billing_email,
					'active'    		=> 1,
					'process_key' => ( isset( $_SESSION['novalnet'][ $paymentmethod ]['pseudo_hash'] ) ? $_SESSION['novalnet'][ $paymentmethod ]['pseudo_hash'] : '' ),
					'date'              => date('Y-m-d H:i:s')
				) );

				if ( $paymentmethod == 'novalnet_paypal' && 100 == $gateway_status['status'] ) {
					$wpdb->insert(
						"{$wpdb->prefix}novalnet_callback_history",
						array(
							'payment_type'  => 'PAYPAL',
							'status'        => $response['status'],
							'callback_tid'  => '',
							'org_tid'       => $response['tid'],
							'amount'        => $order->order_total * 100,
							'currency'      => get_woocommerce_currency(),
							'product_id'    => $this->global_settings['product_id'],
							'order_no'      => $order->id,
							'date'          => date('Y-m-d H:i:s')
						)
					);
				}
				if ( ! empty( $gateway_status['subs_id'] ) ) {

					$subscription_length = WC_Subscriptions_Order::get_subscription_length( $order );
					$tmp_length = 0;

					if ( $subscription_length > 0 ){
						$subscription_freetrial_length = WC_Subscriptions_Order::get_subscription_trial_length( $order );
						$tmp_length = ( ! empty( $subscription_freetrial_length ) ? $subscription_length : ($subscription_length - 1 ) );
					}

					$wpdb->insert(
						"{$wpdb->prefix}novalnet_subscription_details",
						array(
							'order_no' 				=> $order->id,
							'payment_type' 			=> $this->current_payment_id,
							'recurring_payment_type'=> $this->current_payment_id,
							'recurring_amount' 		=> ( WC_Subscriptions_Order::get_recurring_total( $order, '' ) * 100 ),
							'recurring_tid'			=> $response['tid'],
							'signup_date' 			=> date('Y-m-d H:i:s'),
							'subs_id'				=> $gateway_status['subs_id'],
							'next_payment_date' 	=> $gateway_status['next_subs_cycle'],
							'subscription_length' 	=> $tmp_length
						)
					);

					WC_Subscriptions_Manager::activate_subscriptions_for_order( $order );
				}  else if ( class_exists('WC_Subscriptions_Order') && WC_Subscriptions_Order::order_contains_subscription( $order ) ) {
					WC_Subscriptions_Manager::cancel_subscriptions_for_order( $order );
					$novalnet_comments .= $new_line. html_entity_decode(__('This is not processed as a subscription order!', 'wc-novalnet' ), ENT_QUOTES, 'UTF-8');
				}
				if ( $order->customer_note ) {
					$order->customer_note .= $new_line;
				}

				$order->customer_note .= html_entity_decode( $novalnet_comments, ENT_QUOTES, 'UTF-8');

				$order->add_order_note( $novalnet_comments );

				if ( $this->global_settings['debug_log'] ) {
					$this->novalnet_log->add('novalnetpayments', 'Transaction has been completed for the order' . $order->id . ' and the Transaction status in Novalnet for the TID ' . $response['tid'] . ' is ' . $gateway_status['status'] );
					$this->novalnet_log->add('novalnetpayments', 'Transaction status for the Order ' . $order->status );

					if ($order_no != $woo_seq_no)
						$this->novalnet_log->add('novalnetpayments', 'Sequential Order number for current Order ' . $woo_seq_nr);
				}

				$nn_order_notes = array(
					'ID'            => $order_no,
					'post_excerpt'  => $order->customer_note
				);
				wp_update_post( $nn_order_notes );

				if ( ! isset( $_REQUEST['change_payment_method'] ) ) {
					$status = ( $gateway_status['status'] == 90 && $order->payment_method == 'novalnet_paypal' ) ? $this->settings['pending_status'] : $this->settings['order_success_status'] ;
					$order->update_status( $status );
				}

				update_post_meta( $order_no, '_nn_version', NN_VERSION);

				wc_novalnet_display_info( $message, 'message' );
				update_post_meta( $this->id, '_paid_date', current_time('mysql') );

				if ( ! empty(  WC()->session->order_awaiting_payment ) ) { unset( WC()->session->order_awaiting_payment ); }
				$order->reduce_order_stock();
				WC()->cart->empty_cart();
				if ( isset( $_SESSION[ $order->payment_method ] ) )
					unset( $_SESSION[ $order->payment_method ] );

				if ( isset( $_SESSION ) && $_SESSION ) {
					$_SESSION['novalnet'] = false;

				if ( isset( WC()->session->novalnet_aff_id ) )
					unset(WC()->session->novalnet_aff_id);
				}

				if ( in_array( $this->current_payment_id, $this->redirect_payments ) ) {
					wp_safe_redirect( $this->get_return_url( $order ) );
					exit();
				} else {
					return wc_novalnet_redirect($this->get_return_url( $order) );
				}
			}

			/**
			 * Handles the transaction failure process
			 *
			 * @access public
			 * @param array $response
			 * @param string $message
			 * @param integer $order_id
			 * @return array
			 */
			public function transaction_failure( $response, $message, $order_id ) {
				global $wpdb;
				$new_line = "\n";

				$order_no = in_array( $this->current_payment_id, $this->redirect_payments ) ? $response['inputval1'] : $order_id ;

				$order = new WC_Order( $order_no );

				if ( ! empty( $response['tid'] ) ) {
					$test_mode_value = wc_novalnet_check_test_mode_status( $response['test_mode'], $this->settings['test_mode'] );

					$novalnet_comments = $this->prepare_payment_comments( $response, $test_mode_value, $order );
					$novalnet_comments .= $message . $new_line;

					if ( $order->customer_note ) {
						$order->customer_note .= $new_line;
					}

					$order->customer_note .= html_entity_decode( $novalnet_comments, ENT_QUOTES, 'UTF-8' );

					$nn_order_notes = array(
						'ID'            => $order_no,
						'post_excerpt'  => $order->customer_note
					);
					wp_update_post( $nn_order_notes );

					$order->add_order_note( $order->customer_note );

				}
				$order->cancel_order( $message );
				do_action( 'woocommerce_cancelled_order', $order_no );
				if ( $this->global_settings['debug_log'] ) {
					$this->novalnet_log->add('novalnetpayments', 'Transaction has been failed for the order : ' . $order->id . ' due to ' . $message );
					$this->novalnet_log->add('novalnetpayments', 'Transaction status for the Order : ' . $order->status );
				}
				wc_novalnet_display_info( html_entity_decode( $message, ENT_QUOTES, 'UTF-8' ) );

				if ( in_array( $this->current_payment_id, $this->redirect_payments ) ) {
					wp_safe_redirect( WC()->cart->get_checkout_url() );
					exit();
				} else {
					return wc_novalnet_redirect();
				}
			}

			/**
			 * Add Novalnet comments to order confirmation mail to customer below order table
			 * calls from hook 'woocommerce_email_after_order_table'
			 *
			 * @access public
			 * @param object $order
			 * @param integer $sent_to_admin
			 * @return void
			 */
			public function novalnet_email_instructions( $order, $sent_to_admin ) {
				if ( $order->payment_method == $this->id && ( !isset( $_SESSION['novalnet_email_notes'] ) || ( ( $sent_to_admin == 0 ) && ( ! isset( $_SESSION['novalnet_email_notes'] ) || $_SESSION['novalnet_email_notes'] != 2 ) ) ) ) {
					if ( $this->settings['email_notes'] )
						echo wpautop( wptexturize( $this->settings['email_notes'] ) );

					$_SESSION['novalnet_email_notes'] =  ( $sent_to_admin ) ? 1 : 2;
				}
				$invoice_comments = get_post_meta( $order->id, '_nn_invoice_comments', true );
				$order->customer_note = ( in_array( $order->payment_method, $this->invoice_payments ) &&  ! empty( $invoice_comments ) ) ? wpautop( $invoice_comments ) : wpautop( $order->customer_note );
			}

			/**
			 * WooCommerce thankyou page
			 * calls from hook "woocommerce_thankyou_{gateway_id}"
			 *
			 * @access public
			 * @param none
			 * @return void
			 */
			public function thankyou_page() {
				if ( ! isset( WC()->session->novalnet_thankyou_page ) && ! empty( $this->settings['instructions'] ) ) {
					echo wpautop( wptexturize( $this->settings['instructions'] ) );
					WC()->session->set('novalnet_thankyou_page', true);
				}
			}

			/**
			 * WooCommerce receipt page
			 * calls from hook "woocommerce_receipt_{gateway_id}"
			 *
			 * @access public
			 * @param integer $order_id
			 * @return void
			 */
			public function receipt_page( $order_id ) {

				if ( ! isset( WC()->session->novalnet_receipt_page ) ) {
					echo '<p>' . __('Thank you for choosing Novalnet payment.', 'wc-novalnet') . '</p>';
					$order = new WC_Order( $order_id );
					if ( $this->current_payment_id == 'novalnet_cc' ) {
						$this->card_details = WC()->session->get( $this->current_payment_id ) ;
					}
					$this->generate_payment_parameters( $order_id );
					$novalnet_args_array = array();
					foreach ($this->payment_parameters as $key => $value) {
						$novalnet_args_array[] = '<input type="hidden" name="' . esc_attr($key) . '" id="' . esc_attr($key) . '" value="' . esc_attr($value) . '" />';
					}

					wc_enqueue_js('
						$.blockUI( {
							message: "' . esc_js( __( 'After the successful verification, you will be redirected to Novalnet secure order page to proceed with the payment', 'wc-novalnet' ) ) . '",
							baseZ: 99999,
							overlayCSS: { background: "#fff", opacity: 0.6 },
							css: { padding: "20px", zindex: "9999999", textAlign: "center", color: "#555", border: "3px solid #aaa", backgroundColor:"#fff", cursor: "wait", lineHeight: "24px", width:"60%", height:"auto"}
						} );
						jQuery("#submit_novalnet_payment_form").click();
					');

					echo '<form id="formnovalnet" action="' . $this->payment_details[ $this->current_payment_id ]['paygate_url'] . '" method="post" target="_top">' . implode('', $novalnet_args_array) . ' <input type="submit" class="button-alt" id="submit_novalnet_payment_form" value="' . __('Pay', 'wc-novalnet') . '" /><a class="button cancel" href="' . esc_url($order->get_cancel_order_url()) . '">' . __('Cancel', 'wc-novalnet') . '</a> </form>';

					 WC()->session->set('novalnet_receipt_page', true);
				}
			}

			/**
			 * Validates the server response for redirect payments
			 *
			 * @access public
			 * @param array $api_response
			 * @return
			 */
			public function check_novalnet_payment_response( $api_response ) {
				/*if ( $this->check_response_status( $api_response ) ) {
					if ( isset( $api_response['hash'] ) ) {
						if ( ! wc_novalnet_check_hash( $api_response ) ) {
							wc_novalnet_display_info( __('While redirecting some data has been changed. The hash check failed.', 'wc-novalnet') );
							wp_safe_redirect( WC()->cart->get_checkout_url() );
							exit();
						} else {
							$api_response['test_mode'] = wc_novalnet_decode_data( $api_response['test_mode'] );
						}
					}
					return $this->validate_novalnet_status( $api_response, $api_response['order_no'] );
				} else {
					$message = wc_novalnet_response_text( $api_response );
					$this->transaction_failure( $api_response, $message, $api_response['order_no'] );
				}*/
				if ( isset( $api_response['hash'] ) && ! wc_novalnet_check_hash( $api_response ) ) {
					wc_novalnet_display_info(__('While redirecting some data has been changed. The hash check failed', 'novalnet'), 'error');
					wp_safe_redirect( WC()->cart->get_checkout_url() );
					exit();
				} else {
					$message = wc_novalnet_response_text( $api_response );
					if( isset( $api_response['hash'] ) )
						$api_response['test_mode'] = wc_novalnet_decode_data( $api_response['test_mode'] );
					$this->validate_novalnet_status( $api_response, $message,$api_response['order_no'] );
				}
			}
		} // End of class Novalnet_Payment_Gateway
	}

	/**
	 * Adds Novalnet gateway to WooCommerce
	 *
	 * @access public
	 * @param array $methods
	 * @return array $methods
	 */
    function wc_add_novalnet_payments( $methods ) {
		$methods[] =  'WC_Gateway_Novalnet_Cc';
		$methods[] =  'WC_Gateway_Novalnet_Eps';
		$methods[] =  'WC_Gateway_Novalnet_Instantbank';
		$methods[] =  'WC_Gateway_Novalnet_Invoice';
		$methods[] =  'WC_Gateway_Novalnet_Ideal';
		$methods[] =  'WC_Gateway_Novalnet_PayPal';
		$methods[] =  'WC_Gateway_Novalnet_Prepayment';
		$methods[] =  'WC_Gateway_Novalnet_Sepa';
        return $methods;
    }

    if ( isset( $_POST['input2'] ) && $_POST['input2'] == 'novalnet_class_name' && !empty( $_POST['inputval2']  ) ) {
        include_once( dirname( __FILE__ ) . '/includes/gateways/' . $_POST['inputval2'] );
		return;
    } else {
		 add_filter( 'woocommerce_payment_gateways', 'wc_add_novalnet_payments' );
		 foreach ( glob( dirname( __FILE__ ) . '/includes/gateways/*.php' ) as $filename ) {
			include_once $filename;
		}
	}
 }
?>
