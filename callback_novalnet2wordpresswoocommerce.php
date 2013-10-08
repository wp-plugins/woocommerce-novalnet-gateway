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
#  Version : 1.1.0                                      #
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
 * @category   Novalnet
 * @package    Novalnet
 * @version    1.1.0
 * @copyright  Copyright (c)  Novalnet AG. (https://www.novalnet.de)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @notice     1. This script must be placed in Wordpress woocommerce root folder
 *                to avoid rewrite rules (mod_rewrite)
 * 			   2. You have to adapt the value of all the variables
 *                commented with 'adapt ...'
 *             3. Set $test/$debug to false for live system
 */
ob_start();

if (isset($_SESSION))
    session_start();

ini_set('display_errors', true);

/**
 * BEGIN LOAD WORDPRESS
 */
function find_wordpress_base_path() {

    $nn_location = dirname(__FILE__);

    do {
        //	check for other files
        if (file_exists($nn_location . "/wp-config.php")) {
            return $nn_location;
        }
    } while ($nn_location = realpath("$dir/.."));

    return null;
}

define('BASE_PATH', find_wordpress_base_path() . "/");
require(BASE_PATH . 'wp-load.php');
/**
 * END LOAD WORDPRESS
 */
/* Novalnet callback script starts */

//false|true; adapt: set to false for go-live
$debug = false;

//false|true; adapt: set to false for go-live
$test = false;

$lineBreak = empty($_SERVER['HTTP_HOST']) ? PHP_EOL : '<br />';

//whether to add the new tid to db; adapt if necessary
$addSubsequentTidToDb = true;

$aPaymentTypes = array('novalnet_invoice' => 'INVOICE_CREDIT',
    'novalnet_prepayment' => 'INVOICE_CREDIT',
    'novalnet_paypal' => 'PAYPAL');

$payment_method_array = array('novalnet_prepayment', 'novalnet_invoice', 'novalnet_paypal');

//Note: Indicates Payment accepted.
$orderState = 'completed';

//Security Setting; only this IP is allowed for call back script (Novalnet IP, is a fixed value, DO NOT CHANGE!!!!!)
$ipAllowed = '195.143.189.210';

//Reporting Email Addresses Settings (manditory;adapt for your need)
$shopInfo = 'Wordpress Woocommerce ' . $lineBreak;

// email host (adapt)
$mailHost = '';

// email port (adapt)
$mailPort = 25;

// sender email addr., manditory, adapt it
$emailFromAddr = '';

//recipient email addr., manditory, adapt it
$emailToAddr = '';

// email subject (adapt if necessary;)
$emailSubject = 'Novalnet Callback Script Access Report';

//Email text, adapt
$emailBody = '';

// Sender name (adapt)
$emailFromName = '';

// Recipient name (adapt)
$emailToName = '';

//Parameters Settings
$hParamsRequired = array();
if (isset($_REQUEST['payment_type']) && $_REQUEST['payment_type'] == 'INVOICE_CREDIT') {
    $hParamsRequired = array(
        'vendor_id' => '',
        'tid' => '',
        'payment_type' => '',
        'status' => '',
        'amount' => '',
        'tid_payment' => '');
} elseif (isset($_REQUEST['payment_type']) && $_REQUEST['payment_type'] == 'PAYPAL') {
    $hParamsRequired = array(
        'vendor_id' => '',
        'payment_type' => '',
        'status' => '',
        'amount' => '',
        'tid' => '');
}

if (in_array('INVOICE_CREDIT', $aPaymentTypes) and isset($_REQUEST['payment_type']) and $_REQUEST['payment_type'] == 'INVOICE_CREDIT')
    $hParamsRequired['tid_payment'] = '';

ksort($hParamsRequired);

//Test Data Settings
if ($test) {

    $emailFromName = "Novalnet"; // Sender name, adapt
    $emailToName = "Novalnet"; // Recipient name, adapt
    $emailFromAddr = 'test@novalnet.de'; //manditory for test; adapt
    $emailToAddr = 'test@novalnet.de'; //manditory for test; adapt
    $emailSubject = $emailSubject . ' - TEST'; //adapt
    
} #Endif

################### Main Prog. ##########################
try {

//Check Params
    if (checkIP($_REQUEST)) {

        if (checkParams($_REQUEST)) {

            //Get Order ID and Set New Order Status
            if ($orderIncrementId = BasicValidation($_REQUEST))
                setOrderStatus($orderIncrementId);  //and send error mails if any 
                
        }	#Endif
        
    }	#Endif

    if (!$emailBody) {

        $emailBody .= 'Novalnet Callback Script called for StoreId Parameters: ' . print_r($_POST, true) . $lineBreak;
        $emailBody .= 'Novalnet callback succ. ' . $lineBreak;
        $emailBody .= 'Params: ' . print_r($_REQUEST, true) . $lineBreak;
    } #Endif
} catch (Exception $e) {
    $emailBody .= "Exception catched: $lineBreak\$e:" . $e->getMessage() . $lineBreak;
}

if ($emailBody) {

    if (!sendEmailWordpresswoocommerce($emailBody)) {

        if ($debug) {
            echo "Mailing failed!" . $lineBreak;
            echo "This mail text should be sent: " . $lineBreak;
            echo $emailBody;
            
        } #Endif
    
    } #Endif
    
} #Endif

exit();

############## Sub Routines #####################

/**
 * send email contents
 */
function sendEmailWordpresswoocommerce($emailBody) {

    global $lineBreak, $debug, $test, $emailFromAddr, $emailToAddr, $emailFromName, $emailToName, $emailSubject, $shopInfo, $mailHost, $mailPort;
    $emailBodyT = str_replace('<br />', PHP_EOL, $emailBody);

//Send Email
    ini_set('SMTP', $mailHost);
    ini_set('smtp_port', $mailPort);

    header('Content-Type: text/html; charset=iso-8859-1');
    $headers = 'From: ' . $emailFromAddr . "\r\n";

    try {
        if ($debug)
            echo __FUNCTION__ . ': Sending Email suceeded!' . $lineBreak;
        $sendmail = mail($emailToAddr, $emailSubject, $emailBodyT, $headers);
    } catch (Exception $e) {
        if ($debug)
            echo 'Email sending failed: ' . $e->getMessage();
        return false;
    }
    if ($debug)
        echo 'This text has been sent:' . $lineBreak . $emailBody;
    return true;
}	// End  sendEmailWordpresswoocommerce()

/**
 * performs initial parameter check
 */
function checkParams($request) {

    global $lineBreak, $hParamsRequired, $emailBody, $aPaymentTypes;
    $error = false;
    $emailBody = '';

    if (!$request) {
        $emailBody .= 'Novalnet callback received. No params passed over!' . $lineBreak;
        return false;
    }

    if (!isset($request['payment_type'])) {
        $emailBody .= "Novalnet callback received. But Param payment_type missing$lineBreak";
        return false;
    }

    if (isset($request['payment_type']) && $request['payment_type'] == null) {
        $emailBody .= "Required param (payment_type) missing!$lineBreak";
        return false;
    }

    if (!in_array($request['payment_type'], $aPaymentTypes)) {
        $emailBody .= "Novalnet callback received. Payment type [{$request['payment_type']}] is mismatched!$lineBreak";
        return false;
    }

    if ($hParamsRequired) {

        foreach ($hParamsRequired as $k => $v) {

            if (empty($request[$k])) {
                $error = true;
                $emailBody .= 'Required param (' . $k . ') missing!' . $lineBreak;
            } #Endif
        } #Endforach

        if ($error)
            return false;
    } #Endif

    if (!isset($request['status']) or $request['status'] != 100) {

        $emailBody .= 'Novalnet callback received. Status [' . $request['status'] . '] is not valid: Only 100 is allowed.' . "$lineBreak$lineBreak" . $lineBreak;
        return false;
    }

    if ($_REQUEST['payment_type'] == 'PAYPAL') {

        if (strlen($request['tid']) != 17) {
            $emailBody .= 'Novalnet callback received. Invalid TID [' . $request['tid'] . '] for Order.' . "$lineBreak$lineBreak" . $lineBreak;
            return false;
        } #Endif
    } #Endif
    else {

        if (strlen($request['tid_payment']) != 17) {

            $emailBody .= 'Novalnet callback received. Invalid TID [' . $request['tid_payment'] . '] for Order.' . "$lineBreak$lineBreak" . $lineBreak;
            return false;
        } #Endif
    } #Endelse

    if ($_REQUEST['payment_type'] == 'INVOICE_CREDIT') {

        if (strlen($request['tid']) != 17) {

            $emailBody .= 'Novalnet callback received. New TID is not valid.' . "$lineBreak$lineBreak" . $lineBreak;
            
            return false;
            
        }	#Endif  

        return true;
        
    } #Endif

    return true;
    
}	// End  checkParams()

/**
 * performs basic validation
 */
function BasicValidation($request) {

    global $lineBreak, $tableOrderPayment, $tableOrder, $emailBody, $debug;
    $orderDetails = array();
    #$orderNo      = $_REQUEST['order_no']? $_REQUEST['order_no']: $_REQUEST['order_id'];

    $order = getOrderByIncrementId($request);

    if ($debug) {
        echo'Order Details:<pre>';
        print_r($order);
        echo'</pre>';
    }

    return $order; // == true
}	// End  BasicValidation()

/**
 * get order id using Transaction Id
 */
function getOrderByIncrementId($request) {

    global $lineBreak, $tableOrderPayment, $tableOrder, $emailBody, $debug, $payment_method_array, $wpdb, $wp_rewrite;

    //check amount
    $amount = $request['amount'];
    $_amount = isset($order_total) ? $order_total * 100 : 0;

    if (!$amount || $amount < 0) {
        $emailBody .= "Novalnet callback received. The requested amount ($amount) must be greater than zero." . $lineBreak . $lineBreak;
        return false;
    }

    if (!empty($request['order_no']))
        return $request['order_no'];

    elseif (!empty($request['order_id']))
        return $request['order_id'];

    $table_name = $wpdb->posts;

    if (($_REQUEST['payment_type'] == 'INVOICE_CREDIT'))
        $sql = "SELECT ID FROM `$table_name` where post_excerpt LIKE '%" . $request['tid_payment'] . "%'";

    elseif ($_REQUEST['payment_type'] == 'PAYPAL')
        $sql = "SELECT ID FROM `$table_name` where post_excerpt LIKE '%" . $request['tid'] . "%'";

    try {
        $row = $wpdb->get_results($sql);
        $num_rows = $wpdb->num_rows;

        if ($num_rows) {

            $order_id = $row[0]->ID;
            $sql = "SELECT meta_value as payment_method from $wpdb->postmeta where post_id = '$order_id' and meta_key = '_payment_method'";
            $row = $wpdb->get_results($sql);
            $payment_method = $row[0]->payment_method;
            $sql = "SELECT meta_value as order_total from $wpdb->postmeta where post_id = '$order_id' and meta_key = '_order_total'";
            $row = $wpdb->get_results($sql);
            $order_total = $row[0]->order_total;
        } #Endif
    } catch (Exception $e) {
        $emailBody .= 'The original order not found in the shop database table (`' . $table_name . '`);';
        $emailBody .= 'Reason: ' . $e->getMessage() . $lineBreak . $lineBreak;
        $emailBody .= 'Query : ' . $qry . $lineBreak . $lineBreak;
        return false;
    }

    if (empty($order_id)) {

        $emailBody .= 'Novalnet callback received. Payment type is mismatched!' . "$lineBreak$lineBreak" . $lineBreak;
        return false;
    } #Endif

    if ($debug) {

        echo'Order Details:<pre>';
        echo "Order Number:" . $order_id . "</br>";
        echo "Order Total:" . $order_total . "</br>";
        echo'</pre>';
    } #Endif

    if (!in_array($payment_method, $payment_method_array)) {

        $emailBody .= "Novalnet callback received. Payment type [$payment_method] is mismatched!$lineBreak$lineBreak";
        return false;
    } #Endif

    return $order_id;
}	// End  getOrderByIncrementId()

/**
 * set order status as completed
 */
function setOrderStatus($incrementId) {

    global $lineBreak, $createInvoice, $emailBody, $orderStatus, $orderState, $tableOrderPayment, $addSubsequentTidToDb, $payment_method_array, $wpdb, $aPaymentTypes, $wp_rewrite;
    $tbl_or = $wpdb->postmeta;

    $sql = "SELECT pm.meta_value as order_total,p.post_excerpt from $tbl_or as pm left join $wpdb->posts as p on p.ID=pm.post_id where pm.post_id = '$incrementId' and pm.meta_key = '_order_total'";

    if ($_REQUEST['payment_type'] == 'INVOICE_CREDIT')
        $sql .= (!empty($_REQUEST['order_id']) || !empty($_REQUEST['order_no'])) ? " and p.post_excerpt LIKE '%" . $_REQUEST['tid_payment'] . "%'" : "";
    elseif ($_REQUEST['payment_type'] == 'PAYPAL')
        $sql .= (!empty($_REQUEST['order_id']) || !empty($_REQUEST['order_no'])) ? " and p.post_excerpt LIKE '%" . $_REQUEST['tid'] . "%'" : "";

    try {

        $row = $wpdb->get_results($sql);
        $num_rows = $wpdb->num_rows;

        if ($num_rows) {

            $order_total = $row[0]->order_total;
            $sql = "SELECT meta_value as payment_method from $wpdb->postmeta where post_id = '$incrementId' and meta_key = '_payment_method'";
            $row = $wpdb->get_results($sql);
            $payment_method = $row[0]->payment_method;
            $sql = "select slug as order_status from $wpdb->terms where term_id=(select term_id from $wpdb->term_taxonomy where term_taxonomy_id=(select term_taxonomy_id from $wpdb->term_relationships where object_id='$incrementId'))";
            $row = $wpdb->get_results($sql);
            $order_status = $row[0]->order_status;

            if (in_array($payment_method, $payment_method_array) && $_REQUEST['payment_type'] != $aPaymentTypes[$payment_method]) {

                $emailBody .= "Novalnet callback received. Payment type " . ($_REQUEST['payment_type']) . " is not " . $aPaymentTypes[$payment_method] . $lineBreak;
                return false;
            } #Endif

            if (!in_array($payment_method, $payment_method_array)) {

                $emailBody .= "Novalnet callback received. Payment type [$payment_method] is not Prepayment/Invoice/PayPal!$lineBreak$lineBreak";
                return false;
            } #Endif
        } #Endif
        else {
            // $emailBody .= "Novalnet callback received. Payment type is not Prepayment/Invoice/PayPal or the request order id/transaction id is mismatch!";
            $emailBody .= "Novalnet callback received. Order no is not valid ";
            return false;
        } #Endelse
    } catch (Exception $e) {
        $emailBody .= 'The original order not found in the shop database table (`' . $tbl_or . '`);';
        $emailBody .= 'Reason: ' . $e->getMessage() . $lineBreak . $lineBreak;
        $emailBody .= 'Query : ' . $sql . $lineBreak . $lineBreak;
        return false;
    }

    if ($incrementId) {

        if ($order_status != $orderState) {

            if ($_REQUEST['payment_type'] == 'INVOICE_CREDIT')
                $callback_script_text = "\nNovalnet Callback Script executed successfully. The subsequent TID: (" . $_REQUEST['tid'] . ") on " . date('Y-m-d H:i:s');
            else
                $callback_script_text = "\nNovalnet Callback Script executed successfully on " . date('Y-m-d H:i:s');

            $sql = "update $wpdb->term_relationships as tr, $wpdb->posts as p set tr.term_taxonomy_id=(select term_taxonomy_id from $wpdb->term_taxonomy where term_id=(select term_id from $wpdb->terms where slug='$orderState')), p.post_excerpt=CONCAT(p.post_excerpt,'$callback_script_text'),p.post_modified=NOW(),p.post_modified_gmt=NOW(),p.comment_count=p.comment_count+1 where object_id='$incrementId' and ID='$incrementId'";
            
            $wpdb->query($sql);
            
            $order_changed_message = sprintf("$callback_script_text\nOrder status changed from %s to %s.", $order_status, $orderState);

            $sql = "INSERT INTO `wp_comments` set `comment_post_ID`='$incrementId',`comment_date`=NOW(),`comment_date_gmt`=NOW(), `comment_content`='$order_changed_message', `comment_approved`='1', `comment_type`='order_note'";
            
            $wpdb->query($sql);
            
        }	#Endif 

        else {
			
            $emailBody .= "Novalnet callback received. Callback Script executed already. Refer Order :" . $incrementId;
            return false;
            
        }	#Endelse
        
    }	#Endif
    
    else {
		
        $emailBody .= "Novalnet Callback received. No order for Increment-ID $incrementId found.";
        return false;
        
    }	#Endelse

    if ($_REQUEST['payment_type'] == 'INVOICE_CREDIT')
        $emailBody .= 'Novalnet Callback Script executed successfully. Payment for order id: ' . $incrementId . '. New TID: (' . $_REQUEST['tid'] . ') on ' . date('Y-m-d H:i:s');
        
    else
        $emailBody .= 'Novalnet Callback Script executed successfully. Payment for order id: ' . $incrementId . ' on ' . date('Y-m-d H:i:s');
        
    return true;
    
}	// End  setOrderStatus()

/**
 * Checks IP address
 */
function checkIP($request) {
	
    global $lineBreak, $ipAllowed, $test, $emailBody;

    if ($test)
        $ipAllowed = getRealIpAddr();
        
    $callerIp = $_SERVER['REMOTE_ADDR'];

    if ($ipAllowed != $callerIp) {
		
        $emailBody .= 'Novalnet callback received. Unauthorised access from the IP [' . $callerIp . ']' . $lineBreak . $lineBreak;
        $emailBody .= 'Request Params: ' . print_r($request, true);
        return false;
        
    }

    return true;
    
}	// End  checkIP()

/**
 * Validate current user's IP address
 */
function isPublicIP($value) {

    if (!$value || count(explode('.', $value)) != 4)
        return false;
        
    return !preg_match('~^((0|10|172\.16|192\.168|169\.254|255|127\.0)\.)~', $value);
    
}	// End  isPublicIP()

/**
 * Get real Ip Address for current User
 */
function getRealIpAddr() {

    if (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && $this->isPublicIP($_SERVER['HTTP_X_FORWARDED_FOR']))
        return $_SERVER['HTTP_X_FORWARDED_FOR'];

    if (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && $iplist = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])) {
        if ($this->isPublicIP($iplist[0]))
            return $iplist[0];
    } #Endif

    if (isset($_SERVER['HTTP_CLIENT_IP']) && $this->isPublicIP($_SERVER['HTTP_CLIENT_IP']))
        return $_SERVER['HTTP_CLIENT_IP'];

    if (isset($_SERVER['HTTP_X_CLUSTER_CLIENT_IP']) && $this->isPublicIP($_SERVER['HTTP_X_CLUSTER_CLIENT_IP']))
        return $_SERVER['HTTP_X_CLUSTER_CLIENT_IP'];

    if (isset($_SERVER['HTTP_FORWARDED_FOR']) && $this->isPublicIP($_SERVER['HTTP_FORWARDED_FOR']))
        return $_SERVER['HTTP_FORWARDED_FOR'];

    return $_SERVER['REMOTE_ADDR'];
    
}	// End  getRealIpAddr()

ob_end_flush();
?>
