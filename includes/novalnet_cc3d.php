<?php

#########################################################
#                                                       #
#  CC3D / CREDIT CARD 3d secure payment method class    #
#  This module is used for real time processing of      #
#  Credit card data of customers.                       #
#                                                       #
#  Released under the GNU General Public License.       #
#  This free contribution made by request.              #
#  If you have found this script useful a small         #
#  recommendation as well as a comment on merchant form #
#  would be greatly appreciated.                        #
#                                                       #
#  Script : novalnet_cc3d.php                           #
#                                                       #
#########################################################
/*
 * Installs CC3D / CREDIT CARD 3d secure payment to Novalnet Payment Gateway
 */
add_action('plugins_loaded', $novalnet_payment_methods[3] . '_Load', 0);

function novalnet_cc3d_Load() {
    global $novalnet_payment_methods;
    if (class_exists('novalnetpayments')) {
        if (!class_exists($novalnet_payment_methods[3])) {

            class novalnet_cc3d extends novalnetpayments {
                
            }

            $obj = new $novalnet_payment_methods[3]();
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

function add_novalnet_cc3d_gateway($methods) {
    global $novalnet_payment_methods;
    $methods[] = $novalnet_payment_methods[3];
    return $methods;
}

add_filter('woocommerce_payment_gateways', 'add_' . $novalnet_payment_methods[3] . '_gateway');
?>
