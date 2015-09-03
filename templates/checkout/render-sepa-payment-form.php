<?php
 if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
 }

/**
 * The template is for displaying the payment form for
 * Novalnet Direct Debit SEPA payment.
 *
 * Copyright (c) Novalnet
 *
 * This script is only free to the use for merchants of Novalnet. If
 * you have found this script useful a small recommendation as well as a
 * comment on merchant form would be greatly appreciated.
 *
 * @version     10.1.1
 * @package     Novalnet/Templates
 * @author      Novalnet
 *
 */

 $sepa_input_hash = ( NN_Fns()->global_settings['auto_refill'] &&  isset($_SESSION['novalnet']['novalnet_sepa']['pseudo_hash']) && $_SESSION['novalnet']['novalnet_sepa']['pseudo_hash'] != '' ) ? $_SESSION['novalnet']['novalnet_sepa']['pseudo_hash'] : wc_novalnet_sepa_last_payment_refill( $this->settings['last_succ_refill'] );

 echo '<input type="hidden" id="nn_sepa_tag" value="' .( ! isset( $_SESSION['novalnet']['novalnet_sepa']['tid'] ) ? 1 : 0 ) . '"/>';

  if ( ! isset( $_SESSION['novalnet']['novalnet_sepa']['tid'] ) ) : ?>
    <div class="nov_loader" id="sepa_loader" style="display:none"></div>
    <input type="hidden" id="nn_sepa_hash"  name="nn_sepa_hash" value=""/>
    <input type="hidden" id="nn_sepa_uniqueid"  name="nn_sepa_uniqueid" value="<?php echo wc_novalnet_random_string(); ?>" />
    <input type="hidden" id="nn_sepa_input_panhash" value="<?php echo $sepa_input_hash; ?>"/>
    <input type="hidden" id="nn_sepa_refill_complete" value="0"/>
    <input type="hidden" id="nn_sepa_mandate_ref" value=""/>
    <input type="hidden" id="nn_sepa_iban" value=""/>
    <input type="hidden" id="nn_sepa_bic" value=""/>
    <input type="hidden" id="nn_sepa_mandate_date" value=""/>
    <input type="hidden" id="nn_plugin_url"  value="<?php echo NN_Fns()->nn_plugin_url(); ?>"/>
    <input type="hidden" id="nn_lang_mandate_confirm"  value="<?php echo __( 'Please accept the SEPA direct debit mandate', 'wc-novalnet' ); ?>"/>
    <input type="hidden" id="nn_lang_valid_merchant_credentials" value="<?php echo __( 'Please fill in all the mandatory fields', 'wc-novalnet' ); ?>"/>
    <input type="hidden" id="nn_lang_valid_account_details"  value="<?php echo __( 'Your account details are invalid', 'wc-novalnet' ); ?>"/>
    <div id="nn_overlay_id">
        <?php include( dirname( __FILE__ ) .'/render-sepa-overlay.php' ); ?>
    </div>

    <p class="form-row form-row-wide">
        <label for="novalnet_sepa_account_holder"> <?php echo __( 'Account holder', 'wc-novalnet' ); ?> <span class="required">*</span></label>
        <input id="novalnet_sepa_account_holder" class="input-text" type="text"  autocomplete="off" placeholder="" name="novalnet_sepa_account_holder" onkeypress="return is_number_key(event,'holder' );" />
    </p>
    <p class="form-row form-row-wide">
        <label for="novalnet_sepa_bank_country"> <?php echo __( 'Bank country', 'wc-novalnet' ); ?> <span class="required">*</span></label>
        <select id="novalnet_sepa_bank_country">
        <option value="">-- <?php echo __( 'Select', 'wc-novalet' ); ?>--</option>
        <?php
            foreach ( WC()->countries->get_allowed_countries() as $country_code => $country_name ) {
                $selected = ( $country_code ==  WC()->customer->country ) ? 'selected' : '';
        ?>
            <option value="<?php echo $country_code; ?>" <?php echo $selected;  ?> ><?php echo $country_name; ?></option>
        <?php } ?>
        </select>
    </p>
    <p class="form-row form-row-wide">
        <label for="novalnet_sepa_iban"> <?php echo __( 'IBAN or Account number', 'wc-novalnet' ); ?> <span class="required">*</span></label>
        <input type="text" class="input-text" id="novalnet_sepa_iban" value="" autocomplete="off" onkeypress="return is_number_key(event, 'alphanumeric' );" /><br/><span id="novalnet_sepa_iban_span"></span>
    </p>
    <p class="form-row form-row-wide">
        <label for="novalnet_sepa_bic"> <?php echo __( 'BIC or Bank code', 'wc-novalnet' ); ?> <span class="required">*</span></label>
        <input type="text" class="input-text" id="novalnet_sepa_bic" value="" autocomplete="off" onkeypress="return is_number_key(event,'alphanumeric' );" /><br/><span id="novalnet_sepa_bic_span"></span>
    </p>
    <p class="form-row form-row-wide">
        <input type="checkbox" id="novalnet_sepa_mandate_confirm" value="1"/><?php echo __( 'I hereby grant the SEPA direct debit mandate and confirm that the given IBAN and BIC are correct', 'wc-novalnet' );?>
    </p>
    <?php if ( wc_novalnet_fraud_prevention_option( $this->settings ) ) :
        $name = 'novalnet_sepa_pin_by_' . $this->settings['pin_by_callback'];
    ?>
    <p class="form-row form-row-wide">
        <label for="<?php echo $name; ?>"> <?php echo ( $this->settings['pin_by_callback'] == 'tel' ) ? __( 'Telephone number', 'wc-novalnet' ) : ( ( $this->settings['pin_by_callback'] == 'mobile' ) ? __( 'Mobile number', 'wc-novalnet' ) : __( 'E-mail address', 'wc-novalnet' ) ); ?> <span class="required">*</span> </label>
        <input type="text" class="input-text"  name="<?php echo $name; ?>" id="<?php echo $name; ?>" value="" autocomplete="off" />
    </p>
 <?php endif;
 endif;
 if ( isset( $_SESSION['novalnet']['novalnet_sepa']['tid'] ) && wc_novalnet_fraud_prevention_option( $this->settings ) && $this->settings['pin_by_callback'] != 'email' ) : ?>
    <p class="form-row form-row-wide">
        <label for="novalnet_sepa_pin"> <?php echo __( 'Transaction PIN', 'wc-novalnet' ); ?> <span class="required">*</span> </label>
        <input type="text" class="input-text"  name="novalnet_sepa_pin" id="novalnet_sepa_pin" value="" autocomplete="off" />
        <input type="checkbox" name="novalnet_sepa_new_pin" value="1" /><?php echo __( 'Forgot your PIN?', 'wc-novalnet' ); ?>
    </p>
 <?php endif; ?>
