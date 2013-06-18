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
#  Version : 1.0.3                                      #
#                                                       #
#  Please contact sales@novalnet.de for enquiry or Info #
#                                                       #
#########################################################
/**
* Novalnet Callback Script for Wordpress woocommerce shop
*
* NOTICE
*
* This script is used for real time capturing of parameters passed
* from Novalnet AG after Payment processing of customers.
*
* This script is only free to the use for Merchants of Novalnet AG
*
* If you have found this script useful a small recommendation as well
* as a comment on merchant form would be greatly appreciated.
*
* Please contact sales@novalnet.de for enquiry or info
*
* ABSTRACT: This script is called from Novalnet, as soon as a payment
* done for payment methods, e.g. Prepayment, Invoice, PayPal.
* An email will be sent if an error occurs
*
*
* @category   Novalnet
* @package    Novalnet
* @version    1.0
* @copyright  Copyright (c) 2013 Novalnet AG. (http://www.novalnet.de)
* @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
* @notice     1. You have to adapt the value of all the variables
*                commented with 'adapt ...'
*             2. Set $test/$debug to false for live system
*/

ini_set('display_errors','on');
//Variable Settings
$debug        		  = false; //false|true; adapt: set to false for go-live
$test           	  = false; //false|true; adapt: set to false for go-live
$lineBreak     		  = empty($_SERVER['HTTP_HOST'])? PHP_EOL: '<br />';
$addSubsequentTidToDb = true;//whether to add the new tid to db; adapt if necessary

$aPaymentTypes = array('INVOICE_CREDIT','PAYPAL');//adapt here if needed; Options are:

// Order State/Status Settings
/*    Standard Types of Status:
1. pending = pending
2. failed = failed
3. on-hold = on-hold
4. processing = processing
5. completed = completed
6. refunded = refunded
7. cancelled = cancelled
8. awaiting-payment = Awaiting Payment

*/
$payment_method_array = array('novalnet_prepayment', 'novalnet_invoice','novalnet_paypal');
$orderState  = 'completed'; //Note: Indicates Payment accepted.

//$orderComment = $lineBreak.date('d.m.Y H:i:s').': Novalnet callback script changed order state to '.$orderState.' and order status to '. $orderStatus;

//Security Setting; only this IP is allowed for call back script
$ipAllowed = '195.143.189.210'; //Novalnet IP, is a fixed value, DO NOT CHANGE!!!!!
//$ipAllowed = '182.72.184.185'; //Novalnet IP, is a fixed value, DO NOT CHANGE!!!!!

//Reporting Email Addresses Settings
$shopInfo	= 'Wordpress Woocommerce ' . $lineBreak; //manditory;adapt for your need
$mailHost	= 'mail.novalnet.de'; //adapt
$mailPort	= 25; //adapt
$emailFromAddr	= ''; //sender email addr., manditory, adapt it
$emailToAddr	= ''; //recipient email addr., manditory, adapt it
$emailSubject	= 'Novalnet Callback Script Access Report'; //adapt if necessary;
$emailBody	= ''; //Email text, adapt
$emailFromName	= ""; // Sender name, adapt
$emailToName	= ""; // Recipient name, adapt

//Parameters Settings
$hParamsRequired = array(
'vendor_id'		=> '',
'tid'			=> '',
'payment_type'	=> '',
'status'		=> '',
'amount'		=> '',
'tid_payment'	=> '');


/*formatted url
<Site URL>/woocommerce-payment-gateway-novalnet/callback_novalnet2wordpresswoocommerce.php?vendor_id=4&status=100&payment_type=INVOICE_CREDIT&tid_payment=12675800001204435&amount=3778&tid=12675800001204435

Parameters:

tid			Callscript Transaction TID
vendor_id		Merchant ID
status			Successfull payment transaction value
order_no		Existing shops order no which need to be update
payment_type            Types of payment process
tid_payment		Existing shops order transaction id
amount			Customer paid amount in cents
*/

/*$hParamsTest = array(
'vendor_id'		=> '4',
'status'		=> '100',
'amount'		=> '2886', //must be avail. in shop database; 850 = 8.50
'payment_type'	=> 'INVOICE_CREDIT',
'tid_payment'	=> '12613200004110310', //orig. tid; must be avail. in shop database
'tid'			=> '12345678901234567', //subsequent tid, from Novalnet backend; can be a fake for test
'order_no'		=> '',	// Order number
);*/

