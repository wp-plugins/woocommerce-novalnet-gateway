var novalnet_cc3d_enabled = document.getElementById('woocommerce_novalnet_cc_cc3d_enabled');

function display_accesskey(status, color) {
	document.getElementById('woocommerce_novalnet_cc_key_password').disabled = status;
    document.getElementById('woocommerce_novalnet_cc_key_password').style.backgroundColor = color;
	
}

if(novalnet_cc3d_enabled) {
    window.onload = function() {
        if(novalnet_cc3d_enabled.checked == true){
			display_accesskey(false, '#fff');
            
        } else {
			display_accesskey(true, '#f5f5f5');           
        }
    }
    novalnet_cc3d_enabled.onchange = function() {
        if(novalnet_cc3d_enabled.checked == false) {
			display_accesskey(true, '#f5f5f5');

        } else {
			display_accesskey(false, '#fff');
        }
    }
}