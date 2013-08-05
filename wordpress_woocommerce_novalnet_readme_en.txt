#################################################################
#                                                               #
#  INSTALLATION GUIDE                                           #
#                                                               #
#  Direct Debit (German/Austria),                               #
#  Credit Card,	Credit Card 3D Secure,                          #
#  Prepayment, Invoice, Online Transfer, iDEAL			#
#  PCI Standard (Credit Card/Austria/German)                    #
#  PayPal, Telephone Payment				        #
#  								#
#                                                               #
#  These modules are used for real time processing of           #
#  transaction data                                             #
#                                                               #
#  Released under the GNU General Public License                #
#                                                               #
#  This free contribution made by request.                      #
#  If you have found this script usefull a small recommendation #
#  as well as a comment on merchant form would be greatly       #
#  appreciated.                                                 #
#                                                               #
#  				                                #
#  Copyright (c)  Novalnet AG          		                #
#                                                               #
#################################################################
#					   	                #
#  SPECIFICATION DETAILS		   	   		#
#					   	   		#
#  Created	     			- Novalnet AG         	#
#								#
#  CMS(wordpress) Version         	- 3.5.2	                #
#					   	   		#
#  Shop (woocommerce) Version   	- 2.0.13	        #
#					   	   		#
#  Novalnet Version  			- 1.0.4		        #
#					   	   		#
#  Last Updated	     			- 03rd August 2013 	#
#					   	   		#
#  Categories	     			- Payment & Gateways  	#
#					   	   		#
#################################################################


IMPORTANT: The files freewarelicenseagreement.txt and testdata.txt are parts of this readme file.

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

To install NovalnetAG payment module, kindly refer "wordpress_woocommerce_novalnet_readme_en.txt".


Step 3: 
========

----------------------------
I. For woocommerce < 2.0.0
----------------------------


If you wish to display tid details on order email, please open the file 'class-wc-email.php' under 'wp-content/plugins/woocommerce/classes/'.