if (in_array('INVOICE_CREDIT', $aPaymentTypes) and isset($_REQUEST['payment_type']) and $_REQUEST['payment_type'] == 'INVOICE_CREDIT' ){
$hParamsRequired['tid_payment'] = '';
$hParamsTest['tid_payment'] = '12497500001209615'; //orig. tid; must be avail. in shop database; adapt for test;
}
ksort($hParamsRequired);
//ksort($hParamsTest);

//Test Data Settings
if ($test) {
//$_REQUEST		= $hParamsTest;
$emailFromName	= "Novalnet"; // Sender name, adapt
$emailToName	= "Novalnet"; // Recipient name, adapt
$emailFromAddr	= 'test@novalnet.de'; //manditory for test; adapt
$emailToAddr	= 'test@novalnet.de'; //manditory for test; adapt
$emailSubject	= $emailSubject . ' - TEST'; //adapt
}

// ################### Main Prog. ##########################
try {
//Check Params
if (checkIP($_REQUEST)) {
if (checkParams($_REQUEST)) {
//Get Order ID and Set New Order Status
if ($orderIncrementId = BasicValidation($_REQUEST)) {
setOrderStatus($orderIncrementId);  //and send error mails if any 
}
}
}

if (!$emailBody) {
$emailBody .= 'Novalnet Callback Script called for StoreId Parameters: ' . print_r($_POST, true) . $lineBreak;
$emailBody .= 'Novalnet callback succ. ' . $lineBreak;
$emailBody .= 'Params: ' . print_r($_REQUEST, true) . $lineBreak; 
}
} catch (Exception $e) {
$emailBody .= "Exception catched: $lineBreak\$e:" . $e->getMessage() . $lineBreak; 
}

if ($emailBody) {
if (!sendEmailWordpresswoocommerce($emailBody)) {
if ($debug) {
echo "Mailing failed!" . $lineBreak;
echo "This mail text should be sent: " . $lineBreak;
echo $emailBody; 
}
}
}
exit();
// ############## Sub Routines #####################
function sendEmailWordpresswoocommerce($emailBody) {
global $lineBreak, $debug, $test, $emailFromAddr, $emailToAddr, $emailFromName, $emailToName, $emailSubject, $shopInfo, $mailHost, $mailPort;
$emailBodyT = str_replace('<br />', PHP_EOL, $emailBody);

//Send Email
ini_set('SMTP', $mailHost);
ini_set('smtp_port', $mailPort);

@header('Content-Type: text/html; charset=iso-8859-1');
$headers = 'From: ' . $emailFromAddr . "\r\n";
try {
if ($debug) {
echo __FUNCTION__ . ': Sending Email suceeded!' . $lineBreak;
}
$sendmail = @mail($emailToAddr, $emailSubject, $emailBodyT, $headers);
}
catch (Exception $e) {
if ($debug) { echo 'Email sending failed: ' . $e->getMessage(); }
return false;
}
if ($debug) {
echo 'This text has been sent:' . $lineBreak . $emailBody;
}
return true;
}

function checkParams($request) {
global $lineBreak, $hParamsRequired, $emailBody, $aPaymentTypes;
$error = false;
$emailBody = '';
if(!$request){
$emailBody .= 'Novalnet callback received. No params passed over!'.$lineBreak;
return false;
}

if (!isset($request['payment_type'])){
$emailBody .= "Novalnet callback received. But Param payment_type missing$lineBreak";
return false;
}

if (!in_array($request['payment_type'], $aPaymentTypes)){
$emailBody .= "Novalnet callback received. Payment type is not Prepayment/Invoice/PayPal! $lineBreak";
return false;
}

if($hParamsRequired){
foreach ($hParamsRequired as $k=>$v){
if (empty($request[$k])){
$error = true;
$emailBody .= 'Required param ('.$k.') missing!'.$lineBreak;
}
}
if ($error){
return false;
}
}

if(!isset($request['status']) or 100 != $request['status']) {
$emailBody .= 'Novalnet callback received. Status [' . $request['status'] . '] is not valid: Only 100 is allowed.' . "$lineBreak$lineBreak".$lineBreak;
return false;
}

if(strlen($request['tid_payment'])!=17){
$emailBody .= 'Novalnet callback received. Invalid TID [' . $request['tid_payment'] . '] for Order.' . "$lineBreak$lineBreak".$lineBreak;
return false;
}

if(strlen($request['tid'])!=17){
$emailBody .= 'Novalnet callback received. New TID is not valid.' . "$lineBreak$lineBreak".$lineBreak;
return false;
}
return true;
}

