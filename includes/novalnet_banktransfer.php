<?php

#########################################################
#                                                       #
#  Sofortüberweisung / BANKTRANSFER payment      		#
#  method class                                         #
#  This module is used for real time processing of      #
#  German Bankdata of customers.                        #
#                                                       #
#  Released under the GNU General Public License.       #
#  This free contribution made by request.              #
#  If you have found this script useful a small         #
#  recommendation as well as a comment on merchant form #
#  would be greatly appreciated.                        #
#                                                       #
#  Script : novalnet_banktransfer.php     				#
#                                                       #
#########################################################
/*
 * Installs Sofortüberweisung / BANKTRANSFER payment to Novalnet Payment Gateway
 */
add_action('plugins_loaded', $novalnet_payment_methods[0] . '_Load', 0);

function novalnet_banktransfer_Load() {
    if (class_exists('novalnetpayments')) {
        global $novalnet_payment_methods;
        if (!class_exists($novalnet_payment_methods[0])) {

            class novalnet_banktransfer extends novalnetpayments {
                
            }

            $obj = new $novalnet_payment_methods[0]();
        }
    } else {
        return;
    }
}

/*
 * Add the gateway to WooCommerce
 * @access public
 * @param array $methods
 * @package		
 * @return array
 */

function add_novalnet_banktransfer_gateway($methods) {
    global $novalnet_payment_methods;
    $methods[] = $novalnet_payment_methods[0];
    return $methods;
}

add_filter('woocommerce_payment_gateways', 'add_' . $novalnet_payment_methods[0] . '_gateway');
?>
