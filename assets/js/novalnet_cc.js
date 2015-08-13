/**
 * @category   Novalnet credit card action
 * @package    Novalnet
 * @copyright  Novalnet (https://www.novalnet.de)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *
 */
 jQuery.noConflict();
 jQuery( function( $ ) {
    $( document ).ready(function() {
	$( '#novalnet_cc_holder, #novalnet_cc_no, #novalnet_cc_exp_month, #novalnet_cc_exp_year, #novalnet_cc_cvc div.woocommerce-billing-fields input' ).on( 'change', function() {
            if( $('#nn_cc_hash' ).val() != '' ) {
				$('#nn_cc_hash' ).val('');
			}
        } );
        var form_submit_id = ( $('#order_review button[type=submit]').attr('id') != undefined && $('#order_review button[type=submit]').attr('id') != '' ) ? $('#order_review button[type=submit]').attr('id') : $('#order_review input[type=submit]').attr('id');
            $("body").on( "click", '#'+form_submit_id, function( evt ) {
                if ( $('#payment_method_novalnet_cc').attr('checked') == 'checked' && $('#nn_cc_hash' ).val() == '' ) {
                    evt.preventDefault();
                    evt.stopImmediatePropagation();
                    perform_creditcard_hash_request( evt, form_submit_id );
                }
            } );

        if ( $('#payment_method_novalnet_cc').attr('checked') == 'checked' ) {
            if ( $( '#nn_cc_input_panhash' ).val() != '' && $( '#nn_cc_tag' ).val() == 1  ) {
                var refillpanhash   = $('#nn_cc_input_panhash').val(),
                    nn_vendor       = $('#nn_vendor').val(),
                    nn_auth_code    = $('#nn_auth_code').val(),
                    nn_cc_uniqueid  = $('#nn_cc_uniqueid').val();

                if ( nn_vendor == '' || nn_auth_code == '' || nn_cc_uniqueid == '' ) {
                    return false;
                } else {
                    if ( $('#nn_cc_refill_complete').val() != 1 ) {
                        var data = {
                            'vendor_id': nn_vendor,
                            'vendor_authcode': nn_auth_code,
                            'pan_hash': refillpanhash,
                            'unique_id': nn_cc_uniqueid
                        };
                        $( '#cc_loader' ).show();
                        if( $('#novalnet_cc_no').is(':visible') ) {
                            novalnet_cc_ajax( data, '' );
                        }
                    }
                }
            }
        }

        function perform_creditcard_hash_request( evt, formid ) {
            var cc_type     = $('#novalnet_cc_type').val(),
                cc_holder   = $.trim( $('#novalnet_cc_holder').val() ),
                cc_no       = $.trim( getNumbersOnly( $('#novalnet_cc_no').val() ) ),
                cc_exp_month= $('#novalnet_cc_exp_month').val(),
                cc_exp_year = $('#novalnet_cc_exp_year').val(),
                cc_cvc      = $('#novalnet_cc_cvc').val(),
                nn_vendor   = $('#nn_vendor').val(),
                nn_auth_code= $('#nn_auth_code').val(),
                nn_cc_uniqueid= $('#nn_cc_uniqueid').val(),
                current_date_val = new Date(),
                cchash_object = '';

            if ( nn_vendor == '' || nn_auth_code == '' ) {
                alert( $( '#nn_merchant_valid_error_ccmessage' ).val() );
                evt.stopImmediatePropagation();
                return false;
            }

            if ( cc_type == '' || cc_holder == '' || cc_no == '' || cc_exp_month == 0 || cc_exp_year == '' || cc_cvc == '' ) {
                alert( $( '#nn_cc_valid_error_ccmessage' ).val() );
                evt.stopImmediatePropagation();
                return false;
            } else if ( cc_exp_year == current_date_val.getFullYear() && cc_exp_month < ( current_date_val.getMonth() + 1 ) ) {
                alert( $( '#nn_cc_valid_error_ccmessage' ).val() );
                evt.stopImmediatePropagation();
                return false;
            }

            var cchash_object = {
                'noval_cc_exp_month': cc_exp_month,
                'noval_cc_exp_year': cc_exp_year,
                'noval_cc_holder': cc_holder,
                'noval_cc_no': cc_no,
                'noval_cc_type': cc_type,
                'unique_id': nn_cc_uniqueid,
                'vendor_authcode': nn_auth_code,
                'vendor_id': nn_vendor
            };

            $('#cc_loader').show();
            novalnet_cc_ajax( cchash_object, formid);

        }

        function getNumbersOnly(input_val)
        {
           var input = input_val.replace(/^\s+|\s+$/g, '');
          return input.replace(/[^0-9]/g,'');
        }

        function novalnet_cc_ajax( urlparam , formid) {

            var request_data = $.param( urlparam ),
                nnurl = $( location ).attr( 'protocol' ) +"//payport.novalnet.de/payport_cc_pci";
            if ( 'XDomainRequest' in window && window.XDomainRequest !== null ) {
                var xdr = new XDomainRequest();
                xdr.open('POST' , nnurl);
                xdr.onload = function () {
                     get_hash_response( $.parseJSON( this.responseText ), formid );
                };
                xdr.send( request_data );
            } else {
                jQuery.ajax( {
                    type: 'POST',
                    url: nnurl,
                    data: request_data,
                    success: function( data ) {
                        get_hash_response( data, formid );
                    }
                } );
            }
        }

        function get_hash_response( response , formid) {
            $('#cc_loader').hide();
            if ( response.hash_result == 'success' ) {
                    if( formid != '' ) {
                        $('#nn_cc_hash').val( response.pan_hash );
                        $( '#'+formid ).click();
                        return false;
                    }else{
                        var hash_stringvalue = response.hash_string,
                            cc_holder = '',
                            hash_string = hash_stringvalue.split('&'),
                            acc_hold = hash_stringvalue.match('cc_holder=(.*)&cc_exp_year'),
                            data_length = hash_string.length,
                            array_result = {};

                        if ( acc_hold != null && acc_hold[1] != undefined ) {
                            cc_holder = acc_hold[1];
                        }

                        for ( var i = 0; i < data_length; i++ ) {
                            var hash_result_val = hash_string[i].split('=');
                            array_result[ hash_result_val[0] ] = hash_result_val[1];
                        }
                        try{
                         var holder = decodeURIComponent(escape(cc_holder));
                        }catch(e) {
                         var holder = cc_holder;
                        }

                        $( '#novalnet_cc_holder' ).val( holder );
                        $( '#novalnet_cc_no' ).val( getNumbersOnly( array_result.cc_no ) );
                        $( '#novalnet_cc_exp_month' ).val( array_result.cc_exp_month );
                        $( '#novalnet_cc_exp_year' ).val( array_result.cc_exp_year );
                        $( '#novalnet_cc_type' ).val( array_result.cc_type );
                        $('#nn_cc_refill_complete').val('1');
                        return false;
                }
                return false;
            }else{
                alert(response.hash_result);
                return false;
            }
        }

    } );
 } );
