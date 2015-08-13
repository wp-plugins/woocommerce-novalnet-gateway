/**
 * @category   Novalnet SEPA action
 * @package    Novalnet
 * @copyright  Novalnet (https://www.novalnet.de)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *
 */

  jQuery.noConflict();
  jQuery( function( $ ) {
    $( document ).ready(function() {
        var form_submit_id = ( $('#order_review button[type=submit]').attr('id') != undefined && $('#order_review button[type=submit]').attr('id') != '' ) ? $('#order_review button[type=submit]').attr('id') : $('#order_review input[type=submit]').attr('id');
        $( '#novalnet_sepa_mandate_confirm' ).attr( "checked",false );

        $( '#novalnet_sepa_iban, #novalnet_sepa_bic, #novalnet_sepa_bank_country, #novalnet_sepa_account_holder, div.woocommerce-billing-fields input' ).on( 'change', function() {
            sepa_mandate_unconfirm_process();
        } );

        $( '#payment_method_novalnet_sepa' ).on( 'click', function () {
            if ( $( '#nn_sepa_input_panhash' ).val() != '' ) { process_sepa_refill_call(); }
        });

        if ( $( 'input[name=payment_method]:checked' ).val() == 'novalnet_sepa' && $( '#nn_sepa_tag' ).val() == 1 ) {
            if ( $( '#nn_sepa_input_panhash' ).val() != '' ) { process_sepa_refill_call(); }
        }

        $("body").on( "click", "#"+form_submit_id, function( evt ) {
            if( $( '#payment_method_novalnet_sepa' ).attr( 'checked' ) == 'checked' && $( "#novalnet_sepa_mandate_confirm" ).is( ":checked" ) == false && $( '#nn_sepa_tag' ).val() == 1 ) {
                alert( $( '#nn_lang_mandate_confirm' ).val() );
                evt.stopImmediatePropagation();
                return false;
            }
        } );
        $("body").on( "click", "#novalnet_sepa_mandate_confirm", function(evt) {
            evt.stopImmediatePropagation();
            perform_sepa_iban_bic_call();
        });
        function process_sepa_refill_call() {
            var refillpanhash   = $( '#nn_sepa_input_panhash' ).val(),
                nn_vendor       = $( '#nn_vendor' ).val(),
                nn_auth_code    = $( '#nn_auth_code' ).val(),
                nn_sepa_uniqueid= $( '#nn_sepa_uniqueid' ).val();

            if ( nn_vendor == '' || nn_auth_code == '' || nn_sepa_uniqueid == '' ) {
                return false;
            } else {
                if ( $('#nn_sepa_refill_complete').val() != 1 && $( '#nn_sepa_tag' ).val() == 1 ) {
                    var separefill_request = {
                        'vendor_id': nn_vendor,
                        'vendor_authcode': nn_auth_code,
                        'sepa_hash': refillpanhash,
                        'unique_id': nn_sepa_uniqueid,
                        'sepa_data_approved': 1,
                        'mandate_data_req': 1
                    };
                    $( '#sepa_loader' ).show();
                    novalnet_sepa_ajax( separefill_request, 'separefill' );
                }
            }
        }

        function perform_sepa_iban_bic_call() {
            if ( $( '#novalnet_sepa_mandate_confirm' ).is(':checked') != true ) {
                close_mandate_overlay();
                return true;
            }
            $( '#novalnet_sepa_mandate_confirm' ).attr( 'disabled', 'disabled' );
            var bank_country    = $( '#novalnet_sepa_bank_country' ).val(),
                account_holder  = $.trim( $( '#novalnet_sepa_account_holder' ).val() ),
                account_no      = $( '#novalnet_sepa_iban' ).val(),
                bank_code       = $( '#novalnet_sepa_bic' ).val(),
                nn_vendor       = $( '#nn_vendor' ).val(),
                nn_auth_code    = $( '#nn_auth_code' ).val(),
                nn_sepa_uniqueid= $( '#nn_sepa_uniqueid' ).val();

            if ( isNaN( account_no ) && isNaN( bank_code ) ) {
                $( '#novalnet_sepa_iban_span' ).html('');
                $( '#novalnet_sepa_bic_span' ).html('');
                if ( $( '#nn_sepa_hash' ).val() != '' ) {
                    show_mandate_overlay();
                    return false;
                }else {
                    process_sepa_hash_call();
                    return false;
                }
            }
            if( bank_code == '' && isNaN( account_no ) ) {
                if ( $( '#nn_sepa_hash' ).val() != '' ) {
                    show_mandate_overlay();
                    return false;
                }else {
                    process_sepa_hash_call();
                    return false;
                }
                return false;
            }

            if ( nn_vendor == '' || nn_auth_code == '' ) {
                alert( $( '#nn_lang_valid_merchant_credentials' ).val() );
                sepa_mandate_unconfirm_process();
                return false;
            }
            if ( bank_country == '' || account_holder == '' || account_no == '' || bank_code == '' || nn_vendor == '' || nn_auth_code == '' || nn_sepa_uniqueid == '' ) {
                alert( $( '#nn_lang_valid_account_details' ).val() );
                sepa_mandate_unconfirm_process();
                return false;
            }

            if ( !isNaN(account_no) && !isNaN( bank_code ) ){

                if ( $( '#nn_sepa_hash' ).val() != '' && $( '#novalnet_sepa_iban_span' ).html() != '' ) {
                    $( '#novalnet_sepa_mandate_confirm' ).attr( 'disabled', 'disabled' );
                    show_mandate_overlay();
                    return false;
                }

                var iban_bic_request = {
                    'account_holder': account_holder,
                    'bank_account': account_no,
                    'bank_code': bank_code,
                    'bank_country': bank_country,
                    'get_iban_bic': 1,
                    'unique_id': nn_sepa_uniqueid,
                    'vendor_authcode': nn_auth_code,
                    'vendor_id': nn_vendor
                };

                $( '#sepa_loader' ).show();

                novalnet_sepa_ajax( iban_bic_request, 'sepaibanbic' );
            } else {
                alert( $( '#nn_lang_valid_account_details' ).val() );
                sepa_mandate_unconfirm_process();
                return false;
            }
        }

        function show_mandate_overlay() {
            if(($( '#novalnet_sepa_bic' ).val() == '' && $( '#novalnet_sepa_bank_country' ).val() != 'DE' )|| $( '#novalnet_sepa_iban' ).val() == '' || $( '#novalnet_sepa_account_holder' ).val() == '' || $( '#nn_sepa_mandate_date' ).val() == '' || $( '#nn_sepa_mandate_ref' ).val() == '' ) {
                alert( $( '#nn_lang_valid_account_details' ).val() );
                return false;
            }

            $( '.bgCover' ).css({
                display:'block',
                width: $(document).width(),
                height: $(document).height()
            });
            $( '.bgCover' ).css({opacity:0}).animate( {opacity:0.5, backgroundColor:'#878787'} );

            var template_iban = '', template_bic = '';
            template_iban = isNaN( $( '#novalnet_sepa_iban' ).val() ) ? $( '#novalnet_sepa_iban' ).val() : $( '#nn_sepa_iban' ).val();
            template_bic = isNaN( $( '#novalnet_sepa_bic' ).val() ) ? $( '#novalnet_sepa_bic' ).val() : $( '#nn_sepa_bic' ).val();
            $( '#sepa_overlay_iban_span' ).html(template_iban);
            if(template_bic != '' ) {
                $( '#sepa_overlay_bic_span' ).html(template_bic);
                $( '#nn_sepa_overlay_bic_tr' ).show(60);
            } else {
                $( '#sepa_overlay_bic_span' ).html('');
                $( '#nn_sepa_overlay_bic_tr' ).hide(60);
            }
            $( '#sepa_overlay_payee_span' ).html( 'Novalnet AG' );
            $( '#sepa_overlay_creditoridentificationnumber_span' ).html('DE53ZZZ00000004253');
            $( '#sepa_overlay_mandatedate_span' ).html( normalizeDate( $( '#nn_sepa_mandate_date' ).val() ) );
            $( '#sepa_overlay_mandatereference_span' ).html($( '#nn_sepa_mandate_ref' ).val() );
            $( '.sepa_overlay_enduser_name_span' ).html($( '#novalnet_sepa_account_holder' ).val() );

            if ( $( '#novalnet_customer_info' ).length && $( '#novalnet_customer_info' ).val() != '' ) {
                var customer_info = $( '#novalnet_customer_info' ).val(),
                    customer_info_string = customer_info.split('|'),
                    data_length = customer_info_string.length,
                    array_result={};
                for ( var i = 0;i < data_length; i++ ) {
                    var hash_result_val = customer_info_string[i].split('=');
                    array_result[hash_result_val[0]] = hash_result_val[1];
                }

                var tmp_address = array_result.address;
                if ( array_result.address2 != '' )
                    tmp_address = array_result.address + ", " + array_result.address2 ;

                var email   = decodeURIComponent( array_result.email ),
                    address = decodeURIComponent( tmp_address ),
                    city    = decodeURIComponent( array_result.city ),
                    zip     = decodeURIComponent( array_result.zip ),
                    company = decodeURIComponent( array_result.company );

                if ( company == '' || company == null ) {
                    $( '#overlay_company' ).css( 'display', 'none' );
                } else {
                    $( '#sepa_overlay_enduser_company_span' ).html( company );
                }

                $( '#sepa_overlay_enduser_address_span' ).html( address );
                $( '#sepa_overlay_enduser_zip_span' ).html( zip );
                $( '.sepa_overlay_enduser_city_span' ).html( city );
                $( '#sepa_overlay_enduser_country_span' ).html( $( '#novalnet_sepa_bank_country option:selected' ).text() );
                $( '#sepa_overlay_enduser_email_span' ).html( email );
            } else {
                if($( '#billing_company' ).val() == '' || $( '#billing_company' ).val() == null){
                    $( '#overlay_company' ).css( 'display', 'none' );
                }else{
                    $( '#sepa_overlay_enduser_company_span' ).html($( '#billing_company' ).val());
                }

                if ( $( '#billing_address_1' ).length ) {
                    $( '#sepa_overlay_enduser_address_span' ).html( $( '#billing_address_1' ).val() );
                }

                if ( $( '#billing_postcode' ).length ) {
                    $( '#sepa_overlay_enduser_zip_span' ).html( $( '#billing_postcode' ).val() );
                }
                if ( $( '#billing_city' ).length ) {
                    $( '.sepa_overlay_enduser_city_span' ).html( $( '#billing_city' ).val() );
                }
                if ($( '#novalnet_sepa_bank_country' ).length ) {
                    $( '#sepa_overlay_enduser_country_span' ).html( $( '#novalnet_sepa_bank_country option:selected' ).text() );
                }
                if ( $( '#billing_email' ).length ) {
                    $( '#sepa_overlay_enduser_email_span' ).html( $( '#billing_email' ).val() );
                }
            }

            $( '#sepa_mandate_overlay_block_first' ).css({ display:'none', position:'fixed' });
            $( '#sepa_mandate_overlay_block' ).css({ display:'block'});
            get_sepa_responsivesize();
            document.onkeydown = function(event) {
                if(event == undefined) {
                    return false;
                }
                var charCode = (event.which) ? event.which : event.keyCode;
                if ((event.ctrlKey == true && charCode == 82)|| charCode == 116) {
                  return true;
                }
                return false;
            };
            return true;
        }

        function process_sepa_hash_call() {

            $( '#novalnet_sepa_mandate_confirm' ).attr( 'disabled', 'disabled' );

            var bank_country    = $( '#novalnet_sepa_bank_country' ).val(),
                account_holder  = $.trim( $( '#novalnet_sepa_account_holder' ).val() ),
                iban            = $( '#novalnet_sepa_iban' ).val(),
                bic             = $( '#novalnet_sepa_bic' ).val(),
                nn_vendor       = $( '#nn_vendor' ).val(),
                nn_auth_code    = $( '#nn_auth_code' ).val(),
                nn_sepa_uniqueid= $( '#nn_sepa_uniqueid' ).val();

            if ( $( '#nn_sepa_iban' ).length ) {
                var iban_span = $( '#nn_sepa_iban' ).val();
            }
            if ( $( '#nn_sepa_bic' ).length ) {
                var bic_span = $( '#nn_sepa_bic' ).val();
            }

            if (nn_vendor == '' || nn_auth_code == '' ) {
                alert( $( '#nn_lang_valid_merchant_credentials' ).val() );
                sepa_mandate_unconfirm_process();
                return false;
            }

            if ( bank_country == '' || account_holder == '' || iban == '' || nn_vendor == '' || nn_auth_code == '' || nn_sepa_uniqueid == '' ) {
                alert( $( '#nn_lang_valid_account_details' ).val() );
                sepa_mandate_unconfirm_process();
                return false;
            }

            if ( bank_country != 'DE' && bic == '' ) {
                alert( $( '#nn_lang_valid_account_details' ).val() );
                sepa_mandate_unconfirm_process();
                return false;
            }
            if ( bank_country == 'DE' && bic == '' && !isNaN( iban ) ) {
                alert( $( '#nn_lang_valid_account_details' ).val() );
                sepa_mandate_unconfirm_process();
                return false;
            }
            if ( bank_country == 'DE' && bic == '' && isNaN( iban ) ) {
                bic = '123456';
                $( '#novalnet_sepa_bic' ).val('');
                $( '#nn_sepa_bic' ).val('');
            }
            if ( !isNaN( iban ) && !isNaN( bic ) ) {
                var account_no = iban;
                var bank_code = bic;
                iban = bic = '';
            }
            if ( bic == '' && iban_span != '' && bic_span != '' ) {
                iban = iban_span;
                bic = bic_span;
            }
            var request_data_object = {
                'account_holder': account_holder,
                'bank_account': account_no,
                'bank_code': bank_code,
                'vendor_id': nn_vendor,
                'vendor_authcode': nn_auth_code,
                'bank_country': bank_country,
                'unique_id': nn_sepa_uniqueid,
                'sepa_data_approved': 1,
                'mandate_data_req': 1,
                'iban': iban,
                'bic': bic
            };
            novalnet_sepa_ajax( request_data_object, 'sepahash' );
        }

        function normalizeDate(input) {
            if(input != undefined && input != '' ) {
                var parts = input.split('-' );
                return (parts[2] < 10 ? '0' : '' ) + parseInt(parts[2]) + '.'
                    + (parts[1] < 10 ? '0' : '' ) + parseInt(parts[1]) + '.'
                    + parseInt(parts[0]);
            }
        }

        function sepa_mandate_unconfirm_process() {
            $( '#nn_sepa_hash' ).val('');
            $( '#nn_sepa_mandate_ref' ).val('');
            $( '#nn_sepa_mandate_date' ).val('');
            $( '#novalnet_sepa_iban_span' ).html('');
            $( '#novalnet_sepa_bic_span' ).html('');
            $( '#novalnet_sepa_mandate_confirm' ).attr("checked",false);
            $( '#novalnet_sepa_mandate_confirm' ).removeAttr('disabled' );
        }

        function novalnet_sepa_ajax( urlparam, code ) {
            var request_data = $.param( urlparam );
            var nnurl = $( location ).attr( 'protocol' ) + "//payport.novalnet.de/sepa_iban";
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
            $( '#sepa_loader' ).hide();

            if ( response.hash_result == 'success' ) {
                switch( code ){
                    case 'sepahash':
                        $( '#nn_sepa_hash' ).val( response.sepa_hash );
                        $( '#nn_sepa_mandate_ref' ).val( response.mandate_ref );
                        $( '#nn_sepa_mandate_date' ).val( response.mandate_date );
                        show_mandate_overlay();
                        return true;
                        break;
                    case 'sepaibanbic':
                        if ( response.IBAN != '' && response.BIC != '' ) {
                            $( '#nn_sepa_iban' ).val( response.IBAN );
                            $( '#nn_sepa_bic' ).val( response.BIC );
                            $( '#novalnet_sepa_iban_span' ).html( '<b>IBAN:</b> ' + response.IBAN );
                            $( '#nn_sepa_overlay_iban_tr' ).show(60);
                            $( '#novalnet_sepa_bic_span' ).html( '<b>BIC:</b> ' + response.BIC );
                            $( '#nn_sepa_overlay_bic_tr' ).show(60);
                            process_sepa_hash_call();
                            return true;
                        } else {
                            alert( $( '#nn_lang_valid_account_details' ).val() );
                            $( '#nn_sepa_overlay_iban_tr' ).hide(60);
                            $( '#nn_sepa_overlay_bic_tr' ).hide(60);
                            close_mandate_overlay();
                            return false;
                        }
                        break;
                    case 'separefill':
                        var hash_stringvalue = response.hash_string,
                            hash_string = hash_stringvalue.split('&'),
                            acc_hold = hash_stringvalue.match('account_holder=(.*)&bank_code'),
                            account_holder='',
                            array_result = {},
                            data_length = hash_string.length;

                        if ( acc_hold != null && acc_hold[1] != undefined ) account_holder = acc_hold[1];
                        for ( var i=0; i < data_length; i++ ) {
                            var hash_result_val = hash_string[i].split('=');
                            array_result[ hash_result_val[0] ] = hash_result_val[1];
                        }
                        try{
                         var holder = decodeURIComponent(escape(account_holder));
                        }catch(e) {
                         var holder = account_holder;
                        }
                        $( '#novalnet_sepa_account_holder' ).val( holder );
                        $( '#novalnet_sepa_bank_country' ).val( array_result.bank_country );
                        $( '#novalnet_sepa_iban' ).val( array_result.iban );
                        if ( array_result.bic != '123456' )
                            $( '#novalnet_sepa_bic' ).val( array_result.bic );
                        $('#nn_sepa_refill_complete').val(1);
                        break;
                }
                return false;
            } else {
                alert( response.hash_result );
                return false;
            }
        }
    } );

  } );
