jQuery(document).ready(function ($) {
    $('#fastly-media-regen-btn').on('click', function () {
        let $button = $(this);
        let $output = $('#fastly-media-regen-output');

        $button.prop('disabled', true).text('Regenerating...');
        $output.val('Starting media regeneration...\n');
      
        $.ajax({
            url: fastlyMediaRegen.ajax_url,
            type: 'POST',
            data: {
                action: 'fastly_media_regen_background',
                nonce: fastlyMediaRegen.nonce,
                selected_sites: $('#fastly-media-regen-sites').val(),
                batch_size: $('#image-batch-size').val() 
            },
            success: function (response) {
               
                if (response.success) {
                    checkOutput(); // Start polling for live output
                } else {
                    let errorMessage = 'Unknown error';
                    if (response && response.data && response.data.message) {
                        errorMessage = response.data.message;
                    }
                    $output.val('Error: ' + errorMessage);
                    $button.prop('disabled', false).text('Regenerate Media');
                }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                let errorMessage = 'Error: ' + textStatus + ' - ' + errorThrown;
                if (jqXHR.responseText) {
                    errorMessage += '\nResponse: ' + jqXHR.responseText;
                }
                $output.val(errorMessage);
                $button.prop('disabled', false).text('Regenerate Media');
            }
        });
    });

    function checkOutput() {
        $.ajax({
            url: fastlyMediaRegen.ajax_url,
            type: 'POST',
            data: { 
                action: 'fastly_media_regen_output',
                selected_sites: $('#fastly-media-regen-sites').val(),
                batch_size: $('#image-batch-size').val() 
            },
            success: function (response) {
                if (response.success) {
                    let output = response.data.output;
                    $('#fastly-media-regen-output').val(output);
                      if (output.includes('All batches completed!') || output.includes('Finished processing')) {
                        $('#fastly-media-regen-btn').prop('disabled', false).text('Regenerate Media');
                        // Delete the transient.
                        return;
                    }
                }
                setTimeout(checkOutput, 3000); // Poll every 3 seconds
            },
            error: function (jqXHR, textStatus, errorThrown) {
                let errorMessage = 'Error fetching output: ' + textStatus + ' - ' + errorThrown;
                if (jqXHR.responseText) {
                    errorMessage += '\nResponse: ' + jqXHR.responseText;
                }
                $('#fastly-media-regen-output').val(errorMessage);
                $('#fastly-media-regen-btn').prop('disabled', false).text('Regenerate Media');
            }
        });
    }
});
