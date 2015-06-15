/**
 * @category   Novalnet
 * @package    Novalnet
 * @copyright  Novalnet (http://www.novalnet.de)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *
 */

 jQuery( function( $ ) {
	$( document ).ajaxComplete(function( event, xhr, settings ) {
		$('#nn_ref_type').val('none');
		$("#txn_status").val('');
		$('#nn_ref_type').on('change', function() {
			if ( $('#nn_ref_type').val() == 'sepa' ) {
				$("#sepa_iban").val('');
				$("#sepa_bic").val('');
				$('#nn_ref_sepa_form').css('display', 'block');
			} else {
				$('#nn_ref_sepa_form').css('display', 'none');
			}
		} );
	} );

	function process_admin_ajax( url, data ) {
		if ('XDomainRequest' in window && window.XDomainRequest !== null) {
			var xdr = new XDomainRequest();
			xdr.open('POST' , url);
			xdr.onload = function () {
				location.reload();
			};
			xdr.send( data );
		}else{
			$.ajax({
				type : 'POST',
				url: url,
				data: data,
				success: function(data) {
					location.reload();
				}
			});
		}
		return true;
	}

	$('#nn_manage_transaction').on( 'click' ,function() {
		var confirm_status = $("#txn_status").val();
		if ( confirm_status == 100 || confirm_status == 103 ) {
			$("#novalnet_loading_div").css('display','inline');
			process_admin_ajax( $("#nn_manage_txn_url").val(), 'confirm_status='+confirm_status );
			return true;
		}else{
			alert( $("#nn_manage_txn_err").val() );
			return false;
		}
	} );

	$('#nn_amount_change').on( 'click' ,function() {
		var txn_amount	= parseInt($("#nn_txn_amount").val());

		if ( $("#nn_due_date").length )
			var invoice_due_date  = $("#nn_due_date").val();

		var date_regex = /^\d{4}[\-](0?[1-9]|1[012])[\-](0?[1-9]|[12][0-9]|3[01])$/ ;

		if ( invoice_due_date != undefined ) {
			current_date = $( '#nn_current_date' ).val();

			if ( !date_regex.test( invoice_due_date ) ) {
				alert( $("#nn_due_date_err").val() ); return false;
			}

			if( new Date(invoice_due_date).getTime() < new Date(current_date).getTime() ) {
				alert( $("#nn_past_date_err").val() ); return false;
			}
		}

		if ( isNaN(txn_amount) || txn_amount <= 0 ) {
			alert( $("#nn_amt_change_err").val() ); return false;
		} else {
			if ( !window.confirm($("#nn_amt_upd_succ_msg").val()) ){return false;}
			var post_data = '&nn_upd_amount=' + txn_amount;
			if ( invoice_due_date != undefined )
				post_data = post_data + '&invoice_due_date=' + invoice_due_date;
			$("#novalnet_loading_div").css('display','inline');
			process_admin_ajax( $("#nn_amt_change_url").val(), post_data );
			return true;
		}
	} );

	$('#nn_refund').on('click' ,function() {
		var refund_amount = parseInt($("#nn_refund_amount").val()),
			paid_amt = $("#nn_paid_amt").val();

		if ( $("#nn_refund_ref").length )
			var refund_ref = $("#nn_refund_ref").val();

		if ( $("#nn_ref_type").length )
			var ref_type = $("#nn_ref_type").val();

		if ( isNaN(refund_amount) || refund_amount <= 0 ) {
			alert(  $("#nn_invalid_err").val() ) ;
			return false;
		} else if ( refund_amount > paid_amt ) {
			alert( $("#nn_exceeds_err").val() );
			return false;
		} else if ( ref_type == 'sepa' ) {
			var sepa_holder = $("#sepa_acc_holder").val(),
				iban 		= $("#sepa_iban").val(),
				bic 		= $("#sepa_bic").val();

			if ( sepa_holder == '' || iban == '' || bic == '' ) {
				alert( $("#nn_sepa_err").val() ); return false;
			}
		}

		var post_data = {
			'nn_refund_amount': refund_amount
		};
		if ( refund_ref != undefined && refund_ref != '')
			$.extend( post_data, { 'refund_ref': refund_ref } );
		if ( ref_type == 'sepa') {
			$.extend( post_data, { 'ref_type': ref_type, 'sepa_holder': sepa_holder, 'sepa_iban': iban, 'sepa_bic': bic  } );
		}

		$("#novalnet_loading_div").css('display','inline');
		process_admin_ajax( $("#nn_ref_url").val(), post_data );
		return true;
	} );
	$( "#nn_refund_amount").keypress( function( event ) {
		var keycode = ( 'which' in event ) ? event.which : event.keyCode;
		var reg = /^(?:[0-9]+$)/ ;
		return ( reg.test( String.fromCharCode( keycode ) ) || keycode == 0 || keycode == 8 );
	} );

	$( "#nn_txn_amount" ).keypress( function( event ) {
		var keycode = ( 'which' in event ) ? event.which : event.keyCode;
		var reg = /^(?:[0-9]+$)/ ;
		return ( reg.test( String.fromCharCode( keycode ) ) || keycode == 0 || keycode == 8 );
	} );
 } );
