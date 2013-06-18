<?php
#########################################################
#                                                       #
#  Paypal payment method class                          #
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
/*
* Check if WooCommerce is active
*/
add_action('plugins_loaded', $novalnet_payment_methods[10].'_Load', 0);
function novalnet_paypal_Load() {
global $novalnet_payment_methods;
if ( ! class_exists( $novalnet_payment_methods[10] ) ) {
class novalnet_paypal extends novalnetpayments {
}
$obj = new $novalnet_payment_methods[10]();
}
}
/*
* Add the gateway to WooCommerce
* @access public
* @param array $methods
* @package		
* @return array
*/
function add_novalnet_paypal_gateway( $methods ) {
global $novalnet_payment_methods;
$methods[] = $novalnet_payment_methods[10];
return $methods;
}
add_filter('woocommerce_payment_gateways', 'add_'.$novalnet_payment_methods[10].'_gateway' );
?>