a. kindly search the following code 


		// Get mail template
		woocommerce_get_template('emails/admin-new-order.php', array(

and add the following code before the above searched lines.

		if(!in_array($this->payment_method,array('novalnet_elv_at','novalnet_elv_de','novalnet_invoice','novalnet_prepayment','novalnet_cc'))) $order->customer_note.="\n".@$GLOBALS['novalnet_comments'];
		$order->customer_note = nl2br($order->customer_note); 

b. kindly search the following code 


		// Get mail template
		woocommerce_get_template('emails/customer-processing-order.php', array(

and add the following code before the above searched lines.

		if(!in_array($this->payment_method,array('novalnet_elv_at','novalnet_elv_de','novalnet_invoice','novalnet_prepayment','novalnet_cc'))) $order->customer_note.="\n".@$GLOBALS['novalnet_comments'];
		$order->customer_note = nl2br($order->customer_note);

c. kindly search the following code 


		// Get mail template
		woocommerce_get_template('emails/customer-completed-order.php', array(

and add the following code before the above searched lines.

		if(!in_array($this->payment_method,array('novalnet_elv_at','novalnet_elv_de','novalnet_invoice','novalnet_prepayment','novalnet_cc'))) $order->customer_note.="\n".@$GLOBALS['novalnet_comments'];
		$order->customer_note = nl2br($order->customer_note);  

d. kindly search the following code 


		// Get mail template
		woocommerce_get_template('emails/customer-invoice.php', array(

and add the following code before the above searched lines.

		if(!in_array($this->payment_method,array('novalnet_elv_at','novalnet_elv_de','novalnet_invoice','novalnet_prepayment','novalnet_cc'))) $order->customer_note.="\n".@$GLOBALS['novalnet_comments'];
		$order->customer_note = nl2br($order->customer_note);  

---------------------------------------------------------------------------------------------------------------------------------------------------------------

----------------------------
II. For woocommerce >= 2.0.0
----------------------------

If you wish to display tid details on order email, 

a. please open the file 'class-wc-email-customer-completed-order.php' under 'wp-content/plugins/woocommerce/classes/emails/'.

  i. kindly search the following code

	function get_content_html() {
		ob_start();

and add the following code after the above searched lines.

		$this->object->customer_note.="\n".@$GLOBALS['novalnet_comments'];		
		$this->object->customer_note = nl2br($this->object->customer_note);

 ii. kindly search the following code

	function get_content_plain() {
		ob_start();

and add the following code after the above searched lines.

		$this->object->customer_note.="\n".@$GLOBALS['novalnet_comments'];		
		$this->object->customer_note = nl2br($this->object->customer_note);


b. please open the file 'class-wc-email-customer-invoice.php' under 'wp-content/plugins/woocommerce/classes/emails/'.

  i. kindly search the following code

	function get_content_html() {
		ob_start();

and add the following code after the above searched lines.

		$this->object->customer_note.="\n".@$GLOBALS['novalnet_comments'];		
		$this->object->customer_note = nl2br($this->object->customer_note);

 ii. kindly search the following code

	function get_content_plain() {
		ob_start();

and add the following code after the above searched lines.

		$this->object->customer_note.="\n".@$GLOBALS['novalnet_comments'];		
		$this->object->customer_note = nl2br($this->object->customer_note);


c. please open the file 'class-wc-email-customer-processing-order.php' under 'wp-content/plugins/woocommerce/classes/emails/'.

  i. kindly search the following code

	function get_content_html() {
		ob_start();

and add the following code after the above searched lines.

		$this->object->customer_note.="\n".@$GLOBALS['novalnet_comments'];		
		$this->object->customer_note = nl2br($this->object->customer_note);

 ii. kindly search the following code

	function get_content_plain() {
		ob_start();

and add the following code after the above searched lines.

		$this->object->customer_note.="\n".@$GLOBALS['novalnet_comments'];		
		$this->object->customer_note = nl2br($this->object->customer_note);

d. please open the file 'class-wc-email-new-order.php' under 'wp-content/plugins/woocommerce/classes/emails/'.

  i. kindly search the following code

	function get_content_html() {
		ob_start();

and add the following code after the above searched lines.

		$this->object->customer_note.="\n".@$GLOBALS['novalnet_comments'];		
		$this->object->customer_note = nl2br($this->object->customer_note);

 ii. kindly search the following code

	function get_content_plain() {
		ob_start();

and add the following code after the above searched lines.

		$this->object->customer_note.="\n".@$GLOBALS['novalnet_comments'];
		$this->object->customer_note = nl2br($this->object->customer_note);

---------------------------------------------------------------------------------------------------------------------------------------------------------------


Step 4:    (Common for all woocommerce versions)
=================================================


If you wish to display tid details on front end order history page, 

a. kindly search the following code on order-details.php under 'wp-content/plugins/woocommerce/templates/order/'

if ($order->billing_phone) echo '<dt>'.__( 'Telephone:', 'woocommerce' ).'</dt><dd>'.$order->billing_phone.'</dd>';

and add the following code after the above searched lines.

if ( substr(get_bloginfo('language'), 0, 2) == 'de') { echo('<dt>'.'Transaktions Informationen'.': </dt><dd>'.nl2br($order->customer_note).'</dd>'); }
else { echo('<dt>'.'Transaction Information'.': </dt><dd>'.nl2br($order->customer_note).'</dd>'); }

--------------------------------------------------------------------------------------------------------------------------------------------------------------------
 

Empty cache (browser cache) or cache folders if there are any.

-------------------------------------------------------------

Note: If you use Prepayment and/or Per Invoice then contact us for more details.

On Any Technical Problems, please contact sales@novalnet.de / 0049-89-923 068 320.

-------------------------------------------------------------------------------

Note: In Telephone payment method the guest user has to enter his/her address details in checkout form for both first call and second call.This is not necessary for registered user. [shop default flow]

-------------------------------------------------------------------------------

Note:  If you wish to display credit card form in your specified template , kindly search the following codes on novalnetpayments.php 
under'wp-content/plugin/woocommerce-novalnet-gateway/' and fill-out the respective values in below mentioned HTML tags

// code to add css values

<input type="hidden" id="original_customstyle_css" value="" />

<input type="hidden" id="original_customstyle_cssval" value="" />

// code to add css values

for example :-

<input type="hidden" id="original_customstyle_css" value="body~~~test~~~input" /><input type="hidden" id="original_customstyle_cssval" value="color:#222222;font:11px/14px Arial,Verdana,sans-serif;~~~color:red;clear:both;~~~color:red;" />

-------------------------------------------------------------------------------


Important Notice for Online Transfer (Sofortüberweisung):

If you use real transaction data (bank code, bank account number, ect.) real transactions will be performed, even though the test mode is on/activated!
-------------------------------------------------------------------------------

