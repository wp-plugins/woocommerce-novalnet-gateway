<?php
#########################################################
#                                                       #
#  This script is used for real time capturing of       #
#  parameters passed from Novalnet AG after Payment     #
#  processing of customers.                             #
#                                                       #
#  Copyright (c) Novalnet AG                            #
#                                                       #
#  This script is only free to the use for Merchants of #
#  Novalnet AG                                          #
#                                                       #
#  If you have found this script useful a small         #
#  recommendation as well as a comment on merchant form #
#  would be greatly appreciated.                        #
#                                                       #
#  Version : 1.1.8                                      #
#                                                       #
#  Please contact sales@novalnet.de for enquiry or Info #
#                                                       #
#########################################################

require('wp-load.php');

// Variable Settings
$debug     = false; //false|true; adapt: set to false for go-live
$test      = false; //false|true; adapt: set to false for go-live
$lineBreak = empty($_SERVER['HTTP_HOST'])? PHP_EOL: '<br />';
$error 	   = false;

$invoiceAllowed = array('INVOICE_CREDIT', 'INVOICE_START');

$paypalAllowed  = array('PAYPAL');

$paymentTypes = array('INVOICE_CREDIT', 'INVOICE_START', 'PAYPAL', 'ONLINE_TRANSFER', 'CREDITCARD', 'CREDITCARD_BOOKBACK', 'IDEAL','DIRECT_DEBIT_SEPA');

$chargeBackPayments = array('CREDITCARD_CHARGEBACK', 'RETURN_DEBIT_SEPA');

$allowedPayments  = array(
	'novalnet_invoice'      => array('INVOICE_CREDIT', 'INVOICE_START'),
	'novalnet_prepayment'   => array('INVOICE_CREDIT', 'INVOICE_START'),
	'novalnet_paypal'       => array('PAYPAL'),
	'novalnet_banktransfer' => array('ONLINE_TRANSFER'),
	'novalnet_cc'           => array('CREDITCARD', 'CREDITCARD_BOOKBACK', 'CREDITCARD_CHARGEBACK'),
	'novalnet_ideal'        => array('IDEAL'),
	'novalnet_sepa'         => array('DIRECT_DEBIT_SEPA', 'RETURN_DEBIT_SEPA'),
);

//Security Setting; only this IP is allowed for call back script
$ipAllowed = '195.143.189.210'; //Novalnet IP, is a fixed value, DO NOT CHANGE!!!!!

//Reporting Email Addresses Settings (manditory;adapt for your need)
$shopInfo		= ' - Woocommerce';	//adapt if necessary
$mailHost       = ''; //SMTP Host ex: $mailHost ='mail.novalnet.de'; //adapt
$mailPort       = ''; //SMTP Port ex: $mailPort=25 //adapt
$emailFromAddr  = ''; //sender email addr., manditory, adapt it
$emailToAddr    = ''; //recipient email addr., manditory, adapt it
$emailSubject   = 'Novalnet Callback Script Access Report';  //adapt if necessary;
$emailBody      = '';  //Email text, adapt
$emailFromName  = ''; // Sender name, adapt
$emailToName    = ''; // Recipient name, adapt

//Parameters Settings
$requiredParams = array(
	'vendor_id'     => '',
	'tid'           => '',
	'payment_type'  => '',
	'status'        => '',
	'amount'        => '',
	'tid_payment'   => '',
	'order_no'      => ''
);

$request = array_map("trim", $_REQUEST);

if(isset($request['debug_mode']) && $request['debug_mode'] ){
	$debug = true;
	$test = true;
}

if ((!in_array($request['payment_type'], $invoiceAllowed)) && (!in_array($request['payment_type'], $chargeBackPayments))) {
	unset($requiredParams['tid_payment']);
}

// assign currency from the request
$currency = isset($request['currency']) ? $request['currency'] : 'EUR';

// assign tid from the request
if(in_array($request['payment_type'], $paymentTypes))
	$org_tid = (!in_array( $request['payment_type'], $invoiceAllowed )) ? $request['tid'] : $request['tid_payment'];
else
	$org_tid = isset($request['tid_payment']) ? $request['tid_payment'] : '';


// Sort an associative array in ascending order, according to the key
ksort($requiredParams);

