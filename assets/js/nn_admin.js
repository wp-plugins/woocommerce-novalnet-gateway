var novalnet_cc3d_enabled = document.getElementById('woocommerce_novalnet_cc_cc3d_enabled');
if(novalnet_cc3d_enabled){
	window.onload = function(){
		if(novalnet_cc3d_enabled.checked == true){
			document.getElementById('woocommerce_novalnet_cc_key_password').disabled = false;
			document.getElementById('woocommerce_novalnet_cc_key_password').style.backgroundColor = '#fff';
		}else{
			document.getElementById('woocommerce_novalnet_cc_key_password').disabled = true;
			document.getElementById('woocommerce_novalnet_cc_key_password').style.backgroundColor = '#f5f5f5';
		}
	}

	novalnet_cc3d_enabled.onchange = function() {

		if(novalnet_cc3d_enabled.checked == false){
			document.getElementById('woocommerce_novalnet_cc_key_password').disabled = true;
			document.getElementById('woocommerce_novalnet_cc_key_password').style.backgroundColor = '#f5f5f5';

		} else {
			document.getElementById('woocommerce_novalnet_cc_key_password').disabled = false;
			document.getElementById('woocommerce_novalnet_cc_key_password').style.backgroundColor = '#fff';
		}
	}
}