<?php
/*
 * Plugin Name: Woocommerce Payment Gateway by Novalnet
 * Plugin URI:  https://www.novalnet.de/
 * Description: Adds Novalnet Payment Gateway to Woocommerce e-commerce plugin
 * Author:      Novalnet
 * Author URI:  https://www.novalnet.de
 * Version:     1.1.7
 * Text Domain: woocommerce-novalnetpayment
 * Domain Path: /languages/
 * License: 	GPLv2
 */

// Exit if accessed directly
if (!defined('ABSPATH'))
    exit;

register_deactivation_hook(__FILE__, 'novalnet_deactivation');
register_uninstall_hook(__FILE__, 'novalnet_deactivation');
add_action('admin_notices', 'novalnet_admin_notices');
add_action('plugins_loaded', 'init_gateway_novalnet', 0);
add_action('wp_logout', 'novalnet_unset_process');
add_action('wp_login', 'novalnet_unset_process');

// actions to perform once on plugin deactivation
if (!function_exists('novalnet_deactivation')) {
    function novalnet_deactivation() {
		global $wpdb;
        $wpdb->query("delete from $wpdb->options where option_name like 'woocommerce_novalnet_%'");
    }
}

// Get active network plugins
if (!function_exists('nn_active_nw_plugins')) {
    function nn_active_nw_plugins() {
        if (!is_multisite())
            return false;
        $nn_activePlugins = (get_site_option('active_sitewide_plugins')) ? array_keys(get_site_option('active_sitewide_plugins')) : array();
        return $nn_activePlugins;
    }
}

// Display admin notice at back-end during plugin activation
function novalnet_admin_notices() {
    if (!is_plugin_active('woocommerce/woocommerce.php')) {
        echo '<div id="notice" class="error"><p>';
        echo '<b>' . __('Woocommerce Payment Gateway by Novalnet', WC_Gateway_Novalnet::get_textdomain()) . '</b> ' . __('add-on requires', WC_Gateway_Novalnet::get_textdomain()) . ' ' . '<a href="http://www.woothemes.com/woocommerce/" target="_new">' . __('WooCommerce', WC_Gateway_Novalnet::get_textdomain()) . '</a>' . ' ' . __('plugin. Please install and activate it.', WC_Gateway_Novalnet::get_textdomain());
        echo '</p></div>', "\n";
    }
    $novalnet_message = isset($_REQUEST['nn_message']) ? $_REQUEST['nn_message'] : '';
    if(!empty($novalnet_message))
        echo '<div class = "updated">'.$novalnet_message.'</div>';
}

function custom_admin_js() {
	global $novalnet_payment_methods;
	$redirect = get_admin_url() . 'admin.php?' . http_build_query($_GET);
	if (isset($_GET['wc_error']) && in_array($_GET['section'] , $novalnet_payment_methods)){
		$redirect = remove_query_arg('wc_error');
		echo '<script type="text/javascript">
			var url = "'.$redirect.'"
			jQuery("form").attr("action",url);
		  </script>';
	}
}

// Clears Novalnet payments session
function novalnet_unset_process(){
	if(isset($_SESSION) && $_SESSION ){
		$_SESSION['sepa'] = false;
		$_SESSION['cc'] = false;

		if(isset($_SESSION['tel']))
			unset($_SESSION['tel']);
	}
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
	  'nn_telephone'	   =>'novalnet_tel'
);

/**
 * Initiate plugin actions
 */
