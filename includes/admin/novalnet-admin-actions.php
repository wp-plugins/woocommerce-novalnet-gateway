<?php
/**
 * Novalnet Admin Actions
 *
 * Copyright (c) 2015 Novalnet AG <https://www.novalnet.de>
 *
 * Released under the GNU General Public License. This free
 * contribution made by request.If you have found this script
 * usefull a small recommendation as well as a comment on
 * merchant form would be greatly appreciated.
 *
 * This file is used to set the Novalnet configuration parameters and
 * the extention operations for all payment methods
 *
 * @class 		NN_Admin_Config_Settings
 * @version		2.0.0
 * @package		woocommerce-novalnet-gateway/includes/admin/
 * @author 		Novalnet
 * @link		https://www.novalnet.de
 * @copyright	2015 Novalnet AG <https://www.novalnet.de>
 * @license     GNU General Public License version 2.0
 */

 ob_start();

 // Exit if accessed directly
 if ( ! defined( 'ABSPATH' ) )
	exit;

 add_action ( 'admin_head' , 'hide_breakup_errors' );
 /**
  * remove unwanted
  * text displays
  * shop admin end
  * @access public
  * @return void
  */
 function hide_breakup_errors() {
	echo '<style type="text/css">#wpfooter {display:none;}</style>';
 }	// End hide_breakup_errors()

 ### includes novalnet configuration settings and api extension ####
 require_once( NOVALNET_DIR . 'novalnet-config.inc.php' );
 require_once( NOVALNET_DIR . 'novalnet-functions.php' );

 class NN_Admin_Config_Settings {

	/*  required key settings for global configurations */
	public $tab_name 			= 'novalnet_settings';
	public $option_prefix 		= 'novalnet';
	public $global_settings 	= array();

    public $supports_extension  = array( NOVALNET_CC, NOVALNET_SEPA, NOVALNET_PP );
    public $invoice_payments    = array( NOVALNET_IN, NOVALNET_PT );
    public $debit_payments      = array( NOVALNET_SEPA );
	private $allowed_actions 	= array( 'capture_void_process', 'trans_refund_prcoess' );

	function __construct() {
		ob_start();
        /* initialize novalnet language */
        $this->initialize_novalnet_language();
		add_filter( 'woocommerce_settings_tabs_array',array( $this, 'add_novalnet_settings_tab' ), 50 );
        add_action( 'woocommerce_settings_tabs_'.$this->tab_name, array($this, 'add_novalnet_settings' ));
        add_action( 'woocommerce_update_options_'.$this->tab_name, array($this, 'update_novalnet_settings') );
		add_action( 'admin_menu', array( $this, 'add_nn_admin_menus' ) );
        $this->global_settings = $this->fetch_global_configurations();
        add_action( 'wp_before_admin_bar_render', array( $this, 'nn_api_call_check' ) );
        add_action( 'add_meta_boxes', array( &$this, 'novalnet_transaction_meta_boxes' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	public function add_novalnet_settings_tab( $woocommerce_tab ) {
		$woocommerce_tab[ $this->tab_name ] = __( 'Novalnet Global Configuration', 'novalnet' );
		return $woocommerce_tab;
	}

	function add_nn_admin_menus() {
		add_submenu_page( 'wocommerce' , 'Novalnet Administration', 'Novalnet Administration' , 'manage_options', 'wc-novalnet-admin', array( &$this, 'novalnet_admin_information' ) );
	}

	public function novalnet_settings_fields() {

		$available_status = $GLOBALS[ NN_FUNCS ]->get_order_status();
		$admin_url = admin_url( 'admin.php?page=wc-novalnet-admin' );

		$settings = array(
            'title' 	=> array(
				'title'	=> __( 'Novalnet Global Configuration', 'novalnet' ),
                'desc'  => sprintf(__('Please visit %sNovalnet Merchant Administration%s for Configuration, Transaction details!<br/>Please enter your PayPal Account Data at %sNovalnet Merchant Administration%s to use the PayPal payment!', 'novalnet'), '<a href="'.$admin_url.'" target="_new">', '</a>','<a href="'.$admin_url.'" target="_new">', '</a>'),
                'id'    => $this->option_prefix . '_description',
                'type'  => 'title',
			),
            'vendor_id' 	=> array(
				'title' 	=> __('Novalnet Merchant ID','novalnet'),
				'desc' 		=> __('Enter your Novalnet Merchant ID','novalnet'),
				'id' 		=> $this->option_prefix . '_vendor_id',
				'css' 		=> 'width:35%;',
				'type' 		=> 'text',
				'desc_tip' 	=> true,
			),
            'auth_code' => array(
				'title' => __('Novalnet Merchant Authorisation code','novalnet'),
				'desc' => __('Enter your Novalnet Merchant Authorisation code','novalnet'),
				'id' => $this->option_prefix . '_auth_code',
				'css' => 'width:35%;',
				'type' => 'text',
				'desc_tip' => true,

			),
            'product_id' 	=> array(
				'title' 	=> __('Novalnet Product ID','novalnet'),
				'desc' 		=> __('Enter your Novalnet Product ID','novalnet'),
				'id' 		=> $this->option_prefix . '_product_id',
				'css' 		=> 'width:35%;',
				'type' 		=> 'text',
				'desc_tip' 	=> true,
			),
            'tariff_id' 	=> array(
				'title' 	=> __('Novalnet Tariff ID','novalnet'),
				'desc' 		=> __('Enter your Novalnet Tariff ID','novalnet'),
				'id' 		=> $this->option_prefix . '_tariff_id',
				'css' 		=> 'width:35%;',
				'type' 		=> 'text',
				'desc_tip' 	=> true,
			),
            'key_password' => array(
				'title' 	=> __('Novalnet Payment access key','novalnet'),
				'desc' 		=> __('Enter your Novalnet payment access key','novalnet'),
				'id' 		=> $this->option_prefix . '_key_password',
				'css' 		=> 'width:35%;',
				'type' 		=> 'text',
				'desc_tip' 	=> true,
			),
			'referrer_id' 	=> array(
				'title' 	=> __('Referrer ID','novalnet'),
				'desc' 		=> __( 'Referrer ID of the partner at Novalnet, who referred you (only numbers allowed) ', 'novalnet'),
				'id' 		=> $this->option_prefix . '_referrer_id',
				'css' 		=> 'width:35%;',
				'type' 		=> 'text',
				'desc_tip' 	=> true,
			),
            'onhold_success_status' => array(
				'title' 	=> __('OnHold transaction completion status','novalnet'),
				'id' 		=> $this->option_prefix . '_onhold_success_status',
				'type' 		=> 'select',
				'options' 	=> $available_status,
				'css' 		=> 'width:25%;',
			),
			'onhold_cancel_status' => array(
				'title' 	=> __('OnHold cancellation / VOID Transaction status','novalnet'),
				'id' 		=> $this->option_prefix . '_onhold_cancel_status',
				'type' 		=> 'select',
				'options' 	=> $available_status,
				'css' 		=> 'width:25%;',
			),
			'proxy' => array(
				'title' 	=> 'Proxy-Server',
				'desc' 		=> __('If you use a Proxy Server, enter the Proxy Server IP with port here (E.g.: www.proxy.de:80)', 'novalnet'),
				'id' 		=> $this->option_prefix . '_proxy',
				'css' 		=> 'width:35%;',
				'type' 		=> 'text',
				'desc_tip' 	=> true,
			),
			'gateway_timeout' => array(
				'title' 	=> __('Gateway Timeout', 'novalnet'),
				'desc'	 	=> __('Gateway Timeout in seconds' , 'novalnet'),
				'id' 		=> $this->option_prefix . '_gateway_timeout',
				'css' 		=> 'width:35%;',
				'type' 		=> 'text',
				'default'  	=> '240',
				'desc_tip' 	=> true,
			),
			'section_end' => array(
				'type' => 'sectionend',
				'id' => $this->option_prefix . '_section_end'
			)
        );

        return apply_filters( 'woocommerce_' . $this->tab_name, $settings );
	}

	public function fetch_global_configurations() {
		return array(
			'vendor_id' 			=> get_option('novalnet_vendor_id'),
			'auth_code' 			=> get_option('novalnet_auth_code'),
			'product_id' 			=> get_option('novalnet_product_id'),
			'tariff_id'				=> get_option('novalnet_tariff_id'),
			'key_password' 			=> get_option('novalnet_key_password'),
			'referrer_id' 			=> get_option('novalnet_referrer_id'),
			'onhold_success_status' => get_option('novalnet_onhold_success_status'),
			'onhold_cancel_status' 	=> get_option('novalnet_onhold_cancel_status'),
			'proxy' 				=> get_option('novalnet_proxy'),
			'gateway_timeout'	 	=> get_option('novalnet_gateway_timeout'),
		);
	}

	public function add_novalnet_settings() {
		woocommerce_admin_fields( $this->novalnet_settings_fields() );
	}

	public function update_novalnet_settings(){
		woocommerce_update_options( $this->novalnet_settings_fields() );
	}

	// add scripts and style to woocommerce
	public function enqueue_scripts() {
		wp_register_script( 'novalnet', NOVALNET_URL . '/assets/js/novalnet_admin.js' );
		wp_register_style( 'novalnet', NOVALNET_URL . '/assets/css/admin.css' );
		wp_enqueue_script( 'novalnet' );
		wp_enqueue_style( 'novalnet' );
	}

	/**
     * Initialize language for payment methods
     * @access public
     * @return void
     */
    function initialize_novalnet_language() {
        $this->language =  strtoupper( substr( get_bloginfo( 'language' ), 0, 2 ) );
    }

    function novalnet_admin_information(){
		?>
		<h2> Novalnet Administration</h2>
		<div class="nn_map_header"><?php echo __( 'Please login here with Novalnet merchant credentials... Please contact us on support@novalnet.de for activating payment methods!' , 'novalnet' ); ?> </div>
	        <iframe frameborder="0" width="100%" height="600px" border="0" src="https://admin.novalnet.de/">
        </iframe>
		<?php
	}

    /**
    * Verify novalnet gateway action
    * @access public
    * @return void
    */
   public function nn_api_call_check() {
		global $post, $post_id, $wpdb, $wc_order, $nn_api_object, $nn_api_payment;
		if ( is_admin() ) {
			if ( isset ( $_GET['novalnet_gateway_action'] ) && in_array( $_GET['novalnet_gateway_action'], $this->allowed_actions ) && isset ( $_GET['post'] ) ) {

				$post_id  = intval ( $_GET['post'] );
				$wc_order = new WC_Order( $post_id );

				// get the payment method
				$nn_api_payment = $wc_order->payment_method;
				$nn_api_class   = $this->current_class( $nn_api_payment );
				$nn_api_object  = new $nn_api_class();

				if ( in_array( $wc_order->payment_method, $this->supports_extension ) || in_array( $wc_order->payment_method, $this->invoice_payments ) ) {
					$this->_nn_api_action_pass( $_GET['novalnet_gateway_action'] );
				}
			}
		}
	}	// End nn_api_call_check()

   /**
     * add meta box for novalnet
     * payment transactions
     * @access public
     * @return void
     */
    function novalnet_transaction_meta_boxes() {
		global $post, $post_id, $order_id, $wc_order, $wpdb;
		$post_id = get_the_ID();
		$order_id = get_post_meta( $post_id, '_order_number', true );
		if ( ! $order_id )
			$order_id = $post_id;

		$wc_order = new WC_Order( $post_id );

		$nn_txn_details = $GLOBALS[ NN_FUNCS ]->get_novalnet_trans_details( $wc_order->id, 'row', "subs_id,gateway_status,amount,callback_amount,refunded_amount" );

		/** Novalnet Payment authorize action */
		if ( in_array( $wc_order->payment_method, array( NOVALNET_CC, NOVALNET_IN,  NOVALNET_PT, NOVALNET_SEPA ) ) && ! empty ( $nn_txn_details->gateway_status ) && in_array( $nn_txn_details->gateway_status, array( 99, 98, 91 ) ) ) {

			add_meta_box(
				NOVALNET_TRANSACTION_CONFIRM_META,
				__('Manage Transaction', 'novalnet'),
				array( $this, NOVALNET_TRANSACTION_CONFIRM_META),
				'shop_order',
				'side',
				'default'
			);
		}

		if ( ( ( in_array( $wc_order->payment_method, $this->invoice_payments ) && $nn_txn_details->callback_amount > 0 ) || in_array( $wc_order->payment_method, $this->supports_extension ) ) && $nn_txn_details->gateway_status == COMPLETE_CODE && $nn_txn_details->amount > 0 ) {
			add_meta_box(
				NOVALNET_TRANSACTION_REFUND_META,
				__( 'Transaction Refund', 'novalnet' ) ,
				array( $this, NOVALNET_TRANSACTION_REFUND_META ),
				'shop_order',
				'side',
				'default'
			);
		}
	}

	public function novalnet_transaction_confirm_process() {
		global $wpdb, $post, $post_id, $wc_order, $order_id;
		?>

		<div id="novalnet_loading_div"> </div>
		<ul>
			<li>
				<label><?php echo __( 'Please select status', 'novalnet' ); ?> : </label>
				<select id="confirm_status">
					<option value="" ><?php echo __( 'Please select status', 'novalnet' ); ?></option>
					<option value="100" ><?php echo __( 'Debit ', 'novalnet' ); ?></option>
					<option value="103" ><?php echo __( 'Cancel ', 'novalnet' ); ?></option>
				</select>
			</li>
		</ul>

		<p class="buttons">
		   <a id="nov_trans_confirm" class="button button-primary tips" data-tip="<?php echo __( 'Confirm the payment transaction', 'novalnet' ); ?>" onclick= "noval_trans_confirm('<?php echo admin_url( 'post.php?post=' . $post->ID . '&action=edit&novalnet_gateway_action=capture_void_process' ) ; ?>','<?php echo $wc_order->payment_method; ?>', '<?php echo strtoupper( substr( get_bloginfo( 'language' ), 0, 2 ) ) ; ?>')" href="javascript:void(0)"><?php echo __( 'Confirm', 'novalnet' ); ?></a>
		</p>
		<?php
	}

	public function novalnet_transaction_refund() {

		global $wpdb, $post, $post_id, $wc_order, $order_id;

		$nn_txn_details = $GLOBALS[ NN_FUNCS ]->get_novalnet_trans_details( $wc_order->id, 'row', "gateway_status,amount,callback_amount,refunded_amount,currency" ) ;

		$amount = in_array( $wc_order->payment_method, $this->invoice_payments ) ? $nn_txn_details->callback_amount : $nn_txn_details->amount;
	?>
		<div id="novalnet_loading_div"></div>
		<ul >
		<li class="wide">
			<label><?php echo __( 'Refund amount', 'novalnet' ); ?> : </label><br/>
			<input type="text" step="any" id="nov_refund_amount" class="first" name="nov_refund_amount" placeholder="0.00" value="<?php echo sprintf( "%0.2f" , $amount/100 ) ;  ?>" /> <?php echo $nn_txn_details->currency; ?><br/>
		</li>
		</ul>
		<p class="buttons">

			<a id="nn_refund" class="button button-primary tips" data-tip="<?php echo __( 'Refund the current Transaction', 'novalnet' ); ?>" onclick= "noval_refund('<?php echo admin_url( 'post.php?post=' . $post->ID . '&action=edit&novalnet_gateway_action=trans_refund_prcoess' ) ; ?>',<?php echo $amount; ?>, '<?php echo strtoupper( substr( get_bloginfo( 'language'), 0, 2 ) ) ; ?>')" href="javascript:void(0)"><?php echo __('Confirm', 'novalnet'); ?></a>
		</p>
		<?php
	}

	/**
     * provides class name
     * for current payment method
     * @access public
     * @return class name
     */
    function current_class( $id ) {

		switch ( $id ) {
			case NOVALNET_BT:
				return ('WC_Gateway_Novalnet_Banktransfer');
			case NOVALNET_CC:
				return ('WC_Gateway_Novalnet_Cc');
			case NOVALNET_ID:
				return ('WC_Gateway_Novalnet_Ideal');
			case NOVALNET_IN:
				return ('WC_Gateway_Novalnet_Invoice');
			case NOVALNET_PP:
				return ('WC_Gateway_Novalnet_Paypal');
			case NOVALNET_PT:
				return ('WC_Gateway_Novalnet_Prepayment');
			case NOVALNET_SEPA:
				return ('WC_Gateway_Novalnet_Sepa');

		}
	}	// End current_class()

	/**
    * routes the respective
    * action as per request
    * @access private
    * @return void
    */
	function _nn_api_action_pass( $action ) {

		if ( in_array( $action, $this->allowed_actions ) ) {
			call_user_func( array( $this, 'perform_' . $action ) );
        }
	}	// End _nn_api_action_pass()

	public function perform_capture_void_process() {
		global $post, $post_id, $wpdb, $wc_order;


		$return = $this->pre_processing_api_params();

		if ( true === $return ) {
			$data = $GLOBALS[ NN_FUNCS ]->perform_https_request( ( is_ssl() ? 'https://' : 'http://' ) . PAYGATE_URL , $this->nn_api_param );
			wp_parse_str( $data, $aryCaptResponse) ;

			if ( $aryCaptResponse['status'] == COMPLETE_CODE ) {

				// recieves the status code for transactions
				$api_capt_stat_code = $GLOBALS[ NN_FUNCS ]->make_transaction_status( $this->nn_api_param, $wc_order->id, true) ;

				if ( $api_capt_stat_code['status'] == COMPLETE_CODE ) {
					$nn_confirm_message = __('Transaction confirmed successfully','novalnet');
					$status = $this->global_settings['onhold_success_status'];
				}else{
					$nn_confirm_message = __('Transaction deactivated successfully','novalnet');
					$status = $this->global_settings['onhold_cancel_status'];
					do_action( 'woocommerce_cancelled_order', $post_id );
				}

				$update_data  = array( 'gateway_status' =>$api_capt_stat_code['status'] );
				if ( $api_capt_stat_code['status'] != COMPLETE_CODE ) {
					$update_data['active'] = 0;
				}

				// update payment information into the database
				$GLOBALS[ NN_FUNCS ]->update_novalnet_trans_details( $update_data, array( 'order_no' => $wc_order->id ) );

				$this->update_transactiondetails( $nn_confirm_message );

				$wc_order->update_status( $status );

			} else {
				$api_capt_message = isset ( $aryCaptResponse['status_desc'] ) ? $aryCaptResponse['status_desc'] : ( isset ( $aryCaptResponse['status_text'] ) ? $aryCaptResponse['status_text'] : '' );

				$message = ( $_POST['confirm_status'] == 100 ) ? __('confirmation', 'novalnet') : __( 'deactivation' , 'novalnet');

				$nn_capt_err = ( '' == $api_capt_message ) ? sprintf( __('Transaction %s failed','novalnet'), $message ): sprintf ( __('Transaction %s failed due to %s','novalnet' ), $message , $api_capt_message );

				$wc_order->add_order_note( $nn_capt_err );
			}

		} else {
			$wc_order->add_order_note( __('There was an error and your request could not be completed', 'novalnet') );
		}

		wp_safe_redirect( admin_url( 'post.php?post=' . $wc_order->id . '&action=edit' ) );
		exit;
	}

	public function perform_trans_refund_prcoess() {
		global $post, $post_id, $wpdb, $wc_order;

		$nn_ref_new_line = "\n";
		$return = $this->pre_processing_api_params();

		if ( true === $return ) {

			$data = $GLOBALS[ NN_FUNCS ]->perform_https_request( ( is_ssl() ? 'https://' : 'http://') . PAYGATE_URL, $this->nn_api_param ) ;
			wp_parse_str( $data, $aryRefResponse );

			$status_text = ( isset ( $aryRefResponse['status_text'] ) ? $aryRefResponse['status_text'] : ( isset ( $aryRefResponse['status_desc'] ) ? $aryRefResponse['status_desc'] : ( isset ( $aryRefResponse['status_message'] ) ? $aryRefResponse['status_message'] : '' ) ) );

			// recieves the status code for transactions
			$api_ref_stat_code = $GLOBALS[ NN_FUNCS ]->make_transaction_status( $this->nn_api_param , $wc_order->id, true );

			if ( COMPLETE_CODE == $aryRefResponse['status'] ) {

				if ( $wc_order->payment_method == NOVALNET_PP && isset ( $aryRefResponse['paypal_refund_tid'] ) && $aryRefResponse['paypal_refund_tid'] != '' ) {
					$GLOBALS[ NN_FUNCS ]->update_novalnet_trans_details( array( 'gateway_status' => $api_ref_stat_code['status'] ), array( 'order_no' => $wc_order->id ) );

					$nn_ref_message =  sprintf( __( 'Refund has been executed for the TID: %s with the amount %s. New TID:%s for the refunded amount %s ', 'novalnet' ), $this->nn_api_param['tid'], strip_tags( woocommerce_price( $_POST['nov_refund_amount'] ) ),  $aryRefResponse['tid'], strip_tags( woocommerce_price( $_POST['nov_refund_amount'] ) ) );

				} else {

					if ( in_array( $wc_order->payment_method, $this->invoice_payments ) ) {
						$wpdb->update( $wpdb->prefix . 'novalnet_invoice_details' , array( 'amount'=> ( isset ( $api_ref_stat_code['amount'] ) ? $api_ref_stat_code['amount'] : '' ) ) , array( 'post_id' => $wc_order->id ) );
					}

					$amt_details = $GLOBALS[ NN_FUNCS ]->get_novalnet_trans_details( $wc_order->id, 'row', 'amount,callback_amount,refunded_amount' );

					$data = array(
						'gateway_status' => ( isset ( $api_ref_stat_code['status'] ) ? $api_ref_stat_code['status'] : '' ),
						'amount' => $amt_details->amount - ( $_POST['nov_refund_amount'] * 100 ),
						'refunded_amount' => ( $amt_details->refunded_amount + ( $_POST['nov_refund_amount'] * 100)),
						'callback_amount' => $amt_details->callback_amount - ( $_POST['nov_refund_amount'] * 100 )
					);

					$GLOBALS[ NN_FUNCS ]->update_novalnet_trans_details( $data, array( 'order_no' => $wc_order->id ) );

					if ( !empty( $aryRefResponse['tid'] ) ) {
						$nn_ref_message =  sprintf( __( 'Refund has been executed for the TID: %s with the amount %s. New TID:%s for the refunded amount %s ', 'novalnet' ), $this->nn_api_param['tid'], strip_tags( woocommerce_price( $_POST['nov_refund_amount'] ) ),  $aryRefResponse['tid'], strip_tags( woocommerce_price( $_POST['nov_refund_amount'] ) ) );
					} else {
						$nn_ref_message = sprintf( __('Refund has been executed for the TID:%s with the amount %s ', 'novalnet'), $this->nn_api_param['tid'] , strip_tags( woocommerce_price( $_POST['nov_refund_amount'] ) ) );
					}
				}

				$this->update_transactiondetails( $nn_ref_message );

				$amt_details = $GLOBALS[ NN_FUNCS ]->get_novalnet_trans_details( $wc_order->id, 'row', 'amount,callback_amount,refunded_amount' );

				if ( $amt_details->amount == 0 || empty ( $amt_details->amount ) )
					$wc_order->update_status( 'refunded' );

			}else{
				$nn_ref_err = ( '' == $status_text ) ? __('Refund Process failed.','novalnet') : sprintf ( __('Refund Process failed due to %s','novalnet'), $status_text );
				$wc_order->add_order_note( $nn_ref_err);
			}
		}else{
			$wc_order->add_order_note( __('There was an error and your request could not be completed', 'novalnet') );
		}

		wp_safe_redirect( admin_url( 'post.php?post=' . $wc_order->id . '&action=edit' ) );
		exit;
	}

	/**
	 * Update the order transaction details for Extension scenarios
	 *
	 * @access public
	 * @param int order_id
	 * @param string comments
	 * @return void
	 */
	public function update_transactiondetails( $novalnet_comments ) {
		global $wc_order;

		$new_line = "\n";

		$wc_order->add_order_note( $novalnet_comments );

				// adds order note
		if ( $wc_order->customer_note ) {
			$wc_order->customer_note .= $new_line . $new_line;
		}

		$wc_order->customer_note .= html_entity_decode( $novalnet_comments, ENT_QUOTES, 'UTF-8');

		if (WOOCOMMERCE_VERSION < '2.0.0') {
			$wc_order->customer_note .= utf8_encode( $novalnet_comments);
		}


		/** Update Novalnet Transaction details into shop database	 */
		$nn_order_notes = array(
			'ID' 			=> $wc_order->id,
			'post_excerpt'  => $wc_order->customer_note
		);

		wp_update_post( $nn_order_notes );

	}

	/**
	* performs data processer
	* before api call to server
	* @access public
	* @return boolean
	*/
	public function pre_processing_api_params() {
		global $wc_order, $post_id, $nn_api_object;

		$nn_txn_details = $GLOBALS[ NN_FUNCS ]->get_novalnet_trans_details( $wc_order->id, 'row', '*' );

		// api parameters
		$this->nn_api_param  = array(
			'vendor'      => $nn_txn_details->vendor_id,
			'auth_code'   => $nn_txn_details->auth_code,
			'product'     => $nn_txn_details->product_id,
			'tariff'      => $nn_txn_details->tariff_id,
			'key' 	  	  => $nn_txn_details->payment_id,
			'tid'		  => $nn_txn_details->tid,
		);

		$this->nn_api_param['status'] = ( isset ( $_POST['confirm_status'] ) && ! empty ( $_POST['confirm_status'] ) ) ? $_POST['confirm_status']  : COMPLETE_CODE;

		if ( $_GET['novalnet_gateway_action'] == 'capture_void_process' ) {
			$this->nn_api_param['edit_status'] 	= 1;
		} else if ( $_GET['novalnet_gateway_action'] == 'trans_refund_prcoess' ) {
			$this->nn_api_param['refund_request'] = 1;
			$this->nn_api_param['refund_param']   = isset ( $_POST['nov_refund_amount'] ) ? $_POST['nov_refund_amount'] * 100 : '';
		}

		/**  basic validation for capture api call	*/
		$api_return = $GLOBALS[ NN_FUNCS ]->is_valid_api_params( $nn_api_object, $this->nn_api_param['tid'], $post_id );
		return( $api_return );

	}	// End pre_processing_api_params()
}

// sets the global method to access configuration values
$GLOBALS[NN_CONFIG] = new NN_Admin_Config_Settings();
?>
