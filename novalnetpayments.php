<?php
/*
 * Plugin Name: Woocommerce Payment Gateway by Novalnet
 * Plugin URI:  http://www.novalnet.com/modul/woocommerce
 * Description: Adds Novalnet Payment Gateway to Woocommerce e-commerce plugin
 * Author:      Novalnet
 * Author URI:  https://www.novalnet.de
 * 
 * Version: 	 1.1.0
 * Requires at least:   3.3
 * Tested up to:        3.6.1
 *
 * Text Domain:         woocommerce-novalnetpayment
 * Domain Path:         /languages/
 * 
 * License: GPLv2
 */

/* Plugin installation starts */
register_activation_hook(__FILE__, 'novalnetpayments_activation');
register_deactivation_hook(__FILE__, 'novalnetpayments_deactivation');

/* Initiate admin notice display	 */
add_action('admin_notices', 'novalnetpayments_admin_notices');

if (!function_exists('novalnetpayments_activation')) {

    function novalnetpayments_activation() {
        //register uninstaller
        register_uninstall_hook(__FILE__, 'novalnetpayments_uninstall');
        
    }	//	End novalnetpayments_activation()
    
} #Endif

if (!function_exists('novalnetpayments_deactivation')) {

    function novalnetpayments_deactivation() {
        // actions to perform once on plugin deactivation go here  
        
    }	// End novalnetpayments_deactivation()
    
} #Endif

if (!function_exists('novalnetpayments_uninstall')) {

    function novalnetpayments_uninstall() {
        //actions to perform once on plugin uninstall go here
        
    }	// End novalnetpayments_uninstall()
    
} #Endif

/**
 * Display admin notice at back-end during plugin activation
 */
function novalnetpayments_admin_notices() {
    if (!is_plugin_active('woocommerce/woocommerce.php')) {
        echo '<div id="notice" class="error"><p>';
        echo '<b>' . __('Woocommerce Payment Gateway by Novalnet', 'woocommerce-novalnetpayment') . '</b> ' . __('add-on requires', 'woocommerce-novalnetpayment') . ' ' . '<a href="http://www.woothemes.com/woocommerce/" target="_new">' . __('WooCommerce', 'woocommerce-novalnetpayment') . '</a>' . ' ' . __('plugin. Please install and activate it.', 'woocommerce-novalnetpayment');
        echo '</p></div>', "\n";
    } #Endif
    
}	// End novalnetpayments_admin_notices()

/* Plugin installation ends */
$novalnet_payment_methods = array('novalnet_banktransfer', 'novalnet_cc', 'novalnet_cc3d', 'novalnet_elv_at', 'novalnet_elv_de', 'novalnet_ideal', 'novalnet_invoice', 'novalnet_paypal', 'novalnet_prepayment', 'novalnet_tel');

/**
 * Get active network plugins
 */
if (!function_exists('nn_active_nw_plugins')) {

    function nn_active_nw_plugins() {
        if (!is_multisite())
            return false;
        $nn_activePlugins = (get_site_option('active_sitewide_plugins')) ? array_keys(get_site_option('active_sitewide_plugins')) : array();
        return $nn_activePlugins;
    }	// End nn_active_nw_plugins()
    
}	#Endif

add_action('plugins_loaded', 'novalnetpayments_Load', 0);

/**
 * Initial plugin load
 */
