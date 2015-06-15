<?php
  if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
 }
/**
 * The file is for displaying the payment template for
 * Novalnet Invoice payment.
 *
 * Copyright (c) Novalnet
 *
 * This script is only free to the use for merchants of Novalnet. If
 * you have found this script useful a small recommendation as well as a
 * comment on merchant form would be greatly appreciated.
 *
 * @version		10.0.0
 * @package		Novalnet/Templates
 * @author 		Novalnet
 *
 */

 if ( wc_novalnet_fraud_prevention_option(  $this->settings  ) ) :

	if ( ! isset( $_SESSION['novalnet']['novalnet_invoice']['tid'] ) ) :
		$name = 'novalnet_invoice_pin_by_' .  $this->settings['pin_by_callback']; ?>

	<p class="form-row form-row-wide">
		<label for="<?php echo $name; ?>"> <?php echo ( $this->settings['pin_by_callback'] == 'tel') ? __('Telephone number', 'wc-novalnet') : ( ( $this->settings['pin_by_callback'] == 'mobile') ? __('Mobile number', 'wc-novalnet') : __('E-mail address', 'wc-novalnet') ); ?> <span class="required">*</span> </label>
		<input type="text" class="input-text"  name="<?php echo $name; ?>" id="<?php echo $name; ?>" value="" autocomplete="off" />
	</p>

	<?php endif;

	if ( isset( $_SESSION['novalnet']['novalnet_invoice']['tid'] ) && wc_novalnet_fraud_prevention_option( $this->settings ) && $this->settings['pin_by_callback'] != 'email' ) : ?>
		<p class="form-row form-row-wide">
		<label for="novalnet_invoice_pin"> <?php echo __('Transaction PIN', 'wc-novalnet'); ?> <span class="required">*</span> </label>
		<input type="text" class="input-text"  name="novalnet_invoice_pin" id="novalnet_invoice_pin" value="" autocomplete="off" />
		<input type="checkbox" name="novalnet_invoice_new_pin" value="1" /><?php echo __('Forgot your PIN?', 'wc-novalnet'); ?>
	</p>
	<?php
	endif;
 endif;
?>