function init_gateway_novalnet() {

    /* verify whether woocommerce is an active plugin before initializing Novalnet Payment Gateway */
    if (in_array('woocommerce/woocommerce.php', (array) get_option('active_plugins')) || in_array('woocommerce/woocommerce.php', (array) nn_active_nw_plugins())) {
        // Define Novalnet Gateway version constant
        if (!defined('NOVALNET_GATEWAY_VERSION'))
            define('NOVALNET_GATEWAY_VERSION','1.1.8');

		// Define Novalnet Status constant
        if(!defined('NOVALNET_COMPLETE_STATUS'))
			define('NOVALNET_COMPLETE_STATUS', 100);

		// Define Novalnet void status constant
        if(!defined('NOVALNET_VOID_STATUS'))
			define('NOVALNET_VOID_STATUS', 103);

		// Define Novalnet DIR
        if(!defined('NOVALNET_DIR'))
			define('NOVALNET_DIR', dirname(__FILE__));

		// Define Novalnet Gateway LOGO Path
        if(!defined('LOGO_PATH'))
            define('LOGO_PATH',site_url().'/wp-content/plugins/woocommerce-novalnet-gateway/assets/images/');

        if (!class_exists('WC_Payment_Gateway'))
            return;

        if (!class_exists('WC_Gateway_Novalnet')) {

            /**
             * Common class for Novalnet Payment Gateway
             */
            class WC_Gateway_Novalnet extends WC_Payment_Gateway {

				static public $textdomain = NULL;

                /* Novalnet Payment keys */
                var $payment_key_cc = 6;
                var $payment_key_invoice_prepayment = 27;
                var $payment_key_tel = 18;
                var $payment_key_paypal = 34;
                var $payment_key_online_transfer = 33;
                var $payment_key_ideal = 49;
                var $payment_key_sepa = 37;

                /* Novalnet Payment method arrays */
                var $front_end_form_available = array('novalnet_cc', 'novalnet_sepa');
                var $redirect_payments = array('novalnet_banktransfer', 'novalnet_ideal', 'novalnet_paypal');
                var $language_supported = array('en', 'de');
                var $novalnet_extensions = array('novalnet_cc', 'novalnet_invoice',  'novalnet_prepayment', 'novalnet_sepa');
                var $invoice_payments = array('novalnet_invoice', 'novalnet_prepayment');

                // allowed actions for module transaction
                private $allowed_actions = array('nn_capture', 'nn_void', 'nn_refund');
				var $payment_parameters = array();
                /**
                 * Telephone payment second call request
                 */
                public function telephone_secondcall($order_id) {
                    global $woocommerce;
                    $order = new WC_Order($order_id);
                    /* validate Telephone second call mandatory parameters   */
                    if (isset($this->vendor_id) && $this->is_digits($this->vendor_id) && isset($this->auth_code) && $this->auth_code != null && isset($_SESSION['tel']['novalnet_tel_tid']) && $_SESSION['tel']['novalnet_tel_tid'] != null && isset($this->language) && $this->language != null) {

                        ### Process the payment to infoport ##
                        $urlparam = '<?xml version="1.0" encoding="UTF-8"?><nnxml><info_request><vendor_id>' . $this->vendor_id . '</vendor_id>';
                        $urlparam .= '<vendor_authcode>' . $this->auth_code . '</vendor_authcode>';
                        $urlparam .= '<request_type>NOVALTEL_STATUS</request_type><tid>' . $_SESSION['tel']['novalnet_tel_tid'] . '</tid>';
                        $urlparam .= '<lang>' . strtoupper($this->language) . '</lang></info_request></nnxml>';
						list($errno, $errmsg, $data) = $this->perform_https_request($this->second_call_url, $urlparam);

                        if (strstr($data, '<novaltel_status>')) {
                            preg_match('/novaltel_status>?([^<]+)/i', $data, $matches);
                            $aryResponse['status'] = $matches[1];
                            preg_match('/novaltel_status_message>?([^<]+)/i', $data, $matches);
                            $aryResponse['status_desc'] = $matches[1];
                        } else {
							parse_str($data, $aryResponse);
                        }

                        $aryResponse['tid'] = $_SESSION['tel']['novalnet_tel_tid'];
                        $aryResponse['test_mode'] = $_SESSION['tel']['novalnet_tel_test_mode'];
                        $aryResponse['order_no'] = ltrim($order->get_order_number(), _x( '#', 'hash before order number', 'woocommerce' ));
                        $aryResponse['inputval1'] = $order_id;

                        //Manual Testing
                            //$aryResponse['status_desc'] = __('Successful', $this->get_textdomain());
                            //$aryResponse['status']      = 100;
                        //Manual Testing

                        return($this->check_novalnetstatus($aryResponse, $order_id));
                    }
                    else {
                        $this->unset_telephone_session();
                        $this->display_error_messages(__('Required parameter not valid', $this->get_textdomain()), 'error');
                        return($this->redirect_and_success_page('success', $woocommerce->cart->get_checkout_url()));
                    }
                }   // End telephone_secondcall()

                /**
                 * Clears Novalnet Telephone payment session
                 */
                public function unset_telephone_session() {
					if(isset($_SESSION['tel']))
						unset($_SESSION['tel']);

                }   // End unset_telephone_session()

                /**
                 * process Telephone payment server response
                 */
                public function check_tel_payment_status(&$aryResponse, $order) {

                    global $woocommerce;
                    $new_line = "<br />";

                    if ($aryResponse['status'] == NOVALNET_COMPLETE_STATUS && $aryResponse['tid']) {

                        $aryResponse['status_desc'] = '';

                        if(!isset($_SESSION['tel'])){
							$_SESSION['tel'] = array(
								'novalnet_tel_tid' => $aryResponse['tid'],
								'novalnet_tel_test_mode' => $aryResponse['test_mode'],
								'novalnet_tel_amount' => $this->amount
							);
						}
                    }

                    elseif ($aryResponse['status'] == 19)
                        unset($_SESSION['tel']['novalnet_tel_tid']);

                    else
                        $status = $aryResponse['status'];

                    if ($aryResponse['status'] == NOVALNET_COMPLETE_STATUS) {
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

						$this->display_error_messages(__('Following steps are required to complete your payment:', $this->get_textdomain()) . $new_line . $new_line . __('Step 1: Please call the telephone number displayed:', $this->get_textdomain()) . ' ' . $sess_tel . $new_line . str_replace('{amount}', $order->get_formatted_order_total(), __('* This call will cost {amount} (including VAT) and it is possible only for German landline connection! *', $this->get_textdomain())) . $new_line . $new_line . __('Step 2: Please wait for the beep and then hang up the listeners.', $this->get_textdomain()) . $new_line . __('After your successful call, please proceed with the payment.', $this->get_textdomain()), 'message');

                        return($this->redirect_and_success_page('success', $woocommerce->cart->get_checkout_url()));
                    }
                    else {
						$this->do_novalnet_cancel($aryResponse, $this->get_response_text($aryResponse), $order->id);
                    }
                }   // End check_tel_payment_status()

                /**
                 * Validate cart amount
                 */
                public function validate_tel_amount() {

                    global $woocommerce;

                    if ($this->amount < 99 || $this->amount > 1000) {

                        $this->display_error_messages(__('Amounts below 0,99 Euros and above 10,00 Euros cannot be processed and are not accepted!', $this->get_textdomain()), 'error');
                        return($this->redirect_and_success_page('success', $woocommerce->cart->get_checkout_url()));
                    }
                }   // End validate_tel_amount()

                /**
                 * Validate amount variations in cart
                 */
                public function validate_amount_variations() {

                    global $woocommerce;

                    if (isset($_SESSION['tel']['novalnet_tel_amount']) && $_SESSION['tel']['novalnet_tel_amount'] != $this->amount) {

                        unset($_SESSION['tel']);
                        $this->display_error_messages(__('You have changed the order amount after receiving telephone number, please try again with a new call', $this->get_textdomain()), 'error');
                        return($this->redirect_and_success_page('success', $woocommerce->cart->get_checkout_url()));
                    }

                    return('');
                }   // End validate_amount_variations()

                /**
                 * Process data after paygate response
                 */
                public function send_params_to_paygate($order) {
                    list($errno, $errmsg, $data) = $this->perform_https_request($this->payport_or_paygate_url, $this->payment_parameters);
                    $aryResponse = array();
                    parse_str($data, $aryResponse);
                    return($aryResponse);
                }   // End send_params_to_paygate()

                /**
                 * process parameters before sending to server
                 */
                public function form_params_to_payport_and_paygate($order) {
					$remote_ip = $this->getRealIpAddr();
                    $this->user_ip = ($remote_ip =='::1')?'127.0.0.1':$remote_ip;
                    $this->check_curl_installed_or_not();
                    $this->currency_format($order->order_total);
                    $this->check_and_assign_manual_check_limit();
                    $this->get_payment_parameters($order);

                }   // End form_params_to_payport_and_paygate()

                /**
                 * Generate Novalnet secure form
                 */
                public function get_novalnet_form_html($order) {
                    global $woocommerce;
                    $novalnet_args_array = array();
                    foreach ($this->payment_parameters as $key => $value) {
                        $novalnet_args_array[] = '<input type="hidden" name="' . esc_attr($key) . '" id="' . esc_attr($key) . '" value="' . esc_attr($value) . '" />';
                    }

                    if($this->novalnet_payment_method == 'novalnet_cc')
                        $this->payport_or_paygate_url = $this->novalnet_cc3d_payport_url;

                    $script = '
						$.blockUI({
							message: "' . esc_js( __( 'You will be redirected to Novalnet AG in a few seconds.', $this->get_textdomain() ) ) . '",
							baseZ: 99999,
							overlayCSS:
							{
								background: "#fff",
								opacity: 0.6
							},
							css: {
								padding:        "20px",
								zindex:         "9999999",
								textAlign:      "center",
								color:          "#555",
								border:         "3px solid #aaa",
								backgroundColor:"#fff",
								cursor:         "wait",
								lineHeight:		"24px",
							}
						});
						jQuery("#submit_novalnet_payment_form").click();
					';

					if (version_compare($woocommerce->version, '2.1.0', '>=')){
						wc_enqueue_js($script );
					}else{
						$woocommerce->add_inline_js($script );
					}

                    return '<form id="frmnovalnet" name="frmnovalnet" action="' . $this->payport_or_paygate_url . '" method="post" target="_top">' . implode('', $novalnet_args_array) . '
                    <input type="submit" class="button-alt" id="submit_novalnet_payment_form" value="' . __('Pay via Novalnet', $this->get_textdomain()) . '" /> <a class="button cancel" href="' . esc_url($order->get_cancel_order_url()) . '">' . __('Cancel order &amp; restore cart', $this->get_textdomain()) . '</a>
                </form>';
                }   // End get_novalnet_form_html()

                /**
                 * Validate curl extension
                 */
                public function check_curl_installed_or_not() {

                    global $woocommerce;

                    if (!function_exists('curl_init') && !in_array($this->novalnet_payment_method, $this->redirect_payments)) {
                        $this->display_error_messages(__('You need to activate the CURL function on your server, please check with your hosting provider.', $this->get_textdomain()), 'error');
                        wp_safe_redirect($woocommerce->cart->get_checkout_url());
                        exit();
                    }
                }   // End check_curl_installed_or_not()

                /**
                 * Validate shop address field parameter
                 */
                public function check_shop_parameter($order) {

                    global $woocommerce;

                    if (isset($order)) {
                        $this->shop_nn_email = isset($order->billing_email) ? trim($order->billing_email) : null;
                         list($this->shop_nn_first_name, $this->shop_nn_last_name) = $this->_form_username_param($order);

                        /** Novalnet validation for basic address fields (returns true only if the user has modified default workflow) */
                        if ($this->shop_nn_first_name == null || $this->shop_nn_last_name == null || $this->shop_nn_email == null) {

                           $error = __('Customer name/email fields are not valid', $this->get_textdomain());

                            if ($this->debug == 'yes')
                                $this->log->add('novalnetpayments', 'Validation error for payment ' . $this->novalnet_payment_method . $error);

                            $this->display_error_messages($error, 'error');
							return($this->redirect_and_success_page('success', $woocommerce->cart->get_checkout_url()));
                        }
                    }
                }   // End check_shop_parameter()

                /**
                 * Form username parameter
                 */
                private function _form_username_param($order) {
                    $order->billing_first_name = trim($order->billing_first_name);
                    $order->billing_last_name = trim($order->billing_last_name);

                    /* Get customer first and last name  */
                    if(empty($order->billing_first_name) || empty($order->billing_last_name)) {
                        $full_name = $order->billing_first_name.$order->billing_last_name;
                        return preg_match('/\s/', $full_name) ? explode(" ", $full_name, 2) : array($full_name, $full_name);
                    }
                    return array($order->billing_first_name, $order->billing_last_name);
                } // End username formation parameter()

                /**
                 * Collects novalnet payment parameters
                 */
                public function get_payment_parameters($order) {

					$config_data = array(
						'vendor'   => $this->vendor_id,
						'auth_code'=> $this->auth_code,
						'product'  => $this->product_id,
						'tariff'   => $this->tariff_id,
						'key'      => $this->payment_key,
					);

					update_post_meta($order->id,'_nn_config_values', $config_data);

					$this->generate_common_parameters($order);
                    $this->generate_hash_parameters();
                    $this->generate_config_parameters();
                    $this->get_user_variable_parameters();
                    $this->get_return_url_parameters();
                    $this->generate_additional_parameters($order);

                }   // End get_payment_parameters()
                /**
                 * Get back-end hash parameter
                 */
                public function generate_hash_parameters() {

                    if (in_array($this->novalnet_payment_method, $this->redirect_payments)) {
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

                        $hash = $this->hash(array('auth_code' => $this->auth_code, 'product' => $this->product_id, 'tariff' => $this->tariff_id, 'amount' => $this->amount, 'test_mode' => $this->test_mode, 'uniqid' => $this->unique_id));
                        $this->payment_parameters['hash'] = $hash;
                    }
                }   // End generate_hash_parameters()

                /**
                 * Get back-end variation parameter
                 */
                public function generate_config_parameters() {
                        $this->payment_parameters['vendor'] = $this->vendor_id;
                        $this->payment_parameters['product'] = $this->product_id;
                        $this->payment_parameters['tariff'] = $this->tariff_id;
                        $this->payment_parameters['auth_code'] = $this->auth_code;
                }   // End generate_config_parameters()

                /**
                 * Get user variable parameter
                 */
                public function get_user_variable_parameters() {

                    if (in_array($this->novalnet_payment_method, $this->redirect_payments))
                        $this->payment_parameters['user_variable_0'] = site_url();
                }   // End get_user_variable_parameters()

                /**
                 * Get return url parameter
                 */
                public function get_return_url_parameters() {

					$url = wp_get_referer();
					$qry = parse_url($url);
					if(isset($url_value['pay_for_order']) && !empty($url_value['pay_for_order'])){
						$return_url = $url;
					}
                    else{
						$return_url = get_permalink(get_option('woocommerce_checkout_page_id'));
				    }

                    if (in_array($this->novalnet_payment_method, $this->redirect_payments) || ($this->novalnet_payment_method == 'novalnet_cc' && $this->cc3d_enabled == 'yes') ) {
                        $this->payment_parameters['return_url'] = $return_url;
                        $this->payment_parameters['return_method'] = 'POST';
                        $this->payment_parameters['error_return_url'] = $return_url;
                        $this->payment_parameters['error_return_method'] = 'POST';
                        $this->payment_parameters['implementation'] = 'PHP';
						$this->payment_parameters['input2'] = 'novalnet_payment_method';
						$this->payment_parameters['inputval2'] = $this->novalnet_payment_method;

                    }
                }   // End get_return_url_parameters()

                /**
                 * Get back-end additional parameters
                 */
                public function generate_additional_parameters($order) {

                    if (in_array($this->novalnet_payment_method, $this->invoice_payments)) {
						$this->payment_parameters['invoice_type'] = 'PREPAYMENT';
						if ($this->novalnet_payment_method == 'novalnet_invoice')
							$this->payment_parameters['invoice_type'] = 'INVOICE';
                        $invoice_ref = "BNR-" . $this->product_id . "-" . ltrim($order->get_order_number(), _x( '#', 'hash before order number', 'woocommerce' ));
                        $this->payment_parameters['invoice_ref'] = $invoice_ref;
                    }

                    if ($this->novalnet_payment_method == 'novalnet_invoice' && $this->is_digits($this->payment_duration)) {
                        $this->due_date = date("Y-m-d", mktime(0, 0, 0, date("m"), (date("d") + $this->payment_duration), date("Y")));

                        $this->payment_parameters['due_date'] = $this->due_date;
                    }

                    if ($this->novalnet_payment_method == 'novalnet_paypal') {
                        $this->payment_parameters['api_user'] = $this->api_username;
                        $this->payment_parameters['api_pw'] = $this->api_password;
                        $this->payment_parameters['api_signature'] = $this->api_signature;
                    }

                    if ($this->novalnet_payment_method == 'novalnet_cc') {

                        $this->payment_parameters['cc_holder'] = isset($_SESSION['cc_values']['cc_holder']) ? $_SESSION['cc_values']['cc_holder'] : null;
                        $this->payment_parameters['cc_no'] = '';
                        $this->payment_parameters['cc_exp_month'] = isset($_SESSION['cc_values']['exp_month']) ? $_SESSION['cc_values']['exp_month'] : null;
                        $this->payment_parameters['cc_exp_year'] = isset($_SESSION['cc_values']['exp_year']) ? $_SESSION['cc_values']['exp_year'] : null;
                        $this->payment_parameters['cc_cvc2'] = isset($_SESSION['cc_values']['cvv_cvc']) ? $_SESSION['cc_values']['cvv_cvc'] : null;

                        $this->payment_parameters['unique_id'] = isset($_SESSION['cc_values']['nn_unique']) ? $_SESSION['cc_values']['nn_unique'] : null;
                        $this->payment_parameters['pan_hash'] = isset($_SESSION['cc_values']['nncc_hash']) ? $_SESSION['cc_values']['nncc_hash'] : null;

                        if($this->cc3d_enabled == 'yes')
							$this->payment_parameters['encoded_amount'] = $this->encode($this->amount);
                    }

                    if ($this->novalnet_payment_method == 'novalnet_sepa') {
                        if (isset($this->sepa_due_date)) {
                            $due_date_for_sepa = date("Y-m-d", mktime(0, 0, 0, date("m"), (date("d") + $this->sepa_due_date), date("Y")));
                        } else {
							$due_date_for_sepa = date("Y-m-d", mktime(0, 0, 0, date("m"), (date("d") + 7), date("Y")));
						}

                        $this->payment_parameters['bank_account_holder'] = isset($_SESSION['sepa_values']['sepa_owner']) ? $_SESSION['sepa_values']['sepa_owner'] : null;
                        $this->payment_parameters['sepa_hash'] = isset($_SESSION['sepa_values']['panhash']) ? $_SESSION['sepa_values']['panhash'] : null;
                        $this->payment_parameters['sepa_unique_id'] = isset($_SESSION['sepa_values']['sepa_uniqueid']) ? $_SESSION['sepa_values']['sepa_uniqueid'] : null;
                        $this->payment_parameters['bank_code'] = '';
                        $this->payment_parameters['bank_account'] = '';
                        $this->payment_parameters['bic'] = '';
                        $this->payment_parameters['iban'] = '';
                        $this->payment_parameters['iban_bic_confirmed'] = isset($_SESSION['sepa_values']['sepa_confirm']) ? $_SESSION['sepa_values']['sepa_confirm'] : null;
                        $this->payment_parameters['sepa_due_date'] = $due_date_for_sepa;

                        unset($_SESSION['sepa_values']);

                    }

                }   // End generate_additional_parameters()

                /**
                 * Get common payment parameters (for all payment methods)
                 */
                public function generate_common_parameters($order) {

                    /* Novalnet common payment parameters    */

                    $this->payment_parameters = array (
                    'key'       => $this->payment_key,
                    'test_mode' => (in_array($this->novalnet_payment_method, $this->redirect_payments)) ? $this->encode($this->test_mode) : $this->test_mode,
                    'uniqid'    => (in_array($this->novalnet_payment_method, $this->redirect_payments)) ? $this->encode($this->unique_id) : $this->unique_id,
                    'session'   => session_id(),
                    'currency'  => get_woocommerce_currency(),
                    'first_name'=> $this->shop_nn_first_name,
                    'last_name' => $this->shop_nn_last_name,
                    'gender'    => 'u',
                    'email'     => $this->shop_nn_email,
                    'street'    => $order->billing_address_1,
                    'search_in_street' => 1,
                    'city'      => $order->billing_city,
                    'zip'       => $order->billing_postcode,
                    'lang'      => isset($_GET['lang']) ? strtoupper($_GET['lang']) :strtoupper($this->language),
                    'country'   => $order->billing_country,
                    'country_code'=> $order->billing_country,
                    'tel'       => $order->billing_phone,
                    'remote_ip' => $this->user_ip,
                    'customer_no' => $order->user_id > 0 ? $order->user_id : 'guest',
                    'use_utf8'  => 1,
                    'amount'    => (in_array($this->novalnet_payment_method, $this->redirect_payments)) ? $this->encode($this->amount) : $this->amount,
                    /* Added support for official Woocommerce Sequential Order nubmer(pro) plugin */
                    'order_no'  => ltrim($order->get_order_number(), _x( '#', 'hash before order number', 'woocommerce' )),
                    'input1'    => 'nnshop_nr',
                    'inputval1' => $order->id,

                    /* shop version parameters  */
                    'system_name' => 'WORDPRESS - WOOCOMMERCE',   #here always assign the name of the shop system
                    'system_version' => get_bloginfo('version'). ' - '.WOOCOMMERCE_VERSION . ' - NN'. NOVALNET_GATEWAY_VERSION,   #here add the shop system version together with '-' NN payment module version
                    'system_url'=> site_url(),   #dynamically pass the web url/domain of the server
                    'system_ip' => $_SERVER['SERVER_ADDR']
                    );   #dynamically pass the IP of the system where this module running

                    $ref_id = isset($this->referrer_id) ? trim(strip_tags($this->referrer_id)) : '';
                    $ref1 	= isset($this->reference1)  ? trim(strip_tags($this->reference1))  : '';
                    $ref2 	= isset($this->reference2)  ? trim(strip_tags($this->reference2))  : '';

                    if(!empty($ref_id) && is_numeric($ref_id))
						$this->payment_parameters['referrer_id'] = $ref_id;

                    if(!empty($ref1)){
                        $this->payment_parameters['input3'] = 'reference1';
                        $this->payment_parameters['inputval3'] = $ref1;
                    }

                    if(!empty($ref2)){
                       $this->payment_parameters['input4'] = 'reference2';
                       $this->payment_parameters['inputval4'] = $ref2;
                    }
                }   // End generate_common_parameters()

                /**
                 * process data before payport sever
                 */
                public function prepare_to_novalnet_payport($order) {

                    if (!isset($_SESSION['novalnet_receipt_page_got'])) {

                        echo '<p>' . __('Thank you for choosing Novalnet payment.', $this->get_textdomain()) . '</p>';
                        echo $this->get_novalnet_form_html($order);
                        $_SESSION['novalnet_receipt_page_got'] = 1;
                    }
                }   // End prepare_to_novalnet_payport()

                /**
                 * display error and message
                 */
                public function display_error_messages($message, $message_type = 'error') {

                    global $woocommerce;

                    switch ($message_type) {
                        case 'error':
                            if (is_object($woocommerce->session))
                                $woocommerce->session->errors = $message;
                            else
                                $_SESSION['errors'][] = $message;

                             if (version_compare($woocommerce->version, '2.1.0', '>='))
								wc_add_notice($message, 'error');  #add_error function is deprecated use instead of wc_add_notice
							else
								$woocommerce->add_error($message, 'error');

                            break;
                        case 'message':
                            if (is_object($woocommerce->session))
                                $woocommerce->session->messages = $message;
                            else
                                $_SESSION['messages'][] = $message;
                            if (version_compare($woocommerce->version, '2.1.0', '>='))
								wc_add_notice($message);  #add_error function is deprecated use instead of wc_add_notice
							else
								$woocommerce->add_message($message);

                            break;
                    }
                }   // End display_error_messages()

                /**
                 * Validate credit card form fields
                 */
                public function validate_cc_cc3d_form_elements($cc_params) {
                    $error = '';
                    if ($this->novalnet_payment_method == 'novalnet_cc') {

                        if ($cc_params['cc_holder'] == '' || $this->is_invalid_holder_name($cc_params['cc_holder']) || (($cc_params['exp_month'] == '' || $cc_params['exp_year'] == date('Y')) && $cc_params['exp_month'] < date('m')) || $cc_params['exp_year'] == '' || $cc_params['exp_year'] < date('Y') || !$this->is_digits($cc_params['cvv_cvc']) || empty($cc_params['cvv_cvc']) || $cc_params['nncc_hash'] == '' || $cc_params['nn_unique'] == ''){
                            $error = true;
						}
                        if (!$cc_params['cc_type']){
                            $error = true;
						}
                    }
                    if ($error) {

                        if ($this->debug == 'yes')
                            $this->log->add('novalnetpayments', 'Validation error for payment ' . $this->novalnet_payment_method . $error);

                        $this->display_error_messages(__('Please enter valid credit card details!', $this->get_textdomain()), 'error');
                    }
                    else {
                        $_SESSION['cc_values'] = array(
                            'cc_holder' => $cc_params['cc_holder'],
                            'exp_month' => $cc_params['exp_month'],
                            'exp_year' 	=> $cc_params['exp_year'],
                            'cvv_cvc' 	=> $cc_params['cvv_cvc'],
                            'nn_unique' => $cc_params['nn_unique'],
                            'nncc_hash' => $cc_params['nncc_hash'],
                        );
                        return('');
                    }
                }   // End validate_cc_cc3d_form_elements()

                public function validate_sepa_form_elements($sepa_params_value) {
                    $error = '';
                    if( $sepa_params_value['sepa_confirm'] == 0){
                        $error = __('Please confirm IBAN & BIC', $this->get_textdomain());
                    }
                    elseif ($sepa_params_value['sepa_owner'] == '' || $this->is_invalid_holder_name($sepa_params_value['sepa_owner']) || $sepa_params_value['panhash'] == '' || $sepa_params_value['sepa_id'] == '')
                    {
                        $error = __('Please enter valid account details!', $this->get_textdomain());
                    }

                    if ($error) {
                        if ($this->debug == 'yes')
                            $this->log->add('novalnetpayments', 'Validation error for payment ' . $this->novalnet_payment_method . $error);

                        $this->display_error_messages($error, 'error');
                    } else {
                        $_SESSION['sepa_values'] = array(
                            'sepa_owner'	=> $sepa_params_value['sepa_owner'],
                            'panhash' 		=> $sepa_params_value['panhash'],
                            'sepa_uniqueid' => $sepa_params_value['sepa_id'],
                            'sepa_confirm' 	=> $sepa_params_value['sepa_confirm'],
                        );
                        return('');
                    }
                }

                /**
                 * process novalnet payment methods
                 */
                public function process_payment_from($order_id) {

					$order = new WC_Order($order_id);

                    if ($this->debug == 'yes') {
                        $this->log->add('novalnetpayments', 'Order Details:' . print_r($order, true));
                        $this->log->add('novalnetpayments', 'Novalnet Payment Method:' . $order->payment_method);
                    }

                    if($return = $this->check_shop_parameter($order))
						return $return;

                    if ($this->debug == 'yes') {
                        $this->log->add('novalnetpayments', 'Validatation passed for Order No:' . $order_id);
                    }

                    if ($this->novalnet_payment_method == 'novalnet_tel') {
                        $this->form_params_to_payport_and_paygate($order);
                        $return = $this->validate_amount_variations();
                        if ($return)
                            return($return);

                        if (empty($_SESSION['tel']['novalnet_tel_tid'])) {
                            $return = $this->validate_tel_amount();

                            if ($return)
                                return($return);

                            $aryResponse = $this->send_params_to_paygate($order);
                            return($this->check_tel_payment_status($aryResponse, $order));
                        }
                        else
                            return($this->telephone_secondcall($order_id));
                    }
                    elseif (in_array($this->novalnet_payment_method, $this->redirect_payments) || ($this->novalnet_payment_method == 'novalnet_cc' && $this->cc3d_enabled == 'yes')) {
                        return($this->redirect_and_success_page('success', add_query_arg('order-pay', $order->id, $order->get_checkout_payment_url('order-pay'))));
                    }
                    else {
						$this->form_params_to_payport_and_paygate($order);
						$aryResponse = $this->send_params_to_paygate($order);
						return($this->check_novalnetstatus($aryResponse, $order_id));
					}

				} // End process_payment_from()

				public function validate_fields() {

					if($return = $this->backend_data_validation_from_frontend())
						return $return;

					if ($this->novalnet_payment_method == 'novalnet_cc') {

						$cc_params_values = array (
							'cc_holder' => trim($_REQUEST['cc_holder']),
							'exp_month' => $_REQUEST['cc_exp_month'],
							'exp_year' 	=> $_REQUEST['cc_exp_year'],
							'cvv_cvc' 	=> $_REQUEST['cc_cvv_cvc'],
							'cc_type' 	=> $_REQUEST['cc_type'],
							'nn_unique' => $_REQUEST['nn_unique'],
							'nncc_hash' => $_REQUEST['nncc_hash']
						);
						$return = $this->validate_cc_cc3d_form_elements($cc_params_values);
						if ($return)
							return($return);

					}

					elseif ($this->novalnet_payment_method == 'novalnet_sepa') {
						$sepa_params_value = array(
							'sepa_owner' 	=> trim($_REQUEST['sepa_owner']),
							'panhash' 		=> $_REQUEST['panhash'],
							'sepa_id'		=> $_REQUEST['sepa_uniqueid'],
							'sepa_confirm'	=> isset($_REQUEST['sepa_confirm']) ? $_REQUEST['sepa_confirm'] : null,
						);
						$return = $this->validate_sepa_form_elements($sepa_params_value);
						if ($return)
							return($return);

					}

                    return true;
                } // End validate_fields()

                /**
                 * get url for direct form payment methods
                 */
                public function redirect_and_success_page($result, $redirect_url) {

                    return array(
                        'result' => $result,
                        'redirect' => $redirect_url
                    );
                }   // End redirect_and_success_page()

                /**
                 * Validate back-end data
                 */
                public function backend_data_validation_from_frontend() {

                    global $woocommerce;
                    $error = '';

                    if ( !$this->is_digits($this->vendor_id) || !$this->is_digits($this->product_id)
					|| !$this->is_digits($this->tariff_id) || !$this->auth_code
					|| ( (in_array($this->novalnet_payment_method, $this->redirect_payments)
					|| ($this->novalnet_payment_method == 'novalnet_cc' ) && $this->cc3d_enabled == 'yes')
					&& !trim($this->key_password)) || ($this->novalnet_payment_method == 'novalnet_paypal'
					&& ( !trim($this->api_username) || !trim($this->api_password) || !trim($this->api_signature) ) ) )

                        $error = __('Basic parameter not valid', $this->get_textdomain());

                    if (isset($this->manual_check_limit) && intval($this->manual_check_limit) > 0 ) {

						if (!$this->is_digits($this->manual_check_limit) )
							$error = __('Manual limit amount / Product-ID2 / Tariff-ID2 is not valid!', $this->get_textdomain());

                        if (!$this->is_digits($this->product_id_2) || !$this->is_digits($this->tariff_id_2))
                            $error = __('Manual limit amount / Product-ID2 / Tariff-ID2 is not valid!', $this->get_textdomain());
                    }

                    if(isset($this->sepa_due_date) && (!$this->is_digits($this->sepa_due_date) || $this->sepa_due_date < 7)){
                        $error = __('SEPA due date not valid!', $this->get_textdomain());
                    }

                    if ($error) {

                        if ($this->debug == 'yes')
                            $this->log->add('novalnetpayments', 'Validation error for payment ' . $this->novalnet_payment_method . $error);

                        $this->display_error_messages($error, 'error');
                        return($this->redirect_and_success_page('success', $woocommerce->cart->get_checkout_url()));
                    }
                }   // End backend_data_validation_from_frontend()

                /**
                 * validate novalnet configuration parameter
                 */
                public function shop_backend_validation($request) {
                    /* Get woocommerce Novalnet configuration settings   */
                    $vendor_id = $request['woocommerce_' . $this->novalnet_payment_method . '_merchant_id'];
                    $vendor_id = isset($vendor_id) ? trim($vendor_id) : null;
                    $auth_code = $request['woocommerce_' . $this->novalnet_payment_method . '_auth_code'];
                    $auth_code = isset($auth_code) ? trim($auth_code) : null;
                    $product_id = $request['woocommerce_' . $this->novalnet_payment_method . '_product_id'];
                    $product_id = isset($product_id) ? trim($product_id) : null;
                    $tariff_id = $request['woocommerce_' . $this->novalnet_payment_method . '_tariff_id'];
                    $tariff_id = isset($tariff_id) ? trim($tariff_id) : null;
                    $referrer_id = $request['woocommerce_'. $this->novalnet_payment_method . '_referrer_id'];
                    $referrer_id = isset($referrer_id) ? trim($referrer_id) : null;
                    /*  woocommerce Novalnet configuration validation    */

                    if (!$request['woocommerce_' . $this->novalnet_payment_method . '_title'])
                        return(__('Please enter valid Payment Title', $this->get_textdomain()));

                    if (!$this->is_digits($vendor_id))
                        return(__('Please enter valid Novalnet Merchant ID', $this->get_textdomain()));

                    if (!$auth_code)
                        return(__('Please enter valid Novalnet Merchant Authorisation code', $this->get_textdomain()));

                    if (!$this->is_digits($product_id))
                        return(__('Please enter valid Novalnet Product ID', $this->get_textdomain()));

                    if (!$this->is_digits($tariff_id))
                        return(__('Please enter valid Novalnet Tariff ID', $this->get_textdomain()));
					if ($this->novalnet_payment_method == 'novalnet_invoice') {
						$payment_duration = $request['woocommerce_' . $this->novalnet_payment_method . '_payment_duration'];
						$payment_duration = isset($payment_duration) ? trim($payment_duration) : null;
						if (isset($payment_duration) && !$this->is_digits($payment_duration))
							return(__('Please enter valid Payment period in days', $this->get_textdomain()));
					}                    
					if (in_array($this->novalnet_payment_method, $this->redirect_payments) || $this->novalnet_payment_method == 'novalnet_cc') {
						$key_password = $request['woocommerce_' . $this->novalnet_payment_method . '_key_password'];
						$key_password = isset($key_password) ? trim($key_password) : null;
						if (isset($key_password) && !$key_password)
							return(__('Please enter valid Novalnet Payment access key', $this->get_textdomain()));
					}
                    if ($this->novalnet_payment_method == 'novalnet_paypal') {
						$api_username = $request['woocommerce_' . $this->novalnet_payment_method . '_api_username'];
						$api_username = isset($api_username) ? trim($api_username) : null;
						$api_password = $request['woocommerce_' . $this->novalnet_payment_method . '_api_password'];
						$api_password = isset($api_password) ? trim($api_password) : null;
						$api_signature = $request['woocommerce_' . $this->novalnet_payment_method . '_api_signature'];
						$api_signature = isset($api_signature) ? trim($api_signature) : null;
						if (!$api_username)
							return(__('Please enter valid PayPal API username', $this->get_textdomain()));

						if (!$api_password)
							return(__('Please enter valid PayPal API password', $this->get_textdomain()));

						if (!$api_signature)
							return(__('Please enter valid PayPal API signature', $this->get_textdomain()));
					}

                    if (in_array($this->novalnet_payment_method, $this->front_end_form_available)) {
						$manual_check_limit = $request['woocommerce_' . $this->novalnet_payment_method . '_manual_check_limit'];
						$manual_check_limit = isset($manual_check_limit) ? trim($manual_check_limit) : null;
						$product_id_2 = $request['woocommerce_' . $this->novalnet_payment_method . '_product_id_2'];
						$product_id_2 = isset($product_id_2) ? trim($product_id_2) : null;
						$tariff_id_2 = $request['woocommerce_' . $this->novalnet_payment_method . '_tariff_id_2'];
						$tariff_id_2 = isset($tariff_id_2) ? trim($tariff_id_2) : null;
						if ($manual_check_limit && !$this->is_digits($manual_check_limit))
							return(__('Please enter valid Manual checking amount', $this->get_textdomain()));

						if ($manual_check_limit && $this->is_digits($manual_check_limit) && intval($manual_check_limit) > 0) {

							if (!$product_id_2 || !$this->is_digits($product_id_2))
								return(__('Please enter valid Novalnet Second Product ID', $this->get_textdomain()));

							if (!$tariff_id_2 || !$this->is_digits($tariff_id_2))
								return(__('Please enter valid Novalnet Second Tariff ID', $this->get_textdomain()));
						}

						if ($product_id_2 && !$this->is_digits($product_id_2))
							return(__('Please enter valid Novalnet Second Product ID', $this->get_textdomain()));

						if ($tariff_id_2 && !$this->is_digits($tariff_id_2))
							return(__('Please enter valid Novalnet Second Tariff ID', $this->get_textdomain()));
					}
					if ($this->novalnet_payment_method == 'novalnet_sepa') {
						$sepa_due_date = $request['woocommerce_'. $this->novalnet_payment_method . '_sepa_due_date'];
						$sepa_due_date = isset($sepa_due_date) ? trim($sepa_due_date) : null;
						if( strlen($sepa_due_date) > 0 && ($sepa_due_date < 7 || !preg_match('/^[0-9]+$/',$sepa_due_date )))
							return(__('Please enter valid due date', $this->get_textdomain()));
					}     
					return ('');
                }   // End novalnet_backend_validation_from_backend()

                /**
                 * Validate payment gateway settings
                 */
                public function backend_data_validation_from_backend($request) {

                    if (isset($request['save']) && (isset($request['section']) && $request['section'] == $this->novalnet_payment_method)) {

                        $is_backend_error = $this->shop_backend_validation($request);

                        if ($is_backend_error) {
                            $redirect = get_admin_url() . 'admin.php?' . http_build_query($_GET);
                            $redirect = remove_query_arg('save');
                            $redirect = add_query_arg('wc_error', urlencode(esc_attr($is_backend_error)), $redirect);
                            if (!empty($request['subtab']))
                                $redirect = add_query_arg('subtab', esc_attr(str_replace('#', '', $request['subtab'])), $redirect);
                            wp_safe_redirect($redirect);
							exit();
                        }
                    }
                    elseif (isset($request['save']) && isset($_GET['wc_error'])) {
                        $redirect = get_admin_url() . 'admin.php?' . http_build_query($_GET);
                        $redirect = remove_query_arg('wc_error');
                        $redirect = add_query_arg('save', urlencode(esc_attr('true')), $redirect);
                        wp_safe_redirect($redirect);
                        exit();
					}
                }   // End backend_data_validation_from_backend()

                /**
                 * Initialize language for payment methods
                 */
                public function initialize_novalnet_language() {

                    $language_locale = get_bloginfo('language');

                    $this->language = strtoupper(substr($language_locale, 0, 2)) ? strtoupper(substr($language_locale, 0, 2)) : 'en';
                    $this->language = in_array(strtolower($this->language), $this->language_supported) ? $this->language : 'en';
                }   // End initialize_novalnet_language()

                /**
                 * set-up configuration details  for payment methods
                 */
                public function make_payment_details_array() {
					$this->payment_details = array(
                        /*  Novalnet BankTransfer Payment Method     */
                        'novalnet_banktransfer' => array(
							'payment_key' 			=> $this->payment_key_online_transfer,
							'payport_or_paygate_url'=> $this->novalnet_online_transfer_payport_url,
							'second_call_url' 		=> $this->novalnet_second_call_url,
							'payment_name' 			=> 'Novalnet Instant Bank Transfer',
							'payment_description' 	=> 'You will be redirected to Novalnet AG website when you place the order.',
							'payment_logo' 			=> LOGO_PATH . 'instant_logo.png'
                        ),
                        /*  Novalnet Credit Card Payment Method  */
                        'novalnet_cc' => array(
                            'payment_key' 				=> $this->payment_key_cc,
                            'payport_or_paygate_url' 	=> $this->novalnet_paygate_url,
                            'payport_or_paygate_form_display' => $this->novalnet_cc_form_display_url,
                            'second_call_url' 			=> $this->novalnet_second_call_url,
                            'payment_name' 				=> 'Novalnet Credit Card',
                            'payment_description' 		=> 'The amount will be booked immediately from your credit card when you submit the order.',
                            'payment_logo'	 			=> LOGO_PATH . 'creditcard_logo.png'  ,
                        ),
                        /*  Novalnet iDEAL Payment Method    */
                        'novalnet_ideal' => array(
                            'payment_key' 			=> $this->payment_key_ideal,
                            'payport_or_paygate_url'=> $this->novalnet_online_transfer_payport_url,
                            'second_call_url' 		=> $this->novalnet_second_call_url,
                            'payment_name' 			=> 'Novalnet iDEAL',
                            'payment_description' 	=> 'You will be redirected to Novalnet AG website when you place the order.',
                            'payment_logo' 			=> LOGO_PATH .'ideal_logo.png'
                        ),
                        /*  Novalnet Invoice Payment Method  */
                        'novalnet_invoice' => array(
                            'payment_key' 			=> $this->payment_key_invoice_prepayment,
                            'payport_or_paygate_url'=> $this->novalnet_paygate_url,
                            'second_call_url' 		=> $this->novalnet_second_call_url,
                            'payment_name' 			=> 'Novalnet Invoice',
                            'payment_description' 	=> 'The bank details will be emailed to you soon after the completion of checkout process.',
                            'payment_logo' 			=>  LOGO_PATH .'invoice_logo.png'
                        ),
                        /*  Novalnet PayPal Payment Method   */
                        'novalnet_paypal' => array(
                            'payment_key' 			=> $this->payment_key_paypal,
                            'payport_or_paygate_url'=> $this->novalnet_paypal_payport_url,
                            'second_call_url' 		=> $this->novalnet_second_call_url,
                            'payment_name' 			=> 'Novalnet PayPal',
                            'payment_description' 	=> 'You will be redirected to Novalnet AG website when you place the order.',
                            'payment_logo' 			=> LOGO_PATH .'paypal_logo.png'
                        ),
                        /*  Novalnet Prepayment Payment Method   */
                        'novalnet_prepayment' => array(
                            'payment_key' 			=> $this->payment_key_invoice_prepayment,
                            'payport_or_paygate_url'=> $this->novalnet_paygate_url,
                            'second_call_url' 		=> $this->novalnet_second_call_url,
                            'payment_name' 			=> 'Novalnet Prepayment',
                            'payment_description' 	=> 'The bank details will be emailed to you soon after the completion of checkout process.',
                            'payment_logo' 			=> LOGO_PATH .'prepayment_logo.png'
                        ),
                        /*  Novalnet Telephone Payment Method    */
                        'novalnet_tel' => array(
                            'payment_key' 			=> $this->payment_key_tel,
                            'payport_or_paygate_url'=> $this->novalnet_paygate_url,
                            'second_call_url' 		=> $this->novalnet_second_call_url,
                            'payment_name' 			=>'Novalnet Telephone Payment',
                            'payment_description' 	=> 'Your amount will be added in your telephone bill when you place the order',
                            'payment_logo' 			=> LOGO_PATH .'telephone_logo.png'
                        ),
                        /*  Novalnet Direct Debit SEPA Payment Method  */
                        'novalnet_sepa' => array(
                            'payment_key' 			=> $this->payment_key_sepa,
                            'payport_or_paygate_url'=> $this->novalnet_paygate_url,
                            'payport_or_paygate_form_display' => $this->novalnet_sepa_form_display_url,
                            'second_call_url' 		=> $this->novalnet_second_call_url,
                            'payment_name' 			=> 'Novalnet Direct Debit SEPA',
                            'payment_description' 	=> 'Your account will be debited upon delivery of goods.',
                            'payment_logo' 			=> LOGO_PATH . 'sepa_logo.png'
                        ),
                    );
                }   // End make_payment_details_array()

                /**
                 * Assign variables to payment parameters
                 */
                public function assign_config_members() {

                    // trim settings array
                    array_map("trim",$this->settings);

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
                    if (in_array($this->novalnet_payment_method, $this->redirect_payments) || $this->novalnet_payment_method == 'novalnet_cc') {
						if (isset($this->settings['key_password']) && $this->settings['key_password'])
							$this->key_password = $this->settings['key_password'];
					}

					if ($this->novalnet_payment_method == 'novalnet_cc') {
						if (isset($this->settings['cc3d_enabled']) && $this->settings['cc3d_enabled'])
							$this->cc3d_enabled = $this->settings['cc3d_enabled'];

						if (isset($this->settings['amex_enabled']) && $this->settings['amex_enabled'])
							$this->amex_enabled = $this->settings['amex_enabled'];
					}

					if (in_array($this->novalnet_payment_method, $this->front_end_form_available)) {
						if (isset($this->settings['auto_fill_fields']) && $this->settings['auto_fill_fields'])
							$this->auto_fill_fields = $this->settings['auto_fill_fields'];

                        if (isset($this->settings['manual_check_limit']) && $this->settings['manual_check_limit'])
							$this->manual_check_limit = $this->settings['manual_check_limit'];

						if (isset($this->settings['product_id_2']) && $this->settings['product_id_2'])
							$this->product_id_2 = $this->settings['product_id_2'];

						if (isset($this->settings['tariff_id_2']) && $this->settings['tariff_id_2'])
							$this->tariff_id_2 = $this->settings['tariff_id_2'];
					}

                    if (isset($this->settings['payment_duration']))
                        $this->payment_duration = $this->settings['payment_duration'];

					if ($this->novalnet_payment_method == 'novalnet_paypal') {
						if (isset($this->settings['api_username']) && $this->settings['api_username'])
							$this->api_username = $this->settings['api_username'];

						if (isset($this->settings['api_password']) && $this->settings['api_password'])
							$this->api_password = $this->settings['api_password'];

						if (isset($this->settings['api_signature']) && $this->settings['api_signature'])
							$this->api_signature = $this->settings['api_signature'];
					}

                    if (isset($this->settings['sepa_due_date']) && $this->settings['sepa_due_date'])
                        $this->sepa_due_date = $this->settings['sepa_due_date'];

                    if(isset($this->settings['referrer_id']) && $this->settings['referrer_id'])
                        $this->referrer_id = strip_tags($this->settings['referrer_id']);

                    if(isset($this->settings['reference1']) && $this->settings['reference1'])
                        $this->reference1 = strip_tags($this->settings['reference1']);

                    if(isset($this->settings['reference2']) && $this->settings['reference2'])
                        $this->reference2 = strip_tags($this->settings['reference2']);

                    $this->unique_id = uniqid();
                    $this->method_title = __($this->payment_details[$this->novalnet_payment_method]['payment_name'], $this->get_textdomain());

                    // Define user set variables.
                    $this->title = __($this->settings['title'], $this->get_textdomain());
                    $this->description = $this->settings['description'];
                    $this->instructions = $this->settings['instructions'];
                    $this->email_notes = $this->settings['email_notes'];
                    $this->debug = $this->settings['debug'];
                    $this->payment_logo = $this->settings['payment_logo'];
                    $this->set_order_status = $this->settings['set_order_status'];
                    if(isset($this->settings['onhold_txn_complete_status']) && $this->settings['onhold_txn_complete_status'])
						$this->onhold_txn_complete_status = $this->settings['onhold_txn_complete_status'];

					if(isset($this->settings['cancel_order_status']) && $this->settings['cancel_order_status'])
						$this->cancel_order_status = $this->settings['cancel_order_status'];

					if(isset($this->settings['callback_order_status']) && $this->settings['callback_order_status'])
						$this->callback_order_status = $this->settings['callback_order_status'];

                    $this->icon = $this->payment_details[$this->novalnet_payment_method]['payment_logo'];
                }   // End assign_config_members()

                /**
                 * Validate account digits
                 */
                public function is_digits($element) {
                    return(preg_match("/^[0-9]+$/", $element));
                }   // End is_digits()

                /**
                 * Validate account holder name
                 */
                public function is_invalid_holder_name($element) {
                    return preg_match("/[#%\^<>@$=*!]/", $element);
                }   // End is_invalid_holder_name()

                /**
                 * Format amount in cents
                 */
                public function currency_format($amount) {
                    $this->amount = str_replace(',', '', number_format($amount, 2)) * 100;
                }   // End currency_format()

                /**
                 * Assign Manual Check-Limit
                 */
                public function check_and_assign_manual_check_limit() {
                    if (isset($this->manual_check_limit) && $this->manual_check_limit > 0 && $this->amount >= $this->manual_check_limit) {
                        if ($this->product_id_2 && $this->tariff_id_2) {
                            $this->product_id = $this->product_id_2;
                            $this->tariff_id = $this->tariff_id_2;
                        }
                    }
                }   // End check_and_assign_manual_check_limit()

                /**
                 * Get Server Response message
                 */
                public function get_response_text($request) {
                    return(isset($request['status_text']) ? $request['status_text'] : (isset($request['status_desc']) ? $request['status_desc'] : __('Successful', $this->get_textdomain())));
                }   // End get_response_text()

                /**
                 * Successful payment
                 */
                public function do_novalnet_success($request, $message) {
                    global $woocommerce;

                    // trim request array
                    array_map("trim",$request);
                    $order_no = $request['inputval1'];
                    $woo_seq_nr = $request['order_no'];

                    if (in_array($this->novalnet_payment_method, $this->redirect_payments))
                        $request['test_mode'] = $this->decode($request['test_mode']);

                    if ( 'novalnet_cc' == $this->novalnet_payment_method && $this->cc3d_enabled == 'yes' ) {
                        $this->amount = $request['amount'];
                        $this->check_and_assign_manual_check_limit();
                    }

                    $order = new WC_Order($order_no);

                    /* add Novalnet Transaction details to order notes */
                    $new_line = "\n";
                    $novalnet_comments = $new_line . __($this->title, $this->get_textdomain()) . $new_line;
                    $novalnet_comments .= __('Novalnet Transaction ID', $this->get_textdomain()) . ': ' . $request['tid'] . $new_line;
                    $novalnet_comments .= ((isset($request['test_mode']) && $request['test_mode'] == 1) || (isset($this->test_mode) && $this->test_mode == 1)) ? __('Test order', $this->get_textdomain()) : '';

                    /*  add additional information for Prepayment and Invoice order  */
                    if (in_array($this->novalnet_payment_method, $this->invoice_payments)) {

                        $novalnet_comments .= $request['test_mode'] ? $new_line . $new_line : $new_line;
                        $novalnet_comments .= __('Please transfer the amount to the following information to our payment service Novalnet AG', $this->get_textdomain()) . $new_line;
                        if ($this->novalnet_payment_method == 'novalnet_invoice' && $this->is_digits($this->payment_duration))
                            $novalnet_comments.= __('Due date', $this->get_textdomain()) . " : " . date_i18n(get_option('date_format'), strtotime($this->due_date)) . $new_line;
                        $novalnet_comments.= __('Account holder : Novalnet AG', $this->get_textdomain()) . $new_line;
                        $novalnet_comments.= 'IBAN : ' . $request['invoice_iban'] . $new_line;
                        $novalnet_comments.= 'BIC : ' . $request['invoice_bic'] . $new_line;
                        $novalnet_comments.= 'Bank : '. $request['invoice_bankname'] . " " . trim($request['invoice_bankplace']) . $new_line;
                        $novalnet_comments.= __('Amount', $this->get_textdomain()) . " : " . strip_tags($order->get_formatted_order_total()) . $new_line;
                        $novalnet_comments.= __('Reference : TID', $this->get_textdomain()) . " " . $request['tid'];
                    }

                    // adds order note
                    if ($order->customer_note)
                        $order->customer_note .= $new_line;

                    if (in_array($this->novalnet_payment_method, $this->invoice_payments)) {

                        $order->customer_note .= html_entity_decode($novalnet_comments, ENT_QUOTES, 'UTF-8');

                        if (version_compare($woocommerce->version, '2.0.0', '<'))
                            $order->customer_note .= utf8_encode($novalnet_comments);
                    }
                    else
                        $order->customer_note .= html_entity_decode($novalnet_comments, ENT_QUOTES, 'UTF-8');

                    $order->add_order_note($novalnet_comments);

                    if ($this->debug == 'yes')
                        $this->log->add('novalnetpayments', 'Novalnet Transaction Information for Order ' . $woo_seq_nr . ' ' . $order->customer_note);

                    /** Update Novalnet Transaction details into shop database   */
                    $nn_order_notes = array(
                        'ID' => $order_no,
                        'post_excerpt' => $order->customer_note
                    );
                    wp_update_post($nn_order_notes);

                    /**  basic validation for post_back and transaction status call */
                    $return = $this->nn_api_validate($this->vendor_id, $this->auth_code, $this->product_id, $this->tariff_id, $this->payment_key, $request['tid'], $woo_seq_nr);

                    if ($return == true) {

                    // send acknoweldgement call to Novalnet server
                    $this->post_back_param($request, $woo_seq_nr);

                    // receieves the status code for transactions
                    $api_stat_code =$this->transaction_status($request);
                    }
                    else
                    $api_stat_code = 0;
                    update_post_meta($order_no,'_nn_status_code',$api_stat_code);
                    update_post_meta($order_no,'_nn_total_amount', $order->order_total);
                    update_post_meta($order_no,'_nn_order_tid', $request['tid']);
                    update_post_meta($order_no,'_nn_order_code',0);
                    $order->update_status($this->set_order_status);

					if(in_array($this->novalnet_payment_method, $this->invoice_payments) || $api_stat_code != NOVALNET_COMPLETE_STATUS){
						update_post_meta($order_no, '_nn_capture_code', 0);
						update_post_meta($order_no, '_nn_callback_amount', 0);
					}else{
						update_post_meta($order_no, '_nn_capture_code', 1);
						update_post_meta($order_no, '_nn_callback_amount',$order->order_total*100);
					}

                    // Reduce stock levels
                    $order->reduce_order_stock();

                    // Remove cart
                    $woocommerce->cart->empty_cart();

                    // successful message display
                    $this->display_error_messages($message, 'message');

                    // Clears the Novalnet Telephone payment session
                    $this->unset_telephone_session();
                    if(isset($_SESSION['sepa']))  unset($_SESSION['sepa']);
                    if(isset($_SESSION['cc']))    unset($_SESSION['cc']);

                    if ($this->debug == 'yes') {
                        $this->log->add('novalnetpayments', 'Transaction Complete for Order ' . $order_no);
                        $this->log->add('novalnetpayments', 'Transaction Status for Order ' . $order_no . ' is ' . $order->status);
                        if ($order_no != $woo_seq_nr)
                            $this->log->add('novalnetpayments', 'Sequential Order number for current Order ' . $woo_seq_nr);
                    }

                    // Empty awaiting payment session
                    if (!empty($_SESSION['order_awaiting_payment']))
                        unset($_SESSION['order_awaiting_payment']);

					$thankyou_page = add_query_arg('order-received', $order_no, get_permalink(woocommerce_get_page_id('thanks')));

                     if (version_compare($woocommerce->version, '2.1.0', '<'))
                        $thankyou_page = add_query_arg('order', $order_no, get_permalink(woocommerce_get_page_id('thanks')));

                    //  Return thankyou redirect
                    if (in_array($this->novalnet_payment_method, $this->redirect_payments) ) {
							wp_safe_redirect(add_query_arg('key', $order->order_key, $thankyou_page));
							exit();
                    }
                    else {
						if($this->novalnet_payment_method == 'novalnet_cc' && $this->cc3d_enabled == 'yes'){
                            wp_safe_redirect(add_query_arg('key', $order->order_key, $thankyou_page));
                            exit();
                        }

                        return($this->redirect_and_success_page('success', add_query_arg('order-received', $order->id, $this->get_return_url( $order ))));
					}
                }   // End do_novalnet_success()

                /**
                 * Pay account status
                 */
				public function novalnet_pay_status(){
                    global $wpdb;

                    $key = isset($_GET['key']) ? $_GET['key'] : '';
                    $post_id = $wpdb->get_var("SELECT post_id FROM $wpdb->postmeta WHERE meta_key='_order_key' && meta_value='$key'");

                    $get_order_code = $wpdb->get_var("SELECT meta_value FROM $wpdb->postmeta WHERE meta_key = '_nn_order_code' && post_id=$post_id");

                    if(isset($_GET['pay_for_order']) && !empty($_GET['pay_for_order'])){
                        if( is_numeric($get_order_code)){
                            ?>
                            <style>
                                .woocommerce{
                                    display : none;
                            }
                            </style>
                            <?php
                            echo '<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.1/jquery.min.js"></script>';
                            echo '<script type="text/javascript">
                                   $( document ).ready(function() {
                                   $(".entry-content").html("<p style=font-size:17px><b>'.__('Novalnet Transaction for the Order has been executed / cancelled already.',$this->get_textdomain()).'</p></b>");
								  });
                                  </script>';
                        }
                    }
                }



                /**
                 * Order Cancellation
                 */
                 public function do_novalnet_cancel($request, $message, $nn_order_no) {
                    global $woocommerce;

                    $request = array_map("trim",$request);
					$url = site_url() .'/?'.$_SERVER['QUERY_STRING'];
					parse_str($url, $url_value);

					if(isset($url_value['pay_for_order']) && !empty($url_value['pay_for_order'])){
						$order_no = $url_value['order-pay'];
					}else{
						$order_no = (in_array($this->novalnet_payment_method, $this->redirect_payments) || ($this->novalnet_payment_method == 'novalnet_cc' && $this->cc3d_enabled  == 'yes'))?  $request['inputval1'] : $nn_order_no;
					}
					$order = new WC_Order($order_no);

                    $new_line = "\n";
                    $novalnet_comments = $new_line . __($this->title,$this->get_textdomain()) . $new_line;
                    $novalnet_comments .= $message . $new_line;
                    $novalnet_comments .= __('Novalnet Transaction ID', $this->get_textdomain()) . ': ' .$request['tid'] . $new_line;
                    $novalnet_comments .= ((isset($request['test_mode']) && $request['test_mode'] == 1) || (isset($this->test_mode) && $this->test_mode == 1)) ? __('Test order', $this->get_textdomain()) : '';

					 if ($order->customer_note)
						$order->customer_note .= $new_line;

                    $order->customer_note .= html_entity_decode($novalnet_comments, ENT_QUOTES, 'UTF-8');

                    /** Update order cancellation details into database  */
                    $nn_order_notes = array(
                        'ID' => $order_no,
                        'post_excerpt' => $order->customer_note
                    );
                    wp_update_post($nn_order_notes);

                       // adds order note
                    $order->add_order_note($order->customer_note);

					 //  Cancel the order
                    $order->cancel_order($message);

                    // Order cancellation message display
                    do_action('woocommerce_cancelled_order', $order_no);

                    $order->update_status($this->cancel_order_status);

                    // Order cancellation message display
                    $this->display_error_messages((html_entity_decode($message, ENT_QUOTES, 'UTF-8')), 'error');

                    // update novalnet status code into  database
                    update_post_meta($order_no,'_nn_status_code',$request['status']);
                    update_post_meta($order_no,'_nn_cancel_code',1);
                    update_post_meta($order_no,'_nn_order_code',0);

                    // clears telephone payment session
                    $this->unset_telephone_session();

                    if ($this->debug == 'yes') {
                        $this->log->add('novalnetpayments', 'Transaction Cancelled for Order ' . $order_no . ' status message ' . $message);
                    }

                    if (in_array($this->novalnet_payment_method, $this->redirect_payments)) {

                        if(isset($url_value['pay_for_order']) && !empty($url_value['pay_for_order'])){
							wp_safe_redirect($url);
                            exit();
						}
						else{
							wp_safe_redirect($woocommerce->cart->get_checkout_url());
							exit();
						}
                    }
                    else{
						if($this->novalnet_payment_method == 'novalnet_cc' && $this->cc3d_enabled == 'yes'){
							if(isset($url_value['pay_for_order']) && !empty($url_value['pay_for_order'])){
								wp_safe_redirect($url);
								exit();
							}
							else{
                               wp_safe_redirect($woocommerce->cart->get_checkout_url());
								exit();
							}
                        }
                        else{

							if(isset($url_value['pay_for_order']) && !empty($url_value['pay_for_order'])){
								return($this->redirect_and_success_page('success', $url));
							}else{
								return($this->redirect_and_success_page('success', $woocommerce->cart->get_checkout_url()));
							}
						}
					}

                }   // End do_novalnet_cancel()

                /**
                 * Transfer data via curl library (consists of various protocols)
                 */
                public function perform_https_request($url, $form) {

                    if ($this->debug == 'yes')
                        print "<BR>perform_https_request: $url<BR>\n\r\n";
                    if ($this->debug == 'yes')
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
                    if ($this->debug == 'yes') {
                        print_r(curl_getinfo($ch));
                        echo "<BR><BR>\n\n\nperform_https_request: cURL error number:" . $errno . "<BR>\n";
                        echo "\n\n\nperform_https_request: cURL error:" . $errmsg . "<BR>\n";
                    }

                    #close connection
                    curl_close($ch);

                    if ($this->debug == 'yes')
                        print "<BR>\n" . $data;

                    ## read and return data from novalnet paygate
                    return array($errno, $errmsg, $data);
                }   // End perform_https_request()

                /**
                 * Generate Hash parameter value ($h contains encoded data)
                 */
                public function hash($h) {

                    if (!$h)
                        return'Error: no data';
                    if (!function_exists('md5')) {
                        return'Error: func n/a';
                    }
                    return md5($h['auth_code'] . $h['product'] . $h['tariff'] . $h['amount'] . $h['test_mode'] . $h['uniqid'] . strrev($this->key_password));
                }   // End hash()

                /**
                 * Validate Hash parameter
                 */
                public function checkHash(&$request) {

                    if (!$request)
                        return false;#'Error: no data

                    if ($request['hash2'] != $this->hash($request))
                        return false;

                    return true;
                }   // End checkHash()

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
                }   // End encode()

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
                }   // End decode()

                /**
                 * Validate current user's IP address
                 */
                public function isPublicIP($value) {

                    if (!$value || count(explode('.', $value)) != 4)
                        return false;
                    return !preg_match('~^((0|10|172\.16|192\.168|169\.254|255|127\.0)\.)~', $value);
                }   // End isPublicIP()

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
                }   // End getRealIpAddr()

                /**
                 * Process Novalnet server response
                 */
                public function check_novalnetstatus($request, $nn_order_no) {
                    if (isset($request['status']) && $request['status'] == NOVALNET_COMPLETE_STATUS || (isset($request['inputval2']) && $request['inputval2'] == 'novalnet_paypal' && $request['status'] == 90 ))
                        return($this->do_novalnet_success($request, $this->get_response_text($request)));
                    else
                        return($this->do_novalnet_cancel($request, $this->get_response_text($request), $nn_order_no));
                }   // End check_novalnetstatus()

		/**
		 * validate novalnet server response
		 */
		public function check_novalnet_payment_status() {

			global $woocommerce;

			if( isset($_POST) && isset($_POST['tid'])
				&& !isset($_SESSION['tmp_request'])  ) {

				$_SESSION['tmp_request'] = 1;

				if (isset($_REQUEST) && isset($_REQUEST['status']) && (in_array(isset($_REQUEST['inputval2']), $this->redirect_payments))) {

					if (isset($_REQUEST['hash'])) {

						if (!$this->checkHash($_REQUEST)) {
							$message = $this->get_response_text($_REQUEST) . ' - ' . __('Check Hash failed.', $this->get_textdomain());
							$this->do_novalnet_cancel($_REQUEST, $message, null);
						}
						else
							$this->check_novalnetstatus($_REQUEST, null);
					}
					else
						$this->check_novalnetstatus($_REQUEST, null);

				}
			} elseif( isset($_POST) && isset($_POST['tid']) ) {
				wp_safe_redirect($woocommerce->cart->get_checkout_url());
                exit();
			}
		}   // End check_novalnet_payment_status()

		/**
		 * Send acknowledgement parameters to Novalnet server after successful transaction
		 */
		public function post_back_param($request, $order_id) {
				$urlparam = array(
					'vendor' 	=> $this->vendor_id,
					'product' 	=> $this->product_id,
					'key' 		=> $this->payment_key,
					'tariff' 	=> $this->tariff_id,
					'auth_code' => $this->auth_code,
					'status' 	=> 100,
					'tid' 		=> $request['tid'],
					'order_no' 	=> $order_id
				);
				if (in_array($this->novalnet_payment_method, $this->invoice_payments))
					$urlparam ['invoice_ref'] = "BNR-" . $this->product_id . "-" . $order_id;

				$urlparam = http_build_query($urlparam);
				$this->perform_https_request($this->novalnet_paygate_url, $urlparam);
				if ($this->debug == 'yes')
					$this->log->add('novalnetpayments', 'Acknowledgement Parameters sent successfully');

		}   // End post_back_param()

        /**
         * performs transaction status request to Novalnet server
         */
        protected function transaction_status($request) {

            $urlparam = '<?xml version="1.0" encoding="UTF-8"?><nnxml><info_request><vendor_id>' . $this->vendor_id . '</vendor_id>';
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

        }   // End transaction_status()

		public function load_plugin_textdomain() {

			load_plugin_textdomain( $this->get_textdomain(), FALSE, dirname( plugin_basename( __FILE__ ) ) . $this->get_textdomain_path() );
		}

		public static function get_textdomain() {
			if( is_null( self::$textdomain ) )
				self::$textdomain = self::get_plugin_data( 'TextDomain' );

			return self::$textdomain;
		}

		public static function get_textdomain_path() {
			return self::get_plugin_data( 'DomainPath' );
		}

		private static function get_plugin_data( $value = 'Version' ) {
			if ( ! function_exists( 'get_plugin_data' ) )
				require_once( ABSPATH . '/wp-admin/includes/plugin.php' );

			$plugin_data  = get_plugin_data (__FILE__);
			$plugin_value = $plugin_data[ $value ];
					return $plugin_value;
		}

		public function get_novalnet_payments_url() {
			$ssl_status = is_ssl() ? 'https://':'http://';
			$this->novalnet_paygate_url = $ssl_status.'payport.novalnet.de/paygate.jsp';
			$this->novalnet_cc_form_display_url = $ssl_status.'payport.novalnet.de/direct_form.jsp';
			$this->novalnet_sepa_form_display_url = $ssl_status.'payport.novalnet.de/direct_form_sepa.jsp';
			$this->novalnet_online_transfer_payport_url = $ssl_status.'payport.novalnet.de/online_transfer_payport';
			$this->novalnet_cc3d_payport_url = $ssl_status.'payport.novalnet.de/global_pci_payport';
			$this->novalnet_paypal_payport_url = $ssl_status.'payport.novalnet.de/paypal_payport';
			$this->novalnet_second_call_url = $ssl_status.'payport.novalnet.de/nn_infoport.xml';
		}

        /**
         * Constructor for Novalnet gateway
         *
         * @access public
         * @return void
         */
        public function __construct() {
			ob_start();

			if (!isset($_SESSION))
                session_start();

            $this->novalnet_payment_method = $this->id = get_class($this);

            if (in_array($this->novalnet_payment_method, $this->front_end_form_available))
                $this->has_fields = true;

            $this->load_plugin_textdomain();
			// Load the Novalnet Payport URLs
			$this->get_novalnet_payments_url();
            $this->initialize_novalnet_language();
            $this->make_payment_details_array();
            // Load the form fields.
            $this->init_form_fields();

            // Load the settings.
            $this->init_settings();

            if (isset($_REQUEST['section']) && $_REQUEST['section']== $this->novalnet_payment_method){
                $this->backend_data_validation_from_backend($_REQUEST);
			}

            $this->assign_config_members();
			if(isset($_REQUEST['pay_for_order']) && $_REQUEST['pay_for_order'])
				$this->novalnet_pay_status();

            // Logs
            if (isset($this->debug) && $this->debug == 'yes')
                $this->log = new WC_Logger() ;

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
            if (isset($_SESSION['api_call_check_action_got']))
                unset($_SESSION['api_call_check_action_got']);

			add_action('init', array(&$this, 'check_novalnet_payment_status'));

            /* Save hook settings    */
            if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>='))
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

        }   // End __construct()

				/**
				 *Novalnet transaction meta box
				 */
				public function novalnet_transaction_meta_boxes() {
					global $post, $woocommerce;

					$this->order = new WC_Order($post->ID);

					if(in_array($this->order->payment_method,$this->novalnet_extensions) || ($this->order->payment_method == 'novalnet_paypal')){
						add_meta_box('novalnet-gateway-transaction-actions', __('Novalnet Payment Status Management', $this->get_textdomain()), array($this, 'novalnet_gateway_transaction_meta_box'), 'shop_order', 'side', 'default');
					}
				}

				public function novalnet_gateway_transaction_meta_box() {
					global $post;
					$this->order = new WC_Order( $post->ID );
					if(version_compare(WC_VERSION,'2.2.0','>=')){
						$status = wc_get_order_status_name($this->order->status);
					}
					else{
						$status = $this->order->status;
					}
					?>
					<p><strong> <?php echo  __('Current Transaction Status: ',$this->get_textdomain()).__($status,'woocommerce'); ?></strong></p>
					<?php
					if (in_array($this->order->payment_method, $this->novalnet_extensions) && get_post_meta($this->order->id,'_nn_status_code', true) < NOVALNET_COMPLETE_STATUS && get_post_meta($this->order->id,'_nn_cancel_code', true) == 0 && get_post_meta($this->order->id,'_nn_capture_code', true) == 0 )
					:?>

					<ul class="order_actions">

					<li>
						<a id="nn_capture"  class="button button-primary tips" data-tip="<?php echo __( 'Capture the current Transaction', $this->get_textdomain() ); ?>" onclick="if (!window.confirm('<?php echo __('Are you sure you want to capture the payment?',$this->get_textdomain()); ?>')){return false;}" href="<?php echo admin_url('post.php?post='.$post->ID.'&action=edit&novalnet_gateway_action=nn_capture'); ?> "><?php echo __('Capture', $this->get_textdomain()); ?></a>

					</li>


					<li>
						<a id="nn_void" class="button tips" data-tip="<?php echo __( 'Cancel the current Transaction', $this->get_textdomain() ); ?>" onclick="if (!window.confirm('<?php echo __('Are you sure you want to cancel the payment?',$this->get_textdomain()); ?>')){return false;}" href="<?php echo admin_url('post.php?post='.$post->ID.'&action=edit&novalnet_gateway_action=nn_void'); ?> "><?php echo __('Void', $this->get_textdomain()); ?></a>

					</li>
					</ul>
					<?php endif ?>

					<?php
					if (((in_array($this->order->payment_method, $this->novalnet_extensions) || $this->order->payment_method == 'novalnet_paypal') && ( get_post_meta($this->order->id,'_nn_capture_code', true) == 1 && get_post_meta($this->order->id,'_nn_cancel_code', true) == 0 )) && $this->order->order_total > 0 )
					:?>
					<ul>

						<li class="wide">
							<label><?php echo __( 'Amount to be refunded:', $this->get_textdomain() ); ?></label>
							<input type="number" step="any" id="nov_refund_amount" class="first" name="nov_refund_amount" placeholder="0.00" value=" " />
						</li>
					</ul>
					<p class="buttons">
					   <a id="nn_refund" class="button button-primary tips" data-tip="<?php echo __( 'Refund the current Transaction', $this->get_textdomain() ); ?>" onclick= "nn_refund_amount('<?php echo admin_url('post.php?post='.$post->ID.'&action=edit&novalnet_gateway_action=nn_refund'); ?>',<?php echo $this->order->order_total ?>)" href="javascript:void(0)"><?php echo __('Refund', $this->get_textdomain()); ?></a>
					</p>

					<script>
						function nn_refund_amount(url, tot_amount) {
							if (tot_amount >= document.getElementById('nov_refund_amount').value && document.getElementById('nov_refund_amount').value > 0 ) {

								if ( !window.confirm('<?php echo __('Are you sure you want to refund the amount?',$this->get_textdomain()); ?>')){return false;}
								window.location.href = url + "&nn_var_ref_amount="+document.getElementById('nov_refund_amount').value;
							}
							else if (!window.confirm('<?php echo __('Please enter the correct refund amount',$this->get_textdomain()); ?>')) {
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
                }   // End thankyou_page()


				/**
				 * Verify novalnet gateway action
				 */
				public function nn_api_call_check() {
					if(!isset($_SESSION['api_call_check_action_got'])) {
					if(is_admin()) {
						if(isset($_GET['novalnet_gateway_action']) AND in_array($_GET['novalnet_gateway_action'], $this->allowed_actions) AND isset($_GET['post'])) {


							$this->order = new WC_Order( intval( $_GET['post'] ) );
							$this->nn_api_action_pass($this->order->id , $_GET['novalnet_gateway_action']);
						}
					}
					$_SESSION['api_call_check_action_got'] =1;
					}

				}   // End nn_api_call_check()

				/**
				 * routes the respective action as per request
				 */
				private function nn_api_action_pass( $order_id, $action ) {
					if( ! isset($this->order))
						$this->order = new WC_Order( $order_id );

					if(in_array($action, $this->allowed_actions)) {
						call_user_func(array($this, $action));
					}
				}   // End nn_api_action_pass()

				/**
				 * perform action while capture is called
				 */
				private function nn_capture() {

					$nn_capt_new_line = "\n";

					// get the payment method
					$nn_capt_payment = $this->order->payment_method;
					$nn_capt_obj = new $nn_capt_payment();
					$nn_capt_param = get_post_meta($this->order->id,'_nn_config_values', true);

					if ($this->order->payment_method == 'novalnet_sepa') {

					   if (get_post_meta($this->order->id,'_nn_ref_tid', true) == 0) {
							$nn_capt_tid = get_post_meta($this->order->id, '_nn_order_tid', true);
						}
						else{
							$nn_capt_tid = get_post_meta($this->order->id,'_nn_ref_tid', true);
						}
					}
					else {
						$nn_capt_tid = get_post_meta($this->order->id, '_nn_order_tid', true);
					}

					$nn_capt_param['edit_status'] = 1;
					$nn_capt_param['status'] = NOVALNET_COMPLETE_STATUS;
					$nn_capt_param['tid'] = $nn_capt_tid;

					/**  basic validation for capture api call  */
					$return = $this->nn_api_validate($nn_capt_param['vendor'], $nn_capt_param['auth_code'], $nn_capt_param['product'], $nn_capt_param['tariff'], $nn_capt_param['key'], $nn_capt_tid, $this->order->id);

					if ($return == true) {
						list($errno, $errmsg, $data) = $this->perform_https_request($this->novalnet_paygate_url, $nn_capt_param);
						parse_str($data, $aryCaptResponse);

						if($aryCaptResponse['status'] == NOVALNET_COMPLETE_STATUS){
							update_post_meta($this->order->id,'_nn_status_code', $aryCaptResponse['status']);

							$nn_capt_message = sprintf(__('Novalnet Capture action successfully takes place on %s',$this->get_textdomain()), date_i18n(get_option('date_format'), strtotime(date('Y-m-d'))) );

							add_post_meta( $this->order->id, '_paid_date', current_time('mysql'), true );
							if(!in_array($nn_capt_payment, $this->invoice_payments)){
								$this->order->update_status($nn_capt_obj->onhold_txn_complete_status);
									update_post_meta($this->order->id,'_nn_capture_code', 1);
									update_post_meta($this->order->id, '_nn_callback_amount',$this->order->order_total*100);
							}

							$this->order->add_order_note($nn_capt_message);

							$nn_capt_order_notes = array(
							'ID' => $this->order->id,
							'post_excerpt' => $this->order->customer_note
							);
							wp_update_post($nn_capt_order_notes);

							$arr_params = 'novalnet_gateway_action';
							$this->display_admin_messages($nn_capt_message,$arr_params);

						}
						 else {
								if($aryCaptResponse['status_desc'] != null)
									$nn_capt_err = sprintf ( __('Novalnet Capture action failed due to %s',$this->get_textdomain()), $aryCaptResponse['status_desc'] );
								else
									$nn_capt_err = __('Novalnet Capture action failed',$this->get_textdomain());
								$this->order->add_order_note($nn_capt_err);
								$arr_params = 'novalnet_gateway_action';
								$this->display_admin_messages($nn_capt_err,$arr_params);
						}
					}
					wp_safe_redirect(admin_url('post.php?post='.$this->order->id.'&action=edit'));
					exit();
				}
				/**
				 * perform action while void (cancel) is called
				 */
				private function nn_void() {

					// get the payment method
					$nn_void_payment = $this->order->payment_method;
					$nn_void_obj = new $nn_void_payment();
					$nn_void_param = get_post_meta($this->order->id,'_nn_config_values', true);

					if ($this->order->payment_method == 'novalnet_sepa') {

					   if (get_post_meta($this->order->id,'_nn_ref_tid', true) == 0) {
							$nn_void_tid = get_post_meta($this->order->id, '_nn_order_tid', true);
						}
						else
							$nn_void_tid = get_post_meta($this->order->id,'_nn_ref_tid', true);
					}
					else {
						$nn_void_tid = get_post_meta($this->order->id, '_nn_order_tid', true);
					}

					$nn_void_param['edit_status'] = 1;

					$nn_void_param['status'] = NOVALNET_VOID_STATUS;
					$nn_void_param['tid'] = $nn_void_tid;
					/**  basic validation for void api call */
					$return = $this->nn_api_validate($nn_void_param['vendor'], $nn_void_param['auth_code'], $nn_void_param['product'], $nn_void_param['tariff'], $nn_void_param['key'], $nn_void_tid, $this->order->id);

					if ($return == true) {

						list($errno, $errmsg, $data) = $this->perform_https_request($this->novalnet_paygate_url, $nn_void_param);
						parse_str($data, $aryVoidResponse);

						if ($aryVoidResponse['status'] == NOVALNET_COMPLETE_STATUS) {

							update_post_meta($this->order->id,'_nn_status_code',$aryVoidResponse['status']);

							$nn_void_message = sprintf(__('Novalnet Void action successfully takes place on %s',$this->get_textdomain()), date_i18n(get_option('date_format'), strtotime(date('Y-m-d'))) );

							// Order cancellation
							$this->order->update_status($nn_void_obj->cancel_order_status);
							do_action('woocommerce_cancelled_order', $this->order->id);

							// update status code
							update_post_meta($this->order->id,'_nn_cancel_code', 1);
							update_post_meta($this->order->id,'_nn_order_code',0);

							$this->order->add_order_note($nn_void_message);
							/** Update Novalnet voidure Transaction details into shop database   */
							$nn_void_order_notes = array(
							'ID' => $this->order->id,
							'post_excerpt' => $this->order->customer_note
							);
							wp_update_post($nn_void_order_notes);

							$arr_params = 'novalnet_gateway_action';
							$this->display_admin_messages($nn_void_message,$arr_params);
						}
						else {
								if($aryVoidResponse['status_desc'] != null)
									$nn_void_err = sprintf ( __('Novalnet Void action failed due to %s',$this->get_textdomain()), $aryVoidResponse['status_desc'] );
								else
									$nn_void_err = __('Novalnet Void action failed',$this->get_textdomain());
								$this->order->add_order_note($nn_void_err);
						}
						$arr_params = 'novalnet_gateway_action';
						$this->display_admin_messages($nn_void_err,$arr_params);
					}
					wp_safe_redirect(admin_url('post.php?post='.$this->order->id.'&action=edit'));
					exit;
				}

				/**
				 * perform action while refund is called
				 */
				private function nn_refund() {

				   $nn_ref_new_line = "\n";

					// get the payment method
					$nn_ref_payment = $this->order->payment_method;
					$nn_ref_obj = new $nn_ref_payment();
					$nn_ref_amount = $_GET['nn_var_ref_amount'];
					$nn_ref_param = get_post_meta($this->order->id,'_nn_config_values', true);
					if ($nn_ref_payment == 'novalnet_sepa') {
						if(get_post_meta($this->order->id,'_nn_ref_tid', true) == 0)
							$nn_ref_tid = get_post_meta($this->order->id, '_nn_order_tid', true);
						else
							$nn_ref_tid = get_post_meta($this->order->id,'_nn_ref_tid', true);
					}
					else {
						$nn_ref_tid = get_post_meta($this->order->id, '_nn_order_tid', true);
					}

					$nn_ref_param['tid'] = $nn_ref_tid;
					$nn_ref_param['refund_request'] = 1;
					$nn_ref_param['refund_param'] = (str_replace(',', '', number_format($nn_ref_amount, 2)) * 100);
					/**  basic validation for refund api call   */
					$return = $this->nn_api_validate($nn_ref_param['vendor'], $nn_ref_param['auth_code'], $nn_ref_param['product'], $nn_ref_param['tariff'], $nn_ref_param['key'], $nn_ref_tid, $this->order->id);

					if ($return == true) {
						list($errno, $errmsg, $data) = $this->perform_https_request($this->novalnet_paygate_url, $nn_ref_param);
						parse_str($data, $aryRefResponse);
						if ($aryRefResponse['status'] == NOVALNET_COMPLETE_STATUS) {

							if(!empty($aryRefResponse['tid'])) {

								$urlparam = '<?xml version="1.0" encoding="UTF-8"?><nnxml><info_request><vendor_id>' .$nn_ref_param['vendor'] . '</vendor_id>';
								$urlparam .= '<vendor_authcode>' .$nn_ref_param['auth_code'] . '</vendor_authcode>';
								$urlparam .= '<request_type>TRANSACTION_STATUS</request_type>';
								$urlparam .= '<product_id>' . $nn_ref_param['product'] . '</product_id>';
								$urlparam .= '<tid>' . $aryRefResponse['tid'] . '</tid>';
								$urlparam .='</info_request></nnxml>';

								list($errno, $errmsg, $data) = $this->perform_https_request($this->second_call_url, $urlparam);

								if (strstr($data, '<status>')) {
									preg_match('/status>?([^<]+)/i', $data, $matches);
									$nn_status_code = $matches[1];
								}else {
									$nn_status_code = 0;
								}

								if ($nn_ref_payment == 'novalnet_sepa') {
									update_post_meta($this->order->id,'_nn_ref_tid',$aryRefResponse['tid']);
								}

								$nn_ref_message = sprintf(__('Novalnet Refund action successfully takes place on %s for refund amount %s and Transaction ID for payment is %s',$this->get_textdomain()), date_i18n(get_option('date_format'), strtotime(date('Y-m-d'))), strip_tags(woocommerce_price($nn_ref_amount)), $aryRefResponse['tid'] );

							}
							else{
								$nn_status_code = $aryRefResponse['status'];

								$nn_ref_message = sprintf(__('Novalnet Refund action successfully takes place on %s for refund amount %s',$this->get_textdomain()),date_i18n(get_option('date_format'), strtotime(date('Y-m-d'))), strip_tags(woocommerce_price($nn_ref_amount)) );

							}

							update_post_meta($this->order->id,'_nn_status_code',$nn_status_code);

							$nn_bal_amount = (float)$this->order->order_total - (float)$nn_ref_amount;  // balance amount


							if ($nn_bal_amount != 0) {
								if($nn_status_code == NOVALNET_COMPLETE_STATUS)
									update_post_meta($this->order->id,'_nn_capture_code',1);
								else
									update_post_meta($this->order->id,'_nn_capture_code',0);
							}else{
								$this->order->update_status('refunded');
							}

							$this->order->add_order_note($nn_ref_message);
							if ($this->order->customer_note)
								$this->order->customer_note .= $nn_ref_new_line;

							$this->order->customer_note .= $nn_ref_new_line.' '.html_entity_decode($nn_ref_message, ENT_QUOTES, 'UTF-8');
							update_post_meta( $this->order->id, '_order_total', round($nn_bal_amount,2));

							/** Update Novalnet refund Transaction details into shop database    */
							$nn_ref_order_notes = array(
							'ID' => $this->order->id,
							'post_excerpt' => $this->order->customer_note
							);
							wp_update_post($nn_ref_order_notes);

							$arr_params = array( 'novalnet_gateway_action', 'nn_var_ref_amount');
							$this->display_admin_messages($nn_ref_message,$arr_params);

						}
						else {
								if($aryRefResponse['status_desc'] != null)
									$nn_ref_err = sprintf ( __('Novalnet Refund action failed due to %s',$this->get_textdomain()), $aryRefResponse['status_desc'] );
								else
									$nn_ref_err = __('Novalnet Refund action failed',$this->get_textdomain());
								$this->order->add_order_note($nn_ref_err);
						}
						$arr_params = array( 'novalnet_gateway_action', 'nn_var_ref_amount');
						$this->display_admin_messages($nn_ref_err,$arr_params);
					}
					wp_safe_redirect(admin_url('post.php?post='.$this->order->id.'&action=edit'));
					exit;
				}

				/**
                 * Display message to admin panel while processing capture, void, refund
                 */
                public function display_admin_messages($nn_message, $remove_params){

                    $redirect = admin_url() .'post.php?'. http_build_query($_GET);
                    $redirect = remove_query_arg($remove_params);
                    $redirect = add_query_arg('nn_message', urlencode(esc_attr($nn_message)), $redirect);
                    wp_redirect($redirect);
                    exit;
                } //End display_admin_messages()

				/**
				 * api call basic Validation
				 */
				public function nn_api_validate ($api_vendor_id, $api_auth_code, $api_product_id, $api_tariff_id, $api_key, $api_tid, $api_id) {

					if ($this->is_digits($api_vendor_id) && $this->is_digits($api_product_id) && isset($api_key) && $api_key != null && $this->is_digits($api_tariff_id) && !empty($api_auth_code) && isset($api_tid) && !empty($api_tid) && $api_id != null && $api_key != null)
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
                    $order->customer_note = wpautop($order->customer_note);
                }   //End novalnet_email_instructions()

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
                        echo wpautop('<h2>' . __('Transaction Information', $this->get_textdomain()) . '</h2>');
                        echo wpautop(wptexturize($order->customer_note));

                        $_SESSION['novalnet_transactional_info_got'] = 1;
                    }
                }   // End novalnet_transactional_info()

                /**
                 * set current
                 */
                public function set_current() {

                    $this->chosen = true;
                }   // End set_current()

                /**
                 * Displays payment method icon
                 */
                public function get_icon() {

                    $icon_html = '';
					$custom_width_height = '';

                    if ($this->payment_logo) {
						
						if($this->novalnet_payment_method == 'novalnet_cc' && $this->amex_enabled == 'yes') {
							$this->icon = LOGO_PATH . 'creditcard_amex_logo.png' ;
						}

                        $icon_html = '<a href="' . (strtolower($this->language) == 'de' ? 'https://www.novalnet.de' : 'http://www.novalnet.com') . '" target="_new"><img src="' . $this->icon . '" alt="' .  __($this->title, $this->get_textdomain()). '" title="'. __($this->title, $this->get_textdomain()).'" '.$custom_width_height.' /></a>';
					}

                    return($icon_html);

                }   // End get_icon()

                /**
                 * Displays Novalnet Logo icon
                 */
                public function get_title() {
                    return __($this->title, $this->get_textdomain());
                }   // End get_title()

                /**
                 * Payment field to display description and additional info in the checkout form
                 */
                public function payment_fields() {
                    global $woocommerce, $wp;

					if( isset($_SESSION['tmp_request']) && $_SESSION['tmp_request'] == 1 ){
						unset($_SESSION['tmp_request']);
					}

                    // payment description
                    if ($this->description){
						echo __($this->description,$this->get_textdomain());
                    }

                    // test order notice
                    if ($this->test_mode == 1) {
                        echo wpautop('<strong><font color="red">' . __('Please Note: This transaction will run on TEST MODE and the amount will not be charged', $this->get_textdomain()) . '</font></strong>');
                    }

					echo '<script type="text/javascript" src="'.site_url().'/wp-content/plugins/woocommerce-novalnet-gateway/assets/js/nn_session_unset.js"></script>';

					$chosen_method =  $woocommerce->session->chosen_payment_method;
					if(WOOCOMMERCE_VERSION >= '2.1.0')
						$chosen_method = WC()->session->chosen_payment_method;

                    if($chosen_method != 'novalnet_cc') {
                        if(isset($_SESSION['cc'])) $_SESSION['cc'] = false;
                    }

                    if($chosen_method != 'novalnet_sepa') {
                        if(isset($_SESSION['sepa'])) $_SESSION['sepa'] = false;
                    }

					if ( isset($this->manual_check_limit) && $this->manual_check_limit > 0) {
						if ( !$this->is_digits($this->manual_check_limit) && ($this->manual_check_limit > 0) ) {
							echo '<strong><font color="red">'.__('Basic parameter not valid!', $this->get_textdomain()).'</font></strong>';
							break;
						}

						if ( empty($this->product_id_2)
							|| ( isset($this->product_id_2) && !$this->is_digits($this->product_id_2) )
							|| empty($this->tariff_id_2)
							|| ( isset($this->tariff_id_2) && !$this->is_digits($this->tariff_id_2)) ) {

							echo '<strong><font color="red">'.__('Basic parameter not valid!', $this->get_textdomain()).'</font></strong>';
							break;
						}
					}

                    // payment form
                    switch ($this->novalnet_payment_method) {

                        case 'novalnet_cc':
                            /* Novalnet Credit Card Payment form */

                            $nncc_hash = isset($_SESSION['cc']['nncc_hash']) ? $_SESSION['cc']['nncc_hash'] : ''  ;
							$fldVdr = (isset($_SESSION['cc']['cc_fldvdr']) && !empty($panhash)) ? $_SESSION['cc']['cc_fldvdr'] : '' ;
							$form_cc_data = array(
								'vendor_id'		=> $this->vendor_id,
								'auth_code'		=> $this->auth_code,
								'abs_path' 		=> ABSPATH,
								'panhash'		=> $nncc_hash,
								'fldVdr'		=> $fldVdr,
							);

							if(!class_exists('render_cc_template'))
								require_once(NOVALNET_DIR.'/templates/render_cc_template.php');
							$cc = new render_cc_template();
							$cc->render_cc_form($form_cc_data);

                            break;

                        case 'novalnet_sepa':
							$customer_details = '';

						if(isset($wp->query_vars['order-pay']) && !empty($wp->query_vars['order-pay'])){
							$order_id =  isset($_GET['order-pay']) ? $_GET['order-pay'] : (isset($wp->query_vars['order-pay']) ? $wp->query_vars['order-pay'] : '') ;
							$customer_details = $this->get_cust_details($order_id);
						} else{
							$partUrl = parse_url(wp_get_referer());
							wp_parse_str($partUrl['query'], $params);
							$order_id = (isset($params['order-pay']) ? $params['order-pay'] : '' );
							$customer_details = $this->get_cust_details($order_id);
						}

						$panhash = isset($_SESSION['sepa']['panhash']) ? $_SESSION['sepa']['panhash'] : '' ;
						$fldVdr = ((isset($_SESSION['sepa']['sepa_fldvdr']) && !empty($panhash)) ? $_SESSION['sepa']['sepa_fldvdr'] : '' );

						$form_sepa_data = array(
					      'vendor_id'	=> $this->vendor_id,
					      'key'			=> $this->payment_key,
					      'abs_path' 	=> ABSPATH,
					      'panhash'		=> $panhash,
					      'fldVdr'		=> $fldVdr,
				        );

						if(!class_exists('render_sepa_template'))
							require_once(NOVALNET_DIR.'/templates/render_sepa_template.php');
						$sepa = new render_sepa_template();
						$sepa->render_sepa_form($form_sepa_data, $customer_details );

                        break;
                    }   #End Switchcase
                }   // End payment_fields()

				// Fetch the customer details for Pay-option
                public function get_cust_details($order_id){
					$customer_details = '';
					if( !empty($order_id) ) {
						$order = new WC_Order($order_id);
						$customer_details = '&first_name='.urlencode($order->billing_first_name).'&last_name='.urlencode($order->billing_last_name).'&email='.urlencode($order->billing_email).'&country='.urlencode($order->billing_country).'&postcode='.urlencode($order->billing_postcode).'&city='.urlencode($order->billing_city).'&address='.urlencode($order->billing_address_1).'&company='.urlencode($order->billing_company);
					}

					return $customer_details;
				}

                /*
                 * Process the payment and return the result
                 */
                public function process_payment($order_id) {

					$order = new WC_order($order_id);
                    // novalnet status code update
                    if(isset($this->auto_fill_fields) &&  $this->auto_fill_fields){

						if($this->novalnet_payment_method == 'novalnet_cc') {
							$_SESSION['cc'] = array(
								'nncc_hash' => $_REQUEST['nncc_hash'],
								'cc_fldvdr' => $_REQUEST['cc_fldvdr']
							);
						}
						else{
							$_SESSION['sepa'] = array(
								'panhash' => $_REQUEST['panhash'],
								'sepa_fldvdr' => $_REQUEST['sepa_fldvdr'],
							);
						}
					}
                    return($this->process_payment_from($order_id));

                }   // End process_payment()

                /**
                 * Receipt_page
                 */
                public function receipt_page($order_id) {
                    $order = new WC_Order($order_id);
                    $this->check_shop_parameter($order);
                    $this->form_params_to_payport_and_paygate($order);

                    $this->prepare_to_novalnet_payport($order);

                }   // End receipt_page()

                /**
                 * Check if this gateway is enabled and available
                 *
                 * @access public
                 * @return bool
                 */
                function is_valid_for_use() {
                    return(true);
                }   // End is_valid_for_use()

                /**
                 * Admin Panel Options
                 */
                public function admin_options() {

					if($this->novalnet_payment_method == 'novalnet_cc' && $this->settings['amex_enabled'] == 'yes') {
						$this->icon = LOGO_PATH . 'creditcard_amex_logo.png' ;
					}

                    ?>
                    <h3><?php  echo __($this->payment_details[$this->novalnet_payment_method]['payment_name'], $this->get_textdomain()) . ' ' . '<a href="' . (strtolower($this->language) == 'de' ? 'https://www.novalnet.de' : 'http://www.novalnet.com') . '" target="_new"><img height="25px" src="' . $this->icon . '" alt="' . __($this->title, $this->get_textdomain()) . '" title="' . __($this->title, $this->get_textdomain()) . '" /></a>'; ?></h3>

                    <p><?php echo __('Configure with Novalnet dealer details.If you need more information<br><br>you can visit our website for end-customers visit on <a href="https://www.novalnet.de/" target="_blank"> https://www.novalnet.de</a> or please contact our Sales Team <a href="mailto:sales@novalnet.de">sales@novalnet.de</a>.', $this->get_textdomain()); ?></p>
                    <table class="form-table">

					<?php
                        // Generate the HTML For the settings form.
                        $this->generate_settings_html();
                        echo '<script type="text/javascript" src="'.site_url().'/wp-content/plugins/woocommerce-novalnet-gateway/assets/js/nn_admin.js"></script>';
                        ?>
                    </table><!--/.form-table-->
                    <?php
                }   // End admin_options()

                public function get_order_status() {
					global $wpdb;
					$available_status = array();
					$sql = "SELECT slug, name FROM $wpdb->terms WHERE term_id in(SELECT term_id FROM $wpdb->term_taxonomy WHERE taxonomy='%s')";
					$row = $wpdb->get_results( $wpdb->prepare( $sql,'shop_order_status') );

					for($i=0;$i < sizeof($row);$i++) {
						$available_status[$row[$i]->slug]=__($row[$i]->name, 'woocommerce');
					}
					return($available_status);
				}

                /**
                 * Initialise Novalnet Gateway Settings Form Fields
                 */
                public function init_form_fields() {

				add_action('admin_footer', 'custom_admin_js');

				if(version_compare(WC_VERSION,'2.2.0','>=')){

					$order_status = wc_get_order_statuses();
				}
				else{

					$order_status = $this->get_order_status();
				}

					// Enable module
                    $this->form_fields['enabled'] = array(
                        'title' => __('Enable module', $this->get_textdomain()),
                        'type' => 'checkbox',
                        'label'   => __('', 'woocommerce' ),
                        'default' => ''
                    );

                    // Payment title field
                    $this->form_fields['title'] = array(
                        'title' => __('Payment Title', $this->get_textdomain()) . '<span style="color:red;">*</span>',
                        'type' => 'text',
                        'description' => '',
                        'default' => $this->payment_details[$this->novalnet_payment_method]['payment_name']
                    );

                    // Payment description field
                    $this->form_fields['description'] = array(
                        'title' => __('Description', $this->get_textdomain()),
                        'type' => 'textarea',
                        'description' => '',
                        'default' => $this->payment_details[$this->novalnet_payment_method]['payment_description']
                    );

                     // Enable test mode
                    $this->form_fields['test_mode'] = array(
                        'title' => __('Enable Test Mode', $this->get_textdomain()),
                        'type' => 'select',
                        'options' => array('0' => __('No', $this->get_textdomain()), '1' => __('Yes', $this->get_textdomain())),
                        'description' => '',
                        'default' => ''
                    );

                    if($this->novalnet_payment_method == 'novalnet_cc'){
                            // Enable CC3D
                        $this->form_fields['cc3d_enabled'] = array(
                            'title' => __('3D Secure (Note: this has to be set up at Novalnet first. Please contact support@novalnet.de, in case you wish this.)', $this->get_textdomain()),
                            'type' => 'checkbox',
                            'label' => __('(Please note that this procedure has a low acceptance among end customers.) As soon as 3D-Secure is activated for credit cards, the bank prompts the end customer for a password, to prevent credit card abuse. This can serve as a proof, that the customer is actually the owner of the credit card. ',$this->get_textdomain()),
                            'default' => '',
                        );
                        // Enable Amex logo
                        $this->form_fields['amex_enabled'] = array(
							'title' => __('Enable Amex logo',$this->get_textdomain()),
							'type' => 'checkbox',
							'default' => '',
							'label' => __(' To display AMEX logo in front end ', $this->get_textdomain()),
							);
                    }

                    if (in_array($this->novalnet_payment_method, $this->front_end_form_available)) {
                        $this->form_fields['auto_fill_fields'] = array(
                                'title' => __('Auto refill the payment data entered in payment page ', $this->get_textdomain()),
                                'type' => 'select',
                                'options' => array(
                                                '0'  => __('No', $this->get_textdomain()),
                                                '1' => __('Yes', $this->get_textdomain())
                                            ),
                                'default' => '0',
                        );
                    }

                     // Enable Payment Logo
                    $this->form_fields['payment_logo'] = array(
                        'title' => __('Enable Payment Logo', $this->get_textdomain()),
                        'type' => 'select',
                        'options' => array('0' => __('No', $this->get_textdomain()), '1' => __('Yes', $this->get_textdomain())),
                        'description' => __('To display Payment logo in front end', $this->get_textdomain()),
                        'default' => '1'
                    );

                    // Novalnet Merchant ID field
                    $this->form_fields['merchant_id'] = array(
                        'title' => __('Novalnet Merchant ID', $this->get_textdomain()) . '<span style="color:red;">*</span>',
                        'type' => 'text',
                        'description' => __('Enter your Novalnet Merchant ID', $this->get_textdomain()),
                        'default' => ''
                    );

                    // Novalnet Authorisation code field
                    $this->form_fields['auth_code'] = array(
                        'title' => __('Novalnet Merchant Authorisation code', $this->get_textdomain()) . '<span style="color:red;">*</span>',
                        'type' => 'text',
                        'description' => __('Enter your Novalnet Merchant Authorisation code', $this->get_textdomain()),
                        'default' => ''
                    );

                    // Novalnet Product ID field
                    $this->form_fields['product_id'] = array(
                        'title' => __('Novalnet Product ID', $this->get_textdomain()) . '<span style="color:red;">*</span>',
                        'type' => 'text',
                        'description' => __('Enter your Novalnet Product ID', $this->get_textdomain()),
                        'default' => ''
                    );

                    // Novalnet Tariff ID field
                    $this->form_fields['tariff_id'] = array(
                        'title' => __('Novalnet Tariff ID', $this->get_textdomain()) . '<span style="color:red;">*</span>',
                        'type' => 'text',
                        'description' => __('Enter your Novalnet Tariff ID', $this->get_textdomain()),
                        'default' => ''
                    );

                    // Novalnet Payment Access Key field
                    if (in_array($this->novalnet_payment_method, $this->redirect_payments) || $this->novalnet_payment_method == 'novalnet_cc') {
                        $this->form_fields['key_password'] = array(
                            'title' => __('Novalnet Payment access key', $this->get_textdomain()) . '<span style="color:red;">*</span>',
                            'type' => 'text',
                            'description' => __('Enter your Novalnet payment access key', $this->get_textdomain()),
                            'default' => ''
                        );
                    }

                    // Manual check limit fields
                    if (in_array($this->novalnet_payment_method, $this->front_end_form_available)) {
                        $this->form_fields['manual_check_limit'] = array(
                            'title' => __('Manual checking of order, above amount in cents (Note: this is a onhold booking, needs your manual verification and activation) ', $this->get_textdomain()),
                            'type' => 'text',
                            'description' => __(' All the orders above this amount will be set on hold by Novalnet and only after your manual verifcation and confirmation at Novalnet the booking will be done', $this->get_textdomain()),
                            'default' => ''
                        );
                        $this->form_fields['product_id_2'] = array(
                            'title' => __('Second Product ID for manual check condition', $this->get_textdomain()),
                            'type' => 'text',
                            'description' => __('Second Product ID in Novalnet to use the manual check condition', $this->get_textdomain()),
                            'default' => ''
                        );
                        $this->form_fields['tariff_id_2'] = array(
                            'title' => __('Second Tariff ID for manual check condition', $this->get_textdomain()),
                            'type' => 'text',
                            'description' => __('Second Tariff ID in Novalnet to use the manual check condition', $this->get_textdomain()),
                            'default' => ''
                        );
                    }

                    // PayPal configuration fields
                    if ($this->novalnet_payment_method == 'novalnet_paypal') {

                        $this->form_fields['api_username'] = array(
                            'title' => __('PayPal API User Name', $this->get_textdomain()) . '<span style="color:red;">*</span>',
                            'type' => 'text',
                            'description' => __('Please enter your PayPal API username', $this->get_textdomain()),
                            'default' => ''
                        );
                        $this->form_fields['api_password'] = array(
                            'title' => __('PayPal API Password', $this->get_textdomain()) . '<span style="color:red;">*</span>',
                            'type' => 'text',
                            'description' => __('Please enter your PayPal API password', $this->get_textdomain()),
                            'default' => ''
                        );
                        $this->form_fields['api_signature'] = array(
                            'title' => __('PayPal API Signature', $this->get_textdomain()) . '<span style="color:red;">*</span>',
                            'type' => 'text',
                            'description' => __('Please enter your PayPal API signature', $this->get_textdomain()),
                            'default' => ''
                        );
                    }

                    // Payment duration field
                    if ($this->novalnet_payment_method == 'novalnet_invoice') {
                        $this->form_fields['payment_duration'] = array(
                            'title' => __('Payment period in days', $this->get_textdomain()),
                            'type' => 'text',
                            'label' => '',
                            'default' => ''
                        );
                    }

                    if($this->novalnet_payment_method == 'novalnet_sepa'){

                        $this->form_fields['sepa_due_date'] = array(
                            'title' => __('SEPA Payment duration in days ', $this->get_textdomain()),
                            'type' => 'text',
                            'label' => '',
                            'default' => '',
                            'description' => __('Enter the Due date in days, it should be greater than 6. If you leave as empty means default value will be considered as 7 days. ', $this->get_textdomain()),
                        );
                    }

                    // Order status after order success in shop
					$this->form_fields['set_order_status'] = array(
						'title' => __('Set order Status', $this->get_textdomain()),
						'type' => 'select',
						'options'=> $order_status,
						'description' => __('Set the status of orders made with this payment module to this value', 'woocommerce-novalnetpayment'),
					);

					if(in_array($this->novalnet_payment_method, $this->novalnet_extensions) && !in_array($this->novalnet_payment_method, $this->invoice_payments)){

						// Order status of OnHold Transaction Completed
						$this->form_fields['onhold_txn_complete_status'] = array(
							'title' => __('OnHold transaction completion status', $this->get_textdomain()),
							'type' => 'select',
							'options'=> $order_status,
							'description' => '',
						);
					}

					// Order status of OnHold Transaction cancelled
					$this->form_fields['cancel_order_status'] = array(
						'title' => (in_array($this->novalnet_payment_method, $this->novalnet_extensions)) ?__('OnHold cancellation / VOID Transaction status', $this->get_textdomain()) : __('Cancel Order status', $this->get_textdomain()),
						'type' => 'select',
						'options'=> $order_status,
						'description' => '',
					);


					if(in_array($this->novalnet_payment_method, $this->invoice_payments)){
						$this->form_fields['callback_order_status'] = array(
							'title' => __('Callback order status', $this->get_textdomain()),
							'type' => 'select',
							'options'=> $order_status,
							'description' => '',
						);
					}

                    $this->form_fields['referrer_id'] = array(
                        'title' => __('Referrer ID', $this->get_textdomain()),
                        'type' => 'text',
                        'default' => '',
                        'description' => __('Referrer ID of the partner at Novalnet, who referred you (only numbers allowed)',$this->get_textdomain())
                    );

                    $this->form_fields['reference1'] = array(
                        'title' => __('Transaction reference 1', $this->get_textdomain()),
                        'type' => 'text',
                        'default' => '',
                        'description' => __('This will appear in the transactions details / account statement',$this->get_textdomain())
                    );

                    $this->form_fields['reference2'] = array(
                        'title' => __('Transaction reference 2', $this->get_textdomain()),
                        'type' => 'text',
                        'default' => '',
                        'description' => __('This will appear in the transactions details / account statement',$this->get_textdomain())
                    );

                    // Proxy server field (required for cURL protocol, if the client set any proxy port in their server)
                    $this->form_fields['payment_proxy'] = array(
                        'title' => __('Proxy-Server', $this->get_textdomain()),
                        'type' => 'text',
                        'description' => __('If you use a Proxy Server, enter the Proxy Server IP with port here (e.g. www.proxy.de:80)', $this->get_textdomain()),
                        'default' => ''
                    );

                     // Enable thank you page instructions
                    $this->form_fields['instructions'] = array(
                        'title' => __('Thank You page Instructions', $this->get_textdomain()),
                        'type' => 'textarea',
                        'description' => __('Instructions that will be added to the thank you page.', $this->get_textdomain()),
                        'default' => ''
                    );

                    // Enable email instructions
                    $this->form_fields['email_notes'] = array(
                        'title' => __('E-mail Instructions', $this->get_textdomain()),
                        'type' => 'textarea',
                        'description' => __('Instructions that will be added to the order confirmation email', $this->get_textdomain())
                    );

                    $logpath = ((WOOCOMMERCE_VERSION > '2.2.0') ? wc_get_log_file_path('novalnetpayments') : "woocommerce/logs/novalnetpayments-".sanitize_file_name( wp_hash( 'novalnetpayments' )));

                    // Enable Debug Log
                    $this->form_fields['debug'] = array(
                        'title' => __('Debug Log', $this->get_textdomain()),
                        'type' => 'checkbox',
                        'label' => __('Enable logging', $this->get_textdomain()),
                        'default' => 'no',
                        'description' => sprintf( __('Log Novalnet Payment events inside <code>%s.txt</code>', $this->get_textdomain()), $logpath)
                    );

                }   // End init_form_fields()
            }   // End class WC_Gateway_Novalnet
        }   #Endif
    }   #Endif
}   // End init_gateway_novalnet()
/* initiate novalnet payment methods     */
if (isset($_REQUEST['inputval2']) && in_array($_REQUEST['inputval2'], $novalnet_payment_methods))
    require_once(dirname(__FILE__) . '/includes/' . $_REQUEST['inputval2'] . '.php');
else {
    foreach ($novalnet_payment_methods as $novalnet_payment_method)
    require_once(dirname(__FILE__) . '/includes/' . $novalnet_payment_method . '.php');

}
ob_get_clean();
?>
