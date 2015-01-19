<?php
/**
 * Prepayment Payment Gateway by Novalnet
 *
 * Copyright (c) 2015 Novalnet AG <https://www.novalnet.de>
 *
 * Released under the GNU General Public License. This free
 * contribution made by request.If you have found this script
 * usefull a small recommendation as well as a comment on
 * merchant form would be greatly appreciated.
 *
 * This gateway is used for real time processing of prepayment payment of customers.
 *
 * @class 		WC_Gateway_Novalnet_Prepayment
 * @extends		WC_Novalnet_Payment_Gateway
 * @version		2.0.0
 * @package		woocommerce-novalnet-gateway/includes/gateways/novalnet_prepayment
 * @author 		Novalnet
 * @link		https://www.novalnet.de
 * @copyright	2015 Novalnet AG <https://www.novalnet.de>
 * @license     GNU General Public License version 2.0
 */

/*** Install Prepayment payment to Novalnet Payment Gateway ***/
add_action('plugins_loaded', 'init_gateway_' . $novalnet_payment_methods['nn_prepayment'], 0);

function init_gateway_novalnet_prepayment() {

    global $novalnet_payment_methods;

    class WC_Gateway_Novalnet_Prepayment extends WC_Novalnet_Payment_Gateway {

		var $id 			= NOVALNET_PT;
		var $payment_key 	= PAYMENT_KEY_IP;
		var $payment_type 	= 'PREPAYMENT';
		public function __construct() {
			// Load the settings
			$this->init_form_fields();
			$this->init_settings();
			$this->method_title 		= 'Novalnet ' . __( 'Prepayment', 'novalnet' );
			$this->enabled 				= $this->settings['enabled'];
			$this->title 				= $GLOBALS[ NN_FUNCS ]->get_payment_title( $this->settings );
			$this->description 			= $GLOBALS[ NN_FUNCS ]->get_payment_description( $this->settings );
			$this->test_mode 			= $this->settings['test_mode'];
			$this->instructions 		= $this->settings['instructions'];
			$this->end_user_info 		= $this->settings['end_user_info'];
			$this->email_notes 			= $this->settings['email_notes'];
			$this->payment_logo 		= $this->settings['payment_logo'];
			$this->novalnet_logo = $this->settings['novalnet_logo'];
			$this->set_order_status 	= $this->settings['set_order_status'];
			$this->callback_order_status= $this->settings['callback_order_status'];
			$this->reference1 			= isset($this->settings['reference1']) ? trim(strip_tags($this->settings['reference1'])) : null;
			$this->reference2 			= isset($this->settings['reference2']) ? trim(strip_tags($this->settings['reference2'])) : null;
			$this->icon = NOVALNET_URL .'assets/images/novalnet_prepayment.png';
			$this->has_fields 			= false;
			// Save options
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

			add_action('woocommerce_thankyou_' . $this->id, array(&$this, 'thankyou_page'));

			add_action('woocommerce_order_details_after_order_table', array($this, $this->id.'_transactional_info')); // Novalnet Transaction Information
			add_action('woocommerce_email_after_order_table', array($this, 'novalnet_email_instructions'), 15, 2); // customer email instruction

			if(isset($_SESSION['novalnet']['novalnet_prepayment']['prepayment_info_got']))
				unset($_SESSION['novalnet']['novalnet_prepayment']['prepayment_info_got']);
			if (isset($_SESSION['novalnet_email_notes_got']))
				unset($_SESSION['novalnet_email_notes_got']);
		}

		/**
		 * Initialise Gateway Settings Form Fields
		 *
		 * @access public
		 * @return void
		 */
		function init_form_fields() {

			$this->form_fields = array(
				'enabled' 		=> array(
					'title'  	=> __( 'Enable module', 'novalnet' ),
					'type'   	=> 'checkbox',
					'label'  	=> ' ',
					'default'	=> ''
				),
				'title_en' 		=> array(
					'title' 	=> __( 'Payment Title in English', 'novalnet' ),
					'type' 		=> 'text',
					'description'=> '',
					'default' 	=> 'Prepayment'
				),
				'description_en'	=> array(
					'title' 	=> __( 'Description  in English', 'novalnet' ),
					'type' 		=> 'textarea',
					'description'=> '',
					'default' 	=> 'The bank details will be emailed to you soon after the completion of checkout process.'
				),
				'title_de' 		=> array(
					'title' 	=> __( 'Payment Title in German', 'novalnet' ),
					'type' 		=> 'text',
					'description'=> '',
					'default' 	=> 'Vorauskasse'
				),
				'description_de'	=> array(
					'title' 	=> __( 'Description in German', 'novalnet' ),
					'type' 		=> 'textarea',
					'description'=> '',
					'default' 	=> 'Die Bankverbindung wird Ihnen nach Abschluss Ihrer Bestellung per E-Mail zugeschickt.'
				),
				'test_mode' 	=> array(
					'title' 	=> __( 'Enable Test Mode', 'novalnet' ),
					'type' 		=> 'select',
					'options' 	=> array( '0' => __( 'No', 'novalnet' ), '1' => __( 'Yes', 'novalnet' ) ),
					'description'=> '',
					'default' 	=> '0'
				),

				'end_user_info' => array(
					'title' 	=> __( 'Information to the end customer (this will appear in the payment page) ', 'novalnet' ),
					'type' 		=> 'textarea',
					'default' 	=> ''
				),
				'instructions' 	=> array(
					'title' 	=> __( 'Thank You page Instructions', 'novalnet' ),
					'type' 		=> 'textarea',
					'description'=> __( 'Instructions that will be added to the thank you page.', 'novalnet' ),
					'default' 	=> ''
				),
				'email_notes' 	=> array(
					'title' 	=> __( 'E-mail Instructions', 'novalnet' ),
					'type' 		=> 'textarea',
					'description'=> __( 'Instructions that will be added to the order confirmation email', 'novalnet' )
				),
				'payment_logo' 	=> array(
					'title' 	=> __( 'Enable Payment Logo', 'novalnet' ),
					'type' 		=> 'select',
					'options' 	=> array( '0' => __( 'No', 'novalnet' ), '1' => __( 'Yes', 'novalnet' ) ),
					'description'=> __( 'To display Payment logo in front end', 'novalnet' ),
					'default' 	=> '1'
				),
				'novalnet_logo' => array(
					'title' 	=> __( 'Enable Novalnet Logo', 'novalnet' ),
					'type' 		=> 'select',
					'options' 	=> array( '0' => __( 'No', 'novalnet' ), '1' => __( 'Yes', 'novalnet' ) ),
					'description' => __( 'To display Novalnet logo in front end', 'novalnet' ),
					'default' 	=> '1'
				),
				'set_order_status' => array(
					'title' 	=> __( 'Order Completion Status', 'novalnet' ),
					'type' 		=> 'select',
					'options' 	=> $GLOBALS[ NN_FUNCS ]->get_order_status(),
					'description'=> '',
				),
				'callback_order_status' 	=> array(
					'title' 	=> __( 'Callback order status', 'novalnet' ),
					'type' 		=> 'select',
					'options' 	=> $GLOBALS[ NN_FUNCS ]->get_order_status(),
					'description'=> '',
					'default' 	=> '0'
				),
				'reference1' 	=> array(
					'title' 	=> __( 'Transaction reference 1', 'novalnet' ),
					'type' 		=> 'text',
					'default' 	=> '',
					'description'=> __( 'This will appear in the transactions details / account statement.', 'novalnet' )
				),
				'reference2' 	=> array(
					'title' 	=> __( 'Transaction reference 2', 'novalnet' ),
					'type' 		=> 'text',
					'default' 	=> '',
					'description'=> __( 'This will appear in the transactions details / account statement.', 'novalnet' )
				)
			);
		}

		/**
		 * set current
		 */
		public function set_current() {
			$this->chosen = true;
		}   // End set_current()

		/**
		 * Displays payment logo icon
		 */
		public function get_icon(){
			$icon_html = '';
			if ( $this->payment_logo ) {
				$icon_html = '<img src="' . $this->icon . '" alt="' . __($this->title , 'novalnet') . '" title="' . __($this->title , 'novalnet') . '" />';
			}
			return apply_filters('woocommerce_gateway_icon', $icon_html, $this->id);
		}

		/**
		 * Displays gateway_title
		 */
		public function get_title() {
			$novalnet_logo_html = '';
			if ( $this->novalnet_logo && isset( $_SERVER['HTTP_REFERER'] ) && ! strstr( $_SERVER['HTTP_REFERER'] , 'wp-admin' ) && ( ( isset( $_REQUEST['action'] ) && $_REQUEST['action'] == 'woocommerce_update_order_review' && isset( $_SERVER['HTTP_X_REQUESTED_WITH'] ) && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest' ) || ( ! isset( $_REQUEST['woocommerce_pay'] ) && isset( $_GET['pay_for_order'] ) && isset( $_GET['order_id'] ) ) ) ) {
				$novalnet_logo_html = '<img width="90px" src="' . NOVALNET_URL .'assets/images/novalnet_logo.png" alt="NOVALNET AG" title="Novalnet AG" />';
			}

			return apply_filters( 'woocommerce_gateway_title', $novalnet_logo_html . $this->title, $this->id );
		}   // End get_title()

		public function validate_fields() {
			if( $GLOBALS[ NN_FUNCS ]->validate_global_settings( $GLOBALS[ NN_CONFIG ]->global_settings ) ){
				$this->add_display_info(__( __('Basic parameter not valid', 'novalnet') ,'novalnet'), 'error');
				return($this->return_redirect_page('success', WC()->cart->get_checkout_url()));
			}
			return true;
		}

		public function payment_fields(){

			// payment description
			if ($this->description)
				echo wpautop( $this->description );

			if ($this->test_mode == 1)
				echo wpautop('<strong><font color="red">' . __('Please Note: This transaction will run on TEST MODE and the amount will not be charged', 'novalnet') . '</font></strong>');

			if($this->end_user_info)
				echo wpautop($this->end_user_info);

		}

		public function process_payment( $order_id ){
			return $this->generate_payment_parameters($order_id, $this);
		}

		/**
		 * Add text to order confirmation mail to customer below order table
		 * called by 'woocommerce_email_after_order_table' action
		 *
		 * @access public
		 * @return void
		 */
		public function novalnet_email_instructions($order, $sent_to_admin) {

			if ($order->payment_method == $this->id && (!isset($_SESSION['novalnet_email_notes_got']) || (($sent_to_admin == 0) && (!isset($_SESSION['novalnet_email_notes_got']) || $_SESSION['novalnet_email_notes_got'] != 2)))) {

				// email instructions
				if ($this->email_notes)
					echo wpautop(wptexturize($this->email_notes));

				if ($sent_to_admin)
					$_SESSION['novalnet_email_notes_got'] = 1;
				else
					$_SESSION['novalnet_email_notes_got'] = 2;
			}
			$order->customer_note = wpautop($order->customer_note);
		}	//End novalnet_email_instructions()

		public function novalnet_prepayment_transactional_info($order) {
			if (!isset($_SESSION['novalnet']['novalnet_prepayment']['prepayment_info_got']) && $order->payment_method == $this->id) {
				// Novalnet Transaction Information
				echo wpautop('<h2>' . __('Transaction Information', 'novalnet') . '</h2>');
				echo wpautop(wptexturize($order->customer_note));
				$_SESSION['novalnet']['novalnet_prepayment']['prepayment_info_got'] =1;
			}
		}	// End novalnet_prepayment_transactional_info()

	}   // End class novalnet_prepayment

	$obj = new WC_Gateway_Novalnet_Prepayment();
}   // End init_gateway_novlanet_prepayment()

/**
 * Add the gateway to WooCommerce
 * @access public
 * @param array $methods
 * @package
 * @return array
 */
function add_gateway_novalnet_prepayment($methods) {
    global $novalnet_payment_methods;
    $methods[] = NOVALNET_PT_CLASS;
    return $methods;
}   // End add_gateway_novalnet_prepayment()

add_filter('woocommerce_payment_gateways', 'add_gateway_' . $novalnet_payment_methods['nn_prepayment']);
?>