function BasicValidation($request){
global $lineBreak, $tableOrderPayment, $tableOrder, $emailBody, $debug;
$orderDetails = array();
#$orderNo      = $_REQUEST['order_no']? $_REQUEST['order_no']: $_REQUEST['order_id'];
$order = getOrderByIncrementId($request);
if ($debug) {echo'Order Details:<pre>'; print_r($order);echo'</pre>';}
return $order;// == true
}

function getOrderByIncrementId($request) {
global $lineBreak, $tableOrderPayment, $tableOrder, $emailBody, $debug, $payment_method_array, $wpdb;
//check amount
$amount  = $request['amount'];
$_amount = isset($order_total) ? $order_total * 100 : 0;
if(!$amount || $amount < 0) {
$emailBody .= "Novalnet callback received. The requested amount ($amount) must be greater than zero.".$lineBreak.$lineBreak;
return false;
}

if (!empty($request['order_no'])){
return $request['order_no'];
}elseif(!empty($request['order_id'])){
return $request['order_id'];
}

$table_name = $wpdb->posts; 
$sql = "SELECT ID FROM `$table_name` where post_excerpt LIKE '%".$request['tid_payment']."%'";
try {
$row = $wpdb->get_results($sql);
$num_rows = $wpdb->num_rows;
if ( $num_rows ) {
$order_id = $row[0]->ID; 
$sql = "SELECT meta_value as payment_method from $wpdb->postmeta where post_id = '$order_id' and meta_key = '_payment_method'"; 
$row = $wpdb->get_results($sql);
$payment_method = $row[0]->payment_method; 
$sql = "SELECT meta_value as order_total from $wpdb->postmeta where post_id = '$order_id' and meta_key = '_order_total'"; 
$row = $wpdb->get_results($sql);
$order_total = $row[0]->order_total; 
}
} catch (Exception $e) {
$emailBody .= 'The original order not found in the shop database table (`' . $table_name . '`);';
$emailBody .= 'Reason: ' . $e->getMessage() . $lineBreak . $lineBreak;
$emailBody .= 'Query : ' . $qry . $lineBreak . $lineBreak;
return false;
}
if( empty($order_id) ){
$emailBody .= 'Novalnet callback received. Payment type is not Prepayment/Invoice/PayPal!' . "$lineBreak$lineBreak".$lineBreak;
return false;
}
if ($debug) {echo'Order Details:<pre>';
echo "Order Number:".$order_id."</br>";
echo "Order Total:".$order_total."</br>" ;
echo'</pre>';}
if(!in_array($payment_method, $payment_method_array)) {
$emailBody .= "Novalnet callback received. Payment type ($payment_method) is not Prepayment/Invoice/PayPal!$lineBreak$lineBreak";
return false;
}
return $order_id; // == true
}