//Test Data Settings
if ($test) {
    $emailFromName	= 'Novalnet';
    $emailToName	= 'Novalnet';
    $emailFromAddr	= 'testadmin@novalnet.de';
    $emailToAddr	= 'test@novalnet.de';
    $emailSubject	= $emailSubject . ' - TEST';
}

##################### Main Prog. ############################
try{
	if(checkIP()){ # Checking IP Address
		if ( basicValidation() ) { # Checking Basic Parameters
			$orderID = getOrderID(); # Retreive the order details
			if(empty($txn_status_code))
				$txn_status_code = getTransactionStatus($orderID); # Get Transaction Status
			if( $orderID && $txn_status_code == 100 ){
				setOrderStatus($orderID); #Final Part
			}else{
				 echo "Novalnet callback received. Status not valid! or Error in processing the transactions status";exit;
			}
		}
	}

	if ( !$emailBody ) {
		echo 'Novalnet Callback Script called for StoreId Parameters: ' . print_r($_REQUEST, true) . $lineBreak;
		echo 'Novalnet callback succeed. ' . $lineBreak;
		echo 'Params: ' . print_r($_REQUEST, true) . $lineBreak;
		exit;
	}
}catch (Exception $e){
	$emailBody .= "Exception catched: $lineBreak\$e:" . $e->getMessage() . $lineBreak;
}
if ($emailBody && !empty($emailFromAddr) && preg_match('/([\w\-]+\@[\w\-]+\.[\w\-]+)/', $emailToAddr)) {
    if (!sendEmailWoocommerce($emailBody)) {
        $error = true;
    }
} else {
	$error = true;
}

if ($debug && $error) {
	echo "Mailing failed!" . $lineBreak;
	echo "This mail text should be sent: " . $lineBreak;
	echo $emailBody;
}

#################### Sub Routines #########################

/**
* performs sending CallbackScript executed Mail
* @param $emailBody
* @return
*
*/
function sendEmailWoocommerce( $emailBody ) {
	global $lineBreak, $debug, $test, $emailFromAddr, $emailToAddr, $emailFromName, $emailToName, $emailSubject, $mailHost, $mailPort, $shopInfo;

	$headers  = 'Content-Type: text/html; charset=iso-8859-1' . "\r\n";
	$headers .= 'From: ' . $emailFromAddr . "\r\n";

	$emailSubject .= $shopInfo;
	try {
		if ( $debug ) {
			echo __FUNCTION__ . ': Sending Email suceeded!' . $lineBreak;
		}
		wp_mail( $emailToAddr, $emailSubject, $emailBody, $headers ); # WordPress Sending Mail Function
	}
	catch ( Exception $e ) {
		if ( $debug ) { echo 'Email sending failed: ' . $e->getMessage(); }
		return false;
	}
	if ( $debug ) {
		echo 'This text has been sent:' . $lineBreak . $emailBody;
	}
	return true;
}

