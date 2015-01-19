/**
 * @category   Novalnet
 * @package    Novalnet
 * @copyright  Novalnet AG. (http://www.novalnet.de)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *
 */
 jQuery(document).ready(function() {
	if(jQuery('#billing_email')){
		getCustomerEmail();
	}

	jQuery('#billing_email').on('change', function(){
		getCustomerEmail();
	});

	var ua = window.navigator.userAgent;
    var msie = ua.indexOf('MSIE ');
    var trident = ua.indexOf('Trident/');

	if (msie > 0 || trident > 0)
	{
		jQuery("#novalnet_sepa_bank_country").mouseover(function() {
	            jQuery("#novalnet_sepa_bank_country").css("width", "auto");

	    });
	    jQuery("#novalnet_sepa_bank_country").change(function() {
	            jQuery("#novalnet_sepa_bank_country").width(150);

	    });
	    jQuery("#novalnet_sepa_bank_country").blur(function() {
	            jQuery("#novalnet_sepa_bank_country").width(150);
	    });
	}
	else
	{
		jQuery("#novalnet_sepa_bank_country").width(150);
	}

	jQuery('#novalnet_sepa_iban, #novalnet_sepa_bic, #novalnet_sepa_bank_country, #novalnet_sepa_account_holder').on('change', function(){
		jQuery('#novalnet_sepa_mandate_confirm').attr('checked', false);
		jQuery('#novalnet_sepa_mandate_confirm').removeAttr("disabled");
		jQuery('#novalnet_sepa_iban_span').html('');
		jQuery('#novalnet_sepa_bic_span').html('');
	});

	jQuery('#place_order').on('click', function(evt){
		if(jQuery('#payment_method_novalnet_sepa').attr('checked')){
			if(jQuery("#novalnet_sepa_mandate_confirm").is( ":checked" ) == false){
				alert(jQuery('#nn_lang_mandate_confirm').val());
				return false;
			}
			evt.preventDefault();
			sepahashcall();
		}
	});
 });

 function sepahashrequestcall(nnrequesturl_val,process_mode) {

	if(nnrequesturl_val == '') {
		close_mandate_overlay();
		return false;
	}
	var domain = window.location.protocol;
	var nnurl = domain+"//payport.novalnet.de/sepa_iban";

	var process_data = new Array();
	process_data['data'] = nnrequesturl_val;
	process_data['url'] = nnurl;
	process_data['mode'] = process_mode;
	process_data['org_val'] = '';
	process_data['callfrom'] = "hashcall";
	document.getElementById('sepa_loader').style.display='block';
	sepa_ajax_processcall(process_data);
 }

 function getHashResult(response, input_data){
	document.getElementById('sepa_loader').style.display='none';
	if(response.hash_result == 'success'){
		if(input_data['callfrom'] == 'ibancall'){
			jQuery('#nn_sepa_iban').val(response.IBAN);
			jQuery('#nn_sepa_bic').val(response.BIC);
			if(response.IBAN != '' && response.BIC != '') {
				jQuery('#novalnet_sepa_iban_span').html('<b>IBAN:</b> '+response.IBAN);
				jQuery('#nn_sepa_overlay_iban_tr').show(60);
				jQuery('#novalnet_sepa_bic_span').html('<b>BIC:</b> '+response.BIC);
				jQuery('#nn_sepa_overlay_bic_tr').show(60);
				sepahashrequestcall(input_data['org_val'],input_data['mode']);
				return true;
			} else {
				alert(jQuery('#nn_lang_valid_account_details').val());
				jQuery('#nn_sepa_overlay_iban_tr').hide(60);
				jQuery('#nn_sepa_overlay_bic_tr').hide(60);
				close_mandate_overlay_on_cancel();
				return false;
			}
		}else if(input_data['callfrom'] == 'hashcall'){
			jQuery('#nn_sepa_hash').val(response.sepa_hash);
			jQuery('#nn_sepa_mandate_ref').val(response.mandate_ref);
			jQuery('#nn_sepa_mandate_date').val(response.mandate_date);
			if(input_data['mode'] == 'link') {
				show_mandate_overlay_second();
			} else {
				jQuery("#place_order").parents('form').submit();
			}
		}else if(input_data['callfrom'] == 'refillcall'){

			var hash_string = response.hash_string.split('&');
			//This array contains the final val()s as a key and val() format.(i.e cc_no=4200).
			var arrayResult={};
            for (var i=0,len=hash_string.length;i<len;i++) {
                var hash_result_val = hash_string[i].split('=');
				//Here we are assigning the splitted val()s to the arrayResult.
                arrayResult[hash_result_val[0]] = hash_result_val[1];
            }
            jQuery('#novalnet_sepa_account_holder').val(removeUnwantedSpecialChars(jQuery.trim(decodeURIComponent(arrayResult.account_holder))));
            jQuery('#novalnet_sepa_bank_country').val(arrayResult.bank_country);
            jQuery('#novalnet_sepa_iban').val(removeUnwantedSpecialChars(arrayResult.iban));
            if(arrayResult.bic != '123456')
				jQuery('#novalnet_sepa_bic').val(removeUnwantedSpecialChars(arrayResult.bic));

		}
	}else{
		jQuery('#novalnet_sepa_mandate_confirm').removeAttr("disabled");
		sepa_mandate_unconfirm_process();
		alert(response.hash_result);
	}
 }

 function sepa_ajax_processcall(ary_data){
	if ('XDomainRequest' in window && window.XDomainRequest !== null) {
		var xdr = new XDomainRequest(); // Use Microsoft XDR
		xdr.open('POST' , ary_data['url']);

		xdr.onload = function () {
			 getHashResult(jQuery.parseJSON(this.responseText), ary_data);
		};
		setTimeout(function () {
    			xdr.send(ary_data['data']);
  		}, 0);

	}else{
		jQuery.ajax({
			type: 'POST',
			url: ary_data['url'],
			data: ary_data['data'],
			dataType: 'json',
			success: function(data) {
				 getHashResult(data, ary_data);
			}
		});
	}
 }

 function sepaibanbiccall(nnrequesturl_val,process_mode) {
	var domain = window.location.protocol;
	var nnurl = domain+"//payport.novalnet.de/sepa_iban";

	var bank_country = "";var account_holder = "";var account_no = "";
	var bank_code = "";var nn_sepa_uniqueid = "";
	var nn_vendor = "";var nn_auth_code = "";

	if(jQuery('#novalnet_sepa_bank_country')) {bank_country = jQuery('#novalnet_sepa_bank_country').val();}
	if(jQuery('#novalnet_sepa_account_holder')) {account_holder = removeUnwantedSpecialChars(jQuery.trim(jQuery('#novalnet_sepa_account_holder').val()));}
	if(jQuery('#novalnet_sepa_iban')){account_no = removeUnwantedSpecialChars(jQuery.trim(jQuery('#novalnet_sepa_iban').val()));}
	if(jQuery('#novalnet_sepa_bic')){bank_code = removeUnwantedSpecialChars(jQuery.trim(jQuery('#novalnet_sepa_bic').val()));}
	if(jQuery('#nn_vendor')){nn_vendor = jQuery('#nn_vendor').val();}
	if(jQuery('#nn_auth_code')){nn_auth_code = jQuery('#nn_auth_code').val();}
	if(jQuery('#nn_sepa_uniqueid')){nn_sepa_uniqueid = jQuery('#nn_sepa_uniqueid').val();}
	jQuery('#nn_sepa_iban').val('');
	jQuery('#nn_sepa_bic').val('');

	if(isNaN(account_no) && isNaN(bank_code)) {
		jQuery('#novalnet_sepa_iban_span').html('');
		jQuery('#novalnet_sepa_bic_span').html('');
		sepahashrequestcall(nnrequesturl_val,process_mode);
		return false;
	}
	if(bank_code == '' && isNaN(account_no)) {
		sepahashrequestcall(nnrequesturl_val,process_mode);
		return false;
	}

	if(nn_vendor == '' || nn_auth_code == '') {alert(jQuery('#nn_lang_valid_merchant_credentials').val());return false;}

	if(bank_country == '' || account_holder == '' || account_no == '' || bank_code == '' || nn_vendor == '' || nn_auth_code == '' || nn_sepa_uniqueid == '') {
		return false;
	}

	var nnurl_val = "account_holder="+account_holder+"&bank_account="+account_no+"&bank_code="+bank_code+"&vendor_id="+nn_vendor+"&vendor_authcode="+nn_auth_code+"&bank_country="+bank_country+"&unique_id="+nn_sepa_uniqueid+"&get_iban_bic=1";
	var process_data = new Array();
	process_data['data'] = nnurl_val;
	process_data['url'] = nnurl;
	process_data['mode'] = process_mode;
	process_data['org_val'] = nnrequesturl_val;
	process_data['callfrom'] = "ibancall";
	document.getElementById('sepa_loader').style.display='block';
	sepa_ajax_processcall(process_data);
 }

 function sepahashcall(process_mode) {

    if(jQuery('#novalnet_sepa_mandate_confirm').is(':checked') != true) {
        close_mandate_overlay_on_cancel();
        return true;
    }
	jQuery('#novalnet_sepa_mandate_confirm').attr('disabled', 'disabled');
	var bank_country = "";var account_holder = "";var account_no = "";
	var iban = "";var bic = "";var bank_code = "";var nn_sepa_uniqueid = "";
	var nn_vendor = "";var nn_auth_code = "";var mandate_confirm = 0;

	if(jQuery('#novalnet_sepa_bank_country')) {bank_country = jQuery('#novalnet_sepa_bank_country').val();}
	if(jQuery('#novalnet_sepa_account_holder')) {account_holder = removeUnwantedSpecialChars(jQuery.trim(jQuery('#novalnet_sepa_account_holder').val()));}
	if(jQuery('#novalnet_sepa_iban')){iban = removeUnwantedSpecialChars(jQuery.trim(jQuery('#novalnet_sepa_iban').val()));}
	if(jQuery('#novalnet_sepa_bic')){bic = removeUnwantedSpecialChars(jQuery.trim(jQuery('#novalnet_sepa_bic').val()));}
	if(jQuery('#nn_vendor')){nn_vendor = getNumbersOnly(jQuery('#nn_vendor').val());}
	if(jQuery('#nn_auth_code')){nn_auth_code = jQuery('#nn_auth_code').val();}
	if(jQuery('#nn_sepa_uniqueid')){nn_sepa_uniqueid = jQuery('#nn_sepa_uniqueid').val();}
	if(nn_vendor == '' || nn_auth_code == '') {alert(jQuery('#nn_lang_valid_merchant_credentials').val()); sepa_mandate_unconfirm_process(); return false;}

	if(bank_country == '' || account_holder == '' || iban == '' || nn_vendor == '' || nn_auth_code == '' || nn_sepa_uniqueid == '') {
		alert(jQuery('#nn_lang_valid_account_details').val()); sepa_mandate_unconfirm_process(); return false;
	}
	if(bank_country != 'DE' && bic == '') {
		alert(jQuery('#nn_lang_valid_account_details').val()); sepa_mandate_unconfirm_process(); return false;
	} else if(bank_country == 'DE' && bic == '' && !isNaN(iban)) {
		alert(jQuery('#nn_lang_valid_account_details').val()); sepa_mandate_unconfirm_process(); return false;
	}
	if(bank_country == 'DE' && bic == '' && isNaN(iban)) {
		bic = '123456';
	}

	var nnurl_val = "account_holder="+account_holder+"&bank_account=&bank_code=&vendor_id="+nn_vendor+"&vendor_authcode="+nn_auth_code+"&bank_country="+bank_country+"&unique_id="+nn_sepa_uniqueid+"&sepa_data_approved=1&mandate_data_req=1&iban="+iban+"&bic="+bic;

	document.getElementById('sepa_loader').style.display='block';
	sepaibanbiccall(nnurl_val,process_mode);
 }

 function getCustomerEmail() {
	var tmp_email;
	if(jQuery('#billing_email'))
		tmp_email = jQuery('#billing_email').val();

	var nnurl = jQuery("#nn_plugin_url").val() + "/wp-content/plugins/woocommerce-novalnet-gateway/novalnet-functions.php";

	jQuery.ajax({
		url: nnurl,
		type: "post",
		data: {'usr_email' : tmp_email}
	});
 }

 function removeUnwantedSpecialChars(input_val) {
	if( input_val != 'undefined' || input_val != ''){
		return input_val.replace(/[\/\\|\]\[|#,+@'()$~%.~`":;*?<>!^{}=_-]/g,'');
	}
 }

 function getNumbersOnly(input_val) {
	if( input_val != 'undefined' || input_val != ''){
		return input_val.replace(/[^0-9]/g,'');
	}
 }

 function normalizeDate(input) {
	if(input != 'undefined' && input != '') {
		var parts = input.split('-');
		return (parts[2] < 10 ? '0' : '') + parseInt(parts[2]) + '.'
			+ (parts[1] < 10 ? '0' : '') + parseInt(parts[1]) + '.'
			+ parseInt(parts[0]);
	}
 }

 function close_mandate_overlay_on_cancel() {
	if(jQuery('#novalnet_sepa_mandate_confirm').is(':checked')){
		jQuery('#novalnet_sepa_mandate_confirm').attr(':checked', false);
	}

	jQuery('#novalnet_sepa_mandate_confirm').removeAttr("disabled");

	sepa_mandate_unconfirm_process();
	jQuery('#sepa_mandate_overlay_block').hide(60);
	jQuery('.bgCover').css( {display:'none'} );
	return true;
 }

 function confirm_mandate_overlay() {
	close_mandate_overlay();
 }

 //Close confirmation overlay when clicking the close button image
 function close_mandate_overlay() {
	jQuery('#novalnet_sepa_mandate_confirm').removeAttr('disabled');
	jQuery('#sepa_mandate_overlay_block').hide(60);
	jQuery('.bgCover').css( {display:'none'} );
	return true;
 }

 //Open overlay for getting confirmation when mandate = 0
 function show_mandate_overlay() {
	if(jQuery('#novalnet_sepa_bic').val() == '' || jQuery('#novalnet_sepa_iban').val() == '' || jQuery('#novalnet_sepa_account_holder').val() == '') {
		alert(('#nn_lang_valid_account_details').val());
		return false;
	}
	if(jQuery('#nn_sepa_id').val() == 37) {
		sepahashcall();
	}
 }


 function sepa_mandate_unconfirm_process() {

	jQuery('#nn_sepa_hash').val('');
	jQuery('#nn_sepa_mandate_ref').val('');
	jQuery('#nn_sepa_mandate_date').val('');
	jQuery('#novalnet_sepa_iban_span').html('');
	jQuery('#novalnet_sepa_bic_span').html('');
	jQuery('#novalnet_sepa_mandate_confirm').attr("checked",false);
	jQuery('#novalnet_sepa_mandate_confirm').removeAttr('disabled');
 }

 function show_mandate_overlay_second() {
	if((jQuery('#novalnet_sepa_bic').val() == '' && jQuery('#novalnet_sepa_bank_country').val() != 'DE')|| jQuery('#novalnet_sepa_iban').val() == '' || jQuery('#novalnet_sepa_account_holder').val() == '' || jQuery('#nn_sepa_mandate_date').val() == '' || jQuery('#nn_sepa_mandate_ref').val() == '') {
		alert(jQuery('#nn_lang_valid_account_details').val());
		return false;
	}
	jQuery('.bgCover').css({
		display:'block',
		width: jQuery(document).width(),
		height: jQuery(document).height()
	});
	jQuery('.bgCover').css({opacity:0}).animate( {opacity:0.5, backgroundColor:'#878787'} );
	var template_iban = ''; var template_bic = '';

	if(isNaN(removeUnwantedSpecialChars(jQuery('#novalnet_sepa_iban').val()))) {
		template_iban = jQuery('#novalnet_sepa_iban').val();
	} else {
		template_iban = jQuery('#nn_sepa_iban').val();
	}
	if(isNaN(removeUnwantedSpecialChars(jQuery('#novalnet_sepa_bic').val()))) {
		template_bic = jQuery('#novalnet_sepa_bic').val();
	} else {
		template_bic = jQuery('#nn_sepa_bic').val();
	}

	jQuery('#sepa_overlay_iban_span').html(removeUnwantedSpecialChars(template_iban));
	if(template_bic != '') {
		jQuery('#sepa_overlay_bic_span').html(removeUnwantedSpecialChars(template_bic));
		jQuery('#nn_sepa_overlay_bic_tr').show(60);
	} else {
		jQuery('#sepa_overlay_bic_span').html('');
		jQuery('#nn_sepa_overlay_bic_tr').hide(60);
	}
	jQuery('#sepa_overlay_payee_span').html('Novalnet AG');
	jQuery('#sepa_overlay_creditoridentificationnumber_span').html('DE53ZZZ00000004253');
	jQuery('#sepa_overlay_mandatedate_span').html(normalizeDate(jQuery('#nn_sepa_mandate_date').val()));
	jQuery('#sepa_overlay_mandatereference_span').html(jQuery('#nn_sepa_mandate_ref').val());

	jQuery('.sepa_overlay_enduser_name_span').html(removeUnwantedSpecialChars(jQuery('#novalnet_sepa_account_holder').val()));

	if(jQuery('#novalnet_sepa_customer_info'))
		customer_info = jQuery('#novalnet_sepa_customer_info').val();

	if ( customer_info == '' ) {

		if(jQuery('#billing_company').val() == '' || jQuery('#billing_company').val() == null){
			jQuery('#overlay_company').css('display', 'none');
		}else{
			jQuery('#sepa_overlay_enduser_company_span').html(jQuery('#billing_company').val());
		}

		if(jQuery('#billing_address_1')){
			jQuery('#sepa_overlay_enduser_address_span').html(jQuery('#billing_address_1').val());
		}
		if(jQuery('#billing_postcode')){
			jQuery('#sepa_overlay_enduser_zip_span').html(jQuery('#billing_postcode').val());
		}
		if(jQuery('#billing_city')){
			jQuery('.sepa_overlay_enduser_city_span').html(jQuery('#billing_city').val());
		}
		if(jQuery('#novalnet_sepa_bank_country')){
			jQuery('#sepa_overlay_enduser_country_span').html(jQuery('#novalnet_sepa_bank_country').val());
		}
		if(jQuery('#billing_email')){
			jQuery('#sepa_overlay_enduser_email_span').html(jQuery('#billing_email').val());
		}
	} else {

		var customer_info_string = customer_info.split('&');
		var arrayResult={};
		for (var i=0,len=customer_info_string.length;i<len;i++) {
			var hash_result_val = customer_info_string[i].split('=');
			arrayResult[hash_result_val[0]] = hash_result_val[1];
		}

		email 	= decodeURIComponent(arrayResult.email);
		address = decodeURIComponent(arrayResult.address);
		city 	= decodeURIComponent(arrayResult.city);
		zip 	= decodeURIComponent(arrayResult.zip);
		company = decodeURIComponent(arrayResult.company);

		if( company == '' || company == null){
			jQuery('#overlay_company').css('display', 'none');
		}else{
			jQuery('#sepa_overlay_enduser_company_span').html(company);
		}

		jQuery('#sepa_overlay_enduser_address_span').html(address);
		jQuery('#sepa_overlay_enduser_zip_span').html(zip);
		jQuery('.sepa_overlay_enduser_city_span').html(city);
		jQuery('#sepa_overlay_enduser_country_span').html(jQuery('#novalnet_sepa_bank_country').val());
		jQuery('#sepa_overlay_enduser_email_span').html(email);
	}
	jQuery('#sepa_mandate_overlay_block_first').css({ display:'none', position:'fixed' });
	jQuery('#sepa_mandate_overlay_block').css({ display:'block', position:'fixed' });
	return true;
 }

 // AJAX call for refill sepa form elements
 function separefillformcall() {
	var refillpanhash = '';
	if(jQuery('#nn_sepa_input_panhash')){refillpanhash = jQuery('#nn_sepa_input_panhash').val();}

	if(refillpanhash == '' || refillpanhash == 'undefined'){	return false;	}

	var domain = window.location.protocol;
	var nnurl = domain+"//payport.novalnet.de/sepa_iban";

	var nn_vendor = ""; var nn_auth_code = ""; var nn_uniqueid = "";

	if(jQuery('#nn_vendor')){nn_vendor = jQuery('#nn_vendor').val();}
	if(jQuery('#nn_auth_code')){nn_auth_code = jQuery('#nn_auth_code').val();}
	if(jQuery('#nn_sepa_uniqueid')){nn_uniqueid = jQuery('#nn_sepa_uniqueid').val();}

	if(nn_vendor == '' || nn_auth_code == '' || nn_uniqueid == '') {return false;}

	var nnurl_val = "vendor_id="+nn_vendor+"&vendor_authcode="+nn_auth_code+"&unique_id="+nn_uniqueid+"&sepa_data_approved=1&mandate_data_req=1&sepa_hash="+refillpanhash;

	var process_data = new Array();
	process_data['data'] = nnurl_val;
	process_data['url'] = nnurl;
	process_data['callfrom'] = "refillcall";
	document.getElementById('sepa_loader').style.display='block';
	sepa_ajax_processcall(process_data);

 }
 separefillformcall();
