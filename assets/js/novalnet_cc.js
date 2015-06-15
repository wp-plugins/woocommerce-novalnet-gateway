/**
 * @category   Novalnet credit card action
 * @package    Novalnet
 * @copyright  Novalnet (http://www.novalnet.de)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *
 */
 jQuery.noConflict();
 jQuery( function( $ ) {
	$( document ).ready(function() {

		var version = $('#shop_version').val();
        if ( version == '2.1.3' || version == '2.1.7' ) {
			$("body").on( "click", "#place_order", function( evt ) {
				if ( $('#payment_method_novalnet_cc').attr('checked') == 'checked' && $('#nn_cc_hash' ).val() == '' ) {
					evt.preventDefault();
					perform_creditcard_hash_request();
				}
			} );
		} else {
			$( '#place_order' ).on( 'click', function( evt ) {
				if ( $('#payment_method_novalnet_cc').attr('checked') == 'checked' && $('#nn_cc_hash' ).val() == '' ) {
					evt.preventDefault();
					perform_creditcard_hash_request();
				}
			} );
		}

		if ( $('#payment_method_novalnet_cc').attr('checked') == 'checked' ) {
			if ( $( '#nn_cc_input_panhash' ).val() != '' && $( '#nn_cc_tag' ).val() == 1  ) {
				var refillpanhash 	= $('#nn_cc_input_panhash').val(),
					nn_vendor 		= $('#nn_vendor').val(),
					nn_auth_code	= $('#nn_auth_code').val(),
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
						novalnet_cc_ajax( data, 'ccrefill' );
					}
				}
			}
		}

		function perform_creditcard_hash_request() {
			var cc_type 	= $('#novalnet_cc_type').val(),
				cc_holder 	= $.trim( $('#novalnet_cc_holder').val() ),
				cc_no 		= $.trim( $('#novalnet_cc_no').val() ),
				cc_exp_month= $('#novalnet_cc_exp_month').val(),
				cc_exp_year = $('#novalnet_cc_exp_year').val(),
				cc_cvc 		= $('#novalnet_cc_cvc').val(),
				nn_vendor 	= $('#nn_vendor').val(),
				nn_auth_code= $('#nn_auth_code').val(),
				nn_cc_uniqueid= $('#nn_cc_uniqueid').val(),
				current_date_val = new Date(),
				cchash_object = '';

			if ( nn_vendor == '' || nn_auth_code == '' ) {
				alert( $( '#nn_merchant_valid_error_ccmessage' ).val() );
				return false;
			}

			if ( cc_type == '' || cc_holder == '' || cc_no == '' || cc_exp_month == 0 || cc_exp_year == '' || cc_cvc == '' ) {
				alert( $( '#nn_cc_valid_error_ccmessage' ).val() );
				return false;
			} else if ( cc_exp_year == current_date_val.getFullYear() && cc_exp_month < ( current_date_val.getMonth() + 1 ) ) {
				alert( $( '#nn_cc_valid_error_ccmessage' ).val() );
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
			novalnet_cc_ajax( cchash_object, "cchash" );

		}

		function novalnet_cc_ajax( urlparam, code ) {

			var request_data = $.param( urlparam ),
				nnurl = $( location ).attr( 'protocol' ) +"//payport.novalnet.de/payport_cc_pci";
			if ( 'XDomainRequest' in window && window.XDomainRequest !== null ) {
				var xdr = new XDomainRequest();
				xdr.open('POST' , nnurl);
				xdr.onload = function () {
					 get_hash_response( $.parseJSON( this.responseText ), code );
				};
				xdr.send( request_data );
			} else {
				jQuery.ajax( {
					type: 'POST',
					url: nnurl,
					data: request_data,
					success: function( data ) {
						get_hash_response( data, code );
					}
				} );
			}
		}

		function get_hash_response( response, code ) {
			$('#cc_loader').hide();
			if ( response.hash_result == 'success' ) {
				switch( code ) {
					case 'cchash':
						$('#nn_cc_hash').val( response.pan_hash );
						$("#place_order").parents('form').submit();
						break;
					case 'ccrefill':
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

						$( '#novalnet_cc_holder' ).val( decodeURIComponent( cc_holder ) );
						$( '#novalnet_cc_no' ).val( array_result.cc_no );
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