function setOrderStatus($orderID){
	global $wpdb, $lineBreak, $request, $org_tid, $currency, $allowedPayments, $invoiceAllowed, $paypalAllowed, $paymentTypes, $chargeBackPayments, $emailBody, $callback_script_text;

	$new_line = "\n";

	$order = new WC_Order($orderID);

	$payment_method = $order->payment_method;
	$payment_method_title = $order->payment_method_title;
	$order_total = $order->order_total;
	$order_status = $order->status;

	$query = $wpdb->get_results("SELECT option_value FROM ". $wpdb->options . " WHERE option_name = 'woocommerce_".$payment_method."_settings'");

	$config_settings = unserialize($query[0]->option_value);

	$nn_order_id = (!empty($request['order_no'])) ? $request['order_no'] : (!empty($request['order_id']) ? $request['order_id'] : '');

	$final_status = (in_array($request['payment_type'], $invoiceAllowed) ) ? $config_settings['callback_order_status'] : $config_settings['set_order_status'];

	if($orderID){
		if($request['status'] == 100){
			if(in_array($request['payment_type'], $chargeBackPayments)){
					$emailBody .= $callback_script_text .= "Novalnet Callbackscript received. Charge back was executed sucessfully for the TID ".$request['tid_payment']." amount ". ($request['amount'])/100 .  $currency ." on " .date_i18n(get_option('date_format'), strtotime(date('Y-m-d')));

					$order->add_order_note($callback_script_text);

					return true;
			}else{
				$reqAmount = $request['amount'];
				$callback_amount = get_post_meta($orderID,'_nn_callback_amount',true);
				$sum_amount = $reqAmount + $callback_amount;
				$org_amount = sprintf('%0.2f', $order_total)*100;

				if ($callback_amount < $org_amount) {
					if($request['payment_type'] == 'INVOICE_CREDIT'){
						$emailBody .= $callback_script_text .= "$new_line Novalnet Callback Script executed successfully for the TID: ". $_REQUEST['tid_payment']." with amount ".($_REQUEST['amount']/100)."$currency on ". date_i18n(get_option('date_format'), strtotime(date('Y-m-d'))) . " Please refer PAID transaction in our Novalnet Merchant Administration with the TID:".$_REQUEST['tid'];

						update_post_meta($orderID, '_nn_callback_amount', $sum_amount);
						update_post_meta($orderID,'_nn_ref_tid',$org_tid);

						$order->add_order_note($callback_script_text);

						if($sum_amount >= $org_amount){

							update_post_meta($orderID, '_nn_status_code', $request['status']);
							update_post_meta($order->id, '_nn_capture_code', 1);

							if($sum_amount > $org_amount)
								$emailBody .= "<strong> Customer paid amount is greater than the total amount. </strong>". $lineBreak;

							$order->update_status($final_status);
						}
					}
					else {
						if( !in_array($request['payment_type'], $invoiceAllowed) && $callback_amount != $org_amount){

							$callback_script_text .= "Novalnet Callback Script executed successfully for the amount : ".($_REQUEST['amount']/100). "  " .$currency.". The subsequent TID: " . $_REQUEST['tid'] . " on " . date_i18n(get_option('date_format'), strtotime(date('Y-m-d'))). $new_line;

							$emailBody .=  "Novalnet Callback Script executed successfully for the amount : ".($_REQUEST['amount']/100). "  " .$currency.". The subsequent TID: " . $_REQUEST['tid'] . " on " . date_i18n(get_option('date_format'), strtotime(date('Y-m-d'))). $lineBreak;

							$order->update_status($final_status);
							update_post_meta($orderID, '_nn_callback_amount', $sum_amount);
							update_post_meta($orderID, '_nn_status_code', $request['status']);

							$order->add_order_note($callback_script_text);

						}else{
							echo "Novalnet callback received. Callback Script executed already. Refer Order : " . $nn_order_id;exit;
						}
					}
				} else {
					echo "Novalnet callback received. Callback Script executed already. Refer Order :".$nn_order_id;
					exit;
				}
			}
		}else {
			 echo "Novalnet callback received. Callback Script executed already. Refer Order :".$nn_order_id;
			 exit;
		}
	}else {
      echo "Novalnet Callback received. No order for Increment-ID $ordeiID found.";
      exit;
    }
	return true;
}

function getOrderID(){
	global $wpdb, $lineBreak,$debug, $org_tid, $request, $emailBody, $allowedPayments;

	$table_name = $wpdb->postmeta;

	$nn_order_id = (!empty($request['order_no'])) ? $request['order_no'] : (!empty($request['order_id']) ? $request['order_id'] : '');

	if($nn_order_id){
		$sql ="SELECT post_id FROM `$table_name` where meta_value LIKE '%".$nn_order_id."%' AND meta_key='_order_number'";
		try{
			$row = $wpdb->get_results($sql);
			if($row){
				$order_id = $row[0]->post_id;	// getting the order id
				$payment_method = get_post_meta($order_id,'_payment_method',true);
				$order_tid = get_post_meta($order_id, '_nn_order_tid', true);
				$order_total = get_post_meta($order_id,'_order_total',true);
			}else{
				if (ctype_digit ($nn_order_id)) {
					$payment_method = get_post_meta($nn_order_id,'_payment_method',true);
					$order_total = get_post_meta($nn_order_id,'_order_total',true);
					$order_tid = get_post_meta($nn_order_id, '_nn_order_tid', true);
					if($payment_method)
						$order_id = $nn_order_id;
				}else{
					echo "Novalnet callback received. Order id : " . $nn_order_id . " not exist ".$lineBreak;exit;
				}

			}
		} catch (Exception $e){
			echo 'The original order not found in the shop database table (`' . $table_name . '`);';
			echo 'Reason: ' . $e->getMessage() . $lineBreak . $lineBreak;
			echo 'Query : ' . $sql . $lineBreak . $lineBreak;
			exit;
		}

	}
	if (empty($order_id)) {
		echo "Novalnet callback received. Order id : " . $nn_order_id . " not exist ".$lineBreak;exit;
	}
	if ($debug) {
		echo'Order Details:<pre>';
		echo "Order Number:" . $nn_order_id . "</br>";
		echo "Order Total:" . $order_total . "</br>";
		echo'</pre>';
	}

	$order = new WC_Order($order_id);

	if(empty($order->customer_note)){
		updateTransactionDetails($order);
	}

	if ( !array_key_exists($payment_method, $allowedPayments) || !in_array($request['payment_type'], $allowedPayments[$payment_method])){
		echo "Novalnet callback received. Payment type [".$request['payment_type']."] is mismatched!$lineBreak$lineBreak";
		exit;
	}

	if((isset($org_tid) && 100 == $request['status'] && $order_tid && $order_tid !== $org_tid) ){
		echo "Novalnet callback received. TID [".$org_tid."] is mismatched!$lineBreak$lineBreak";
		exit;
	}

	return $order_id;
}

