<?php
#########################################################
#                                                       #
#  INVOICE payment method class                         #
#  This module is used for real time processing of      #
#  Invoice data of customers.                       	#
#                                                       #
#  Released under the GNU General Public License.       #
#  This free contribution made by request.              #
#  If you have found this script useful a small         #
#  recommendation as well as a comment on merchant form #
#  would be greatly appreciated.                        #
#                                                       #
#  Script : novalnet_invoice.php                        #
#                                                       #
#########################################################

/**
 * Installs INVOICE payment to Novalnet Payment Gateway
 */
add_action('plugins_loaded', 'init_gateway_'.$novalnet_payment_methods['nn_invoice'], 0);

function init_gateway_novalnet_invoice() {

    global $novalnet_payment_methods;

    if (class_exists('WC_Gateway_Novalnet')) {

        if (!class_exists('novalnet_invoice')) {

            class novalnet_invoice extends WC_Gateway_Novalnet {

            }   // End class novalnet_invoice

            $obj = new novalnet_invoice();
        }
    }
    else
        return;
}   // End init_gateway_novalnet_invoice()

/*
 * Add the gateway to WooCommerce
 * @access public
 * @param array $methods
 * @package
 * @return array
 */

function add_gateway_novalnet_invoice($methods) {
    global $novalnet_payment_methods;
    $methods[] = 'novalnet_invoice';
    return $methods;
}   // End add_gateway_novalnet_invoice()

add_filter('woocommerce_payment_gateways', 'add_gateway_'.$novalnet_payment_methods['nn_invoice']);
?>