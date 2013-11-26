<?php

#########################################################
#                                                       #
#  TELEPHONE payment method class                       #
#  This module is used for real time processing of      #
#  TELEPHONE  payment of customers.                     #
#                                                       #
#  Released under the GNU General Public License.       #
#  This free contribution made by request.              #
#  If you have found this script useful a small         #
#  recommendation as well as a comment on merchant form #
#  would be greatly appreciated.                        #
#                                                       #
#  Script :novalnet_tel.php                             #
#                                                       #
#########################################################
/*
 * Installs TELEPHONE payment to Novalnet Payment Gateway
 */
add_action('plugins_loaded', 'init_gateway_'. $novalnet_payment_methods[9], 0);

function init_gateway_novalnet_tel() {
	
    global $novalnet_payment_methods;
    
    if (class_exists('WC_Gateway_Novalnet')) {
		
        if (!class_exists('novalnet_tel')) {

            class novalnet_tel extends WC_Gateway_Novalnet {
                
            }	// End class novalnet_tel
            
            $obj = new novalnet_tel();
            
        }
        
    }	
    else
        return;
        
}	// End init_novalnet_tel()

/**
 * Add the gateway to WooCommerce
 * @access public
 * @param array $methods
 * @package		
 * @return array
 */
function add_gateway_novalnet_tel($methods) {
	
    global $novalnet_payment_methods;
    $methods[] = 'novalnet_tel';
    return $methods;
    
}	// End add_novalnet_tel_gateway()

add_filter('woocommerce_payment_gateways', 'add_gateway_' . $novalnet_payment_methods[9]);
?>
