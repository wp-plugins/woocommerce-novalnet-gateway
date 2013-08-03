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
/*
 * Installs INVOICE payment to Novalnet Payment Gateway
 */
add_action('plugins_loaded', $novalnet_payment_methods[9] . '_Load', 0);

function novalnet_invoice_Load() {
    global $novalnet_payment_methods;
    if (class_exists('novalnetpayments')) {
        if (!class_exists($novalnet_payment_methods[9])) {

            class novalnet_invoice extends novalnetpayments {
                
            }

            $obj = new $novalnet_payment_methods[9]();
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

function add_novalnet_invoice_gateway($methods) {
    global $novalnet_payment_methods;
    $methods[] = $novalnet_payment_methods[9];
    return $methods;
}

add_filter('woocommerce_payment_gateways', 'add_' . $novalnet_payment_methods[9] . '_gateway');
?>
