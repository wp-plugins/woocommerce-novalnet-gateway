#################################################################
#                                                               #
#  INSTALLATION GUIDE                                           #
#                                                               #
#  Direct Debit (German/Austria),                               #
#  Credit Card,	Credit Card 3D Secure,                          #
#  Prepayment, Invoice, Online Transfer, iDEAL			#
#  PayPal, Telephone Payment			                #
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
#  CMS(wordpress) Version         	- 3.7.1	                #
#					   	   		#
#  Shop (woocommerce) Version   	- 2.0.20	        #
#					   	   		#
#  Novalnet Version  			- 1.1.1		        #
#					   	   		#
#  Last Updated	     			- 26-11-2013		#	
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

a) To install NovalnetAG payment module, 

	kindly refer "IG-wordpress_v_3.3-3.7.1_woocommerce_v_1.6.6_v_2.0.0-2.0.20_novalnet_v_1.1.1_en.pdf". (please download it from here svn.wp-plugins.org/woocommerce-novalnet-gateway/assets/IG-wordpress_v_3.3-3.7.1_woocommerce_v_1.6.6_v_2.0.0-2.0.20_novalnet_v_1.1.1_en.pdf)

b) To install NovalnetAG Callback Script,
 
  Please Copy the 'callback_novalnet2wordpresswoocommerce.php' file and place into the " Wordpress <Root_Directory>/ ". 
  Example: /var/www/wordpress/ 


Step 3: 
========

If you wish to add line breaks for Order Transaction detail in notification emails, please follow the below procedures

----------------------------
I. For woocommerce version >= 2.0.0
----------------------------

