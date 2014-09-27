<?php
#########################################################
#                                                       #
#  PREPAYMENT payment method class                      #
#  This module is used for real time processing of      #
#  PREPAYMENT payment of customers.                     #
#                                                       #
#  Released under the GNU General Public License.       #
#  This free contribution made by request.              #
#  If you have found this script useful a small         #
#  recommendation as well as a comment on merchant form #
#  would be greatly appreciated.                        #
#                                                       #
#  Script : novalnet_prepayment.php                     #
#                                                       #
#########################################################

/**
 * Installs PREPAYMENT payment to Novalnet Payment Gateway
 */
add_action('plugins_loaded', 'init_gateway_' . $novalnet_payment_methods['nn_prepayment'], 0);

function init_gateway_novalnet_prepayment() {

    global $novalnet_payment_methods;

    if (class_exists('WC_Gateway_Novalnet')) {

        if (!class_exists('novalnet_prepayment')) {

            class novalnet_prepayment extends WC_Gateway_Novalnet {

            }   // End class novalnet_prepayment

            $obj = new novalnet_prepayment();
        }
    }
    else
        return;
}   // End init_gateway_novlanet_prepayment()

/*
 * Add the gateway to WooCommerce
 * @access public
 * @param array $methods
 * @package
 * @return array
 */

function add_gateway_novalnet_prepayment($methods) {
    global $novalnet_payment_methods;
    $methods[] = 'novalnet_prepayment';
    return $methods;
}   // End add_gateway_novalnet_prepayment()

add_filter('woocommerce_payment_gateways', 'add_gateway_' . $novalnet_payment_methods['nn_prepayment']);
?>