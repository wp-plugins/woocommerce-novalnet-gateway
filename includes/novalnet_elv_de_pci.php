<?php
#########################################################
#                                                       #
#  ELVDEPCI / DIRECT DEBIT PCI payment method class     #
#  This module is used for real time processing of      #
#  German Bankdata of customers.                        #
#                                                       #
#  Released under the GNU General Public License.       #
#  This free contribution made by request.              #
#  If you have found this script useful a small         #
#  recommendation as well as a comment on merchant form #
#  would be greatly appreciated.                        #
#                                                       #
#  Script : novalnet_elv_de_pci.php                     #
#                                                       # 
#########################################################
/*
* Check if WooCommerce is active
*/
add_action('plugins_loaded', $novalnet_payment_methods[7].'_Load', 0);
function novalnet_elv_de_pci_Load() {
global $novalnet_payment_methods;
if ( ! class_exists( $novalnet_payment_methods[7] ) ) {
class novalnet_elv_de_pci extends novalnetpayments {
}
$obj = new $novalnet_payment_methods[7]();
}
}
/*
* Add the gateway to WooCommerce
* @access public
* @param array $methods
* @package		
* @return array
*/
function add_novalnet_elv_de_pci_gateway( $methods ) {
global $novalnet_payment_methods;
$methods[] = $novalnet_payment_methods[7];
return $methods;
}
add_filter('woocommerce_payment_gateways', 'add_'.$novalnet_payment_methods[7].'_gateway' );
?>