<?php

#########################################################
#                                                       #
#  PAYPAL payment method class                          #
#  This module is used for real time processing of      #
#  transaction of customers.                            #
#                                                       #
#  Released under the GNU General Public License.       #
#  This free contribution made by request.              #
#  If you have found this script useful a small         #
#  recommendation as well as a comment on merchant form #
#  would be greatly appreciated.                        #
#                                                       #
#  Script : novalnet_paypal.php                         #
#                                                       #
#########################################################

/**
 * Installs PAYPAL payment to Novalnet Payment Gateway
 */
add_action('plugins_loaded', 'init_gateway_' . $novalnet_payment_methods[7], 0);

function init_gateway_novalnet_paypal() {
    
    global $novalnet_payment_methods;
    
    if (class_exists('WC_Gateway_Novalnet')) {
    
        if (!class_exists('novalnet_paypal')) {

            class novalnet_paypal extends WC_Gateway_Novalnet {
                
            }   // End class novalnet_paypal

            $obj = new novalnet_paypal();
        }
    }
    else
        return;
    
}   // End init_gateway_novalnet_paypal

/*
 * Add the gateway to WooCommerce
 * @access public
 * @param array $methods
 * @package		
 * @return array
 */

function add_gateway_novalnet_paypal($methods) {
    global $novalnet_payment_methods;
    $methods[] = 'novalnet_paypal';
    return $methods;
}   // End add_novalnet_paypal_gateway()

add_filter('woocommerce_payment_gateways', 'add_gateway_' . $novalnet_payment_methods[7]);
?>
