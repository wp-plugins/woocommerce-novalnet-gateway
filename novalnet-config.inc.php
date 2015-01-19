<?php
/**
 * Novalnet Constants Definition
 *
 * Copyright (c) 2015 Novalnet AG <https://www.novalnet.de>
 *
 * Released under the GNU General Public License. This free
 * contribution made by request.If you have found this script
 * usefull a small recommendation as well as a comment on
 * merchant form would be greatly appreciated.
 *
 * This file is used to define the datas in the payment files.
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

/* Novalnet Gateway version  */
define( 'NOVALNET_GATEWAY_VERSION','2.0.0' );


/* Novalnet Payment urls */
define( 'PAYGATE_URL', 'payport.novalnet.de/paygate.jsp' );                    # paygate url
define( 'SOFORT_PAYPORT_URL', 'payport.novalnet.de/online_transfer_payport' ); # online transfer payport url
define( 'PCI_PAYPORT_URL', 'payport.novalnet.de/global_pci_payport' );         # global pci payport url
define( 'PAYPAL_PAYPORT_URL', 'payport.novalnet.de/paypal_payport' );          # paypal payport url
define( 'INFOPORT_URL', 'payport.novalnet.de/nn_infoport.xml' );               # infoport url

/* Novalnet Payment keys */
define( 'PAYMENT_KEY_BT', 33);	# Online Bank Transfer
define( 'PAYMENT_KEY_CC', 6);	# Credit Card family
define( 'PAYMENT_KEY_ID', 49);	# iDEAL
define( 'PAYMENT_KEY_IP', 27);	# Invoice & Prepayment
define( 'PAYMENT_KEY_PP', 34);	# PayPal
define( 'PAYMENT_KEY_SEPA', 37);	# SEPA

/* Novalnet payment status code */
define( 'PENDING_CODE', 90);     # Paypal pending code
define( 'COMPLETE_CODE', 100);   # Order status complete
define( 'VOID_CODE', 103);       # Order status Void

/* Novalnet Payment Method Class */
define( 'NOVALNET_BASE_CLASS', 'WC_Novalnet_Payment_Gateway' );
define( 'NOVALNET_BT_CLASS', 'WC_Gateway_Novalnet_Banktransfer' );
define( 'NOVALNET_CC_CLASS', 'WC_Gateway_Novalnet_Cc' );
define( 'NOVALNET_ID_CLASS', 'WC_Gateway_Novalnet_Ideal' );
define( 'NOVALNET_IN_CLASS', 'WC_Gateway_Novalnet_Invoice' );
define( 'NOVALNET_PP_CLASS', 'WC_Gateway_Novalnet_Paypal' );
define( 'NOVALNET_PT_CLASS', 'WC_Gateway_Novalnet_Prepayment' );
define( 'NOVALNET_SEPA_CLASS', 'WC_Gateway_Novalnet_Sepa' );

/* Novalnet Payment Methods */
define( 'NOVALNET_BT', 'novalnet_banktransfer' );
define( 'NOVALNET_CC', 'novalnet_cc' );
define( 'NOVALNET_ID', 'novalnet_ideal' );
define( 'NOVALNET_IN', 'novalnet_invoice' );
define( 'NOVALNET_PP', 'novalnet_paypal' );
define( 'NOVALNET_PT', 'novalnet_prepayment' );
define( 'NOVALNET_SEPA', 'novalnet_sepa' );

/** Novalnet Config Details */
define( 'NN_CONFIG', 'novalnet_config' );
define( 'NN_FUNCS', 'nn_functions' );
define( 'NOVALNET_TRANSACTION_CONFIRM_META', 'novalnet_transaction_confirm_process' );
define( 'NOVALNET_TRANSACTION_REFUND_META', 'novalnet_transaction_refund' );

?>