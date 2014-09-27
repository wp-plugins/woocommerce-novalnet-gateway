<?php
#########################################################
#  This file is used for rendering the Direct Debit		#
#  SEPA template.                              			#
#             											#
#  Released under the GNU General Public License.       #
#  This free contribution made by request.              #
#  If you have found this script useful a small         #
#  recommendation as well as a comment on merchant form #
#  would be greatly appreciated.                        #
#                                                       #
#  Script : render_sepa_template.php                    #
#                                                       #
#########################################################
require_once(NOVALNET_DIR.'/novalnet_css_link.php');

class render_sepa_template{

	public function render_sepa_form($load_data, $customer_details){

		if(NOVALNET_SEPA_CUSTOM_CSS)
			$original_sepa_css = NOVALNET_SEPA_CUSTOM_CSS;
		else $original_sepa_css = 'body~~~input, select~~~#novalnet_sepa_country~~~input.mandate_confirm_btn';

		if(NOVALNET_SEPA_CUSTOM_CSS_STYLE)
			$original_sepa_cssval = NOVALNET_SEPA_CUSTOM_CSS_STYLE;
		else
			$original_sepa_cssval = 'font-family:Open Sans,Helvetica,Arial,sans-serif;font-size:12px;~~~border: 1px solid #CCCCCC; border-radius: 3px; padding: 0.428571rem;height:17px !important;width:180px;~~~height:34px !important;width:196px !important;~~~height:32px !important;';


		echo '<br /> <div id="loading_sepaiframe_div" style="display:inline;"><img alt="' . __('Loading...', 'woocommerce-novalnetpayment') . '" src="' . LOGO_PATH . 'novalnet-loading-icon.gif" style="box-shadow:none;"></div><input type="hidden" name="sepa_owner" id="sepa_owner" value="" /><input type="hidden" name="panhash" id="panhash" value="" /><input type="hidden" name="sepa_uniqueid" id="sepa_uniqueid" value="" /><input type="hidden" name="sepa_confirm" id="sepa_confirm" value="" /><input type="hidden" name="sepa_fldvdr" id="sepa_fldvdr" value="" /><input type="hidden" name="sepa_vendor_id" id="sepa_vendor_id" value="' . $load_data['vendor_id'] . '" /><input type="hidden" name="nnsiteurl" id="nnsiteurl" value="' . (site_url()) . '" /><input type="hidden" name="nn_cust_data" id="nn_cust_data" value="' . $customer_details . '" />
		<input type="hidden" name="nnurldata" id="nnurldata" value="&panhash='.$load_data['panhash'].'&fldvdr='.$load_data['fldVdr'].'&abs_path='.$load_data['abs_path'].'" />
		<input type="hidden" id="original_sepa_customstyle_css" value="'.$original_sepa_css.'" /><input type="hidden" id="original_sepa_customstyle_cssval" value="'.$original_sepa_cssval.'" /><script type="text/javascript" src="'.site_url().'/wp-content/plugins/woocommerce-novalnet-gateway/assets/js/nnsepa.js"></script><iframe width="100%" scrolling="no" height="460px" frameborder="0" name="novalnet_sepa_iframe" id="novalnet_sepa_iframe" onload="getSEPAValues()"></iframe>';
	}
}
?>