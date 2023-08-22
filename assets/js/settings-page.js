(function($) {

    var ajax_url = backend_vars.ajax_url;

    $('body').on('click','#update_gainrate',function(e){
        e.preventDefault();
        let data = {
            action: 'update_gainrate',
        };
        $.ajax({
            url: ajax_url,
            data: data,
            method: 'POST',
            success: function(response) {
                console.log('success');
            },
            error: function(response) {
                console.log('error');
            }
        });
    });

    $('body').on('click','#test',function(e){
        e.preventDefault();
        let data = {
            action: 'test_configuration',
        };
        $.ajax({
            url: ajax_url,
            data: data,
            method: 'POST',
            success: function(response) {
                console.log('success');
                console.log(response);
            },
            error: function(response, ar2, ar3) {
                console.log('error');
                console.log(ar2);
                console.log(ar3);
            }
        });
    });

})( jQuery );

