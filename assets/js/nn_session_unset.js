var validNavigation = false;
 
function endSession() {
	jQuery.ajax({
		url        : './wp-content/plugins/woocommerce-novalnet-gateway/nn_unset.php',
		type   		: 'post',
		dataType   : 'html',
		data       : 'clr_session=1',
		global     :  false,
		async      :  false,
		success    :  function (result){
		}
	});
}
 
function wireUpEvents() {
	window.onbeforeunload = function() {
    		if (!validNavigation) {
        		endSession();
      		}
  	}
 
  	jQuery("a,input[type=submit]").bind("click", function() {
    	validNavigation = true;
  	});
 
	jQuery("form").bind("submit", function() {
    	validNavigation = true;
  	});
}
 
jQuery(document).ready(function() {
  wireUpEvents(); 
});