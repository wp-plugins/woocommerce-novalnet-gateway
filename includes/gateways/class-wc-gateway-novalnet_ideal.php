<?php
/**
 * iDEAL Payment Gateway by Novalnet
 *
 * Copyright (c) 2015 Novalnet AG <https://www.novalnet.de>
 *
 * Released under the GNU General Public License. This free
 * contribution made by request.If you have found this script
 * usefull a small recommendation as well as a comment on
 * merchant form would be greatly appreciated.
 *
 * This gateway is used for real time processing of German Bankdata of customers.
 *
 * @class 		WC_Gateway_Novalnet_Ideal
 * @extends		WC_Novalnet_Payment_Gateway
 * @version		2.0.0
 * @package		woocommerce-novalnet-gateway/includes/gateways/novalnet_ideal
 * @author 		Novalnet
 * @link		https://www.novalnet.de
 * @copyright	2015 Novalnet AG <https://www.novalnet.de>
 * @license     GNU General Public License version 2.0
 */

/*** Install iDEAL payment to Novalnet Payment Gateway ***/
add_action('plugins_loaded', 'init_gateway_' . $novalnet_payment_methods['nn_ideal'], 0);

function init_gateway_novalnet_ideal() {

    global $novalnet_payment_methods;

    class WC_Gateway_Novalnet_Ideal extends WC_Novalnet_Payment_Gateway {

		var $id 		  = NOVALNET_ID;
		var $payment_key  = PAYMENT_KEY_ID;
		var $payment_type = 'IDEAL';

		public function __construct() {

			// Load the settings
			$this->init_form_fields();
			$this->init_settings();
			$this->method_title = 'Novalnet ' . __( 'iDEAL', 'novalnet' );
			$this->enabled 		= $this->settings['enabled'];
			$this->title 		= $GLOBALS[ NN_FUNCS ]->get_payment_title( $this->settings );
			$this->description 	= $GLOBALS[ NN_FUNCS ]->get_payment_description( $this->settings );
			$this->test_mode 	= $this->settings['test_mode'];
			$this->instructions = $this->settings['instructions'];
			$this->end_user_info= $this->settings['end_user_info'];
			$this->email_notes 	= $this->settings['email_notes'];
			$this->payment_logo = $this->settings['payment_logo'];
			$this->novalnet_logo = $this->settings['novalnet_logo'];
			$this->set_order_status = $this->settings['set_order_status'];
			$this->icon 		= NOVALNET_URL .'assets/images/novalnet_ideal.png';
			$this->has_fields 	= false;
			$this->reference1 	= isset($this->settings['reference1']) ? trim(strip_tags($this->settings['reference1'])) : null;
			$this->reference2 	= isset($this->settings['reference2']) ? trim(strip_tags($this->settings['reference2'])) : null;
			// Save options
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

			add_action('woocommerce_receipt_' . $this->id, array(&$this, 'receipt_page'));
			add_action('woocommerce_thankyou_' . $this->id, array(&$this, 'thankyou_page'));
			add_action('init', array(&$this, 'check_ideal_response'));
			add_action('woocommerce_order_details_after_order_table', array($this, $this->id.'_transactional_info')); // Novalnet Transaction Information
			add_action('woocommerce_email_after_order_table', array($this, 'novalnet_email_instructions'), 15, 2); // customer email instruction

			if(isset($_SESSION['novalnet']['novalnet_ideal']['ideal_info_got']))
				unset($_SESSION['novalnet']['novalnet_ideal']['ideal_info_got']);

			if(isset($_SESSION['novalnet']['novalnet_ideal_receipt_page_got']))
				unset($_SESSION['novalnet']['novalnet_ideal_receipt_page_got']);

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
					'default' 	=> 'iDEAL'
				),
				'description_en'	=> array(
					'title' 	=> __( 'Description in English', 'novalnet' ),
					'type' 		=> 'textarea',
					'description'=> '',
					'default' 	=> 'You will be redirected to Novalnet AG website when you place the order.'
				),
				'title_de' 		=> array(
					'title' 	=> __( 'Payment Title in German', 'novalnet' ),
					'type' 		=> 'text',
					'description'=> '',
					'default' 	=> 'iDEAL'
				),
				'description_de'	=> array(
					'title' 	=> __( 'Description in German', 'novalnet' ),
					'type' 		=> 'textarea',
					'description'=> '',
					'default' 	=> 'Sie werden zur Website der Novalnet AG umgeleitet, sobald Sie die Bestellung bestätigen. '
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

			if ($this->description)
				echo wpautop( $this->description );

			if ($this->test_mode == 1)
				echo wpautop('<strong><font color="red">' . __('Please Note: This transaction will run on TEST MODE and the amount will not be charged', 'novalnet') . '</font></strong>');

			if($this->end_user_info)
				echo wpautop($this->end_user_info);

		}

		public function process_payment( $order_id ){

			$order = new WC_Order($order_id);
			return array(
				'result' 	=> 'success',
				'redirect'	=> $order->get_checkout_payment_url( true )
			);
		}

		/**
		 * Receipt_page
		 */
		function receipt_page( $order_id ) {
			if (!isset($_SESSION['novalnet']['novalnet_ideal_receipt_page_got'])) {
				echo '<p>' . __('Thank you for choosing Novalnet payment.', 'novalnet') . '</p>';
				echo $this->get_novalnet_ideal_form_html($order_id);
				$_SESSION['novalnet']['novalnet_ideal_receipt_page_got'] = 1;
			}
		}

		/**
		 * Generate Novalnet secure form
		 */
		public function get_novalnet_ideal_form_html($order_id) {
			global $woocommerce;
			$order = new WC_Order( $order_id );
			$payment_parameters = $this->generate_payment_parameters($order_id, $this);
			$payment_parameters['lang'] = strtoupper( substr( get_bloginfo( 'language' ), 0, 2 ) );
			$payment_parameters['language'] = strtoupper( substr( get_bloginfo( 'language' ), 0, 2 ) );
			$novalnet_args_array = array();
			foreach ($payment_parameters as $key => $value) {
				$novalnet_args_array[] = '<input type="hidden" name="' . esc_attr($key) . '" id="' . esc_attr($key) . '" value="' . esc_attr($value) . '" />';
			}

			$script = '
				$.blockUI({
					message: "' . esc_js( __( 'You will be redirected to Novalnet AG in a few seconds.', 'novalnet' ) ) . '",
					baseZ: 99999,
					overlayCSS:
					{
						background: "#fff",
						opacity: 0.6
					},
					css: {
						padding:        "20px",
						zindex:         "9999999",
						textAlign:      "center",
						color:          "#555",
						border:         "3px solid #aaa",
						backgroundColor:"#fff",
						cursor:         "wait",
						lineHeight:		"24px",
					}
				});
				jQuery("#submit_novalnet_ideal_payment_form").click();
			';

			if (version_compare($woocommerce->version, '2.1.0', '>=')){
				wc_enqueue_js($script );
			}else{
				$woocommerce->add_inline_js($script );
			}

			return '<form id="frmnovalnet" name="frmnovalnet" action="' . (is_ssl() ? 'https://' : 'http://'). SOFORT_PAYPORT_URL . '" method="post" target="_top">' . implode('', $novalnet_args_array) . '
			<input type="submit" class="button-alt" id="submit_novalnet_ideal_payment_form" value="' . __('Pay via Novalnet', 'novalnet') . '" /> <a class="button cancel" href="' . esc_url($order->get_cancel_order_url()) . '">' . __('Cancel order &amp; restore cart', 'novalnet') . '</a>
		</form>';
		}   // End get_novalnet_form_html()

		function check_ideal_response(){
			if(isset($_REQUEST['status']) && isset( $_REQUEST['user_variable_0'] ) && $_REQUEST['payment_type'] == "IDEAL" && isset($_REQUEST['input1'])){
				$response = array_map( "trim", $_REQUEST );
				if ( isset( $response['hash'] ) && ! $this->novalnet_check_hash( $response ) ) {
					$this->add_display_info(__( __('Check Hash failed', 'novalnet') ,'novalnet'), 'error');
					wp_safe_redirect( WC()->cart->get_checkout_url() );
					exit();
				} else {
					$this->check_redirect_response( $response, $this );
				}
			}
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

		public function novalnet_ideal_transactional_info($order) {
			if (!isset($_SESSION['novalnet']['novalnet_ideal']['ideal_info_got']) && $order->payment_method == $this->id) {
				echo wpautop('<h2>' . __('Transaction Information', 'novalnet') . '</h2>');
				echo wpautop(wptexturize($order->customer_note));
				$_SESSION['novalnet']['novalnet_ideal']['ideal_info_got'] = 1;
			}
		}	// End novalnet_ideal_transactional_info()

	}   // End class NOVALNET_ID_CLASS

	$obj = new WC_Gateway_Novalnet_Ideal();
}   // End init_gateway_novalnet_ideal()

/**
 * Add the gateway to WooCommerce
 * @access public
 * @param array $methods
 * @package
 * @return array
 */
function add_gateway_novalnet_ideal($methods) {
    global $novalnet_payment_methods;
    $methods[] = NOVALNET_ID_CLASS;
    return $methods;
}   // End add_gateway_novalnet_ideal()

add_filter('woocommerce_payment_gateways', 'add_gateway_' . $novalnet_payment_methods['nn_ideal']);
?>
