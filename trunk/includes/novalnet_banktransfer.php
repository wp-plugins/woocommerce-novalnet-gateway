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

/**
 * Installs Sofortüberweisung / BANKTRANSFER payment to Novalnet Payment Gateway
 */
add_action('plugins_loaded', 'init_gateway_'.$novalnet_payment_methods['nn_banktransfer'], 0);

function init_gateway_novalnet_banktransfer() {

    if (class_exists('WC_Gateway_Novalnet')) {

        global $novalnet_payment_methods;

        if (!class_exists('novalnet_banktransfer')) {

            class novalnet_banktransfer extends WC_Gateway_Novalnet {

            }	// End class novalnet_banktransfer

            $obj = new novalnet_banktransfer();
        }
    }
    else
        return;
}	// End init_gateway_novalnet_banktransfer()

/*
 * Add the gateway to WooCommerce
 * @access public
 * @param array $methods
 * @package
 * @return array
 */

function add_gateway_novalnet_banktransfer($methods) {
    global $novalnet_payment_methods;
    $methods[] = 'novalnet_banktransfer';
    return $methods;
}	// End add_gateway_novalnet_banktransfer()

add_filter('woocommerce_payment_gateways', 'add_gateway_'.$novalnet_payment_methods['nn_banktransfer']);
?>