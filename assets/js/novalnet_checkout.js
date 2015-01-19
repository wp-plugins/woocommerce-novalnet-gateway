/**
 * @category   Novalnet
 * @package    Novalnet
 * @copyright  Novalnet AG. (http://www.novalnet.de)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *
 */

 jQuery(document).ready(function() {
	// to fetch firstname & lastname from the checkout
	jQuery('#billing_first_name, #billing_last_name, #billing_phone, #billing_email').on('change', function(){
		getUserInfo('novalnet_cc');
		getUserInfo('novalnet_sepa');
	});

	jQuery(document).on('click','input[type=radio]', function(){
		if(jQuery(this).attr('name') == 'payment_method'){
			if (jQuery(this).val() == 'novalnet_cc'){
				getUserInfo('novalnet_cc');
			}
			if (jQuery(this).val() == 'novalnet_sepa'){
				getUserInfo('novalnet_sepa');
			}
		}
	});
 });

 function getUserInfo( payment_method ){
	var fname = lname = customer_info = '';
	if(jQuery('#'+payment_method+'_customer_info'))
		customer_info = jQuery('#'+payment_method+'_customer_info').val();

	if(customer_info == ''){
		get_data_from_checkout(payment_method);
	}else if(payment_method != 'novalnet_invoice'){
		get_data_from_db(customer_info, payment_method);
	}
 }

 function get_data_from_checkout(payment_method){

	if(jQuery('#billing_first_name'))
		fname = jQuery('#billing_first_name').val();
	if(jQuery('#billing_last_name'))
		lname = jQuery('#billing_last_name').val();

	if(jQuery('#'+payment_method+'_holder'))
		jQuery('#'+payment_method+'_holder').val(fname + ' ' + lname);
	if(jQuery('#'+payment_method+'_account_holder'))
		jQuery('#'+payment_method+'_account_holder').val(fname + ' ' + lname);

 }

 function get_data_from_db(customer_info, payment_method){
	var customer_info_string = customer_info.split('&');
	var arrayResult={};
	for (var i=0,len=customer_info_string.length;i<len;i++) {
		var hash_result_val = customer_info_string[i].split('=');
		arrayResult[hash_result_val[0]] = hash_result_val[1];
	}

	fname = decodeURIComponent(arrayResult.first_name);
	lname = decodeURIComponent(arrayResult.last_name);
	email = decodeURIComponent(arrayResult.email);
	tel = decodeURIComponent(arrayResult.tel);

	if(jQuery('#'+payment_method+'_holder'))
		jQuery('#'+payment_method+'_holder').val(fname + ' ' + lname);
	if(jQuery('#'+payment_method+'_account_holder'))
		jQuery('#'+payment_method+'_account_holder').val(fname + ' ' + lname);
 }