function getTransactionStatus($id){
	global $lineBreak, $request, $org_tid;
	$nn_status_code = 0;
	$ssl_status = is_ssl() ? 'https://' : 'http://';
	$url = $ssl_status.'payport.novalnet.de/nn_infoport.xml';
	$config_data = get_post_meta($id, '_nn_config_values', true);

	if(!empty($config_data)){
		$urlparam = '<?xml version="1.0" encoding="UTF-8"?><nnxml><info_request><vendor_id>' . $config_data['vendor']. '</vendor_id>';
		$urlparam .= '<vendor_authcode>' . $config_data['auth_code'] . '</vendor_authcode>';
		$urlparam .= '<request_type>TRANSACTION_STATUS</request_type>';
		$urlparam .= '<product_id>' . $config_data['product'] . '</product_id>';
		$urlparam .= '<tid>' . $org_tid . '</tid>';
		$urlparam .='</info_request></nnxml>';

		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $urlparam);  // add POST fields
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		$data = curl_exec($ch);
		curl_close($ch);

		if (strstr($data, '<status>')) {
			preg_match('/status>?([^<]+)/i', $data, $matches);
			$nn_status_code = $matches[1];
		}
	}
	return $nn_status_code;
}

function updateTransactionDetails($order){
	global $lineBreak, $request, $org_tid, $wpdb, $txn_status_code, $invoiceAllowed;
	$lineBreak = "\n";

	$payment_method = $order->payment_method;

	$query = $wpdb->get_results("SELECT option_value FROM ". $wpdb->options . " WHERE option_name = 'woocommerce_".$payment_method."_settings'");

	$config_settings = unserialize($query[0]->option_value);

	$comments = html_entity_decode($order->payment_method_title, ENT_QUOTES, 'UTF-8') . $lineBreak;
	$comments .= 'Novalnet Transaction ID : ' . $org_tid . $lineBreak;
	if(isset($request['test_mode']) && $request['test_mode'])
		$comments .= 'Test Order' . $lineBreak;

	if($order->customer_note)
		$order->customer_note .= $new_line;

	$order->customer_note .= html_entity_decode($comments, ENT_QUOTES, 'UTF-8');

	$nn_order_notes = array(
		'ID' => $order->id,
		'post_excerpt' => $order->customer_note
	);

	wp_update_post($nn_order_notes);

	$order->add_order_note($order->customer_note);

	update_post_meta($order->id,'_nn_total_amount', $order->order_total);
    update_post_meta($order->id,'_nn_order_tid', $org_tid);

	$txn_status_code = getTransactionStatus($order->id);

	if($txn_status_code == 100){
		$order->update_status($config_settings['set_order_status']);
		if(!in_array($request['payment_type'], $invoiceAllowed))
			update_post_meta($order->id,'_nn_callback_amount', $order->order_total*100);
	}

	return true;
}

