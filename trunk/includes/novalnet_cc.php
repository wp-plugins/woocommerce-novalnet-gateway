<?php
#########################################################
#                                                       #
#  CC / CREDIT CARD payment method class                #
#  This module is used for real time processing of      #
#  Credit card data of customers.                       #
#                                                       #
#  Released under the GNU General Public License.       #
#  This free contribution made by request.              #
#  If you have found this script useful a small         #
#  recommendation as well as a comment on merchant form #
#  would be greatly appreciated.                        #
#                                                       #
#  Script : novalnet_cc.php                             #
#                                                       #
#########################################################
/*
 * Installs CC / CREDIT CARD payment to Novalnet Payment Gateway
 */
add_action('plugins_loaded', 'init_gateway_'.$novalnet_payment_methods['nn_creditcard'], 0);

function init_gateway_novalnet_cc() {

    global $novalnet_payment_methods;

    if (class_exists('WC_Gateway_Novalnet')) {

        if (!class_exists('novalnet_cc')) {

            class novalnet_cc extends WC_Gateway_Novalnet {

            }   // End class novalnet_cc

            $obj = new novalnet_cc();
        }
    }
    else
        return;
}   // End init_gateway_novalnet_cc()

/*
 * Add the gateway to WooCommerce
 * @access public
 * @param array $methods
 * @package
 * @return array
 */

function add_gateway_novalnet_cc($methods) {
    global $novalnet_payment_methods;
    $methods[] = 'novalnet_cc';
    return $methods;
}	// End add_gateway_novalnet_cc()

add_filter('woocommerce_payment_gateways', 'add_gateway_'.$novalnet_payment_methods['nn_creditcard']);
?>
