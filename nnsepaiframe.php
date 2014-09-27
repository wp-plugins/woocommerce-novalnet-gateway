<?php
#########################################################
#  This file is used for loading Sepa Iframe.           #
#  					                					#
#  Released under the GNU General Public License.       #
#  This free contribution made by request.              #
#  If you have found this script useful a small         #
#  recommendation as well as a comment on merchant form #
#  would be greatly appreciated.                        #
#                                                       #
#  Script : nnsepaiframe.php                            #
#                                                       #
#########################################################

require_once($_REQUEST['abs_path'].'/wp-config.php');
global $wpdb;
$que= $wpdb->get_results("SELECT option_value FROM ". $wpdb->options . " WHERE option_name = 'woocommerce_novalnet_sepa_settings'");
$config_params = unserialize($que[0]->option_value);

// To get language parameter if exists in the URL
$url = wp_get_referer();
wp_parse_str($url, $params);
$lang = (isset($params['lang']) && !empty($params['lang'])) ? strtoupper($params['lang']) : strtoupper(substr(get_bloginfo('language'), 0, 2));

$name = $_REQUEST['first_name'] . " " . $_REQUEST['last_name'];
if($_REQUEST['action'] == 'novalnet_sepa_iframe') {

	$panhash = isset($_REQUEST['panhash']) ? $_REQUEST['panhash'] : '' ;
	$fldVdr = ((isset($_REQUEST['fldvdr']) && !empty($panhash)) ? $_REQUEST['fldvdr'] : '' );

	$form_parameters_for_sepa = array(
		'lang' 			=> $lang,
		'vendor_id' 	=> $config_params['merchant_id'],
		'product_id'	=> $config_params['product_id'],
		'authcode'		=> $config_params['auth_code'],
		'payment_id'	=>	'37',
		'country' 		=> $_REQUEST['country'],
		'panhash' 		=> $panhash,
		'fldVdr' 		=> $fldVdr,
		'name' 			=> htmlentities($name, ENT_QUOTES, "UTF-8"),
		'comp' 			=> htmlentities($_REQUEST['company'], ENT_QUOTES, "UTF-8"),
		'address' 		=> htmlentities($_REQUEST['address'], ENT_QUOTES, "UTF-8"),
		'zip' 			=> $_REQUEST['postcode'],
		'city' 			=> htmlentities($_REQUEST['city'], ENT_QUOTES, "UTF-8"),
		'email' 		=> $_REQUEST['email']
	);

	// basic validation for iframe request parameter
	if((empty($_REQUEST['first_name']) || empty($_REQUEST['last_name']) || empty($form_parameters_for_sepa['address']) || empty($form_parameters_for_sepa['zip']) || empty($form_parameters_for_sepa['city']) || empty($form_parameters_for_sepa['country']) || empty($form_parameters_for_sepa['email']))){
			$data = '<strong><font color="red">' . __('In order to use SEPA direct debit, please log in or enter your address. ', WC_Gateway_Novalnet::get_textdomain()) . '</font></strong>';
	}  else if (!preg_match("/^[0-9]+$/", $form_parameters_for_sepa['vendor_id']) || !preg_match("/^[0-9]+$/", $form_parameters_for_sepa['product_id']) || empty($form_parameters_for_sepa['authcode']) || empty($form_parameters_for_sepa['payment_id']) || empty($form_parameters_for_sepa['lang']) || empty($form_parameters_for_sepa['country'])) {
		$data ='<strong><font color="red">' .  __('Basic parameter not valid', WC_Gateway_Novalnet::get_textdomain()) . '</font></strong>';
	} else if(!function_exists('curl_init'))  {
		$data ='<strong><font color="red">' .  __('You need to activate the CURL function on your server, please check with your hosting provider.', WC_Gateway_Novalnet::get_textdomain()) . '</font></strong>';
	}else{
		$ssl_status = is_ssl() ? 'https://' : 'http://';
		$sepa_iframe_url = $ssl_status."payport.novalnet.de/direct_form_sepa.jsp";
		$ch = curl_init($sepa_iframe_url);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $form_parameters_for_sepa);  // add POST fields
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
