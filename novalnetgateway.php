<?php
/*
 * Plugin Name: Woocommerce Payment Gateway by Novalnet
 * Plugin URI:  http://www.novalnet.com/modul/woocommerce
 * Description: Adds Novalnet Payment Gateway to Woocommerce e-commerce plugin
 * Author:      Novalnet
 * Author URI:  https://www.novalnet.de
 *
 * Version: 	 1.1.1
 * Requires at least:   3.3
 * Tested up to:        3.7.1
 *
 * Text Domain:         woocommerce-novalnetpayment
 * Domain Path:         /languages/
 *
 * License: GPLv2
 */

// Exit if accessed directly
if (!defined('ABSPATH'))
    exit;

/* Plugin installation starts */
register_activation_hook(__FILE__, 'novalnet_activation');
register_deactivation_hook(__FILE__, 'novalnet_deactivation');

/* Load Novalnet Gateway language translations */
load_plugin_textdomain('woocommerce-novalnetpayment', false, dirname(plugin_basename(__FILE__)) . '/languages/');

/* Initiate admin notice display	 */
add_action('admin_notices', 'novalnet_admin_notices');

// actions to perform once on plugin activation
if (!function_exists('novalnet_activation')) {

    function novalnet_activation() {

        //register uninstaller
        register_uninstall_hook(__FILE__, 'novalnet_uninstall');

    }	//	End novalnet_activation()
}

// actions to perform once on plugin deactivation
if (!function_exists('novalnet_deactivation')) {

    function novalnet_deactivation() {

	global $wpdb;
        $wpdb->query("delete from $wpdb->options where option_name like 'woocommerce_novalnet_%'");

    }	// End novalnet_deactivation()
}

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
}

/**
 * Display admin notice at back-end during plugin activation
 */
function novalnet_admin_notices() {

    if (!is_plugin_active('woocommerce/woocommerce.php')) {

        echo '<div id="notice" class="error"><p>';
        echo '<b>' . __('Woocommerce Payment Gateway by Novalnet', 'woocommerce-novalnetpayment') . '</b> ' . __('add-on requires', 'woocommerce-novalnetpayment') . ' ' . '<a href="http://www.woothemes.com/woocommerce/" target="_new">' . __('WooCommerce', 'woocommerce-novalnetpayment') . '</a>' . ' ' . __('plugin. Please install and activate it.', 'woocommerce-novalnetpayment');
        echo '</p></div>', "\n";

    }
}	// End novalnet_admin_notices()

//actions to perform once on plugin uninstall
if (!function_exists('novalnet_uninstall')) {

    function novalnet_uninstall() {

        global $wpdb;
        $wpdb->query("delete from $wpdb->options where option_name like 'woocommerce_novalnet_%'");

    }	// End novalnet_uninstall()
}

/* Plugin installation ends */
$novalnet_payment_methods = array('novalnet_banktransfer', 'novalnet_cc', 'novalnet_cc3d', 'novalnet_elv_at', 'novalnet_elv_de', 'novalnet_ideal', 'novalnet_invoice', 'novalnet_paypal', 'novalnet_prepayment', 'novalnet_tel');

add_action('plugins_loaded', 'init_gateway_novalnet', 0);

/**
 * Initiate plugin actions
 */
