<?php

 if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
 }

/**
 * Novalnet Instant bank transfer Payment
 *
 * This gateway is used for real time processing of German bank data of customers.
 *
 * Copyright (c) Novalnet
 *
 * This script is only free to the use for merchants of Novalnet. If
 * you have found this script useful a small recommendation as well as a
 * comment on merchant form would be greatly appreciated.
 *
 * @class 		WC_Gateway_Novalnet_Instantbank
 * @extends		Novalnet_Payment_Gateway
 * @package		Novalnet/Classes/Payment
 * @author 		Novalnet
 * @located at 	/includes/gateways
 */

 class WC_Gateway_Novalnet_Instantbank extends Novalnet_Payment_Gateway {
	var $id = 'novalnet_instantbank';
 }

 $obj = new WC_Gateway_Novalnet_Instantbank();

?>