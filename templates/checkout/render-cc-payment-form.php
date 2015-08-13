<?php
 if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
 }

/**
 * The file is for displaying the payment template for
 * Novalnet Credit card payment.
 *
 * Copyright (c) Novalnet
 *
 * This script is only free to the use for merchants of Novalnet. If
 * you have found this script useful a small recommendation as well as a
 * comment on merchant form would be greatly appreciated.
 *
 * @version     10.1.0
 * @package     Novalnet/Templates
 * @author      Novalnet
 *
 */

 $cc_input_hash = ( NN_Fns()->global_settings['auto_refill'] &&  isset($_SESSION['novalnet']['novalnet_cc']['pseudo_hash']) && $_SESSION['novalnet']['novalnet_cc']['pseudo_hash'] != '' ) ? $_SESSION['novalnet']['novalnet_cc']['pseudo_hash'] : '';

 ?>
 <input type="hidden" id="nn_cc_tag" value="<?php echo ! isset( $_SESSION['novalnet']['novalnet_cc']['tid'] ) ? 1 : 0; ?>"/>

 <?php
 if ( ! isset( $_SESSION['novalnet']['novalnet_cc']['tid'] ) ) : ?>

    <div class="nov_loader" id="cc_loader" style="display:none"></div>
    <input type="hidden" id="nn_cc_uniqueid"  name="nn_cc_uniqueid" value="<?php echo wc_novalnet_random_string(); ?>" />
    <input type="hidden" id="nn_cc_hash"  name="nn_cc_hash" value=""/>
    <input type="hidden" id="nn_credit_flag" value="0"/>
    <input type="hidden" id="nn_cc_input_panhash"  value="<?php echo  $cc_input_hash; ?>"/>
    <input type="hidden" id="nn_cc_refill_complete" value="0"/>
    <input type="hidden" id="nn_merchant_valid_error_ccmessage"  value="<?php echo __( 'Please fill in all the mandatory fields','wc-novalnet' ); ?>"/>
    <input type="hidden" id="nn_cc_valid_error_ccmessage"  value="<?php echo __( 'Your credit card details are invalid','wc-novalnet' ); ?>"/>

    <p class="form-row form-row-wide">
        <label for="novalnet_cc_type"> <?php echo __( 'Type of card', 'wc-novalnet' ); ?> <span class="required">*</span></label>
        <select id="novalnet_cc_type">
            <option value="">--<?php echo __( 'Select', 'wc-novalnet' ); ?>--</option>
            <option value='VI'>Visa</option>
            <option value='MC'>Mastercard</option>
            <?php if ( $this->settings['enable_amex_type'] ) : ?>
                <option value='AE'>AMEX</option>
            <?php endif; ?>
        </select>
    </p>
    <p class="form-row form-row-wide">
        <label for="novalnet_cc_holder"> <?php echo __( 'Card holder name', 'wc-novalnet' ); ?> <span class="required">*</span></label>
        <input id="novalnet_cc_holder" class="input-text" type="text"  autocomplete="off" onkeypress="return is_number_key(event,'holder' );"/>
    </p>
    <p class="form-row form-row-wide">
        <label for="novalnet_cc_no"> <?php echo __( 'Card number', 'wc-novalnet' ); ?> <span class="required">*</span></label>
        <input type="text" id="novalnet_cc_no" class="input-text" value="" autocomplete="off" onkeypress="return is_number_key(event);" />
    </p>
    <p class="form-row form-row-wide">
        <label for="novalnet_cc_expiry_date"> <?php echo __( 'Expiry date', 'wc-novalnet' ); ?> <span class="required">*</span></label>
        <select id="novalnet_cc_exp_month" >
            <?php
                $cc_month = array( '--' . __( 'Month', 'wc-novalnet' ) . '--' , __( 'January', 'wc-novalnet' ) , __( 'February', 'wc-novalnet' ) , __( 'March', 'wc-novalnet' ), __( 'April', 'wc-novalnet' ), __( 'May', 'wc-novalnet' ), __( 'June', 'wc-novalnet' ), __( 'July', 'wc-novalnet' ), __( 'August', 'wc-novalnet' ), __( 'September', 'wc-novalnet' ), __( 'October', 'wc-novalnet' ), __( 'November', 'wc-novalnet' ), __( 'December', 'wc-novalnet' ) );
                foreach( $cc_month as $month => $name ) :
            ?>
            <option value="<?php echo $month; ?>"> <?php echo $name; ?></option>
            <?php endforeach; ?>
        </select>
        <select id="novalnet_cc_exp_year" >
            <option value="">--<?php echo __( 'Year', 'wc-novalnet' ); ?>--</option>
            <?php
                $today = getdate();
                $limit = ( $this->settings['exp_year_limit'] > 0 && wc_novalnet_digits_check( $this->settings['exp_year_limit'] ) ) ? $this->settings['exp_year_limit'] : 25;

                for ( $i = $today['year']; $i < ( $today['year'] + $limit ); $i++ ) :
            ?>
            <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
            <?php endfor; ?>
        </select>
    </p>
    <p class="form-row form-row-wide">
        <label for="novalnet_cc_cvc"> <?php echo  __( 'CVC/CVV/CID', 'wc-novalnet' ); ?> <span class="required">*</span></label>
        <input type="text" name="novalnet_cc_cvc" id="novalnet_cc_cvc" value="" width="20%" autocomplete="off" onkeypress="return is_number_key(event);" />
        <span  id="novalnet_cc_cvc_hint">
            <img src="<?php echo NN_Fns()->nn_plugin_url() .'/assets/images/novalnet_cc_cvc_hint.png'; ?>" border="0" style="margin-top:0px;" alt="CCV/CVC?">
            <span id="novalnet_cc_cvc_href">
                <img src="<?php echo NN_Fns()->nn_plugin_url() .'/assets/images/novalnet_cc_cvc_href.png'; ?>">
            </span>
        </span>
    </p>
    <?php if ( ! $this->settings['cc_secure_enabled'] && wc_novalnet_fraud_prevention_option( $this->settings ) ) :
        $name = 'novalnet_cc_pin_by_' . $this->settings['pin_by_callback'];
    ?>
    <p class="form-row form-row-wide">
        <label for="<?php echo $name; ?>"> <?php echo ( $this->settings['pin_by_callback'] == 'tel' ) ? __( 'Telephone number', 'wc-novalnet' ) : ( ($this->settings['pin_by_callback'] == 'mobile' ) ? __( 'Mobile number', 'wc-novalnet' ) : __( 'E-mail address', 'wc-novalnet' ) ); ?> <span class="required">*</span> </label>
        <input type="text" class="input-text" name="<?php echo $name; ?>" id="<?php echo $name; ?>" value="" autocomplete="off" />
    </p>
    <?php endif;
  endif;

 if ( ! $this->settings['cc_secure_enabled'] && isset( $_SESSION['novalnet']['novalnet_cc']['tid'] ) && wc_novalnet_fraud_prevention_option( $this->settings ) && $this->settings['pin_by_callback'] != 'email' ) : ?>
    <p class="form-row form-row-wide">
        <label for="novalnet_cc_pin"> <?php echo __( 'Transaction PIN', 'wc-novalnet' ); ?> <span class="required">*</span> </label>
        <input type="text" name="novalnet_cc_pin" class="input-text"  id="novalnet_cc_pin" value="" autocomplete="off" />
        <input type="checkbox" name="novalnet_cc_new_pin" value="1" /><?php echo __( 'Forgot your PIN?', 'wc-novalnet' ); ?>
    </p>
 <?php endif; ?>