function novalnetpayments_Load() {

    /* Load Novalnet language translations */
    load_plugin_textdomain('woocommerce-novalnetpayment', false, dirname(plugin_basename(__FILE__)) . '/languages/');

    /* verify whether woocommerce is an active plugin before initializing Novlanet Payment Gateway */
    if (in_array('woocommerce/woocommerce.php', (array) get_option('active_plugins')) || in_array('woocommerce/woocommerce.php', (array) nn_active_nw_plugins())) {
		
        if (!class_exists('WC_Payment_Gateway'))
            return;
            
        if (!class_exists('novalnetpayments')) {

            /**
             * Common class for Novalnet Payment Gateway
             */
            class novalnetpayments extends WC_Payment_Gateway {
                			 
				/* Novalnet Payment urls */
                var $novalnet_paygate_url = 'https://payport.novalnet.de/paygate.jsp';
                var $novalnet_cc_form_display_url = 'https://payport.novalnet.de/direct_form.jsp';
                var $novalnet_online_transfer_payport_url = 'https://payport.novalnet.de/online_transfer_payport';
                var $novlanet_cc3d_payport_url = 'https://payport.novalnet.de/global_pci_payport';
                var $novlanet_paypal_payport_url = 'https://payport.novalnet.de/paypal_payport';
                var $novlanet_tel_second_call_url = 'https://payport.novalnet.de/nn_infoport.xml';

                /* Novalnet Payment keys */
                var $payment_key_for_cc_family = 6;
                var $payment_key_for_at_family = 8;
                var $payment_key_for_de_family = 2;
                var $payment_key_for_invoice_prepayment = 27;
                var $payment_key_for_tel = 18;
                var $payment_key_for_paypal = 34;
                var $payment_key_for_online_transfer = 33;
                var $payment_key_for_ideal = 49;

                /* Novalnet Payment method arrays */
                var $front_end_form_available_array = array('novalnet_cc', 'novalnet_cc3d', 'novalnet_elv_de', 'novalnet_elv_at');
                var $manual_check_limit_not_available_array = array('novalnet_banktransfer', 'novalnet_ideal', 'novalnet_invoice', 'novalnet_prepayment', 'novalnet_paypal', 'novalnet_tel');
                var $return_url_parameter_for_array = array('novalnet_banktransfer', 'novalnet_cc3d', 'novalnet_ideal', 'novalnet_paypal');
                var $encode_applicable_for_array = array('novalnet_banktransfer', 'novalnet_ideal', 'novalnet_paypal');
                var $user_variable_parameter_for_arrray = array('novalnet_banktransfer', 'novalnet_paypal', 'novalnet_ideal');
                var $language_supported_array = array('en', 'de');

                /**
                 * Telephone payment second call request
                 */
                public function do_make_second_call_for_novalnet_telephone($order_id) {
					
                    global $woocommerce;
                    $order = new WC_Order($order_id);
					
                    /* validate Telephone second call mandatory parameters	 */
                    if (isset($this->vendor_id) && $this->vendor_id != null && isset($this->auth_code) && $this->auth_code != null && isset($_SESSION['novalnet_tel_tid']) && $_SESSION['novalnet_tel_tid'] != null && isset($this->language) && $this->language != null) {

                        ### Process the payment to infoport ##
                        $urlparam = '<nnxml><info_request><vendor_id>' . $this->vendor_id . '</vendor_id>';
                        $urlparam .= '<vendor_authcode>' . $this->auth_code . '</vendor_authcode>';
                        $urlparam .= '<request_type>NOVALTEL_STATUS</request_type><tid>' . $_SESSION['novalnet_tel_tid'] . '</tid>';
                        $urlparam .= '<lang>' . strtoupper($this->language) . '</lang></info_request></nnxml>';
                        list($errno, $errmsg, $data) = $this->perform_https_request($this->second_call_url, $urlparam);               
                        if (strstr($data, '<novaltel_status>')) {
                            preg_match('/novaltel_status>?([^<]+)/i', $data, $matches);
                            $aryResponse['status'] = $matches[1];
                            preg_match('/novaltel_status_message>?([^<]+)/i', $data, $matches);
                            $aryResponse['status_desc'] = $matches[1];
                        }	#Endif 
                        
                        else {
                            $aryPaygateResponse = explode('&', $data);
                            foreach ($aryPaygateResponse as $key => $value) {
                                if ($value != "") {
                                    $aryKeyVal = explode("=", $value);
                                    $aryResponse[$aryKeyVal[0]] = $aryKeyVal[1];
                                }	#Endif
                            }	#Endforeach
                        }	#Endelse
                        
                        $aryResponse['tid'] = $_SESSION['novalnet_tel_tid'];
                        $aryResponse['test_mode'] = $_SESSION['novalnet_tel_test_mode'];
                        $aryResponse['order_no'] = ltrim($order->get_order_number(), __('#', 'hash before order number', 'woocommerce-novalnetpayment'));
                        $aryResponse['inputval1'] = $order_id;

                        // Manual Testing
                        //    $aryResponse['status_desc'] = __('Successful', 'woocommerce-novalnetpayment');
                        //    $aryResponse['status'] 		= 100;
                        // Manual Testing

                        return($this->do_check_novalnet_status($aryResponse));
						
                    }	#Endif
                     
                    else {
                        $this->do_unset_novalnet_telephone_sessions();
                        $this->do_check_and_add_novalnet_errors_and_messages(__('Basic Parameter Missing', 'woocommerce-novalnetpayment'), 'error');
                        return($this->do_return_redirect_page_for_pay_or_thanks_page('success', $woocommerce->cart->get_checkout_url()));             
                    }	#Endelse
                    
                }	// End do_make_second_call_for_novalnet_telephone()

                /**
                 * Clears Telephone payment session value
                 */
                public function do_unset_novalnet_telephone_sessions() {
					
                    unset($_SESSION['novalnet_tel_tid']);
                    unset($_SESSION['novalnet_tel_test_mode']);
                    unset($_SESSION['novalnet_tel_amount']);
                    
                }	// End do_unset_novalnet_telephone_sessions()

                /**
                 * process Telephone payment server response
                 */
                public function do_check_novalnet_tel_payment_status(&$aryResponse, $order) {
                    global $woocommerce;
                    $new_line = "<br />";
                    
                    if ($aryResponse['status'] == 100 && $aryResponse['tid']) {
                        $aryResponse['status_desc'] = '';
                        if (!isset($_SESSION['novalnet_tel_tid']))
                            $_SESSION['novalnet_tel_tid'] = $aryResponse['tid'];
                        $_SESSION['novalnet_tel_test_mode'] = $aryResponse['test_mode'];
                        $_SESSION['novalnet_tel_amount'] = $this->amount;
                    }	#Endif
                  
                    elseif ($aryResponse['status'] == 19)
                        unset($_SESSION['novalnet_tel_tid']);

                    else
                        $status = $aryResponse['status'];
                   
				   if ($aryResponse['status'] == 100) {
                        $sess_tel = trim($aryResponse['novaltel_number']);
                        if ($sess_tel) {
                            $aryTelDigits = str_split($sess_tel, 4);
                            $count = 0;
                            $str_sess_tel = '';
                            foreach ($aryTelDigits as $ind => $digits) {
                                $count++;
                                $str_sess_tel .= $digits;
                                if ($count == 1)
                                    $str_sess_tel .= '-';
                                else
                                    $str_sess_tel .= ' ';
                            }	#Endforeach
                            
                            $str_sess_tel = trim($str_sess_tel);
                            if ($str_sess_tel)
                                $sess_tel = $str_sess_tel;
                        }	#Endif
                        
                        $this->do_check_and_add_novalnet_errors_and_messages(__('Following steps are required to complete your payment:', 'woocommerce-novalnetpayment') . $new_line . $new_line . __('Step 1: Please call the telephone number displayed:', 'woocommerce-novalnetpayment') . ' ' . $sess_tel . $new_line . str_replace('{amount}', $order->get_formatted_order_total(), __('* This call will cost {amount} (including VAT) and it is possible only for German landline connection! *', 'woocommerce-novalnetpayment')) . $new_line . $new_line . __('Step 2: Please wait for the beep and then hang up the listeners.', 'woocommerce-novalnetpayment') . $new_line . __('After your successful call, please proceed with the payment.', 'woocommerce-novalnetpayment'), 'message');
                        return($this->do_return_redirect_page_for_pay_or_thanks_page('success', $woocommerce->cart->get_checkout_url()));
                    }	#Endif
                    
                    else {
						
                        $this->do_check_and_add_novalnet_errors_and_messages($aryResponse['status_desc'], 'error');
                        return($this->do_return_redirect_page_for_pay_or_thanks_page('success', $woocommerce->cart->get_checkout_url()));
                        
                    }	#Endelse
                    
                }	// End do_check_novalnet_tel_payment_status()

                /**
                 * Validate cart amount
                 */
                public function do_validate_amount() {
					
                    global $woocommerce;
                    
                    if ($this->amount < 99 || $this->amount > 1000) {
						
                        $this->do_check_and_add_novalnet_errors_and_messages(__('Amounts below 0,99 Euros and above 10,00 Euros cannot be processed and are not accepted!', 'woocommerce-novalnetpayment'), 'error');
                        return($this->do_return_redirect_page_for_pay_or_thanks_page('success', $woocommerce->cart->get_checkout_url()));
                        
                    }	#Endif
                    
                }	// End do_validate_amount()

                /**
                 * Validate amount variations in cart
                 */
                public function do_validate_amount_variations() {
					
                    global $woocommerce;
                    
                    if (isset($_SESSION['novalnet_tel_amount']) && $_SESSION['novalnet_tel_amount'] != $this->amount) {
                    
                        $this->do_unset_novalnet_telephone_sessions();
                        $this->do_check_and_add_novalnet_errors_and_messages(__('You have changed the order amount after receiving telephone number, please try again with a new call', 'woocommerce-novalnetpayment'), 'error');
                        return($this->do_return_redirect_page_for_pay_or_thanks_page('success', $woocommerce->cart->get_checkout_url()));
                    
                    }	#Endif
                    
                    return('');
                    
                }	// End do_validate_amount_variations()

                /**
                 * Process data after paygate response
                 */
                public function do_prepare_to_novalnet_paygate($order) {
					
                    list($errno, $errmsg, $data) = $this->perform_https_request($this->payport_or_paygate_url, $this->payment_parameters);
                    $aryResponse = array();
                    
                    #capture the result and message and other parameters from response data '$data' in an array
                    $aryPaygateResponse = explode('&', $data);
                    
                    foreach ($aryPaygateResponse as $key => $value) {              
                        if ($value != "") {
                            $aryKeyVal = explode("=", $value);
                            $aryResponse[$aryKeyVal[0]] = $aryKeyVal[1];
                        }	#Endif     
                    }	#Endforeach
                    return($aryResponse);
					
                }	// End do_prepare_to_novalnet_paygate()

                /**
                 * process parameters before sending to server
                 */
                public function do_necessary_actions_before_prepare_to_novalnet_payport_or_paygate($order) {
                    $this->user_ip = $this->getRealIpAddr();
                    $this->do_check_curl_installed_or_not();
                    $this->do_format_amount($order->order_total);
                    $this->do_check_novalnet_backend_data_validation_from_frontend();
                    $this->do_check_and_assign_manual_check_limit();
                    $this->do_check_shop_parameter($order);
                    $this->do_form_payment_parameters($order);
                }	// End do_necessary_actions_before_prepare_to_novalnet_payport_or_paygate()

                /**
                 * Generate Novalnet secure form
                 */
                public function get_novalnet_form_html($order) {
                    
                    global $woocommerce;
                    $novalnet_args_array = array();
                    
                    foreach ($this->payment_parameters as $key => $value) {
                        $novalnet_args_array[] = '<input type="hidden" name="' . esc_attr($key) . '" id="' . esc_attr($key) . '" value="' . esc_attr($value) . '" />';
                    }	#Endforeach
                    
                    $woocommerce->add_inline_js('
						jQuery("body").block({
								message: "<img src=\"' . esc_url(apply_filters('woocommerce_ajax_loader_url', $woocommerce->plugin_url() . '/assets/images/ajax-loader.gif')) . '\" alt=\"' . __('Redirecting...', 'woocommerce-novalnetpayment') . '&hellip;\" style=\"float:left; margin-right: 10px;\" />' . __('You will be redirected to Novalnet AG in a few seconds. <br>', 'woocommerce-novalnetpayment') . '",
								baseZ: 99999,
								overlayCSS:
								{
								background: "#fff",
								opacity: 0.6
								},
								css: {
								padding:        20,
								textAlign:      "center",
								color:          "#555",
								border:         "3px solid #aaa",
								backgroundColor:"#fff",
								cursor:         "wait",
								lineHeight:		"32px"
								}
								});
						jQuery("#submit_novalnet_payment_form").click();
					');
					
                    return '<form id="frmnovalnet" name="frmnovalnet" action="' . $this->payport_or_paygate_url . '" method="post" target="_top">' . implode('', $novalnet_args_array) . '
					<input type="submit" class="button-alt" id="submit_novalnet_payment_form" value="' . __('Pay via Novalnet', 'woocommerce-novalnetpayment') . '" /> <a class="button cancel" href="' . esc_url($order->get_cancel_order_url()) . '">' . __('Cancel order &amp; restore cart', 'woocommerce-novalnetpayment') . '</a>
				</form>';
				
                }	// End get_novalnet_form_html()

                /**
                 * Validate curl extension
                 */
                public function do_check_curl_installed_or_not() {
					
                    global $woocommerce;
                    
                    if (!function_exists('curl_init') && !in_array($this->novalnet_payment_method, $this->return_url_parameter_for_array)) {
                        $this->do_check_and_add_novalnet_errors_and_messages(__('You need to activate the CURL function on your server, please check with your hosting provider.', 'woocommerce-novalnetpayment'), 'error');
                        wp_safe_redirect($woocommerce->cart->get_checkout_url());
                        exit();
                        
                    } #Endif
                    
                }	// End do_check_curl_installed_or_not()

                /**
                 * Validate shop address field parameter 
                 */
                public function do_check_shop_parameter($order) {
                    
                    global $woocommerce;
                    $nn_tmp_name = array();
                    
                    if (isset($order)) {

                        $nn_first_name = isset($order->billing_first_name) ? trim($order->billing_first_name) : null;
                        $nn_last_name = isset($order->billing_last_name) ? trim($order->billing_last_name) : null;
                        $this->shop_nn_email = isset($order->billing_email) ? trim($order->billing_email) : null;
                       
					   /* Get customer first and last name	 */
                        if ($nn_first_name != null && $nn_last_name != null) {
                            $this->shop_nn_first_name = $nn_first_name;
                            $this->shop_nn_last_name = $nn_last_name;
                        } #Endif 
                        
                        elseif ($nn_first_name != null && $nn_last_name == null) {
                            $full_name = $nn_first_name;
                            $nn_tmp_name = explode(' ', $full_name, 2);
                            if (count($nn_tmp_name) == 1) {
                                $this->shop_nn_first_name = $nn_tmp_name[0];
                                $this->shop_nn_last_name = $nn_tmp_name[0];
                            } #Endif
                            else {
                                $this->shop_nn_first_name = $nn_tmp_name[0];
                                $this->shop_nn_last_name = $nn_tmp_name[1];
                            } #Endelse
                        } #Endelseif
                        
                        elseif ($nn_first_name == null && $nn_last_name != null) {
                            $full_name = $nn_last_name;
                            $nn_tmp_name = explode(' ', $full_name, 2);
                            if (count($nn_tmp_name) == 1) {
                                $this->shop_nn_first_name = $nn_tmp_name[0];
                                $this->shop_nn_last_name = $nn_tmp_name[0];
                            } #Endif 
                            else {
                                $this->shop_nn_first_name = $nn_tmp_name[0];
                                $this->shop_nn_last_name = $nn_tmp_name[1];
                            } #Endelse
                        } #Endelseif
                        
                        elseif ($nn_first_name == null && $nn_last_name == null) {
                            $this->shop_nn_first_name = $nn_first_name;
                            $this->shop_nn_last_name = $nn_last_name;
                        } #Endelseif  

                        /** Novalnet validation for basic address fields (returns true only if the user modified default workflow) */
                        if ($this->shop_nn_first_name == null || $this->shop_nn_last_name == null || $this->shop_nn_email == null) {
                            $this->do_check_and_add_novalnet_errors_and_messages(__('Please enter the customer name / email.', 'woocommerce-novalnetpayment'), 'error');
                            wp_safe_redirect($woocommerce->cart->get_checkout_url());
                            exit();
                        }	#Endif
                        
                    }	#Endif
                    
                }	// End do_check_shop_parameter()

                /**
                 * Collects novalnet payment parameters
                 */
                public function do_form_payment_parameters($order) {
                    $this->get_backend_hash_parameter_array();
                    $this->get_backend_variation_parameter_array();
                    $this->get_user_variable_parameter_array();
                    $this->get_return_url_parameter_array();
                    $this->get_backend_additional_parameter_array($order);
                    $this->get_backend_common_parameter_array($order);	
                }	// End do_form_payment_parameters()

                /**
                 * Get back-end hash parameter
                 */
                public function get_backend_hash_parameter_array() {
                    
                    if (in_array($this->novalnet_payment_method, $this->encode_applicable_for_array)) {
                        $this->auth_code = $this->encode($this->auth_code);
                        $this->product_id = $this->encode($this->product_id);
                        $this->tariff_id = $this->encode($this->tariff_id);
                        $this->amount = $this->encode($this->amount);
                        $this->test_mode = $this->encode($this->test_mode);
                        $this->unique_id = $this->encode($this->unique_id);
                      
                        if (isset($this->api_username))
                            $this->api_username = $this->encode($this->api_username);
                      
                        if (isset($this->api_password))
                            $this->api_password = $this->encode($this->api_password);
                      
                        if (isset($this->api_signature))
                            $this->api_signature = $this->encode($this->api_signature);
                      
                        $hash = $this->hash(array('authcode' => $this->auth_code, 'product_id' => $this->product_id, 'tariff' => $this->tariff_id, 'amount' => $this->amount, 'test_mode' => $this->test_mode, 'uniqid' => $this->unique_id));
                        $this->payment_parameters['hash'] = $hash;
                    } #Endif
                    
                }	// End get_backend_hash_parameter_array()

                /**
                 * Get back-end variation parameter
                 */
                public function get_backend_variation_parameter_array() {
					
                    $this->payment_parameters['vendor'] = $this->vendor_id;
                    $this->payment_parameters['product'] = $this->product_id;
                    $this->payment_parameters['tariff'] = $this->tariff_id;
                    $this->payment_parameters['auth_code'] = $this->auth_code;
                    
                }	// End get_backend_variation_parameter_array()

                /**
                 * Get user variable parameter
                 */
                public function get_user_variable_parameter_array() {
                    
                    if (in_array($this->novalnet_payment_method, $this->user_variable_parameter_for_arrray))
                        $this->payment_parameters['user_variable_0'] = site_url();
                                        
                }	// End get_user_variable_parameter_array()

                /**
                 * Get return url parameter 
                 */
                public function get_return_url_parameter_array() {
					
                    $return_url = get_permalink(get_option('woocommerce_checkout_page_id'));
                    
                    if (in_array($this->novalnet_payment_method, $this->return_url_parameter_for_array)) {
                        $this->payment_parameters['return_url'] = $return_url;
                        $this->payment_parameters['return_method'] = 'POST';
                        $this->payment_parameters['error_return_url'] = $return_url;
                        $this->payment_parameters['error_return_method'] = 'POST';
                        $this->payment_parameters['novalnet_payment_method'] = $this->novalnet_payment_method;
                    } #Endif
                    
                }	// End get_return_url_parameter_array()

                /**
                 * Get back-end additional parameters
                 */
                public function get_backend_additional_parameter_array($order) {
					
                    if ($this->novalnet_payment_method == 'novalnet_invoice' || $this->novalnet_payment_method == 'novalnet_prepayment') {
                        $this->invoice_type = strtoupper(substr($this->novalnet_payment_method, strpos($this->novalnet_payment_method, '_') + 1, strlen($this->novalnet_payment_method)));
                        $this->invoice_ref = "BNR-" . $this->product_id . "-" . ltrim($order->get_order_number(), __('#', 'hash before order number', 'woocommerce-novalnetpayment'));
                        $this->payment_parameters['invoice_type'] = $this->invoice_type;
                        $this->payment_parameters['invoice_ref'] = $this->invoice_ref;
                        
                    }	#Endif
                    
                    if ($this->novalnet_payment_method == 'novalnet_invoice') {
						
                        if (is_numeric($this->payment_duration)) {
                            
                            if ($this->payment_duration > 0) {
                                $this->due_date = date("Y-m-d", mktime(0, 0, 0, date("m"), (date("d") + $this->payment_duration), date("Y")));
                            } #Endif 
                            
                            else
                                $this->due_date = date("Y-m-d", mktime(0, 0, 0, date("m"), date("d"), date("Y")));
                                
                        }	#Endif
                        
                        else
                            $this->due_date = '';
                            
                        $this->payment_parameters['due_date'] = $this->due_date;
                        $this->payment_parameters['end_date'] = $this->due_date;
                        
                    }	#Endif
                    
                    if ($this->novalnet_payment_method == 'novalnet_paypal') {
                        $this->payment_parameters['api_user'] = $this->api_username;
                        $this->payment_parameters['api_pw'] = $this->api_password;
                        $this->payment_parameters['api_signature'] = $this->api_signature;
                    }	#Endif
                    
                    if ($this->novalnet_payment_method == 'novalnet_elv_de' || $this->novalnet_payment_method == 'novalnet_elv_at') {
                        $this->payment_parameters['bank_account_holder'] = $_SESSION['bank_account_holder'];
                        $this->payment_parameters['bank_account'] = $_SESSION['bank_account'];
                        $this->payment_parameters['bank_code'] = $_SESSION['bank_code'];
                       
                        if ($this->novalnet_payment_method == 'novalnet_elv_de')
                            $this->payment_parameters['acdc'] = isset($_SESSION['acdc']) ? 1 : 0;
                       
                        unset($_SESSION['bank_account_holder']);
                        unset($_SESSION['bank_account']);
                        unset($_SESSION['bank_code']);
                       
                        if (isset($_SESSION['acdc']))
                            unset($_SESSION['acdc']);
                            
                    }	#Endif
                    
                    if ($this->novalnet_payment_method == 'novalnet_cc3d' || $this->novalnet_payment_method == 'novalnet_cc') {
                        
                        $this->payment_parameters['cc_holder'] = isset($_SESSION['cc_holder']) ? $_SESSION['cc_holder'] : null;
                        $this->payment_parameters['cc_no'] = isset($_SESSION['cc_number']) ? $_SESSION['cc_number'] : null;
                        $this->payment_parameters['cc_exp_month'] = isset($_SESSION['exp_month']) ? $_SESSION['exp_month'] : null;
                        $this->payment_parameters['cc_exp_year'] = isset($_SESSION['exp_year']) ? $_SESSION['exp_year'] : null;
                        $this->payment_parameters['cc_cvc2'] = isset($_SESSION['cvv_cvc']) ? $_SESSION['cvv_cvc'] : null;
                        
                        if ($this->novalnet_payment_method == 'novalnet_cc') {
							
                            $this->payment_parameters['unique_id'] = $_SESSION['nn_unique_id'];
                            $this->payment_parameters['pan_hash'] = $_SESSION['nn_cardno_id'];
                            
                        }	#Endif
                        
                        unset($_SESSION['cc_holder']);
                        unset($_SESSION['cc_number']);
                        unset($_SESSION['exp_month']);
                        unset($_SESSION['exp_year']);
                        unset($_SESSION['cvv_cvc']);
                        unset($_SESSION['nn_unique_id']);
                        unset($_SESSION['nn_cardno_id']);
                        
                    }	#Endif
                    
                }	// End get_backend_additional_parameter_array()

                /**
                 * Get common payment parameters (for all payment methods)
                 */
                public function get_backend_common_parameter_array($order) {
				
                    /* Novalnet common payment parameters	 */
                    $this->payment_parameters['key'] = $this->payment_key;
                    $this->payment_parameters['test_mode'] = $this->test_mode;
                    $this->payment_parameters['uniqid'] = $this->unique_id;
                    $this->payment_parameters['session'] = session_id();
                    $this->payment_parameters['currency'] = get_woocommerce_currency();
                    $this->payment_parameters['first_name'] = $this->shop_nn_first_name;
                    $this->payment_parameters['last_name'] = $this->shop_nn_last_name;
                    $this->payment_parameters['gender'] = 'u';
                    $this->payment_parameters['email'] = $this->shop_nn_email;
                    $this->payment_parameters['street'] = $order->billing_address_1;
                    $this->payment_parameters['search_in_street'] = 1;
                    $this->payment_parameters['city'] = $order->billing_city;
                    $this->payment_parameters['zip'] = $order->billing_postcode;
                    $this->payment_parameters['lang'] = strtoupper($this->language);
                    $this->payment_parameters['country'] = $order->billing_country;
                    $this->payment_parameters['country_code'] = $order->billing_country;
                    $this->payment_parameters['tel'] = $order->billing_phone;
                 // $this->payment_parameters['fax'] 	= "";
                //  $this->payment_parameters['birthday'] = ;
                    $this->payment_parameters['remote_ip'] = $this->user_ip;

                    /* Added support for official Woocommerce Sequential Order nubmer(pro) plugin */
                    $this->payment_parameters['order_no'] = ltrim($order->get_order_number(), __('#', 'hash before order number', 'woocommerce-novalnetpayment'));
                    $this->payment_parameters['input1'] = 'nnshop_nr';
                    $this->payment_parameters['inputval1'] = $order->id;

                    $this->payment_parameters['customer_no'] = $order->user_id > 0 ? $order->user_id : __('Guest', 'woocommerce-novalnetpayment');
                    $this->payment_parameters['use_utf8'] = 1;
                    $this->payment_parameters['amount'] = $this->amount;
                    
                }	// End get_backend_common_parameter_array()

                /**
                 * process data before payport sever
                 */
                public function do_prepare_to_novalnet_payport($order) {
					
                    if (!isset($_SESSION['novalnet_receipt_page_got'])) {
						
                        echo '<p>' . __('Thank you for your order, please click the button below to pay with Novalnet.', 'woocommerce-novalnetpayment') . '</p>';
                        echo $this->get_novalnet_form_html($order);
                        $_SESSION['novalnet_receipt_page_got'] = 1;
                    
                    } #Endif
                    
                }	// End do_prepare_to_novalnet_payport()

                /**
                 * display error and message
                 */ 
                 public function do_check_and_add_novalnet_errors_and_messages($message, $message_type = 'error') {
					 
                    global $woocommerce;
                    
                    switch ($message_type) {
                        case 'error':
                            if (is_object($woocommerce->session))
                                $woocommerce->session->errors = $message;
                            else
                                $_SESSION['errors'][] = $message;
                            $woocommerce->add_error($message);
                            break;
                        case 'message':
                            if (is_object($woocommerce->session))
                                $woocommerce->session->messages = $message;
                            else
                                $_SESSION['messages'][] = $message;
                            $woocommerce->add_message($message);
                            break;
                    } #Endswitchcase
                    
                }	// End do_check_and_add_novalnet_errors_and_messages()

                /**
                 * Validate credit card form fields
                 */
                public function do_validate_cc_form_elements($cc_holder, $cc_number, $exp_month, $exp_year, $cvv_cvc, $cc_type = null, $unique_id = null, $pan_hash = null) {
					
                    global $woocommerce;
                    $error = '';
                    
                    if ($this->novalnet_payment_method == 'novalnet_cc') {
						
                        if ($cc_holder == '' || $this->is_invalid_holder_name($cc_holder) || (($exp_month == '' || $exp_year == date('Y')) && $exp_month < date('m')) || $exp_year == '' || $exp_year < date('Y') || $cvv_cvc == '' || strlen($cvv_cvc) < 3 || strlen($cvv_cvc) > 4 || !$this->is_digits($cvv_cvc) || $pan_hash == '' || $unique_id == '')
                            $error = true;

                        if (!$cc_type)
                            $error = true;                                 
                    }	#Endif
                    
                    elseif ($this->novalnet_payment_method == 'novalnet_cc3d') {
						
                        if ($cc_holder == '' || $this->is_invalid_holder_name($cc_holder) || $cc_number == '' || strlen($cc_number) < 12 || !$this->is_digits($cc_number) || (($exp_month == '' || $exp_year == date('Y')) && $exp_month < date('m')) || $exp_year == '' || $exp_year < date('Y') || $cvv_cvc == '' || strlen($cvv_cvc) < 3 || strlen($cvv_cvc) > 4 || !$this->is_digits($cvv_cvc))
                            $error = true;
                            
                    } #Endelseif
                    
                    if ($error) {
                     
                        $this->do_check_and_add_novalnet_errors_and_messages(__('Please enter valid credit card details!', 'woocommerce-novalnetpayment'), 'error');
                        return($this->do_return_redirect_page_for_pay_or_thanks_page('success', $woocommerce->cart->get_checkout_url()));
                    
                    }	#Endif 
                    
                    else {
                    
                        $_SESSION['cc_holder'] = $cc_holder;
                        $_SESSION['cc_number'] = $cc_number;
                        $_SESSION['exp_month'] = $exp_month;
                        $_SESSION['exp_year'] = $exp_year;
                        $_SESSION['cvv_cvc'] = $cvv_cvc;
                        
                        if ($this->novalnet_payment_method == 'novalnet_cc') {
                            $_SESSION['nn_unique_id'] = $unique_id;
                            $_SESSION['nn_cardno_id'] = $pan_hash;
                        } #Endif
                        
                        return('');
                        
                    }	#Endelse
                    
                }	// End do_validate_cc_form_elements()

                /**
                 * validate Direct Debit form fields
                 */
                public function do_validate_elv_at_elv_de_form_elements($bank_account_holder, $bank_account, $bank_code, $acdc = '') {
                    global $woocommerce;
                    
                    $error = '';
                    
                    if ($bank_account_holder == '' || $this->is_invalid_holder_name($bank_account_holder) || $bank_account == '' || strlen($bank_account) < 5 || !$this->is_digits($bank_account) || $bank_code == '' || strlen($bank_code) < 3 || !$this->is_digits($bank_code))
                        $error = __('Please enter valid account details!', 'woocommerce-novalnetpayment');
                    
                    elseif ($this->novalnet_payment_method == 'novalnet_elv_de' && $this->acdc == 'yes' && $acdc == '')
                        $error = __('Please enable credit rating check', 'woocommerce-novalnetpayment');
                        
                    if ($error) {
                        $this->do_check_and_add_novalnet_errors_and_messages($error, 'error');
                        return($this->do_return_redirect_page_for_pay_or_thanks_page('success', $woocommerce->cart->get_checkout_url()));
                    }	#Endif
                     
                    else {
                        
                        $_SESSION['bank_account_holder'] = $bank_account_holder;
                        $_SESSION['bank_account'] = $bank_account;
                        $_SESSION['bank_code'] = $bank_code;
                        
                        if (isset($acdc))
                            $_SESSION['acdc'] = $acdc;
                        
                        return('');
                        
                    }	#Endelse
                    
                }	// End do_validate_elv_at_elv_de_form_elements()

                /**
                 * process novalnet payment methods
                 */
                public function do_process_payment_from_novalnet_payments($order_id) {
                    $order = new WC_Order($order_id);
                    
                    if ($this->novalnet_payment_method == 'novalnet_tel') {
                    
                        $this->do_necessary_actions_before_prepare_to_novalnet_payport_or_paygate($order);
                        $return = $this->do_validate_amount_variations();
                    
                        if ($return)
                            return($return);
                    
                        if (empty($_SESSION['novalnet_tel_tid'])) {
                            $return = $this->do_validate_amount();
                    
                            if ($return)
                                return($return);
                    
                            $aryResponse = $this->do_prepare_to_novalnet_paygate($order);

                            return($this->do_check_novalnet_tel_payment_status($aryResponse, $order));
                    
                        }	#Endif 
                        
                        else
                            return($this->do_make_second_call_for_novalnet_telephone($order_id));
                    }	#Endif 
                    
                    elseif (in_array($this->novalnet_payment_method, $this->return_url_parameter_for_array)) {
                        
                        if ($this->novalnet_payment_method == 'novalnet_cc3d') {
                            
                            $return = $this->do_validate_cc_form_elements(trim($_REQUEST['cc3d_holder'], '&'), str_replace(' ', '', $_REQUEST['cc3d_number']), $_REQUEST['cc3d_exp_month'], $_REQUEST['cc3d_exp_year'], str_replace(' ', '', $_REQUEST['cvv_cvc']));
                            
                            if ($return)
                                return($return);
                                
                        } #Endif
                        
                        return($this->do_return_redirect_page_for_pay_or_thanks_page('success', $this->do_build_redirect_url($order, 'pay')));
                        
                    }	#Endelseif
                     
                    else {
						
                        if ($this->novalnet_payment_method == 'novalnet_cc') {
							
                            $return = $this->do_validate_cc_form_elements(trim($_REQUEST['cc_holder'], '&'), null, $_REQUEST['cc_exp_month'], $_REQUEST['cc_exp_year'], str_replace(' ', '', $_REQUEST['cc_cvv_cvc']), $_REQUEST['cc_type'], $_REQUEST['nn_unique_id'], $_REQUEST['nn_cardno_id']);
                            
                            if ($return)
                                return($return);
                                
                        }	#Endif
                        
                        elseif ($this->novalnet_payment_method == 'novalnet_elv_at') {
							
                            $return = $this->do_validate_elv_at_elv_de_form_elements(trim($_REQUEST['bank_account_holder_at'], '&'), str_replace(' ', '', $_REQUEST['bank_account_at']), str_replace(' ', '', $_REQUEST['bank_code_at']));
                            
                            if ($return)
                                return($return);
                                
                        }	#Endelseif 
                        
                        elseif ($this->novalnet_payment_method == 'novalnet_elv_de') {
							
                            $return = $this->do_validate_elv_at_elv_de_form_elements(trim($_REQUEST['bank_account_holder_de'], '&'), str_replace(' ', '', $_REQUEST['bank_account_de']), str_replace(' ', '', $_REQUEST['bank_code_de']), isset($_REQUEST['acdc']) ? $_REQUEST['acdc'] : null);
                            
                            if ($return)
                                return($return);
                                
                        }	#Endelseif
                        
                        $this->do_necessary_actions_before_prepare_to_novalnet_payport_or_paygate($order);
                        $aryResponse = $this->do_prepare_to_novalnet_paygate($order);
                        return($this->do_check_novalnet_status($aryResponse));
                        
                    }	#Endelse
                    
                }	// End do_process_payment_from_novalnet_payments()

                /**
                 * get url for direct form payment methods
                 */
                public function do_return_redirect_page_for_pay_or_thanks_page($result, $redirect_url) {
					
                    return array(
                        'result' => $result,
                        'redirect' => $redirect_url
                    );
                }	// End do_return_redirect_page_for_pay_or_thanks_page()

                /**
                 * Validate back-end data
                 */
                public function do_check_novalnet_backend_data_validation_from_frontend() {
                    
                    global $woocommerce;
                    $error = '';
                    
                    if (!$this->vendor_id || !$this->product_id || !$this->tariff_id || !$this->auth_code || (isset($this->key_password) && !$this->key_password) || (isset($this->api_username) && !$this->api_username) || (isset($this->api_password) && !$this->api_password) || (isset($this->api_signature) && !$this->api_signature))
                        $error = __('Basic Parameter Missing', 'woocommerce-novalnetpayment');
                    
                    if (isset($this->manual_check_limit) && $this->manual_check_limit > 0) {
						
                        if (empty($this->product_id_2) || empty($this->tariff_id_2))
                            $error = __('Product-ID2 and/or Tariff-ID2 missing!', 'woocommerce-novalnetpayment');
                            
                    }	#Endif
                    
                    if ($error) {
                        
                        $this->do_check_and_add_novalnet_errors_and_messages($error, 'error');
                        wp_safe_redirect($woocommerce->cart->get_checkout_url());
                        exit();
                        
                    }	#Endif
                    
                }	// End do_check_novalnet_backend_data_validation_from_frontend()

                /**
                 * build redirect url for direct form payment methods
                 */
                public function do_build_redirect_url($order, $page) {
					
                    return(add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, get_permalink(woocommerce_get_page_id($page)))));
                }	
                // End do_build_redirect_url()

                /**
                 * Get pci compliant secure credit card form from Novalnet server 
                 */
                public function do_check_is_any_request_to_print_cc_iframe() {
                    
                    if ($this->novalnet_payment_method == 'novalnet_cc' && isset($_SERVER['HTTP_REFERER']) && !strstr($_SERVER['HTTP_REFERER'], 'wp-admin')) {
						
                        $this->payport_or_paygate_form_display = $this->payment_details['novalnet_cc']['payport_or_paygate_form_display'];
                        
                        $form_parameters = array(
                            'nn_lang_nn' => strtoupper($this->language),
                            'nn_vendor_id_nn' => $this->vendor_id,
                            'nn_product_id_nn' => $this->product_id,
                            'nn_payment_id_nn' => $this->payment_key
                        );
                        
                        list($errno, $errmsg, $data) = $this->perform_https_request($this->payport_or_paygate_form_display, $form_parameters);
                        
                        file_put_contents(ABSPATH . '/wp-content/plugins/woocommerce-novalnet-gateway/includes/novalnet_cc_iframe.html', $data);
                        
                    } #Endif
                    
                }	// End do_check_is_any_request_to_print_cc_iframe()

                /**
                 * Display direct debit form fields
                 */
                public function do_print_form_elements_for_novalnet_elv_de_at($suffix) {
					
                    $payment_field_html = '<div>&nbsp;</div><div>
            <div style="float:left;width:50%;">' . __('Account holder', 'woocommerce-novalnetpayment') . ':<span style="color:red;">*</span></div>
            <div style="float:left;width:50%;"><input type="text" name="bank_account_holder_' . $suffix . '" id="bank_account_holder_' . $suffix . '" value="" autocomplete="off" /></div>
            <div style="clear:both;">&nbsp;</div>
            <div style="float:left;width:50%;">' . __('Account number', 'woocommerce-novalnetpayment') . ':<span style="color:red;">*</span></div>
            <div style="float:left;width:50%;"><input type="text" name="bank_account_' . $suffix . '" id="bank_account_' . $suffix . '" value="" autocomplete="off" /></div>
            <div style="clear:both;">&nbsp;</div>
            <div style="float:left;width:50%;">' . __('Bankcode', 'woocommerce-novalnetpayment') . ':<span style="color:red;">*</span></div>
            <div style="float:left;width:50%;"><input type="text" name="bank_code_' . $suffix . '" id="bank_code_' . $suffix . '" value="" autocomplete="off" /></div>';
            
                    if ($suffix == 'de' && $this->acdc == 'yes') {
                        $payment_field_html.='
            <div style="clear:both;">&nbsp;</div>
            <div style="float:left;width:50%;"><a id="acdc_link" href="javascript:show_acdc_info();" onclick="show_acdc_info();">' . __('Your credit rating is checked by us', 'woocommerce-novalnetpayment') . '</a>:<span style="color:red;">*</span></div>
            <div style="float:left;width:50%;"><input type="checkbox" name="acdc" id="acdc" class="inputbox" value="1" /></div>
            <script type="text/javascript" language="javascript">
            function show_acdc_info(){
            urlpopup="' . (is_ssl() ? 'https://www.novalnet.de/img/acdc_info.png' : 'http://www.novalnet.de/img/acdc_info.png') . '";
            w="550";h="300";
            x=250;y=100; 
            //x=screen.availWidth/2-w/2;y=screen.availHeight/2-h/2; 
            showbaby=window.open(urlpopup,"showbaby","toolbar=0,location=0,directories=0,status=0,menubar=0,resizable=1,width="+w+",height="+h+",left="+x+",top="+y+",screenX="+x+",screenY="+y);
            showbaby.focus(); 
            }
            </script>
            ';
                    }	#Endif
                    
                    $payment_field_html.='<div style="clear:both;">&nbsp;</div></div>';
                    
                    return($payment_field_html);
                    
                }	// End do_print_form_elements_for_novalnet_elv_de_at()

                /**
                 * validate novalnet configuration parameter
                 */
                public function novalnet_backend_validation_from_backend($request) {

                    /* Get woocommerce Novalnet configuration settings	 */
                    $vendor_id = $request['woocommerce_' . $this->novalnet_payment_method . '_merchant_id'];
                    $auth_code = $request['woocommerce_' . $this->novalnet_payment_method . '_auth_code'];
                    $product_id = $request['woocommerce_' . $this->novalnet_payment_method . '_product_id'];
                    $tariff_id = $request['woocommerce_' . $this->novalnet_payment_method . '_tariff_id'];
                    $payment_duration = isset($request['woocommerce_' . $this->novalnet_payment_method . '_payment_duration']) ? $request['woocommerce_' . $this->novalnet_payment_method . '_payment_duration'] : null;
                    $key_password = isset($request['woocommerce_' . $this->novalnet_payment_method . '_key_password']) ? $request['woocommerce_' . $this->novalnet_payment_method . '_key_password'] : null;
                    $api_username = isset($request['woocommerce_' . $this->novalnet_payment_method . '_api_username']) ? $request['woocommerce_' . $this->novalnet_payment_method . '_api_username'] : null;
                    $api_password = isset($request['woocommerce_' . $this->novalnet_payment_method . '_api_password']) ? $request['woocommerce_' . $this->novalnet_payment_method . '_api_password'] : null;
                    $api_signature = isset($request['woocommerce_' . $this->novalnet_payment_method . '_api_signature']) ? $request['woocommerce_' . $this->novalnet_payment_method . '_api_signature'] : null;
                    $manual_check_limit = isset($request['woocommerce_' . $this->novalnet_payment_method . '_manual_check_limit']) ? $request['woocommerce_' . $this->novalnet_payment_method . '_manual_check_limit'] : null;
                    $product_id_2 = isset($request['woocommerce_' . $this->novalnet_payment_method . '_product_id_2']) ? $request['woocommerce_' . $this->novalnet_payment_method . '_product_id_2'] : null;
                    $tariff_id_2 = isset($request['woocommerce_' . $this->novalnet_payment_method . '_tariff_id_2']) ? $request['woocommerce_' . $this->novalnet_payment_method . '_tariff_id_2'] : null;

                    /* 	woocommerce Novalnet configuration validation	 */
                    foreach ($this->language_supported_array as $language) {
						
                        if (!$request['woocommerce_' . $this->novalnet_payment_method . '_title_' . $language])
                            return(__('Please enter valid Payment Title', 'woocommerce-novalnetpayment'));
                            
                    } #Endforeach
                    
                    if (isset($vendor_id) && !$vendor_id)
                        return(__('Please enter valid Novalnet Merchant ID', 'woocommerce-novalnetpayment'));
                   
                    if (isset($auth_code) && !$auth_code)
                        return(__('Please enter valid Novalnet Merchant Authorisation code', 'woocommerce-novalnetpayment'));
                   
                    if (isset($product_id) && (!$product_id || !$this->is_digits($product_id)))
                        return(__('Please enter valid Novalnet Product ID', 'woocommerce-novalnetpayment'));
                   
                    if (isset($tariff_id) && (!$tariff_id || !$this->is_digits($tariff_id)))
                        return(__('Please enter valid Novalnet Tariff ID', 'woocommerce-novalnetpayment'));
                   
                    if (isset($payment_duration) && $payment_duration && !$this->is_digits($payment_duration))
                        return(__('Please enter valid Payment period in days', 'woocommerce-novalnetpayment'));
                   
                    if (isset($key_password) && !$key_password)
                        return(__('Please enter valid Novalnet Payment access key', 'woocommerce-novalnetpayment'));
                   
                    if (isset($api_username) && !$api_username)
                        return(__('Please enter valid PayPal API username', 'woocommerce-novalnetpayment'));
                   
                    if (isset($api_password) && !$api_password)
                        return(__('Please enter valid PayPal API password', 'woocommerce-novalnetpayment'));
                   
                    if (isset($api_signature) && !$api_signature)
                        return(__('Please enter valid PayPal API signature', 'woocommerce-novalnetpayment'));
                   
                    if (isset($manual_check_limit) && $manual_check_limit && !$this->is_digits($manual_check_limit))
                        return(__('Please enter valid Manual checking amount', 'woocommerce-novalnetpayment'));
                   
                    if (isset($manual_check_limit) && $manual_check_limit && $this->is_digits($manual_check_limit)) {
                   
                        if (isset($product_id_2) && (!$product_id_2 || !$this->is_digits($product_id_2)))
                            return(__('Please enter valid Novalnet Second Product ID', 'woocommerce-novalnetpayment'));
                        
                        if (isset($tariff_id_2) && (!$tariff_id_2 || !$this->is_digits($tariff_id_2)))
                            return(__('Please enter valid Novalnet Second Tariff ID', 'woocommerce-novalnetpayment'));
                        
                    }	#Endif 
                    
					if (isset($tariff_id_2) && $tariff_id_2 && !$this->is_digits($tariff_id_2))
                            return(__('Please enter valid Novalnet Second Tariff ID', 'woocommerce-novalnetpayment'));
                      
                    if (isset($product_id_2) && $product_id_2 && !$this->is_digits($product_id_2))
                            return(__('Please enter valid Novalnet Second Product ID', 'woocommerce-novalnetpayment'));
                    return('');
                    
                }	// End novalnet_backend_validation_from_backend()

                /**
                 * Validate payment gateway settings
                 */
                public function do_check_novalnet_backend_data_validation_from_backend($request) {
					
                    if (isset($request['save']) && isset($request['subtab']) && ($request['subtab'] == '#gateway-' . $this->novalnet_payment_method || isset($request['section']) && $request['section'] == $this->novalnet_payment_method)) {
						
                        $is_backend_error = $this->novalnet_backend_validation_from_backend($request);
                        
                        if ($is_backend_error) {
							
                            $redirect = get_admin_url() . 'admin.php?' . http_build_query($_GET);
                            $redirect = remove_query_arg('saved');
                            $redirect = add_query_arg('wc_error', urlencode(esc_attr($is_backend_error)), $redirect);
                            
                            if (!empty($request['subtab']))
                                $redirect = add_query_arg('subtab', esc_attr(str_replace('#', '', $request['subtab'])), $redirect);
                            wp_safe_redirect($redirect);
                            exit();
                            
                        }	#Endif
                        
                    }	#Endif 
                    
                    elseif (isset($request['saved']) && isset($_GET['wc_error'])) {
						
                        $redirect = get_admin_url() . 'admin.php?' . http_build_query($_GET);
                        $redirect = remove_query_arg('wc_error');
                        $redirect = add_query_arg('saved', urlencode(esc_attr('true')), $redirect);
                        wp_safe_redirect($redirect);
                        exit();
                        
                    }	#Endelseif
                    
                }	// End do_check_novalnet_backend_data_validation_from_backend()

                /**
                 * Initialize language for payment methods
                 */
                public function do_initialize_novalnet_language() {
					
                    $language_locale = get_bloginfo('language');
                    $this->language = strtoupper(substr($language_locale, 0, 2)) ? strtoupper(substr($language_locale, 0, 2)) : 'en';
                    $this->language = in_array(strtolower($this->language), $this->language_supported_array) ? $this->language : 'en';
                
                }	// End do_initialize_novalnet_language()

                /**
                 * trim server resonse
                 */
                public function do_trim_array_values(&$array) {
					
                    if (isset($array) && is_array($array))
                        
                        foreach ($array as $key => $val) {
                            if (!is_array($val))
                                $array[$key] = trim($val);
                        } #Endforeach
                        
                }	// End do_trim_array_values()

                /**
                 * set-up configuration details  for payment methods
                 */
                public function do_make_payment_details_array() {
					
                    $this->payment_details = array(
                    
                        /* 	Novalnet BankTransfer Payment Method	 */
                        'novalnet_banktransfer' => array(
                            'payment_key' => $this->payment_key_for_online_transfer,
                            'payport_or_paygate_url' => $this->novalnet_online_transfer_payport_url,
                            'second_call_url' => '',
                            'payment_name' => __('Instant Bank Transfer', 'woocommerce-novalnetpayment'),
                            'payment_logo' => __('www.novalnet.de/img/sofort_Logo_en.png', 'woocommerce-novalnetpayment')
                        ),
                        
                        /* 	Novalnet Credit Card Payment Method	 */
                        'novalnet_cc' => array(
                            'payment_key' => $this->payment_key_for_cc_family,
                            'payport_or_paygate_url' => $this->novalnet_paygate_url,
                            'payport_or_paygate_form_display' => $this->novalnet_cc_form_display_url,
                            'second_call_url' => '',
                            'payment_name' => __('Credit Card', 'woocommerce-novalnetpayment'),
                            'payment_logo' => __('www.novalnet.de/img/creditcard_small.jpg', 'woocommerce-novalnetpayment')
                        ),
                       
                        /* 	Novalnet Credit Card 3D Secure Payment Method	 */
                        'novalnet_cc3d' => array(
                            'payment_key' => $this->payment_key_for_cc_family,
                            'payport_or_paygate_url' => $this->novlanet_cc3d_payport_url,
                            'second_call_url' => '',
                            'payment_name' => __('Credit Card 3D Secure', 'woocommerce-novalnetpayment'),
                            'payment_logo' => __('www.novalnet.de/img/creditcard_small.jpg', 'woocommerce-novalnetpayment')
                        ),
                        
                        /* 	Novalnet Direct Debit Austria Payment Method	 */
                        'novalnet_elv_at' => array(
                            'payment_key' => $this->payment_key_for_at_family,
                            'payport_or_paygate_url' => $this->novalnet_paygate_url,
                            'second_call_url' => '',
                            'payment_name' => __('Direct Debit Austria', 'woocommerce-novalnetpayment'),
                            'payment_logo' => __('www.novalnet.de/img/ELV_Logo.png', 'woocommerce-novalnetpayment')
                        ),
                        
                        /* 	Novalnet Direct Debit German Payment Method	 */
                        'novalnet_elv_de' => array(
                            'payment_key' => $this->payment_key_for_de_family,
                            'payport_or_paygate_url' => $this->novalnet_paygate_url,
                            'second_call_url' => '',
                            'payment_name' => __('Direct Debit German', 'woocommerce-novalnetpayment'),
                            'payment_logo' => __('www.novalnet.de/img/ELV_Logo.png', 'woocommerce-novalnetpayment')
                        ),
                        
                        /* 	Novalnet iDEAL Payment Method	 */
                        'novalnet_ideal' => array(
                            'payment_key' => $this->payment_key_for_ideal,
                            'payport_or_paygate_url' => $this->novalnet_online_transfer_payport_url,
                            'second_call_url' => '',
                            'payment_name' => __('iDEAL', 'woocommerce-novalnetpayment'),
                            'payment_logo' => __('www.novalnet.de/img/ideal_payment_small.png', 'woocommerce-novalnetpayment')
                        ),
                        
                        /* 	Novalnet Invoice Payment Method	 */
                        'novalnet_invoice' => array(
                            'payment_key' => $this->payment_key_for_invoice_prepayment,
                            'payport_or_paygate_url' => $this->novalnet_paygate_url,
                            'second_call_url' => '',
                            'payment_name' => __('Invoice', 'woocommerce-novalnetpayment'),
                            'payment_logo' => __('www.novalnet.de/img/kauf-auf-rechnung.jpg', 'woocommerce-novalnetpayment')
                        ),
                        
                        /* 	Novalnet PayPal Payment Method	 */
                        'novalnet_paypal' => array(
                            'payment_key' => $this->payment_key_for_paypal,
                            'payport_or_paygate_url' => $this->novlanet_paypal_payport_url,
                            'second_call_url' => '',
                            'payment_name' => __('PayPal', 'woocommerce-novalnetpayment'),
                            'payment_logo' => __('www.novalnet.de/img/paypal-small.png', 'woocommerce-novalnetpayment')
                        ),
                        
                        /* 	Novalnet Prepayment Payment Method	 */
                        'novalnet_prepayment' => array(
                            'payment_key' => $this->payment_key_for_invoice_prepayment,
                            'payport_or_paygate_url' => $this->novalnet_paygate_url,
                            'second_call_url' => '',
                            'payment_name' => __('Prepayment', 'woocommerce-novalnetpayment'),
                            'payment_logo' => __('www.novalnet.de/img/vorauskasse.jpg', 'woocommerce-novalnetpayment')
                        ),
                        
                        /* 	Novalnet Telephone Payment Method	 */
                        'novalnet_tel' => array(
                            'payment_key' => $this->payment_key_for_tel,
                            'payport_or_paygate_url' => $this->novalnet_paygate_url,
                            'second_call_url' => $this->novlanet_tel_second_call_url,
                            'payment_name' => __('Telephone Payment', 'woocommerce-novalnetpayment'),
                            'payment_logo' => __('www.novalnet.de/img/novaltel_logo.png', 'woocommerce-novalnetpayment')
                        )
                    );
                    
                }	// End do_make_payment_details_array()

                /**
                 * Assign variables to payment parameters
                 */
                public function do_assign_config_vars_to_members() {
					
					// trim settigns array
                    $this->do_trim_array_values($this->settings);
                    
                    $this->do_make_payment_details_array();

                    /* assign basic configuration parameters */
                    $this->test_mode = $this->settings['test_mode'];
                    $this->vendor_id = $this->settings['merchant_id'];
                    $this->auth_code = $this->settings['auth_code'];
                    $this->product_id = $this->settings['product_id'];
                    $this->tariff_id = $this->settings['tariff_id'];
                    $this->payment_key = $this->payment_details[$this->novalnet_payment_method]['payment_key'];

                    /* assign payment url for each payment methods */
                    $this->payport_or_paygate_url = $this->payment_details[$this->novalnet_payment_method]['payport_or_paygate_url'];
                    $this->second_call_url = $this->payment_details[$this->novalnet_payment_method]['second_call_url'];

                    /* assign additional configuration parameters */
                    if (isset($this->settings['key_password']) && $this->settings['key_password'])
                        $this->key_password = $this->settings['key_password'];
                   
                    if (isset($this->settings['acdc']) && $this->settings['acdc'])
                        $this->acdc = $this->settings['acdc'];
                   
                    if (isset($this->settings['payment_duration']))
                        $this->payment_duration = $this->settings['payment_duration'];
                   
                    if (isset($this->settings['manual_check_limit']) && $this->settings['manual_check_limit'])
                        $this->manual_check_limit = str_replace(array(' ', ',', '.'), '', $this->settings['manual_check_limit']);
                   
                    if (isset($this->settings['product_id_2']) && $this->settings['product_id_2'])
                        $this->product_id_2 = $this->settings['product_id_2'];
                   
                    if (isset($this->settings['tariff_id_2']) && $this->settings['tariff_id_2'])
                        $this->tariff_id_2 = $this->settings['tariff_id_2'];
                   
                    if (isset($this->settings['api_username']) && $this->settings['api_username'])
                        $this->api_username = $this->settings['api_username'];
                   
                    if (isset($this->settings['api_password']) && $this->settings['api_password'])
                        $this->api_password = $this->settings['api_password'];
                   
                    if (isset($this->settings['api_signature']) && $this->settings['api_signature'])
                        $this->api_signature = $this->settings['api_signature'];
                    
                    $this->unique_id = uniqid();
                    $this->method_title = $this->payment_details[$this->novalnet_payment_method]['payment_name'];
                    $this->title = $this->settings['title_' . strtolower($this->language)];
                    $this->description = $this->settings['description_' . strtolower($this->language)];
                    $this->payment_logo = $this->settings['payment_logo'];
                    $this->icon = (is_ssl() ? 'https://' : 'http://') . $this->payment_details[$this->novalnet_payment_method]['payment_logo'];
                    
                }	// End do_assign_config_vars_to_members()

                /**
                 * Validate account digits
                 */
                public function is_digits($element) {
					
                    return(preg_match("/^[0-9]+$/", $element));
                }	// End is_digits()

                /**
                 * Validate account holder name
                 */
                public function is_invalid_holder_name($element) {
					
                    return preg_match("/[#%\^<>@$=*!]/", $element);
                }	// End is_invalid_holder_name()

                /**
                 * Format amount in cents
                 */
                public function do_format_amount($amount) {
					
                    $this->amount = str_replace(',', '', number_format($amount, 2)) * 100;
                }	// End do_format_amount()

                /**
                 * Assign Manual Check-Limit 
                 */
                public function do_check_and_assign_manual_check_limit() {
					
                    if (isset($this->manual_check_limit) && $this->manual_check_limit && $this->amount >= $this->manual_check_limit) {
                        
                        if ($this->product_id_2 && $this->tariff_id_2) {
                            $this->product_id = $this->product_id_2;
                            $this->tariff_id = $this->tariff_id_2;
                        }	#Endif
                        
                    }	#Endif
                }	// End do_check_and_assign_manual_check_limit()

                /**
                 * Get Server Response message
                 */
                public function do_get_novalnet_response_text($request) {
					
                    return(isset($request['status_text']) ? $request['status_text'] : (isset($request['status_desc']) ? $request['status_desc'] : __('Successful', 'woocommerce-novalnetpayment')));
                    
                }	// End do_get_novalnet_response_text()

                /**
                 * Successful payment
                 */
                public function do_novalnet_success($request, $message) {
					
                    global $woocommerce;
                    
                     // trim request array
                    $this->do_trim_array_values($request);	
				
                    $order_no = $request['inputval1'];  
                    $woo_seq_nr = $request['order_no'];

					if (in_array($this->novalnet_payment_method, $this->encode_applicable_for_array))
							$request['test_mode'] = $this->decode($request['test_mode']);						
						
					if($this->novalnet_payment_method == 'novalnet_cc3d') {
						$this->amount = $request['amount'];
						$this->do_check_and_assign_manual_check_limit();								
					}	#Endif
                    
                    $order = new WC_Order($order_no);

                    /* add Novalnet Transaction details to order notes */
                    $new_line = "\n";
                    $novalnet_comments = $new_line . $this->title . $new_line;
                    $novalnet_comments .= __('Novalnet Transaction ID', 'woocommerce-novalnetpayment') . ': ' . $request['tid'] . $new_line;
                    $novalnet_comments .= ((isset($request['test_mode']) && $request['test_mode'] == 1) || (isset($this->test_mode) && $this->test_mode == 1)) ? __('Test order', 'woocommerce-novalnetpayment') : '';
                    
                    /*	add additional information for Prepayment and Invoice order	*/
                    if ($this->novalnet_payment_method == 'novalnet_invoice' || $this->novalnet_payment_method == 'novalnet_prepayment') {
                    
                        $novalnet_comments .= $request['test_mode'] ? $new_line . $new_line : $new_line;
                        $novalnet_comments .= __('Please transfer the amount to the following information to our payment service Novalnet AG', 'woocommerce-novalnetpayment') . $new_line;
                        if ($this->novalnet_payment_method == 'novalnet_invoice' && is_numeric($this->payment_duration))
                            $novalnet_comments.= __('Due date', 'woocommerce-novalnetpayment') . " : " . date_i18n(get_option('date_format'), strtotime($this->due_date)) . $new_line;                             
                        $novalnet_comments.= __('Account holder : Novalnet AG', 'woocommerce-novalnetpayment') . $new_line;
                        $novalnet_comments.= __('Account number', 'woocommerce-novalnetpayment') . " : " . $request['invoice_account'] . $new_line;
                        $novalnet_comments.= __('Bankcode', 'woocommerce-novalnetpayment') . " : " . $request['invoice_bankcode'] . $new_line;
                        $novalnet_comments.= __('Bank', 'woocommerce-novalnetpayment') . " : " . $request['invoice_bankname'] . " " . trim($request['invoice_bankplace']) . $new_line;
                        $novalnet_comments.= __('Amount', 'woocommerce-novalnetpayment') . " : " . strip_tags($order->get_formatted_order_total()) . $new_line;
                        $novalnet_comments.= __('Reference : TID', 'woocommerce-novalnetpayment') . " " . $request['tid'] . $new_line . $new_line;
                        $novalnet_comments.= __('Only for international transfers :', 'woocommerce-novalnetpayment') . $new_line;
                        $novalnet_comments.= __('IBAN', 'woocommerce-novalnetpayment') . " : " . $request['invoice_iban'] . $new_line;
                        $novalnet_comments.= __('SWIFT / BIC', 'woocommerce-novalnetpayment') . " : " . $request['invoice_bic'] . $new_line;
                        
                    }	#Endif
                    
                    if ($order->customer_note)
                        $order->customer_note.= $new_line;
                    
                    if ($this->novalnet_payment_method == 'novalnet_invoice' || $this->novalnet_payment_method == 'novalnet_prepayment') {
						
                        $order->customer_note .= html_entity_decode($novalnet_comments, ENT_QUOTES, 'UTF-8');
                        
                        if (version_compare($woocommerce->version, '2.0.0', '<'))
                            $order->customer_note = utf8_encode($order->customer_note);
                            
                    }	#Endif
                    
                    else
                        $order->customer_note .= html_entity_decode($novalnet_comments, ENT_QUOTES, 'UTF-8');

                    /** Update Novalnet Transaction details into shop database	 */
                    $nn_order_notes = array(
                        'ID' => $order_no,
                        'post_excerpt' => $order->customer_note
                    );
                    wp_update_post($nn_order_notes);

                    // adds order note
                    $order->add_order_note($order->customer_note);
                    
                    if (isset($request['novalnet_payment_method']) && isset($request['status']) && $request['novalnet_payment_method'] == 'novalnet_paypal' && $request['status'] == 90) {

                        // Empty awaiting payment session
                        if (!empty($woocommerce->session->order_awaiting_payment))
                            unset($woocommerce->session->order_awaiting_payment);
                        
                        $nn_order_status = $this->order_status;
                        
                        apply_filters('woocommerce_payment_complete_order_status', $nn_order_status, $order_no);

                        // Update order status
                        $order->update_status($nn_order_status, $message);
                        
                        add_post_meta($order_no, '_paid_date', current_time('mysql'), true);
                        
                        $nn_order = array(
                            'ID' => $order_no,
                            'post_date' => current_time('mysql', 0),
                            'post_date_gmt' => current_time('mysql', 1)
                        );
                        wp_update_post($nn_order);
                        
                        if (apply_filters('woocommerce_payment_complete_reduce_order_stock', true, $order_no))

                        // Reduce stock levels
                            $order->reduce_order_stock();
                        
                        do_action('woocommerce_payment_complete', $order_no);
                        
                    }	#Endif
                    
                    else						
                        // make the payment complete
                        $order->payment_complete();
                        
                    // Remove cart
                    $woocommerce->cart->empty_cart();

                    // send acknoweldgement call to Novalnet server
                    $this->post_back_param($request, $woo_seq_nr);
					
					// successful message display
                    $this->do_check_and_add_novalnet_errors_and_messages($message, 'message');

                    // Clears the Novalnet Telephone payment session
                    $this->do_unset_novalnet_telephone_sessions();

                    //	Return thankyou redirect
                    if (in_array($this->novalnet_payment_method, $this->return_url_parameter_for_array)) {
						
                        wp_safe_redirect(add_query_arg('key', $order->order_key, add_query_arg('order', $order_no, get_permalink(woocommerce_get_page_id('thanks')))));
                        exit();
                        
                    }	#Endif 
                    
                    else
                        return($this->do_return_redirect_page_for_pay_or_thanks_page('success', $this->do_build_redirect_url($order, 'thanks')));
                        
                }	// End do_novalnet_success()
                
                /**
                 * Order Cancellation
                 */
                  public function do_novalnet_cancel($request, $message) {
					  
                    global $woocommerce;
                 	
                    // trim request array
                    $this->do_trim_array_values($request);
					
                    $order_no = $request['inputval1'];    
                    $order = new WC_Order($order_no);
                    
                    $new_line = "\n";
                    $novalnet_comments = $this->title . $new_line;
                    $novalnet_comments .= $message . $new_line;
                    
                    if ($order->customer_note)
                        $order->customer_note .= $new_line;
                    
                    $order->customer_note .= html_entity_decode($novalnet_comments, ENT_QUOTES, 'UTF-8');

                    /** Update order cancellation details into database	 */
                    $nn_order_notes = array(
                        'ID' => $order_no,
                        'post_excerpt' => $order->customer_note
                    );
                    wp_update_post($nn_order_notes);

                    // adds order note
                    $order->add_order_note($order->customer_note);
                    
                    //	Cancel the order
                    $order->cancel_order($message);

                    // Order cancellation message display
                    do_action('woocommerce_cancelled_order', $order_no);
                    $this->do_check_and_add_novalnet_errors_and_messages($message, 'error');
                    
                    // clears telephone payment session
                    $this->do_unset_novalnet_telephone_sessions();
                    
                    if (in_array($this->novalnet_payment_method, $this->return_url_parameter_for_array)) {
						wp_safe_redirect($woocommerce->cart->get_checkout_url());
						exit();
                    }	#Endif
                    else
						return($this->do_return_redirect_page_for_pay_or_thanks_page('success', $woocommerce->cart->get_checkout_url()));
						
                }	// End do_novalnet_cancel()

                /**
                 * Transfer data via curl library (consists of various protocols)
                 */
                public function perform_https_request($url, $form) {
					
                    global $globaldebug;
                    
                    if ($globaldebug)
                        print "<BR>perform_https_request: $url<BR>\n\r\n";
                    if ($globaldebug)
                        print "perform_https_request: $form<BR>\n\r\n";
                    
                    ## some prerquisites for the connection
                    $ch = curl_init($url);
                    
                    // a non-zero parameter tells the library to do a regular HTTP post.
                    curl_setopt($ch, CURLOPT_POST, 1);
                    
                    // add POST fields  
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $form);  
                    
                    // don't allow redirects
                    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
                    
                    // decomment it if you want to have effective ssl checking
                    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                    
                    // decomment it if you want to have effective ssl checking
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    
                    // return into a variable
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                    
                    // maximum time, in seconds, that you'll allow the CURL functions to take
                    curl_setopt($ch, CURLOPT_TIMEOUT, 240);
                    
                    // payment proxy
                    if (isset($this->payment_proxy))
                        curl_setopt($ch, CURLOPT_PROXY, $this->payment_proxy);
                    
                    ## establish connection
                    $data = curl_exec($ch);
                    
                    ## determine if there were some problems on cURL execution
                    $errno = curl_errno($ch);
                    $errmsg = curl_error($ch);
                    
                    ###bug fix for PHP 4.1.0/4.1.2 (curl_errno() returns high negative value in case of successful termination)
                    if ($errno < 0)
                        $errno = 0;
                    
                    ##bug fix for PHP 4.1.0/4.1.2
                    if ($globaldebug) {
                        print_r(curl_getinfo($ch));
                        echo "<BR><BR>\n\n\nperform_https_request: cURL error number:" . $errno . "<BR>\n";
                        echo "\n\n\nperform_https_request: cURL error:" . $errmsg . "<BR>\n";
                    } #Endif
                    
                    #close connection
                    curl_close($ch);
                   
                    if ($globaldebug)
                        print "<BR>\n" . $data;
                    
                    ## read and return data from novalnet paygate
                    return array($errno, $errmsg, $data);
                    
                }	// End perform_https_request()

                /**
                 * Generate Hash parameter value ($h contains encoded data)
                 */
                public function hash($h) {
					
                    if (!$h)
                        return'Error: no data';
                    if (!function_exists('md5')) {
                        return'Error: func n/a';
                    } #Endif
                    return md5($h['authcode'] . $h['product_id'] . $h['tariff'] . $h['amount'] . $h['test_mode'] . $h['uniqid'] . strrev($this->key_password));
                    
                }	// End hash()

                /**
                 * Validate Hash parameter
                 */
                public function checkHash(&$request) {
		
                $h['authcode'] = $request['auth_code']; 		#encoded
                $h['product_id'] = $request['product']; 		#encoded
                $h['tariff'] = $request['tariff']; 				#encoded              
                $h['amount'] = $request['amount']; 				#encoded
                $h['test_mode'] = $request['test_mode']; 		#encoded
                $h['uniqid'] = $request['uniqid']; 				#encoded
                   
                    if (!$request)
                        return false;					#'Error: no data
                   
                    if ($request['hash2'] != $this->hash($h))
                        return false;
                   
                    return true;
                    
                }	// End checkHash()

                /**
                 * Encode payment parameters
                 */
                public function encode($data) {
					
                    $data = trim($data);
                    
                    if ($data == '')
                        return'Error: no data';
                    
					if (!function_exists('base64_encode') or !function_exists('pack') or !function_exists('crc32'))
                        return'Error: func n/a';
                                        
                    try {
                        $crc = sprintf('%u', crc32($data)); # %u is a must for ccrc32 returns a signed value
                        $data = $crc . "|" . $data;
                        $data = bin2hex($data . $this->key_password);
                        $data = strrev(base64_encode($data));
                    } catch (Exception $e) {
                        echo('Error: ' . $e);
                    }
                    return $data;
                    
                }	// End encode()

                /**
                 * Decode payment parameters
                 */
                public function decode($data) {
					
                    $data = trim($data);
                    
                    if ($data == '')
                        return'Error: no data';
                        
                    if (!function_exists('base64_decode') or !function_exists('pack') or !function_exists('crc32'))
                        return'Error: func n/a';
                   
                    try {
                        $data = base64_decode(strrev($data));
                        $data = pack("H" . strlen($data), $data);
                        $data = substr($data, 0, stripos($data, $this->key_password));
                        $pos = strpos($data, "|");
                        
                        if ($pos === false)
                            return("Error: CKSum not found!");
                            
                        $crc = substr($data, 0, $pos);
                        $value = trim(substr($data, $pos + 1));
                        if ($crc != sprintf('%u', crc32($value)))
                            return("Error; CKSum invalid!");
                            
                        return $value;
                    } catch (Exception $e) {
                        echo('Error: ' . $e);
					}
                        
                }	// End decode()

                /**
                 * Validate current user's IP address
                 */
                public function isPublicIP($value) {
					
                    if (!$value || count(explode('.', $value)) != 4)
                        return false;
                    return !preg_match('~^((0|10|172\.16|192\.168|169\.254|255|127\.0)\.)~', $value);
                    
                }	// End isPublicIP()

                /**
                 * Get the real Ip Adress of the User
                 */
                public function getRealIpAddr() {
                    
                    if (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && $this->isPublicIP($_SERVER['HTTP_X_FORWARDED_FOR']))
                        return $_SERVER['HTTP_X_FORWARDED_FOR'];
                    
                    if (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && $iplist = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])) {
                       
                        if ($this->isPublicIP($iplist[0]))
                            return $iplist[0];
                            
                    }	#Endif
                    
                    if (isset($_SERVER['HTTP_CLIENT_IP']) && $this->isPublicIP($_SERVER['HTTP_CLIENT_IP']))
                        return $_SERVER['HTTP_CLIENT_IP'];
                    
                    if (isset($_SERVER['HTTP_X_CLUSTER_CLIENT_IP']) && $this->isPublicIP($_SERVER['HTTP_X_CLUSTER_CLIENT_IP']))
                        return $_SERVER['HTTP_X_CLUSTER_CLIENT_IP'];
                    
                    if (isset($_SERVER['HTTP_FORWARDED_FOR']) && $this->isPublicIP($_SERVER['HTTP_FORWARDED_FOR']))
                        return $_SERVER['HTTP_FORWARDED_FOR'];
                    
                    return $_SERVER['REMOTE_ADDR'];
                    
                }	// End getRealIpAddr()

                /**
                 * Process Novalnet server response
                 */
                public function do_check_novalnet_status($request) {
					
                    if (isset($request['status'])) {
						
						if ($request['status'] == 100)
                            return($this->do_novalnet_success($request, $this->do_get_novalnet_response_text($request)));
                        
                        elseif (isset($request['novalnet_payment_method']) && $request['novalnet_payment_method'] == 'novalnet_paypal' && $request['status'] == 90) {
							
                            $this->order_status = 'processing';
                            return($this->do_novalnet_success($request, $this->do_get_novalnet_response_text($request)));
                            
                        } #Endelseif
                        
                        else
                            return($this->do_novalnet_cancel($request, $this->do_get_novalnet_response_text($request)));
                            
                    }	#Endif
                    
                }	// End do_check_novalnet_status()

                /**
                 * validate novalnet server response
                 */
                public function do_check_novalnet_payment_status() {
                    
                    if (isset($_REQUEST['status']) && isset($_REQUEST['novalnet_payment_method']) && in_array($_REQUEST['novalnet_payment_method'], $this->return_url_parameter_for_array)) {
						
                        if (isset($_REQUEST['hash'])) {
							
                            if (!$this->checkHash($_REQUEST)) {
								
                                $message = $this->do_get_novalnet_response_text($_REQUEST) . ' - ' . __('Check Hash failed.', 'woocommerce-novalnetpayment');
                                $this->do_novalnet_cancel($_REQUEST, $message);
                                
                            } #Endif
                            
                            else
                                $this->do_check_novalnet_status($_REQUEST);
                                
                        } #Endif
                        
                        else
                            $this->do_check_novalnet_status($_REQUEST);
                            
                    } #Endif
                    
                }	// End do_check_novalnet_payment_status()

                /**
                 * Send acknowledgement parameters to Novalnet server after successful transaction 
                 */
                public function post_back_param($request, $order_id) {
					
					/*	basic validation for post back parameter */
                    if (isset($this->vendor_id) && $this->vendor_id != null && isset($this->product_id) && $this->product_id != null && isset($this->payment_key) && $this->payment_key != null && isset($this->tariff_id) && $this->tariff_id != null && isset($this->auth_code) && $this->auth_code != null && isset($request['tid']) && $request['tid'] != null && isset($order_id) && $order_id != null) {
						
                        $urlparam = 'vendor=' . $this->vendor_id . '&product=' . $this->product_id . '&key=' . $this->payment_key . '&tariff=' . $this->tariff_id . '&auth_code=' . $this->auth_code . '&status=100&tid=' . $request['tid'] . '&order_no=' . $order_id;
                        
                        if ($this->novalnet_payment_method == 'novalnet_invoice' || $this->novalnet_payment_method == 'novalnet_prepayment')
                            $urlparam .='&invoice_ref=' . "BNR-" . $this->product_id . "-" . $order_id;
						
                        list($errno, $errmsg, $data) = $this->perform_https_request($this->novalnet_paygate_url, $urlparam);	
						
                    } #Endif
                    
                }	// End post_back_param()

                /**
                 * Constructor for Novalnet gateway
                 * 
                 * @access public
                 * @return void
                 */
                public function __construct() {

                    global $woocommerce;
                    
                    if (!isset($_SESSION))
                        session_start();
                    
                    if (isset($_REQUEST))
                        $this->do_trim_array_values($_REQUEST);

                    // called after all plugins have loaded
                    add_action('plugins_loaded', array(&$this, 'plugins_loaded'));

                    $this->novalnet_payment_method = $this->id = get_class($this);
                    $this->has_fields = true;

                    // Load the form fields.
                    $this->init_form_fields();

                    // Load the settings.
                    $this->init_settings();

                    $this->do_initialize_novalnet_language();
                    $this->do_assign_config_vars_to_members();

                    // Logs
                    if (isset($this->debug) && $this->debug == 'yes')
                        $this->log = $woocommerce->logger();
                    
                    if (!$this->is_valid_for_use())
                        $this->enabled = false;

                    // novalnet page sessions
                    if (isset($_SESSION['novalnet_receipt_page_got']))
                        unset($_SESSION['novalnet_receipt_page_got']);
                    if (isset($_SESSION['novalnet_thankyou_page_got']))
                        unset($_SESSION['novalnet_thankyou_page_got']);

                    add_action('init', array(&$this, 'do_check_novalnet_payment_status'));

                    if (isset($_REQUEST))
                        $this->do_check_novalnet_backend_data_validation_from_backend($_REQUEST);

                    // actions to perform
                    add_action('woocommerce_successful_request', array(&$this, 'successful_request'));
                    add_action('woocommerce_thankyou_' . $this->novalnet_payment_method, array(&$this, 'thankyou_page'));
                    add_action('woocommerce_receipt_' . $this->novalnet_payment_method, array(&$this, 'receipt_page'));
                    add_action('woocommerce_update_options_payment_gateways', array(&$this, 'process_admin_options'));
                    add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

                    /*	basic validation for iframe request parameter	*/
                    if (isset($this->vendor_id) && $this->vendor_id != null && isset($this->product_id) && $this->product_id != null && isset($this->auth_code) && $this->auth_code != null && isset($this->payment_key) && $this->payment_key != null && isset($this->language) && $this->language != null) {

                        $this->nn_cc_check = true;
                        
                        /* Novalnet Credit card iframe server request */
                        $this->do_check_is_any_request_to_print_cc_iframe();
                        
                    } #Endif
                    
                    else
                        $this->nn_cc_check = false;
                        
                }	// End __construct()

                /**
                 * thank you page
                 */
                public function thankyou_page($order_id) {
					
                    if (!isset($_SESSION['novalnet_thankyou_page_got'])) {
                    
                        $order = new WC_Order($order_id);
                        echo wpautop('<strong>' . __('Transaction Information:', 'woocommerce-novalnetpayment') . '</strong>');
                        echo wpautop(wptexturize($order->customer_note));
                        $_SESSION['novalnet_thankyou_page_got'] = 1;
                        
                    }	#Endif
                    
                }	// End thankyou_page()

                /**
                 * set current
                 */
                public function set_current() {
					
                    $this->chosen = true;
                }	// End set_current()

                /**
                 * Displays payment method icon
                 */
                public function get_icon() {
                    
                    $icon_html = '';
                    
                    if ($this->payment_logo)
                        $icon_html = '<a href="' . (strtolower($this->language) == 'de' ? 'https://www.novalnet.de' : 'http://www.novalnet.com') . '" alt="' . __('novalnet.com', 'woocommerce-novalnetpayment') . '" target="_new"><img height ="30" src="' . $this->icon . '" alt="' . $this->method_title . '" /></a>';
                        
                    return($icon_html);
                    
                }	// End get_icon()

                /**
                 * Displays Novalnet Logo icon
                 */
                public function get_title() {
                    
                    return($this->title);
                }	// End get_title()

                /**
                 * Payment field to display description and additional info in the checkout form	 
                 */
                public function payment_fields() {

                    // payment description
                    if ($this->description)
                        echo wpautop(wptexturize($this->description));

                    // test order notice
                    if ($this->test_mode == 1) {
                        $test_notice = __('Please Note: This transaction will run on TEST MODE and the amount will not be charged', 'woocommerce-novalnetpayment');
                        echo wpautop('<strong><font color="red">' . $test_notice . '</font></strong>');
                    }	#Endif
                    
                    // payment form
                    switch ($this->novalnet_payment_method) {
						
                        case 'novalnet_cc':
                            if (isset($this->nn_cc_check) && $this->nn_cc_check == true) {

                                /* Novalnet Credit Card Payment form */
                                print '<br /><div id="loading_iframe_div" style="display:;"><img alt="' . __('Loading...', 'woocommerce-novalnetpayment') . '" src="' . (is_ssl() ? 'https://www.novalnet.de/img/novalnet-loading-icon.gif' : 'http://www.novalnet.de/img/novalnet-loading-icon.gif') . '"></div><input type="hidden" name="cc_type" id="cc_type" value="" /><input type="hidden" name="cc_holder" id="cc_holder" value="" /><input type="hidden" name="cc_exp_month" id="cc_exp_month" value="" /><input type="hidden" name="cc_exp_year" id="cc_exp_year" value="" /><input type="hidden" name="cc_cvv_cvc" id="cc_cvv_cvc" value="" /><input type="hidden" id="original_vendor_id" value="' . ($this->vendor_id) . '" /><input type="hidden" id="original_vendor_authcode" value="' . ($this->auth_code) . '" /><input type="hidden" id="original_customstyle_css" value="" /><input type="hidden" id="original_customstyle_cssval" value="" /><input type="hidden" name="nn_unique_id" id="nn_unique_id" value="" /><input type="hidden" name="nn_cardno_id" id="nn_cardno_id" value="" /><iframe onLoad="doHideLoadingImageAndDisplayIframe(this);" name="novalnet_cc_iframe" id="novalnet_cc_iframe" src="' . site_url() . '/wp-content/plugins/woocommerce-novalnet-gateway/includes/novalnet_cc_iframe.html" scrolling="no" frameborder="0" style="width:100%; height:280px; border:none; display:none;"></iframe>   
							<script type="text/javascript" language="javascript">
									function doHideLoadingImageAndDisplayIframe(element) {
										document.getElementById("loading_iframe_div").style.display = "none";
										element.style.display = "";
										var iframe = (element.contentWindow || element.contentDocument);
										if (iframe.document) iframe = iframe.document;
										iframe.getElementById("novalnetCc_cc_type").onchange = function() {
											doAssignIframeElementsValuesToFormElements(iframe);
										}
										iframe.getElementById("novalnetCc_cc_owner").onkeyup = function() {
											doAssignIframeElementsValuesToFormElements(iframe);
										}
										iframe.getElementById("novalnetCc_expiration").onchange = function() {
											doAssignIframeElementsValuesToFormElements(iframe);
										}
										iframe.getElementById("novalnetCc_expiration_yr").onchange = function() {
											doAssignIframeElementsValuesToFormElements(iframe);
										}
										iframe.getElementById("novalnetCc_cc_cid").onkeyup = function() {
											doAssignIframeElementsValuesToFormElements(iframe);
										}
									}

									function doAssignIframeElementsValuesToFormElements(iframe) {
										document.getElementById("cc_type").value = iframe.getElementById("novalnetCc_cc_type").value;
										document.getElementById("cc_holder").value = iframe.getElementById("novalnetCc_cc_owner").value;
										document.getElementById("cc_exp_month").value = iframe.getElementById("novalnetCc_expiration").value;
										document.getElementById("cc_exp_year").value = iframe.getElementById("novalnetCc_expiration_yr").value;
										document.getElementById("cc_cvv_cvc").value = iframe.getElementById("novalnetCc_cc_cid").value;
									}
									var novalnetHiddenId = "novalnet_cc_formid";
									var getInputForm = document.getElementById("original_vendor_id");
									if (getInputForm.form.getAttribute("id") == null || getInputForm.form.getAttribute("id") == "") {
										getInputForm.form.setAttribute("id", novalnetHiddenId);
										getFormId = getInputForm.form.getAttribute("id");
									} else {
										getFormId = getInputForm.form.getAttribute("id");
									}
									window.addEventListener ? window.addEventListener("load", nn_cc, false) : window.attachEvent && window.attachEvent("onload", nn_cc);

									function nn_cc() {
										document.forms[getFormId].onclick = function() {
											var iform = document.getElementById("novalnet_cc_iframe");
											var novalnet_cc_iframe = (iform.contentWindow || iform.contentDocument);
											if (novalnet_cc_iframe.document) novalnet_cc_iframe = novalnet_cc_iframe.document;
											if (novalnet_cc_iframe.getElementById("nncc_cardno_id").value != null) {
												document.getElementById("nn_cardno_id").value = novalnet_cc_iframe.getElementById("nncc_cardno_id").value;
												document.getElementById("nn_unique_id").value = novalnet_cc_iframe.getElementById("nncc_unique_id").value;
											}
										}
									}
							</script>
							';
                            } #Endif 
                            
                            else
								  echo wpautop('<strong><font color="red">' . __('Basic Parameter Missing', 'woocommerce-novalnetpayment') . '</font></strong>');
                            break;
                            
                        case 'novalnet_cc3d':

                            /* Novalnet Credit Card 3D Secure Payment form */
                            $payment_field_html = '<div>&nbsp;</div><div>
            <div style="float:left;width:50%;">' . __('Credit card holder', 'woocommerce-novalnetpayment') . ':<span style="color:red;">*</span></div>
            <div style="float:left;width:50%;"><input type="text" name="cc3d_holder" id="cc3d_holder" value="" autocomplete="off" /></div>
            <div style="clear:both;">&nbsp;</div>
            <div style="float:left;width:50%;">' . __('Card number', 'woocommerce-novalnetpayment') . ':<span style="color:red;">*</span></div>
            <div style="float:left;width:50%;"><input type="text" name="cc3d_number" id="cc3d_number" value="" autocomplete="off" /></div>
            <div style="clear:both;">&nbsp;</div>
            <div style="float:left;width:50%;">' . __('Expiration Date', 'woocommerce-novalnetpayment') . ':<span style="color:red;">*</span></div>
            <div style="float:left;width:50%;">
            <select name="cc3d_exp_month" id="cc3d_exp_month">
            <option value="">' . __('Month', 'woocommerce-novalnetpayment') . '</option>
            <option value="1">' . __('January', 'woocommerce-novalnetpayment') . '</option>
            <option value="2">' . __('February', 'woocommerce-novalnetpayment') . '</option>
            <option value="3">' . __('March', 'woocommerce-novalnetpayment') . '</option>
            <option value="4">' . __('April', 'woocommerce-novalnetpayment') . '</option>
            <option value="5">' . __('May', 'woocommerce-novalnetpayment') . '</option>
            <option value="6">' . __('June', 'woocommerce-novalnetpayment') . '</option>
            <option value="7">' . __('July', 'woocommerce-novalnetpayment') . '</option>
            <option value="8">' . __('August', 'woocommerce-novalnetpayment') . '</option>
            <option value="9">' . __('September', 'woocommerce-novalnetpayment') . '</option>
            <option value="10">' . __('October', 'woocommerce-novalnetpayment') . '</option>
            <option value="11">' . __('November', 'woocommerce-novalnetpayment') . '</option>
            <option value="12">' . __('December', 'woocommerce-novalnetpayment') . '</option>
            </select>&nbsp;
            <select name="cc3d_exp_year" id="cc3d_exp_year">
            <option value="">' . __('Year', 'woocommerce-novalnetpayment') . '</option>';
            
                            for ($iYear = date('Y'); $iYear < date('Y') + 6; $iYear++) {
                                $payment_field_html.='<option value="' . $iYear . '">' . $iYear . '</option>';
                            } #Endfor
                            
                            $payment_field_html.='</select>
            </div>
            <div style="clear:both;">&nbsp;</div>
            <div style="float:left;width:50%;">' . __('CVC (Verification Code)', 'woocommerce-novalnetpayment') . ':<span style="color:red;">*</span></div>
            <div style="float:left;width:50%;"><input type="text" name="cvv_cvc" id="cvv_cvc" value="" maxlength="4" autocomplete="off" /><br />' . __('* On Visa-, Master- and Eurocard you will find the 3 digit CVC-Code near the signature field at the rearside of the creditcard.', 'woocommerce-novalnetpayment') . '</div>
            <div style="clear:both;">&nbsp;</div></div>';
            
                            print $payment_field_html;
                            break;
                            
                        case 'novalnet_elv_de':

                            /* Novalnet Direct Debit German Payment form */
                            print $this->do_print_form_elements_for_novalnet_elv_de_at('de');
                            break;
                            
                        case 'novalnet_elv_at':

                            /* Novalnet Direct Debit Austria Payment form */
                            print $this->do_print_form_elements_for_novalnet_elv_de_at('at');
                            break;
                            
                    }	#Endswitchcase
                    
                }	// End payment_fields()

                /*
                 * Process the payment and return the result
                 */
                public function process_payment($order_id) {
					
                    return($this->do_process_payment_from_novalnet_payments($order_id));
                }	// End process_payment()

                /**
                 * Receipt_page
                 */
                public function receipt_page($order_id) {
                    
                    $order = new WC_Order($order_id);
                    $this->do_necessary_actions_before_prepare_to_novalnet_payport_or_paygate($order);
                    $this->do_prepare_to_novalnet_payport($order);
                    
                }	// End receipt_page()

                /**
                 * plugins loaded
                 */
                public function plugins_loaded() {
                    
                }	// End plugins_loaded()

                /**
                 * include template
                 */
                public function include_template_functions() {
                    
                }	// End include_template_functions()

                /**
                 * is valid for use
                 */
                function is_valid_for_use() {
                    return(true);
                }	// End is_valid_for_use()

                /**
                 * Admin Panel Options 
                 */
                public function admin_options() {
                    ?>
                    <h3><?php echo '<a href="' . (strtolower($this->language) == 'de' ? 'https://' : 'http://') . 'www.' . __('novalnet.com', 'woocommerce-novalnetpayment') . '" alt="' . __('novalnet.com', 'woocommerce-novalnetpayment') . '" target="_new"><img src="' . (is_ssl() ? 'https://' : 'http://') . __('www.novalnet.de/img/NN_Logo_T.png', 'woocommerce-novalnetpayment') . '" alt="' . __('novalnet.com', 'woocommerce-novalnetpayment') . '" /></a>&nbsp;' . $this->payment_details[$this->novalnet_payment_method]['payment_name'] . ' ' . '<a href="' . (strtolower($this->language) == 'de' ? 'https://www.novalnet.de' : 'http://www.novalnet.com') . '" alt="' . __('novalnet.com', 'woocommerce-novalnetpayment') . '" target="_new"><img height ="30" src="' . $this->icon . '" alt="' . $this->method_title . '" /></a>'; ?></h3>
                    <p><?php echo __('Configure with Novalnet dealer details.If you need more information<br><br>you can visit our website for end-customers visit on <a href="https://www.novalnet.de/" target="_blank"> https://www.novalnet.de</a> or please contact our Sales Team <a href="mailto:sales@novalnet.de">sales@novalnet.de</a>.', 'woocommerce-novalnetpayment'); ?></p>
                    <table class="form-table">
                        <?php
                        // Generate the HTML For the settings form.
                        $this->generate_settings_html();
                        ?>
                    </table><!--/.form-table-->
                    <?php
                }	// End admin_options()

                /**
                 * Initialise Novalnet Gateway Settings Form Fields
                 */
                public function init_form_fields() {
                    
                    // Enable module
                    $this->form_fields['enabled'] = array(
                        'title' => __('Enable module', 'woocommerce-novalnetpayment'),
                        'type' => 'checkbox',
                        'label' => '',
                        'default' => ''
                    );
                    
                    // Payment title & description fields
                    foreach ($this->language_supported_array as $language) {
                        $this->form_fields['title_' . $language] = array(
                            'title' => __('Payment Title', 'woocommerce-novalnetpayment') . ' (' . $language . ')<span style="color:red;">*</span>',
                            'type' => 'text',
                            'description' => '',
                            'default' => ''
                        );
                        $this->form_fields['description_' . $language] = array(
                            'title' => __('Description', 'woocommerce-novalnetpayment') . ' (' . $language . ')',
                            'type' => 'textarea',
                            'description' => '',
                            'default' => ''
                        );
                    } #Endforeach
                    
                    // Enable test mode
                    $this->form_fields['test_mode'] = array(
                        'title' => __('Enable Test Mode', 'woocommerce-novalnetpayment'),
                        'type' => 'select',
                        'options' => array('0' => __('No', 'woocommerce-novalnetpayment'), '1' => __('Yes', 'woocommerce-novalnetpayment')),
                        'description' => '',
                        'default' => ''
                    );
                    
                    // Novalnet Merchant ID field
                    $this->form_fields['merchant_id'] = array(
                        'title' => __('Novalnet Merchant ID', 'woocommerce-novalnetpayment') . '<span style="color:red;">*</span>',
                        'type' => 'text',
                        'description' => __('Enter your Novalnet Merchant ID', 'woocommerce-novalnetpayment'),
                        'default' => ''
                    );
                    
                    // Novalnet Authorisation code field
                    $this->form_fields['auth_code'] = array(
                        'title' => __('Novalnet Merchant Authorisation code', 'woocommerce-novalnetpayment') . '<span style="color:red;">*</span>',
                        'type' => 'text',
                        'description' => __('Enter your Novalnet Merchant Authorisation code', 'woocommerce-novalnetpayment'),
                        'default' => ''
                    );
                    
                    // Novalnet Product ID field
                    $this->form_fields['product_id'] = array(
                        'title' => __('Novalnet Product ID', 'woocommerce-novalnetpayment') . '<span style="color:red;">*</span>',
                        'type' => 'text',
                        'description' => __('Enter your Novalnet Product ID', 'woocommerce-novalnetpayment'),
                        'default' => ''
                    );
                    
                    // Novalnet Tariff ID field
                    $this->form_fields['tariff_id'] = array(
                        'title' => __('Novalnet Tariff ID', 'woocommerce-novalnetpayment') . '<span style="color:red;">*</span>',
                        'type' => 'text',
                        'description' => __('Enter your Novalnet Tariff ID', 'woocommerce-novalnetpayment'),
                        'default' => ''
                    );
                    
                    // Novalnet Payment Access Key field
                    if (in_array($this->novalnet_payment_method, $this->encode_applicable_for_array)) {
                        $this->form_fields['key_password'] = array(
                            'title' => __('Novalnet Payment access key', 'woocommerce-novalnetpayment') . '<span style="color:red;">*</span>',
                            'type' => 'text',
                            'description' => __('Enter your Novalnet payment access key', 'woocommerce-novalnetpayment'),
                            'default' => ''
                        );
                    } #Endif
                    
                    // Enable ACDC
                    if ($this->novalnet_payment_method == 'novalnet_elv_de') {
                        $this->form_fields['acdc'] = array(
                            'title' => __('Enable credit rating check', 'woocommerce-novalnetpayment'),
                            'type' => 'checkbox',
                            'label' => '',
                            'default' => ''
                        );
                    } #Endif
                    
                    // Payment duration field
                    if ($this->novalnet_payment_method == 'novalnet_invoice') {
                        $this->form_fields['payment_duration'] = array(
                            'title' => __('Payment period in days', 'woocommerce-novalnetpayment'),
                            'type' => 'text',
                            'label' => '',
                            'default' => ''
                        );
                    } #Endif
                    
                    // Manual check limit fields
                    if (!in_array($this->novalnet_payment_method, $this->manual_check_limit_not_available_array)) {
                        $this->form_fields['manual_check_limit'] = array(
                            'title' => __('Manual checking amount in cents', 'woocommerce-novalnetpayment'),
                            'type' => 'text',
                            'description' => __('Please enter the amount in cents', 'woocommerce-novalnetpayment'),
                            'default' => ''
                        );
                        $this->form_fields['product_id_2'] = array(
                            'title' => __('Second Product ID in Novalnet', 'woocommerce-novalnetpayment'),
                            'type' => 'text',
                            'description' => __('for the manual checking', 'woocommerce-novalnetpayment'),
                            'default' => ''
                        );
                        $this->form_fields['tariff_id_2'] = array(
                            'title' => __('Second Tariff ID in Novalnet', 'woocommerce-novalnetpayment'),
                            'type' => 'text',
                            'description' => __('for the manual checking', 'woocommerce-novalnetpayment'),
                            'default' => ''
                        );
                    } #Endif
                    
                    // PayPal configuration fields
                    if ($this->novalnet_payment_method == 'novalnet_paypal') {
						
                        $this->form_fields['api_username'] = array(
                            'title' => __('PayPal API User Name', 'woocommerce-novalnetpayment') . '<span style="color:red;">*</span>',
                            'type' => 'text',
                            'description' => __('Please enter your PayPal API username', 'woocommerce-novalnetpayment'),
                            'default' => ''
                        );
                        $this->form_fields['api_password'] = array(
                            'title' => __('PayPal API Password', 'woocommerce-novalnetpayment') . '<span style="color:red;">*</span>',
                            'type' => 'text',
                            'description' => __('Please enter your PayPal API password', 'woocommerce-novalnetpayment'),
                            'default' => ''
                        );
                        $this->form_fields['api_signature'] = array(
                            'title' => __('PayPal API Signature', 'woocommerce-novalnetpayment') . '<span style="color:red;">*</span>',
                            'type' => 'text',
                            'description' => __('Please enter your PayPal API signature', 'woocommerce-novalnetpayment'),
                            'default' => ''
                        );
                        
                    } #Endif
                    
                    // Proxy server field (required for cURL protocol, if the client set any proxy port in their server)
                    $this->form_fields['payment_proxy'] = array(
                        'title' => __('Proxy-Server', 'woocommerce-novalnetpayment'),
                        'type' => 'text',
                        'description' => __('If you use a Proxy Server, enter the Proxy Server IP with port here (e.g. www.proxy.de:80)', 'woocommerce-novalnetpayment'),
                        'default' => ''
                    );
                    
                    // Enable Payment Logo
                    $this->form_fields['payment_logo'] = array(
                        'title' => __('Enable Payment Logo', 'woocommerce-novalnetpayment'),
                        'type' => 'select',
                        'options' => array('0' => __('No', 'woocommerce-novalnetpayment'), '1' => __('Yes', 'woocommerce-novalnetpayment')),
                        'description' => __('To display Payment logo in front end', 'woocommerce-novalnetpayment'),
                        'default' => ''
                    );
                
                }	// End init_form_fields()
                
            }	// End class novalnetpayments
            
        }	#Endif
        
    }	#Endif
    
}	// End novalnetpayments_Load()

/* initiate novlanet payment methods	 */
if (isset($_REQUEST['novalnet_payment_method']) && in_array($_REQUEST['novalnet_payment_method'], $novalnet_payment_methods))
    require_once(dirname(__FILE__) . '/includes/' . $_REQUEST['novalnet_payment_method'] . '.php'); 
else {
    foreach ($novalnet_payment_methods as $novalnet_payment_method)
        require_once(dirname(__FILE__) . '/includes/' . $novalnet_payment_method . '.php');
    ob_get_clean();
}
?>
