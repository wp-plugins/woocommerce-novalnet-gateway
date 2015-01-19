/**
 * @category   Novalnet
 * @package    Novalnet
 * @copyright  Novalnet AG. (http://www.novalnet.de)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *
 */

function noval_refund(url, paid_amount, cur_lang){

	var nov_refund_amount = jQuery("#nov_refund_amount").val();
	var regex = new RegExp(/^\d*\.?\d{1,2}$/);


	if ( nov_refund_amount <= 0 || regex.test( nov_refund_amount ) == false) {

		var err_msg = (cur_lang == 'EN') ? 'Invalid amount!' : 'Ungültiger Betrag!';
		if(!window.confirm(err_msg)) {
            return false;
		}

	} else if ( nov_refund_amount > (paid_amount/100) ) {
		var err_msg = (cur_lang == 'EN') ? 'Refund cannot be processed, as the amount exceeds the paid amount!' : 'Die Rückerstattung kann nicht durchgeführt werden, da der Betrag den gezahlten Betrag überschreitet!';
		if(!window.confirm(err_msg)) {
            return false;
		}
	} else {
		var post_data = '&nov_refund_amount='+nov_refund_amount;
		jQuery("#novalnet_loading_div").css('display','inline');
		jQuery.ajax({
			type : 'POST',
			url: url,
			data: post_data,
			success: function(data) {
				location.reload();
			}
		});
		return true;
	}
}

function noval_trans_confirm(url, method, language){
	var confirm_status = jQuery("#confirm_status").val();
	if(confirm_status == 100 || confirm_status == 103){
		jQuery("#novalnet_loading_div").css('display','inline');
		jQuery.ajax({
			type : 'POST',
			url: url,
			data: '&confirm_status='+confirm_status,
			success: function(data) {
				location.reload();
			}
		});
		return true;
	}else{
		var err_msg = (language == 'EN') ? 'Please select the status!' : 'Bitte ändern Sie den Status!';
		if (!window.confirm(err_msg)) {	 return false; }
	}
}