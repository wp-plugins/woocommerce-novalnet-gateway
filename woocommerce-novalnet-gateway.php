<?php
/*
 * Plugin Name: Woocommerce Payment Gateway by Novalnet
 * Plugin URI:  http://www.novalnet.com/modul/woocommerce
 * Description: Adds Novalnet Payment Gateway to Woocommerce e-commerce plugin
 * Author:      Novalnet
 * Author URI:  https://www.novalnet.de
 * Version: 	2.0.0
 * Text Domain: novalnet
 * Domain Path: /languages/
 * License:     GNU General Public License version 2.0
 */

 // Exit if accessed directly
 if ( ! defined('ABSPATH') )
    exit;

 // Novalnet Gateway plugin base directory and URL
 define( 'NOVALNET_DIR', plugin_dir_path( __FILE__ ) );
 define( 'NOVALNET_URL', plugin_dir_url( __FILE__ ) );

 // Plugin Activation and De-activation process
 register_activation_hook( __FILE__ , 'novalnet_activation' );
 register_deactivation_hook( __FILE__ , 'novalnet_uninstall' );
 register_uninstall_hook( __FILE__ , 'novalnet_uninstall' );

 // Initiate admin notice display
 add_action( 'admin_notices', 'novalnet_admin_notices' );

 //Filter the gateways displaying in the checkout
 add_filter( 'woocommerce_available_payment_gateways','novalnet_filter_gateways' , 1 );

 // additional links in the plugin page
 add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'novalnet_action_links' );

 // action on login and logout
 add_action('wp_logout', 'novalnet_unset_process');
 add_action('wp_login', 'novalnet_unset_process');

 // actions to perform once on plugin activation
 function novalnet_activation() {
	global $wpdb;
	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	if ( ! get_option('novalnet_db_version') || get_option('novalnet_db_version') != NOVALNET_GATEWAY_VERSION ) {

		$charset_collate = '';
		if ( ! empty (  $wpdb->charset ) ) {
			$charset_collate = 'DEFAULT CHARACTER SET ' . $wpdb->charset;
		}
		if ( ! empty (  $wpdb->collate ) ) {
			$charset_collate .= ' COLLATE ' . $wpdb->collate;
		}

		$nn_trans_table = $wpdb->prefix . 'novalnet_transaction_detail';

		$txn_sql = "CREATE TABLE IF NOT EXISTS $nn_trans_table (
			`id` int(20) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Auto Increment ID',
			`order_no` bigint(20) unsigned NOT NULL COMMENT 'Order ID from shop',
			`payment_type` varchar(50) NOT NULL COMMENT 'Executed Payment type of this order',
			`tid` varchar(20) COMMENT 'Novalnet Transaction Reference ID',
			`vendor_id` int(11) unsigned NOT NULL COMMENT 'Vendor ID',
			`auth_code` varchar(30) NOT NULL COMMENT 'Authorisation Code',
			`product_id` int(8) unsigned NOT NULL COMMENT 'Product ID',
			`tariff_id` int(8) unsigned NOT NULL COMMENT 'Tariff ID',
			`payment_id` int(11) unsigned NOT NULL COMMENT 'Payment ID',
			`subs_id` int(8) unsigned DEFAULT NULL COMMENT 'Subscription Status',
			`amount` int(11) NOT NULL COMMENT 'Transaction amount in cents',
			`callback_amount` int(11) DEFAULT '0' COMMENT 'Transaction paid amount in cents',
			`refunded_amount` int(11) DEFAULT '0' COMMENT 'Transaction refunded amount in cents',
			`currency` char(3) NOT NULL COMMENT 'Transaction currency',
			`status` varchar(9) NOT NULL COMMENT 'Novalnet transaction status in response',
			`gateway_status` varchar(9) NOT NULL COMMENT 'Novalnet transaction status',
			`test_mode` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT 'Transaction test mode status',
			`customer_id` int(11) unsigned DEFAULT NULL COMMENT 'Customer ID from shop',
			`customer_email` varchar(50) DEFAULT NULL COMMENT 'Customer email from shop',
			`date` datetime NOT NULL COMMENT 'Transaction Date for reference',
			`active` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT 'Status',
			`process_key` varchar(255) DEFAULT NULL COMMENT 'Encrypted process key',
			PRIMARY KEY (`id`),
			KEY `tid` (`tid`),
			KEY `tariff_id` (`tariff_id`),
			KEY `subs_id` (`subs_id`),
			KEY `payment_id` (`payment_id`),
			KEY `payment_type` (`payment_type`),
			KEY `amount` (`amount`),
			KEY `status` (`status`),
			KEY `order_no` (`order_no`),
			KEY `date` (`date`),
			KEY `currency` (`currency`),
			KEY `gateway_status` (`gateway_status`)
		) $charset_collate COMMENT='Novalnet Transaction History';";

		dbDelta( $txn_sql );

		$callback_table = $wpdb->prefix . 'novalnet_callback_history';

		$clbck_sql = "CREATE TABLE IF NOT EXISTS $callback_table (
			`id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Auto Increment ID',
			`date` datetime NOT NULL COMMENT 'Callback DATE TIME',
			`payment_type` varchar(100) NOT NULL COMMENT 'Callback Payment Type',
			`status` varchar(20) DEFAULT NULL COMMENT 'Callback Status',
			`callback_tid` varchar(20) NOT NULL COMMENT 'Callback Reference ID',
			`org_tid` varchar(20) DEFAULT NULL COMMENT 'Original Transaction ID',
			`amount` int(11) DEFAULT NULL COMMENT 'Amount in cents',
			`currency` varchar(5) DEFAULT NULL COMMENT 'Currency',
			`product_id` int(11) unsigned DEFAULT NULL COMMENT 'Callback Product ID',
			`order_no` bigint(20) unsigned NOT NULL COMMENT 'Order ID from shop',
			PRIMARY KEY (`id`),
			KEY `payment_type` (`payment_type`),
			KEY `status` (`status`),
			KEY `callback_tid` (`callback_tid`),
			KEY `amount` (`amount`),
			KEY `currency` (`currency`),
			KEY `product_id` (`product_id`),
			KEY `orders_id` (`order_no`),
			KEY `date` (`date`),
			KEY `org_tid` (`org_tid`)
			) $charset_collate COMMENT='Novalnet Callback History';";

		dbDelta( $clbck_sql );

		$affiliate_table = $wpdb->prefix . 'novalnet_aff_account_detail';

		$affiliate_sql = "CREATE TABLE IF NOT EXISTS $affiliate_table (
			`id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
			`vendor_id` int(11) unsigned NOT NULL,
			`vendor_authcode` varchar(40) NOT NULL,
			`product_id` int(11) unsigned NOT NULL,
			`product_url` varchar(200) NOT NULL,
			`activation_date` datetime NOT NULL,
			`aff_id` int(11) unsigned DEFAULT NULL,
			`aff_authcode` varchar(40) DEFAULT NULL,
			`aff_accesskey` varchar(40) DEFAULT NULL,
			PRIMARY KEY (`id`),
			KEY `vendor_id` (`vendor_id`),
			KEY `product_id` (`product_id`),
			KEY `aff_id` (`aff_id`)
		) COMMENT='Novalnet merchant / affiliate account information';";

		dbDelta( $affiliate_sql );
	}

	if ( ! get_option('novalnet_db_version') ) {
		add_option( 'novalnet_db_version', NOVALNET_GATEWAY_VERSION );
	}elseif ( get_option( 'novalnet_db_version' ) != NOVALNET_GATEWAY_VERSION ) {
		update_option( 'novalnet_db_version', NOVALNET_GATEWAY_VERSION );
	}
 }	// End novalnet_activation()


 //actions to perform once on plugin uninstall
 function novalnet_uninstall() {
	global $novalnet_payment_methods;
	delete_option( 'novalnet_db_version' );
	foreach ( $novalnet_payment_methods as $k => $v ){
		delete_option ( 'woocommerce_'. $v . '_settings' );
	}
	foreach ( $GLOBALS[ NN_CONFIG ]->global_settings as $k => $v){
		delete_option ( 'novalnet_' . $k );
	}
 }	// End novalnet_uninstall()


 /**
  * Display admin notice at back-end during plugin activation
  */
 function novalnet_admin_notices() {

    if ( ! is_plugin_active('woocommerce/woocommerce.php') ) {

        echo '<div id="notice" class="error"><p>';
        echo '<b>' . __('Woocommerce Payment Gateway by Novalnet', 'novalnet') . '</b> ' . __('add-on requires', 'novalnet') . ' ' . '<a href="http://www.woothemes.com/woocommerce/" target="_new">' . __('WooCommerce', 'novalnet') . '</a>' . ' ' . __('plugin. Please install and activate it.', 'novalnet');
        echo '</p></div>', "\n";

    }
 }	// End novalnet_admin_notices()

 /**
  * Get active network plugins
  */
 function nn_active_nw_plugins() {
	if ( ! is_multisite() )
		return false;
	$nn_activePlugins = ( get_site_option('active_sitewide_plugins') ) ? array_keys( get_site_option('active_sitewide_plugins') ) : array();
	return $nn_activePlugins;
 }

 /**
  * Disable payments in the checkout if not configured with valid API details
  */
 function novalnet_filter_gateways( $gateways ) {
	global $novalnet_payment_methods;
    $enabled_novalnet_payments = array();
    $configurations = $GLOBALS[NN_CONFIG]->global_settings;
    $message = $GLOBALS[ NN_FUNCS ]->validate_global_settings( $configurations );

    foreach ( $gateways as $k ) {
        if ( in_array( $k->id, $novalnet_payment_methods ) ) {
            array_push( $enabled_novalnet_payments, $k->id );
        }
    }
    foreach ( $enabled_novalnet_payments as $key => $value ) {
        if ( $message ) {
            unset( $gateways[ $value ] );
        }
    }
    return $gateways;
 }

 // Clears Novalnet payments session
 function novalnet_unset_process() {
	if ( isset( $_SESSION ) && $_SESSION ) {
		$_SESSION['novalnet'] = false;
	}
 }

 // Adds the configuration link in the Plugins page.
 function novalnet_action_links( $links ) {
   $add_links = array( '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=novalnet_settings' ) . '">' . __('Configuration','novalnet') . '</a>' );
   return array_merge( $add_links , $links );
 }

 /* Plugin installation ends */
 $novalnet_payment_methods = array(
	  'nn_banktransfer'    =>'novalnet_banktransfer',
	  'nn_creditcard'      =>'novalnet_cc',
	  'nn_ideal'           =>'novalnet_ideal',
	  'nn_invoice'		   =>'novalnet_invoice',
	  'nn_paypal'          =>'novalnet_paypal',
	  'nn_prepayment'      =>'novalnet_prepayment',
	  'nn_directdebitsepa' =>'novalnet_sepa',
 );

 add_action('plugins_loaded', 'init_wc_novalnet_payment_gateway', 0);

 /*** Initiate plugin actions */
 function init_wc_novalnet_payment_gateway() {

	/* loads Novalnet Gateway language translations */
    load_plugin_textdomain( 'novalnet', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

    /* verify whether woocommerce is an active plugin before initializing Novalnet Payment Gateway */
    if ( in_array( 'woocommerce/woocommerce.php', ( array ) get_option( 'active_plugins' ) ) || in_array( 'woocommerce/woocommerce.php', ( array ) nn_active_nw_plugins() ) ) {

		if ( ! class_exists('WC_Payment_Gateway') )
            return;

			/*** Common class for Novalnet Payment Gateway ***/
		class WC_Novalnet_Payment_Gateway extends WC_Payment_Gateway {

			/* Novalnet Payment method arrays */
			public $has_return_url 	      	= array( NOVALNET_BT, NOVALNET_ID, NOVALNET_PP );
			public $invoice_payments        = array( NOVALNET_IN, NOVALNET_PT );
			public $has_manual_check_limit  = array( NOVALNET_CC, NOVALNET_SEPA );

			function __construct() {
				ob_start();

				if ( ! isset( $_SESSION ) ) {
					session_start();
				}
			}

			/**
			 * display error and message
			 */
			public function add_display_info( $message, $message_type = 'error' ) {
				global $woocommerce;

				switch ( $message_type ) {
					case 'error':
						if ( is_object( $woocommerce->session ) )
							$woocommerce->session->errors = $message;
						else
							$_SESSION['errors'][] = $message;

						 if ( version_compare( $woocommerce->version, '2.1.0', '>=' ) )
							wc_add_notice( $message, 'error' );
						else
							$woocommerce->add_error( $message, 'error' );

						break;
					case 'message':
						if ( is_object( $woocommerce->session ) )
							$woocommerce->session->messages = $message;
						else
							$_SESSION['messages'][] = $message;
						if ( version_compare( $woocommerce->version, '2.1.0', '>=' ) )
							wc_add_notice( $message );
						else
							$woocommerce->add_message( $message );

						break;
				}
			}   // End add_display_info()

			/**
			 * Validate curl extension
			 */
			public function check_curl_installed_or_not( $payments ) {
				if ( ! function_exists('curl_init') ) {
					return true;
				}
				return false;
			}   // End check_curl_installed_or_not()

			/**
			 * get url for direct form payment methods
			 */
			public function return_redirect_page( $result, $redirect_url ) {

				return array(
					'result'   => $result,
					'redirect' => $redirect_url
				);
			}   // End return_redirect_page()

			public function generate_payment_parameters( $order_id, $nn_obj ) {
				$is_redirect_method = 0;
				$wc_order = new WC_Order( $order_id );

				$curl_init = $this->check_curl_installed_or_not( $nn_obj->id );
				if ( $curl_init ) {
					$this->add_display_info( __( 'You need to activate the CURL function on your server, please check with your hosting provider.', 'novalnet' ), 'error' );
					return( $this->return_redirect_page( 'success', WC()->cart->get_checkout_url() ) );
				}

				list( $firstName, $lastName, $email ) = $this->check_customer_details( $wc_order );

				$amount = $this->currency_format( $wc_order->order_total );

				$payment_parameters = array(
					'vendor'		=> $GLOBALS[ NN_CONFIG ]->global_settings['vendor_id'],
					'auth_code'		=> $GLOBALS[ NN_CONFIG ]->global_settings['auth_code'],
					'product'		=> $GLOBALS[ NN_CONFIG ]->global_settings['product_id'],
					'tariff'		=> $GLOBALS[ NN_CONFIG ]->global_settings['tariff_id'],
					'key'			=> $nn_obj->payment_key,
					'payment_type'  => $nn_obj->payment_type,
					'test_mode'		=> $nn_obj->test_mode,
					'currency'		=> get_woocommerce_currency(),
					'first_name'	=> $firstName,
					'last_name'		=> $lastName,
					'gender'		=> 'u',
					'email'			=> $email,
					'street'		=> $wc_order->billing_address_1 . $wc_order->billing_address_2,
					'search_in_street'=> 1,
					'city'			=> $wc_order->billing_city,
					'zip'			=> ! empty( $wc_order->billing_postcode ) ? $wc_order->billing_postcode : '-',
					'lang'			=> strtoupper( $GLOBALS[ NN_CONFIG ]->language ),
					'language'		=> $GLOBALS[ NN_CONFIG ]->language,
					'country'		=> $wc_order->billing_country,
					'country_code'	=> $wc_order->billing_country,
					'tel'			=> $wc_order->billing_phone,
					'remote_ip'		=> $this->get_remote_server_addr('REMOTE_ADDR'),
					'customer_no'	=> $wc_order->user_id > 0 ? $wc_order->user_id : 'guest',
					'use_utf8'		=> 1,
					'amount'		=> $amount,
					'order_no'		=> ltrim( $wc_order->get_order_number(), _x( '#', 'hash before order number', 'woocommerce' ) ),
					'system_name'   => 'wordpress-woocommerce',
					'system_version'=> get_bloginfo('version'). '-'.WOOCOMMERCE_VERSION . '-NN'. NOVALNET_GATEWAY_VERSION,
					'system_url'    => site_url(),
					'system_ip'     => $this->get_remote_server_addr('SERVER_ADDR'),
				);

				if ( isset( $GLOBALS[ NN_CONFIG ]->global_settings['referrer_id'] ) && $GLOBALS[ NN_FUNCS ]->is_digits( $GLOBALS[ NN_CONFIG ]->global_settings['referrer_id'] ) ) {
					$payment_parameters['referrer_id'] = $GLOBALS[ NN_CONFIG ]->global_settings['referrer_id'];
				}

				if (in_array( $nn_obj->id, $this->has_manual_check_limit ) && $this->assign_manual_check_limit( $nn_obj->manual_limit, $amount ) ){
					$payment_parameters['product'] =  $nn_obj->product_id2;
					$payment_parameters['tariff'] =  $nn_obj->tariff_id2;
				}


				if ( $nn_obj->payment_type == 'CREDITCARD' ) {

					$payment_parameters['cc_holder']    = $payment_parameters['cc_no'] = $payment_parameters['cc_exp_month'] = $payment_parameters['cc_exp_year'] = '';
					$payment_parameters['cc_cvc2'] = $nn_obj->card_details['cvc'];
					$payment_parameters['pan_hash']= $nn_obj->card_details['panhash'];
					$payment_parameters['unique_id']= $nn_obj->card_details['uniqid'];

					if ( isset( $nn_obj->cc_secure ) && $nn_obj->cc_secure == '1') {
						$payment_parameters['encoded_amount'] = $amount;
						$this->generate_encode(  $payment_parameters, array( 'encoded_amount' ) );
						$is_redirect_method = 1;
					}
				} else if ( $nn_obj->payment_type == 'DIRECT_DEBIT_SEPA' ) {

					$payment_parameters['bank_account'] = $payment_parameters['bank_code'] = $payment_parameters['bic'] = $payment_parameters['iban'] = '';
					$payment_parameters['iban_bic_confirmed'] = 1;
					$payment_parameters['bank_account_holder'] = $GLOBALS[ NN_FUNCS ]->get_valid_holdername(trim( $nn_obj->sepa_details['sepa_holder']));
					$payment_parameters['sepa_hash'] = $nn_obj->sepa_details['sepa_panhash'];
					$payment_parameters['sepa_unique_id'] = $nn_obj->sepa_details['sepa_uniqid'];
					$sepa_due_date_limit = ( ( $nn_obj->sepa_duration != '' && $nn_obj->sepa_duration >= 7 ) ? $nn_obj->sepa_duration : 7 );
					$payment_parameters['sepa_due_date'] = (date('Y-m-d', strtotime('+'.$sepa_due_date_limit.' days')));
				} else if (in_array( $nn_obj->id, $this->invoice_payments)) {
					if ( $nn_obj->id == NOVALNET_IN & !empty (  $nn_obj->due_date)) {
							$payment_parameters['due_date'] = $nn_obj->due_date;
					}
					$payment_parameters['invoice_type'] = $nn_obj->payment_type;
					$payment_parameters['invoice_ref'] = 'BNR-' . $payment_parameters['product'] . '-' . ltrim( $wc_order->get_order_number(), _x( '#', 'hash before order number', 'woocommerce' ) ) ;
				} else if (in_array( $nn_obj->id, $this->has_return_url)) {
					$payment_parameters['uniqid'] = uniqid();
					$this->generate_encode($payment_parameters, array('auth_code', 'product', 'tariff', 'amount', 'test_mode', 'uniqid'));
					$payment_parameters['hash'] = $this->generate_hash_value( $payment_parameters );
					$payment_parameters['implementation']= 'PHP'; #Encoding type
					$is_redirect_method = 1;
				}
				if ( $is_redirect_method == 1 ) {
					$payment_parameters['user_variable_0'] = site_url();
					$payment_parameters['return_url'] = get_permalink( get_option('woocommerce_checkout_page_id' ) );
					$payment_parameters['error_return_url'] = get_permalink( get_option('woocommerce_checkout_page_id' ) );
					$payment_parameters['return_method'] = 'POST' ;
					$payment_parameters['error_return_method'] = 'POST' ;
				}
				$payment_parameters = $this->append_custom_parameters( $payment_parameters, $nn_obj, $wc_order->id ) ;
				$payment_url = $this->get_payment_url( $nn_obj );

				if ( $payment_url != '' && $is_redirect_method == 0) {

					$payment_response = $GLOBALS[ NN_FUNCS ]->perform_https_request( $payment_url, $payment_parameters );

					wp_parse_str( $payment_response, $response );

					return $this->validate_novalnet_status( $nn_obj, $response, $order_id );
				} else {
					return $payment_parameters;
				}
			}

			public function get_payment_url( $nn_obj ) {
				$payment = $nn_obj->id;
				$domain = is_ssl() ? 'https://' : 'http://';
				if ( $payment == NOVALNET_BT || $payment == NOVALNET_ID )
					return $domain.SOFORT_PAYPORT_URL;
				else if ( $payment == NOVALNET_PP )
					return $domain.PAYPAL_PAYPORT_URL;
				else if ( $payment == NOVALNET_CC && $nn_obj->cc_secure == 1 )
					return $domain.PCI_PAYPORT_URL;
				else
					return $domain.PAYGATE_URL;
			}

			public function append_custom_parameters( $urlparam = array(), $nn_obj, $order_nr ) {
				$urlparam['input1'] = 'nn_shopnr';
				$urlparam['inputval1'] = $order_nr;

				if ( ! empty (  $nn_obj->reference1 ) ) {
					$urlparam['input2'] = 'Reference1';
					$urlparam['inputval2'] = $nn_obj->reference1;
				}
				if ( ! empty (  $nn_obj->reference2 ) ) {
					$urlparam['input3'] = 'Reference2';
					$urlparam['inputval3'] = $nn_obj->reference2;
				}

				return $urlparam;
			}

			/**
			 * Form username parameter
			 */
			private function _form_username_param( $order ) {

				$order->billing_first_name = trim( $order->billing_first_name );
                $order->billing_last_name = trim( $order->billing_last_name );

				/* Get customer first and last name */
				if ( empty( $order->billing_first_name ) || empty( $order->billing_last_name ) ) {
					$full_name = $order->billing_first_name . $order->billing_last_name;
					return preg_match( '/\s/', $full_name ) ? explode( " ", $full_name, 2 ) : array($full_name, $full_name );
				}
				return array( $order->billing_first_name, $order->billing_last_name );
			} // End username formation parameter()

			public function check_customer_details( $order ) {
                list( $fname, $lname ) = $this->_form_username_param( $order );
				if ( $fname == null || $lname == null || !is_email( $order->billing_email ) ) {
					$this->add_display_info( __('Customer name/email fields are not valid', 'novalnet') , 'error' );
					return($this->return_redirect_page( 'success', WC()->cart->get_checkout_url() ) );
				}
				return array( $fname, $lname, $order->billing_email );
			}

			/*
			* Perform manual check limit functionality
			* @return product, tariff values
			*/
			public function assign_manual_check_limit( $manual_limit , $order_amount = null ) {
				if ( $manual_limit != '' && intval( $manual_limit ) > 0 && $order_amount >= $manual_limit ) {
					return true;
				}
				return false;
			}

			public function currency_format( $amount ) {
				return  str_replace(',', '', number_format( $amount, 2 ) ) * 100;
			}

			/*
			* Return Remote / Server IP-address
			* @return IP Address
			*/
			public function get_remote_server_addr( $type ) {
				return ( ( $_SERVER[ $type] == '::1' ) ? '127.0.0.1' : $_SERVER[ $type ] );
			}

			/*
			* Perform the encoding process for redirection payment methods
			* @return Encoded value
			*/
			public function generate_encode( &$payment_parameters, $encoded_values ) {
				foreach ( $encoded_values as $k => $v ) {
					$data = $payment_parameters[ $v ];
					try {
						$crc = sprintf('%u', crc32( $data ) );
						$data = $crc . "|" . $data;
						$data = bin2hex( $data . trim( $GLOBALS[ NN_CONFIG ]->global_settings['key_password'] ) );
						$data = strrev( base64_encode( $data) );
					} catch ( Exception $e ) {
						echo('Error: ' . $e );
					}
					$payment_parameters[ $v ] = $data;
				}
			}

			/*
			* Perform the decoding process for redirection payment methods
			* @return Decoded value
			*/
			public function generate_decode( $data = '' ) {
				try {
					$data = base64_decode( strrev( $data ) );
					$data = pack("H" . strlen( $data ), $data );
					$data = substr( $data, 0, stripos( $data, trim( $GLOBALS[ NN_CONFIG ]->global_settings['key_password'] ) ) );
					$pos = strpos( $data, "|" );
					if ( $pos === false ) {
							return ( "Error: CKSum not found!" );
					}
					$crc = substr( $data, 0, $pos );
					$value = trim(substr( $data, $pos + 1 ) );
					if ( $crc != sprintf('%u', crc32( $value ) ) ) {
							return("Error; CKSum invalid!");
					}
					return $value;
				} catch ( Exception $e ) {
						echo('Error: ' . $e );
				}

				return $data;
			}

			/*
			* Perform HASH Generation process for redirection payment methods
			* @return HASH value
			*/
			public function generate_hash_value( $payment_parameters = array() ) {
				return md5( $payment_parameters['auth_code'] . $payment_parameters['product'] . $payment_parameters['tariff'] . $payment_parameters['amount'] . $payment_parameters['test_mode'] . $payment_parameters['uniqid'] . strrev( trim( $GLOBALS[ NN_CONFIG ]->global_settings['key_password'] ) ) );
			}

			//check the response hash is equal to request hash
			public function novalnet_check_hash( $request ) {
				return ( $request && $request['hash2'] == $this->generate_hash_value( $request ) );
			}

			public function check_redirect_response( $response, $nn_obj ) {
				$order_id = (int) $response['order_no'];
				try {
					$order                   = new WC_Order( $order_id );
					if ( isset( $response['hash'] ) )
						$response['test_mode'] = $this->generate_decode( $response['test_mode'] );
					$this->validate_novalnet_status( $nn_obj, $response, $order_id );
				}
				catch ( Exception $e ) {
					echo 'Error -' . $e;
				}
			}

			/**
			 * Process Novalnet server response
			 */
			public function validate_novalnet_status( $payment_obj, $response , $nn_order_no ) {

				$message = $this->get_novalnet_response_text( $response );
				if ( true === $this->check_response_status( $response, $payment_obj ) ) {
					return( $this->transaction_success( $response, $message, $payment_obj ) );
				} else {
					return( $this->transaction_failure( $response, $message, $nn_order_no, $payment_obj ) );
				}
			}	// End validate_novalnet_status()

			/**
			 * validate novalnet status response
			 * @access public
			 * @return boolean
			 *
			 */
			public function check_response_status( $response, $payment_obj ) {
				if ( COMPLETE_CODE == $response['status'] || ( isset( $payment_obj->id ) && $payment_obj->id == NOVALNET_PP && PENDING_CODE == $response['status'] ) ) {
					return true;
				}
				else {
					return false;
				}
			}	// End check_response_status()

			/**
			 * Get Server Response message
			 */
			public function get_novalnet_response_text( $response ) {
				return( isset( $response['status_text'] ) ? $response['status_text'] : ( isset( $response['status_desc'] ) ? $response['status_desc'] : ( isset( $response['status_message'] ) ? $response['status_message'] : '' ) ) );
			}   // End get_response_text()

			public function get_test_order_result( $response, $payment_obj ) {
				return ( ( ( isset( $response['test_mode'] ) && 1 == $response['test_mode'] ) || ( isset( $payment_obj->test_mode ) && 1 == $payment_obj->test_mode ) ) ? 1 : 0 );
			}

			/* adds Novalnet Transaction details to order notes */
			public function prepare_payment_comments( $response, $payment_obj, $order ) {
				global $wpdb;
				$tmp_test_mode = $this->get_test_order_result( $response, $payment_obj ) ;

				$new_line = "\n";
				$novalnet_comments  = $new_line . __( $payment_obj->title, 'novalnet') . $new_line;
				$novalnet_comments .= __('Novalnet Transaction ID : ', 'novalnet') . $response['tid'] . $new_line;
				$novalnet_comments .= $tmp_test_mode ? __('Test order', 'novalnet') : '';

				if ( in_array( $payment_obj->id, $this->invoice_payments ) ) {

					$novalnet_comments .= $response['test_mode'] ? $new_line . $new_line : $new_line;
					$novalnet_comments .= __('Please transfer the amount to the following information to our payment service Novalnet AG', 'novalnet') . $new_line;
					if ( $response['due_date'] != '' ) {
						$novalnet_comments.= __('Due date : ', 'novalnet') . date_i18n(get_option('date_format'), strtotime( $response['due_date'])) . $new_line;
					}
					$novalnet_comments .= __('Account holder : Novalnet AG', 'novalnet') . $new_line;
					$novalnet_comments .= 'IBAN : ' . $response['invoice_iban'] . $new_line;
					$novalnet_comments .= 'BIC : ' . $response['invoice_bic'] . $new_line;
					$novalnet_comments .= 'Bank : ' . $response['invoice_bankname'] . ' ' . trim( $response['invoice_bankplace']) . $new_line;
					$novalnet_comments .= __('Amount : ', 'novalnet') . strip_tags( $order->get_formatted_order_total()) . $new_line;
					$novalnet_comments .= __('Reference 1: BNR-', 'novalnet') . $GLOBALS[ NN_CONFIG ]->global_settings['product_id'] . '-' . $response['order_no'] . $new_line ;
					$novalnet_comments .= __('Reference 2: TID ', 'novalnet') . $response['tid'] . $new_line;
					$novalnet_comments .= __('Reference 3: Order No ', 'novalnet') . $response['order_no'] . $new_line . $new_line;
				}
				return $novalnet_comments;
			}

			/**
			 * Successful payment
			 * @access public
			 * @return success page url
			 */
			public function transaction_success( $response, $message, $payment_obj ) {

				global $wpdb;
				$order_no   = $response['inputval1'];
				$woo_seq_nr = $response['order_no'];
				$new_line = "\n";

			    $order = new WC_Order( $order_no );
				$novalnet_comments = $this->prepare_payment_comments( $response, $payment_obj, $order );

				// adds order note
				if ( $order->customer_note ) {
					$order->customer_note .= $new_line;
				}

				$order->customer_note .= html_entity_decode( $novalnet_comments, ENT_QUOTES, 'UTF-8');

				if (WOOCOMMERCE_VERSION < '2.0.0') {
					$order->customer_note .= utf8_encode( $novalnet_comments);
				}

				$order->add_order_note( $novalnet_comments);

				/** Update Novalnet Transaction details into shop database	 */
				$nn_order_notes = array(
					'ID' 			=> $order_no,
					'post_excerpt'  => $order->customer_note
				);
				wp_update_post( $nn_order_notes );

				/**	 basic validation for post_back and transaction status call	*/
				$return = $GLOBALS[ NN_FUNCS ]->is_valid_api_params( $payment_obj ,$response['tid'], $woo_seq_nr );

				if (in_array( $payment_obj->id, $this->has_manual_check_limit ) && $this->assign_manual_check_limit( $payment_obj->manual_limit, $order->order_total*100 ) ) {
					$product =  $payment_obj->product_id2;
					$tariff =  $payment_obj->tariff_id2;
				} else {
					$product =  $GLOBALS[ NN_CONFIG ]->global_settings['product_id'];
					$tariff =  $GLOBALS[ NN_CONFIG ]->global_settings['tariff_id'];
				}

				if ( $return ) {
					// send acknoweldgement call to Novalnet server
					$this->post_back_param( $response, $woo_seq_nr, $order, $payment_obj );
					// recieves the status code for transactions
					$gateway_status = $GLOBALS[ NN_FUNCS ]->make_transaction_status( $response, $order->id, false, array( 'product' => $product, 'tariff' => $tariff) );
				}

				$wpdb->insert( $wpdb->prefix.'novalnet_transaction_detail', array(
					'vendor_id'		=> $GLOBALS[ NN_CONFIG ]->global_settings['vendor_id'],
					'auth_code' 	=> $GLOBALS[ NN_CONFIG ]->global_settings['auth_code'],
					'tid' 			=> $response['tid'],
					'gateway_status'=> $gateway_status['status'],
					'subs_id' 		=> ( ! empty ( $gateway_status['subs_id'] ) ? $gateway_status['subs_id'] : '' ),
					'status' 		=> $response['status'],
					'test_mode' 	=> $this->get_test_order_result( $response, $payment_obj),
					'active' 		=> 1 ,
					'product_id' 	=> $product,
					'tariff_id' 	=> $tariff,
					'payment_id' 	=> $payment_obj->payment_key,
					'payment_type' 	=> $payment_obj->id,
					'amount' 		=> $order->order_total * 100,
					'callback_amount'=> !in_array( $order->payment_method, $this->invoice_payments) ? $order->order_total * 100 : 0,
					'refunded_amount'=> 0,
					'currency' 		=> get_woocommerce_currency(),
					'customer_id' 	=> ( $order->user_id ) ? $order->user_id  : 0,
					'customer_email'=> $order->billing_email,
					'order_no' 		=> $order->id,
					'date' 			=> date('Y-m-d H:i:s') ,
					'process_key' 	=> ( isset( $_SESSION['novalnet'][ $payment_obj->id ]['nn_sepa_hash'] ) ? $_SESSION['novalnet'][ $payment_obj->id ]['nn_sepa_hash'] : null ),
				));

				if ( $order->payment_method == NOVALNET_PP && $gateway_status['status'] == COMPLETE_CODE ) {
					$wpdb->insert(
						$wpdb->prefix . "novalnet_callback_history",
						array(
							'payment_type' 	=> $payment_obj->payment_type,
							'status' 		=> $response['status'],
							'callback_tid' 	=> '',
							'org_tid' 		=> $response['tid'],
							'amount' 		=> $order->order_total * 100,
							'currency' 		=> get_woocommerce_currency(),
							'product_id' 	=> $GLOBALS[ NN_CONFIG ]->global_settings['product_id'],
							'order_no' 		=> $order->id,
							'date' 			=> date('Y-m-d H:i:s')
						)
					);
				}

				$order->update_status( $payment_obj->set_order_status );

				if ( $gateway_status['status'] == 90 && $order->payment_method == NOVALNET_PP )
					$order->update_status( $payment_obj->paypal_pending_status );
				// successful message display
				$this->add_display_info( $message, 'message' );

				// Empty awaiting payment session
				if ( ! empty (  WC()->session->order_awaiting_payment ) ) {
					unset( WC()->session->order_awaiting_payment );
				}

				// Reduce stock levels
				$order->reduce_order_stock();

				// Remove cart
				WC()->cart->empty_cart();

				if ( isset( $_SESSION['novalnet'] ) )
					unset( $_SESSION['novalnet'] );

				if ( in_array( $payment_obj->id, $this->has_return_url ) || ( $payment_obj->id == NOVALNET_CC && $payment_obj->cc_secure ==1 ) ) {
					wp_safe_redirect( $this->get_return_url( $order ) );
					exit();
				}
				else {
					return( $this->return_redirect_page('success', $this->get_return_url( $order) ) );
				}
			}

			public function transaction_failure( $response, $message, $nn_order_no, $payment_obj ) {

				global $wpdb, $woocommerce;
				$new_line = "\n";

				$order_no = ( in_array( $payment_obj->id, $this->has_return_url ) || ( $payment_obj->id == NOVALNET_CC && $payment_obj->cc_secure == 1 ) ) ? $response['inputval1'] : $nn_order_no;

				$order = new WC_Order( $order_no );

				$novalnet_comments = $this->prepare_payment_comments( $response, $payment_obj, $order );
				$novalnet_comments .= $new_line . $message . $new_line;

				if ( $order->customer_note ) {
					$order->customer_note .= $new_line;
				}

				$order->customer_note .= html_entity_decode( $novalnet_comments, ENT_QUOTES, 'UTF-8' );

				/** Update order cancellation details into database	 */
				$nn_order_notes = array(
					'ID' 			=> $order_no,
					'post_excerpt'  => $order->customer_note
				);
				wp_update_post( $nn_order_notes );

				// adds order note
				$order->add_order_note( $order->customer_note );

				if (in_array( $payment_obj->id, $this->has_manual_check_limit ) && $this->assign_manual_check_limit( $payment_obj->manual_limit, $order->order_total*100 ) ) {
					$product =  $payment_obj->product_id2;
					$tariff =  $payment_obj->tariff_id2;
				} else {
					$product =  $GLOBALS[ NN_CONFIG ]->global_settings['product_id'];
					$tariff =  $GLOBALS[ NN_CONFIG ]->global_settings['tariff_id'];
				}

				$wpdb->insert( $wpdb->prefix.'novalnet_transaction_detail', array(
					'vendor_id' 	=> $GLOBALS[ NN_CONFIG ]->global_settings['vendor_id'],
					'auth_code' 	=> $GLOBALS[ NN_CONFIG ]->global_settings['auth_code'],
					'tid' 			=> $response['tid'],
					'gateway_status'=> 0,
					'subs_id' 		=> ( ! empty (  $gateway_status['subs_id'] ) ? $gateway_status['subs_id'] : '' ) ,
					'status' 		=> $response['status'],
					'test_mode' 	=> $this->get_test_order_result( $response, $payment_obj ),
					'active' 		=> 0 ,
					'product_id' 	=> $product,
					'tariff_id' 	=> $tariff,
					'payment_id' 	=> $payment_obj->payment_key,
					'payment_type' 	=> $payment_obj->id,
					'amount' 		=> $order->order_total * 100,
					'currency' 		=> get_woocommerce_currency(),
					'customer_id' 	=> ( $order->user_id ) ? $order->user_id  : 0,
					'customer_email'=> $order->billing_email,
					'order_no' 		=> $order->id,
					'date' 			=> date('Y-m-d H:i:s') ,
				));

				//	Cancel the order
				$order->cancel_order( $message );
				// Order cancellation message display
				do_action( 'woocommerce_cancelled_order', $order_no );

				$this->add_display_info( html_entity_decode( $message, ENT_QUOTES, 'UTF-8' ), 'error' );

				if ( in_array( $payment_obj->id, $this->has_return_url ) || ( $payment_obj->id == NOVALNET_CC && $payment_obj->cc_secure == 1 ) ) {
					wp_safe_redirect( WC()->cart->get_checkout_url() );
					exit();
				} else {
					return( $this->return_redirect_page('success', WC()->cart->get_checkout_url() ) );
				}
			}

			/**
			 * Send acknowledgement parameters to Novalnet server after successful transaction
			 */
			public function post_back_param( $response, $seq_no, $order, $nn_obj ) {

				if (in_array( $nn_obj->id, $this->has_manual_check_limit ) && $this->assign_manual_check_limit( $nn_obj->manual_limit, $order->order_total*100 ) ) {
					$product =  $nn_obj->product_id2;
					$tariff =  $nn_obj->tariff_id2;
				} else {
					$product =  $GLOBALS[ NN_CONFIG ]->global_settings['product_id'];
					$tariff =  $GLOBALS[ NN_CONFIG ]->global_settings['tariff_id'];
				}

				$urlparam = array(
					'vendor'    => $GLOBALS[ NN_CONFIG ]->global_settings['vendor_id'],
					'product'   => $product,
					'key'       => $nn_obj->payment_key,
					'tariff'    => $tariff,
					'auth_code' => $GLOBALS[ NN_CONFIG ]->global_settings['auth_code'],
					'status'    => COMPLETE_CODE,
					'tid'       => $response['tid'],
					'order_no'  => $seq_no
				);

				if ( in_array( $order->payment_method, $this->invoice_payments) ) {
					$urlparam['invoice_ref'] = "BNR-" . $GLOBALS[ NN_CONFIG ]->global_settings['product_id']. "-" . $seq_no;
				}

				$data = $GLOBALS[ NN_FUNCS ]->perform_https_request( ( is_ssl() ? 'https://' : 'http://' ) . PAYGATE_URL, $urlparam );

			}	// End post_back_param()

			public function thankyou_page() {
				if ( ! isset ( $_SESSION['novalnet']['novalnet_thankyou_page_got'] ) ) {
					if ( $this->instructions ) {
						echo wpautop( wptexturize( wp_kses_post( $this->instructions ) ) );
						 $_SESSION['novalnet']['novalnet_thankyou_page_got'] = 1;
					}
				}
			}
		}
	}
 }
 /* includes novalnet payment gateway base and admin setting class files */
 include_once( NOVALNET_DIR . 'includes/admin/novalnet-admin-actions.php');
 include_once( NOVALNET_DIR . 'novalnet-functions.php');

 foreach ( $novalnet_payment_methods as $novalnet_payment_method ) {
	require_once(dirname(__FILE__) . '/includes/gateways/class-wc-gateway-' . $novalnet_payment_method . '.php');
 }

 ob_get_clean();
?>