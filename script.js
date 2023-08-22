(function($) {

    var ajax_url = backend_vars.ajax_url;

// Apply redeem
jQuery('body').on('click','#redeem-button',function(e){
    e.preventDefault();
    let redeemValue = jQuery('#redeem_input').val();
    redeemValue = parseFloat(redeemValue.replace(/[^0-9.]/g, ''));
    console.log(redeemValue);
    if(redeemValue > 0) {
        let data = {
            action: 'loyale_apply_redeem',
            redeem_value: redeemValue
        };
        jQuery.ajax({
            url: ajax_url,
            data: data,
            method: 'POST',
            success: function(response) {
                let redeemData = JSON.parse(response);
                if(redeemData['is_applied']) {
                    jQuery('body').trigger('update_checkout', { update_shipping_method: true });
                    console.log(redeemData);
                }
            },
            error: function(response) {
            }
        });
    }
});

})( jQuery );