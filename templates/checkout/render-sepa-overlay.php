<?php

 if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
 }

/**
 * The file is for displaying the Overlay for
 * Novalnet Direct Debit SEPA mandate Confirmation.
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
?>

<div class="bgCover"></div>

<!-- Loading Icon -->
<div id='sepa_mandate_overlay_block_first' style='display:none;' class='overlay_window_block'>
    <img src= "<?php echo NN_Fns()->nn_plugin_url() . '/assets/images/novalnet_loading_icon.gif'; ?>" alt='Loading...'/>
</div>

<!-- Overlay Content -->
<div id='sepa_mandate_overlay_block' style='display:none;' class='overlay_window_block'>
    <!-- Overlay Header -->
    <div class='nn_header'>
        <h1 id="sepa_overlay_text"><?php echo __( 'Direct debit SEPA mandate confirmation','wc-novalnet' ); ?></h1>
    </div>

    <!-- Overlay Body -->
    <div class='body_div' id='overlay_window_block_body'>
        <table class="nov_overlay">
            <tr>
                <td><?php echo __( 'Creditor', 'wc-novalnet' ); ?></td>
                <td><span id='sepa_overlay_payee_span'> </span></td>
            </tr>
            <tr>
                <td><?php echo __( 'Creditor identifier','wc-novalnet' ); ?></td>
                <td><span id='sepa_overlay_creditoridentificationnumber_span'> </span></td>
            </tr>
            <tr>
                <td><?php echo __( 'Mandate reference', 'wc-novalnet' ); ?></td>
                <td><span id='sepa_overlay_mandatereference_span'> </span></td>
            </tr>
        </table>

        <?php echo __( 'By granting this mandate form, I authorize (A) the creditor to send instructions to my bank to debit my account and (B) my bank to debit my account in accordance with the instructions from the creditor for this and future payments. <br/><br/> As part of my rights, I am entitled to a refund from my bank under the terms and conditions of my agreement with my bank. A refund must be claimed within eight weeks from the date on which my account was debited.', 'wc-novalnet' ); ?>
        <br/><br/>
        <table class="nov_overlay">
            <tr>
                <td><?php echo __( 'Name of the payer', 'wc-novalnet' ); ?></td>
                <td><span class='sepa_overlay_enduser_name_span'> </span></td>
            </tr>
            <tr id="overlay_company">
                <td><?php echo __( 'Company name', 'wc-novalnet' ); ?></td>
                <td><span id='sepa_overlay_enduser_company_span'> </span></td>
            </tr>
            <tr id="overlay_address">
                <td><?php echo __( 'Street name and number', 'wc-novalnet' ); ?></td>
                <td><span id='sepa_overlay_enduser_address_span'> </span></td>
            </tr>
            <tr id="overlay_city">
                <td><?php echo __( 'Postal code and City', 'wc-novalnet' ); ?></td>
                <td><span id='sepa_overlay_enduser_zip_span'> </span> <span class='sepa_overlay_enduser_city_span'> </span></td>
            </tr>
            <tr>
                <td><?php echo __( 'Country', 'wc-novalnet' ); ?></td>
                <td><span id='sepa_overlay_enduser_country_span'> </span></td>
            </tr>
            <tr id="overlay_email">
                <td><?php echo __( 'E-mail', 'wc-novalnet' ); ?></td>
                <td><span id='sepa_overlay_enduser_email_span'> </span></td>
            </tr>
            <tr id='nn_sepa_overlay_iban_tr'>
                <td>IBAN</td>
                <td><span id='sepa_overlay_iban_span'> </span></td>
            </tr>
            <tr id='nn_sepa_overlay_bic_tr'>
                <td>BIC</td>
                <td><span id='sepa_overlay_bic_span'> </span></td>
            </tr>
            <tr>
                <td colspan=2>
                    <span class='sepa_overlay_enduser_city_span'> </span>,
                    <span id='sepa_overlay_mandatedate_span'> </span>,
                    <span class='sepa_overlay_enduser_name_span'> </span>
                </td>
            </tr>
        </table>

    </div>

    <!-- Overlay Footer -->
    <div class='nn_footer'>
        <input type='button' value='<?php echo __( 'Confirm', 'wc-novalnet' ); ?>' class='sepa_overlay_buttons' id='mandate_confirm' onclick="confirm_mandate_overlay();" />
        <input type='button' value='<?php echo __( 'Cancel', 'wc-novalnet' ); ?>' class='sepa_overlay_buttons' id='mandate_cancel' onclick="close_mandate_overlay();" />
        <img src="<?php echo NN_Fns()->nn_plugin_url() . '/assets/images/novalnet_logo.png'; ?>" alt='Novalnet' style='float:right; width:120px'/>
    </div>
</div>
<!-- Mandate overlay END-->