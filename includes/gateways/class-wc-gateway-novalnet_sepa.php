<?php
/**
 * Direct Debit SEPA Payment Gateway by Novalnet
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
 * @class      WC_Gateway_Novalnet_Sepa
 * @extends    WC_Novalnet_Payment_Gateway
 * @version    2.0.0
 * @package    woocommerce-novalnet-gateway/includes/gateways/novalnet_sepa
 * @author     Novalnet
 * @link       https://www.novalnet.de
 * @copyright  2015 Novalnet AG <https://www.novalnet.de>
 * @license    GNU General Public License version 2.0
 */

 /*** Install Direct Debit SEPA payment to Novalnet Payment Gateway ***/
 add_action( 'plugins_loaded', 'init_gateway_' . $novalnet_payment_methods['nn_directdebitsepa'], 0 );

 function init_gateway_novalnet_Sepa() {

    global $novalnet_payment_methods;

	class WC_Gateway_Novalnet_Sepa extends WC_Novalnet_Payment_Gateway {

		var $id 			= NOVALNET_SEPA;
		var $payment_key 	= PAYMENT_KEY_SEPA;
		var $payment_type 	= 'DIRECT_DEBIT_SEPA';
		var $supports 	= array( 'default_credit_card_form' );
		var $sepa_details = array();

		public function __construct() {

			// Load the settings
			$this->init_form_fields();
			$this->init_settings();
			$this->method_title     = 'Novalnet ' . __( 'Direct Debit SEPA', 'novalnet' );
			$this->enabled          = $this->settings['enabled'];

			$this->test_mode        = $this->settings['test_mode'];
			$this->manual_limit     = $this->settings['manual_limit'];
			$this->product_id2      = $this->settings['product_id2'];
			$this->tariff_id2       = $this->settings['tariff_id2'];
			$this->sepa_duration	= $this->settings['payment_duration'];
			$this->auto_refill    	= $this->settings['auto_refill'];
			$this->end_user_info    = $this->settings['end_user_info'];
			$this->instructions     = $this->settings['instructions'];
			$this->email_notes      = $this->settings['email_notes'];
			$this->payment_logo     = $this->settings['payment_logo'];
			$this->novalnet_logo 	= $this->settings['novalnet_logo'];
			$this->set_order_status = $this->settings['set_order_status'];
			$this->reference1 		= isset( $this->settings['reference1'] ) ? trim( strip_tags( $this->settings['reference1'] ) ) : null;
			$this->reference2 		= isset( $this->settings['reference2'] ) ? trim( strip_tags( $this->settings['reference2'] ) ) : null;
			$this->icon       		= NOVALNET_URL . 'assets/images/novalnet_sepa.png';
			$this->has_fields 		= true;

			$this->title 			= $GLOBALS[ NN_FUNCS ]->get_payment_title( $this->settings );
			$this->description 		= $GLOBALS[ NN_FUNCS ]->get_payment_description( $this->settings );

			// Save options
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

			add_action( 'woocommerce_thankyou_' . $this->id, array( &$this, 'thankyou_page' ) );
			add_action( 'woocommerce_order_details_after_order_table', array( $this, $this->id . '_transactional_info' ) );
			add_action( 'woocommerce_email_after_order_table', array( $this, 'novalnet_email_instructions' ), 15, 2 );

			if ( isset( $_SESSION['novalnet']['novalnet_sepa']['sepa_info_got'] ) )
				unset( $_SESSION['novalnet']['novalnet_sepa']['sepa_info_got'] );
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
					'title'  	=> __( 'Enable module', 'novalnet' ),
					'type'   	=> 'checkbox',
					'label'  	=> ' ',
					'default'	=> ''
				),
				'title_en' 		=> array(
					'title' 	=> __( 'Payment Title in English', 'novalnet' ),
					'type' 		=> 'text',
					'description'=> '',
					'default' 	=> 'Direct Debit SEPA'
				),
				'description_en'	=> array(
					'title' 	=> __( 'Description in English', 'novalnet' ),
					'type' 		=> 'textarea',
					'description'=> '',
					'default' 	=> 'Your account will be debited upon delivery of goods.'
				),
				'title_de' 		=> array(
					'title' 	=> __( 'Payment Title in German', 'novalnet' ),
					'type' 		=> 'text',
					'description'=> '',
					'default' 	=> 'Lastschrift SEPA'
				),
				'description_de'	=> array(
					'title' 	=> __( 'Description in German', 'novalnet' ),
					'type' 		=> 'textarea',
					'description'=> '',
					'default' 	=> 'Die Belastung Ihres Kontos erfolgt mit dem Versand der Ware.'
				),
				'test_mode' 	=> array(
					'title' 	=> __( 'Enable Test Mode', 'novalnet' ),
					'type' 		=> 'select',
					'options' 	=> array( '0' => __( 'No', 'novalnet' ), '1' => __( 'Yes', 'novalnet' ) ),
					'description'=> '',
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
                'payment_duration' => array(
					'title' 	 => __( 'SEPA Payment duration in days', 'novalnet' ),
					'type' 		 => 'text',
					'label' 	 => '',
					'default' 	 => '',
					'description'=> __( 'Enter the Due date in days, it should be greater than 6. If you leave as empty means default value will be considered as 7 days', 'novalnet' )
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
		} // End set_current()

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

			return apply_filters( 'woocommerce_gateway_title', $novalnet_logo_html . $this->title , $this->id );
		} // End get_title()

		public function validate_fields() {
			$error = '';

			if( $GLOBALS[ NN_FUNCS ]->validate_global_settings( $GLOBALS[ NN_CONFIG ]->global_settings ) ){
				$error = __('Basic parameter not valid', 'novalnet');
			} else if (isset($this->manual_limit) && $this->manual_limit && !$GLOBALS[ NN_FUNCS ]->is_digits($this->manual_limit)){
					$error = __('Manual limit amount / Product-ID2 / Tariff-ID2 is not valid!', 'novalnet');
			} else if (isset($this->manual_limit) && intval($this->manual_limit) > 0) {
				if (!$GLOBALS[ NN_FUNCS ]->is_digits($this->product_id2) || !$GLOBALS[ NN_FUNCS ]->is_digits($this->tariff_id2) )
					$error = __('Manual limit amount / Product-ID2 / Tariff-ID2 is not valid!', 'novalnet');
			} else if ( empty( $_POST['novalnet_sepa_account_holder'] ) ||  empty( $_POST['nn_sepa_hash'] ) || empty( $_POST['nn_sepa_uniqueid'] ) ) {
				$error =  __( 'Please enter valid account details!', 'novalnet' );
			}

			if( $error ){
				$this->add_display_info( $error , 'error' );
				return ( $this->return_redirect_page( 'success', WC()->cart->get_checkout_url() ) );
			} else {
				if ( $this->auto_refill ) {
					$_SESSION['novalnet']['novalnet_sepa'] = $_POST; #For refilling purpose
				}
				$this->sepa_details = array(
					'sepa_holder' => $_POST['novalnet_sepa_account_holder'],
					'sepa_panhash'=> $_POST['nn_sepa_hash'],
					'sepa_uniqid' => $_POST['nn_sepa_uniqueid']
				);
			}

			if ( isset( $this->sepa_duration ) && $this->sepa_duration != '' && strlen( $this->sepa_duration ) > 0 ) {
				if ( !$GLOBALS[ NN_FUNCS ]->is_digits( $this->sepa_duration ) || $this->sepa_duration < 7 ) {
					$this->add_display_info( __( 'SEPA Due date is not valid', 'novalnet' ), 'error' );
					return ( $this->return_redirect_page( 'success', WC()->cart->get_checkout_url() ) );
				}
			}

			return true;
		}

		public function payment_fields() {

			if ( $this->description )
				echo wpautop( $this->description );

			if ( $this->test_mode == 1 )
				echo wpautop( '<strong><font color="red">' . __( 'Please Note: This transaction will run on TEST MODE and the amount will not be charged', 'novalnet' ) . '</font></strong>' );

			if ( $this->end_user_info )
				echo wpautop( $this->end_user_info );

			if ( WC()->session->chosen_payment_method != 'novalnet_sepa' ) {
				if ( isset( $_SESSION['novalnet']['novalnet_sepa'] ) )
					$_SESSION['novalnet']['novalnet_sepa'] = false;
			}

			/* Novalnet Direct Debit German Payment form */
			echo '<div class="sepa_loader" id="sepa_loader" style="display:none"></div>';
			$default_fields = array(
				 'sepa_holder_field' => '<tr><td style="border:0;">
					<label for="' . esc_attr( $this->id ) . '_account_holder">' . __( 'Account Holder', 'novalnet' ) . ' <span class="required">*</span></label></td>
					<td style="border:0;">
					<input id="' . esc_attr( $this->id ) . '_account_holder" class="input-text" type="text"  autocomplete="off" placeholder="" name="' . ( $this->id . '_account_holder' ) . '" /></td>

				</tr>',
				'sepa_country_field' => '<tr><td style="border:0;">
					<label for="' . esc_attr( $this->id ) . '_bank_country">' . __( 'Bank Country', 'novalnet' ) . ' <span class="required">*</span></label> </td><td style="border:0;">
					<select id="' . esc_attr( $this->id ) . '_bank_country" class="sepa_country_list">' . $GLOBALS[ NN_FUNCS ]->get_sepa_bank_country() . '</select>
				</td></tr>',
				'sepa_accno_iban_field' => '<tr><td style="border:0;">
					<label for="' . esc_attr( $this->id ) . '_iban">' . __( 'IBAN or Account Number ', 'novalnet' ) . ' <span class="required">*</span></label></td>
					<td style="border:0;">
					<input id="' . esc_attr( $this->id ) . '_iban" class="input-text" type="text" autocomplete="off" /><br /><span id="novalnet_sepa_iban_span"></span>
				</td>',
				'sepa_bankcode_bic_field' => '<tr><td style="border:0;">
					<label for="' . esc_attr( $this->id ) . '_bic">' . __( 'BIC or Bank Code', 'novalnet' ) . ' <span class="required">*</span></label></td>
					<td style="border:0;">
					<input id="' . esc_attr( $this->id ) . '_bic" class="input-text" type="text" autocomplete="off" /><br /><span id="novalnet_sepa_bic_span"></span></td></tr>',
				'sepa_mandate_confirm_field' => '<tr><td colspan="2" style="border:0;">
					<input id="' . esc_attr( $this->id ) . '_mandate_confirm" type="checkbox" name="' . ( $this->id . '_mandate_confirm' ) . '" value="1" onclick="sepahashcall(\'link\');"/>
					&nbsp;' . __( 'I hereby grant the mandate for the SEPA direct debit (electronic transmission) and confirm that the given IBAN and BIC are correct!', 'novalnet' ) . '
				</td></tr>'
			);

			if ( !empty( $default_fields ) ) {
			?>
				<fieldset class="sepa_form" id="<?php echo $this->id; ?>_form">  <?php do_action( 'woocommerce_credit_card_form_start', $this->id ); ?>
			<?php
				echo '<input type="hidden" id="nn_vendor"  name="nn_vendor" value="' . $GLOBALS[ NN_CONFIG ]->global_settings['vendor_id'] . '"/> <input type="hidden" id="nn_auth_code"  name="nn_auth_code" value="' . $GLOBALS[ NN_CONFIG ]->global_settings['auth_code'] . '"/> <input type="hidden" id="nn_sepa_id"  name="nn_sepa_id" value="' . $this->payment_key . '"/> <input type="hidden" id="nn_sepa_hash"  name="nn_sepa_hash" value=""/> <input type="hidden" id="nn_sepa_mandate_ref"  name="nn_sepa_mandate_ref" value=""/> <input type="hidden" id="nn_sepa_iban"  name="nn_sepa_iban" value=""/> <input type="hidden" id="nn_sepa_bic"  name="nn_sepa_bic" value=""/> <input type="hidden" id="nn_sepa_mandate_date"  name="nn_sepa_mandate_date" value=""/> <input type="hidden" id="nn_plugin_url"  name="nn_plugin_url" value="' . site_url() . '"/><input type="hidden" id="nn_sepa_uniqueid"  name="nn_sepa_uniqueid" value="' . $GLOBALS[ NN_FUNCS ]->generate_random_string( 30 ) . '"/> <input type="hidden" id="nn_sepa_input_panhash"  name="nn_sepa_input_panhash" value="' . $this->getSepaRefillHash( $this->id ) . '"/> <input type="hidden" id="novalnet_sepa_customer_info" name="novalnet_sepa_customer_info" value="' . $GLOBALS[NN_FUNCS]->get_customer_info() . '" /> <input type="hidden" id="nn_lang_mandate_confirm"  name="nn_lang_mandate_confirm" value="' . __( 'Please confirm IBAN & BIC', 'novalnet' ) . '"/> <input type="hidden" id="nn_lang_valid_merchant_credentials"  name="nn_lang_valid_merchant_credentials" value="' . __( 'Please enter valid Merchant Credentials', 'novalnet' ) . '"/> <input type="hidden" id="nn_lang_valid_account_details"  name="nn_lang_valid_account_details" value="' . __( 'Please enter valid account details!', 'novalnet' ) . '"/> <div id="nn_overlay_id">' . $this->get_sepa_ovelay_template() . '</div> <script src="' . NOVALNET_URL . 'assets/js/novalnet_sepa.js" type="text/javascript"></script><script src="' . NOVALNET_URL . 'assets/js/novalnet_checkout.js" type="text/javascript"></script><link rel="stylesheet" type="text/css" media="all" href="'.NOVALNET_URL.'assets/css/novalnet_sepa.css">';

				echo "<table width='100%' style='border-style: none;'>";
				foreach ( $default_fields as $field ) {
					echo $field;
				}
				echo "</table>";
				do_action( 'woocommerce_credit_card_form_end', $this->id );
			?>
				<div class="clear"></div>
				</fieldset>
			<?php
			}
		}

		public function getSepaRefillHash( $code ) {
			if ( isset( $_SESSION['novalnet']['novalnet_sepa']['nn_sepa_hash'] ) ) {
				return ( ( isset( $_SESSION['novalnet']['novalnet_sepa']['nn_sepa_hash'] ) && ( $this->auto_refill ) ) ? $_SESSION['novalnet']['novalnet_sepa']['nn_sepa_hash'] : '' );
			}
		}

		public function get_sepa_ovelay_template() {

			$template_path   = NOVALNET_URL . "templates/sepa_overlay_template.tmp";
			$template_source = file_get_contents( $template_path );

			$lang_params = array(
				'NOVALNET_SEPA_OVERLAY_CONFIRM_TITLE' => __( 'SEPA Direct Debit Mandate Confirmation ', 'novalnet' ),
				'NOVALNET_SEPA_OVERLAY_PAYEE' => __( 'Creditor', 'novalnet' ),
				'NOVALNET_SEPA_OVERLAY_CREDITOR_IDENTIFICATION_NUMBER' => __( 'Creditor identification number', 'novalnet' ),
				'NOVALNET_SEPA_OVERLAY_MANDATE_REFERENCE' => __( 'Mandate reference', 'novalnet' ),
				'NOVALNET_SEPA_OVERLAY_MANDATE_OVERLAY_CONFIRM_PARAGRAPH' => __( 'I hereby authorize the payee to collect payments from my account by direct debit. At the same time I instruct my financial institution to redeem the conclusions drawn by the payee to my account debits.<br/><br/>Note: I may request within eight weeks from the debit date, a refund of the amount charged from my account, as per my bank rules & regulations. ', 'novalnet' ),
				'NOVALNET_SEPA_OVERLAY_ENDUSER_FULLNAME' => __( 'Name of the payee', 'novalnet' ),
				'NOVALNET_SEPA_OVERLAY_COMPANY' => __( 'Company', 'novalnet' ),
				'NOVALNET_SEPA_OVERLAY_ADDRESS' => __( 'Address', 'novalnet' ),
				'NOVALNET_SEPA_OVERLAY_ZIPCODE_AND_CITY' => __( 'Zipcode and city', 'novalnet' ),
				'NOVALNET_SEPA_OVERLAY_COUNTRY' => __( 'Country', 'novalnet' ),
				'NOVALNET_SEPA_OVERLAY_EMAIL' => __( 'E-Mail', 'novalnet' ),
				'NOVALNET_SEPA_OVERLAY_IBAN' => 'IBAN',
				'NOVALNET_SEPA_OVERLAY_SWIFT_BIC' => 'BIC',
				'NOVALNET_SEPA_OVERLAY_CONFIRM_BTN' => __( 'Confirm', 'novalnet' ),
				'NOVALNET_SEPA_OVERLAY_CANCEL_BTN' => __( 'Cancel', 'novalnet' )
			);

			foreach ( $lang_params as $key => $value ) {
				$template_source = str_replace( $key, $value, $template_source );
			}
			$template_source = str_replace( '###NOVALNET_SEPA_FORM_STYLE###', "<link rel='stylesheet' type='text/css' media='all' href='" . NOVALNET_URL . "assets/css/novalnet_sepa.css'>", $template_source );
			$template_source = str_replace( '###NOVALNET_PLUGIN_IMG_DIR###', NOVALNET_URL . "assets/images", $template_source );
			return $template_source;
		}

		public function process_payment( $order_id ) {
			return $this->generate_payment_parameters( $order_id, $this );
		}

		/**
		 * Add text to order confirmation mail to customer below order table
		 * called by 'woocommerce_email_after_order_table' action
		 *
		 * @access public
		 * @return void
		 */
		public function novalnet_email_instructions( $order, $sent_to_admin ) {
			if ( $order->payment_method == $this->id && ( !isset( $_SESSION['novalnet_email_notes_got'] ) || ( ( $sent_to_admin == 0 ) && ( !isset( $_SESSION['novalnet_email_notes_got'] ) || $_SESSION['novalnet_email_notes_got'] != 2 ) ) ) ) {

				if ( $this->email_notes )
					echo wpautop( wptexturize( $this->email_notes ) );

				if ( $sent_to_admin )
					$_SESSION['novalnet_email_notes_got'] = 1;
				else
					$_SESSION['novalnet_email_notes_got'] = 2;
			}
			$order->customer_note = wpautop($order->customer_note);
		}

		public function novalnet_sepa_transactional_info( $order ) {
			if ( !isset( $_SESSION['novalnet']['novalnet_sepa']['sepa_info_got'] ) && $order->payment_method == $this->id ) {

				echo wpautop( '<h2>' . __( 'Transaction Information', 'novalnet' ) . '</h2>' );
				echo wpautop( wptexturize( $order->customer_note ) );
				$_SESSION['novalnet']['novalnet_sepa']['sepa_info_got'] = 1;
			}
		}
	}

	$obj = new WC_Gateway_Novalnet_Sepa();
 } // End init_gateway_novalnet_sepa()

/**
 * Add the gateway to WooCommerce
 * @access public
 * @param array $methods
 * @package
 * @return array
 */

function add_gateway_novalnet_sepa( $methods )
{
    global $novalnet_payment_methods;
    $methods[] = NOVALNET_SEPA_CLASS;
    return $methods;
} // End add_gateway_novalnet_sepa()

add_filter( 'woocommerce_payment_gateways', 'add_gateway_' . $novalnet_payment_methods['nn_directdebitsepa'] );
?>
