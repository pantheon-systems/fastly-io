jQuery(document).ready(function ($) {
    $('#fastly-media-regen-btn').on('click', function () {
        let $button = $(this);
        let $output = $('#fastly-media-regen-output');
        
        $button.prop('disabled', true).text('Regenerating...');
        $output.val('Running media regeneration...\n');
        
        $.ajax({
            url: fastlyMediaRegen.ajax_url,
            type: 'POST',
            data: {
                action: 'fastly_media_regen',
                nonce: fastlyMediaRegen.nonce,
                selected_sites: $('#fastly-media-regen-sites').val()
            },
            success: function (response) {
                if (response.success) {
                    $output.val(response.data.output);
                } else {
                    $output.val('Error: ' + response.data);
                }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                $output.val('An error occurred: ' + textStatus + ' - ' + errorThrown + '\nResponse: ' + jqXHR.responseText);
            },
            complete: function () {
                $button.prop('disabled', false).text('Regenerate Media');
            }
        });
    });
});
