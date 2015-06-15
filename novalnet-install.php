<?php

 if ( ! defined('ABSPATH') ) {
    exit; // Exit if accessed directly
 }

/**
 * Novalnet Plugin Installation process
 *
 * This file is used for creating tables while installing the plugins.
 *
 * Copyright (c) Novalnet
 *
 * This script is only free to the use for merchants of Novalnet. If
 * you have found this script useful a small recommendation as well as a
 * comment on merchant form would be greatly appreciated.
 *
 * @package		Novalnet
 * @author 		Novalnet
 *
 */

 /**
  * Creates Novalnet tables while activating the plugins
  * Calls from the hook "register_activation_hook"
  *
  * @param none
  * @return void
  */
 function novalnet_activation_process() {
	global $wpdb;
	$wpdb->hide_errors();
	$charset_collate = $wpdb->get_charset_collate();
	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

	if ( ! get_option('novalnet_db_version') || get_option('novalnet_db_version') != NN_VERSION ) {

		$txn_table = "
			CREATE TABLE IF NOT EXISTS {$wpdb->prefix}novalnet_transaction_detail (
			`id` bigint(20) NOT NULL AUTO_INCREMENT COMMENT 'Auto Increment ID',
			`order_no` bigint(20) unsigned NOT NULL COMMENT 'Post ID for the order in shop',
			`vendor_id` bigint(11) unsigned NOT NULL COMMENT 'Novalnet Vendor ID',
			`auth_code` varchar(30) NOT NULL COMMENT 'Novalnet Authentication code',
			`product_id` bigint(8) unsigned NOT NULL COMMENT 'Novalnet Project ID',
			`tariff_id` bigint(8) unsigned NOT NULL COMMENT 'Novalnet Tariff ID',
			`payment_id` bigint(11) unsigned NOT NULL COMMENT 'Payment ID',
			`payment_type` varchar(50) NOT NULL COMMENT 'Executed Payment type of this order',
			`tid` bigint(20) unsigned NOT NULL COMMENT 'Novalnet Transaction Reference ID',
			`subs_id` bigint(8) unsigned DEFAULT NULL COMMENT 'Subscription Status',
			`amount` bigint(11) NOT NULL COMMENT 'Transaction amount in cents',
			`callback_amount` bigint(11) DEFAULT '0' COMMENT 'Transaction paid amount in cents',
			`refunded_amount` bigint(11) DEFAULT '0' COMMENT 'Transaction refunded amount in cents',
			`currency` varchar(5) NOT NULL COMMENT 'Transaction currency',
			`status` varchar(9) NOT NULL COMMENT 'Novalnet transaction status in response',
			`gateway_status` varchar(9) NOT NULL COMMENT 'Novalnet transaction status',
			`test_mode` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT 'Transaction test mode status',
			`customer_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Customer ID from shop',
			`customer_email` varchar(50) DEFAULT NULL COMMENT 'Customer ID from shop',
			`date` datetime NOT NULL COMMENT 'Transaction Date for reference',
			`active` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT 'Status',
			`process_key` varchar(255) DEFAULT NULL COMMENT 'Encrypted process key',
			PRIMARY KEY (`id`),
			INDEX `tid` (`tid`),
			INDEX `order_no` (`order_no`)
			) $charset_collate COMMENT='Novalnet Transaction History';";
		dbDelta( $txn_table );

		$invoice_table = "
			CREATE TABLE IF NOT EXISTS {$wpdb->prefix}novalnet_invoice_details (
			`id` bigint(20) NOT NULL AUTO_INCREMENT COMMENT 'Auto Increment ID',
			`order_no` bigint(20) NOT NULL COMMENT 'Post ID for the order in shop',
			`payment_type` varchar(50) NOT NULL COMMENT 'Callback Payment Type',
			`amount` bigint(11) DEFAULT NULL COMMENT 'Amount in cents',
			`invoice_due_date` date DEFAULT NULL,
			`invoice_bank_details` text DEFAULT NULL COMMENT 'Novalnet Invoice account details',
			PRIMARY KEY (`id`),
			INDEX `order_no` (`order_no`)
			) $charset_collate COMMENT='Novalnet Invoice Payment Details';";
		dbDelta( $invoice_table );

		$subs_table = "
			CREATE TABLE IF NOT EXISTS {$wpdb->prefix}novalnet_subscription_details (
			`id` bigint(20) NOT NULL AUTO_INCREMENT COMMENT 'Auto Increment ID',
			`order_no` bigint(20) NOT NULL COMMENT 'Post ID for the order in shop',
			`payment_type` varchar(50) NOT NULL COMMENT 'Payment Type',
			`recurring_payment_type` varchar(50) NOT NULL COMMENT 'Recurring Payment Type',
			`recurring_amount` bigint(11) DEFAULT NULL COMMENT 'Amount in cents',
			`tid` bigint(20) unsigned NOT NULL COMMENT 'Novalnet Transaction Reference ID',
			`recurring_tid` bigint(20) unsigned NOT NULL COMMENT 'Novalnet Transaction Reference ID',
			`subs_id` bigint(8) unsigned DEFAULT NULL COMMENT 'Subscription Status',
			`signup_date` datetime NOT NULL COMMENT 'Subscription signup date',
			`next_payment_date` datetime NOT NULL COMMENT 'Subscription next cycle date',
			`suspended_date` datetime NOT NULL COMMENT 'Subscription suspended date',
			`termination_reason` varchar(255) DEFAULT NULL COMMENT 'Subscription termination reason by merchant',
			`termination_at` datetime DEFAULT NULL COMMENT 'Subscription terminated date',
			`subscription_length` bigint(10) NOT NULL DEFAULT 0 COMMENT 'Length of Subscription',
			PRIMARY KEY (`id`),
			INDEX `order_no` (`order_no`),
			INDEX `tid` (`tid`)
			) $charset_collate COMMENT='Novalnet Subscription Payment Details';";
		dbDelta( $subs_table );

		$callback_table = "
			CREATE TABLE IF NOT EXISTS {$wpdb->prefix}novalnet_callback_history (
			`id` bigint(20) NOT NULL AUTO_INCREMENT COMMENT 'Auto Increment ID',
			`date` datetime NOT NULL COMMENT 'Callback DATE TIME',
			`payment_type` varchar(50) NOT NULL COMMENT 'Callback Payment Type',
			`status` varchar(9) DEFAULT NULL COMMENT 'Callback Status',
			`callback_tid`bigint(20) unsigned NOT NULL COMMENT 'Callback Reference ID',
			`org_tid` bigint(20) unsigned DEFAULT NULL COMMENT 'Original Transaction ID',
			`amount` bigint(11) DEFAULT NULL COMMENT 'Amount in cents',
			`currency` varchar(5) DEFAULT NULL COMMENT 'Currency',
			`product_id` bigint(8) unsigned NOT NULL COMMENT 'Novalnet Project ID',
			`order_no` bigint(20) NOT NULL COMMENT 'Post ID for the order in shop',
			PRIMARY KEY (`id`),
			INDEX `order_no` (`order_no`),
			INDEX `org_tid` (`org_tid`)
			) $charset_collate COMMENT='Novalnet Callback History';";
		dbDelta( $callback_table );

		$aff_table = "
			CREATE TABLE IF NOT EXISTS {$wpdb->prefix}novalnet_aff_account_detail (
			`id` bigint(20) NOT NULL AUTO_INCREMENT COMMENT 'Auto Increment ID',
			`vendor_id` bigint(11) unsigned NOT NULL,
			`vendor_authcode` varchar(40) NOT NULL,
			`product_id` bigint(11) unsigned NOT NULL,
			`product_url` varchar(200) NOT NULL,
			`activation_date` datetime NOT NULL,
			`aff_id` bigint(11) unsigned DEFAULT NULL,
			`aff_authcode` varchar(40) DEFAULT NULL,
			`aff_accesskey` varchar(40) DEFAULT NULL,
			PRIMARY KEY (`id`),
			INDEX `vendor_id` (`vendor_id`),
			INDEX `aff_id` (`aff_id`)
			) $charset_collate COMMENT='Novalnet merchant / affiliate account information';";
		dbDelta( $aff_table );

		$aff_user_table = "
			CREATE TABLE IF NOT EXISTS  {$wpdb->prefix}novalnet_aff_user_detail (
			`id` bigint(20) NOT NULL AUTO_INCREMENT COMMENT 'Auto Increment ID',
			`aff_id` bigint(11) unsigned NOT NULL COMMENT 'Affiliate merchant ID',
			`customer_id` bigint(20) unsigned NOT NULL COMMENT 'Affiliate Customer ID',
			`aff_shop_id` bigint(20) unsigned NOT NULL COMMENT 'Post ID for the order in shop',
			`aff_order_no` varchar(20) NOT NULL,
			PRIMARY KEY (`id`),
			INDEX `aff_id` (`aff_id`),
			INDEX `customer_id` (`customer_id`),
			INDEX `aff_order_no` (`aff_order_no`)
			) COMMENT='Novalnet affiliate customer account information';";

		dbDelta( $aff_user_table );
		if ( ! get_option('novalnet_db_version') ) {
			add_option( 'novalnet_db_version', NN_VERSION );
		}elseif ( get_option( 'novalnet_db_version' ) != NN_VERSION ) {
			update_option( 'novalnet_db_version', NN_VERSION );
		}
	}
 }

 /**
  * Deletes the novalnet configuration values from wp_options tables
  * Calls from the hook "register_deactivation_hook"
  *
  * @param none
  * @return void
  */
 function novalnet_uninstallation_process() {
	global $wpdb;
	$wpdb->query("delete from $wpdb->options where option_name like '%novalnet%'");
 }
?>
