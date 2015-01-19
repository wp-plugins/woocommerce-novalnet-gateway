<?php
/**
 * Novalnet API Operations
 *
 * Copyright (c) 2015 Novalnet AG <https://www.novalnet.de>
 *
 * Released under the GNU General Public License. This free
 * contribution made by request.If you have found this script
 * useful a small recommendation as well as a comment on
 * merchant form would be greatly appreciated.
 *
 * This file is used to access Novalnet API operations  for all payment methods.
 *
 * @version		2.0.0
 * @package		woocommerce-novalnet-gateway/
 * @author 		Novalnet
 * @link		https://www.novalnet.de
 * @copyright	2015 Novalnet AG <https://www.novalnet.de>
 * @license     GNU General Public License version 2.0
 */

 // Exit if accessed directly
 if ( ! defined( 'ABSPATH' ) )
  exit;

 require_once('woocommerce-novalnet-gateway.php');

 class NN_Functions{

	function __construct(){
		if(isset($_SESSION['novalnet']['novalnet_thankyou_page_got']))
			unset($_SESSION['novalnet']['novalnet_thankyou_page_got']);

		if(isset($_REQUEST['pay_for_order']) && $_REQUEST['pay_for_order'])
			$this->novalnet_pay_status();
	}

	/*
	* Return if given input value is numeric or not
	* @return Boolean
	*/
	public function is_digits( $input = '' , $return = false  ) {
		$input = trim($input);
		if ( $input != '' && preg_match ("/^[0-9]+$/" , $input ) ) {
			if( $return )
				return trim($input);
			else
				return true;
		}
		return false;
	}

	/*
	* Return valid holder name
	*
	* @return String
	*/
	public function get_valid_holdername($org_holder_name = '') {
		$org_holder_name = trim($org_holder_name);
		$from_ary = array('=', '&');
		return str_replace($from_ary, '', trim($org_holder_name));
	}
	/*
	* Generate unique string
	* @return String
	*/
	public function generate_random_string($char_limit = '30'){
		$randomwordarray=array( "a","b","c","d","e","f","g","h","i","j","k","l","m","1","2","3","4","5","6","7","8","9","0");
		shuffle($randomwordarray);
		return substr(implode($randomwordarray,""), 0, $char_limit);
	}

	public function check_merchant_configuration( $options ) {
		if ( ! $this->is_digits( $options['nn_vendor_id'] ) || ! $this->is_digits( $options['nn_project_id'] ) || ! $this->is_digits( $options['nn_tariff_id'] ) || empty( $options['nn_auth_code'] ) || empty( $options['nn_key_password'] ) || ( isset( $options['nn_manual_check'] ) && $options['nn_manual_check'] && ! $this->is_digits( $options['nn_manual_check'] ) ) || ( isset( $options['nn_gateway_timout'] ) && $options['nn_gateway_timout'] && ! $this->is_digits( $options['nn_gateway_timout'] ) ) || ( isset($options['nn_callback_mail_address'] ) && $options['nn_callback_mail_address'] && !is_email( $options['nn_callback_mail_address']) ) || ( isset( $options['nn_callback_mail_address_bcc'] ) && $options['nn_callback_mail_address_bcc'] && !is_email( $options['nn_callback_mail_address_bcc'] ) ) )	{
			return true;
		}
		return false;
	}

	public function validate_global_settings( $options ) {

		if ( ! $this->is_digits( $options['vendor_id'] ) || ! $this->is_digits( $options['product_id'] ) || ! $this->is_digits( $options['tariff_id'] ) || empty( $options['auth_code'] ) || empty( $options['key_password'] ) || ( isset( $options['gateway_timeout'] ) && $options['gateway_timeout'] && ! $this->is_digits( $options['gateway_timeout'] ) ) )	{
			return true;
		}
		return false;
	}

	/**
     * api call basic Validation
     * @access public
     * @return bool
     */
    public function is_valid_api_params( $nn_obj, $api_tid, $api_id ) {

        if ( self::is_basic_validation($nn_obj) && isset($api_tid) && $api_tid != null && $api_id != null ) {
            return true;
        }
        else {
            return false;
        }
    }	// End is_valid_api_params()

    /**
     * Basic parameter validations
     * @access public
     * @return bool
     */
    public function is_basic_validation($nn_obj) {

		$nn_api_config  = $GLOBALS[ NN_CONFIG ]->global_settings;

        if ( !empty($nn_api_config['vendor_id'])  && !empty($nn_api_config['product_id'])  && !empty($nn_api_config['tariff_id'])  && !empty($nn_api_config['auth_code'])  && !empty($nn_obj->payment_key) ) {
            return true;
        }
        else {
            return false;
        }
    }	// End is_basic_validation()

	/**
	 * Transfer data via curl library (consists of various protocols)
	 * @access public
	 * @return array
	 */
	public function perform_https_request( $url = '', $form = array() ) {
		if ( ! empty( $url ) && ! empty( $form ) ) {

			$curl_timeout = $GLOBALS[ NN_CONFIG ]->global_settings['gateway_timeout'];
			$ch = curl_init($url);
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $form);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
			curl_setopt($ch, CURLOPT_TIMEOUT, (($curl_timeout != '' && $curl_timeout > 240) ? $curl_timeout : 240));  #Custom CURL time-out
			// payment proxy
			if (isset($GLOBALS[ NN_CONFIG ]->global_settings['proxy'])) {
				curl_setopt($ch, CURLOPT_PROXY, trim($GLOBALS[ NN_CONFIG ]->global_settings['proxy']));
			}
			$response = curl_exec($ch);
			curl_close($ch);
			return $response;
		}
		return array();
	}

	/**
	* performs transaction status
	* request to Novalnet server
	* @access public
	* @return string
	*/
	function make_transaction_status( $response, $order_id, $exists = false, $array = array() ) {
		if ( $exists ) {
			$result = self::get_novalnet_trans_details( $order_id, 'row', 'vendor_id,auth_code,product_id');
			$nn_api_config = array( 'vendor_id' => $result->vendor_id, 'product_id' => $result->product_id, 'auth_code' => $result->auth_code );
		} else {
			$nn_api_config = $GLOBALS[ NN_CONFIG ]->global_settings;
			if ( !empty( $array ) ){
				$nn_api_config['product_id'] = $array['product'];
				$nn_api_config['tariff_id'] = $array['tariff'];
			}
		}

		$data = array();

		if ( !empty( $nn_api_config ) ) {

			$urlparam = $this->generate_xmldatarequest(array(
				'vendor_id' 	 => $nn_api_config['vendor_id'],
				'vendor_authcode'=> $nn_api_config['auth_code'],
				'request_type' 	 => 'TRANSACTION_STATUS',
				'tid' 			 => $response['tid'],
				'product_id' 	 => $nn_api_config['product_id'],
			));

			$data_xml = self::perform_https_request( ( is_ssl() ? 'https://' : 'http://' ).INFOPORT_URL, $urlparam );
			$data = json_decode(json_encode((array)simplexml_load_string($data_xml)),1);

		}

		return ($data);

	}	// End make_transaction_status()

	public function generate_xmldatarequest( $request_param ) {

		$urlparam = '<?xml version="1.0" encoding="UTF-8"?><nnxml><info_request>';

		foreach ($request_param as $key => $value){
			$urlparam .= '<' . $key . '>' . $value . '</' . $key . '>';
		}

		$urlparam .='</info_request></nnxml>';
		return $urlparam;
	}

	public function get_sepa_bank_country(){
		global $woocommerce;
		$country_list = '';
		if (version_compare($woocommerce->version, '2.1.0', '>=')){
			$countries = WC()->countries->countries;
			$chosen_country = isset(WC()->customer->country) ? WC()->customer->country : '';
		}else{
			$countries =  $woocommerce->countries->countries;
			$chosen_country = $woocommerce->customer->country;
		}

		foreach ( $countries as $code => $country ){
			if($code == $chosen_country)
				$selected = 'selected="selected"';
			else
				$selected = '';
			$country_list .= '<option value="'.$code.'" '. $selected.'>'.$country.'</option>';
		}

		return $country_list;
	}

	public function log_initial_trans_details($data = array()){
		global $wpdb;
		$wpdb->insert( $wpdb->prefix.'novalnet_transaction_detail', $data );
	}

	public function get_payment_title( $settings ) {
		if ( substr( get_bloginfo( 'language' ), 0, 2 ) == 'de' )
			return $settings['title_de'];

		return $settings['title_en'];
	}

	public function get_payment_description( $settings ) {
		if ( substr( get_bloginfo( 'language' ), 0, 2 ) == 'de' )
			return $settings['description_de'];

		return $settings['description_en'];
	}

	public function update_novalnet_trans_details($data, $where) {
		global $wpdb;
		$wpdb->update($wpdb->prefix.'novalnet_transaction_detail', $data, $where);

	}
	public function get_novalnet_trans_details($order_id, $type, $data){
		global $wpdb;
		$sql = "SELECT ".$data." FROM ".$wpdb->prefix."novalnet_transaction_detail WHERE order_no=". $order_id ;

		switch($type){
			case 'var':
				$result = $wpdb->get_var($sql);
				return $result;
			case 'row':
				$result = $wpdb->get_row($sql);

				if($wpdb->num_rows > 1){
					$sql = "SELECT ".$data." FROM ".$wpdb->prefix."novalnet_transaction_detail WHERE order_no=". $order_id ." ORDER BY id DESC LIMIT 1";
					$result = $wpdb->get_row($sql);
				}
				return $result;
		}
	}

	public function get_order_status() {

		global $wpdb;

		if(WOOCOMMERCE_VERSION >= '2.2.0') {
			$available_status = wc_get_order_statuses();
		}else {
			$sql = "SELECT slug, name FROM $wpdb->terms WHERE term_id in(SELECT term_id FROM $wpdb->term_taxonomy WHERE taxonomy='%s')";
			$row = $wpdb->get_results( $wpdb->prepare( $sql,'shop_order_status') );

			for($i=0;$i < sizeof($row);$i++) {
				$available_status[$row[$i]->slug]=__($row[$i]->name, 'woocommerce');
			}
		}

		return $available_status;
	}

	/**
	 * Pay account status
	 */
	public function novalnet_pay_status(){
		global $wpdb;
		$order_number = ( $_GET['order-pay'] ? $_GET['order-pay'] : ($wp->query_vars['order-pay'] ? $wp->query_vars['order-pay'] : '') );
		$url = site_url() .'/?'.$_SERVER['QUERY_STRING'];
		wp_parse_str($url, $url_value);

		$confirm_order = $this->get_novalnet_trans_details($order_number, 'var', 'tid');
		$cur_lang = get_bloginfo('language');
        $message = 'Novalnet Transaction for the Order has been executed / cancelled already.';
        if(substr($cur_lang, 0, 2) == 'de')
            $message = 'Die Novalnet-Buchung für die Bestellung wurde schon ausgeführt / abgeschlossen.';

		if(!isset($url_value['change_payment_method']) && isset($url_value['pay_for_order']) && !empty($url_value['pay_for_order'])){
			if( !empty($confirm_order) ){
				?>
				<style>
					.woocommerce{
						display : none;
				}
				</style>
				<?php
				echo '<script src="'.NOVALNET_URL .'assets/js/jquery.js" type="text/javascript"></script>';
				echo '<script type="text/javascript">
					   $( document ).ready(function() {
					   $(".entry-content").html("<p style=font-size:17px><b>'.$message.'</p></b>");
					   });
					  </script>';
			}
		}
	}

	public function get_customer_info() {
		global $wp;
		$order_number = ( isset( $_REQUEST['order-pay'] ) ? $_REQUEST['order-pay'] : isset( $wp->query_vars['order-pay'] ) ? $wp->query_vars['order-pay'] : '' );
		if ( ! empty( $order_number ) && isset( $_REQUEST['pay_for_order'] ) ) {

			$order = new WC_Order( $order_number );

			$customer_info = array(
				'first_name' 	=> str_replace(" ", '', $order->billing_first_name),
				'last_name' 	=> str_replace(" ", '', $order->billing_last_name),
				'company' 		=> str_replace(" ", '', $order->billing_company),
				'city'			=> str_replace(" ", '', $order->billing_city),
				'email'			=> $order->billing_email,
				'zip'			=> $order->billing_postcode,
				'address'		=> str_replace(" ", '', $order->billing_address_1),
			);

			return http_build_query( $customer_info );
		}
	}
}

$GLOBALS[NN_FUNCS] = new NN_Functions();
?>
