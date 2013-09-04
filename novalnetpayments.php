<?php
/*
 * Plugin Name: Woocommerce Payment Gateway by Novalnet
 * Plugin URI:  http://www.novalnet.com/modul/woocommerce-payment-modul/
 * Description: Adds Novalnet Payment Gateway to Woocommerce e-commerce plugin
 * Author:      Novalnet
 * Author URI:  https://www.novalnet.de
 * 
 * Version: 	 1.0.4 
 * Requires at least:   3.3
 * Tested up to:        3.5.2
 *
 * Text Domain:         woocommerce-novalnetpayment
 * Domain Path:         /languages/
 * 
 * License: GPLv2
 */
 
/* Plugin installation starts */
register_activation_hook(__FILE__, 'novalnetpayments_activation');
register_deactivation_hook(__FILE__, 'novalnetpayments_deactivation');

add_action('admin_notices', 'novalnetpayments_admin_notices');
if (!function_exists('novalnetpayments_activation')) {

    function novalnetpayments_activation() {
        /**
         * if you're already using .htaccess file please comment out these lines[line @30 to line @37]     
         **/

        $htaccess_path = ABSPATH . 'wp-content/plugins/' . dirname(plugin_basename(__FILE__));
        copy($htaccess_path . '/htaccess.txt', ABSPATH . 'htaccess.txt');
        $sub_folder = substr(home_url(), strpos(home_url(), $_SERVER['HTTP_HOST']) + strlen($_SERVER['HTTP_HOST']), strlen(home_url()));
        $htaccess_content = file_get_contents(ABSPATH . 'htaccess.txt');
        $htaccess_content = str_replace("RewriteRule . /index.php [L]", "RewriteRule . " . $sub_folder . "/index.php [L]", $htaccess_content);
        file_put_contents(ABSPATH . '.htaccess', $htaccess_content);

        //register uninstaller
        register_uninstall_hook(__FILE__, 'novalnetpayments_uninstall');
    }

}
if (!function_exists('novalnetpayments_deactivation')) {

    function novalnetpayments_deactivation() {
        // actions to perform once on plugin deactivation go here  
    }

}
if (!function_exists('novalnetpayments_uninstall')) {

    function novalnetpayments_uninstall() {
        //actions to perform once on plugin uninstall go here
    }

}

/**
 * Display admin notice at back-end during plugin activation
 **/
function novalnetpayments_admin_notices() {
    if (!is_plugin_active('woocommerce/woocommerce.php')) {
        echo '<div id="notice" class="error"><p>';
        echo '<b>' . __('Woocommerce Payment Gateway by Novalnet', 'woocommerce-novalnetpayment') . '</b> ' . __('add-on requires', 'woocommerce-novalnetpayment') . ' ' . '<a href="http://www.woothemes.com/woocommerce/" target="_new">' . __('WooCommerce', 'woocommerce-novalnetpayment') . '</a>' . ' ' . __('plugin. Please install and activate it.', 'woocommerce-novalnetpayment');
        echo '</p></div>', "\n";
    }
}

/* Plugin installation ends */
$novalnet_payment_methods = array('novalnet_banktransfer', 'novalnet_cc', 'novalnet_cc_pci', 'novalnet_cc3d', 'novalnet_elv_at', 'novalnet_elv_at_pci', 'novalnet_elv_de', 'novalnet_elv_de_pci', 'novalnet_ideal', 'novalnet_invoice', 'novalnet_paypal', 'novalnet_prepayment', 'novalnet_tel');

add_action('plugins_loaded', 'novalnetpayments_Load', 0);

