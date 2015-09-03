/**
 * @category   Novalnet Subscription action
 * @package    Novalnet
 * @copyright  Novalnet (https://www.novalnet.de)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *
 */

 jQuery(document).ready(function(){
    var tmphrefinnerparams;
    var hrefinnerparams=[];
    var url = jQuery('#novalnet_url').val();

    if ( jQuery('#novalnet_shop_admin').val() == 1 ) {
        jQuery('.cancelled').click(function() {
            if(jQuery('.cancelled').parent('div').attr('class') == 'row-actions'){
                jQuery('.cancelled').parent('div').removeClass('row-actions');
            }
            var cancelhref = jQuery(this).children("a").attr('href').split("?")[1];
            var action_url = jQuery(this).children("a").attr('href');
            var hrefparams = cancelhref.split("&");

            for(var i=0; i < hrefparams.length; i++) {
                tmphrefinnerparams = hrefparams[i].split("=");
                hrefinnerparams[tmphrefinnerparams[0]] = tmphrefinnerparams[1];
            }
            if ( hrefinnerparams["subscription"] ) {
                var curElement = this;
                var data = "nov_cus_subs_key="+hrefinnerparams["subscription"];
                process_cancel_option( url, data , curElement, action_url )
            }
            return false;
        });
    } else {
        jQuery('a.reactivate').css('visibility','visible');
        jQuery('a.suspend').css('visibility','visible');
        jQuery('a.cancel').css('visibility','visible');
        jQuery("a.reactivate").click(function(e){
            jQuery(this).css('visibility','hidden');
        });
        jQuery("a.suspend").click(function(e){
            jQuery(this).css('visibility','hidden');
        });

    jQuery('.subscription-actions').find("a.cancel").each(function() {
            jQuery(this).bind("click", function(event){
                jQuery(this).css('visibility','hidden');
                var cancelhref = jQuery(this).attr("href").split("?")[1];
                var hrefparams = cancelhref.split("&");
                for(var i=0; i < hrefparams.length; i++) {
                    tmphrefinnerparams = hrefparams[i].split("=");
                    hrefinnerparams[tmphrefinnerparams[0]] = tmphrefinnerparams[1];
                }

                if ( hrefinnerparams["subscription_key"] ) {
                    var curElement = this;
                    var data = "nov_cus_subs_key="+hrefinnerparams["subscription_key"];
                    document.getElementById('subs_loader').style.display='block';
                    process_cancel_option( url, data ,curElement, curElement.href)
                }
                return false;
            });
        });
    }
    return true;
 });

 function process_cancel_option( url, subs_key, curElement, action_url ){
    var url_value = url + '?nov_action=subs_cancel&'+subs_key;
    if ('XDomainRequest' in window && window.XDomainRequest !== null) {
        var xdr = new XDomainRequest();
        xdr.open('POST' , url_value);
        xdr.onload = function () {
            cancel_dropdown_list( curElement, action_url );
        };
        xdr.send();
    } else {
        jQuery.ajax({
            url: url_value,
            type: "POST",
            data: '',
            success: function( response ) {
                if ( response == "success" ) {
                    cancel_dropdown_list( curElement, action_url );
                }
            },
            error: function(errorThrown){
               console.log(errorThrown);
               return false;
            }
        });
    }
 }

 function cancel_dropdown_list( curElement, action_url ) {
    jQuery('.widefat.subscriptions .column-status').css('width',200);
    var form = jQuery("<form method='GET'></form>").attr("action", action_url);
    var reasonstr = jQuery('#avail_reasons').val();
    var reason = reasonstr.split('|');
    document.getElementById('subs_loader').style.display='none';
    form.append(jQuery("<select style='width:180px;' name='nn_cancel'></select>") .html(
        "<option style='color:#000 !important;' value=''>"+reason[0]+"<option value='1'>"+reason[1]+"</option><option value='2'>"+reason[2]+"</option><option value='3'>"+reason[3]+"</option><option value='4'>"+reason[4]+"</option><option value='5'>"+reason[5]+"</option><option value='6'>"+reason[6]+"</option><option value='7'>"+reason[7]+"</option><option value='8'>"+reason[8]+"</option><option value='9'>"+reason[9]+"</option><option value='10'>"+reason[10]+"</option><option value='11'>"+reason[11]+"</option>"));
    form.append(jQuery("<input type='button' id='nn_cancel_btn' onclick='append_custom_url_action(this)' value='"+jQuery('#subs_cancel_button').val()+"'/>"));
    jQuery(curElement).hide();
    jQuery(curElement).parent().append(form);
    return false;
 }

 function append_custom_url_action(e) {
    var action = e.form.action;
    document.getElementById('subs_loader').style.display='block';
    jQuery("input[type=button]").attr("disabled","disabled");
    action = action + "&nov_reason="+jQuery(e.form.nn_cancel).val();
    window.location.href = action;

 }