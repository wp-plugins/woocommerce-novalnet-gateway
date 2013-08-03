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
add_action('plugins_loaded', $novalnet_payment_methods[4] . '_Load', 0);

function novalnet_elv_at_Load() {
    global $novalnet_payment_methods;
    if (class_exists('novalnetpayments')) {
        if (!class_exists($novalnet_payment_methods[4])) {

            class novalnet_elv_at extends novalnetpayments {
                
            }

            $obj = new $novalnet_payment_methods[4]();
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

function add_novalnet_elv_at_gateway($methods) {
    global $novalnet_payment_methods;
    $methods[] = $novalnet_payment_methods[4];
    return $methods;
}

add_filter('woocommerce_payment_gateways', 'add_' . $novalnet_payment_methods[4] . '_gateway');
?>
