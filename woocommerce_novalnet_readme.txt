/**
 * README INSTRUCTIONS
 *
 * Direct Debit SEPA, Credit Card (3DSecure and non 3DSecure):
 * Visa, Mastercard, Amex, JCB, CUP and Maestro,
 * Prepayment, Invoice,
 * Online Transfer : eps, iDEAL and Instant Bank Transfer
 * Wallet system : PayPal
 *
 * These modules are programmed in high standard and supports
 * PCI DSS standard and the trusted shops standard used for
 * real time processing of transactions through Novalnet
 *
 * Released under the GNU General Public License
 *
 * This free contribution made by request.
 * If you have found this script useful a small recommendation
 * as well as a comment on merchant form would be greatly
 * appreciated.
 *
 * Copyright (c) Novalnet
 *
 ****************************************************************
 *
 * SPECIFICATION DETAILS
 *
 * Created                           - Novalnet AG
 *
 * CMS (Wordpress) Version           - 3.7.x-4.x
 *
 * Woocommerce subscription version  - 1.4.x-1.5.x
 *
 * Shop (WooCommerce) Version        - 2.1.x-2.3.x
 *
 * Novalnet Version                  - 10.1.0
 *
 * Last Updated                      - 10-08-2015
 *
 * Stability                         - Stable
 *
 * Categories                        - Payment Gateways
 *
 **/


IMPORTANT:

    1. Please enter/activate your Server IP address on Novalnet Administration portal under the menu "Project", for transaction API access on Void, Capture, Refund, Amount/Due Date update and Transaction status enquiry from your shop.

    2. "freewarelicenseagreement.txt" is part of this readme file.

WooCommerce is an extension for Wordpress. Therefore a working Wordpress system is a must.

How to install:
---------------

Step 1:
========

You have to install php modules: php-curl in your Webserver.
      Refer to the following website for installation instructions:
        http://curl.haxx.se/docs/install.html.

      If you use Ubuntu/Debian, you can try the following commands:
        sudo apt-get install curl php5-curl php5-mcrypt
        apachectl restart (restart the Webserver)


Step 2:
========

To install Novalnet payment module, kindly refer "IG-wordpress_v_3.7.x-4.x_woocommerce_v_2.1.x-2.3.x_novalnet_v_10.1.0.pdf".


Step 3:
=======

To display transaction details in mail while creating recurring order, kindly follow the below mentioned details,

search the below mentioned code in the following file path <root>/wp-content/plugins/woocommerce/includes/abstracts/abstract-wc-order.php

  $new_order_status = $order_needs_processing ? 'processing' :'completed';

and then comment the lines which has mentioned below,

  $new_order_status = $order_needs_processing ? 'processing' :'completed';
  $new_order_status = apply_filters('woocommerce_payment_complete_order_status', $new_order_status,$this->id);
  $this->update_status( $new_order_status );

-----
Note:
-----

1. if w3 total cache plugin is activated then kindly uncheck the Enable option in the backend via Performance -> Minify -> JS minify settings.

2. After installing the Novalnet module, kindly save the Novalnet Global Configuration and payment settings.

3. Clear cache (browser cache) or cache folders if there are any.

------------------------------------------------------------------------
AFFILIATE PROCESS: Follow the below necessary step to set up the process
------------------------------------------------------------------------
Set the shop website URL with the vendor id:

E.g.: https://woocommerce.novalnet.de/?nn_aff_id=Vendor-ID

---------------
Important note:
---------------
Kindly, contact sales@novalnet.de / tel. +49 (089) 923068320 to get the test data to process the payment.


CALLBACK SCRIPT: this is necessary for keeping your database/system actual and synchronize with the Novalnet transaction status.
--------------------------------------------------------------------------------------------------------------------------------

Your system will be notified through Novalnet system (asynchronous) about each transaction and its status.

For example, if you use Novalnet's "Invoice/Prepayment/PayPal" payment methods then on receival of the credit entry, your system will be notified through the Novalnet system and your system can automatically change the status of the order: from "Order Completion Status/Order status for the pending payment" to "Callback order status/Order Completion Status".

Please use the "novalnet-callback-handler.php" provided in this payment package. Please follow the instructions in the "callback_script_testing_procedure.txt" file. You will find more details in the "novalnet-callback-handler.php" script itself.

Step to update callback script url in Novalnet Administration area for callback script execution :

After logging into Novalnet Administration area, please choose your particular project navigate to "PROJECT" menu, then select appropriate "Project" and navigate to "Project Overview" tab and then update callback script url in "Vendor script URL" field.

E.g.: https://woocommerce.novalnet.de?wc_api=novalnet_callback

Please contact us on sales@novalnet.de for activating other payment methods
===============================================================================

OUR CONTACT DETAILS / YOU CAN REACH US ON:

Tel.   : +49 (0)89 923 068 321
Web    : www.novalnet.com
E-mail : support@novalnet.de
===============================================================================
