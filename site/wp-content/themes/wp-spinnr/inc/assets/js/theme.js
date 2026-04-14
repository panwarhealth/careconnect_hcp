var spinnrSetAsDefault = function(postName, postType) {
    jQuery.ajax({
        method:'POST',
        url: ajaxurl,
        data: {
            'action' : 'wp_spinnr_set_as_default',
            'postname' : postName,
            'posttype' : postType,
        },
        success: function(){
            window.location.reload(true);
        }
    });
};