a. please open the file 'class-wc-email-customer-completed-order.php' under 'wp-content/plugins/woocommerce/classes/emails/'.

  i. kindly search the following function

	function get_content_html() {
		
and add the below codes after the " ob_start(); " line in the above search result.
		
	// code to add
		$this->object->customer_note = wpautop($this->object->customer_note);
	// end

 ii. kindly search the following function

	function get_content_plain() {

and add the below codes after the " ob_start(); " line in the above search result.

	// code to add
		$this->object->customer_note = wpautop($this->object->customer_note);
	// end

b. please open the file 'class-wc-email-customer-invoice.php' under 'wp-content/plugins/woocommerce/classes/emails/'.

  i. kindly search the following function

	function get_content_html() {
		
and add the below codes after the " ob_start(); " line in the above search result. 

	// code to add
		$this->object->customer_note = wpautop($this->object->customer_note);
	// end
 ii. kindly search the following function

	function get_content_plain() {

and add the below codes after the " ob_start(); " line in the above search result. 
	
	// code to add
		$this->object->customer_note = wpautop($this->object->customer_note);
	// end

c. please open the file 'class-wc-email-customer-processing-order.php' under 'wp-content/plugins/woocommerce/classes/emails/'.

  i. kindly search the following function

	function get_content_html() {

and add the below codes after the " ob_start(); " line in the above search result. 

	// code to add
		$this->object->customer_note = wpautop($this->object->customer_note);
	// end

 ii. kindly search the following function

	function get_content_plain() {
		
and add the below codes after the " ob_start(); " line in the above search result. 

	// code to add
		$this->object->customer_note = wpautop($this->object->customer_note);
	// end

d. please open the file 'class-wc-email-new-order.php' under 'wp-content/plugins/woocommerce/classes/emails/'.

  i. kindly search the following function

	function get_content_html() {
		
and add the below codes after the " ob_start(); " line in the above search result. 

	// code to add 
		$this->object->customer_note = wpautop($this->object->customer_note);
	// end
 
ii. kindly search the following function

	function get_content_plain() {

and add the below codes after the " ob_start(); " line in the above search result.

	// code to add
		$this->object->customer_note = wpautop($this->object->customer_note);
	// end


---------------------------------------------------------------------------------------------------------------------------------------------------------------

----------------------------
II. For woocommerce version < 2.0.0
----------------------------


please open the file 'class-wc-email.php' under 'wp-content/plugins/woocommerce/classes/'.

a. kindly search the following code 


		// Get mail template
		woocommerce_get_template('emails/admin-new-order.php', array(

and add the following code before the above search lines.

		if(!in_array($this->payment_method,array('novalnet_elv_at','novalnet_elv_de','novalnet_invoice','novalnet_prepayment','novalnet_cc'))) 
		$order->customer_note = nl2br($order->customer_note); 

b. kindly search the following code 


		// Get mail template
		woocommerce_get_template('emails/customer-processing-order.php', array(

and add the following code before the above search lines.

		if(!in_array($this->payment_method,array('novalnet_elv_at','novalnet_elv_de','novalnet_invoice','novalnet_prepayment','novalnet_cc')))
			$order->customer_note = nl2br($order->customer_note);

c. kindly search the following code 


		// Get mail template
		woocommerce_get_template('emails/customer-completed-order.php', array(

and add the following code before the above search lines.

		if(!in_array($this->payment_method,array('novalnet_elv_at','novalnet_elv_de','novalnet_invoice','novalnet_prepayment','novalnet_cc'))) 
			$order->customer_note = nl2br($order->customer_note);  

d. kindly search the following code 


		// Get mail template
		woocommerce_get_template('emails/customer-invoice.php', array(

and add the following code before the above search lines.

		if(!in_array($this->payment_method,array('novalnet_elv_at','novalnet_elv_de','novalnet_invoice','novalnet_prepayment','novalnet_cc')))
			$order->customer_note = nl2br($order->customer_note);  

---------------------------------------------------------------------------------------------------------------------------------------------------------------
 

Empty cache (browser cache) or cache folders if there are any.

-------------------------------------------------------------

Note: If you use Prepayment and/or Per Invoice then contact us for more details.

On Any Technical Problems, please contact sales@novalnet.de / 0049-89-923 068 320.

-------------------------------------------------------------------------------

Note: In Telephone payment method the guest user has to enter his/her address details in checkout form for both first call and second call.This is not necessary for registered user. [shop default flow]

-------------------------------------------------------------------------------

Note:  If you wish to display Credit Card form in your specified template , kindly search the following codes on novalnetpayments.php 
under'wp-content/plugin/woocommerce-novalnet-gateway/' and fill-out the respective values in below mentioned HTML tags

// code to add css values

<input type="hidden" id="original_customstyle_css" value="" />

<input type="hidden" id="original_customstyle_cssval" value="" />

// code to add css values

for example :-

<input type="hidden" id="original_customstyle_css" value="body~~~test~~~input" /><input type="hidden" id="original_customstyle_cssval" value="color:#222222;font:11px/14px Arial,Verdana,sans-serif;~~~color:red;clear:both;~~~color:red;" />

-------------------------------------------------------------------------------

Note:  If you wish to display Amex logo in Credit Card checkout form, please open the file "novalnetpayments.php" under "wp-content/plugin/woocommerce-novalnet-gateway/" and kindly search the line  $icon_html = '';
and the add the follow codes

// code to add for displaying Amex logo

if ($this->novalnet_payment_method == 'novalnet_cc') {
			$icon_cc_amex_html = '<a href="' . (strtolower($this->language) == 'de' ? 'https://www.novalnet.de' : 'http://www.novalnet.com') . '" alt="' . __('novalnet.com', 'woocommerce-novalnetpayment') . '" target="_new"><img height ="25" src="' . site_url() . '/wp-content/plugins/woocommerce-novalnet-gateway/includes/creditcard_amex_small.jpg' .'"alt="' . $this->method_title . '" title="'.$this->title.'" /></a>';
			return $icon_cc_amex_html;
		    }
		    
// code to add for displaying Amex logo

-------------------------------------------------------------------------------

Important Notice for Online Transfer (Sofortüberweisung):

If you use real transaction data (bank code, bank account number, etc.) real transactions will be performed, even though the test mode is on/activated!
-------------------------------------------------------------------------------

