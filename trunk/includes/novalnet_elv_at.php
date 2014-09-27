<?php

#########################################################
#                                                       #
#  ELVAT / DIRECT DEBIT payment method class            #
#  This module is used for real time processing of      #
#  Austrian Bankdata of customers.                      #
#                                                       #
#  Released under the GNU General Public License.       #
#  This free contribution made by request.              #
#  If you have found this script useful a small         #
#  recommendation as well as a comment on merchant form #
#  would be greatly appreciated.                        #
#                                                       #
#  Script : novalnet_elv_at.php                         #
#                                                       #
#########################################################
/*
 * Installs ELVAT / DIRECT DEBIT payment to Novalnet Payment Gateway
 */
add_action('plugins_loaded', 'init_gateway_' . $novalnet_payment_methods[3], 0);

function init_gateway_novalnet_elv_at() {
    
    global $novalnet_payment_methods;
    
    if (class_exists('WC_Gateway_Novalnet')) {
    
        if (!class_exists('novalnet_elv_at')) {

            class novalnet_elv_at extends WC_Gateway_Novalnet {
                
            }   // End class novalnet_elv_at

            $obj = new novalnet_elv_at();
        }
    }
    else
        return;    
}   // End init_gateway_novalnet_elv_at()

/*
 * Add the gateway to WooCommerce
 * @access public
 * @param array $methods
 * @package		
 * @return array
 */

function add_gateway_novalnet_elv_at($methods) {
    global $novalnet_payment_methods;
    $methods[] = 'novalnet_elv_at';
    return $methods;
}	// End add_gateway_novalnet_elv_at()

add_filter('woocommerce_payment_gateways', 'add_gateway_' . $novalnet_payment_methods[3]);
?>