function novalnetpayments_Load() {
    load_plugin_textdomain('woocommerce-novalnetpayment', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
        if (!class_exists('WC_Payment_Gateway'))
            return;
        if (!class_exists('novalnetpayments')) {

            class novalnetpayments extends WC_Payment_Gateway {

                var $novalnet_paygate_url 					= 'https://payport.novalnet.de/paygate.jsp';
                var $novalnet_pci_payport_url 				= 'https://payport.novalnet.de/pci_payport';
                var $novalnet_online_transfer_payport 		= 'https://payport.novalnet.de/online_transfer_payport';
                var $payment_key_for_cc_family 				= 6;
                var $payment_key_for_at_family 				= 8;
                var $payment_key_for_de_family 				= 2;
                var $payment_key_for_invoice_prepayment 	= 27;
                var $front_end_form_available_array 		= array('novalnet_cc', 'novalnet_cc3d', 'novalnet_elv_de', 'novalnet_elv_at');
                var $manual_check_limit_not_available_array = array('novalnet_banktransfer', 'novalnet_ideal', 'novalnet_invoice', 'novalnet_prepayment', 'novalnet_paypal', 'novalnet_tel');
                var $return_url_parameter_for_array 		= array('novalnet_banktransfer', 'novalnet_cc_pci', 'novalnet_cc3d', 'novalnet_elv_at_pci', 'novalnet_elv_de_pci', 'novalnet_ideal', 'novalnet_paypal');
                var $encode_applicable_for_array 			= array('novalnet_banktransfer', 'novalnet_cc_pci', 'novalnet_elv_at_pci', 'novalnet_elv_de_pci', 'novalnet_ideal', 'novalnet_paypal');
                var $user_variable_parameter_for_arrray 	= array('novalnet_banktransfer', 'novalnet_paypal', 'novalnet_ideal');
                var $language_supported_array 				= array('en', 'de');
				
				/**
				 * Telephone payment second call request
				 **/ 
                public function do_make_second_call_for_novalnet_telephone($order_id) {
                    ### Process the payment to payport ##
                    $urlparam = '<nnxml><info_request><vendor_id>' . $this->vendor_id . '</vendor_id>';
                    $urlparam .= '<vendor_authcode>' . $this->auth_code . '</vendor_authcode>';
                    $urlparam .= '<request_type>NOVALTEL_STATUS</request_type><tid>' . $_SESSION['novalnet_tel_tid'] . '</tid>';
                    $urlparam .= '<lang>' . strtoupper($this->language) . '</lang></info_request></nnxml>';
                    list($errno, $errmsg, $data) = $this->perform_https_request($this->payport_or_paygate_url, $urlparam);
                    if (strstr($data, '<novaltel_status>')) {
                        preg_match('/novaltel_status>?([^<]+)/i', $data, $matches);
                        $aryResponse['status'] = $matches[1];
                        preg_match('/novaltel_status_message>?([^<]+)/i', $data, $matches);
                        $aryResponse['status_desc'] = $matches[1];
                    } else {
                        $aryPaygateResponse = explode('&', $data);
                        foreach ($aryPaygateResponse as $key => $value) {
                            if ($value != "") {
                                $aryKeyVal = explode("=", $value);
                                $aryResponse[$aryKeyVal[0]] = $aryKeyVal[1];
                            }
                        }
                    }
                    $aryResponse['tid'] 			= $_SESSION['novalnet_tel_tid'];
                    $aryResponse['test_mode'] 		= $_SESSION['novalnet_tel_test_mode'];
                    $aryResponse['order_no'] 		= $order_id;
                    // Manual Testing
                    //      $aryResponse['status_desc'] = __('Successful', 'woocommerce-novalnetpayment');
                    //      $aryResponse['status'] 		= 100;
                    // Manual Testing
                    return($this->do_check_novalnet_status($aryResponse, $order_id));
                }
				
				/**
				 * Clears Telephone payment session value
				 **/ 
                public function do_unset_novalnet_telephone_sessions() {
                    unset($_SESSION['novalnet_tel_tid']);
                    unset($_SESSION['novalnet_tel_test_mode']);
                    unset($_SESSION['novalnet_tel_amount']);
                }
				
				/**
				 * process Telephone payment server response
				 **/ 
                public function do_check_novalnet_tel_payment_status(&$aryResponse, $order) {
                    global $woocommerce;
                    $new_line = "<br />";
                    if ($aryResponse['status'] == 100 && $aryResponse['tid']) {
                        $aryResponse['status_desc'] = '';
                        if (!isset($_SESSION['novalnet_tel_tid']))
                            $_SESSION['novalnet_tel_tid'] = $aryResponse['tid'];
                        $_SESSION['novalnet_tel_test_mode'] = $aryResponse['test_mode'];
                        $_SESSION['novalnet_tel_amount'] = $this->amount;
                    }
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
                            }
                            $str_sess_tel = trim($str_sess_tel);
                            if ($str_sess_tel)
                                $sess_tel = $str_sess_tel;
                        }
                        $this->do_check_and_add_novalnet_errors_and_messages(__('Following steps are required to complete your payment:', 'woocommerce-novalnetpayment') . $new_line . $new_line . __('Step 1: Please call the telephone number displayed:', 'woocommerce-novalnetpayment') . ' ' . $sess_tel . $new_line . str_replace('{amount}', $order->get_formatted_order_total(), __('* This call will cost {amount} (including VAT) and it is possible only for German landline connection! *', 'woocommerce-novalnetpayment')) . $new_line . $new_line . __('Step 2: Please wait for the beep and then hang up the listeners.', 'woocommerce-novalnetpayment') . $new_line . __('After your successful call, please proceed with the payment.', 'woocommerce-novalnetpayment'), 'message');
                        return($this->do_return_redirect_page_for_pay_or_thanks_page('success', $woocommerce->cart->get_checkout_url()));
                    } else {
                        $this->do_check_and_add_novalnet_errors_and_messages($aryResponse['status_desc'], 'error');
                        return($this->do_return_redirect_page_for_pay_or_thanks_page('success', $woocommerce->cart->get_checkout_url()));
                    }
                }
				
				/**
				 * Validate cart amount
				 **/ 
                public function do_validate_amount() {
                    global $woocommerce;
                    if ($this->amount < 99 || $this->amount > 1000) {
                        $this->do_check_and_add_novalnet_errors_and_messages(__('Amounts below 0,99 Euros and above 10,00 Euros cannot be processed and are not accepted!', 'woocommerce-novalnetpayment'), 'error');
                        return($this->do_return_redirect_page_for_pay_or_thanks_page('success', $woocommerce->cart->get_checkout_url()));
                    }
                }
				
				/**
				 * Validate amount variations in cart
				 **/ 
                public function do_validate_amount_variations() {
                    global $woocommerce;
                    if (isset($_SESSION['novalnet_tel_amount']) && $_SESSION['novalnet_tel_amount'] != $this->amount) {
                        $this->do_unset_novalnet_telephone_sessions();
                        $this->do_check_and_add_novalnet_errors_and_messages(__('You have changed the order amount after receiving telephone number, please try again with a new call', 'woocommerce-novalnetpayment'), 'error');
                        return($this->do_return_redirect_page_for_pay_or_thanks_page('success', $woocommerce->cart->get_checkout_url()));
                    }
                    return('');
                }
				
				/**
				 * Process data after paygate response
				 **/ 
                public function do_prepare_to_novalnet_paygate($order) {
                    list($errno, $errmsg, $data) = $this->perform_https_request($this->payport_or_paygate_url, $this->payment_parameters);
                    $aryResponse = array();
                    #capture the result and message and other parameters from response data '$data' in an array
                    $aryPaygateResponse = explode('&', $data);
                    foreach ($aryPaygateResponse as $key => $value) {
                        if ($value != "") {
                            $aryKeyVal = explode("=", $value);
                            $aryResponse[$aryKeyVal[0]] = $aryKeyVal[1];
                        }
                    }
                    return($aryResponse);
                }
				
				/**
				 * process parameters before sending to server
				 **/ 
                public function do_necessary_actions_before_prepare_to_novalnet_payport_or_paygate($order) {
                    $this->user_ip = $this->getRealIpAddr();
                    $this->do_check_curl_installed_or_not();
                    $this->do_format_amount($order->order_total);
                    $this->do_check_novalnet_backend_data_validation_from_frontend();
                    $this->do_check_and_assign_manual_check_limit();
                    $this->do_form_payment_parameters($order);
                }

                /**
                 * Generate Novalnet secure form
                 **/
                public function get_novalnet_form_html($order) {
                    global $woocommerce;
                    $novalnet_args_array = array();
                    foreach ($this->payment_parameters as $key => $value) {
                        $novalnet_args_array[] = '<input type="hidden" name="' . esc_attr($key) . '" id="' . esc_attr($key) . '" value="' . esc_attr($value) . '" />';
                    }
                    $woocommerce->add_inline_js('
            jQuery("body").block({
            message: "<img src=\"' . esc_url(apply_filters('woocommerce_ajax_loader_url', $woocommerce->plugin_url() . '/assets/images/ajax-loader.gif')) . '\" alt=\"' . __('Redirecting...', 'woocommerce-novalnetpayment') . '&hellip;\" style=\"float:left; margin-right: 10px;\" />' . __('You will be redirected to Novalnet AG in a few seconds. <br>', 'woocommerce-novalnetpayment') . '",
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
            ');
                    $novalnet_form_html = '<form name="frmnovalnet" id="frmnovalnet" method="post" action="' . $this->payport_or_paygate_url . '" target="_top">
            ' . implode('', $novalnet_args_array) . '
            <a class="button cancel" href="' . esc_url($order->get_cancel_order_url()) . '">' . __('Cancel order &amp; restore cart', 'woocommerce-novalnetpayment') . '</a>
            </form>
            <script type="text/javascript" language="javascript">
            window.onload = function() { document.forms.frmnovalnet.submit(); }
            </script>
            ';
                    return($novalnet_form_html);
                }
				
				/**
				 * Validate curl extension
				 **/ 
                public function do_check_curl_installed_or_not() {
                    global $woocommerce;
                    if (!function_exists('curl_init') && !in_array($this->novalnet_payment_method, $this->return_url_parameter_for_array)) {
                        $this->do_check_and_add_novalnet_errors_and_messages(__('You need to activate the CURL function on your server, please check with your hosting provider.', 'woocommerce-novalnetpayment'), 'error');
                        wp_safe_redirect($woocommerce->cart->get_checkout_url());
                        exit();
                    }
                }
				
				/**
				 * Collects novalnet payment parameters
				 **/ 
                public function do_form_payment_parameters($order) {
                    $this->get_backend_hash_parameter_array();
                    $this->get_backend_variation_parameter_array();
                    $this->get_user_variable_parameter_array();
                    $this->get_return_url_parameter_array();
                    $this->get_backend_additional_parameter_array($order);
                    $this->get_backend_common_parameter_array($order);
                }
				
				/**
				 * Get back-end hash parameter
				 **/ 
                public function get_backend_hash_parameter_array() {
                    if (in_array($this->novalnet_payment_method, $this->encode_applicable_for_array)) {
                        $this->auth_code 	= $this->encode($this->auth_code);
                        $this->product_id 	= $this->encode($this->product_id);
                        $this->tariff_id 	= $this->encode($this->tariff_id);
                        $this->amount 		= $this->encode($this->amount);
                        $this->test_mode 	= $this->encode($this->test_mode);
                        $this->unique_id 	= $this->encode($this->unique_id);
                        if (isset($this->api_username))
                            $this->api_username  = $this->encode($this->api_username);
                        if (isset($this->api_password))
                            $this->api_password  = $this->encode($this->api_password);
                        if (isset($this->api_signature))
                            $this->api_signature = $this->encode($this->api_signature);
                        $hash = $this->hash(array('authcode' => $this->auth_code, 'product_id' => $this->product_id, 'tariff' => $this->tariff_id, 'amount' => $this->amount, 'test_mode' => $this->test_mode, 'uniqid' => $this->unique_id));
                        $this->payment_parameters['hash'] = $hash;
                    }
                }
				
				/**
				 * Get back-end variation parameter
				 **/ 
                public function get_backend_variation_parameter_array() {
                    if ($this->novalnet_payment_method == 'novalnet_cc_pci' || $this->novalnet_payment_method == 'novalnet_elv_at_pci' || $this->novalnet_payment_method == 'novalnet_elv_de_pci') {
                        $this->payment_parameters['vendor_id'] 		= $this->vendor_id;
                        $this->payment_parameters['product_id'] 	= $this->product_id;
                        $this->payment_parameters['tariff_id'] 		= $this->tariff_id;
                        $this->payment_parameters['vendor_authcode']= $this->auth_code;
                        $this->payment_parameters['implementation'] = 'PHP_PCI';
                    } else {
                        $this->payment_parameters['vendor'] 		= $this->vendor_id;
                        $this->payment_parameters['product'] 		= $this->product_id;
                        $this->payment_parameters['tariff'] 		= $this->tariff_id;
                        $this->payment_parameters['auth_code'] 		= $this->auth_code;
                    }
                }
				
				/**
				 * Get user variable parameter
				 **/ 
                public function get_user_variable_parameter_array() {
                    if (in_array($this->novalnet_payment_method, $this->user_variable_parameter_for_arrray)) {
                        $this->payment_parameters['user_variable_0'] = site_url();
                    }
                }
				
				/**
				 * Get return url parameter 
				 **/ 
                public function get_return_url_parameter_array() {
                    $return_url = get_permalink(get_option('woocommerce_checkout_page_id'));
                    if (in_array($this->novalnet_payment_method, $this->return_url_parameter_for_array)) {
                        $this->payment_parameters['return_url'] 		 = $return_url;
                        $this->payment_parameters['return_method'] 		 = 'POST';
                        $this->payment_parameters['error_return_url'] 	 = $return_url;
                        $this->payment_parameters['error_return_method'] = 'POST';
                        $this->payment_parameters['novalnet_payment_method'] = $this->novalnet_payment_method;
                    }
                }
				
				/**
				 * Get back-end additional parameters
				 **/ 
                public function get_backend_additional_parameter_array($order) {
                    global $woocommerce;
                    if ($this->novalnet_payment_method == 'novalnet_invoice' || $this->novalnet_payment_method == 'novalnet_prepayment') {
                        $this->invoice_type = strtoupper(substr($this->novalnet_payment_method, strpos($this->novalnet_payment_method, '_') + 1, strlen($this->novalnet_payment_method)));
                        $this->invoice_ref = "BNR-" . $this->product_id . "-" . $order->id;
                        $this->payment_parameters['invoice_type'] = $this->invoice_type;
                        $this->payment_parameters['invoice_ref']  = $this->invoice_ref;
                    }
                    if ($this->novalnet_payment_method == 'novalnet_invoice') {
                        if ($this->payment_duration) {
                            $this->due_date = date("Y-m-d", mktime(0, 0, 0, date("m"), (date("d") + $this->payment_duration), date("Y")));
                        } else {
                            $this->due_date = date("Y-m-d", mktime(0, 0, 0, date("m"), date("d"), date("Y")));
                        }
                        $this->payment_parameters['due_date'] = $this->due_date;
                        $this->payment_parameters['end_date'] = $this->due_date;
                    }
                    if ($this->novalnet_payment_method == 'novalnet_paypal') {
                        $this->payment_parameters['api_user'] 		= $this->api_username;
                        $this->payment_parameters['api_pw'] 		= $this->api_password;
                        $this->payment_parameters['api_signature'] 	= $this->api_signature;
                    }
                    if ($this->novalnet_payment_method == 'novalnet_elv_de' || $this->novalnet_payment_method == 'novalnet_elv_at') {
                        $this->payment_parameters['bank_account_holder'] 	= $_SESSION['bank_account_holder'];
                        $this->payment_parameters['bank_account'] 			= $_SESSION['bank_account'];
                        $this->payment_parameters['bank_code'] 				= $_SESSION['bank_code'];
                        if ($this->novalnet_payment_method == 'novalnet_elv_de')
                            $this->payment_parameters['acdc'] = isset($_SESSION['acdc']) ? 1 : 0;
                        unset($_SESSION['bank_account_holder']);
                        unset($_SESSION['bank_account']);
                        unset($_SESSION['bank_code']);
                        if (isset($_SESSION['acdc']))
                            unset($_SESSION['acdc']);
                    }
                    if ($this->novalnet_payment_method == 'novalnet_cc3d' || $this->novalnet_payment_method == 'novalnet_cc') {
                        $this->payment_parameters['cc_holder'] = isset($_SESSION['cc_holder']) ? $_SESSION['cc_holder'] : null;
                        $this->payment_parameters['cc_no'] = isset($_SESSION['cc_number']) ? $_SESSION['cc_number'] : null;
                        $this->payment_parameters['cc_exp_month'] 	= isset($_SESSION['exp_month']) ? $_SESSION['exp_month'] : null;
                        $this->payment_parameters['cc_exp_year'] 	= isset($_SESSION['exp_year']) ? $_SESSION['exp_year'] : null;
                        $this->payment_parameters['cc_cvc2'] 		= isset($_SESSION['cvv_cvc']) ? $_SESSION['cvv_cvc'] : null;
                        if ($this->novalnet_payment_method == 'novalnet_cc') {
                            $this->payment_parameters['unique_id'] 	= $_SESSION['nn_unique_id'];
                            $this->payment_parameters['pan_hash']  	= $_SESSION['nn_cardno_id'];
                        }
                        unset($_SESSION['cc_holder']);
                        unset($_SESSION['cc_number']);
                        unset($_SESSION['exp_month']);
                        unset($_SESSION['exp_year']);
                        unset($_SESSION['cvv_cvc']);
                        unset($_SESSION['nn_unique_id']);
                        unset($_SESSION['nn_cardno_id']);
                    }
                }
				
				/**
				 * Get common payment parameters (for all payment methods)
				 **/ 
                public function get_backend_common_parameter_array($order) {
                    $this->payment_parameters['key'] 		= $this->payment_key;
                    $this->payment_parameters['test_mode'] 	= $this->test_mode;
                    $this->payment_parameters['uniqid'] 	= $this->unique_id;
                    $this->payment_parameters['session'] 	= session_id();
                    $this->payment_parameters['currency'] 	= get_woocommerce_currency();
                    $this->payment_parameters['first_name'] = $order->billing_first_name;
                    $this->payment_parameters['last_name'] 	= $order->billing_last_name;
                    $this->payment_parameters['gender'] 	= 'u';
                    $this->payment_parameters['email'] 		= $order->billing_email;
                    $this->payment_parameters['street'] 	= $order->billing_address_1;
                    $this->payment_parameters['search_in_street'] = 1;
                    $this->payment_parameters['city'] 		= $order->billing_city;
                    $this->payment_parameters['zip'] 		= $order->billing_postcode;
                    $this->payment_parameters['lang'] 		= strtoupper($this->language);
                    $this->payment_parameters['country'] 	= $order->billing_country;
                    $this->payment_parameters['country_code'] = $order->billing_country;
                    $this->payment_parameters['tel'] 		= $order->billing_phone;
                    // $this->payment_parameters['fax'] 	= "";
                    //  $this->payment_parameters['birthday'] = ;
                    $this->payment_parameters['remote_ip'] 	= $this->user_ip;
                    $this->payment_parameters['order_no'] 	= $order->id;
                    $this->payment_parameters['customer_no']= $order->user_id > 0 ? $order->user_id : __('Guest', 'woocommerce-novalnetpayment');
                    $this->payment_parameters['use_utf8'] 	= 1;
                    $this->payment_parameters['amount'] 	= $this->amount;
                }
				
				/**
				 * process data before payport sever
				 **/ 
                public function do_prepare_to_novalnet_payport($order) {
                    if (!isset($_SESSION['novalnet_receipt_page_got'])) {
                        echo '<p>' . __('You will be redirected to Novalnet AG in a few seconds. <br>', 'woocommerce-novalnetpayment') . '<input type="submit" name="enter" id="enter" onClick="document.getElementById(\'enter\').disabled=\'true\';document.forms.frmnovalnet.submit();" value="' . __('Redirecting...', 'woocommerce-novalnetpayment') . '" /></p>';
                        echo $this->get_novalnet_form_html($order);
                        $_SESSION['novalnet_receipt_page_got'] = 1;
                    }
                }
				
				/**
				 * display error and message
				 **/ 
                public function do_check_and_add_novalnet_errors_and_messages($message, $message_type = 'error') {
                    global $woocommerce;
                    switch ($message_type) {
                        case 'error':
                            if (is_object($woocommerce->session))
                                $woocommerce->session->errors 	= $message;
                            else
                                $_SESSION['errors'][] 			= $message;
                            $woocommerce->add_error($message);
                            break;
                        case 'message':
                            if (is_object($woocommerce->session))
                                $woocommerce->session->messages = $message;
                            else
                                $_SESSION['messages'][] 		= $message;
                            $woocommerce->add_message($message);
                            break;
                    }
                }

                /**
                 * Validate credit card form fields
                 **/
                public function do_validate_cc_form_elements($cc_holder, $cc_number, $exp_month, $exp_year, $cvv_cvc, $cc_type = null, $unique_id = null, $pan_hash = null) {
                    global $woocommerce;
                    $error = '';
                    if ($this->novalnet_payment_method == 'novalnet_cc') {
                    if ($cc_holder == '' || $this->is_invalid_holder_name($cc_holder) || (($exp_month == '' || $exp_year == date('Y')) && $exp_month < date('m')) || $exp_year == '' || $exp_year < date('Y') || $cvv_cvc == '' || strlen($cvv_cvc) < 3 || strlen($cvv_cvc) > 4 || !$this->is_digits($cvv_cvc) || $pan_hash == '' || $unique_id == '')
                            $error= true;
                            
						if (!$cc_type)
							$error = true;
						else {
                        switch ($cc_type) {
                            case 'VI': // Visa //
                                if (!preg_match('/^[0-9]{3}$/', $cvv_cvc))
                                    $error = true;
                                break;
                            case 'MC': // Master Card //
                                if (!preg_match('/^[0-9]{3}$/', $cvv_cvc))
                                    $error = true;
                                break;
                            case 'AE': // American Express //
                                if (!preg_match('/^[0-9]{4}$/', $cvv_cvc))
                                    $error = true;
                                break;
                            case 'DI': // Discovery //
                                if (!preg_match('/^[0-9]{3}$/', $cvv_cvc))
                                    $error = true;
                                break;
                            case 'SM': // Switch or Maestro //
                                if (!preg_match('/^[0-9]{3,4}$/', $cvv_cvc))
                                    $error = true;
                                break;
                            case 'SO': // Solo // 
                                if (!preg_match('/^[0-9]{3,4}$/', $cvv_cvc))
                                    $error = true;
                                break;
                            case 'JCB': // JCB // 
                                if (!preg_match('/^[0-9]{4}$/', $cvv_cvc))
                                    $error = true;
                                break;
                        }
					}
                        
                    }
                    elseif ($this->novalnet_payment_method == 'novalnet_cc3d') {
                        if ($cc_holder == '' || $this->is_invalid_holder_name($cc_holder) || $cc_number == '' || strlen($cc_number) < 12 || !$this->is_digits($cc_number) || (($exp_month == '' || $exp_year == date('Y')) && $exp_month < date('m')) || $exp_year == '' || $exp_year < date('Y') || $cvv_cvc == '' || strlen($cvv_cvc) < 3 || strlen($cvv_cvc) > 4 || !$this->is_digits($cvv_cvc))
                           $error = true;
					}
                    if ($error) {
                        $this->do_check_and_add_novalnet_errors_and_messages(__('Please enter valid credit card details!', 'woocommerce-novalnetpayment'), 'error');
                         return($this->do_return_redirect_page_for_pay_or_thanks_page('success', $woocommerce->cart->get_checkout_url()));
                    } else {
                        $_SESSION['cc_holder'] 	= $cc_holder;
                        $_SESSION['cc_number'] 	= $cc_number;
                        $_SESSION['exp_month'] 	= $exp_month;
                        $_SESSION['exp_year'] 	= $exp_year;
                        $_SESSION['cvv_cvc'] 	= $cvv_cvc;
                        if ($this->novalnet_payment_method == 'novalnet_cc') {
                            $_SESSION['nn_unique_id'] = $unique_id;
                            $_SESSION['nn_cardno_id'] = $pan_hash;
                        }
                        return('');
                    }
                }

                /**
                 * validate Direct Debit form fields
                 **/
                public function do_validate_elv_at_elv_de_form_elements($bank_account_holder, $bank_account, $bank_code, $acdc = '') {
                    global $woocommerce;
                    $error = '';
                    if ($bank_account_holder == '' || $this->is_invalid_holder_name($bank_account_holder) || $bank_account == '' || strlen($bank_account) < 5 || !$this->is_digits($bank_account) || $bank_code == '' || strlen($bank_code) < 3 || !$this->is_digits($bank_code)) {
                        $error = __('Please enter valid account details!', 'woocommerce-novalnetpayment');
                    } else if ($this->novalnet_payment_method == 'novalnet_elv_de' && $this->acdc == 'yes' && $acdc == '') {
                        $error = __('Please enable credit rating check', 'woocommerce-novalnetpayment');
                    }
                    if ($error) {
                        $this->do_check_and_add_novalnet_errors_and_messages($error, 'error');
                        return($this->do_return_redirect_page_for_pay_or_thanks_page('success', $woocommerce->cart->get_checkout_url()));
                    } else {
                        $_SESSION['bank_account_holder'] = $bank_account_holder;
                        $_SESSION['bank_account'] = $bank_account;
                        $_SESSION['bank_code'] = $bank_code;
                        if (isset($acdc))
                            $_SESSION['acdc'] = $acdc;
                        return('');
                    }
                }
				
				/**
				 * process novalnet payment methods
				 **/ 
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
                        } else {
                            return($this->do_make_second_call_for_novalnet_telephone($order_id));
                        }
                    } else if (in_array($this->novalnet_payment_method, $this->return_url_parameter_for_array)) {
                        if ($this->novalnet_payment_method == 'novalnet_cc3d') {
                            $return = $this->do_validate_cc_form_elements(trim($_REQUEST['cc3d_holder'], '&'), str_replace(' ', '', $_REQUEST['cc3d_number']), $_REQUEST['cc3d_exp_month'], $_REQUEST['cc3d_exp_year'], str_replace(' ', '', $_REQUEST['cvv_cvc']));
                            if ($return)
                                return($return);
                        }
                        return($this->do_return_redirect_page_for_pay_or_thanks_page('success', $this->do_build_redirect_url($order, 'pay')));
                    } else {
                        if ($this->novalnet_payment_method == 'novalnet_cc') {
                            $return = $this->do_validate_cc_form_elements(trim($_REQUEST['cc_holder'], '&'), null, $_REQUEST['cc_exp_month'], $_REQUEST['cc_exp_year'], str_replace(' ', '', $_REQUEST['cc_cvv_cvc']), $_REQUEST['cc_type'], $_REQUEST['nn_unique_id'], $_REQUEST['nn_cardno_id']);
                            if ($return)
                                return($return);
                        }
                        else if ($this->novalnet_payment_method == 'novalnet_elv_at') {
                            $return = $this->do_validate_elv_at_elv_de_form_elements(trim($_REQUEST['bank_account_holder_at'], '&'), str_replace(' ', '', $_REQUEST['bank_account_at']), str_replace(' ', '', $_REQUEST['bank_code_at']));
                            if ($return)
                                return($return);
                        } else if ($this->novalnet_payment_method == 'novalnet_elv_de') {
                            $return = $this->do_validate_elv_at_elv_de_form_elements(trim($_REQUEST['bank_account_holder_de'], '&'), str_replace(' ', '', $_REQUEST['bank_account_de']), str_replace(' ', '', $_REQUEST['bank_code_de']), @$_REQUEST['acdc']);
                            if ($return)
                                return($return);
                        }
                        $this->do_necessary_actions_before_prepare_to_novalnet_payport_or_paygate($order);
                        $aryResponse = $this->do_prepare_to_novalnet_paygate($order);
                        return($this->do_check_novalnet_status($aryResponse, $order_id));
                    }
                }
				
				/**
				 * get url for direct form payment methods
				 **/ 
                public function do_return_redirect_page_for_pay_or_thanks_page($result, $redirect_url) {
                    return array(
                        'result' => $result,
                        'redirect' => $redirect_url
                    );
                }
				
				/**
				 * Validate back-end data
				 **/ 
                public function do_check_novalnet_backend_data_validation_from_frontend() {
                    global $woocommerce;
                    $error = '';
                    if (!$this->vendor_id || !$this->product_id || !$this->tariff_id || !$this->auth_code || (isset($this->key_password) && !$this->key_password) || (isset($this->api_username) && !$this->api_username) || (isset($this->api_password) && !$this->api_password) || (isset($this->api_signature) && !$this->api_signature)) {
                        $error = __('Basic Parameter Missing', 'woocommerce-novalnetpayment');
                    }

                    if (isset($this->manual_check_limit) && $this->manual_check_limit > 0) {
                        if (empty($this->product_id_2) || empty($this->tariff_id_2)) {
                            $error = __('Product-ID2 and/or Tariff-ID2 missing!', 'woocommerce-novalnetpayment');
                        }
                    } elseif (!empty($this->product_id_2) || !empty($this->tariff_id_2)) {
                        $error = __('Manual Check limit field missing!', 'woocommerce-novalnetpayment');
                    }
                    if ($error) {
                        $this->do_check_and_add_novalnet_errors_and_messages($error, 'error');
                        wp_safe_redirect($woocommerce->cart->get_checkout_url());
                        exit();
                    }
                }
				
				/**
				 * build redirect url for direct form payment methods
				 **/ 
                public function do_build_redirect_url($order, $page) {
                    return(add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, get_permalink(woocommerce_get_page_id($page)))));
                }
				
				/**
				 * Get pci compliant secure credit card form from Novalnet server 
				 **/ 
                public function do_check_is_any_request_to_print_cc_iframe() {
                    global $woocommerce;
                    if ($this->novalnet_payment_method == 'novalnet_cc' && !strstr(@$_SERVER['HTTP_REFERER'], 'wp-admin')) {
                        $this->payport_or_paygate_form_display = $this->payment_details['novalnet_cc']['payport_or_paygate_form_display'];
                        $form_parameters = array(
                            'nn_lang_nn' => strtoupper($this->language),
                            'nn_vendor_id_nn' => $this->vendor_id,
                            'nn_product_id_nn' => $this->product_id,
                            'nn_payment_id_nn' => $this->payment_key,
                        );
                        list($errno, $errmsg, $data) = $this->perform_https_request($this->payport_or_paygate_form_display, $form_parameters);
                        file_put_contents(ABSPATH . '/wp-content/plugins/woocommerce-novalnet-gateway/includes/novalnet_cc_iframe.html', $data);
                    }
                }
				
				/**
				 * Display direct debit form fields
				 **/ 
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
                    }
                    $payment_field_html.='<div style="clear:both;">&nbsp;</div></div>';
                    return($payment_field_html);
                }
				
				/**
				 * validate novalnet configuration parameter
				 **/ 
                public function novalnet_backend_validation_from_backend($request) {
                    $vendor_id = $request['woocommerce_' . $this->novalnet_payment_method . '_merchant_id'];
                    $auth_code = $request['woocommerce_' . $this->novalnet_payment_method . '_auth_code'];
                    $product_id = $request['woocommerce_' . $this->novalnet_payment_method . '_product_id'];
                    $tariff_id = $request['woocommerce_' . $this->novalnet_payment_method . '_tariff_id'];
                    $payment_duration = @$request['woocommerce_' . $this->novalnet_payment_method . '_payment_duration'];
                    $key_password = @$request['woocommerce_' . $this->novalnet_payment_method . '_key_password'];
                    $api_username = @$request['woocommerce_' . $this->novalnet_payment_method . '_api_username'];
                    $api_password = @$request['woocommerce_' . $this->novalnet_payment_method . '_api_password'];
                    $api_signature = @$request['woocommerce_' . $this->novalnet_payment_method . '_api_signature'];
                    $manual_check_limit = @$request['woocommerce_' . $this->novalnet_payment_method . '_manual_check_limit'];
                    $product_id_2 = @$request['woocommerce_' . $this->novalnet_payment_method . '_product_id_2'];
                    $tariff_id_2 = @$request['woocommerce_' . $this->novalnet_payment_method . '_tariff_id_2'];
                    foreach ($this->language_supported_array as $language) {
                        if (!$request['woocommerce_' . $this->novalnet_payment_method . '_title_' . $language])
                            return(__('Please enter valid Payment Title', 'woocommerce-novalnetpayment'));
                    }
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
                        if (isset($product_id_2) && (!$product_id_2 || !$this->is_digits($product_id_2))) {
                            return(__('Please enter valid Novalnet Second Product ID', 'woocommerce-novalnetpayment'));
                        }
                        if (isset($tariff_id_2) && (!$tariff_id_2 || !$this->is_digits($tariff_id_2))) {
                            return(__('Please enter valid Novalnet Second Tariff ID', 'woocommerce-novalnetpayment'));
                        }
                    } else if (isset($product_id_2) && $product_id_2 && $this->is_digits($product_id_2)) {
                        if (isset($manual_check_limit) && ($manual_check_limit == '' || !$this->is_digits($manual_check_limit))) {
                            return(__('Please enter valid Manual checking amount', 'woocommerce-novalnetpayment'));
                        }
                        if (isset($tariff_id_2) && ($tariff_id_2 == '' || !$this->is_digits($tariff_id_2))) {
                            return(__('Please enter valid Novalnet Second Tariff ID', 'woocommerce-novalnetpayment'));
                        }
                    } else if (isset($tariff_id_2) && $tariff_id_2 && $this->is_digits($tariff_id_2)) {
                        if (isset($manual_check_limit) && ($manual_check_limit == '' || !$this->is_digits($manual_check_limit))) {
                            return(__('Please enter valid Manual checking amount', 'woocommerce-novalnetpayment'));
                        }
                        if (isset($product_id_2) && ($product_id_2 == '' || !$this->is_digits($product_id_2))) {
                            return(__('Please enter valid Novalnet Second Product ID', 'woocommerce-novalnetpayment'));
                        }
                    }
                    return('');
                }
				
				/**
				 * Validate payment gateway settings
				 **/ 
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
                        }
                    } else if (isset($request['saved']) && isset($_GET['wc_error'])) {
                        $redirect = get_admin_url() . 'admin.php?' . http_build_query($_GET);
                        $redirect = remove_query_arg('wc_error');
                        $redirect = add_query_arg('saved', urlencode(esc_attr('true')), $redirect);
                        wp_safe_redirect($redirect);
                        exit();
                    }
                }
				
				/**
				 * list order status
				 **/ 
                public function list_order_statuses() {
                    global $wpdb;
                    $sql = "select name, slug from $wpdb->terms where term_id in (select term_id from $wpdb->term_taxonomy where taxonomy='%s')";
                    $row = $wpdb->get_results($wpdb->prepare($sql, 'shop_order_status'));
                    for ($i = 0, $order_statuses = array(); $i < count($row); $i++) {
                        $order_statuses[$row[$i]->slug] = __($row[$i]->name, 'woocommerce');
                    }
                    return($order_statuses);
                }
				
				/**
				 * Validate order status
				 **/ 
                public function do_check_novalnet_order_status() {
                    if (in_array($this->order_status, array('failed')))
                        return(false);
                    return(true);
                }
				
				/**
				 * Initialize language for payment methods
				 */ 
                public function do_initialize_novalnet_language() {
                    $language_locale = get_bloginfo('language');
                    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest') {
                        $language_locale = $_SESSION['novalnet_language'];
                    } else {
                        $_SESSION['novalnet_language'] = $language_locale;
                    }
                    $this->language = strtoupper(substr($language_locale, 0, 2)) ? strtoupper(substr($language_locale, 0, 2)) : 'en';
                    $this->language = in_array(strtolower($this->language), $this->language_supported_array) ? $this->language : 'en';
                }
				
				/**
				 * trim server resonse
				 **/ 
                public function do_trim_array_values(&$array) {
                    if (isset($array) && is_array($array))
                        foreach ($array as $key => $val) {
                            if (!is_array($val))
                                $array[$key] = trim($val);
                        }
                }
				
				/**
				 * set-up configuration details  for payment methods
				 **/
                public function do_make_payment_details_array() {
                    $this->payment_details = array(
                        'novalnet_banktransfer'		=> array(
                            'payment_key' 				=> 33,
                            'payport_or_paygate_url' 	=> $this->novalnet_online_transfer_payport,
                            'second_call_url' 			=> '',
                            'payment_name' 				=> __('Instant Bank Transfer', 'woocommerce-novalnetpayment'),
                            'payment_logo' 				=> __('www.novalnet.de/img/sofort_Logo_en.png', 'woocommerce-novalnetpayment')
                        ),
                        'novalnet_cc' 				=> array(
                            'payment_key' 				=> $this->payment_key_for_cc_family,
                            'payport_or_paygate_url' 	=> $this->novalnet_paygate_url,
                            'payport_or_paygate_form_display' => 'https://payport.novalnet.de/direct_form.jsp',
                            'second_call_url' 			=> '',
                            'payment_name' 				=> __('Credit Card', 'woocommerce-novalnetpayment'),
                            'payment_logo' 				=> __('www.novalnet.de/img/creditcard_small.jpg', 'woocommerce-novalnetpayment')
                        ),
                        'novalnet_cc_pci' 			=> array(
                            'payment_key' 				=> $this->payment_key_for_cc_family,
                            'payport_or_paygate_url' 	=> $this->novalnet_pci_payport_url,
                            'second_call_url' 			=> '',
                            'payment_name' 				=> __('Credit Card PCI', 'woocommerce-novalnetpayment'),
                            'payment_logo' 				=> __('www.novalnet.de/img/creditcard_small.jpg', 'woocommerce-novalnetpayment')
                        ),
                        'novalnet_cc3d' => array(
                            'payment_key' => $this->payment_key_for_cc_family,
                            'payport_or_paygate_url' => 'https://payport.novalnet.de/global_pci_payport',
                            'second_call_url' => '',
                            'payment_name' => __('Credit Card 3D Secure', 'woocommerce-novalnetpayment'),
                            'payment_logo' => __('www.novalnet.de/img/creditcard_small.jpg', 'woocommerce-novalnetpayment')
                        ),
                        'novalnet_elv_at' => array(
                            'payment_key' => $this->payment_key_for_at_family,
                            'payport_or_paygate_url' => $this->novalnet_paygate_url,
                            'second_call_url' => '',
                            'payment_name' => __('Direct Debit Austria', 'woocommerce-novalnetpayment'),
                            'payment_logo' => __('www.novalnet.de/img/ELV_Logo.png', 'woocommerce-novalnetpayment')
                        ),
                        'novalnet_elv_at_pci' => array(
                            'payment_key' => $this->payment_key_for_at_family,
                            'payport_or_paygate_url' => $this->novalnet_pci_payport_url,
                            'second_call_url' => '',
                            'payment_name' => __('Direct Debit Austria PCI', 'woocommerce-novalnetpayment'),
                            'payment_logo' => __('www.novalnet.de/img/ELV_Logo.png', 'woocommerce-novalnetpayment')
                        ),
                        'novalnet_elv_de' => array(
                            'payment_key' => $this->payment_key_for_de_family,
                            'payport_or_paygate_url' => $this->novalnet_paygate_url,
                            'second_call_url' => '',
                            'payment_name' => __('Direct Debit German', 'woocommerce-novalnetpayment'),
                            'payment_logo' => __('www.novalnet.de/img/ELV_Logo.png', 'woocommerce-novalnetpayment')
                        ),
                        'novalnet_elv_de_pci' => array(
                            'payment_key' => $this->payment_key_for_de_family,
                            'payport_or_paygate_url' => $this->novalnet_pci_payport_url,
                            'second_call_url' => '',
                            'payment_name' => __('Direct Debit German PCI', 'woocommerce-novalnetpayment'),
                            'payment_logo' => __('www.novalnet.de/img/ELV_Logo.png', 'woocommerce-novalnetpayment')
                        ),
                        'novalnet_ideal' => array(
                            'payment_key' => 49,
                            'payport_or_paygate_url' => $this->novalnet_online_transfer_payport,
                            'second_call_url' => '',
                            'payment_name' => __('iDEAL', 'woocommerce-novalnetpayment'),
                            'payment_logo' => __('www.novalnet.de/img/ideal_payment_small.png', 'woocommerce-novalnetpayment')
                        ),
                        'novalnet_invoice' => array(
                            'payment_key' => $this->payment_key_for_invoice_prepayment,
                            'payport_or_paygate_url' => $this->novalnet_paygate_url,
                            'second_call_url' => '',
                            'payment_name' => __('Invoice', 'woocommerce-novalnetpayment'),
                            'payment_logo' => __('www.novalnet.de/img/kauf-auf-rechnung.jpg', 'woocommerce-novalnetpayment')
                        ),
                        'novalnet_paypal' => array(
                            'payment_key' => 34,
                            'payport_or_paygate_url' => 'https://payport.novalnet.de/paypal_payport',
                            'second_call_url' => '',
                            'payment_name' => __('PayPal', 'woocommerce-novalnetpayment'),
                            'payment_logo' => __('www.novalnet.de/img/paypal-small.png', 'woocommerce-novalnetpayment')
                        ),
                        'novalnet_prepayment' => array(
                            'payment_key' => $this->payment_key_for_invoice_prepayment,
                            'payport_or_paygate_url' => $this->novalnet_paygate_url,
                            'second_call_url' => '',
                            'payment_name' => __('Prepayment', 'woocommerce-novalnetpayment'),
                            'payment_logo' => __('www.novalnet.de/img/vorauskasse.jpg', 'woocommerce-novalnetpayment')
                        ),
                        'novalnet_tel' => array(
                            'payment_key' => 18,
                            'payport_or_paygate_url' => $this->novalnet_paygate_url,
                            'second_call_url' => 'https://payport.novalnet.de/nn_infoport.xml',
                            'payment_name' => __('Telephone Payment', 'woocommerce-novalnetpayment'),
                            'payment_logo' => __('www.novalnet.de/img/novaltel_logo.png', 'woocommerce-novalnetpayment')
                        )
                    );
                }
				
				/**
				 * Assign variables to payment parameters
				 **/ 
                public function do_assign_config_vars_to_members() {
                    $this->do_trim_array_values($this->settings);
                    $this->do_make_payment_details_array();
                    $this->test_mode = $this->settings['test_mode'];
                    $this->vendor_id = $this->settings['merchant_id'];
                    $this->auth_code = $this->settings['auth_code'];
                    $this->product_id = $this->settings['product_id'];
                    $this->tariff_id = $this->settings['tariff_id'];
                    $this->order_status = $this->settings['order_status'];
                    $this->payment_key = $this->payment_details[$this->novalnet_payment_method]['payment_key'];
                    $this->payport_or_paygate_url = $this->payment_details[$this->novalnet_payment_method]['payport_or_paygate_url'];
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
                    $this->novalnet_logo = $this->settings['novalnet_logo'];
                    $this->payment_logo = $this->settings['payment_logo'];
                    $this->icon = (is_ssl() ? 'https://' : 'http://') . $this->payment_details[$this->novalnet_payment_method]['payment_logo'];
                }
				
				/**
				 * Validate account digits
				 **/ 
                public function is_digits($element) {
                    return(preg_match("/^[0-9]+$/", $element));
                }
				
				/**
				 * Validate account holder name
				 **/ 
                public function is_invalid_holder_name($element) {
                    return preg_match("/[#%\^<>@$=*!]/", $element);
                }
				
				/**
				 * Format amount in cents
				 **/ 
                public function do_format_amount($amount) {
                    $this->amount = str_replace(',', '', number_format($amount, 2)) * 100;
                }

                /**
                 * Assign Manual Check-Limit 
                 **/
                public function do_check_and_assign_manual_check_limit() {
                    if (isset($this->manual_check_limit) && $this->manual_check_limit && $this->amount >= $this->manual_check_limit) {
                        if ($this->product_id_2 && $this->tariff_id_2) {
                            $this->product_id = $this->product_id_2;
                            $this->tariff_id = $this->tariff_id_2;
                        }
                    }
                }
				
				/**
				 * Get Server Response message
				 **/ 
                public function do_get_novalnet_response_text($request) {
                    return($request['status_text'] ? $request['status_text'] : ($request['status_desc'] ? $request['status_desc'] : __('Successful', 'woocommerce-novalnetpayment')));
                }

                /*
                 * Successful payment
                 */
                public function do_novalnet_success($request, $message) {
                    global $woocommerce, $wp_taxonomies, $wpdb;
                    $this->do_trim_array_values($request);
                    $order_no = $request['order_no'];
                    $GLOBALS['wp_rewrite'] = new WP_Rewrite();
                    if (in_array($this->novalnet_payment_method, $this->encode_applicable_for_array))
                        $request['test_mode'] = $this->decode($request['test_mode']);
                    $order = new WC_Order($order_no);
                    $this->post_back_param($request, $order_no);
                    $new_line = "\n";
                    $novalnet_comments = $this->title . $new_line;
                    $novalnet_comments .= __('Novalnet Transaction ID', 'woocommerce-novalnetpayment') . ': ' . $request['tid'] . $new_line;
                    $novalnet_comments .= ((isset($request['test_mode']) && $request['test_mode'] == 1) || (isset($this->test_mode) && $this->test_mode == 1)) ? __('Test order', 'woocommerce-novalnetpayment') : '';
                    if ($this->novalnet_payment_method == 'novalnet_invoice' || $this->novalnet_payment_method == 'novalnet_prepayment') {
                        $novalnet_comments .= $request['test_mode'] ? $new_line . $new_line : $new_line;
                        $novalnet_comments .= __('Please transfer the amount to the following information to our payment service Novalnet AG', 'woocommerce-novalnetpayment') . $new_line;
                        if ($this->payment_duration) {
                            $novalnet_comments.= __('Due date', 'woocommerce-novalnetpayment') . " : " . date_i18n(get_option('date_format'), strtotime($this->due_date)) . $new_line;
                        }
                        $novalnet_comments.= __('Account holder : Novalnet AG', 'woocommerce-novalnetpayment') . $new_line;
                        $novalnet_comments.= __('Account number', 'woocommerce-novalnetpayment') . " : " . $request['invoice_account'] . $new_line;
                        $novalnet_comments.= __('Bankcode', 'woocommerce-novalnetpayment') . " : " . $request['invoice_bankcode'] . $new_line;
                        $novalnet_comments.= __('Bank', 'woocommerce-novalnetpayment') . " : " . $request['invoice_bankname'] . " " . trim($request['invoice_bankplace']) . $new_line;
                        $novalnet_comments.= __('Amount', 'woocommerce-novalnetpayment') . " : " . strip_tags($order->get_formatted_order_total()) . $new_line;
                        $novalnet_comments.= __('Reference : TID', 'woocommerce-novalnetpayment') . " " . $request['tid'] . $new_line . $new_line;
                        $novalnet_comments.= __('Only for international transfers :', 'woocommerce-novalnetpayment') . $new_line;
                        $novalnet_comments.= __('IBAN', 'woocommerce-novalnetpayment') . " : " . $request['invoice_iban'] . $new_line;
                        $novalnet_comments.= __('SWIFT / BIC', 'woocommerce-novalnetpayment') . " : " . $request['invoice_bic'] . $new_line;
                    }
                    $GLOBALS['novalnet_comments'] = $novalnet_comments;
                    if ($order->customer_note)
                        $order->customer_note.= $new_line;
                    if ($this->novalnet_payment_method == 'novalnet_invoice' || $this->novalnet_payment_method == 'novalnet_prepayment') {
                        $order->customer_note .= html_entity_decode($novalnet_comments, ENT_QUOTES, 'UTF-8');
                        if (version_compare($woocommerce->version, '2.0.0', '<'))
                            $order->customer_note = utf8_encode($order->customer_note);
                    }
                    else
                        $order->customer_note .= html_entity_decode($novalnet_comments, ENT_QUOTES, 'UTF-8');
                    $sql = "update $wpdb->posts set post_excerpt='" . $wpdb->escape($order->customer_note) . "' where ID='$order_no'";
                    $row = $wpdb->query($sql);
                    $order->customer_note = nl2br($order->customer_note);
                    $order->update_status($this->order_status, $message);

                    // Reduce stock levels
                    $order->reduce_order_stock();

                    // Remove cart
                    $woocommerce->cart->empty_cart();

                    // Empty awaiting payment session
                    unset($_SESSION['order_awaiting_payment']);
                    $this->do_check_and_add_novalnet_errors_and_messages($message, 'message');

                    //	Return thankyou redirect
                    if (in_array($this->novalnet_payment_method, $this->return_url_parameter_for_array)) {
                        wp_safe_redirect(add_query_arg('key', $order->order_key, add_query_arg('order', $order_no, get_permalink(woocommerce_get_page_id('thanks')))));
                        exit();
                    } else {
                        $this->do_unset_novalnet_telephone_sessions();
                        return($this->do_return_redirect_page_for_pay_or_thanks_page('success', $this->do_build_redirect_url($order, 'thanks')));
                    }
                }

                /**
                 * Transfer data via curl library (consists of various protocols)
                 **/
                public function perform_https_request($url, $form) {
                    global $globaldebug;
                    ## requrl: the URL executed later on
                    if ($globaldebug)
                        print "<BR>perform_https_request: $url<BR>\n\r\n";
                    if ($globaldebug)
                        print "perform_https_request: $form<BR>\n\r\n";
                    ## some prerquisites for the connection
                    $ch = curl_init($url);
                    curl_setopt($ch, CURLOPT_POST, 1);  // a non-zero parameter tells the library to do a regular HTTP post.
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $form);  // add POST fields
                    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);  // don't allow redirects
                    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);  // decomment it if you want to have effective ssl checking
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);  // decomment it if you want to have effective ssl checking
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);  // return into a variable
                    curl_setopt($ch, CURLOPT_TIMEOUT, 240);  // maximum time, in seconds, that you'll allow the CURL functions to take
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
                        echo "\n\n\nperform_https_request: cURL error:" . $error . "<BR>\n";
                    }
                    #close connection
                    curl_close($ch);
                    ## read and return data from novalnet paygate
                    if ($globaldebug)
                        print "<BR>\n" . $data;
                    return array($errno, $errmsg, $data);
                }
				
				/**
				 * Generate Hash parameter value
				 **/ 
                public function hash($h) { #$h contains encoded data
                    if (!$h)
                        return'Error: no data';
                    if (!function_exists('md5')) {
                        return'Error: func n/a';
                    }
                    return md5($h['authcode'] . $h['product_id'] . $h['tariff'] . $h['amount'] . $h['test_mode'] . $h['uniqid'] . strrev($this->key_password));
                }
				
				/**
				 * Validate Hash parameter
				 **/ 
                public function checkHash(&$request) {
                    if ($this->novalnet_payment_method == 'novalnet_cc' || $this->novalnet_payment_method == 'novalnet_cc_pci' || $this->novalnet_payment_method == 'novalnet_elv_at_pci' || $this->novalnet_payment_method == 'novalnet_elv_de_pci') {
                        $h['authcode'] 		= $request['vendor_authcode']; #encoded
                        $h['product_id'] 	= $request['product_id']; #encoded
                        $h['tariff'] 		= $request['tariff_id']; #encoded
                    } else {
                        $h['authcode'] 		= $request['auth_code']; #encoded
                        $h['product_id'] 	= $request['product']; #encoded
                        $h['tariff'] 		= $request['tariff']; #encoded
                    }
                    $h['amount'] 			= $request['amount']; #encoded
                    $h['test_mode'] 		= $request['test_mode']; #encoded
                    $h['uniqid'] 			= $request['uniqid']; #encoded
                    if (!$request)
                        return false;#'Error: no data';
                    if ($request['hash2'] != $this->hash($h)) {
                        return false;
                    }
                    return true;
                }
				
				/**
				 * Encode payment parameters
				 **/ 
                public function encode($data) {
                    $data = trim($data);
                    if ($data == '')
                        return'Error: no data';
                    if (!function_exists('base64_encode') or !function_exists('pack') or !function_exists('crc32')) {
                        return'Error: func n/a';
                    }
                    try {
                        $crc = sprintf('%u', crc32($data)); # %u is a must for ccrc32 returns a signed value
                        $data = $crc . "|" . $data;
                        $data = bin2hex($data . $this->key_password);
                        $data = strrev(base64_encode($data));
                    } catch (Exception $e) {
                        echo('Error: ' . $e);
                    }
                    return $data;
                }
				
				/**
				 * Decode payment parameters
				 **/ 
                public function decode($data) {
                    $data = trim($data);
                    if ($data == '') {
                        return'Error: no data';
                    }
                    if (!function_exists('base64_decode') or !function_exists('pack') or !function_exists('crc32')) {
                        return'Error: func n/a';
                    }
                    try {
                        $data = base64_decode(strrev($data));
                        $data = pack("H" . strlen($data), $data);
                        $data = substr($data, 0, stripos($data, $this->key_password));
                        $pos = strpos($data, "|");
                        if ($pos === false) {
                            return("Error: CKSum not found!");
                        }
                        $crc = substr($data, 0, $pos);
                        $value = trim(substr($data, $pos + 1));
                        if ($crc != sprintf('%u', crc32($value))) {
                            return("Error; CKSum invalid!");
                        }
                        return $value;
                    } catch (Exception $e) {
                        echo('Error: ' . $e);
                    }
                }
				
				/**
				 * Validate current user's IP address
				 **/ 
                public function isPublicIP($value) {
                    if (!$value || count(explode('.', $value)) != 4)
                        return false;
                    return !preg_match('~^((0|10|172\.16|192\.168|169\.254|255|127\.0)\.)~', $value);
                }

                /**
                 * Get the real Ip Adress of the User
				**/
                public function getRealIpAddr() {
                    if ($this->isPublicIP(@$_SERVER['HTTP_X_FORWARDED_FOR']))
                        return @$_SERVER['HTTP_X_FORWARDED_FOR'];
                    if ($iplist = explode(',', @$_SERVER['HTTP_X_FORWARDED_FOR'])) {
                        if ($this->isPublicIP($iplist[0]))
                            return $iplist[0];
                    }
                    if ($this->isPublicIP(@$_SERVER['HTTP_CLIENT_IP']))
                        return @$_SERVER['HTTP_CLIENT_IP'];
                    if ($this->isPublicIP(@$_SERVER['HTTP_X_CLUSTER_CLIENT_IP']))
                        return @$_SERVER['HTTP_X_CLUSTER_CLIENT_IP'];
                    if ($this->isPublicIP(@$_SERVER['HTTP_FORWARDED_FOR']))
                        return @$_SERVER['HTTP_FORWARDED_FOR'];
                    return @$_SERVER['REMOTE_ADDR'];
                }
				
				/**
				 * Order Cancellation
				 **/ 
                public function do_novalnet_cancel($request, $message, $order_no) {
                    global $woocommerce, $wp_taxonomies, $wpdb;
                    $GLOBALS['wp_rewrite'] = new WP_Rewrite();
                    $wp_taxonomies['shop_order_status'] = '';
                    $order = new WC_Order($order_no);
                    $new_line 						 = "\n";
                    $novalnet_comments 				 = $this->title . $new_line;
                    $novalnet_comments 				.= $message . $new_line;
                    $GLOBALS['novalnet_comments'] 	 = $novalnet_comments;
                    if ($order->customer_note)
                        $order->customer_note		.= $new_line;
                    if ($this->novalnet_payment_method == 'novalnet_invoice' || $this->novalnet_payment_method == 'novalnet_prepayment') {
                        $order->customer_note 		.= html_entity_decode($novalnet_comments, ENT_QUOTES, 'UTF-8');
                        if (version_compare($woocommerce->version, '2.0.0', '<'))
                            $order->customer_note 	 = utf8_encode($order->customer_note);
                    }
                    else
                        $order->customer_note .= html_entity_decode($novalnet_comments, ENT_QUOTES, 'UTF-8');
                    $sql = "update $wpdb->posts set post_excerpt='" . $wpdb->escape($order->customer_note) . "' where ID='$order_no'";
                    $row = $wpdb->query($sql);
                    $order->customer_note = nl2br($order->customer_note);
                    $order->cancel_order($message);
                    // Message
                    $this->do_check_and_add_novalnet_errors_and_messages($message, 'error');
                    do_action('woocommerce_cancelled_order', $request['order_no']);
                    $this->do_unset_novalnet_telephone_sessions();
                    return($this->do_return_redirect_page_for_pay_or_thanks_page('success', $woocommerce->cart->get_checkout_url()));
                }
				
				/**
				 * Process Novalnet server response
				 **/ 
                public function do_check_novalnet_status($request, $nn_order_id) {
                    if ($request['status']) {
                        if ($request['status'] == 100) {
                            return($this->do_novalnet_success($request, $this->do_get_novalnet_response_text($request)));
                        } else {
                            return($this->do_novalnet_cancel($request, $this->do_get_novalnet_response_text($request), $nn_order_id));
                        }
                    }
                }
				
				/**
				 * Order cancellation for direct form payment
				 **/ 
                public function do_nn_cancel($request, $message) {
                    global $woocommerce, $wp_taxonomies, $wpdb;
                    $GLOBALS['wp_rewrite'] = new WP_Rewrite();
                    $order_no = $request['order_no'];
                    $order = new WC_Order($order_no);
                    $new_line = "\n";
                    $novalnet_comments 			 	 = $this->title . $new_line;
                    $novalnet_comments 				.= $message . $new_line;
                    $GLOBALS['novalnet_comments'] 	 = $novalnet_comments;
                    if ($order->customer_note)
                        $order->customer_note		.= $new_line;
                    $order->customer_note 			.= html_entity_decode($novalnet_comments, ENT_QUOTES, 'UTF-8');
                    $sql = "update $wpdb->posts set post_excerpt='" . $wpdb->escape($order->customer_note) . "' where ID='$order_no'";
								$row 				 = $wpdb->query($sql);
                    $order->customer_note 			 = nl2br($order->customer_note);
                    $order->cancel_order($message);
                    // Message
                    do_action('woocommerce_cancelled_order', $order_no);
                    $this->do_check_and_add_novalnet_errors_and_messages($message, 'error');
                    $this->do_unset_novalnet_telephone_sessions();
                    wp_safe_redirect($woocommerce->cart->get_checkout_url());
                    exit();
                }
				
				/**
				 * Process order status repsonse
				 **/ 
                public function do_check_nn_status($request) {
                    if (isset($request['status'])) {
                        if ($request['novalnet_payment_method'] == 'novalnet_paypal' && $request['status'] == 90) {
							 $this->order_status = 'processing';
                            return($this->do_novalnet_success($request, $this->do_get_novalnet_response_text($request)));
                        }
                        if ($request['status'] == 100) {
                            return($this->do_novalnet_success($request, $this->do_get_novalnet_response_text($request)));
                        } else {
                            return($this->do_nn_cancel($request, $this->do_get_novalnet_response_text($request)));
                        }
                    }
                }
				
				/**
				 * validate novalnet server response
				 **/ 
                public function do_check_novalnet_payment_status() {
                    if (isset($_REQUEST['hash'])) {
                        if (!$this->checkHash($_REQUEST)) {
                            $message = $this->do_get_novalnet_response_text($_REQUEST) . ' - ' . __('Check Hash failed.', 'woocommerce-novalnetpayment');
                            $this->do_nn_cancel($_REQUEST, $message);
                        } else {
                            $this->do_check_nn_status($_REQUEST);
                        }
                    } else {
                        $this->do_check_nn_status($_REQUEST);
                    }
                }

                /**
                 * Send acknowledgement parameters to Novalnet server after payment success 
                 **/
                public function post_back_param($request, $order_id) {
                    $urlparam = 'vendor=' . $this->vendor_id . '&product=' . $this->product_id . '&key=' . $this->payment_key . '&tariff=' . $this->tariff_id . '&auth_code=' . $this->auth_code;
                    $urlparam .= '&status=100&tid=' . $request['tid'] . '&vwz2=' . $order_id . '&vwz3=' . date('Y-m-d H:i:s') . '&order_no=' . $order_id;
                    list($errno, $errmsg, $data) = $this->perform_https_request($this->novalnet_paygate_url, $urlparam);
                }

                /**
                 * Constructor for the Novalnet gateway
                 **/
                public function __construct() {
                    global $woocommerce;
                    @session_start();
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
                    if (!$this->do_check_novalnet_order_status())
                        $this->enabled = false;
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
                    $this->do_check_is_any_request_to_print_cc_iframe();
                }

                public function thankyou_page() {
                }

                public function set_current() {
                    $this->chosen = true;
                }

                /*
                 * Displays payment method icon
                 */

                public function get_icon() {
                    $icon_html = '';
                    if ($this->payment_logo)
                        $icon_html = '<a href="' . (strtolower($this->language) == 'de' ? 'https://www.novalnet.de' : 'http://www.novalnet.com') . '" alt="' . __('novalnet.com', 'woocommerce-novalnetpayment') . '" target="_new"><img height ="30" src="' . $this->icon . '" alt="' . $this->method_title . '" /></a>';
                    return($icon_html);
                }

                /*
                 * Displays Novalnet Logo icon
                 */

                public function get_title() {
                    $novalnet_logo_html = '';
                    if ($this->novalnet_logo && !strstr(@$_SERVER['HTTP_REFERER'], 'wp-admin') && ((isset($_REQUEST['action']) && $_REQUEST['action'] == 'woocommerce_update_order_review' && @$_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest') || (!isset($_REQUEST['woocommerce_pay']) && isset($_GET['pay_for_order']) && isset($_GET['order_id']) && isset($_GET['order']))))
                        $novalnet_logo_html = '<a href="' . (strtolower($this->language) == 'de' ? 'https://' : 'http://') . 'www.' . __('novalnet.com', 'woocommerce-novalnetpayment') . '" alt="' . __('novalnet.com', 'woocommerce-novalnetpayment') . '" target="_new"><img src="' . (is_ssl() ? 'https://' : 'http://') . __('www.novalnet.de/img/NN_Logo_T.png', 'woocommerce-novalnetpayment') . '" alt="' . __('novalnet.com', 'woocommerce-novalnetpayment') . '" /></a>&nbsp;';
                    return($novalnet_logo_html . $this->title);
                }

                /*
                 * Payment field to display description and additional info in the checkout form	 
                 */

                public function payment_fields() {
                    
                    // payment description
					if ($this->description)
						echo wpautop(wptexturize($this->description));
                    
                    // test order notice
					if ($this->test_mode == 1)	{
						$test_notice  = __('Please Note: This transaction will run on TEST MODE and the amount will not be charged','woocommerce-novalnetpayment');
						echo  wpautop('<strong><font color="red">'.$test_notice.'</font></strong>');
					}			   
                    
                    // payment form
                    switch ($this->novalnet_payment_method) {
                        case 'novalnet_cc':
                            print '<br /><div id="loading_iframe_div" style="display:;"><img alt="' . __('Loading...', 'woocommerce-novalnetpayment') . '" src="' . (is_ssl() ? 'https://www.novalnet.de/img/novalnet-loading-icon.gif' : 'http://www.novalnet.de/img/novalnet-loading-icon.gif') . '"></div><input type="hidden" name="cc_type" id="cc_type" value="" /><input type="hidden" name="cc_holder" id="cc_holder" value="" /><input type="hidden" name="cc_exp_month" id="cc_exp_month" value="" /><input type="hidden" name="cc_exp_year" id="cc_exp_year" value="" /><input type="hidden" name="cc_cvv_cvc" id="cc_cvv_cvc" value="" /><input type="hidden" id="original_vendor_id" value="' . ($this->vendor_id) . '" /><input type="hidden" id="original_vendor_authcode" value="' . ($this->auth_code) . '" /><input type="hidden" id="original_customstyle_css" value="" /><input type="hidden" id="original_customstyle_cssval" value="" /><input type="hidden" name="nn_unique_id" id="nn_unique_id" value="" /><input type="hidden" name="nn_cardno_id" id="nn_cardno_id" value="" /><iframe onLoad="doHideLoadingImageAndDisplayIframe(this);" name="novalnet_cc_iframe" id="novalnet_cc_iframe" src="' . site_url() . '/wp-content/plugins/woocommerce-novalnet-gateway/includes/novalnet_cc_iframe.html" scrolling="no" frameborder="0" style="width:100%; height:260px; border:none; display:none;"></iframe>
            <script type="text/javascript" language="javascript">
            function doHideLoadingImageAndDisplayIframe(element) {
            document.getElementById("loading_iframe_div").style.display="none";
            element.style.display="";
            var iframe = (element.contentWindow || element.contentDocument);
            if (iframe.document) iframe=iframe.document;
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
            if(getInputForm.form.getAttribute("id")==null || getInputForm.form.getAttribute("id") =="") {
			getInputForm.form.setAttribute("id", novalnetHiddenId);
			getFormId 				= getInputForm.form.getAttribute("id");
			}else{
			getFormId 				= getInputForm.form.getAttribute("id");
			}
            document.getElementById(getFormId).onsubmit = function(){
			var iform = document.getElementById("novalnet_cc_iframe");
			var novalnet_cc_iframe = (iform.contentWindow || iform.contentDocument);
			if (novalnet_cc_iframe.document) novalnet_cc_iframe=novalnet_cc_iframe.document;
			if( novalnet_cc_iframe.getElementById("nncc_cardno_id").value != null ) {
				document.getElementById("nn_cardno_id").value = novalnet_cc_iframe.getElementById("nncc_cardno_id").value;
				document.getElementById("nn_unique_id").value = novalnet_cc_iframe.getElementById("nncc_unique_id").value;
			}
		}
            </script>
            ';
                            break;
                        case 'novalnet_cc3d':
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
                            }
                            $payment_field_html.='</select>
            </div>
            <div style="clear:both;">&nbsp;</div>
            <div style="float:left;width:50%;">' . __('CVC (Verification Code)', 'woocommerce-novalnetpayment') . ':<span style="color:red;">*</span></div>
            <div style="float:left;width:50%;"><input type="text" name="cvv_cvc" id="cvv_cvc" value="" maxlength="4" autocomplete="off" /><br />' . __('* On Visa-, Master- and Eurocard you will find the 3 digit CVC-Code near the signature field at the rearside of the creditcard.', 'woocommerce-novalnetpayment') . '</div>
            <div style="clear:both;">&nbsp;</div></div>';
                            print $payment_field_html;
                            break;
                        case 'novalnet_elv_de':
                            print $this->do_print_form_elements_for_novalnet_elv_de_at('de');
                            break;
                        case 'novalnet_elv_at':
                            print $this->do_print_form_elements_for_novalnet_elv_de_at('at');
                            break;
                    }
                }

                /*
                 * Process the payment and return the result
                 */

                public function process_payment($order_id) {
                    return($this->do_process_payment_from_novalnet_payments($order_id));
                }

                /**
                 * Receipt_page
                 **/
                public function receipt_page($order_id) {
                    $order = new WC_Order($order_id);
                    $this->do_necessary_actions_before_prepare_to_novalnet_payport_or_paygate($order);
                    $this->do_prepare_to_novalnet_payport($order);
                }

                public function plugins_loaded() {
                
                }

                public function include_template_functions() {
                    
                }

                function is_valid_for_use() {
                    return(true);
                }

                /**
                 * Admin Panel Options 
                 **/
                public function admin_options() {
                    ?>
                    <h3><?php echo '<a href="' . (strtolower($this->language) == 'de' ? 'https://' : 'http://') . 'www.' . __('novalnet.com', 'woocommerce-novalnetpayment') . '" alt="' . __('novalnet.com', 'woocommerce-novalnetpayment') . '" target="_new"><img src="' . (is_ssl() ? 'https://' : 'http://') . __('www.novalnet.de/img/NN_Logo_T.png', 'woocommerce-novalnetpayment') . '" alt="' . __('novalnet.com', 'woocommerce-novalnetpayment') . '" /></a>&nbsp;' . $this->payment_details[$this->novalnet_payment_method]['payment_name'] . ' ' . '<a href="' . (strtolower($this->language) == 'de' ? 'https://www.novalnet.de' : 'http://www.novalnet.com') . '" alt="' . __('novalnet.com', 'woocommerce-novalnetpayment') . '" target="_new"><img height ="30" src="' . $this->icon . '" alt="' . $this->method_title . '" /></a>'; ?></h3>
                    <p><?php echo __('Configure with Novalnet dealer details.If you need more information<br><br>you can visit our website for end-customers visit on <a href="https://www.novalnet.de/" target="_blank"> https://www.novalnet.de</a> or please contact our Sales Team <a href="mailto:sales@novalnet.de">sales@novalnet.de</a>.', 'woocommerce-novalnetpayment'); ?></p>
                    <table class="form-table">
                    <?php
                    if (!$this->do_check_novalnet_order_status()) {
                        ?>
                            <div class="inline error"><p><strong><?php
                        echo __('Gateway Disabled', 'woocommerce-novalnetpayment');
                        ;
                        ?></strong>: <?php echo $this->title . __(' will be disabled for this order status. And, this payment will be hidden in front end.', 'woocommerce-novalnetpayment'); ?></p></div>
                        <?php
                    }
                    // Generate the HTML For the settings form.
                    $this->generate_settings_html();
                    ?>
                    </table><!--/.form-table-->
                    <?php
                }

                /**
                 * Initialise Novalnet Gateway Settings Form Fields
                 **/
                public function init_form_fields() {
                    $order_statuses = $this->list_order_statuses();
                    $this->form_fields['enabled'] = array(
                        'title' 		=> __('Enable module', 'woocommerce-novalnetpayment'),
                        'type' 			=> 'checkbox',
                        'label' 		=> '',
                        'default' 		=> ''
                    );
                    foreach ($this->language_supported_array as $language) {
                        $this->form_fields['title_' . $language] = array(
                            'title' 	=> __('Payment Title', 'woocommerce-novalnetpayment') . ' (' . $language . ')<span style="color:red;">*</span>',
                            'type' 		=> 'text',
                            'description' => '',
                            'default' 	=> ''
                        );
                        $this->form_fields['description_' . $language] = array(
                            'title' 	=> __('Description', 'woocommerce-novalnetpayment') . ' (' . $language . ')',
                            'type' 		=> 'textarea',
                            'description' => '',
                            'default' 	=> ''
                        );
                    }
                    $this->form_fields['test_mode'] = array(
                        'title' 		=> __('Enable Test Mode', 'woocommerce-novalnetpayment'),
                        'type' 			=> 'select',
                        'options' 		=> array('0' => __('No', 'woocommerce-novalnetpayment'), '1' => __('Yes', 'woocommerce-novalnetpayment')),
                        'description' 	=> '',
                        'default' 		=> ''
                    );
                    $this->form_fields['merchant_id'] = array(
                        'title' 		=> __('Novalnet Merchant ID', 'woocommerce-novalnetpayment') . '<span style="color:red;">*</span>',
                        'type' 			=> 'text',
                        'description' 	=> __('Enter your Novalnet Merchant ID', 'woocommerce-novalnetpayment'),
                        'default' 		=> ''
                    );
                    $this->form_fields['auth_code'] = array(
                        'title' 		=> __('Novalnet Merchant Authorisation code', 'woocommerce-novalnetpayment') . '<span style="color:red;">*</span>',
                        'type' 			=> 'text',
                        'description' 	=> __('Enter your Novalnet Merchant Authorisation code', 'woocommerce-novalnetpayment'),
                        'default' 		=> ''
                    );
                    $this->form_fields['product_id'] = array(
                        'title' 		=> __('Novalnet Product ID', 'woocommerce-novalnetpayment') . '<span style="color:red;">*</span>',
                        'type' 			=> 'text',
                        'description' 	=> __('Enter your Novalnet Product ID', 'woocommerce-novalnetpayment'),
                        'default' 		=> ''
                    );
                    $this->form_fields['tariff_id'] = array(
                        'title' 		=> __('Novalnet Tariff ID', 'woocommerce-novalnetpayment') . '<span style="color:red;">*</span>',
                        'type' 			=> 'text',
                        'description' 	=> __('Enter your Novalnet Tariff ID', 'woocommerce-novalnetpayment'),
                        'default' 		=> ''
                    );
                    if (in_array($this->novalnet_payment_method, $this->encode_applicable_for_array)) {
                        $this->form_fields['key_password'] = array(
                            'title' 	=> __('Novalnet Payment access key', 'woocommerce-novalnetpayment') . '<span style="color:red;">*</span>',
                            'type' 		=> 'text',
                            'description' => __('Enter your Novalnet payment access key', 'woocommerce-novalnetpayment'),
                            'default' 	=> ''
                        );
                    }
                    if ($this->novalnet_payment_method == 'novalnet_elv_de') {
                        $this->form_fields['acdc'] = array(
                            'title' 	=> __('Enable credit rating check', 'woocommerce-novalnetpayment'),
                            'type' 		=> 'checkbox',
                            'label' 	=> '',
                            'default' 	=> ''
                        );
                    }
                    if ($this->novalnet_payment_method == 'novalnet_invoice') {
                        $this->form_fields['payment_duration'] = array(
                            'title' 	=> __('Payment period in days', 'woocommerce-novalnetpayment'),
                            'type' 		=> 'text',
                            'label' 	=> '',
                            'default' 	=> ''
                        );
                    }
                    if (!in_array($this->novalnet_payment_method, $this->manual_check_limit_not_available_array)) {
                        $this->form_fields['manual_check_limit'] = array(
                            'title' 	=> __('Manual checking amount in cents', 'woocommerce-novalnetpayment'),
                            'type' 		=> 'text',
                            'description' => __('Please enter the amount in cents', 'woocommerce-novalnetpayment'),
                            'default' 	=> ''
                        );
                        $this->form_fields['product_id_2'] = array(
                            'title' 	=> __('Second Product ID in Novalnet', 'woocommerce-novalnetpayment'),
                            'type' 		=> 'text',
                            'description' => __('for the manual checking', 'woocommerce-novalnetpayment'),
                            'default' 	=> ''
                        );
                        $this->form_fields['tariff_id_2'] = array(
                            'title' 	=> __('Second Tariff ID in Novalnet', 'woocommerce-novalnetpayment'),
                            'type' 		=> 'text',
                            'description' => __('for the manual checking', 'woocommerce-novalnetpayment'),
                            'default' 	=> ''
                        );
                    }
                    if ($this->novalnet_payment_method == 'novalnet_paypal') {
                        $this->form_fields['api_username'] = array(
                            'title' 	=> __('PayPal API User Name', 'woocommerce-novalnetpayment') . '<span style="color:red;">*</span>',
                            'type' 		=> 'text',
                            'description' => __('Please enter your PayPal API username', 'woocommerce-novalnetpayment'),
                            'default' 	=> ''
                        );
                        $this->form_fields['api_password'] = array(
                            'title' 	=> __('PayPal API Password', 'woocommerce-novalnetpayment') . '<span style="color:red;">*</span>',
                            'type' 		=> 'text',
                            'description' => __('Please enter your PayPal API password', 'woocommerce-novalnetpayment'),
                            'default' 	=> ''
                        );
                        $this->form_fields['api_signature'] = array(
                            'title' 	=> __('PayPal API Signature', 'woocommerce-novalnetpayment') . '<span style="color:red;">*</span>',
                            'type' 		=> 'text',
                            'description' => __('Please enter your PayPal API signature', 'woocommerce-novalnetpayment'),
                            'default' 	=> ''
                        );
                    }
                    $this->form_fields['payment_proxy'] = array(
                        'title' 		=> __('Proxy-Server', 'woocommerce-novalnetpayment'),
                        'type' 			=> 'text',
                        'description' 	=> __('If you use a Proxy Server, enter the Proxy Server IP with port here (e.g. www.proxy.de:80)', 'woocommerce-novalnetpayment'),
                        'default' 		=> ''
                    );
                    $this->form_fields['order_status'] = array(
                        'title' 		=> __('Set order Status', 'woocommerce-novalnetpayment'),
                        'type' 			=> 'select',
                        'options' 		=> $order_statuses,
                        'description' 	=> __('Set the status of orders made with this payment module to this value', 'woocommerce-novalnetpayment'),
                        'default' 		=> ''
                    );
                    $this->form_fields['novalnet_logo'] = array(
                        'title' 		=> __('Enable Novalnet Logo', 'woocommerce-novalnetpayment'),
                        'type' 			=> 'select',
                        'options' 		=> array('0' => __('No', 'woocommerce-novalnetpayment'), '1' => __('Yes', 'woocommerce-novalnetpayment')),
                        'description' 	=> __('To display Novalnet logo in front end', 'woocommerce-novalnetpayment'),
                        'default' 		=> ''
                    );
                    $this->form_fields['payment_logo'] = array(
                        'title' 		=> __('Enable Payment Logo', 'woocommerce-novalnetpayment'),
                        'type' 			=> 'select',
                        'options' 		=> array('0' => __('No', 'woocommerce-novalnetpayment'), '1' => __('Yes', 'woocommerce-novalnetpayment')),
                        'description' 	=> __('To display Payment logo in front end', 'woocommerce-novalnetpayment'),
                        'default' 		=> ''
                    );
                }

            }

        }
    }
}

if (strstr($_SERVER['REQUEST_URI'], '/woocommerce-novalnet-gateway/callback_novalnet2wordpresswoocommerce.php'))
    require_once(dirname(__FILE__) . '/callback_novalnet2wordpresswoocommerce.php');
if (isset($_REQUEST['novalnet_payment_method']) && in_array($_REQUEST['novalnet_payment_method'], $novalnet_payment_methods)) {
    require_once(dirname(__FILE__) . '/includes/' . @$_REQUEST['novalnet_payment_method'] . '.php');
} else {
    foreach ($novalnet_payment_methods as $novalnet_payment_method)
        require_once(dirname(__FILE__) . '/includes/' . $novalnet_payment_method . '.php');
    ob_get_clean();
}
?>
