jQuery(document).ready(function(){
    getSEPAIframe();
	jQuery('#billing_first_name, #billing_last_name, #billing_country, #billing_company, #billing_address_1, #billing_postcode, #billing_city, #billing_email').on('change', function(){
		getSEPAIframe();
	});

	jQuery(document).on('click','input[type=radio]', function(){
		if(jQuery(this).attr('name') == 'payment_method'){
			var value = jQuery(this).val();
			if (value == 'novalnet_sepa') {
				getSEPAIframe();
			}
		}
	});
});

function getSEPAIframe() {

	var fname = jQuery('#billing_first_name').val() ;
	var lname = jQuery('#billing_last_name').val();
	var country = jQuery('#billing_country').val();
	var company = jQuery('#billing_company').val();
	var address = jQuery('#billing_address_1').val();
	var zip = jQuery('#billing_postcode').val();
	var city = jQuery('#billing_city').val();
	var email = jQuery('#billing_email').val();
    var sepaaction = "action=novalnet_sepa_iframe";
	var nncust = "&first_name="+encodeURIComponent(fname)+"&last_name="+encodeURIComponent(lname)+"&email="+encodeURIComponent(email);
	var nncustaddr = "&country="+encodeURIComponent(country)+"&postcode="+encodeURIComponent(zip)+"&city="+encodeURIComponent(city)+"&address="+encodeURIComponent(address)+"&company="+encodeURIComponent(company);
    var nnconfig = jQuery('#nnurldata').val();
    var data = sepaaction + nncust + nncustaddr + nnconfig;
    var nnsiteurl = jQuery('#nnsiteurl').val();
	var nncust_details = jQuery('#nn_cust_data').val();

	if(nncust_details == ""){
		var data = sepaaction + nncust + nncustaddr + nnconfig;
	}else{
		var data = sepaaction + nncust_details + nnconfig;
	}
	var nnfullpath = nnsiteurl+"/wp-content/plugins/woocommerce-novalnet-gateway/nnsepaiframe.php?"+data;
	jQuery("#novalnet_sepa_iframe").attr('src',nnfullpath);
	jQuery("#loading_sepaiframe_div").css('display','inline');
	jQuery(document).on('click','#place_order',function(){
	getSEPAValues();
	});
}

function getSEPAValues() {
	jQuery("#loading_sepaiframe_div").css('display','none');
	var novalnet_sepa_iframe = jQuery("#novalnet_sepa_iframe").contents();

	if(novalnet_sepa_iframe.find("#nnsepa_hash").val() != null){
		jQuery("#sepa_owner").val(novalnet_sepa_iframe.find("#novalnet_sepa_owner").val());
		jQuery("#sepa_uniqueid").val(novalnet_sepa_iframe.find("#nnsepa_unique_id").val());
		jQuery("#sepa_confirm").val(novalnet_sepa_iframe.find("#nnsepa_iban_confirmed").val());
		jQuery("#sepa_mandate_ref").val(novalnet_sepa_iframe.find("#nnsepa_mandate_ref").val());
		jQuery("#sepa_mandate_date").val(novalnet_sepa_iframe.find("#nnsepa_mandate_date").val());
		jQuery("#panhash").val(novalnet_sepa_iframe.find("#nnsepa_hash").val());

		var sepa_owner=0;var sepa_accountno=0;var sepa_bankcode=0;var sepa_iban=0;var sepa_swiftbic=0; var sepa_hash=0; var sepa_country=0;

		if(novalnet_sepa_iframe.find("#novalnet_sepa_owner").val()!= "") sepa_owner=1;
		if(novalnet_sepa_iframe.find("#novalnet_sepa_accountno").val()!= "") sepa_accountno=1;
		if(novalnet_sepa_iframe.find("#novalnet_sepa_bankcode").val()!= "") sepa_bankcode=1;
		if(novalnet_sepa_iframe.find("#novalnet_sepa_iban").val()!= "") sepa_iban=1;
		if(novalnet_sepa_iframe.find("#novalnet_sepa_swiftbic").val()!= "") sepa_swiftbic=1;
		if(novalnet_sepa_iframe.find("#nnsepa_hash").val()!= "") sepa_hash=1;
		if(novalnet_sepa_iframe.find("#novalnet_sepa_country").val()!= "") {
		 sepa_country = 1 +"-"+ novalnet_sepa_iframe.find("#novalnet_sepa_country").val();
		}
		var fldvdr = (sepa_owner+","+sepa_accountno+","+sepa_bankcode+","+sepa_iban+","+sepa_swiftbic+","+sepa_hash+","+sepa_country);
		jQuery("#sepa_fldvdr").val(fldvdr);
	}
}