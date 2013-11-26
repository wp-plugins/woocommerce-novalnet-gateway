<?php

#########################################################
#                                                       #
#  ELVDE / DIRECT DEBIT payment method class            #
#  This module is used for real time processing of      #
#  German Bankdata of customers.                      	#
#                                                       #
#  Released under the GNU General Public License.       #
#  This free contribution made by request.              #
#  If you have found this script useful a small         #
#  recommendation as well as a comment on merchant form #
#  would be greatly appreciated.                        #
#                                                       #
#  Script : novalnet_elv_de.php                         #
#                                                       #
#########################################################
/*
 * Installs ELVDE / DIRECT DEBIT payment to Novalnet Payment Gateway
 */
add_action('plugins_loaded', 'init_gateway_' . $novalnet_payment_methods[4], 0);

function init_gateway_novalnet_elv_de() {
    
    global $novalnet_payment_methods;
    
    if (class_exists('WC_Gateway_Novalnet')) {
    
        if (!class_exists('novalnet_elv_de')) {

            class novalnet_elv_de extends WC_Gateway_Novalnet {
                
            }   // End class novalnet_elv_de

            $obj = new novalnet_elv_de();
        }
    }
    else
        return;
}   // End init_gateway_novalnet_elv_de()

/*
 * Add the gateway to WooCommerce
 * @access public
 * @param array $methods
 * @package		
 * @return array
 */

function add_gateway_novalnet_elv_de($methods) {
    global $novalnet_payment_methods;
    $methods[] = 'novalnet_elv_de';
    return $methods;
}	// End add_gateway_novalnet_elv_de()

add_filter('woocommerce_payment_gateways', 'add_gateway_' . $novalnet_payment_methods[4]);
?>
