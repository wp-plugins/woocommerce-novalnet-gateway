/**
 * @category   Novalnet checkout action
 * @package    Novalnet
 * @copyright  Novalnet (https://www.novalnet.de)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *
 */

 jQuery( function( $ ) {
    fetch_user_details();
	
    $( document ).ajaxComplete(function( event, xhr, settings ) {
        if ( ( $( 'input[name=woocommerce_pay]' ).length && $('input[name=woocommerce_pay]').val() == 1 ) || ( $( 'input[name=woocommerce_change_payment]' ).length && ( $('input[name=woocommerce_change_payment]').val() != undefined ) ) ) {
            $( document ).on( 'click','input[type=radio]', function() {
                if ( $( this ).attr('name') == 'payment_method' ) {
                    fetch_user_details();
                }
            } );
        } else {
            update_user_details_from_checkout();
        }

        $( 'div.woocommerce-billing-fields input' ).on( 'change', function() {
            update_user_details_from_checkout();
        } );

        $( window ).resize( function() {
            get_sepa_responsivesize();
        } );
    } );

    function update_user_details_from_checkout() {
        if ( $( '#billing_first_name' ).length )
            var fname = $( '#billing_first_name' ).val();
        if ( $( '#billing_last_name' ).length )
            var lname = $( '#billing_last_name' ).val();
        fname = ( fname == undefined ) ? '' : fname;
        lname = ( lname == undefined ) ? '' : lname;
        if ( fname == undefined && lname == undefined )
            fname = lname = '';
        var customer_name = jQuery.trim( fname + ' ' + lname );

        if ( $( '#novalnet_sepa_account_holder' ).length && $( '#novalnet_sepa_account_holder' ).val() == '' )
             $( '#novalnet_sepa_account_holder' ).val( customer_name );

        if ( $( '#novalnet_sepa_pin_by_tel' ).length && $( '#novalnet_sepa_pin_by_tel' ).val() == '' )
             $( '#novalnet_sepa_pin_by_tel').val( $( '#billing_phone' ).val() );
        if ( $( '#novalnet_sepa_pin_by_email' ).length && $( '#novalnet_sepa_pin_by_email' ).val() == '' )
             $( '#novalnet_sepa_pin_by_email' ).val( $( '#billing_email' ).val() );

        if ( $( '#novalnet_cc_holder' ).length && $( '#novalnet_cc_holder' ).val() == '' )
             $( '#novalnet_cc_holder' ).val( customer_name );
        if ( $( '#novalnet_cc_pin_by_tel' ).length && $( '#novalnet_cc_pin_by_tel' ).val() == '' )
             $( '#novalnet_cc_pin_by_tel' ).val( $( '#billing_phone' ).val() );
        if ( $( '#novalnet_cc_pin_by_email' ).length && $( '#novalnet_cc_pin_by_email' ).val() == '' )
             $( '#novalnet_cc_pin_by_email' ).val( $( '#billing_email' ).val() );

        if ( $( '#novalnet_invoice_pin_by_tel' ).length && $( '#novalnet_invoice_pin_by_tel' ).val() == '' )
             $( '#novalnet_invoice_pin_by_tel' ).val( $( '#billing_phone' ).val() );
        if ( $( '#novalnet_invoice_pin_by_email' ).length && $( '#novalnet_invoice_pin_by_email' ).val() == '' )
             $( '#novalnet_invoice_pin_by_email' ).val( $( '#billing_email' ).val() );
    }

    function fetch_user_details() {

        if ( $( '#novalnet_customer_info' ).length )
            var customer_info = $('#novalnet_customer_info').val();
        if ( customer_info != undefined && customer_info != '' ) {
            var customer_info_string = customer_info.split('|'),
                array_result = {},
                data_length = customer_info_string.length
                customer_name = '';

            for ( var i = 0; i < data_length; i++ ) {
                var hash_result_val = customer_info_string[ i ].split('=');
                array_result[ hash_result_val[0] ] = hash_result_val[1];
            }

            customer_name =  array_result.first_name + " " + array_result.last_name;
            if ( $( '#novalnet_cc_holder').length )
                 $( '#novalnet_cc_holder').val( customer_name );
            if ( $( '#novalnet_sepa_account_holder').length )
                 $( '#novalnet_sepa_account_holder').val( customer_name );
        } else {
            update_user_details_from_checkout();
        }
    }
	
 } );

 /************************ Direct Debit SEPA payment ***********************/

 function get_sepa_responsivesize() {
    var window_width = jQuery( window ).width();
    if ( window_width <= 720 ) {
        jQuery('#sepa_mandate_overlay_block').addClass('mobile');
    } else {
        jQuery('#sepa_mandate_overlay_block').removeClass('mobile');
    }
 }

 function confirm_mandate_overlay() {
	document.onkeydown = null;
    jQuery( '#novalnet_sepa_mandate_confirm' ).removeAttr( 'disabled' );
    jQuery( '#sepa_mandate_overlay_block' ).hide( 60 );
    jQuery( '.bgCover' ).css( { display:'none' } );
    return true;
 }

 function close_mandate_overlay() {
	document.onkeydown = null;
    if ( jQuery( '#novalnet_sepa_mandate_confirm' ).is( ':checked' ) ) {
        jQuery( '#novalnet_sepa_mandate_confirm' ).attr( ':checked', false );
    }
    jQuery( '#novalnet_sepa_mandate_confirm' ).attr("checked",false);
    jQuery( '#novalnet_sepa_mandate_confirm' ).removeAttr('disabled');
    jQuery( '#sepa_mandate_overlay_block' ).hide(60);
    jQuery( '.bgCover' ).css( {display:'none'} );
    return true;
 }

 /************************ Common Functions ************************/

 function is_number_key(event, allowstring ) {
    var keycode = ( 'which' in event ) ? event.which : event.keyCode,
        event = event || window.event,
        reg = '' ;
    if ( allowstring == 'alphanumeric' ) {
        reg = /^(?:[0-9a-zA-Z]+$)/;
    } else if ( allowstring == 'holder' ) {
        var reg = /^(?:[0-9a-zA-Z&\s]+$)/;
    } else {
        var reg = ( ( event.target || event.srcElement ).id == 'novalnet_cc_no' ) ? /^(?:[0-9\s]+$)/ : /^(?:[0-9]+$)/ ;
    }
    return ( reg.test( String.fromCharCode( keycode ) ) || keycode == 0 || keycode == 8 );
 }

 function trim_space( input_val ) {
    var input = jQuery.trim(input_val.replace(/\b \b/g, ''));
    return jQuery.trim(input.replace(/\s{2,}/g, ''));
 }
