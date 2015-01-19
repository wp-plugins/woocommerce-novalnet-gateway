/**
 * @category   Novalnet
 * @package    Novalnet
 * @copyright  Novalnet AG. (http://www.novalnet.de)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *
 */

 jQuery(document).ready(function() {
	jQuery('#place_order').click(function (evt){
		if(jQuery('#payment_method_novalnet_cc').attr('checked')){
			evt.preventDefault();
			cchashcall();
		}
	});
 });

 function cchashcall() {
	var cc_type = "";var cc_holder = "";var cc_no = "";
	var cc_exp_month = "";var cc_exp_year = "";var cc_cvc = "";
	var nn_vendor = "";var nn_auth_code = "";var nn_cc_uniqueid = "";
	if(jQuery('#novalnet_cc_type')) {cc_type = jQuery('#novalnet_cc_type').val();}
	if(jQuery('#novalnet_cc_holder')) {cc_holder = removeUnwantedSpecialChars(jQuery.trim(jQuery('#novalnet_cc_holder').val()));}
	if(jQuery('#novalnet_cc_no')){cc_no = getNumbersOnly(jQuery.trim(jQuery('#novalnet_cc_no').val()));}
	if(jQuery('#novalnet_cc_exp_month')){cc_exp_month = jQuery('#novalnet_cc_exp_month').val();}
	if(jQuery('#novalnet_cc_exp_year')){cc_exp_year = jQuery('#novalnet_cc_exp_year').val();}
	if(jQuery('#novalnet_cc_cvc')){cc_cvc = getNumbersOnly(jQuery.trim(jQuery('#novalnet_cc_cvc').val()));}
	if(jQuery('#nn_vendor')){nn_vendor = jQuery('#nn_vendor').val();}
	if(jQuery('#nn_auth_code')){nn_auth_code = jQuery('#nn_auth_code').val();}
	if(jQuery('#nn_cc_uniqueid')){nn_cc_uniqueid = jQuery('#nn_cc_uniqueid').val();}
	if(nn_vendor == '' || nn_auth_code == '') {alert(jQuery('#nn_merchant_valid_error_ccmessage').val());return false;}
	var currentDateVal = new Date();
	if(cc_type == '' || cc_holder == '' || cc_no == '' || cc_exp_month == '' || cc_exp_year == '' || cc_cvc == '') {
		alert(jQuery('#nn_cc_valid_error_ccmessage').val()); return false;
	} else if(cc_exp_year == currentDateVal.getFullYear() && cc_exp_month < (currentDateVal.getMonth()+1)) {
		alert(jQuery('#nn_cc_valid_error_ccmessage').val()); return false;
	}
	var nnurl_val = "noval_cc_exp_month="+cc_exp_month+"&noval_cc_exp_year="+cc_exp_year+"&noval_cc_holder="+cc_holder+"&noval_cc_no="+cc_no+"&noval_cc_type="+cc_type+"&unique_id="+nn_cc_uniqueid+"&vendor_authcode="+nn_auth_code+"&vendor_id="+nn_vendor;
	document.getElementById('cc_loader').style.display='block';
	cc_ajax_processcall(nnurl_val, "cchash" );
 }

 function removeUnwantedSpecialChars(input_val) {
	if ( input_val != 'undefined' || input_val != '') {
		return input_val.replace(/[\/\\|\]\[|#,+@'()$~%.~`":;*?<>!^{}=_-]/g,'');
	}
 }

 function getNumbersOnly(input_val) {
	if( input_val != 'undefined' || input_val != ''){
		return input_val.replace(/[^0-9]/g,'');
	}
 }

 function isNumberKey(evt)
{
	var charCode = (evt.which) ? evt.which : evt.keyCode
    if (String.fromCharCode(evt.which) == '.' || String.fromCharCode(evt.which) == "'"
    || String.fromCharCode(evt.which) =='#' || String.fromCharCode(evt.which) =='t') return false;

    if((charCode == 35 || charCode == 36 ||charCode == 37
      || charCode == 39||charCode == 46) && evt.shiftKey == false) {
        return true;
    }
    else if((evt.ctrlKey == true && charCode == 114)|| String.fromCharCode(evt.which) == ' ') {
      return true;
    }
    else if(charCode > 31 && (charCode < 48 || charCode > 57)) {
        return false;
    }else{
    return true;
    }

  return true;
}

 function cc_ajax_processcall(input_data, callfrom){
	var domain = window.location.protocol;
	var nnurl = domain+"//payport.novalnet.de/payport_cc_pci";
	if ('XDomainRequest' in window && window.XDomainRequest !== null) {
		var xdr = new XDomainRequest(); // Use Microsoft XDR
		xdr.open('POST' , nnurl);
		xdr.onload = function () {
			 getCCHashResult(jQuery.parseJSON(this.responseText), callfrom);
		};
		xdr.send(input_data);
	}else{
		jQuery.ajax({
			type: 'POST',
			url: nnurl,
			data: input_data,
			success: function(data) {
				getCCHashResult(data, callfrom);
			}
		});
	}
 }

 function getCCHashResult(response, callfrom){
	document.getElementById('cc_loader').style.display='none';
	if(response.hash_result == 'success'){
		if(callfrom == 'cchash'){
			jQuery('#nn_cc_hash').val(response.pan_hash);
			jQuery("#place_order").parents('form').submit();
		}else if(callfrom == 'ccrefill'){
            var hash_string = response.hash_string.split('&');
			var arrayResult={};
            for (var i=0,len=hash_string.length;i<len;i++) {
                var hash_result_val = hash_string[i].split('=');
                arrayResult[hash_result_val[0]] = hash_result_val[1];
            }
            jQuery('#novalnet_cc_holder').val(removeUnwantedSpecialChars(jQuery.trim(decodeURIComponent(arrayResult.cc_holder))));
            jQuery('#novalnet_cc_no').val(arrayResult.cc_no);
            var novalnet_cc_exp_month = arrayResult.cc_exp_month;
            novalnet_cc_exp_month = ((novalnet_cc_exp_month.length == 1)?'0'+novalnet_cc_exp_month:novalnet_cc_exp_month);
            jQuery('#novalnet_cc_exp_month').val(novalnet_cc_exp_month);

            jQuery('#novalnet_cc_exp_year').val(arrayResult.cc_exp_year);
            jQuery('#novalnet_cc_type').val(arrayResult.cc_type);
		}
	}else{
		alert(response.hash_result);
		return false;
	}
 }

 function ccrefillcall() {
	var cc_panhash = '';
	if(jQuery('#nn_cc_input_panhash')){cc_panhash = jQuery('#nn_cc_input_panhash').val();}
	if(cc_panhash == '' || cc_panhash == 'undefined') {return false;}
	if(jQuery('#nn_vendor')){nn_vendor = jQuery('#nn_vendor').val();}
	if(jQuery('#nn_auth_code')){nn_auth_code = jQuery('#nn_auth_code').val();}
	if(jQuery('#nn_cc_uniqueid')){nn_cc_uniqueid = jQuery('#nn_cc_uniqueid').val();}
	if(nn_vendor == '' || nn_auth_code == '' || nn_cc_uniqueid == '' ) {return false;}

	var nnurl_val = "pan_hash="+cc_panhash+"&unique_id="+nn_cc_uniqueid+"&vendor_authcode="+nn_auth_code+"&vendor_id="+nn_vendor;
	document.getElementById('cc_loader').style.display='block';
	cc_ajax_processcall(nnurl_val, "ccrefill");
 }
 ccrefillcall();
