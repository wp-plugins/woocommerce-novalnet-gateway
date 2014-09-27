#################################################################
#                                                               #
#  INSTALLATION GUIDE                                           #
#                                                               #
#  Credit Card (3DSecure and non 3DSecure): Visa, Mastercard, 	#
#  Amex, JCB, CUP. Debitcard: Maestro  							#
#  Direct debit SEPA											#
#  Prepayment, Invoice, Online Transfer, iDEAL, eps, PayPal,    #
#  sofortüberweisung, Telephone Payment.      			#
#                                                               #
#  These modules are programmed in high standard and supports	#
#  PCI DSS Standard and the Trustshops Standard	used for 	#
#  real time processing of transactions through Novalnet	#
#                                                               #
#  Released under the GNU General Public License                #
#                                                               #
#  This free contribution made by request.                      #
#  If you have found this script usefull a small recommendation #
#  as well as a comment on merchant form would be greatly       #
#  appreciated.                                                 #
#                                                               #
#  Copyright (c) Novalnet AG   		                       	#
#                                                               #
#################################################################
#								#
#  SPECIFICATION DETAILS					#
#					   	   	   	#
#  Created	     		- Novalnet AG  			#
#								#
#  CMS(wordpress) Version       - 4.0	                        #
#					   	   		#
#  Shop (woocommerce) Version   - 2.1.12        		#
#				  	   			#
#  Novalnet Version  		- 1.1.7		        	#
#					   	   		#
#  Last Updated	     		- 23-09-2014	        	#
#					   			#
#  Categories	     		- Payment & Gateways		#
#  																#
#  Compatibile CMS version  - 3.5 - 4.0                  		#
#																#
#  Compatibile Shop version - 2.0.20 - 2.2.4					#
#	        						#
#################################################################

IMPORTANT: 

	1. Please enter/activate your Server IP address on Novalnet Administration portal under the menu "Project", for transaction API access on Void, Capture, Refund and Transaction status enquiry from your shop. 
	
	2. The files freewarelicenseagreement.txt and testdata.txt are parts of this readme file.
Woocommerce is an extension for Wordpress. Therefore a working Wordpress system is a must.

How to install:
---------------

Step 1:
========

You have to install php modules: curl and php-curl in your Webserver.
      Refer to the following website for installation instructions:
        http://curl.haxx.se/docs/install.html.

      If you use Ubantu/Debian, you can try the following commands:
        sudo apt-get install curl php5-curl php5-mcrypt
        apachectl restart (restart the Webserver)


Step 2:
========

a) To install NovalnetAG payment module,

	kindly refer "IG-wordpress_v_3.5-4.0_woocommerce_v_2.0.20-2.2.4_novalnet_v_1.1.7_en.pdf".

b) To install NovalnetAG Callback Script,

  Please Copy the 'callback_novalnet2woocommerce.php' file and place into the " Wordpress <Root_Directory>/ ".

  Example: /var/www/wordpress/


Empty cache (browser cache) or cache folders if there are any.
-------------------------------------------------------------

Note: In Telephone payment method the guest user has to enter his/her address details in checkout form for both first call and second call. This is not necessary for registered user. [shop default flow]

-------------------------------------------------------------------------------

Note: I
=======

If you wish to display Novalnet Credit Card and Novalnet Direct Debit SEPA form in your specified template , Please open novalnet_css_link.php file under'wp-content/plugins/woocommerce-novalnet-gateway/'

i) Novalnet Credit Card
-----------------------

Kindly search the following codes and fill-out the respective values in below mentioned HTML tags

// code to add css values

define('NOVALNET_CC_CUSTOM_CSS','');	## enter here your css value between the single quotation as per your style
define('NOVALNET_CC_CUSTOM_CSS_VALUE','');	## enter here your css value between the single quotation as per your style

// code to add css values

for example :-

define('NOVALNET_CC_CUSTOM_CSS', 'body~~~input, select~~~td~~~#novalnetCc_cc_type, #novalnetCc_expiration, #novalnetCc_expiration_yr~~~#novalnetCc_cc_type~~~#novalnetCc_expiration~~~#novalnetCc_expiration_yr~~~td');
define('NOVALNET_CC_CUSTOM_CSS_STYLE', 'font-family:Open Sans,Helvetica,Arial,sans-serif;font-size:12px;~~~border: 1px solid #CCCCCC; border-radius: 3px; padding: 0.428571rem;height:17px !important;width:180px;~~~color:#5E5E5E;~~~height:34px !important;~~~width:196px !important;~~~width:107px !important;~~~width:80px;~~~padding:0.428571rem !important;');


ii) Novalnet Direct Debit SEPA
-----------------------------

Kindly search the following codes and fill-out the respective values in below mentioned HTML tags

// code to add css values

define('NOVALNET_SEPA_CUSTOM_CSS','');	## enter here your css value between the single quotation as per your style
define('NOVALNET_SEPA_CUSTOM_CSS_VALUE','');	## enter here your css value between the single quotation as per your style

// code to add css values

for example :-

define('NOVALNET_SEPA_CUSTOM_CSS', 'body~~~input, select~~~#novalnet_sepa_country~~~input.mandate_confirm_btn');
define('NOVALNET_SEPA_CUSTOM_CSS_STYLE', 'font-family:Open Sans,Helvetica,Arial,sans-serif;font-size:12px;~~~border: 1px solid #CCCCCC; border-radius: 3px; padding: 0.428571rem;height:17px !important;width:180px;~~~height:34px !important;width:196px !important;~~~height:32px !important;');
-------------------------------------------------------------------------------

Note: II
========

If client wish to changes the title and description of Novalnet Payment Methods, they have to add corresponding language text in language file "/wp-content/plugins/woocommerce/i18n/languages/woocommerce.pot". After that they need to add same language depentent(payment or description text) in woocommerce-[language_name].po file.

-------------------------------------------------------------------------------

Important Notice for Online Transfer (Sofortüberweisung):

1. If you use real transaction data (bank code, bank account number, etc.) real transactions will be performed, even though the test mode is on/activated!

-------------------------------------------------------------------------------

CALLBACK SCRIPT: this is necessary for keeping your database/system actual and synchrone with the Novalnet's transaction status.
--------------------------------------------------------------------------------------------------------------------------------

Your system will be notified through Novalnet system(asynchrone) about each transaction and its status.

For example, if you use Novalnet's "Invoice/Prepayment/PayPal" payment methods then on recieval of the credit entry, your system will be notified through the Novalnet system and your system can automatically change the status of the order: from "pending" to "paid".

Please use the "callback_novalnet2woocommerce.php" provided in this payment package. Please follow the instructions in the "Callbackscript_testing_procedure.txt" file. You will find more details in the "callback_novalnet2woocommerce.php" script itself.

Step to update callback script url in Novalnet Administration area for callback script execution :

After logging into Novalnet Administration area, please choose your particular project navigate to "PROJECT" menu, then select appropriate "Project" and navigate to "Project Overview" tab and then update callback script url in "Vendor script URL" field.
Ex: https://woocommerce.novalnet.de/callback_novalnet2woocommerce.php

Please contact us on sales@novalnet.de for activating other payment methods
===============================================================================

OUR CONTACT DETAILS / YOU CAN REACH US ON:

Tel    : +49 (0)89 923 068 320

Web    : www.novalnet.com
E-mail : sales@novalnet.de
===============================================================================
