<?php
#########################################################
#                                                       #
#  DIRECT DEBIT SEPA payment method class      			#
#  This module is used for real time processing of      #
#  German Bankdata of customers.                      	#
#                                                       #
#  Released under the GNU General Public License.       #
#  This free contribution made by request.              #
#  If you have found this script useful a small         #
#  recommendation as well as a comment on merchant form #
#  would be greatly appreciated.                        #
#                                                       #
#  Script : novalnet_sepa.php                         	#
#                                                       #
#########################################################
/*
 * Installs ELVDE / DIRECT DEBIT SEPA payment to Novalnet Payment Gateway
 */
add_action('plugins_loaded', 'init_gateway_'.$novalnet_payment_methods['nn_directdebitsepa'], 0);

function init_gateway_novalnet_sepa() {

    global $novalnet_payment_methods;

    if (class_exists('WC_Gateway_Novalnet')) {

        if (!class_exists('novalnet_sepa')) {

            class novalnet_sepa extends WC_Gateway_Novalnet {

            }   // End class novalnet_sepa

            $obj = new novalnet_sepa();
        }
    }
    else
        return;
}   // End init_gateway_novalnet_sepa()

/*
 * Add the gateway to WooCommerce
 * @access public
 * @param array $methods
 * @package
 * @return array
 */

function add_gateway_novalnet_sepa($methods) {
    global $novalnet_payment_methods;
    $methods[] = 'novalnet_sepa';
    return $methods;
}	// End add_gateway_novalnet_sepa()

add_filter('woocommerce_payment_gateways', 'add_gateway_'.$novalnet_payment_methods['nn_directdebitsepa']);
?>
