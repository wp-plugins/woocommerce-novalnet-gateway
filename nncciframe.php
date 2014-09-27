<?php
#########################################################
#  This file is used for loading Credit Card Iframe.    #
#						      							#
#  Released under the GNU General Public License.       #
#  This free contribution made by request.              #
#  If you have found this script useful a small         #
#  recommendation as well as a comment on merchant form #
#  would be greatly appreciated.                        #
#                                                       #
#  Script : nncciframe.php                              #
#                                                       #
#########################################################

require_once($_REQUEST['abs_path'].'/wp-config.php');
global $wpdb;
$que=$wpdb->get_results("SELECT option_value FROM ". $wpdb->options . " WHERE option_name = 'woocommerce_novalnet_cc_settings'");
$config_params = unserialize($que[0]->option_value);

// To get language parameter if exists in the URL
$url = wp_get_referer();
wp_parse_str($url, $params);
$lang = (isset($params['lang']) && !empty($params['lang'])) ? strtoupper($params['lang']) :strtoupper(substr(get_bloginfo('language'), 0, 2));

if($_REQUEST['action'] == 'novalnet_cc_iframe') {

	$nncc_hash = isset($_REQUEST['panhash']) ? $_REQUEST['panhash'] : '' ;
	$fldVdr = ((isset($_REQUEST['fldvdr']) && !empty($panhash)) ? $_REQUEST['fldvdr'] : '' );

	$form_parameters_for_cc = array(
		'nn_lang_nn' => $lang,
		'nn_vendor_id_nn' => $config_params['merchant_id'],
		'nn_authcode_nn' => $config_params['auth_code'],
		'nn_product_id_nn' => $config_params['product_id'],
		'nn_payment_id_nn' => '6',
		'nn_hash' => $nncc_hash,
		'fldVdr' =>	$fldVdr,
	);
	// 	basic validation for iframe request parameter
    if ( !preg_match("/^[0-9]+$/", $form_parameters_for_cc['nn_vendor_id_nn'])
		|| !preg_match("/^[0-9]+$/", $form_parameters_for_cc['nn_product_id_nn'])
		|| empty($form_parameters_for_cc['nn_authcode_nn']) || empty($form_parameters_for_cc['nn_payment_id_nn'])
		|| empty($form_parameters_for_cc['nn_lang_nn'])) {

		$data ='<strong><font color="red">' .  __('Basic parameter not valid', WC_Gateway_Novalnet::get_textdomain()) . '</font></strong>';
	}else if(!function_exists('curl_init'))  {
		$data ='<strong><font color="red">' .  __('You need to activate the CURL function on your server, please check with your hosting provider.', WC_Gateway_Novalnet::get_textdomain()) . '</font></strong>';
	} else {
		$ssl_status = is_ssl() ? 'https://' : 'http://';
		$cc_iframe_url = $ssl_status."payport.novalnet.de/direct_form.jsp";
		$ch = curl_init($cc_iframe_url);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $form_parameters_for_cc);  // add POST fields
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		$data = curl_exec($ch);
		curl_close($ch);
	}
	echo $data;
	exit;
}
?>
