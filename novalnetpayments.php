<?php
/*
 * Plugin Name: Woocommerce Payment Gateway by Novalnet
 * Plugin URI:  http://www.novalnet.com/modul/woocommerce-payment-modul/
 * Description: Adds Novalnet Payment Gateway to Woocommerce e-commerce plugin
 * Version:     1.0.3
 * Author:      Novalnet 
 * Author URI:  https://www.novalnet.de/
 */

/* Plugin installation starts */
register_activation_hook(__FILE__, 'novalnetpayments_activation');
register_deactivation_hook(__FILE__, 'novalnetpayments_deactivation');

if (!function_exists('novalnetpayments_activation')) {

    function novalnetpayments_activation() {

        global $wpdb;
        $payment_description_en_for_cc_cpi_de_pci_at_pci_banktransfer_ideal_paypal = "You will be redirected to Novalnet AG website when you place the order.";
        $payment_description_de_for_cc_cpi_de_pci_at_pci_banktransfer_ideal_paypal = "Sie werden in wenigen Sekunden zur Novalnet AG weitergeleitet.";

        $payment_description_en_for_cc_cc3d = "The amount will be booked immediately from your credit card when you submit the order.";
        $payment_description_de_for_cc_cc3d = "Die Belastung Ihrer Kreditkarte erfolgt mit dem Abschluss der Bestellung.";

        $payment_description_en_for_at_de = "Your account will be debited upon delivery of goods.";
        $payment_description_de_for_at_de = "Die Belastung Ihres Kontos erfolgt mit dem Versand der Ware.";

        $payment_description_en_for_invoice_prepayment = "The bank details will be emailed to you soon after the completion of checkout process.";
        $payment_description_de_for_invoice_prepayment = "Die Bankverbindung wird Ihnen nach Abschluss Ihrer Bestellung per E-Mail zugeschickt.";

        $payment_description_en_for_telephone = "Your amount will be added in your telephone bill when you place the order";
        $payment_description_de_for_telephone = "Ihr Betrag wird zu Ihrer Telefonrechnung hinzugefügt werden, wenn Sie die Bestellung aufgeben";

//actions to perform once on plugin activation go here  
        $query = "select option_name from $wpdb->options where option_name like 'woocommerce_novalnet_%'";
        $row = $wpdb->get_results($query);
        if ($wpdb->num_rows == 0) {
            $query = str_replace('{wp_prefix}', $wpdb->prefix, file_get_contents(dirname(__FILE__) . '/' . 'includes/install.mysql.utf8.sql'));

            $query = str_replace("payment_description_en_for_cc_cpi_de_pci_at_pci_banktransfer_ideal_paypal", $payment_description_en_for_cc_cpi_de_pci_at_pci_banktransfer_ideal_paypal, $query);

            $query = str_replace("payment_description_de_for_cc_cpi_de_pci_at_pci_banktransfer_ideal_paypal", $payment_description_de_for_cc_cpi_de_pci_at_pci_banktransfer_ideal_paypal, $query);

            $query = str_replace("payment_description_en_for_cc_cc3d", $payment_description_en_for_cc_cc3d, $query);

            $query = str_replace("payment_description_de_for_cc_cc3d", $payment_description_de_for_cc_cc3d, $query);

            $query = str_replace("payment_description_en_for_at_de", $payment_description_en_for_at_de, $query);

            $query = str_replace("payment_description_de_for_at_de", $payment_description_de_for_at_de, $query);

            $query = str_replace("payment_description_en_for_invoice_prepayment", $payment_description_en_for_invoice_prepayment, $query);

            $query = str_replace("payment_description_de_for_invoice_prepayment", $payment_description_de_for_invoice_prepayment, $query);

            $query = str_replace("payment_description_en_for_telephone", $payment_description_en_for_telephone, $query);

            $query = str_replace("payment_description_de_for_telephone", $payment_description_de_for_telephone, $query);

            $query = utf8_encode($query);

            $wpdb->query($query);
        }
        // if you're already using .htaccess file please comment it from here [line @66 to line @73]

        $htaccess_path = ABSPATH . 'wp-content/plugins/' . dirname(plugin_basename(__FILE__));
        copy($htaccess_path . '/htaccess.txt', ABSPATH . 'htaccess.txt');
        $sub_folder = substr(home_url(), strpos(home_url(), $_SERVER['HTTP_HOST']) + strlen($_SERVER['HTTP_HOST']), strlen(home_url()));
        $htaccess_content = file_get_contents(ABSPATH . 'htaccess.txt');
        $htaccess_content = str_replace("RewriteRule . /index.php [L]", "RewriteRule . " . $sub_folder . "/index.php [L]", $htaccess_content);
        file_put_contents(ABSPATH . '.htaccess', $htaccess_content);

        // if you're already using .htaccess file please comment upto this line [line @66 to line @73]
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
        global $wpdb;
        $query = "delete from $wpdb->options where option_name like 'woocommerce_novalnet_%'";
        $wpdb->query($query);
        //actions to perform once on plugin uninstall go here
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
        if (!class_exists(novalnetpayments)) {

            class novalnetpayments extends WC_Payment_Gateway {

                var $novalnet_paygate_url = 'https://payport.novalnet.de/paygate.jsp';
                var $novalnet_pci_payport_url = 'https://payport.novalnet.de/pci_payport';
                var $novalnet_online_transfer_payport = 'https://payport.novalnet.de/online_transfer_payport';
                var $payment_key_for_cc_family = 6;
                var $payment_key_for_at_family = 8;
                var $payment_key_for_de_family = 2;
                var $payment_key_for_invoice_prepayment = 27;
                var $front_end_form_available_array = array('novalnet_cc3d', 'novalnet_elv_de', 'novalnet_elv_at');
                var $manual_check_limit_not_available_array = array('novalnet_banktransfer', 'novalnet_ideal', 'novalnet_invoice', 'novalnet_prepayment', 'novalnet_paypal', 'novalnet_tel');
                var $return_url_parameter_for_array = array('novalnet_cc', 'novalnet_banktransfer', 'novalnet_cc_pci', 'novalnet_cc3d', 'novalnet_elv_at_pci', 'novalnet_elv_de_pci', 'novalnet_ideal', 'novalnet_paypal');
                var $encode_applicable_for_array = array('novalnet_cc', 'novalnet_banktransfer', 'novalnet_cc_pci', 'novalnet_elv_at_pci', 'novalnet_elv_de_pci', 'novalnet_ideal', 'novalnet_paypal');
                var $user_variable_parameter_for_arrray = array('novalnet_banktransfer', 'novalnet_paypal', 'novalnet_ideal');
                var $language_supported_array = array('en', 'de');

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
                    $aryResponse['tid'] = $_SESSION['novalnet_tel_tid'];
                    $aryResponse['test_mode'] = $_SESSION['novalnet_tel_test_mode'];
                    $aryResponse['order_no'] = $order_id;
                    // Manual Testing
            //        $aryResponse['status_desc'] = __('Successful', 'woocommerce-novalnetpayment');
            //        $aryResponse['status'] = 100;
                    // Manual Testing
                    return($this->do_check_novalnet_status($aryResponse, $order_id));
                }

                public function do_unset_novalnet_telephone_sessions() {
                    unset($_SESSION['novalnet_tel_tid']);
                    unset($_SESSION['novalnet_tel_test_mode']);
                    unset($_SESSION['novalnet_tel_amount']);
                }

                public function do_check_novalnet_tel_payment_status(&$aryResponse, $order) {
                    global $woocommerce;
                    $new_line = "<br />";
                    if ($aryResponse['status'] == 100 && $aryResponse['tid']) {
                        $aryResponse['status_desc'] = '';
                        if (empty($_SESSION['novalnet_tel_tid']) && !$_SESSION['novalnet_tel_tid'])
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

                public function do_validate_amount() {
                    global $woocommerce;
                    if ($this->amount < 99 || $this->amount > 1000) {
                        $this->do_check_and_add_novalnet_errors_and_messages(__('Amounts below 0,99 Euros and above 10,00 Euros cannot be processed and are not accepted!', 'woocommerce-novalnetpayment'), 'error');
                        return($this->do_return_redirect_page_for_pay_or_thanks_page('success', $woocommerce->cart->get_checkout_url()));
                    }
                }

                public function do_validate_amount_variations() {
                    global $woocommerce;
                    if (isset($_SESSION['novalnet_tel_amount']) && $_SESSION['novalnet_tel_amount'] && $_SESSION['novalnet_tel_amount'] != $this->amount) {
                        $this->do_unset_novalnet_telephone_sessions();
                        $this->do_check_and_add_novalnet_errors_and_messages(__('You have changed the order amount after receiving telephone number, please try again with a new call', 'woocommerce-novalnetpayment'), 'error');
                        return($this->do_return_redirect_page_for_pay_or_thanks_page('success', $woocommerce->cart->get_checkout_url()));
                    }
                    return('');
                }

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

                public function do_necessary_actions_before_prepare_to_novalnet_payport_or_paygate($order) {
                    $this->user_ip = $this->getRealIpAddr();
                    $this->do_check_curl_installed_or_not();
                    $this->do_format_amount($order->order_total);
                    $this->do_check_novalnet_backend_data_validation_from_frontend();
                    $this->do_check_and_assign_manual_check_limit();
                    $this->do_form_payment_parameters($order);
                }

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

                public function do_check_curl_installed_or_not() {
                    global $woocommerce;
                    if (!function_exists('curl_init') && !in_array($this->novalnet_payment_method, $this->return_url_parameter_for_array)) {
                        $this->do_check_and_add_novalnet_errors_and_messages(__('You need to activate the CURL function on your server, please check with your hosting provider.', 'woocommerce-novalnetpayment'), 'error');
                        wp_safe_redirect($woocommerce->cart->get_checkout_url());
                        exit();
                    }
                }

                public function do_form_payment_parameters($order) {
                    $this->get_backend_hash_parameter_array();
                    $this->get_backend_variation_parameter_array();
                    $this->get_user_variable_parameter_array();
                    $this->get_return_url_parameter_array();
                    $this->get_backend_additional_parameter_array($order);
                    $this->get_backend_common_parameter_array($order);
                }

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
                }

                public function get_backend_variation_parameter_array() {
                    if ($this->novalnet_payment_method == 'novalnet_cc' || $this->novalnet_payment_method == 'novalnet_cc_pci' || $this->novalnet_payment_method == 'novalnet_elv_at_pci' || $this->novalnet_payment_method == 'novalnet_elv_de_pci') {
                        $this->payment_parameters['vendor_id'] = $this->vendor_id;
                        $this->payment_parameters['product_id'] = $this->product_id;
                        $this->payment_parameters['tariff_id'] = $this->tariff_id;
                        $this->payment_parameters['vendor_authcode'] = $this->auth_code;
                        $this->payment_parameters['implementation'] = 'PHP_PCI';
                    } else {
                        $this->payment_parameters['vendor'] = $this->vendor_id;
                        $this->payment_parameters['product'] = $this->product_id;
                        $this->payment_parameters['tariff'] = $this->tariff_id;
                        $this->payment_parameters['auth_code'] = $this->auth_code;
                    }
                }

                public function get_user_variable_parameter_array() {
                    if (in_array($this->novalnet_payment_method, $this->user_variable_parameter_for_arrray)) {
                        $this->payment_parameters['user_variable_0'] = home_url();
                    }
                }

                public function get_return_url_parameter_array() {
                    $return_url = get_permalink(get_option('woocommerce_checkout_page_id'));
                    if (in_array($this->novalnet_payment_method, $this->return_url_parameter_for_array)) {
                        $this->payment_parameters['return_url'] = $return_url;
                        $this->payment_parameters['return_method'] = 'POST';
                        $this->payment_parameters['error_return_url'] = $return_url;
                        $this->payment_parameters['error_return_method'] = 'POST';
                        $this->payment_parameters['novalnet_payment_method'] = $this->novalnet_payment_method;
                    }
                }

                public function get_backend_additional_parameter_array($order) {
                    global $woocommerce;
                    if ($this->novalnet_payment_method == 'novalnet_invoice' || $this->novalnet_payment_method == 'novalnet_prepayment') {
                        $this->invoice_type = strtoupper(substr($this->novalnet_payment_method, strpos($this->novalnet_payment_method, '_') + 1, strlen($this->novalnet_payment_method)));
                        $this->invoice_ref = "BNR-" . $this->product_id . "-" . $order->id;
                        $this->payment_parameters['invoice_type'] = $this->invoice_type;
                        $this->payment_parameters['invoice_ref'] = $this->invoice_ref;
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
                        $this->payment_parameters['api_user'] = $this->api_username;
                        $this->payment_parameters['api_pw'] = $this->api_password;
                        $this->payment_parameters['api_signature'] = $this->api_signature;
                    }
                    if ($this->novalnet_payment_method == 'novalnet_elv_de' || $this->novalnet_payment_method == 'novalnet_elv_at') {
                        $this->account_holder = $_SESSION['bank_account_holder'];
                        $this->account_number = $_SESSION['bank_account'];
                        $this->bank_code = $_SESSION['bank_code'];
                        if (isset($_SESSION['acdc']))
                            $this->acdc = $_SESSION['acdc'];
                        $this->payment_parameters['bank_account_holder'] = trim($this->account_holder, '&');
                        $this->payment_parameters['bank_account'] = $this->account_number;
                        $this->payment_parameters['bank_code'] = $this->bank_code;
                        if ($this->novalnet_payment_method == 'novalnet_elv_de')
                            $this->payment_parameters['acdc'] = $this->acdc ? 1 : 0;
                        unset($_SESSION['bank_account_holder']);
                        unset($_SESSION['bank_account']);
                        unset($_SESSION['bank_code']);
                        if (isset($_SESSION['acdc']))
                            unset($_SESSION['acdc']);
                    }
                    if ($this->novalnet_payment_method == 'novalnet_cc3d') {
                        $this->cc_holder = $_SESSION['cc_holder'];
                        $this->cc_no = $_SESSION['cc_number'];
                        $this->cc_exp_month = $_SESSION['exp_month'];
                        $this->cc_exp_year = $_SESSION['exp_year'];
                        $this->cvv_cvc = $_SESSION['cvv_cvc'];
                        $this->payment_parameters['cc_holder'] = trim($this->cc_holder, '&');
                        $this->payment_parameters['cc_no'] = $this->cc_no;
                        $this->payment_parameters['cc_exp_month'] = $this->cc_exp_month;
                        $this->payment_parameters['cc_exp_year'] = $this->cc_exp_year;
                        $this->payment_parameters['cc_cvc2'] = $this->cvv_cvc;
                        unset($_SESSION['cc_holder']);
                        unset($_SESSION['cc_number']);
                        unset($_SESSION['exp_month']);
                        unset($_SESSION['exp_year']);
                        unset($_SESSION['cvv_cvc']);
                    }
                }

                public function get_backend_common_parameter_array($order) {

                    $this->payment_parameters['key'] = $this->payment_key;
                    $this->payment_parameters['test_mode'] = $this->test_mode;
                    $this->payment_parameters['uniqid'] = $this->unique_id;
                    $this->payment_parameters['session'] = session_id();
                    $this->payment_parameters['currency'] = get_woocommerce_currency();
                    $this->payment_parameters['first_name'] = $order->billing_first_name;
                    $this->payment_parameters['last_name'] = $order->billing_last_name;
                    $this->payment_parameters['gender'] = 'u';
                    $this->payment_parameters['email'] = $order->billing_email;
                    $this->payment_parameters['street'] = $order->billing_address_1;
                    $this->payment_parameters['search_in_street'] = 1;
                    $this->payment_parameters['city'] = $order->billing_city;
                    $this->payment_parameters['zip'] = $order->billing_postcode;
                    $this->payment_parameters['lang'] = strtoupper($this->language);
                    $this->payment_parameters['country'] = $order->billing_country;
                    $this->payment_parameters['country_code'] = $order->billing_country;
                    $this->payment_parameters['tel'] = $order->billing_phone;
                    $this->payment_parameters['fax'] = "";
                    //$this->payment_parameters['birthday'] = ;
                    $this->payment_parameters['remote_ip'] = $this->user_ip;
                    $this->payment_parameters['order_no'] = $order->id;
                    $this->payment_parameters['customer_no'] = $order->user_id > 0 ? $order->user_id : __('Guest', 'woocommerce-novalnetpayment');
                    $this->payment_parameters['use_utf8'] = 1;
                    $this->payment_parameters['amount'] = $this->amount;
                }

                public function do_prepare_to_novalnet_payport($order) {
                    if (!@$_SESSION['novalnet_receipt_page_got']) {
                        if ($this->novalnet_payment_method == 'novalnet_cc') {
                            $this->get_novalnet_pci_form_html($order);
                        } else {
                            echo '<p>' . __('You will be redirected to Novalnet AG in a few seconds. <br>', 'woocommerce-novalnetpayment') . '<input type="submit" name="enter" id="enter" onClick="document.getElementById(\'enter\').disabled=\'true\';document.forms.frmnovalnet.submit();" value="' . __('Redirecting...', 'woocommerce-novalnetpayment') . '" /></p>';
                            echo $this->get_novalnet_form_html($order);
                        }
                        $_SESSION['novalnet_receipt_page_got'] = 1;
                    }
                }

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
                }

                public function do_validate_cc_form_elements($cc_holder, $cc_number, $exp_month, $exp_year, $cvv_cvc, $cc_type = null) {
                    global $woocommerce;
                    $cc_number = str_replace(' ', '', $cc_number);
                    $error = false;
                    if ($cc_holder == '' || $this->is_invalid_holder_name($cc_holder) || $cc_number == '' || strlen($cc_number) < 12 || !$this->is_digits($cc_number) || (($exp_month == '' || $exp_year == date('Y')) && $exp_month < date('m')) || $exp_year == '' || $exp_year < date('Y') || $cvv_cvc == '' || strlen($cvv_cvc) < 3 || strlen($cvv_cvc) > 4 || !$this->is_digits($cvv_cvc)) {
                        $error = true;
                    }
                    if ($error) {
                        $this->do_check_and_add_novalnet_errors_and_messages(__('Please enter valid credit card details!', 'woocommerce-novalnetpayment'), 'error');
                        return($this->do_return_redirect_page_for_pay_or_thanks_page('success', $woocommerce->cart->get_checkout_url()));
                    } else {
                        $_SESSION['cc_holder'] = $cc_holder;
                        $_SESSION['cc_number'] = $cc_number;
                        $_SESSION['exp_month'] = $exp_month;
                        $_SESSION['exp_year'] = $exp_year;
                        $_SESSION['cvv_cvc'] = $cvv_cvc;
                        return('');
                    }
                }

                public function do_validate_elv_at_elv_de_form_elements($bank_account_holder, $bank_account, $bank_code, $acdc = '') {
                    global $woocommerce;
                    $bank_account_holder = trim($bank_account_holder, '&');
                    $bank_account = str_replace(' ', '', $bank_account);
                    $bank_code = str_replace(' ', '', $bank_code);
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
                            $return = $this->do_validate_cc_form_elements($_REQUEST['cc3d_holder'], $_REQUEST['cc3d_number'], $_REQUEST['cc3d_exp_month'], $_REQUEST['cc3d_exp_year'], $_REQUEST['cvv_cvc']);
                            if ($return)
                                return($return);
                        }
                        return($this->do_return_redirect_page_for_pay_or_thanks_page('success', $this->do_build_redirect_url($order, 'pay')));
                    }
                    else if ($this->novalnet_payment_method == 'novalnet_elv_at') {
                        $return = $this->do_validate_elv_at_elv_de_form_elements($_REQUEST['bank_account_holder_at'], $_REQUEST['bank_account_at'], $_REQUEST['bank_code_at']);
                        if ($return)
                            return($return);
                    } else if ($this->novalnet_payment_method == 'novalnet_elv_de') {
                        $return = $this->do_validate_elv_at_elv_de_form_elements($_REQUEST['bank_account_holder_de'], $_REQUEST['bank_account_de'], $_REQUEST['bank_code_de'], @$_REQUEST['acdc']);
                        if ($return)
                            return($return);
                    }
                    $this->do_necessary_actions_before_prepare_to_novalnet_payport_or_paygate($order);
                    $aryResponse = $this->do_prepare_to_novalnet_paygate($order);
                    return($this->do_check_novalnet_status($aryResponse, $order_id));
                }

                public function do_return_redirect_page_for_pay_or_thanks_page($result, $redirect_url) {
                    return array(
                        'result' => $result,
                        'redirect' => $redirect_url
                    );
                }

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

                public function do_build_redirect_url($order, $page) {
                    return(add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, get_permalink(woocommerce_get_page_id($page)))));
                }

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
urlpopup="' . ($this->is_secure_url() ? 'https://' : 'http://') . __('www.novalnet.de/img/acdc_info.png', 'woocommerce-novalnetpayment') . '";
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

                public function do_check_novalnet_backend_data_validation_from_backend($request) {
                    if ($request['save'] && ($request['subtab'] == '#gateway-' . $this->novalnet_payment_method || $request['section'] == $this->novalnet_payment_method)) {
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
                    } else if ($request['saved'] && $_GET['wc_error']) {
                        $redirect = get_admin_url() . 'admin.php?' . http_build_query($_GET);
                        $redirect = remove_query_arg('wc_error');
                        $redirect = add_query_arg('saved', urlencode(esc_attr('true')), $redirect);
                        wp_safe_redirect($redirect);
                        exit();
                    }
                }

                public function list_order_statuses() {
                    global $wpdb;
                    $sql = "select name, slug from $wpdb->terms where term_id in (select term_id from $wpdb->term_taxonomy where taxonomy='%s')";
                    $row = $wpdb->get_results($wpdb->prepare($sql, 'shop_order_status'));
                    for ($i = 0, $order_statuses = array(); $i < count($row); $i++) {
                        $order_statuses[$row[$i]->slug] = __($row[$i]->name, 'woocommerce');
                    }
                    return($order_statuses);
                }

                public function do_check_novalnet_order_status() {
                    if (in_array($this->order_status, array('failed')))
                        return(false);
                    return(true);
                }

                public function do_initialize_novalnet_language() {
                    $language_locale = get_bloginfo('language');
                    if ($_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest') {
                        $language_locale = $_SESSION['novalnet_language'];
                    } else {
                        $_SESSION['novalnet_language'] = $language_locale;
                    }
                    $this->language = strtoupper(substr($language_locale, 0, 2)) ? strtoupper(substr($language_locale, 0, 2)) : 'en';
                    $this->language = in_array(strtolower($this->language), $this->language_supported_array) ? $this->language : 'en';
                }

                public function do_trim_array_values(&$array) {
                    if (isset($array) && is_array($array))
                        foreach ($array as $key => $val) {
                            if (!is_array($val))
                                $array[$key] = trim($val);
                        }
                }

                public function do_make_payment_details_array() {
                    $this->payment_details = array(
                        'novalnet_banktransfer' => array(
                            'payment_key' => 33,
                            'payport_or_paygate_url' => $this->novalnet_online_transfer_payport,
                            'second_call_url' => '',
                            'payment_name' => __('Instant Bank Transfer', 'woocommerce-novalnetpayment'),
                            'payment_logo' => __('www.novalnet.de/img/sofort_Logo_en.png', 'woocommerce-novalnetpayment')
                        ),
                        'novalnet_cc' => array(
                            'payment_key' => $this->payment_key_for_cc_family,
                            'payport_or_paygate_url' => $this->novalnet_pci_payport_url, // New CC PCI
                            'second_call_url' => '',
                            'payment_name' => __('Credit Card', 'woocommerce-novalnetpayment'),
                            'payment_logo' => __('www.novalnet.de/img/creditcard_small.jpg', 'woocommerce-novalnetpayment')
                        ),
                        'novalnet_cc_pci' => array(
                            'payment_key' => $this->payment_key_for_cc_family,
                            'payport_or_paygate_url' => $this->novalnet_pci_payport_url,
                            'second_call_url' => '',
                            'payment_name' => __('Credit Card PCI', 'woocommerce-novalnetpayment'),
                            'payment_logo' => __('www.novalnet.de/img/creditcard_small.jpg', 'woocommerce-novalnetpayment')
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
                    if ($this->payment_logo)
                        $this->icon = ($this->is_secure_url() ? 'https://' : 'http://') . $this->payment_details[$this->novalnet_payment_method]['payment_logo'];
                }

                public function is_digits($element) {
                    return(preg_match("/^[0-9]+$/", $element));
                }

                public function is_invalid_holder_name($element) {
                    return preg_match("/[#%\^<>@$=*!]/", $element);
                }

                public function do_format_amount($amount) {
                    $this->amount = str_replace(',', '', number_format($amount, 2)) * 100;
                }

                public function is_secure_url() {
                    return((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') || $_SERVER['SERVER_PORT'] == '443' ? true : false);
                }

                public function do_check_and_assign_manual_check_limit() {
                    if (isset($this->manual_check_limit) && $this->manual_check_limit && $this->amount >= $this->manual_check_limit) {
                        if ($this->product_id_2 && $this->tariff_id_2) {
                            $this->product_id = $this->product_id_2;
                            $this->tariff_id = $this->tariff_id_2;
                        }
                    }
                }

                public function do_get_novalnet_response_text($request) {
                    return($request['status_text'] ? $request['status_text'] : ($request['status_desc'] ? $request['status_desc'] : __('Successful', 'woocommerce-novalnetpayment')));
                }

                public function do_novalnet_success($request, $message) {
                    global $woocommerce, $wp_taxonomies, $wpdb;
                    $this->do_trim_array_values($request);
                    $order_no = $request['order_no'];
                    $GLOBALS['wp_rewrite'] = new WP_Rewrite();
                    if (!isset($woocommerce->cart) || $woocommerce->cart == '')
                        $woocommerce->cart = new WC_Cart();
                    if (!isset($woocommerce->countries) || $woocommerce->countries == '')
                        $woocommerce->countries = new WC_Countries();
                    $wp_taxonomies['shop_order_status'] = '';
                    if (in_array($this->novalnet_payment_method, $this->encode_applicable_for_array))
                        $request['test_mode'] = $this->decode($request['test_mode']);
                    $order = new WC_Order($order_no);
                    $new_line = "\n";
                    $novalnet_comments = $this->title . $new_line;
                    $novalnet_comments .= __('Novalnet Transaction ID', 'woocommerce-novalnetpayment') . ': ' . $request['tid'] . $new_line;
                    $novalnet_comments .= $request['test_mode'] ? __('Test order', 'woocommerce-novalnetpayment') : '';
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
                    if (in_array($this->novalnet_payment_method, $this->return_url_parameter_for_array)) {
                        wp_safe_redirect(add_query_arg('key', $order->order_key, add_query_arg('order', $order_no, get_permalink(woocommerce_get_page_id('thanks')))));
                        exit();
                    } else {
                        $this->do_unset_novalnet_telephone_sessions();
                        return($this->do_return_redirect_page_for_pay_or_thanks_page('success', $this->do_build_redirect_url($order, 'thanks')));
                    }
                }

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
                    if ($this->payment_proxy)
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

                public function hash($h) { #$h contains encoded data
                    if (!$h)
                        return'Error: no data';
                    if (!function_exists('md5')) {
                        return'Error: func n/a';
                    }
                    return md5($h['authcode'] . $h['product_id'] . $h['tariff'] . $h['amount'] . $h['test_mode'] . $h['uniqid'] . strrev($this->key_password));
                }

                public function checkHash(&$request) {
                    if ($this->novalnet_payment_method == 'novalnet_cc' || $this->novalnet_payment_method == 'novalnet_cc_pci' || $this->novalnet_payment_method == 'novalnet_elv_at_pci' || $this->novalnet_payment_method == 'novalnet_elv_de_pci') {
                        $h['authcode'] = $request['vendor_authcode']; #encoded
                        $h['product_id'] = $request['product_id']; #encoded
                        $h['tariff'] = $request['tariff_id']; #encoded
                    } else {
                        $h['authcode'] = $request['auth_code']; #encoded
                        $h['product_id'] = $request['product']; #encoded
                        $h['tariff'] = $request['tariff']; #encoded
                    }
                    $h['amount'] = $request['amount']; #encoded
                    $h['test_mode'] = $request['test_mode']; #encoded
                    $h['uniqid'] = $request['uniqid']; #encoded
                    if (!$request)
                        return false;#'Error: no data';
                    //echo $this->hash($h)."<br>";
                    //echo $request['hash2']."<br>";exit;
                    if ($request['hash2'] != $this->hash($h)) {
                        return false;
                    }
                    return true;
                }

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

                public function isPublicIP($value) {
                    if (!$value || count(explode('.', $value)) != 4)
                        return false;
                    return !preg_match('~^((0|10|172\.16|192\.168|169\.254|255|127\.0)\.)~', $value);
                }

                ### get the real Ip Adress of the User ###

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

                public function do_novalnet_cancel($request, $message, $nn_order_id) {
                    global $woocommerce, $wp_taxonomies;
                    $GLOBALS['wp_rewrite'] = new WP_Rewrite();
                    if (!isset($woocommerce->cart) || $woocommerce->cart == '')
                        $woocommerce->cart = new WC_Cart();
                    $wp_taxonomies['shop_order_status'] = '';
                    $order = new WC_Order($nn_order_id);
                    $order->cancel_order($message);
                    // Message
                    $this->do_check_and_add_novalnet_errors_and_messages($message, 'error');
                    do_action('woocommerce_cancelled_order', $request['order_no']);
                    $this->do_unset_novalnet_telephone_sessions();
                    return($this->do_return_redirect_page_for_pay_or_thanks_page('success', $woocommerce->cart->get_checkout_url()));
                }

                public function do_check_novalnet_status($request, $nn_order_id) {
                    if ($request['status']) {
                        if ($request['status'] == 100) {
                            return($this->do_novalnet_success($request, $this->do_get_novalnet_response_text($request)));
                        } else {
                            return($this->do_novalnet_cancel($request, $this->do_get_novalnet_response_text($request), $nn_order_id));
                        }
                    }
                }

                public function do_nn_cancel($request, $message) {

                    global $woocommerce, $wp_taxonomies;
                    $GLOBALS['wp_rewrite'] = new WP_Rewrite();
                    if (!isset($woocommerce->cart) || $woocommerce->cart == '')
                        $woocommerce->cart = new WC_Cart();
                    $wp_taxonomies['shop_order_status'] = '';
                    $order = new WC_Order($request['order_no']);
                    $order->cancel_order($message);
                    // Message
                    do_action('woocommerce_cancelled_order', $request['order_no']);
                    $this->do_check_and_add_novalnet_errors_and_messages($message, 'error');
                    $this->do_unset_novalnet_telephone_sessions();
                    wp_safe_redirect($woocommerce->cart->get_checkout_url());
                    exit();
                }

                public function do_check_nn_status($request) {
                    if ($request['status']) {
                        if  ($request['novalnet_payment_method']== 'novalnet_paypal' && $request['status'] == 90) {
                             $this->order_status = 'pending';
                            return($this->do_novalnet_success($request, $this->do_get_novalnet_response_text($request)));
					}
                        if ($request['status'] == 100) {
                            return($this->do_novalnet_success($request, $this->do_get_novalnet_response_text($request)));
                        } else {
                            return($this->do_nn_cancel($request, $this->do_get_novalnet_response_text($request)));
                        }
                    }
                }

                public function do_check_novalnet_payment_status() {
                
                    if ($_REQUEST['hash']) {
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

                public function get_novalnet_pci_form_html($order) {
                    global $woocommerce;
                    echo get_header();
                    $img_url = $this->is_secure_url() ? 'https://www.novalnet.de/img/novalnet-loading-icon.gif' : 'http://www.novalnet.de/img/novalnet-loading-icon.gif';
                    $novalnet_form_data .='<form name="frmnovalnet" id="frmnovalnet" method="post" action="' . $this->payport_or_paygate_url . '" target="nn_iframe">';

                    foreach ($this->payment_parameters as $key => $value) {
                        $novalnet_form_data .= '<input type="hidden" name="' . esc_attr($key) . '" id="' . esc_attr($key) . '" value="' . esc_attr($value) . '" />' . "\n";
                    }
                    $novalnet_form_data .='<iframe id="nn_iframe" name ="nn_iframe" width="600" height="450" src="' . $img_url . '"></iframe>';
                    $novalnet_form_data .= ' <br> </form>';
                    print $novalnet_form_data;
                    print ' <script type="text/javascript" language="javascript">
window.onload = function() { document.forms.frmnovalnet.submit(); }
</script>
';
                    echo get_footer();
                    exit();
                }

                public function __construct() {
                    global $woocommerce;
                    @session_start();
                    $this->do_trim_array_values($_REQUEST);
                    // called only after woocommerce has finished loading
                    //add_action( $this->novalnet_payment_method.'_init', array( &$this, $this->novalnet_payment_method.'_loaded' ) );
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
                    if ($this->debug == 'yes')
                        $this->log = $woocommerce->logger();
                    if (!$this->is_valid_for_use())
                        $this->enabled = false;
                    if (!$this->do_check_novalnet_order_status())
                        $this->enabled = false;
                    if (@$_SESSION['novalnet_receipt_page_got'])
                        unset($_SESSION['novalnet_receipt_page_got']);
                    if (@$_SESSION['novalnet_thankyou_page_got'])
                        unset($_SESSION['novalnet_thankyou_page_got']);
                    add_action('init', array(&$this, 'do_check_novalnet_payment_status'));
                    $this->do_check_novalnet_backend_data_validation_from_backend($_REQUEST);
                    add_action('woocommerce_successful_request', array(&$this, 'successful_request'));
                    add_action('woocommerce_thankyou_' . $this->novalnet_payment_method, array(&$this, 'thankyou_page'));
                    add_action('woocommerce_receipt_' . $this->novalnet_payment_method, array(&$this, 'receipt_page'));
                    add_action('woocommerce_update_options_payment_gateways', array(&$this, 'process_admin_options'));
                    add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
                    //$this->do_check_is_any_request_to_print_cc_iframe();
                }

                public function thankyou_page() {
                    
                }

                public function set_current() {
                    $this->chosen = true;
                }

                public function get_icon() {
                    $icon_html = '';
                    if ($this->payment_logo)
                        $icon_html = '<a href="' . (strtolower($this->language) == 'de' ? 'https://' : 'http://') . 'www.' . __('novalnet.com', 'woocommerce-novalnetpayment') . '" alt="' . __('novalnet.com', 'woocommerce-novalnetpayment') . '" target="_new"><img src="' . $this->icon . '" alt="' . $this->method_title . '" /></a>';
                    return($icon_html);
                }

                public function get_title() {
                    $novalnet_logo_html = '';

                    if ($this->novalnet_logo && !strstr($_SERVER['HTTP_REFERER'], 'wp-admin') && (($_REQUEST['action'] == 'woocommerce_update_order_review' && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest') || (!$_REQUEST['woocommerce_pay'] && $_GET['pay_for_order'] && $_GET['order_id'] && $_GET['order'])))
                        $novalnet_logo_html = '<a href="' . (strtolower($this->language) == 'de' ? 'https://' : 'http://') . 'www.' . __('novalnet.com', 'woocommerce-novalnetpayment') . '" alt="' . __('novalnet.com', 'woocommerce-novalnetpayment') . '" target="_new"><img src="' . ($this->is_secure_url() ? 'https://' : 'http://') . __('www.novalnet.de/img/NN_Logo_T.png', 'woocommerce-novalnetpayment') . '" alt="' . __('novalnet.com', 'woocommerce-novalnetpayment') . '" /></a>&nbsp;';
                    return($novalnet_logo_html . $this->title);
                }

                public function payment_fields() {
                    echo $this->description;
                    switch ($this->novalnet_payment_method) {
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
                            for ($iYear = date(Y); $iYear < date(Y) + 6; $iYear++) {
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

                public function process_payment($order_id) {
                    return($this->do_process_payment_from_novalnet_payments($order_id));
                }

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

                public function admin_options() {
                    ?>
                    <h3><?php echo __('Novalnet AG', 'woocommerce-novalnetpayment'); ?></h3>
                    <p><?php echo __('Novalnet AG', 'woocommerce-novalnetpayment'); ?></p>
                    <table class="form-table">
                        <?php
                        if (!$this->do_check_novalnet_order_status()) {
                            ?>
                            <div class="inline error"><p><strong><?php echo __('Gateway Disabled', 'woocommerce-novalnetpayment');
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

                public function init_form_fields() {
                    $order_statuses = $this->list_order_statuses();
                    $this->form_fields['enabled'] = array(
                        'title' => __('Enable module', 'woocommerce-novalnetpayment'),
                        'type' => 'checkbox',
                        'label' => '',
                        'default' => ''
                    );
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
                    }
                    $this->form_fields['test_mode'] = array(
                        'title' => __('Enable Test Mode', 'woocommerce-novalnetpayment'),
                        'type' => 'select',
                        'options' => array('0' => __('No', 'woocommerce-novalnetpayment'), '1' => __('Yes', 'woocommerce-novalnetpayment')),
                        'description' => '',
                        'default' => ''
                    );
                    $this->form_fields['merchant_id'] = array(
                        'title' => __('Novalnet Merchant ID', 'woocommerce-novalnetpayment') . '<span style="color:red;">*</span>',
                        'type' => 'text',
                        'description' => __('Enter your Novalnet Merchant ID', 'woocommerce-novalnetpayment'),
                        'default' => ''
                    );
                    $this->form_fields['auth_code'] = array(
                        'title' => __('Novalnet Merchant Authorisation code', 'woocommerce-novalnetpayment') . '<span style="color:red;">*</span>',
                        'type' => 'text',
                        'description' => __('Enter your Novalnet Merchant Authorisation code', 'woocommerce-novalnetpayment'),
                        'default' => ''
                    );
                    $this->form_fields['product_id'] = array(
                        'title' => __('Novalnet Product ID', 'woocommerce-novalnetpayment') . '<span style="color:red;">*</span>',
                        'type' => 'text',
                        'description' => __('Enter your Novalnet Product ID', 'woocommerce-novalnetpayment'),
                        'default' => ''
                    );
                    $this->form_fields['tariff_id'] = array(
                        'title' => __('Novalnet Tariff ID', 'woocommerce-novalnetpayment') . '<span style="color:red;">*</span>',
                        'type' => 'text',
                        'description' => __('Enter your Novalnet Tariff ID', 'woocommerce-novalnetpayment'),
                        'default' => ''
                    );
                    if (in_array($this->novalnet_payment_method, $this->encode_applicable_for_array)) {
                        $this->form_fields['key_password'] = array(
                            'title' => __('Novalnet Payment access key', 'woocommerce-novalnetpayment') . '<span style="color:red;">*</span>',
                            'type' => 'text',
                            'description' => __('Enter your Novalnet payment access key', 'woocommerce-novalnetpayment'),
                            'default' => ''
                        );
                    }
                    if ($this->novalnet_payment_method == 'novalnet_elv_de') {
                        $this->form_fields['acdc'] = array(
                            'title' => __('Enable credit rating check', 'woocommerce-novalnetpayment'),
                            'type' => 'checkbox',
                            'label' => '', // _WOOCOMMERCE_NOVALNET_ACDC_DESCRIPTION
                            'default' => ''
                        );
                    }
                    if ($this->novalnet_payment_method == 'novalnet_invoice') {
                        $this->form_fields['payment_duration'] = array(
                            'title' => __('Payment period in days', 'woocommerce-novalnetpayment'),
                            'type' => 'text',
                            'label' => '',
                            'default' => ''
                        );
                    }
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
                    $this->form_fields['payment_proxy'] = array(
                        'title' => __('Proxy-Server', 'woocommerce-novalnetpayment'),
                        'type' => 'text',
                        'description' => __('If you use a Proxy Server, enter the Proxy Server IP with port here (e.g. www.proxy.de:80)', 'woocommerce-novalnetpayment'),
                        'default' => ''
                    );
                    $this->form_fields['order_status'] = array(
                        'title' => __('Set order Status', 'woocommerce-novalnetpayment'),
                        'type' => 'select',
                        'options' => $order_statuses,
                        'description' => __('Set the status of orders made with this payment module to this value', 'woocommerce-novalnetpayment'),
                        'default' => ''
                    );
                    $this->form_fields['novalnet_logo'] = array(
                        'title' => __('Enable Novalnet Logo', 'woocommerce-novalnetpayment'),
                        'type' => 'select',
                        'options' => array('0' => __('No', 'woocommerce-novalnetpayment'), '1' => __('Yes', 'woocommerce-novalnetpayment')),
                        'description' => __('To display Novalnet logo in front end', 'woocommerce-novalnetpayment'),
                        'default' => ''
                    );
                    $this->form_fields['payment_logo'] = array(
                        'title' => __('Enable Payment Logo', 'woocommerce-novalnetpayment'),
                        'type' => 'select',
                        'options' => array('0' => __('No', 'woocommerce-novalnetpayment'), '1' => __('Yes', 'woocommerce-novalnetpayment')),
                        'description' => __('To display Payment logo in front end', 'woocommerce-novalnetpayment'),
                        'default' => ''
                    );
                }

            }

        }
    }
}

if (strstr($_SERVER['REQUEST_URI'], '/woocommerce-payment-gateway-novalnet/callback_novalnet2wordpresswoocommerce.php'))
    require_once(dirname(__FILE__) . '/callback_novalnet2wordpresswoocommerce.php');
if (isset($_REQUEST['novalnet_payment_method']) && in_array(@$_REQUEST['novalnet_payment_method'], $novalnet_payment_methods)) {
    require_once(dirname(__FILE__) . '/includes/' . @$_REQUEST['novalnet_payment_method'] . '.php');
} else {
    foreach ($novalnet_payment_methods as $novalnet_payment_method)
        require_once(dirname(__FILE__) . '/includes/' . $novalnet_payment_method . '.php');
    ob_get_clean();
}
?>
