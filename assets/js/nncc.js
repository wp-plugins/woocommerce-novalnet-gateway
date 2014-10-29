jQuery(document).ready(function(){
	showCCIframe();
	jQuery(document).on('click','input[type=radio]', function(){
		if(jQuery(this).attr('name') == 'payment_method'){
		  var value = jQuery(this).val();
		  if (value == 'novalnet_cc'){
			  showCCIframe();
		   }
		  }
	});
});

function showCCIframe() {
    jQuery("#loading_cc_iframe_div").css('display','inline');
    var ccaction = "action=novalnet_cc_iframe";
    var nnconfig = jQuery("#nnccrequest").val();
    var data = ccaction + nnconfig;
    var nnccsiteurl = jQuery("#nnccsiteurl").val();
    var nnfullpath = nnccsiteurl + "/wp-content/plugins/woocommerce-novalnet-gateway/nncciframe.php?" + data;
    jQuery("#novalnet_cc_iframe").attr('src',nnfullpath);
    jQuery("#loading_cc_iframe_div").css('display','inline');
    jQuery(document).on('click', '#place_order', function(){
    getCCValues();
	});
}

function loadCCIframe(element) {
    jQuery("#loading_cc_iframe_div").css('display','none');
    var cc_iframe = jQuery("#novalnet_cc_iframe").contents();
    if(cc_iframe.find("#novalnetCc_cc_type")){
		cc_iframe.find("#novalnetCc_cc_type").on('change', function(){
            assignValuesToElements(cc_iframe)
        });

        cc_iframe.find("#novalnetCc_cc_owner").on('keyup',function(){
            assignValuesToElements(cc_iframe)
        });

        cc_iframe.find("#novalnetCc_expiration").on('change',function(){
           assignValuesToElements(cc_iframe)
        });

        cc_iframe.find("#novalnetCc_expiration_yr").on('change',function(){
            assignValuesToElements(cc_iframe)
        });

        cc_iframe.find("#novalnetCc_cc_cid").on('keyup',function(){
            assignValuesToElements(cc_iframe)
        })
    }
}

function assignValuesToElements(cc_iframe) {
    jQuery("#cc_type").val(cc_iframe.find("#novalnetCc_cc_type").val());
    jQuery("#cc_holder").val(cc_iframe.find("#novalnetCc_cc_owner").val());
    jQuery("#cc_exp_month").val(cc_iframe.find("#novalnetCc_expiration").val());
    jQuery("#cc_exp_year").val(cc_iframe.find("#novalnetCc_expiration_yr").val());
    jQuery("#cc_cvv_cvc").val(cc_iframe.find("#novalnetCc_cc_cid").val());
}

function getCCValues() {
    var novalnet_cc_iframe = jQuery("#novalnet_cc_iframe").contents();
    var cc_type=0; var cc_holder=0; var cc_no=0; var nncc_hash=0; var cc_exp_month=0; var cc_exp_year=0; var cc_cvv_cvc=0;
    if(novalnet_cc_iframe.find("#novalnetCc_cc_type").val() != "") cc_type=1;
    if(novalnet_cc_iframe.find("#novalnetCc_cc_owner").val() != "") cc_holder=1;
    if(novalnet_cc_iframe.find("#novalnetCc_cc_number").val() != "") cc_no=1;
    if(novalnet_cc_iframe.find("#novalnetCc_expiration").val() != "")  cc_exp_month = 1;
    if(novalnet_cc_iframe.find("#novalnetCc_expiration_yr").val() != "") cc_exp_year = 1;
    if(novalnet_cc_iframe.find("#novalnetCc_cc_cid").val() != "") cc_cvv_cvc=1;
    var a = jQuery("#cc_fldvdr").val(cc_type+","+cc_holder+","+cc_no+","+cc_exp_month+","+cc_exp_year+","+cc_cvv_cvc);

    if (novalnet_cc_iframe.find("#nncc_cardno_id").val() != "") {
        jQuery("#nn_unique").val(novalnet_cc_iframe.find("#nncc_unique_id").val());
        jQuery("#nncc_hash").val(novalnet_cc_iframe.find("#nncc_cardno_id").val());
    }
}