/**
* performs initial parameter check
*
* @param array $request
* @return
*
*/
function basicValidation() {
	global $lineBreak, $requiredParams, $emailBody, $allowedPayments, $paypalAllowed, $invoiceAllowed, $paymentTypes, $chargeBackPayments, $request;

	$error = false;

	// If no params passed through CallBackScript URL
	if( !$request ) {
	  echo 'Novalnet callback received. No params passed over!'.$lineBreak;
	  exit;
	}

	//If Params passed but respective param's value if not passed
	if ( $requiredParams ) {
		foreach ( $requiredParams as $k=>$v ) {
			if ( empty( $request[$k] ) ) {
				$error = true;
				echo 'Required param ('.$k.') missing!'.$lineBreak;
			}
		}
		if ( $error ) {
			exit;
		}
	}

	// If requested payment type is not available
	if ( !empty( $request['payment_type'] )) {
		if(!in_array($request['payment_type'],$paymentTypes ) && !in_array($request['payment_type'],$chargeBackPayments )){
			echo "Novalnet callback received. Payment type [". $request['payment_type'] ."] is mismatched! $lineBreak";
			exit;
		}
	}

	// Validating requested status
	if (!isset($request['status']) or $request['status'] <= 0 ) {
		echo 'Novalnet callback received. Status [' . $request['status'] . '] is not valid' . "$lineBreak$lineBreak".$lineBreak;exit;
	}

	// Validating length of $_REQUEST['tid_payment'] should not be less than 17 digit
	if ( strlen( $_REQUEST['tid_payment'] ) != 17 && (in_array( $request['payment_type'], $invoiceAllowed )  || in_array( $request['payment_type'], $chargeBackPayments ))) {
		echo 'Novalnet callback received. Invalid TID [' . $request['tid_payment'] . '] for Order.' . "$lineBreak$lineBreak".$lineBreak;exit;
	}

	//	Validating length of $_REQUEST['tid'] should not be less than 17 digit
	if ( strlen( $_REQUEST['tid']) != 17 ) {
		if ( in_array( $_REQUEST['payment_type'], $invoiceAllowed ) || in_array( $request['payment_type'], $chargeBackPayments ) ) {
			echo 'Novalnet callback received. New TID is not valid.' . "$lineBreak$lineBreak".$lineBreak;
		} else {
			echo 'Novalnet callback received. Invalid TID ['.$request['tid'].']for Order.'."$lineBreak$lineBreak".$lineBreak;
		}
		exit;
	}

	// Validating amount
	if(!$request['amount'] || $request['amount'] < 0) {
		echo 'Novalnet callback received. The requested amount ['.$request['amount'].'] must be greater than zero.';
		exit;
	}
	return true;
}

function isPublicIP($value) {
    if (!$value || count(explode('.', $value)) != 4)
      return false;
    return !preg_match('~^((0|10|172\.16|192\.168|169\.254|255|127\.0)\.)~', $value);
  }

/**
* get IP address
*
*/
function getRealIpAddr() {
	if (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && isPublicIP($_SERVER['HTTP_X_FORWARDED_FOR']))
		return $_SERVER['HTTP_X_FORWARDED_FOR'];

	if (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && $iplist = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])) {
		if (isPublicIP($iplist[0]))
			return $iplist[0];
		}
	if (isset($_SERVER['HTTP_CLIENT_IP']) && isPublicIP($_SERVER['HTTP_CLIENT_IP']))
		return $_SERVER['HTTP_CLIENT_IP'];

	if (isset($_SERVER['HTTP_X_CLUSTER_CLIENT_IP']) && isPublicIP($_SERVER['HTTP_X_CLUSTER_CLIENT_IP']))
		return $_SERVER['HTTP_X_CLUSTER_CLIENT_IP'];

	if (isset($_SERVER['HTTP_FORWARDED_FOR']) && isPublicIP($_SERVER['HTTP_FORWARDED_FOR']))
		return $_SERVER['HTTP_FORWARDED_FOR'];

	return $_SERVER['REMOTE_ADDR'];
}

/**
* Checks IP address
*/
function checkIP() {
	global $lineBreak, $ipAllowed, $test, $emailBody, $request;
	$callerIp = $_SERVER['REMOTE_ADDR'];
	if ($test) {
		$ipAllowed = getRealIpAddr();
	}
	if ($ipAllowed != $callerIp) {
		echo 'Novalnet callback received. Unauthorised access from the IP [' . $callerIp . ']' . $lineBreak . $lineBreak;
		exit;
	}
	return true;
}
?>