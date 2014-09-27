<?php
#########################################################
#  This file is used for rendering the Credit card 		#
#  template.                              				#
#             											#
#  Released under the GNU General Public License.       #
#  This free contribution made by request.              #
#  If you have found this script useful a small         #
#  recommendation as well as a comment on merchant form #
#  would be greatly appreciated.                        #
#                                                       #
#  Script : render_cc_template.php                      #
#                                                       #
#########################################################

require_once(NOVALNET_DIR.'/novalnet_css_link.php');

class render_cc_template{
	public function render_cc_form($form_data){

		if(NOVALNET_CC_CUSTOM_CSS)
			$original_cc_css = NOVALNET_CC_CUSTOM_CSS;
		else 	$original_cc_css = 'body~~~input, select~~~td~~~#novalnetCc_cc_type, #novalnetCc_expiration, #novalnetCc_expiration_yr~~~#novalnetCc_cc_type~~~#novalnetCc_expiration~~~#novalnetCc_expiration_yr~~~td';


		if(NOVALNET_CC_CUSTOM_CSS_STYLE)
			$original_cc_cssval = NOVALNET_CC_CUSTOM_CSS_STYLE;
		else	$original_cc_cssval = 'font-family:Open Sans,Helvetica,Arial,sans-serif;font-size:12px;~~~border: 1px solid #CCCCCC; border-radius: 3px; padding: 0.428571rem;height:17px !important;width:180px;~~~color:#5E5E5E;~~~height:34px !important;~~~width:196px !important;~~~width:107px !important;~~~width:80px;~~~padding:0.428571rem !important;';

		echo '<br /><div id="loading_cc_iframe_div" style="display:inline;"><img alt="' . __('Loading...', 'woocommerce-novalnetpayment') . '" src="' . LOGO_PATH . 'novalnet-loading-icon.gif" style="box-shadow:none;"></div><input type="hidden" name="cc_type" id="cc_type" value="" /><input type="hidden" name="cc_holder" id="cc_holder" value="" /><input type="hidden" name="cc_exp_month" id="cc_exp_month" value="" /><input type="hidden" name="cc_exp_year" id="cc_exp_year" value="" /><input type="hidden" name="cc_cvv_cvc" id="cc_cvv_cvc" value="" /><input type="hidden" name="original_vendor_id" id="original_vendor_id" value="' . ($form_data['vendor_id']) . '" /><input type="hidden" name="original_vendor_authcode" id="original_vendor_authcode" value="'.($form_data['auth_code']) . '" /><input type="hidden" id="original_customstyle_css" value="'.$original_cc_css.'" /><input type="hidden" id="original_customstyle_cssval" value="'.$original_cc_cssval.'" /><input type="hidden" name="nnccsiteurl" id="nnccsiteurl" value="' . (site_url()) . '" /><input type="hidden" name="nn_unique" id="nn_unique" value="" /><input type="hidden" name="nncc_hash" id="nncc_hash" value="" /><input type="hidden" name="cc_fldvdr" id="cc_fldvdr" value="" />
		<input type="hidden" name="nnccrequest" id="nnccrequest" value="&panhash='.$form_data['panhash'].'&fldvdr='.$form_data['fldVdr'].'&abs_path='.$form_data['abs_path'].'" />
		<script type="text/javascript" src="'.site_url().'/wp-content/plugins/woocommerce-novalnet-gateway/assets/js/nncc.js"></script><iframe width="100%" scrolling="no" height="280px" frameborder="0" name="novalnet_cc_iframe" id="novalnet_cc_iframe" onload = "loadCCIframe(this);" ></iframe>';
	}
}
?>