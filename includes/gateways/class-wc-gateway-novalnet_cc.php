<?php
/**
 * Credir Card / Credit Card 3D Secure Payment Gateway by Novalnet
 *
 * Copyright (c) 2015 Novalnet AG <https://www.novalnet.de>
 *
 * Released under the GNU General Public License. This free
 * contribution made by request.If you have found this script
 * usefull a small recommendation as well as a comment on
 * merchant form would be greatly appreciated.
 *
 * This gateway is used for real time processing of Credit card data of customers.
 *
 * @class 		WC_Gateway_Novalnet_Cc
 * @extends		WC_Novalnet_Payment_Gateway
 * @version		2.0.0
 * @package		woocommerce-novalnet-gateway/includes/gateways/novalnet_cc
 * @author 		Novalnet
 * @link		https://www.novalnet.de
 * @copyright	2015 Novalnet AG <https://www.novalnet.de>
 * @license     GNU General Public License version 2.0
 */

/*** Install CC / CREDIT CARD payment to Novalnet Payment Gateway ***/
add_action('plugins_loaded', 'init_gateway_' . $novalnet_payment_methods['nn_creditcard'], 0);

function init_gateway_novalnet_cc() {

    global $novalnet_payment_methods;

	class WC_Gateway_Novalnet_Cc extends WC_Novalnet_Payment_Gateway {

		var $id 			= NOVALNET_CC;
		var $payment_key 	= PAYMENT_KEY_CC;
		var $payment_type 	= 'CREDITCARD';
		var $supports 		= array( 'default_credit_card_form' );
		var $card_details 	= array();

		public function __construct() {

			if ( ! isset( $_SESSION ) ) {
				session_start();
			}
			// Load the settings
			$this->init_form_fields();
			$this->init_settings();
			$this->method_title 	= 'Novalnet ' .__( 'Credit Card', 'novalnet' );
			$this->enabled 			= $this->settings['enabled'];
			$this->title 			= $GLOBALS[ NN_FUNCS ]->get_payment_title( $this->settings );
			$this->description 		= $GLOBALS[ NN_FUNCS ]->get_payment_description( $this->settings );
			$this->test_mode 		= $this->settings['test_mode'];
			$this->cc_secure 		= $this->settings['cc_secure'];
			$this->amex_accept 		= $this->settings['amex_accept'];
			$this->manual_limit     = $this->settings['manual_limit'];
			$this->product_id2      = $this->settings['product_id2'];
			$this->tariff_id2       = $this->settings['tariff_id2'];
			$this->auto_refill		= $this->settings['auto_refill'];
			$this->valid_year_limit = $this->settings['valid_year_limit'];
			$this->end_user_info 	= $this->settings['end_user_info'];
			$this->instructions 	= $this->settings['instructions'];
			$this->email_notes 		= $this->settings['email_notes'];
			$this->payment_logo 	= $this->settings['payment_logo'];
			$this->novalnet_logo 	= $this->settings['novalnet_logo'];
			$this->set_order_status = $this->settings['set_order_status'];
			$this->has_fields 		= true;
			$this->icon 			= NOVALNET_URL .'assets/images/novalnet_cc.png';
			$this->reference1 		= isset( $this->settings['reference1'] ) ? trim( strip_tags( $this->settings['reference1'] ) ) : null;
			$this->reference2 		= isset ( $this->settings['reference2'] ) ? trim( strip_tags( $this->settings['reference2'] ) ) : null;

			if ( $this->amex_accept == 1 )
				$this->icon = NOVALNET_URL .'assets/images/novalnet_cc_amex.png';

			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			add_action( 'woocommerce_thankyou_' . $this->id, array( &$this, 'thankyou_page' ) );
			add_action( 'woocommerce_order_details_after_order_table', array( $this, $this->id. '_transactional_info') );
			add_action( 'woocommerce_email_after_order_table', array( $this, 'novalnet_email_instructions' ), 15, 2 );
			if ( isset( $this->cc_secure ) && $this->cc_secure ) {
				add_action( 'woocommerce_receipt_' . $this->id, array( &$this, 'receipt_page' ) );
			}

			add_action( 'init', array( &$this, 'check_cc3d_response' ) );


			if ( isset( $_SESSION['novalnet']['novalnet_cc']['novalnet_cc_receipt_page_got'] ) )
				 unset( $_SESSION['novalnet']['novalnet_cc']['novalnet_cc_receipt_page_got'] );
			if ( isset( $_SESSION['novalnet']['novalnet_cc']['cc_info_got'] ) )
				 unset( $_SESSION['novalnet']['novalnet_cc']['cc_info_got'] );
			if ( isset( $_SESSION['novalnet_email_notes_got'] ) )
				 unset( $_SESSION['novalnet_email_notes_got'] );
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
					'title'  	=> __( 'Enable module', 'novalnet' ) ,
					'type'   	=> 'checkbox',
					'label'  	=> ' ',
					'default'	=> ''
				),
				'title_en' 		=> array(
					'title' 	=> __( 'Payment Title in English', 'novalnet' ),
					'type' 		=> 'text',
					'description'=> '',
					'default' 	=> 'Credit Card'
				),
				'description_en'	=> array(
					'title' 	=> __( 'Description in English', 'novalnet' ),
					'type' 		=> 'textarea',
					'description'=> '',
					'default' 	=> 'The amount will be booked immediately from your credit card when you submit the order.'
				),
				'title_de' 		=> array(
					'title' 	=> __( 'Payment Title in German', 'novalnet' ),
					'type' 		=> 'text',
					'description'=> '',
					'default' 	=> 'Kreditkarte'
				),
				'description_de'	=> array(
					'title' 	=> __( 'Description in German', 'novalnet' ),
					'type' 		=> 'textarea',
					'description'=> '',
					'default' 	=> 'Die Belastung Ihrer Kreditkarte erfolgt mit dem Abschluss der Bestellung.
'
				),
				'test_mode' 	=> array(
					'title' 	=> __( 'Enable Test Mode', 'novalnet' ),
					'type' 		=> 'select',
					'options' 	=> array( '0' => __( 'No', 'novalnet' ), '1' => __( 'Yes', 'novalnet' ) ),
					'description'=> '',
					'default' 	=> '0'
				),
				'cc_secure' 	=> array(
					'title' 	=> __( '3D Secure (Note: this has to be set up at Novalnet first. Please contact support@novalnet.de, in case you wish this)', 'novalnet' ),
					'type' 		=> 'select',
					'options' 	=> array( '0' => __( 'No', 'novalnet' ), '1' => __( 'Yes', 'novalnet' ) ),
					'description'=> __( '(Please note that this procedure has a low acceptance among end customers.) As soon as 3D-Secure is activated for credit cards, the bank prompts the end customer for a password, to prevent credit card abuse. This can serve as a proof, that the customer is actually the owner of the credit card. ', 'novalnet'),
					'default' 	=> '0'
				),
				'amex_accept' 	=> array(
					'title' 	=> __( 'Enable AMEX card type', 'novalnet' ),
					'type' 		=> 'select',
					'options' 	=> array( '0' => __( 'No', 'novalnet' ), '1' => __( 'Yes', 'novalnet' ) ),
					'default' 	=> '0'
				),
				'auto_refill' 	=> array(
					'title' 	=> __( 'Auto refill the payment data entered in payment page', 'novalnet' ),
					'type' 		=> 'select',
					'options' 	=> array( '0' => __( 'No', 'novalnet' ), '1' => __( 'Yes', 'novalnet' ) ),
					'description'=> __( 'If yes, the entered payment data will be automatically refilled while error handling', 'novalnet'),
					'default' 	=> '0'
				),
				'manual_limit' => array(
					'title' 	 => __( 'Manual checking of order, above amount in cents (Note: this is a onhold booking, needs your manual verification and activation) ', 'novalnet' ),
					'type' 		 => 'text',
					'label' 	 => '',
					'default' 	 => '',
					'description'=> __( 'All the orders above this amount will be set on hold by Novalnet and only after your manual verifcation and confirmation at Novalnet the booking will be done ', 'novalnet' )
                ),
                'product_id2' => array(
					'title' 	 => __( 'Second Product ID for manual check condition ', 'novalnet' ),
					'type' 		 => 'text',
					'label' 	 => '',
					'default' 	 => '',
					'description'=> __( 'Second Product ID in Novalnet to use the manual check condition ', 'novalnet' )
                ),
                'tariff_id2' => array(
					'title' 	 => __( 'Second Tariff ID for manual check condition ', 'novalnet' ),
					'type' 		 => 'text',
					'label' 	 => '',
					'default' 	 => '',
					'description'=> __( ' Second Tariff ID in Novalnet to use the manual check condition ', 'novalnet' )
                ),
				'valid_year_limit'=> array(
					'title' 	 => __('Valid year limit in payment form', 'novalnet') ,
					'type' 		 => 'text',
					'description'=> __('Set the number of years display in the payment form. Default 25 years from the current year', 'novalnet'),
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
					'description'=> ''
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
		}

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
			$error = '';
			$cvc_val = $GLOBALS[ NN_FUNCS ]->is_digits( $_POST['novalnet_cc_cvc'], true );

			if( $GLOBALS[ NN_FUNCS ]->validate_global_settings( $GLOBALS[ NN_CONFIG ]->global_settings ) ){
				$error = __('Basic parameter not valid', 'novalnet');
			} else if (isset($this->manual_limit) && $this->manual_limit && !$GLOBALS[ NN_FUNCS ]->is_digits($this->manual_limit)){
					$error = __('Manual limit amount / Product-ID2 / Tariff-ID2 is not valid!', 'novalnet');
			} else if (isset($this->manual_limit) && intval($this->manual_limit) > 0) {
				if (!$GLOBALS[ NN_FUNCS ]->is_digits($this->product_id2) || !$GLOBALS[ NN_FUNCS ]->is_digits($this->tariff_id2) )
					$error = __('Manual limit amount / Product-ID2 / Tariff-ID2 is not valid!', 'novalnet');
			} else if ( ( empty ( $cvc_val ) || empty( $_POST['nn_cc_hash'] ) || empty ( $_POST['nn_cc_uniqueid']  ) ) ) {
				$error = __('Please enter valid credit card details!', 'novalnet');
			}
			if( $error ){
				$this->add_display_info(__( $error ,'novalnet'), 'error');
				return($this->return_redirect_page('success', WC()->cart->get_checkout_url()));
			}else {
				if ( $this->auto_refill ) {
					$_SESSION['novalnet']['novalnet_cc'] = $_POST;#For refilling purpose
				}
				$this->card_details = array(
					'cc_holder' => $_POST['novalnet_cc_holder'],
					'cvc' 		=> $_POST['novalnet_cc_cvc'],
					'panhash' 	=> $_POST['nn_cc_hash'],
					'uniqid' 	=> $_POST['nn_cc_uniqueid'],
				);
				if ( isset( $this->cc_secure ) &&  $this->cc_secure == 1 ) {
					$_SESSION['novalnet_cc']['card_details'] = $this->card_details;
				}
			}

			return true;
		}

		public function payment_fields() {
			// payment description
			if ($this->description)
				echo wpautop( $this->description );

			if ($this->test_mode == 1)
				echo wpautop( '<strong><font color="red">' . __('Please Note: This transaction will run on TEST MODE and the amount will not be charged', 'novalnet') . '</font></strong>' );

			if($this->end_user_info)
				echo wpautop( $this->end_user_info );

			if ( WC()->session->chosen_payment_method != $this->id && isset($_SESSION['novalnet'][ $this->id ]) ) {
				$_SESSION['novalnet'][$this->id] = false;
			}

			echo '<div class="cc_loader" id="cc_loader" style="display:none"></div>';
			$default_fields = array(
				'card_type_field' => '<tr><td style="border:0;">
					<label for="' . esc_attr( $this->id ) . '_type">' . __( 'Credit Card Type', 'novalnet' ) . ' <span class="required">*</span></label></td>
					<td style="border:0;">
					<select id="'.esc_attr( $this->id ).'_type">'.  $this->get_creditcard_type().'</select>
				</td></tr>',
				'card_holder_field' => '<tr><td style="border:0;">
					<label for="' . esc_attr( $this->id ) . '_holder">' . __( 'Credit Card Holder', 'novalnet' ) . ' <span class="required">*</span></label> </td>
					<td style="border:0;">
					<input id="' . esc_attr( $this->id ) . '_holder" class="input-text" type="text"  autocomplete="off" name="' . (  $this->id . '_holder'  ) . '"  />
				</td></tr>',
				'card_number_field' => '<tr><td style="border:0;">
					<label for="' . esc_attr( $this->id ) . '_no">' . __( 'Credit Card Number', 'novalnet' ) . ' <span class="required">*</span></label></td>
					<td style="border:0;">
					<input id="' . esc_attr( $this->id ) . '_no" class="input-text" type="text" autocomplete="off" onkeypress="return isNumberKey(event, true);"  />
				</td></tr>',
				'card_expiry_field' => '<tr><td style="border:0;">
					<label for="' . esc_attr( $this->id ) . '_expiry_date">' . __( 'Valid Date (Month/Year)', 'novalnet' ) . ' <span class="required">*</span></label></td>
					<td style="border:0;">
					<select id="'.esc_attr( $this->id ).'_exp_month" >'. $this->get_valid_expiry_month().'</select>
					<select id="'.esc_attr( $this->id ).'_exp_year" >'. $this->get_valid_expiry_year().'</select>
				</td></tr>',
				'card_cvc_field' => '<tr><td style="border:0;">
					<label for="' . esc_attr( $this->id ) . '_cvc">' . __( 'CVC (Approval Code)', 'novalnet' ) . ' <span class="required">*</span></label></td>
					<td style="border:0;"> 	<input id="' . esc_attr( $this->id ) . '_cvc" class="input-text" type="text" autocomplete="off" name="' . (  $this->id . '_cvc'  ) . '" onkeypress="return isNumberKey(event, false);" /><span  id="novalnet_cc_cvc_hint"> <img src="'.NOVALNET_URL.'assets/images/novalnet_cc_cvc_hint.png" border="0" style="margin-top:0px;" alt="CCV/CVC?"> <span id="novalnet_cc_cvc_href"><img src="'.NOVALNET_URL.'assets/images/novalnet_cc_cvc_href.png"></span></span></td></tr>',
			);

			if(!empty($default_fields)){
				?>
				<fieldset class="nn_form" border="0" id="<?php echo $this->id; ?>_form">
				<?php do_action('woocommerce_credit_card_form_start', $this->id ); ?>
				<?php
					echo '<input type="hidden" id="nn_vendor"  name="nn_vendor" value="' . $GLOBALS[ NN_CONFIG ]->global_settings['vendor_id'] . '"/> <input type="hidden" id="nn_auth_code"  name="nn_auth_code" value="' . $GLOBALS[ NN_CONFIG ]->global_settings['auth_code'] . '"/> <input type="hidden" id="nn_cc_hash"  name="nn_cc_hash" value=""/> <input type="hidden" id="nn_cc_uniqueid"  name="nn_cc_uniqueid" value="'.$GLOBALS[ NN_FUNCS ]->generate_random_string(30).'"/> <input type="hidden" id="nn_cc_input_panhash"  name="nn_cc_input_panhash" value="'.$this->getCreditCardRefillHash().'"/> <input type="hidden" id="novalnet_cc_customer_info" name="novalnet_cc_customer_info" value="' . $GLOBALS[NN_FUNCS]->get_customer_info() . '" /> <input type="hidden" id="nn_cc_valid_error_ccmessage"  name="nn_cc_valid_error_ccmessage" value="'.__('Please enter valid credit card details!','novalnet').'"/> <input type="hidden" id="nn_merchant_valid_error_ccmessage"  name="nn_merchant_valid_error_ccmessage" value="'.__('Please enter valid Merchant Credentials','novalnet').'"/> <link rel="stylesheet" type="text/css" media="all" href="'.NOVALNET_URL.'assets/css/novalnet_cc.css"> <script src="'.NOVALNET_URL.'assets/js/novalnet_cc.js" type="text/javascript"></script> <script src="'.NOVALNET_URL.'assets/js/novalnet_checkout.js" type="text/javascript"></script>';

					echo "<table width='100%' style='border-style:none;'>";
					foreach ( $default_fields as $field ) {
						echo $field;
					}
					echo "</table>";
				?>
				<?php do_action('woocommerce_credit_card_form_end', $this->id ); ?>
				<div class="clear"></div>
				</fieldset>
				<?php
			}
		}

		public function get_creditcard_type() {
			$cc_type = array( "VI" => 'Visa' , "MC" => 'Mastercard');
			$option ="<option value=''>".__('--Please Select--', 'novalnet')."</option>";
			if($this->amex_accept == 1){
				$cc_type['AE'] = 'AMEX';
			}
			foreach($cc_type as $k => $v){
				$option .= "<option value=".$k.">".$v."</option>";
			}
			return $option;
		}

		public function get_valid_expiry_month(){
			$cc_month = "<option value=''>".__('Month', 'novalnet')."</option>";

			for ($i = 1; $i <= 12; $i++) {
				$i_val = (($i<=9)?'0'.$i:$i);
				$cc_month  .= "<option value=".$i_val.">".$i_val."</option>";
			}
			return $cc_month;
		}

		public function get_valid_expiry_year() {
			$cc_year = "<option value=''>".__('Year', 'novalnet')."</option>";
			$today = getdate();
			$limit = 25;
			if(isset($this->valid_year_limit) && trim($this->valid_year_limit) > 0 && $GLOBALS[ NN_FUNCS ]->is_digits($this->valid_year_limit)){
				$limit = $this->valid_year_limit;
			}

			for ($i = $today['year']; $i < ($today['year']+$limit); $i++) {
				$cc_year .= "<option value=".$i.">".$i."</option>";
			}

			return $cc_year;
		}

		/*
		* Return refill hash - CREDIT CARD
		*
		* @return HASH
		*/
		public function getCreditCardRefillHash() {
			if( $this->auto_refill ) {
				if(isset($_SESSION['novalnet']['novalnet_cc']['nn_cc_hash']) && $_SESSION['novalnet']['novalnet_cc']['nn_cc_hash'] != ''){
					return $_SESSION['novalnet']['novalnet_cc']['nn_cc_hash'];
				}
			}
			return '';
		}

		public function process_payment( $order_id ) {
			if(!isset($this->cc_secure) || $this->cc_secure == 0){
				return $this->generate_payment_parameters($order_id, $this);
			}else{
				$order = new WC_Order($order_id);
				return array(
					'result' 	=> 'success',
					'redirect'	=> $order->get_checkout_payment_url( true )
				);
			}
		}

		/**
		 * Receipt_page
		 */
		function receipt_page( $order_id ) {
			if (!isset($_SESSION['novalnet']['novalnet_cc']['novalnet_cc_receipt_page_got'])) {
				echo '<p>' . __('Thank you for your order, please click the button below to pay with Novalnet.', 'novalnet') . '</p>';
				echo $this->get_novalnet_cc_form_html($order_id);
				$_SESSION['novalnet']['novalnet_cc']['novalnet_cc_receipt_page_got'] = 1;
			}
		}

		/**
		 * Generate Novalnet secure form
		 */
		public function get_novalnet_cc_form_html($order_id) {
			global $woocommerce;
			$order = new WC_Order( $order_id );
			$this->card_details = $_SESSION['novalnet_cc']['card_details'];
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
				jQuery("#submit_novalnet_cc_payment_form").click();
			';

			if (version_compare($woocommerce->version, '2.1.0', '>=')){
				wc_enqueue_js($script );
			}else{
				$woocommerce->add_inline_js($script );
			}

			return '<form id="frmnovalnet" name="frmnovalnet" action="' . (is_ssl() ? 'https://' : 'http://').PCI_PAYPORT_URL . '" method="post" target="_top">' . implode('', $novalnet_args_array) . '
			<input type="submit" class="button-alt" id="submit_novalnet_cc_payment_form" value="' . __('Pay via Novalnet', 'novalnet') . '" /> <a class="button cancel" href="' . esc_url($order->get_cancel_order_url()) . '">' . __('Cancel order &amp; restore cart', 'novalnet') . '</a>
			</form>';
		}   // End get_novalnet_form_html()

		function check_cc3d_response(){
			if(isset($_REQUEST['status']) && isset( $_REQUEST['user_variable_0'] ) && $_REQUEST['payment_type'] == "CREDITCARD" && isset($_REQUEST['input1'])){
				$response = array_map("trim", $_REQUEST);
				$this->check_redirect_response( $response, $this );
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

		public function novalnet_cc_transactional_info($order) {
			if (!isset($_SESSION['novalnet']['novalnet_cc']['cc_info_got']) && $order->payment_method == $this->id) {
				// Novalnet Transaction Information
				echo wpautop('<h2>' . __('Transaction Information', 'novalnet') . '</h2>');
				echo wpautop(wptexturize($order->customer_note));
				$_SESSION['novalnet']['novalnet_cc']['cc_info_got'] = 1;
			}
		}	// End novalnet_cc_transactional_info()
	}   // End class NOVALNET_CC_CLASS
	$obj = new WC_Gateway_Novalnet_Cc();

}   // End init_gateway_novalnet_cc()

/**
 * Add the gateway to WooCommerce
 * @access public
 * @param array $methods
 * @package
 * @return array
 */
function add_gateway_novalnet_cc($methods) {
    global $novalnet_payment_methods;
    $methods[] = NOVALNET_CC_CLASS;
    return $methods;
}	// End add_gateway_novalnet_cc()

add_filter('woocommerce_payment_gateways', 'add_gateway_' . $novalnet_payment_methods['nn_creditcard']);
?>
