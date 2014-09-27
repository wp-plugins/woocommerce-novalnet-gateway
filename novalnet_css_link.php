<?php
#########################################################
#                                                       #
#  CUSTOMISED CSS LINK FOR NOVALNET payment methods     #
#                                                       #
#  Released under the GNU General Public License.       #
#  This free contribution made by request.              #
#  If you have found this script usefull a small        #
#  recommendation as well as a comment on merchant form #
#  would be greatly appreciated.                        #
#                                                       #
#  Script : novalnet_css_link.php                       #
#                                                       #
#########################################################

/**
 *SEPA Custom CSS
 */
define('NOVALNET_SEPA_CUSTOM_CSS', 'body~~~input, select~~~#novalnet_sepa_country~~~input.mandate_confirm_btn');
define('NOVALNET_SEPA_CUSTOM_CSS_STYLE', 'font-family:Source Sans Pro,Helvetica,sans-serif;font-size:12px;~~~border: 1px solid #CCCCCC; border-radius: 3px; padding: 0.428571rem;height:17px !important;width:180px;~~~height:34px !important;width:196px !important;~~~height:32px !important;');

/**
 *Credit Card Custom CSS
 */
define('NOVALNET_CC_CUSTOM_CSS', 'body~~~input, select~~~td~~~#novalnetCc_cc_type, #novalnetCc_expiration, #novalnetCc_expiration_yr~~~#novalnetCc_cc_type~~~#novalnetCc_expiration~~~#novalnetCc_expiration_yr~~~td');
define('NOVALNET_CC_CUSTOM_CSS_STYLE', 'font-family:Source Sans Pro,Helvetica,sans-serif;font-size:12px;~~~border: 1px solid #CCCCCC; border-radius: 3px; padding: 0.428571rem;height:17px !important;width:180px;~~~color:#5E5E5E;~~~height:34px !important;~~~width:196px !important;~~~width:107px !important;~~~width:80px;~~~padding:0.428571rem !important;');

?>