jQuery(document).ready(function ($) {
   jQuery("#clear_cache").click(function() {
    var url = $("#clear_cache_url").val();
    
    jQuery.ajax({
        type: "GET",
        url: url,
        async: true,
    }).success(function (data) {
        //if(data === true) {
                alert('Cache cleared.');
        /*} else {
                alert('Something went wrong. Please try again.');
        }*/
    }).error(function (request, status, error) {
        alert('Something went wrong. Please try again.');
    });

   });      
});

jQuery("input[name='grayline_tourcms_wp_mail_send_method']").change(function() {
    
    if(jQuery(this).val() == "SMTP") {
        jQuery("#smtp_setting_section").show();
    }
    else {
        jQuery("#smtp_setting_section").hide();
    }
}); 

jQuery("#remove_sublogo").click(function() {
    var url = jQuery("#upload_sublogo_url").val();

               
    jQuery("#img_sublogo").prop('src', '');
    jQuery("#img_sublogo").hide();
    jQuery(this).hide();

    jQuery.ajax({
        type: "GET",
        url: url,
        processData: false,
        contentType: false
    }).success(function (data) {
        
    }).error(function (request, status, error) {
        alert('Something went wrong. Please try again.');
    });
});

function add_image(obj) { 
    var parent=jQuery(obj).parent().parent('div.field_row');
    var inputField = jQuery(parent).find("input.meta_image_url");
    var inputFieldID = jQuery(parent).find("input.meta_image_id");
    var fileFrame = wp.media.frames.file_frame = wp.media({
        multiple: false
    });
    fileFrame.on('select', function() {
        var selection = fileFrame.state().get('selection').first().toJSON();
        inputField.val(selection.url);
        inputFieldID.val(selection.id);
        jQuery(parent)
        .find("div.image_wrap")
        .html('<img src="'+selection.url+'" height="90" width="90" />');
    });
    fileFrame.open(); 
}

function remove_field(obj) {
    var parent=jQuery(obj).parent().parent();
    //console.log(parent)
    parent.remove();
}

function add_field_row() { 
    var row = jQuery('#master-row').html();
    jQuery(row).appendTo('#field_wrap');
}

// Video
function add_video(obj) { 
    var parent=jQuery(obj).parent().parent('div.field_row2');
    var inputField = jQuery(parent).find("input.meta_video_url");
    var fileFrame = wp.media.frames.file_frame = wp.media({
        multiple: false
    });
    fileFrame.on('select', function() {
        var selection = fileFrame.state().get('selection').first().toJSON();
        inputField.val(selection.url);
    });
    fileFrame.open(); 
}

function remove_video(obj) {
    var parent=jQuery(obj).parent().parent();
    //console.log(parent)
    parent.remove();
}

function add_video_field_row() { 
    var row = jQuery('#master-row-video').html();
    jQuery(row).appendTo('#field_wrap2');
}