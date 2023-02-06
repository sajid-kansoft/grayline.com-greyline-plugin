jQuery(document).ready(function ($) {
        jQuery("#synch_tour").click(function() {
        alert("Syncing tours.")
        //jQuery("#synch_tour").prop("disabled",true);
        //jQuery.post(wp_grayline_plugin_sync_tourjs.ajax, { action: 'tour_sync' }, function(res) {
        
            var url = jQuery("#sync_tour_url").val();

            jQuery.ajax({
                type: "GET",
                url: url,
                async: true,
            }).success(function (data) {
                
            }).error(function (request, status, error) {
                alert('Something went wrong. Please try again.');
            });  
        });
});
        