function init_gateway_novalnet() {

    /* verify whether woocommerce is an active plugin before initializing Novalnet Payment Gateway */
    if (in_array('woocommerce/woocommerce.php', (array) get_option('active_plugins')) || in_array('woocommerce/woocommerce.php', (array) nn_active_nw_plugins())) {
	// Define Novalnet Gateway version constant
	if (!defined('NOVALNET_GATEWAY_VERSION'))
	    define('NOVALNET_GATEWAY_VERSION','1.1.1');

        if (!class_exists('WC_Payment_Gateway'))
            return;

        if (!class_exists('WC_Gateway_Novalnet')) {

            /**
             * Common class for Novalnet Payment Gateway
             */
            class WC_Gateway_Novalnet extends WC_Payment_Gateway {

		/* Novalnet Payment urls */
                var $novalnet_paygate_url = 'https://payport.novalnet.de/paygate.jsp';
                var $novalnet_cc_form_display_url = 'https://payport.novalnet.de/direct_form.jsp';
                var $novalnet_online_transfer_payport_url = 'https://payport.novalnet.de/online_transfer_payport';
                var $novalnet_cc3d_payport_url = 'https://payport.novalnet.de/global_pci_payport';
                var $novalnet_paypal_payport_url = 'https://payport.novalnet.de/paypal_payport';
                var $novalnet_second_call_url = 'https://payport.novalnet.de/nn_infoport.xml';

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
				var $novalnet_mod_ext_array = array('novalnet_cc', 'novalnet_cc3d', 'novalnet_elv_at', 'novalnet_elv_de', 'novalnet_invoice',  'novalnet_prepayment');
				
				// allowed actions for module transaction
				private $allowed_actions = array('nn_capture', 'nn_void', 'nn_refund');

                /**
                 * Telephone payment second call request
                 */
                public function do_make_second_call_for_novalnet_telephone($order_id) {

                    global $woocommerce;
                    $order = new WC_Order($order_id);

                    /* validate Telephone second call mandatory parameters	 */
                    if (isset($this->vendor_id) && $this->is_digits($this->vendor_id) && $this->vendor_id != null && isset($this->auth_code) && $this->auth_code != null && isset($_SESSION['novalnet_tel_tid']) && $_SESSION['novalnet_tel_tid'] != null && isset($this->language) && $this->language != null) {

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
                        }
			else {
                            $aryPaygateResponse = explode('&', $data);

			    foreach ($aryPaygateResponse as $key => $value) {

				if ($value != "") {
                                    $aryKeyVal = explode("=", $value);
                                    $aryResponse[$aryKeyVal[0]] = $aryKeyVal[1];
                                }
                            }
                        }

                        $aryResponse['tid'] = $_SESSION['novalnet_tel_tid'];
                        $aryResponse['test_mode'] = $_SESSION['novalnet_tel_test_mode'];
                        $aryResponse['order_no'] = ltrim($order->get_order_number(), __('#', 'hash before order number', 'woocommerce-novalnetpayment'));
                        $aryResponse['inputval1'] = $order_id;

                        // Manual Testing
                        //    $aryResponse['status_desc'] = __('Successful', 'woocommerce-novalnetpayment');
                        //    $aryResponse['status'] 		= 100;
                        // Manual Testing

                        return($this->do_check_novalnet_status($aryResponse, $order_id));
                    }
		    else {
                        $this->do_unset_novalnet_telephone_sessions();
                        $this->do_check_and_add_novalnet_errors_and_messages(__('Required parameter not valid', 'woocommerce-novalnetpayment'), 'error');
                        return($this->do_return_redirect_page_for_pay_or_thanks_page('success', $woocommerce->cart->get_checkout_url()));
                    }
                }	// End do_make_second_call_for_novalnet_telephone()

                /**
                 * Clears Novalnet Telephone payment session
                 */
                public function do_unset_novalnet_telephone_sessions() {

                    if (isset($_SESSION['novalnet_tel_tid']))
                        unset($_SESSION['novalnet_tel_tid']);
                    if (isset($_SESSION['novalnet_tel_test_mode']))
                        unset($_SESSION['novalnet_tel_test_mode']);
                    if (isset($_SESSION['novalnet_tel_amount']))
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
                    }
                    else {

                        $this->do_check_and_add_novalnet_errors_and_messages($aryResponse['status_desc'], 'error');
                        return($this->do_return_redirect_page_for_pay_or_thanks_page('success', $woocommerce->cart->get_checkout_url()));
                    }
                }	// End do_check_novalnet_tel_payment_status()

                /**
                 * Validate cart amount
                 */
                public function do_validate_amount() {

                    global $woocommerce;

                    if ($this->amount < 99 || $this->amount > 1000) {

                        $this->do_check_and_add_novalnet_errors_and_messages(__('Amounts below 0,99 Euros and above 10,00 Euros cannot be processed and are not accepted!', 'woocommerce-novalnetpayment'), 'error');
                        return($this->do_return_redirect_page_for_pay_or_thanks_page('success', $woocommerce->cart->get_checkout_url()));
                    }
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
                    }

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
                        }
                    }
                    return($aryResponse);
                }	// End do_prepare_to_novalnet_paygate()

                /**
                 * process parameters before sending to server
                 */
                public function do_necessary_actions_before_prepare_to_novalnet_payport_or_paygate($order) {
                    $this->user_ip = ($this->getRealIpAddr()=='::1')?'127.0.0.1':$this->getRealIpAddr();
                    $this->do_check_curl_installed_or_not();
                    $this->do_format_amount($order->order_total);
                    $this->do_check_and_assign_manual_check_limit();
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
                    }

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
                    }
                }	// End do_check_curl_installed_or_not()

                /**
                 * Validate shop address field parameter
                 */
                public function do_check_shop_parameter($order) {

                    global $woocommerce;
                    $nn_tmp_name = array();

                    if (isset($order)) {
						$this->shop_nn_email = isset($order->billing_email) ? trim($order->billing_email) : null;
                         list($this->shop_nn_first_name, $this->shop_nn_last_name) = $this->_form_username_param($order);

                        /** Novalnet validation for basic address fields (returns true only if the user has modified default workflow) */
                        if ($this->shop_nn_first_name == null || $this->shop_nn_last_name == null || $this->shop_nn_email == null) {

                            $error = __('Customer name/email fields are not valid', 'woocommerce-novalnetpayment');

                            if ($this->debug == 'yes')
                                $this->log->add('novalnetpayments', 'Validation error for payment ' . $this->novalnet_payment_method . $error);

                            $this->do_check_and_add_novalnet_errors_and_messages($error, 'error');
                            wp_safe_redirect($woocommerce->cart->get_checkout_url());
                            exit();
                        }
                    }
                }	// End do_check_shop_parameter()

				/**
                 * Form username parameter
                 */
				private function _form_username_param($order) {
					$order->billing_first_name = $this->remSpace($order->billing_first_name);
					$order->billing_last_name = $this->remSpace($order->billing_last_name);

					/* Get customer first and last name  */
					if(empty($order->billing_first_name) || empty($order->billing_last_name)) {
						$full_name = $order->billing_first_name.$order->billing_last_name;
						return preg_match('/\s/', $full_name) ? explode(" ", $full_name, 2) : array($full_name, $full_name);
					}
					return array($order->billing_first_name, $order->billing_last_name);
				} // End username formation parameter()

				/**
				 * Remove the left and right spaces
				 */
				public function remSpace($val) {
					return trim($val);
				}

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
                    }
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
                    }
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
                    }

                    if ($this->novalnet_payment_method == 'novalnet_invoice') {

                        if ($this->is_digits($this->payment_duration)) {

                            if ($this->payment_duration > 0) {
                                $this->due_date = date("Y-m-d", mktime(0, 0, 0, date("m"), (date("d") + $this->payment_duration), date("Y")));
                            }
                            else
                                $this->due_date = date("Y-m-d", mktime(0, 0, 0, date("m"), date("d"), date("Y")));
                        }
                        else
                            $this->due_date = '';

                        $this->payment_parameters['due_date'] = $this->due_date;
                        $this->payment_parameters['end_date'] = $this->due_date;
                    }

                    if ($this->novalnet_payment_method == 'novalnet_paypal') {
                        $this->payment_parameters['api_user'] = $this->api_username;
                        $this->payment_parameters['api_pw'] = $this->api_password;
                        $this->payment_parameters['api_signature'] = $this->api_signature;
                    }

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
                    }

                    if ($this->novalnet_payment_method == 'novalnet_cc3d' || $this->novalnet_payment_method == 'novalnet_cc') {

                        $this->payment_parameters['cc_holder'] = isset($_SESSION['cc_holder']) ? $_SESSION['cc_holder'] : null;
                        $this->payment_parameters['cc_no'] = isset($_SESSION['cc_number']) ? $_SESSION['cc_number'] : null;
                        $this->payment_parameters['cc_exp_month'] = isset($_SESSION['exp_month']) ? $_SESSION['exp_month'] : null;
                        $this->payment_parameters['cc_exp_year'] = isset($_SESSION['exp_year']) ? $_SESSION['exp_year'] : null;
                        $this->payment_parameters['cc_cvc2'] = isset($_SESSION['cvv_cvc']) ? $_SESSION['cvv_cvc'] : null;

                        if ($this->novalnet_payment_method == 'novalnet_cc') {

                            $this->payment_parameters['unique_id'] = $_SESSION['nn_unique_id'];
                            $this->payment_parameters['pan_hash'] = $_SESSION['nn_cardno_id'];
                        }

                        unset($_SESSION['cc_holder']);
                        unset($_SESSION['cc_number']);
                        unset($_SESSION['exp_month']);
                        unset($_SESSION['exp_year']);
                        unset($_SESSION['cvv_cvc']);
                        unset($_SESSION['nn_unique_id']);
                        unset($_SESSION['nn_cardno_id']);
                    }
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

		    /* shop version parameters	*/
		    $this->payment_parameters['input2'] = 'CMS name / version';
                    $this->payment_parameters['inputval2'] = 'WORDPRESS '.get_bloginfo('version');
		    $this->payment_parameters['input3'] = 'Shopsystem name / version';
                    $this->payment_parameters['inputval3'] = 'WOOCOMMERCE '.WOOCOMMERCE_VERSION;
		    $this->payment_parameters['input4'] = 'Novalnet module version';
                    $this->payment_parameters['inputval4'] = NOVALNET_GATEWAY_VERSION;

                    $this->payment_parameters['customer_no'] = $order->user_id > 0 ? $order->user_id : 'guest';
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
                    }
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
                    }
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
                    }

                    elseif ($this->novalnet_payment_method == 'novalnet_cc3d') {

                        if ($cc_holder == '' || $this->is_invalid_holder_name($cc_holder) || $cc_number == '' || strlen($cc_number) < 12 || !$this->is_digits($cc_number) || (($exp_month == '' || $exp_year == date('Y')) && $exp_month < date('m')) || $exp_year == '' || $exp_year < date('Y') || $cvv_cvc == '' || strlen($cvv_cvc) < 3 || strlen($cvv_cvc) > 4 || !$this->is_digits($cvv_cvc))
                            $error = true;
                    }

                    if ($error) {

                        if ($this->debug == 'yes')
                            $this->log->add('novalnetpayments', 'Validation error for payment ' . $this->novalnet_payment_method . $error);

                        $this->do_check_and_add_novalnet_errors_and_messages(__('Please enter valid credit card details!', 'woocommerce-novalnetpayment'), 'error');
                        return($this->do_return_redirect_page_for_pay_or_thanks_page('success', $woocommerce->cart->get_checkout_url()));
                    }

                    else {

                        $_SESSION['cc_holder'] = $cc_holder;
                        $_SESSION['cc_number'] = $cc_number;
                        $_SESSION['exp_month'] = $exp_month;
                        $_SESSION['exp_year'] = $exp_year;
                        $_SESSION['cvv_cvc'] = $cvv_cvc;

                        if ($this->novalnet_payment_method == 'novalnet_cc') {
                            $_SESSION['nn_unique_id'] = $unique_id;
                            $_SESSION['nn_cardno_id'] = $pan_hash;
                        }

                        return('');
                    }
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

                        if ($this->debug == 'yes')
                            $this->log->add('novalnetpayments', 'Validation error for payment ' . $this->novalnet_payment_method . $error);

                        $this->do_check_and_add_novalnet_errors_and_messages($error, 'error');
                        return($this->do_return_redirect_page_for_pay_or_thanks_page('success', $woocommerce->cart->get_checkout_url()));
                    }

                    else {

                        $_SESSION['bank_account_holder'] = $bank_account_holder;
                        $_SESSION['bank_account'] = $bank_account;
                        $_SESSION['bank_code'] = $bank_code;


                        if (isset($acdc))
                            $_SESSION['acdc'] = $acdc;
							
                        return('');
                    }
                }	// End do_validate_elv_at_elv_de_form_elements()

                /**
                 * process novalnet payment methods
                 */
                public function do_process_payment_from_novalnet_payments($order_id) {

                    $order = new WC_Order($order_id);
                    if ($this->debug == 'yes') {
                        $this->log->add('novalnetpayments', 'Order Details:' . print_r($order, true));
                        $this->log->add('novalnetpayments', 'Novalnet Payment Method:' . $order->payment_method);
                    }
                    $this->do_check_novalnet_backend_data_validation_from_frontend();
                    $this->do_check_shop_parameter($order);

                    if ($this->debug == 'yes') {
                        $this->log->add('novalnetpayments', 'Validatation passed for Order No:' . $order_id);
                    }
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
                        }
                        else
                            return($this->do_make_second_call_for_novalnet_telephone($order_id));
                    }

                    elseif (in_array($this->novalnet_payment_method, $this->return_url_parameter_for_array)) {

                        if ($this->novalnet_payment_method == 'novalnet_cc3d') {

                            $return = $this->do_validate_cc_form_elements(trim($_REQUEST['cc3d_holder'], '&'), str_replace(' ', '', $_REQUEST['cc3d_number']), $_REQUEST['cc3d_exp_month'], $_REQUEST['cc3d_exp_year'], str_replace(' ', '', $_REQUEST['cvv_cvc']));

                            if ($return)
                                return($return);
                        }

                        return($this->do_return_redirect_page_for_pay_or_thanks_page('success', $this->do_build_redirect_url($order, 'pay')));
                    }

                    else {

                        if ($this->novalnet_payment_method == 'novalnet_cc') {

                            $return = $this->do_validate_cc_form_elements(trim($_REQUEST['cc_holder'], '&'), null, $_REQUEST['cc_exp_month'], $_REQUEST['cc_exp_year'], str_replace(' ', '', $_REQUEST['cc_cvv_cvc']), $_REQUEST['cc_type'], $_REQUEST['nn_unique_id'], $_REQUEST['nn_cardno_id']);

                            if ($return)
                                return($return);
                        }

                        elseif ($this->novalnet_payment_method == 'novalnet_elv_at') {

                            $return = $this->do_validate_elv_at_elv_de_form_elements(trim($_REQUEST['bank_account_holder_at'], '&'), str_replace(' ', '', $_REQUEST['bank_account_at']), str_replace(' ', '', $_REQUEST['bank_code_at']));

                            if ($return)
                                return($return);
                        }

                        elseif ($this->novalnet_payment_method == 'novalnet_elv_de') {

                            $return = $this->do_validate_elv_at_elv_de_form_elements(trim($_REQUEST['bank_account_holder_de'], '&'), str_replace(' ', '', $_REQUEST['bank_account_de']), str_replace(' ', '', $_REQUEST['bank_code_de']), isset($_REQUEST['acdc']) ? $_REQUEST['acdc'] : null);

                            if ($return)
                                return($return);
                        }

                        $this->do_necessary_actions_before_prepare_to_novalnet_payport_or_paygate($order);
                        $aryResponse = $this->do_prepare_to_novalnet_paygate($order);
                        return($this->do_check_novalnet_status($aryResponse, $order_id));
                    }
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

                    if (!$this->vendor_id || !$this->is_digits($this->vendor_id) || !$this->product_id || !$this->is_digits($this->product_id) || !$this->tariff_id || !$this->is_digits($this->tariff_id) || !$this->auth_code || (isset($this->key_password) && !$this->key_password) || (isset($this->api_username) && !$this->api_username) || (isset($this->api_password) && !$this->api_password) || (isset($this->api_signature) && !$this->api_signature))
                        $error = __('Basic parameter not valid', 'woocommerce-novalnetpayment');

                    if (isset($this->manual_check_limit) && $this->is_digits($this->manual_check_limit) && $this->manual_check_limit > 0) {

                        if (empty($this->product_id_2) || empty($this->tariff_id_2))
                            $error = __('Product-ID2 and/or Tariff-ID2 missing!', 'woocommerce-novalnetpayment');
                    }

                    if ($error) {

                        if ($this->debug == 'yes')
                            $this->log->add('novalnetpayments', 'Validation error for payment ' . $this->novalnet_payment_method . $error);

                        $this->do_check_and_add_novalnet_errors_and_messages($error, 'error');
                        wp_safe_redirect($woocommerce->cart->get_checkout_url());
                        exit();
                    }
                }	// End do_check_novalnet_backend_data_validation_from_frontend()

                /**
                 * build redirect url for direct form payment methods
                 */
                public function do_build_redirect_url($order, $page) {

                    return(add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, get_permalink(woocommerce_get_page_id($page)))));
                }	// End do_build_redirect_url()

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

                        /* 	basic validation for iframe request parameter	 */
                        if ($this->vendor_id != null && $this->is_digits($this->vendor_id) && $this->product_id != null && $this->is_digits($this->product_id) && $this->auth_code != null && $this->payment_key == 6 && $this->language != null) {
                            list($errno, $errmsg, $data) = $this->perform_https_request($this->payport_or_paygate_form_display, $form_parameters);
                            file_put_contents(ABSPATH . '/wp-content/plugins/woocommerce-novalnet-gateway/includes/novalnet_cc_iframe.html', $data);
                        } else {
                            $data = '<strong><font color="red">' . __('Basic Parameter Missing', 'woocommerce-novalnetpayment') . '</font></strong>';
                            file_put_contents(ABSPATH . '/wp-content/plugins/woocommerce-novalnet-gateway/includes/novalnet_cc_iframe.html', $data);
                        }
                    }
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
                        $payment_field_html.='<div style="clear:both;">&nbsp;</div>
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
                }	// End do_print_form_elements_for_novalnet_elv_de_at()

                /**
                 * validate novalnet configuration parameter
                 */
                public function novalnet_backend_validation_from_backend($request) {

                    /* Get woocommerce Novalnet configuration settings	 */
                    $vendor_id = isset($request['woocommerce_' . $this->novalnet_payment_method . '_merchant_id']) ? $request['woocommerce_' . $this->novalnet_payment_method . '_merchant_id'] : null;
                    $auth_code = isset($request['woocommerce_' . $this->novalnet_payment_method . '_auth_code']) ? $request['woocommerce_' . $this->novalnet_payment_method . '_auth_code'] : null;
                    $product_id = isset($request['woocommerce_' . $this->novalnet_payment_method . '_product_id']) ? $request['woocommerce_' . $this->novalnet_payment_method . '_product_id'] : null;
                    $tariff_id = isset($request['woocommerce_' . $this->novalnet_payment_method . '_tariff_id']) ? $request['woocommerce_' . $this->novalnet_payment_method . '_tariff_id'] : null;
                    $payment_duration = isset($request['woocommerce_' . $this->novalnet_payment_method . '_payment_duration']) ? $request['woocommerce_' . $this->novalnet_payment_method . '_payment_duration'] : null;
                    $key_password = isset($request['woocommerce_' . $this->novalnet_payment_method . '_key_password']) ? $request['woocommerce_' . $this->novalnet_payment_method . '_key_password'] : null;
                    $api_username = isset($request['woocommerce_' . $this->novalnet_payment_method . '_api_username']) ? $request['woocommerce_' . $this->novalnet_payment_method . '_api_username'] : null;
                    $api_password = isset($request['woocommerce_' . $this->novalnet_payment_method . '_api_password']) ? $request['woocommerce_' . $this->novalnet_payment_method . '_api_password'] : null;
                    $api_signature = isset($request['woocommerce_' . $this->novalnet_payment_method . '_api_signature']) ? $request['woocommerce_' . $this->novalnet_payment_method . '_api_signature'] : null;
                    $manual_check_limit = isset($request['woocommerce_' . $this->novalnet_payment_method . '_manual_check_limit']) ? $request['woocommerce_' . $this->novalnet_payment_method . '_manual_check_limit'] : null;
                    $product_id_2 = isset($request['woocommerce_' . $this->novalnet_payment_method . '_product_id_2']) ? $request['woocommerce_' . $this->novalnet_payment_method . '_product_id_2'] : null;
                    $tariff_id_2 = isset($request['woocommerce_' . $this->novalnet_payment_method . '_tariff_id_2']) ? $request['woocommerce_' . $this->novalnet_payment_method . '_tariff_id_2'] : null;

                    /* 	woocommerce Novalnet configuration validation	 */

                    if (!$request['woocommerce_' . $this->novalnet_payment_method . '_title'])
                        return(__('Please enter valid Payment Title', 'woocommerce-novalnetpayment'));

                    if (isset($vendor_id) && (!$vendor_id || !$this->is_digits($vendor_id)))
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

                    if (isset($manual_check_limit) && $manual_check_limit && $this->is_digits($manual_check_limit) && $manual_check_limit != 0) {

                        if (isset($product_id_2) && (!$product_id_2 || !$this->is_digits($product_id_2)))
                            return(__('Please enter valid Novalnet Second Product ID', 'woocommerce-novalnetpayment'));

                        if (isset($tariff_id_2) && (!$tariff_id_2 || !$this->is_digits($tariff_id_2)))
                            return(__('Please enter valid Novalnet Second Tariff ID', 'woocommerce-novalnetpayment'));
                    }

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
                        }
                    }

                    elseif (isset($request['saved']) && isset($_GET['wc_error'])) {

                        $redirect = get_admin_url() . 'admin.php?' . http_build_query($_GET);
                        $redirect = remove_query_arg('wc_error');
                        $redirect = add_query_arg('saved', urlencode(esc_attr('true')), $redirect);
                        wp_safe_redirect($redirect);
                        exit();
                    }
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
                        }
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
                            'second_call_url' => $this->novalnet_second_call_url,
                            'payment_name' => __('Instant Bank Transfer', 'woocommerce-novalnetpayment'),
                            'payment_description' => __('You will be redirected to Novalnet AG website when you place the order.', 'woocommerce-novalnetpayment'),
                            'payment_logo' => __('www.novalnet.de/img/sofort_Logo_en.png', 'woocommerce-novalnetpayment')
                        ),
                        /* 	Novalnet Credit Card Payment Method	 */
                        'novalnet_cc' => array(
                            'payment_key' => $this->payment_key_for_cc_family,
                            'payport_or_paygate_url' => $this->novalnet_paygate_url,
                            'payport_or_paygate_form_display' => $this->novalnet_cc_form_display_url,
                            'second_call_url' => $this->novalnet_second_call_url,
                            'payment_name' => __('Credit Card', 'woocommerce-novalnetpayment'),
                            'payment_description' => __('The amount will be booked immediately from your credit card when you submit the order.', 'woocommerce-novalnetpayment'),
                            'payment_logo' => __('www.novalnet.de/img/creditcard_small.jpg', 'woocommerce-novalnetpayment')
                        ),
                        /* 	Novalnet Credit Card 3D Secure Payment Method	 */
                        'novalnet_cc3d' => array(
                            'payment_key' => $this->payment_key_for_cc_family,
                            'payport_or_paygate_url' => $this->novalnet_cc3d_payport_url,
                            'second_call_url' => $this->novalnet_second_call_url,
                            'payment_name' => __('Credit Card 3D Secure', 'woocommerce-novalnetpayment'),
                            'payment_description' => __('The amount will be booked immediately from your credit card when you submit the order.', 'woocommerce-novalnetpayment'),
                            'payment_logo' => __('www.novalnet.de/img/creditcard_small.jpg', 'woocommerce-novalnetpayment')
                        ),
                        /* 	Novalnet Direct Debit Austria Payment Method	 */
                        'novalnet_elv_at' => array(
                            'payment_key' => $this->payment_key_for_at_family,
                            'payport_or_paygate_url' => $this->novalnet_paygate_url,
                            'second_call_url' => $this->novalnet_second_call_url,
                            'payment_name' => __('Direct Debit Austria', 'woocommerce-novalnetpayment'),
                            'payment_description' => __('Your account will be debited upon delivery of goods.', 'woocommerce-novalnetpayment'),
                            'payment_logo' => __('www.novalnet.de/img/ELV_Logo.png', 'woocommerce-novalnetpayment')
                        ),
                        /* 	Novalnet Direct Debit German Payment Method	 */
                        'novalnet_elv_de' => array(
                            'payment_key' => $this->payment_key_for_de_family,
                            'payport_or_paygate_url' => $this->novalnet_paygate_url,
                            'second_call_url' => $this->novalnet_second_call_url,
                            'payment_name' => __('Direct Debit German', 'woocommerce-novalnetpayment'),
                            'payment_description' => __('Your account will be debited upon delivery of goods.', 'woocommerce-novalnetpayment'),
                            'payment_logo' => __('www.novalnet.de/img/ELV_Logo.png', 'woocommerce-novalnetpayment')
                        ),
                        /* 	Novalnet iDEAL Payment Method	 */
                        'novalnet_ideal' => array(
                            'payment_key' => $this->payment_key_for_ideal,
                            'payport_or_paygate_url' => $this->novalnet_online_transfer_payport_url,
                            'second_call_url' => $this->novalnet_second_call_url,
                            'payment_name' => __('iDEAL', 'woocommerce-novalnetpayment'),
                            'payment_description' => __('You will be redirected to Novalnet AG website when you place the order.', 'woocommerce-novalnetpayment'),
                            'payment_logo' => __('www.novalnet.de/img/ideal_payment_small.png', 'woocommerce-novalnetpayment')
                        ),
                        /* 	Novalnet Invoice Payment Method	 */
                        'novalnet_invoice' => array(
                            'payment_key' => $this->payment_key_for_invoice_prepayment,
                            'payport_or_paygate_url' => $this->novalnet_paygate_url,
                            'second_call_url' => $this->novalnet_second_call_url,
                            'payment_name' => __('Invoice', 'woocommerce-novalnetpayment'),
                            'payment_description' => __('The bank details will be emailed to you soon after the completion of checkout process.', 'woocommerce-novalnetpayment'),
                            'payment_logo' => __('www.novalnet.de/img/kauf-auf-rechnung.jpg', 'woocommerce-novalnetpayment')
                        ),
                        /* 	Novalnet PayPal Payment Method	 */
                        'novalnet_paypal' => array(
                            'payment_key' => $this->payment_key_for_paypal,
                            'payport_or_paygate_url' => $this->novalnet_paypal_payport_url,
                            'second_call_url' => $this->novalnet_second_call_url,
                            'payment_name' => __('PayPal', 'woocommerce-novalnetpayment'),
                            'payment_description' => __('You will be redirected to Novalnet AG website when you place the order.', 'woocommerce-novalnetpayment'),
                            'payment_logo' => __('www.novalnet.de/img/paypal-small.png', 'woocommerce-novalnetpayment')
                        ),
                        /* 	Novalnet Prepayment Payment Method	 */
                        'novalnet_prepayment' => array(
                            'payment_key' => $this->payment_key_for_invoice_prepayment,
                            'payport_or_paygate_url' => $this->novalnet_paygate_url,
                            'second_call_url' => $this->novalnet_second_call_url,
                            'payment_name' => __('Prepayment', 'woocommerce-novalnetpayment'),
                            'payment_description' => __('The bank details will be emailed to you soon after the completion of checkout process.', 'woocommerce-novalnetpayment'),
                            'payment_logo' => __('www.novalnet.de/img/vorauskasse.jpg', 'woocommerce-novalnetpayment')
                        ),
                        /* 	Novalnet Telephone Payment Method	 */
                        'novalnet_tel' => array(
                            'payment_key' => $this->payment_key_for_tel,
                            'payport_or_paygate_url' => $this->novalnet_paygate_url,
                            'second_call_url' => $this->novalnet_second_call_url,
                            'payment_name' => __('Telephone Payment', 'woocommerce-novalnetpayment'),
                            'payment_description' => __('Your amount will be added in your telephone bill when you place the order', 'woocommerce-novalnetpayment'),
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

                    /* assign basic configuration parameters */
                    $this->test_mode = $this->settings['test_mode'];
                    $this->vendor_id = $this->settings['merchant_id'];
                    $this->auth_code = $this->settings['auth_code'];
                    $this->product_id = $this->settings['product_id'];
                    $this->tariff_id = $this->settings['tariff_id'];
                    $this->payment_key = $this->payment_details[$this->novalnet_payment_method]['payment_key'];

                    $this->payment_proxy = $this->settings['payment_proxy'];

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

                    // Define user set variables.
                    $this->title = $this->settings['title'];
                    $this->description = $this->settings['description'];
                    $this->instructions = $this->settings['instructions'];
                    $this->email_notes = $this->settings['email_notes'];
                    $this->debug = $this->settings['debug'];
                    $this->novalnet_logo = $this->settings['novalnet_logo'];
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

                    if (isset($this->manual_check_limit) && $this->manual_check_limit > 0 && $this->amount >= $this->manual_check_limit) {

                        if ($this->product_id_2 && $this->tariff_id_2) {
                            $this->product_id = $this->product_id_2;
                            $this->tariff_id = $this->tariff_id_2;
                        }
                    }
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

                    if ($this->novalnet_payment_method == 'novalnet_cc3d') {
                        $this->amount = $request['amount'];
                        $this->do_check_and_assign_manual_check_limit();
                    }

                    $order = new WC_Order($order_no);

                    /* add Novalnet Transaction details to order notes */
                    $new_line = "\n";
                    $novalnet_comments = $new_line . $this->title . $new_line;
                    $novalnet_comments .= __('Novalnet Transaction ID', 'woocommerce-novalnetpayment') . ': ' . $request['tid'] . $new_line;
                    $novalnet_comments .= ((isset($request['test_mode']) && $request['test_mode'] == 1) || (isset($this->test_mode) && $this->test_mode == 1)) ? __('Test order', 'woocommerce-novalnetpayment') : '';

                    /* 	add additional information for Prepayment and Invoice order	 */
                    if ($this->novalnet_payment_method == 'novalnet_invoice' || $this->novalnet_payment_method == 'novalnet_prepayment') {

                        $novalnet_comments .= $request['test_mode'] ? $new_line . $new_line : $new_line;
                        $novalnet_comments .= __('Please transfer the amount to the following information to our payment service Novalnet AG', 'woocommerce-novalnetpayment') . $new_line;
                        if ($this->novalnet_payment_method == 'novalnet_invoice' && $this->is_digits($this->payment_duration))
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
                    }

                    // adds order note
                    if ($order->customer_note)
                        $order->customer_note .= $new_line;

                    if ($this->novalnet_payment_method == 'novalnet_invoice' || $this->novalnet_payment_method == 'novalnet_prepayment') {

                        $order->customer_note .= html_entity_decode($novalnet_comments, ENT_QUOTES, 'UTF-8');

                        if (version_compare($woocommerce->version, '2.0.0', '<'))
                            $order->customer_note .= utf8_encode($novalnet_comments);
                    }
                    else
                        $order->customer_note .= html_entity_decode($novalnet_comments, ENT_QUOTES, 'UTF-8');

                    $order->add_order_note($order->customer_note);

                    if ($this->debug == 'yes')
                        $this->log->add('novalnetpayments', 'Novalnet Transaction Information for Order ' . $woo_seq_nr . ' ' . $order->customer_note);
                    /** Update Novalnet Transaction details into shop database	 */
                    $nn_order_notes = array(
                        'ID' => $order_no,
                        'post_excerpt' => $order->customer_note
                    );
                    wp_update_post($nn_order_notes);

		    /**	 basic validation for post_back and transaction status call	*/
		    $return = $this->do_make_nn_api_validate($this->vendor_id, $this->auth_code, $this->product_id, $this->tariff_id, $this->payment_key, $request['tid'], $woo_seq_nr);

		    if ($return == true) {

			// send acknoweldgement call to Novalnet server
			$this->post_back_param($request, $woo_seq_nr);

			// recieves the status code for transactions
			$api_stat_code =$this->do_make_transaction_status($request);
		    }
		    else
			$api_stat_code = 0;

		    update_post_meta($order_no,'_nn_status_code',$api_stat_code);

		    // Update Order status
		    if (isset($api_stat_code) && $api_stat_code != 100)
			$order->update_status('on-hold',$message);
		    else
			$order->update_status('processing',$message);

		    // Reduce stock levels
		    $order->reduce_order_stock();

		    // Remove cart
		    $woocommerce->cart->empty_cart();

		    // successful message display
		    $this->do_check_and_add_novalnet_errors_and_messages($message, 'message');

                    // Clears the Novalnet Telephone payment session
                    $this->do_unset_novalnet_telephone_sessions();

                    if ($this->debug == 'yes') {
                        $this->log->add('novalnetpayments', 'Transaction Complete for Order ' . $order_no);
                        $this->log->add('novalnetpayments', 'Transaction Status for Order ' . $order_no . ' is ' . $order->status);
                        if ($order_no != $woo_seq_nr)
                            $this->log->add('novalnetpayments', 'Sequential Order number for current Order ' . $woo_seq_nr);
                    }

                    // Empty awaiting payment session
                    if (!empty($woocommerce->session->order_awaiting_payment))
                        unset($woocommerce->session->order_awaiting_payment);

                    //	Return thankyou redirect
                    if (in_array($this->novalnet_payment_method, $this->return_url_parameter_for_array)) {

                        wp_safe_redirect(add_query_arg('key', $order->order_key, add_query_arg('order', $order_no, get_permalink(woocommerce_get_page_id('thanks')))));
                        exit();
                    }
                    else
                        return($this->do_return_redirect_page_for_pay_or_thanks_page('success', $this->do_build_redirect_url($order, 'thanks')));
                }	// End do_novalnet_success()

                /**
                 * Order Cancellation
                 */
                public function do_novalnet_cancel($request, $message, $nn_order_no) {

                    global $woocommerce;

                    // trim request array
                    $this->do_trim_array_values($request);

                    if (in_array($this->novalnet_payment_method, $this->return_url_parameter_for_array)) {
                        $order_no = $request['inputval1'];
                        $order = new WC_Order($order_no);
                    } else {
                        $order_no = $nn_order_no;
                        $order = new WC_Order($order_no);
                    }

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

					// update novalnet status code into  database
					update_post_meta($order_no,'_nn_status_code',$request['status']);
					update_post_meta($order_no,'_nn_ref_code',1);
					
                    // clears telephone payment session
                    $this->do_unset_novalnet_telephone_sessions();

                    if ($this->debug == 'yes') {
                        $this->log->add('novalnetpayments', 'Transaction Cancelled for Order ' . $order_no . ' status message ' . $message);
                    }

                    if (in_array($this->novalnet_payment_method, $this->return_url_parameter_for_array)) {
                        wp_safe_redirect($woocommerce->cart->get_checkout_url());
                        exit();
                    }
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
                    }

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
                    }
                    return md5($h['authcode'] . $h['product_id'] . $h['tariff'] . $h['amount'] . $h['test_mode'] . $h['uniqid'] . strrev($this->key_password));
                }	// End hash()

                /**
                 * Validate Hash parameter
                 */
                public function checkHash(&$request) {

                    $h['authcode'] = $request['auth_code'];   #encoded
                    $h['product_id'] = $request['product'];   #encoded
                    $h['tariff'] = $request['tariff'];    #encoded
                    $h['amount'] = $request['amount'];    #encoded
                    $h['test_mode'] = $request['test_mode'];   #encoded
                    $h['uniqid'] = $request['uniqid'];    #encoded

                    if (!$request)
                        return false;#'Error: no data

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
                    }

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
                public function do_check_novalnet_status($request, $nn_order_no) {

                    if (isset($request['status']) && $request['status'] == 100 || (isset($request['novalnet_payment_method']) && $request['novalnet_payment_method'] == 'novalnet_paypal' && $request['status'] == 90 ))
                        return($this->do_novalnet_success($request, $this->do_get_novalnet_response_text($request)));
                    else
                        return($this->do_novalnet_cancel($request, $this->do_get_novalnet_response_text($request), $nn_order_no));
                }	// End do_check_novalnet_status()

                /**
                 * validate novalnet server response
                 */
                public function do_check_novalnet_payment_status() {

                    if (isset($_REQUEST) && isset($_REQUEST['status']) && isset($_REQUEST['novalnet_payment_method']) && in_array($_REQUEST['novalnet_payment_method'], $this->return_url_parameter_for_array)) {

                        if (isset($_REQUEST['hash'])) {

                            if (!$this->checkHash($_REQUEST)) {

                                $message = $this->do_get_novalnet_response_text($_REQUEST) . ' - ' . __('Check Hash failed.', 'woocommerce-novalnetpayment');
                                $this->do_novalnet_cancel($_REQUEST, $message, null);
                            }
                            else
                                $this->do_check_novalnet_status($_REQUEST, null);
                        }
                        else
                            $this->do_check_novalnet_status($_REQUEST, null);
                    }
                }	// End do_check_novalnet_payment_status()

                /**
                 * Send acknowledgement parameters to Novalnet server after successful transaction
                 */
                public function post_back_param($request, $order_id) {

                        $urlparam = 'vendor=' . $this->vendor_id . '&product=' . $this->product_id . '&key=' . $this->payment_key . '&tariff=' . $this->tariff_id . '&auth_code=' . $this->auth_code . '&status=100&tid=' . $request['tid'] . '&order_no=' . $order_id;

                        if ($this->novalnet_payment_method == 'novalnet_invoice' || $this->novalnet_payment_method == 'novalnet_prepayment')
                            $urlparam .='&invoice_ref=' . "BNR-" . $this->product_id . "-" . $order_id;

                        list($errno, $errmsg, $data) = $this->perform_https_request($this->novalnet_paygate_url, $urlparam);

                        if ($this->debug == 'yes')
                            $this->log->add('novalnetpayments', 'Acknowledgement Parameters sent successfully');

                }	// End post_back_param()

		/**
		 * performs transaction status request to Novalnet server
		 */
		protected function do_make_transaction_status($request) {

		    $urlparam = '<nnxml><info_request><vendor_id>' . $this->vendor_id . '</vendor_id>';
                    $urlparam .= '<vendor_authcode>' . $this->auth_code . '</vendor_authcode>';
                    $urlparam .= '<request_type>TRANSACTION_STATUS</request_type>';
		    $urlparam .= '<product_id>' . $this->product_id . '</product_id>';
		    $urlparam .= '<tid>' . $request['tid'] . '</tid>';
		    $urlparam .='</info_request></nnxml>';
		    list($errno, $errmsg, $data) = $this->perform_https_request($this->second_call_url, $urlparam);

		    if (strstr($data, '<status>')) {
                        preg_match('/status>?([^<]+)/i', $data, $matches);
                        $nn_status_code = $matches[1];
                    }
		    else 
		        $nn_status_code = 0;
			
		    if($this->debug == 'yes')
			$this->log->add('novalnetpayments','Transaction status received : '.$nn_status_code);

		    return ($nn_status_code);

		}	// End do_make_transaction_status()


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

                    $this->novalnet_payment_method = $this->id = get_class($this);

                    if (in_array($this->novalnet_payment_method, $this->front_end_form_available_array))
                        $this->has_fields = true;

                    $this->do_initialize_novalnet_language();
                    $this->do_make_payment_details_array();

                    // Load the form fields.
                    $this->init_form_fields();

                    // Load the settings.
                    $this->init_settings();

                    if (isset($_REQUEST))
                        $this->do_check_novalnet_backend_data_validation_from_backend($_REQUEST);

                    $this->do_assign_config_vars_to_members();

                    // Logs
                    if (isset($this->debug) && $this->debug == 'yes')
                        $this->log = $woocommerce->logger();

                    if (!$this->is_valid_for_use())
                        $this->enabled = false;

                    // novalnet page sessions
                    if (isset($_SESSION['novalnet_thankyou_page_got']))
                        unset($_SESSION['novalnet_thankyou_page_got']);
                    if (isset($_SESSION['novalnet_receipt_page_got']))
                        unset($_SESSION['novalnet_receipt_page_got']);
                    if (isset($_SESSION['novalnet_transactional_info_got']))
                        unset($_SESSION['novalnet_transactional_info_got']);
                    if (isset($_SESSION['novalnet_email_notes_got']))
                        unset($_SESSION['novalnet_email_notes_got']);
		    if(isset($_SESSION['api_call_check_action_got']))
			unset($_SESSION['api_call_check_action_got']);

                    add_action('init', array(&$this, 'do_check_novalnet_payment_status'));

                    /* Save hook settings	 */
                    if (version_compare($woocommerce->version, '2.0.0', '>='))
                        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));#woocommerce version > 2.0.0
                    else
                        add_action('woocommerce_update_options_payment_gateways', array(&$this, 'process_admin_options'));#woocommerce version < 2.0.0

		    // actions to perform
                    add_action('woocommerce_receipt_' . $this->novalnet_payment_method, array(&$this, 'receipt_page'));
                    add_action('woocommerce_order_details_after_order_table', array($this, 'novalnet_transactional_info')); // Novalnet Transaction Information
                    add_action('woocommerce_email_after_order_table', array($this, 'novalnet_email_instructions'), 15, 2); // customer email instruction
                    add_action('woocommerce_thankyou_' . $this->novalnet_payment_method, array(&$this, 'thankyou_page'));

		    add_action('wp_before_admin_bar_render', array($this, 'nn_api_call_check'));
		    add_action('add_meta_boxes', array($this, 'novalnet_transaction_meta_boxes'));

		    /* Novalnet Credit card iframe server request */
                    $this->do_check_is_any_request_to_print_cc_iframe();

                }	// End __construct()

		/**
		 *Novalnet transaction meta box
		 */
		public function novalnet_transaction_meta_boxes() {

		   add_meta_box('novalnet-gateway-transaction-actions', __('Novalnet Payment Status Management', 'woocommerce-novalnetpayment'), array($this, 'novalnet_gateway_transaction_meta_box'), 'shop_order', 'side', 'default');

		}


		public function novalnet_gateway_transaction_meta_box() {

		    global $post, $woocommerce;

		    $this->order = new WC_Order( $post->ID );

		    ?>
		    <p><strong> <?php echo  __('Current Transaction Status: ','woocommerce-novalnetpayment').__($this->order->status,'woocommerce'); ?></strong></p>


								<?php if (get_post_meta($this->order->id,'_nn_status_code', true) < 100 && in_array($this->order->payment_method, $this->novalnet_mod_ext_array) && get_post_meta($this->order->id,'_nn_ref_code', true) == 0 ) :?>

								<ul class="order_actions">

								<li>
									<a id="nn_capture"  class="button button-primary tips" data-tip="<?php echo __( 'Capture the current Transaction', 'woocommerce-novalnetpayment' ); ?>" onclick="if (!window.confirm('<?php echo __('Are you sure you want to capture the payment?','woocommerce-novalnetpayment'); ?>')){return false;}" href="<?php echo admin_url('post.php?post='.$post->ID.'&action=edit&novalnet_gateway_action=nn_capture'); ?> "><?php echo __('Capture', 'woocommerce-novalnetpayment'); ?></a>

								</li>


								<li>
									<a id="nn_void" class="button tips" data-tip="<?php echo __( 'Cancel the current Transaction', 'woocommerce-novalnetpayment' ); ?>" onclick="if (!window.confirm('<?php echo __('Are you sure you want to cancel the payment?','woocommerce-novalnetpayment'); ?>')){return false;}" href="<?php echo admin_url('post.php?post='.$post->ID.'&action=edit&novalnet_gateway_action=nn_void'); ?> "><?php echo __('Void', 'woocommerce-novalnetpayment'); ?></a>

								</li>
								</ul>
								<?php endif ?>

								<?php if ((get_post_meta($this->order->id,'_nn_status_code', true) == 100 ) && $this->order->order_total > 0 && in_array($this->order->payment_method, $this->novalnet_mod_ext_array) && get_post_meta($this->order->id,'_nn_ref_code', true) == 0) :?>
								<ul>

								    <li class="wide">
										<label><?php echo __( 'Amount to be refunded:', 'woocommerce-novalnetpayment' ); ?></label>
										<input type="number" step="any" id="refund_amount" class="first" name="refund_amount" placeholder="0.00" value=" " />
								    </li>
								</ul>
								<p class="buttons">
								   <a id="nn_refund" class="button button-primary tips" data-tip="<?php echo __( 'Refund the current Transaction', 'woocommerce-novalnetpayment' ); ?>" onclick= "nn_refund_amount('<?php echo admin_url('post.php?post='.$post->ID.'&action=edit&novalnet_gateway_action=nn_refund'); ?>',<?php echo $this->order->order_total ?>)" href="javascript:void(0)"><?php echo __('Refund', 'woocommerce-novalnetpayment'); ?></a>
								</p>

								<script>
								    function nn_refund_amount(url, tot_amount) {

									if (tot_amount >= document.getElementById('refund_amount').value && document.getElementById('refund_amount').value > 0 ) {

									    if ( !window.confirm('<?php echo __('Are you sure you want to refund the amount?','woocommerce-novalnetpayment'); ?>')){return false;}
									    window.location.href = url + "&nn_var_ref_amount="+document.getElementById('refund_amount').value;
									}
									else if (!window.confirm('<?php echo __('Please enter the correct refund amount','woocommerce-novalnetpayment'); ?>')) {
										return false;
									    
									}

								    }
								    </script>
								<?php endif ?>


					<br />
		    <?php
		}

                /**
                 * Output for the order received page.
                 *
                 * @access public
                 * @return void
                 */
                public function thankyou_page() {

                    if (!isset($_SESSION['novalnet_thankyou_page_got'])) {

                        if ($this->instructions)
                            echo wpautop(wptexturize($this->instructions));

                        $_SESSION['novalnet_thankyou_page_got'] = 1;
                    }
                }	// End thankyou_page()


		/**
		 * Verify novalnet gateway action
		 */
		public function nn_api_call_check() {

		    if(!isset($_SESSION['api_call_check_action_got'])) {
			if(is_admin()) {
				if(isset($_GET['novalnet_gateway_action']) AND in_array($_GET['novalnet_gateway_action'], $this->allowed_actions) AND isset($_GET['post'])) {
					global $woocommerce;
					$this->order = new WC_Order( intval( $_GET['post'] ) );
					$this->nn_api_action_pass($this->order->id , $_GET['novalnet_gateway_action']);
				}
			}
			$_SESSION['api_call_check_action_got'] =1;
		    }

		}	// End nn_api_call_check()

		/**
		 * routes the respective action as per request
		 */
		private function nn_api_action_pass( $order_id, $action ) {
			if( ! isset($this->order))
				$this->order = new WC_Order( $order_id );

			if(in_array($action, $this->allowed_actions)) {
				call_user_func(array($this, 'do_make_' . $action));
			}
		}	// End nn_api_action_pass()

		/**
		 * perform action while capture is called
		 */
		private function do_make_nn_capture() {
			
			if (get_post_meta($this->order->id,'_nn_status_code', true) < 100 && in_array($this->order->payment_method, $this->novalnet_mod_ext_array ) && get_post_meta($this->order->id,'_nn_ref_code', true) == 0 ) {
			
				$nn_capt_new_line = "\n";

				// get the payment method
				$nn_capt_payment = $this->order->payment_method;
				$nn_capt_obj = new $nn_capt_payment();

				if (!in_array($nn_capt_payment, $this->manual_check_limit_not_available_array)) {

				if (isset($nn_capt_obj->manual_check_limit) && $nn_capt_obj->manual_check_limit > 0 && ( str_replace(',', '', number_format($this->order->order_total, 2)) * 100 ) >= $nn_capt_obj->manual_check_limit) {

					if ($nn_capt_obj->product_id_2 && $nn_capt_obj->tariff_id_2) {
					$nn_capt_obj->product_id = $nn_capt_obj->product_id_2;
					$nn_capt_obj->tariff_id = $nn_capt_obj->tariff_id_2;
					}
				}
				}

				$nn_capt_note = $this->order->customer_note;
				preg_match('/ID\:\s([0-9]{17})\s/',$nn_capt_note,$nn_capt_flag);

				$nn_capt_tid = $nn_capt_flag[1];

				$nn_capt_param['edit_status'] = 1;
				$nn_capt_param['vendor'] = $nn_capt_obj->vendor_id;
				$nn_capt_param['auth_code'] = $nn_capt_obj->auth_code;
				$nn_capt_param['product'] = $nn_capt_obj->product_id;
				$nn_capt_param['tariff'] = $nn_capt_obj->tariff_id;
				$nn_capt_param['key'] = $nn_capt_obj->payment_key;
				$nn_capt_param['status'] = 100;
				$nn_capt_param['tid'] = $nn_capt_tid;

				/**	 basic validation for capture api call	*/
				$return = $this->do_make_nn_api_validate($nn_capt_obj->vendor_id, $nn_capt_obj->auth_code, $nn_capt_obj->product_id, $nn_capt_obj->tariff_id, $nn_capt_obj->payment_key, $nn_capt_tid, $this->order->id);

				if ($return == true) {

					list($errno, $errmsg, $data) = $this->perform_https_request($this->novalnet_paygate_url, $nn_capt_param);

					#capture the result and message and other parameters from response data '$data' in an array
					$aryCaptPaygateResponse = explode('&', $data);

					foreach ($aryCaptPaygateResponse as $key => $value) {
						if ($value != "") {
						$aryKeyVal = explode("=", $value);
						$aryCaptResponse[$aryKeyVal[0]] = $aryKeyVal[1];
						}
					}

					if ($aryCaptResponse['status'] == 100) {
						update_post_meta($this->order->id,'_nn_status_code', $aryCaptResponse['status']);
						$nn_capt_message = sprintf(__('Novalnet Capture action successfully takes place on %s','woocommerce-novalnetpayment'), date('Y-m-d H:i:s') );
						$this->order->update_status('processing',$nn_capt_message);

						add_post_meta( $this->order->id, '_paid_date', current_time('mysql'), true );

						if ($this->order->customer_note)
						$this->order->customer_note .= $nn_capt_new_line;

						$this->order->customer_note .= $nn_capt_new_line.' '.$nn_capt_message.' '.$nn_capt_new_line;

						/** Update Novalnet capture Transaction details into shop database	 */
						$nn_capt_order_notes = array(
						'ID' => $this->order->id,
						'post_excerpt' => $this->order->customer_note
						);
						wp_update_post($nn_capt_order_notes);

					}
					 else {
							if($aryCaptResponse['status_desc'] != null)
								$nn_capt_err = sprintf ( __('Novalnet Capture action failed due to %s','woocommerce-novalnetpayment'), $aryCaptResponse['status_desc'] );
							else
								$nn_capt_err = __('Novalnet Capture action failed','woocommerce-novalnetpayment');
							$this->order->add_order_note($nn_capt_err);
						}
				}
			}
			wp_safe_redirect(admin_url('post.php?post='.$this->order->id.'&action=edit'));
			exit;

		}

		/**
		 * perform action while void (cancel) is called
		 */
		private function do_make_nn_void() {
		
			if (get_post_meta($this->order->id,'_nn_status_code', true) < 100 && in_array($this->order->payment_method, $this->novalnet_mod_ext_array ) && get_post_meta($this->order->id,'_nn_ref_code', true) == 0 ) {

				$nn_void_new_line = "\n";

				// get the payment method
				$nn_void_payment = $this->order->payment_method;
				$nn_void_obj = new $nn_void_payment();

				if (!in_array($nn_void_payment, $this->manual_check_limit_not_available_array)) {

				if (isset($nn_void_obj->manual_check_limit) && $nn_void_obj->manual_check_limit > 0 && ( str_replace(',', '', number_format($this->order->order_total, 2)) * 100 ) >= $nn_void_obj->manual_check_limit) {

					if ($nn_void_obj->product_id_2 && $nn_void_obj->tariff_id_2) {
					$nn_void_obj->product_id = $nn_void_obj->product_id_2;
					$nn_void_obj->tariff_id = $nn_void_obj->tariff_id_2;
					}
				}
				}

				$nn_void_note = $this->order->customer_note;
				preg_match('/ID\:\s([0-9]{17})\s/',$nn_void_note,$nn_void_flag);

				$nn_void_tid = $nn_void_flag[1];

				$nn_void_param['edit_status'] = 1;
				$nn_void_param['vendor'] = $nn_void_obj->vendor_id;
				$nn_void_param['auth_code'] = $nn_void_obj->auth_code;
				$nn_void_param['product'] = $nn_void_obj->product_id;
				$nn_void_param['tariff'] = $nn_void_obj->tariff_id;
				$nn_void_param['key'] = $nn_void_obj->payment_key;
				$nn_void_param['status'] = 103;
				$nn_void_param['tid'] = $nn_void_tid;

				/**	 basic validation for void api call	*/
				$return = $this->do_make_nn_api_validate($nn_void_obj->vendor_id, $nn_void_obj->auth_code, $nn_void_obj->product_id, $nn_void_obj->tariff_id, $nn_void_obj->payment_key, $nn_void_tid, $this->order->id);

				if ($return == true) {

					list($errno, $errmsg, $data) = $this->perform_https_request($this->novalnet_paygate_url, $nn_void_param);

					#capture the result and message and other parameters from response data '$data' in an array
					$aryVoidPaygateResponse = explode('&', $data);

					foreach ($aryVoidPaygateResponse as $key => $value) {
						if ($value != "") {
						$aryKeyVal = explode("=", $value);
						$aryVoidResponse[$aryKeyVal[0]] = $aryKeyVal[1];
						}
					}
					
					if ($aryVoidResponse['status'] == 100) {
						update_post_meta($this->order->id,'_nn_status_code',$aryVoidResponse['status']);
						$nn_void_message = sprintf(__('Novalnet Void action successfully takes place on %s','woocommerce-novalnetpayment'), date('Y-m-d H:i:s') );

						// Order cancellation
						$this->order->update_status('cancelled', $nn_void_message);
						do_action('woocommerce_cancelled_order', $this->order->id);
						
						// update status code
						update_post_meta($this->order->id,'_nn_ref_code', 1);
						
						if ($this->order->customer_note)
						$this->order->customer_note .= $nn_void_new_line;

						$this->order->customer_note .= $nn_void_new_line.' '.$nn_void_message.' '.$nn_void_new_line;

						/** Update Novalnet voidure Transaction details into shop database	 */
						$nn_void_order_notes = array(
						'ID' => $this->order->id,
						'post_excerpt' => $this->order->customer_note
						);
						wp_update_post($nn_void_order_notes);
					}
					else {
							if($aryVoidResponse['status_desc'] != null)
								$nn_void_err = sprintf ( __('Novalnet Void action failed due to %s','woocommerce-novalnetpayment'), $aryVoidResponse['status_desc'] );
							else
								$nn_void_err = __('Novalnet Void action failed','woocommerce-novalnetpayment');
							$this->order->add_order_note($nn_void_err);
						}
				}
			}
			wp_safe_redirect(admin_url('post.php?post='.$this->order->id.'&action=edit'));
			exit;
		}

		/**
		 * perform action while refund is called
		 */
		private function do_make_nn_refund() {
		   
		   if ((get_post_meta($this->order->id,'_nn_status_code', true) == 100 ) && $this->order->order_total > 0 && in_array($this->order->payment_method, $this->novalnet_mod_ext_array) && get_post_meta($this->order->id,'_nn_ref_code', true) == 0) {
			   $nn_ref_new_line = "\n";

				// get the payment method
				$nn_ref_payment = $this->order->payment_method;
				$nn_ref_obj = new $nn_ref_payment();
				$nn_ref_amount = $_GET['nn_var_ref_amount'];

				if (!in_array($nn_ref_payment, $this->manual_check_limit_not_available_array)) {

				if (isset($nn_ref_obj->manual_check_limit) && $nn_ref_obj->manual_check_limit > 0 && ( str_replace(',', '', number_format($this->order->order_total, 2)) * 100 ) >= $nn_ref_obj->manual_check_limit) {

					if ($nn_ref_obj->product_id_2 && $nn_ref_obj->tariff_id_2) {
					$nn_ref_obj->product_id = $nn_ref_obj->product_id_2;
					$nn_ref_obj->tariff_id = $nn_ref_obj->tariff_id_2;
					}
				}
				}
				
				 if (($this->order->payment_method == 'novalnet_elv_at' || $this->order->payment_method == 'novalnet_elv_de')) {
   
				   if (get_post_meta($this->order->id,'_nn_ref_tid', true) == 0) {
						$nn_ref_note = $this->order->customer_note;
						preg_match('/ID\:\s([0-9]{17})\s/',$nn_ref_note,$nn_ref_flag);
						$nn_ref_tid = $nn_ref_flag[1];
					}
					else
						$nn_ref_tid = get_post_meta($this->order->id,'_nn_ref_tid', true);
				}
				else {
					$nn_ref_note = $this->order->customer_note;
					preg_match('/ID\:\s([0-9]{17})\s/',$nn_ref_note,$nn_ref_flag);
					$nn_ref_tid = $nn_ref_flag[1];
				}
				
				$nn_ref_param['vendor'] = $nn_ref_obj->vendor_id;
				$nn_ref_param['auth_code'] = $nn_ref_obj->auth_code;
				$nn_ref_param['product'] = $nn_ref_obj->product_id;
				$nn_ref_param['tariff'] = $nn_ref_obj->tariff_id;
				$nn_ref_param['key'] = $nn_ref_obj->payment_key;
				$nn_ref_param['tid'] = $nn_ref_tid;
				$nn_ref_param['refund_request'] = 1;
				$nn_ref_param['refund_param'] = (str_replace(',', '', number_format($nn_ref_amount, 2)) * 100);

				/**	 basic validation for refund api call	*/
				$return = $this->do_make_nn_api_validate($nn_ref_obj->vendor_id, $nn_ref_obj->auth_code, $nn_ref_obj->product_id, $nn_ref_obj->tariff_id, $nn_ref_obj->payment_key,$nn_ref_tid, $this->order->id);

				if ($return == true) {

					list($errno, $errmsg, $data) = $this->perform_https_request($this->novalnet_paygate_url, $nn_ref_param);

					#capture the result and message and other parameters from response data '$data' in an array
					$aryRefPaygateResponse = explode('&', $data);

					foreach ($aryRefPaygateResponse as $key => $value) {
						if ($value != "") {
						$aryKeyVal = explode("=", $value);
						$aryRefResponse[$aryKeyVal[0]] = $aryKeyVal[1];
						}
					}

					if ($aryRefResponse['status'] == 100) {
						
						update_post_meta($this->order->id,'_nn_status_code',$aryRefResponse['status']);
						
						if(isset($aryRefResponse['tid']) && $aryRefResponse['tid'] >0) {
							
							if (($this->order->payment_method == 'novalnet_elv_at' || $this->order->payment_method == 'novalnet_elv_de'))
								update_post_meta ($this->order->id,'_nn_ref_tid',$aryRefResponse['tid']);
							
							$nn_ref_message = sprintf(__('Novalnet Refund action successfully takes place on %s for refund amount %s and Transaction ID for payment is %s','woocommerce-novalnetpayment'), date('Y-m-d H:i:s'), strip_tags(woocommerce_price($nn_ref_amount)), $aryRefResponse['tid'] );
							}
						else
							$nn_ref_message = sprintf(__('Novalnet Refund action successfully takes place on %s for refund amount %s','woocommerce-novalnetpayment'), date('Y-m-d H:i:s'), strip_tags(woocommerce_price($nn_ref_amount)) );

						$nn_bal_amount = (float)$this->order->order_total - (float)$nn_ref_amount;	// balance amount
						if ($nn_bal_amount != 0) {
							$this->order->update_status('processing');
							$this->order->add_order_note($nn_ref_message);
						}
						else
							$this->order->update_status('refunded', $nn_ref_message);	// Order refunded

						if ($this->order->customer_note)
							$this->order->customer_note .= $nn_ref_new_line;

						$this->order->customer_note .= $nn_ref_new_line.' '.html_entity_decode($nn_ref_message, ENT_QUOTES, 'UTF-8').' '.$nn_ref_new_line;
						update_post_meta( $this->order->id, '_order_total', $nn_bal_amount);

						/** Update Novalnet refund Transaction details into shop database	 */
						$nn_ref_order_notes = array(
						'ID' => $this->order->id,
						'post_excerpt' => $this->order->customer_note
						);
						wp_update_post($nn_ref_order_notes);

					}
					else {
							if($aryRefResponse['status_desc'] != null)
								$nn_ref_err = sprintf ( __('Novalnet Refund action failed due to %s','woocommerce-novalnetpayment'), $aryRefResponse['status_desc'] );
							else
								$nn_ref_err = __('Novalnet Refund action failed','woocommerce-novalnetpayment');
							$this->order->add_order_note($nn_ref_err);
						}
				}
			}
			wp_safe_redirect(admin_url('post.php?post='.$this->order->id.'&action=edit'));
			exit;
		}

		/**
		 * api call basic Validation
		 */
		public function do_make_nn_api_validate ($api_vendor_id, $api_auth_code, $api_product_id, $api_tariff_id, $api_key, $api_tid, $api_id) {

		    if ($api_vendor_id != null && $this->is_digits($api_vendor_id) && $api_product_id != null && $this->is_digits($api_product_id) && isset($api_key) && $api_key != null && $api_tariff_id != null && $this->is_digits($api_tariff_id) && $api_auth_code != null && isset($api_tid) && $api_tid != null && $api_id != null && $api_key != null)
			return true;
		    else
		        return false;

		}

                /**
                 * Add text to order confirmation mail to customer below order table
                 * called by 'woocommerce_email_after_order_table' action
                 *
                 * @access public
                 * @return void
                 */
                public function novalnet_email_instructions($order, $sent_to_admin) {

                    if ($order->payment_method == $this->novalnet_payment_method && (!isset($_SESSION['novalnet_email_notes_got']) || (($sent_to_admin == 0) && (!isset($_SESSION['novalnet_email_notes_got']) || $_SESSION['novalnet_email_notes_got'] != 2)))) {

                        // email instructions
                        if ($this->email_notes)
                            echo wpautop(wptexturize($this->email_notes));

                        if ($sent_to_admin)
                            $_SESSION['novalnet_email_notes_got'] = 1;
                        else
                            $_SESSION['novalnet_email_notes_got'] = 2;
                    }
                }	//End novalnet_email_instructions()

                /**
                 * Add Novalnet Transactional Information to order details table
                 * called by 'woocommerce_order_details_after_order_table' action
                 *
                 * @access public
                 * @return void
                 */
                public function novalnet_transactional_info($order) {

                    if (!isset($_SESSION['novalnet_transactional_info_got']) && $order->payment_method == $this->novalnet_payment_method) {

                        // Novalnet Transaction Information
                        echo wpautop('<h2>' . __('Transaction Information', 'woocommerce-novalnetpayment') . '</h2>');
                        echo wpautop(wptexturize($order->customer_note));

                        $_SESSION['novalnet_transactional_info_got'] = 1;
                    }
                }	// End novalnet_transactional_info()

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
                        $icon_html = '<a href="' . (strtolower($this->language) == 'de' ? 'https://www.novalnet.de' : 'http://www.novalnet.com') . '" alt="' . __('novalnet.com', 'woocommerce-novalnetpayment') . '" target="_new"><img height ="25" src="' . $this->icon . '" alt="' . $this->method_title . '" title="'.$this->title.'" /></a>';

                    return($icon_html);

                }	// End get_icon()

                /**
                 * Displays Novalnet Logo icon
                 */
                public function get_title() {

                    $novalnet_logo_html = '';
                    if ($this->novalnet_logo && isset($_SERVER['HTTP_REFERER']) && !strstr($_SERVER['HTTP_REFERER'], 'wp-admin') && ((isset($_REQUEST['action']) && $_REQUEST['action'] == 'woocommerce_update_order_review' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest') || (!isset($_REQUEST['woocommerce_pay']) && isset($_GET['pay_for_order']) && isset($_GET['order_id']) && isset($_GET['order']))))
                        $novalnet_logo_html = '<a href="' . (strtolower($this->language) == 'de' ? 'https://' : 'http://') . 'www.' . __('novalnet.com', 'woocommerce-novalnetpayment') . '" alt="' . __('novalnet.com', 'woocommerce-novalnetpayment') . '" target="_new"><img src="' . (is_ssl() ? 'https://' : 'http://') . __('www.novalnet.de/img/NN_Logo_T.png', 'woocommerce-novalnetpayment') . '" alt="' . __('novalnet.com', 'woocommerce-novalnetpayment') . '" title="Novalnet AG" /></a>&nbsp;';
                    return($novalnet_logo_html . ' ' . $this->title);

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

                    }

                    // payment form
                    switch ($this->novalnet_payment_method) {

                        case 'novalnet_cc':

                            /* Novalnet Credit Card Payment form */
                            print '<br /><div id="loading_iframe_div" style="display:;"><img alt="' . __('Loading...', 'woocommerce-novalnetpayment') . '" src="' . (is_ssl() ? 'https://www.novalnet.de/img/novalnet-loading-icon.gif' : 'http://www.novalnet.de/img/novalnet-loading-icon.gif') . '"></div><input type="hidden" name="cc_type" id="cc_type" value="" /><input type="hidden" name="cc_holder" id="cc_holder" value="" /><input type="hidden" name="cc_exp_month" id="cc_exp_month" value="" /><input type="hidden" name="cc_exp_year" id="cc_exp_year" value="" /><input type="hidden" name="cc_cvv_cvc" id="cc_cvv_cvc" value="" /><input type="hidden" id="original_vendor_id" value="' . ($this->vendor_id) . '" /><input type="hidden" id="original_vendor_authcode" value="' . ($this->auth_code) . '" /><input type="hidden" id="original_customstyle_css" value="" /><input type="hidden" id="original_customstyle_cssval" value="" /><input type="hidden" name="nn_unique_id" id="nn_unique_id" value="" /><input type="hidden" name="nn_cardno_id" id="nn_cardno_id" value="" /><iframe onLoad="doHideLoadingImageAndDisplayIframe(this);" name="novalnet_cc_iframe" id="novalnet_cc_iframe" src="' . site_url() . '/wp-content/plugins/woocommerce-novalnet-gateway/includes/novalnet_cc_iframe.html" scrolling="no" frameborder="0" style="width:100%; height:285px; border:none; display:none;"></iframe>
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
			    </script>';

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

                            /* Novalnet Direct Debit German Payment form */
                            print $this->do_print_form_elements_for_novalnet_elv_de_at('de');
                            break;

                        case 'novalnet_elv_at':

                            /* Novalnet Direct Debit Austria Payment form */
                            print $this->do_print_form_elements_for_novalnet_elv_de_at('at');
                            break;
							
                    }	#End Switchcase
                }	// End payment_fields()

                /*
                 * Process the payment and return the result
                 */
                public function process_payment($order_id) {
					
					// novalnet status code update
					add_post_meta($order_id,'_nn_status_code',0);
					add_post_meta($order_id,'_nn_ref_code',0);
					add_post_meta($order_id,'_nn_ref_tid',0);
					
                    return($this->do_process_payment_from_novalnet_payments($order_id));

                }	// End process_payment()

                /**
                 * Receipt_page
                 */
                public function receipt_page($order_id) {

                    $order = new WC_Order($order_id);
                    $this->do_check_novalnet_backend_data_validation_from_frontend();
                    $this->do_check_shop_parameter($order);
                    $this->do_necessary_actions_before_prepare_to_novalnet_payport_or_paygate($order);
                    $this->do_prepare_to_novalnet_payport($order);

                }	// End receipt_page()

                /**
                 * Check if this gateway is enabled and available
                 *
                 * @access public
                 * @return bool
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

                    // Payment title field
                    $this->form_fields['title'] = array(
                        'title' => __('Payment Title', 'woocommerce-novalnetpayment') . '<span style="color:red;">*</span>',
                        'type' => 'text',
                        'description' => '',
                        'default' => $this->payment_details[$this->novalnet_payment_method]['payment_name']
                    );

                    // Payment description field
                    $this->form_fields['description'] = array(
                        'title' => __('Description', 'woocommerce-novalnetpayment'),
                        'type' => 'textarea',
                        'description' => '',
                        'default' => $this->payment_details[$this->novalnet_payment_method]['payment_description']
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
                    }

                    // Enable ACDC
                    if ($this->novalnet_payment_method == 'novalnet_elv_de') {
                        $this->form_fields['acdc'] = array(
                            'title' => __('Enable credit rating check', 'woocommerce-novalnetpayment'),
                            'type' => 'checkbox',
                            'label' => '',
                            'default' => ''
                        );
                    }

                    // Payment duration field
                    if ($this->novalnet_payment_method == 'novalnet_invoice') {
                        $this->form_fields['payment_duration'] = array(
                            'title' => __('Payment period in days', 'woocommerce-novalnetpayment'),
                            'type' => 'text',
                            'label' => '',
                            'default' => ''
                        );
                    }

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
                    }

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
                    }

                    // Proxy server field (required for cURL protocol, if the client set any proxy port in their server)
                    $this->form_fields['payment_proxy'] = array(
                        'title' => __('Proxy-Server', 'woocommerce-novalnetpayment'),
                        'type' => 'text',
                        'description' => __('If you use a Proxy Server, enter the Proxy Server IP with port here (e.g. www.proxy.de:80)', 'woocommerce-novalnetpayment'),
                        'default' => ''
                    );

                    // Enable thank you page instructions
                    $this->form_fields['instructions'] = array(
                        'title' => __('Thank You page Instructions', 'woocommerce-novalnetpayment'),
                        'type' => 'textarea',
                        'description' => __('Instructions that will be added to the thank you page.', 'woocommerce-novalnetpayment'),
                        'default' => ''
                    );

                    // Enable email instructions
                    $this->form_fields['email_notes'] = array(
                        'title' => __('E-mail Instructions', 'woocommerce-novalnetpayment'),
                        'type' => 'textarea',
                        'description' => __('Instructions that will be added to the order confirmation email', 'woocommerce-novalnetpayment')
                    );

                    // Enable Novalnet Logo
                    $this->form_fields['novalnet_logo'] = array(
                        'title' => __('Enable Novalnet Logo', 'woocommerce-novalnetpayment'),
                        'type' => 'select',
                        'options' => array('0' => __('No', 'woocommerce-novalnetpayment'), '1' => __('Yes', 'woocommerce-novalnetpayment')),
                        'description' => __('To display Novalnet logo in front end', 'woocommerce-novalnetpayment'),
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

                    // Gateway Testing
                    $this->form_fields['gateway_testing'] = array(
                        'title' => __('Gateway Testing', 'woocommerce-novalnetpayment'),
                        'type' => 'title',
                        'description' => '',
                    );

                    // Enable test mode
                    $this->form_fields['test_mode'] = array(
                        'title' => __('Enable Test Mode', 'woocommerce-novalnetpayment'),
                        'type' => 'select',
                        'options' => array('0' => __('No', 'woocommerce-novalnetpayment'), '1' => __('Yes', 'woocommerce-novalnetpayment')),
                        'description' => '',
                        'default' => ''
                    );

                    // Enable Debug Log
                    $this->form_fields['debug'] = array(
                        'title' => __('Debug Log', 'woocommerce-novalnetpayment'),
                        'type' => 'checkbox',
                        'label' => __('Enable logging', 'woocommerce-novalnetpayment'),
                        'default' => 'no',
                        'description' => sprintf( __('Log Novalnet Payment events inside <code>woocommerce/logs/novalnetpayments-%s.txt</code>', 'woocommerce-novalnetpayment'),sanitize_file_name( wp_hash( 'novalnetpayments' ) ))
                    );
                }	// End init_form_fields()
            }	// End class WC_Gateway_Novalnet
        }	#Endif
    }	#Endif
}	// End init_gateway_novalnet()

/* initiate novalnet payment methods	 */
if (isset($_REQUEST['novalnet_payment_method']) && in_array($_REQUEST['novalnet_payment_method'], $novalnet_payment_methods))
    require_once(dirname(__FILE__) . '/includes/' . $_REQUEST['novalnet_payment_method'] . '.php');
else {
    foreach ($novalnet_payment_methods as $novalnet_payment_method)
        require_once(dirname(__FILE__) . '/includes/' . $novalnet_payment_method . '.php');
    ob_get_clean();
}
?>