function setOrderStatus($incrementId) {
global $lineBreak, $createInvoice, $emailBody, $orderStatus, $orderState, $tableOrderPayment, $addSubsequentTidToDb, $payment_method_array, $wpdb;
$tbl_or = $wpdb->postmeta; 
$sql = "SELECT pm.meta_value as order_total,p.post_excerpt from $tbl_or as pm left join $wpdb->posts as p on p.ID=pm.post_id where pm.post_id = '$incrementId' and pm.meta_key = '_order_total'"; 
$sql .= (!empty($_REQUEST['order_id']) || !empty($_REQUEST['order_no'])) ? " and p.post_excerpt LIKE '%".$_REQUEST['tid_payment']."%'" : "";
try {
$row = $wpdb->get_results($sql);
$num_rows = $wpdb->num_rows;
if ( $num_rows ) {
$order_total = $row[0]->order_total; 
$sql = "SELECT meta_value as payment_method from $wpdb->postmeta where post_id = '$incrementId' and meta_key = '_payment_method'"; 
$row = $wpdb->get_results($sql);
$payment_method = $row[0]->payment_method; 
$sql="select slug as order_status from $wpdb->terms where term_id=(select term_id from $wpdb->term_taxonomy where term_taxonomy_id=(select term_taxonomy_id from $wpdb->term_relationships where object_id='$incrementId'))";
$row = $wpdb->get_results($sql);
$order_status = $row[0]->order_status;
if(!in_array($payment_method, $payment_method_array )) {
$emailBody .= "Novalnet callback received. Payment type ($payment_method) is not Prepayment/Invoice/PayPal!$lineBreak$lineBreak";
return false;
}
} else{
$emailBody .= "Novalnet callback received. Payment type is not Prepayment/Invoice/PayPal or the request order id/transaction id is mismatch!$lineBreak$lineBreak";
return false;
}
} catch (Exception $e) {
$emailBody .= 'The original order not found in the shop database table (`' . $tbl_or . '`);';
$emailBody .= 'Reason: ' . $e->getMessage() . $lineBreak . $lineBreak;
$emailBody .= 'Query : ' . $sql . $lineBreak . $lineBreak;
return false;
}

if ($incrementId) {
if ($order_status!=$orderState){
$callback_script_text = "\nNovalnet Callback Script executed successfully. The subsequent TID: (" . $_REQUEST['tid'] . ") on " . date('Y-m-d H:i:s');
$sql="update $wpdb->term_relationships as tr, $wpdb->posts as p set tr.term_taxonomy_id=(select term_taxonomy_id from $wpdb->term_taxonomy where term_id=(select term_id from $wpdb->terms where slug='$orderState')), p.post_excerpt=CONCAT(p.post_excerpt,'$callback_script_text'),p.post_modified=NOW(),p.post_modified_gmt=NOW(),p.comment_count=p.comment_count+1 where object_id='$incrementId' and ID='$incrementId'"; 
$wpdb->query($sql);
$order_changed_message = sprintf("$callback_script_text\nOrder status changed from %s to %s.",$order_status,$orderState);
$sql="INSERT INTO `wp_comments` set `comment_post_ID`='$incrementId',`comment_date`=NOW(),`comment_date_gmt`=NOW(), `comment_content`='$order_changed_message', `comment_approved`='1', `comment_type`='order_note'";
$wpdb->query($sql);
}else {
$emailBody .= "Novalnet callback received. Callback Script executed already. Refer Order :".$incrementId;
return false;
}
} else {
$emailBody .= "Novalnet Callback received. No order for Increment-ID $incrementId found.";
return false;
}
$emailBody .= 'Novalnet Callback Script executed successfully. Payment for order id: ' . $incrementId . '. New TID: ('. $_REQUEST['tid'] . ') on ' . date('Y-m-d H:i:s');
return true;
}

function checkIP($request) {
global $lineBreak, $ipAllowed, $test, $emailBody;
if ($test) {
$ipAllowed = getRealIpAddr();
}
$callerIp = $_SERVER['REMOTE_ADDR'];
if ($ipAllowed != $callerIp) {
$emailBody .= 'Novalnet callback received. Unauthorised access from the IP [' . $callerIp . ']' . $lineBreak . $lineBreak;
$emailBody .= 'Request Params: ' . print_r($request, true);
return false;
}
return true;
}

function isPublicIP($value) {
if (!$value || count(explode('.', $value)) != 4)
return false;
return !preg_match('~^((0|10|172\.16|192\.168|169\.254|255|127\.0)\.)~', $value);
}

function getRealIpAddr() {
if (isPublicIP(@$_SERVER['HTTP_X_FORWARDED_FOR']))
return @$_SERVER['HTTP_X_FORWARDED_FOR'];
if ($iplist = explode(',', @$_SERVER['HTTP_X_FORWARDED_FOR'])) {
if (isPublicIP($iplist[0]))
return $iplist[0];
}
if (isPublicIP(@$_SERVER['HTTP_CLIENT_IP']))
return @$_SERVER['HTTP_CLIENT_IP'];
if (isPublicIP(@$_SERVER['HTTP_X_CLUSTER_CLIENT_IP']))
return @$_SERVER['HTTP_X_CLUSTER_CLIENT_IP'];
if (isPublicIP(@$_SERVER['HTTP_FORWARDED_FOR']))
return @$_SERVER['HTTP_FORWARDED_FOR'];
return @$_SERVER['REMOTE_ADDR'];
}
?>
