#################################################################
#                                                               #
#  READ-ME INSTRUCTION                                          #
#                                                               #
#  Credit Card (3DSecure and non 3DSecure): Visa, Mastercard, 	#
#  Amex, JCB, CUP. Debitcard: Maestro  							#
#  Direct debit SEPA											#
#  Prepayment, Invoice.											#
#  Online Transfer: iDEAL, eps, PayPal,  sofortüberweisung.		#      					
#                                                               #
#  These modules are programmed in high standard and supports	#
#  PCI DSS Standard and the Trusted shops Standard used for 	#
#  real time processing of transactions through Novalnet		#
#                                                               #
#  Released under the GNU General Public License                #
#                                                               #
#  This free contribution made by request.                      #
#  If you have found this script usefull a small recommendation #
#  as well as a comment on merchant form would be greatly       #
#  appreciated.                                                 #
#                                                               #
#  Copyright (c) Novalnet AG   		                       		#
#                                                               #
#################################################################
#																#
#  SPECIFICATION DETAILS										#
#					   	   	   									#
#  Created	     				- Novalnet AG  					#
#																#
#  CMS(wordpress) Version       - 4.1	                        #
#					   	   										#
#  Shop (woocommerce) Version   - 2.2.10        				#
#				  	   											#
#  Novalnet Version  			- 2.0.0		        			#
#					   	   										#
#  Last Updated	     			- 16-01-2015	        		#
#					   											#
#  Categories	     			- Payment & Gateways			#
#  																#
#  Compatibile CMS version  	- 4.1   						#
#																#
#  Compatibile Shop version 	- 2.1.0 - 2.2.10				#
#	        													#
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

      If you use Ubuntu/Debian, you can try the following commands:
        sudo apt-get install curl php5-curl php5-mcrypt
        apachectl restart (restart the Webserver)


Step 2:
========

a) To install Novalnet payment module,

	Kindly refer "IG-wordpress_v_4.1_woocommerce_v_2.1.0-2.2.10_novalnet_v_2.0.0_en.pdf".

b) To install NovalnetAG Callback Script,

  Please Copy the 'callback_novalnet2woocommerce.php' file and place into the " Wordpress <Root_Directory>/ ".

  Example: /var/www/wordpress/


Empty cache (browser cache) or cache folders if there are any.
-------------------------------------------------------------


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
