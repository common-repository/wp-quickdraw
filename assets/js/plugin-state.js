window.hifipixBatchImporter = {
    needsRegenerate: false
};

jQuery(document).ready( function($) {
    // Update W3TC CDN "Custom File List" Setting
    $('body').on('click', '#wpqd_custom_file_cdn', function (e) {
        e.preventDefault();

        var $container = $(this).parent().parent(),
            $responseArea = $('#wpqd_custom_file_cdn_feedback', $container);

        $container.append( '<div class="wpqd-spinner"></div>' ); // AJAX loader
        $responseArea.empty(); // Clear existing feedback

        $.ajax({
            url: ajaxurl,
            data: {
                'action': 'wpqd_custom_file_cdn_update'
            },
            success: function(data) {
                var response = JSON.parse(data);

                if ( response === true ) {
                    $container.addClass('notice-success').removeClass('notice-warning');
                    $responseArea.append( '<p><strong>Your W3TC settings were updated successfully!</strong></p>' );    
                } else {
                    $container.addClass('notice-error').removeClass('notice-warning');
                    $responseArea.append( '<p><strong>There was a problem updating your W3TC settings. If the issue persists, please update your settings manually.</strong></p>' );
                }
                $('.wpqd-spinner', $container).remove(); // Remove AJAX loader
            }
        });
    });

    // Run compatibility check in Tools > Compatibility Check (settings page)
    $('body').on('click', '#wpqd_data-compatibility_check .button, #wpqd_compatibility_check', function (e) {
        e.preventDefault();

        var $fieldset = $('#wpqd_data-compatibility_check, #wpqd-warnings'),
            $responseArea = $('<div class="wpqd-compatibility-check-response"></div>');

        $fieldset.append( '<div class="wpqd-spinner"></div>' ); // AJAX loader

        $.ajax({
            url: ajaxurl,
            data: {
                'action': 'wpqd_compatibility_check_ajax'
            },
            success: function(data) {
                $('#wpqd-compatibility-check-errors').empty();
                $('.wpqd-compatibility-check-response').remove(); // Clear existing feedback
                $fieldset.append($responseArea);
                $('.wpqd-spinner', $fieldset).remove(); // Remove AJAX loader

                var $responseSelector = $('.wpqd-compatibility-check-response'),
                    errors = JSON.parse(data);

                if ( $('#wpqd-compatibility-check-errors').length > 0 ) {
                    var $list = $('#wpqd-compatibility-check-errors');

                    if ( data === '1') { // Compatibility check passed
                        $('#wpqd-warnings')
                            .removeClass('notice-error hide-dismiss')
                            .addClass('notice-success');
                        $list.append( '<li>Passed compatibility check</li>' );
                        return;
                    }
                    
                    $.each(errors, function(k, v) {
                        $list.append( '<li>' + v + '</li>' );
                    });
                    return;
                }

                if ( data === '1') { // Compatibility check passed
                    $responseSelector
                        .addClass('notice notice-success')
                        .html( '<p><strong>Passed compatibility check</strong></p>' );
                    return;
                }

                $responseSelector
                    .addClass('notice notice-error')
                    .html( '<p><strong>Compatibility check failed</strong></p>' )
                    .append( '<ul class="errors"></ul>' );

                $.each(errors, function(k, v) {
                    $('.errors', $responseSelector).append('<li>' + v + '</li>');
                });
            }
        });
    });

    // Maybe generate new API key on button click
    $('body').on('click', '#wpqd_data-generate_api_key .button, #wpqd_regen_api_key', function (e) {
        e.preventDefault();

        var $fieldset = $('#wpqd_data-generate_api_key, #wpqd-warnings-api'),
            $input = $('#wpqd_data-api_key input, #wpqd_regen_api_key'),
            $responseArea = $('<div class="api_key-response"></div>');

        $fieldset.append( '<div class="wpqd-spinner"></div>' ); // AJAX loader
        $input.attr('disabled', true);

        $.ajax({
            url: ajaxurl,
            data: {
                'action': 'wpqd_create_api_key'
            },
            success: function(data) {
                if ( $('.test_api_key-response', $fieldset).length > 0 ) {
                    $('.test_api_key-response', $fieldset).remove();
                }
                $('.api_key-response', $fieldset).remove(); // Clear existing feedback
                $('.wpqd-spinner', $fieldset).remove(); // Remove AJAX loader
                $fieldset.append($responseArea);
                $input.attr('disabled', false);

                var response = JSON.parse(data),
                    currentVal = $input.val(),
                    $responseSelector = $('.api_key-response', $fieldset);

                if ( typeof response.key !== 'undefined' ) {
                    if ( response.key == currentVal ) {
                        $responseSelector
                            .addClass('notice notice-warning')
                            .html( '<p><strong>No need to update API key as the site URL has not changed.</strong</p>' );
                    } else {
                        $input.val( response.key );
                        $responseSelector
                            .addClass('notice notice-success')
                            .html( '<p><strong>Successfully updated API key!</strong</p>' );
                    }
                } else if ( typeof response.error !== 'undefined' ) {
                    $responseSelector
                        .addClass('notice notice-error')
                        .html( '<p><strong>Error: ' + response.error + '</strong></p>' );
                }
            }
        });
    });

    // Test API authentication on button click
    $('body').on('click', '#wpqd_data-test_api_key .button, #wpqd_test_api_key', function (e) {
        e.preventDefault();

        var $fieldset = $('#wpqd_data-test_api_key, #wpqd-warnings-api'),
            $input = $('#wpqd_data-test_api_key input, #wpqd_test_api_key'),
            $responseArea = $('<div class="test_api_key-response"></div>');
        $fieldset.append( '<div class="wpqd-spinner"></div>' ); // AJAX loader
        $input.attr('disabled', true);

        $.ajax({
            url: ajaxurl,
            data: {
                'action': 'wpqd_test_api_key'
            },
            success: function(data) {
                if ( $('.api_key-response', $fieldset).length > 0 ) {
                    $('.api_key-response', $fieldset).remove();
                }
                $('.test_api_key-response').remove(); // Clear existing feedback
                $('.wpqd-spinner', $fieldset).remove(); // Remove AJAX loader
                $fieldset.append($responseArea);
                $input.attr('disabled', false);

                var response = JSON.parse(data),
                    $responseSelector = $('.test_api_key-response');

                if ( ! response.error && typeof response.message !== 'undefined' ) {
                    $responseSelector
                        .addClass('notice notice-success')
                        .html( '<p><strong>' + response.message + '</strong</p>' );
                } else {
                    $responseSelector
                        .addClass('notice notice-error')
                        .html( '<p><strong>Error: ' + response.message + '</strong></p>' );
                }

            }
        });
    });

    // Dismiss compatibility check errors on "X" icon click
    $('body').on('click', '#wpqd-warnings .notice-dismiss', function (e) {
        $.ajax({
            url: ajaxurl,
            data: {
                'action': 'wpqd_dismiss_admin_error'
            }
        });
    });

    // Dismiss filesystem errors on "X" icon click
    $('body').on('click', '#wpqd-warnings-filesystem .notice-dismiss', function (e) {
        $.ajax({
            url: ajaxurl,
            data: {
                'action': 'wpqd_dismiss_admin_error_filesystem'
            }
        });
    });

    // Dismiss image generation success message on "X" icon click
    $('body').on('click', '#wpqd-import-success .notice-dismiss', function (e) {
        window.hifipixBatchImporter.dismissAdminProgress();
    });

    // Prompt imageset regeneration confirmation when clicking button
    $('body').on('click', '#wpqd_data-regenerate_images .button', function (e) {
        e.preventDefault();
        window.hifipixBatchImporter.displayConfirmation( window.hifipixBatchImporter.regenerateImages );
    });

    // Prompt imageset regeneration confirmation when clicking button in plugin update notice
    $('body').on('click', '#wpqd-regenerate-images', function (e) {
        e.preventDefault();
        var $responseArea = $('#wpqd-regenerate-images-feedback');

        $responseArea.hide();
        $(this).parent().append( '<div class="wpqd-spinner"></div>' ); // AJAX loader

        $.ajax({
            url: ajaxurl,
            data: {
                'action': 'wpqd_reimport_all_ajax'
            },
            success: function(data) {
                $responseArea.empty(); // Clear existing feedback
                $('.wpqd-spinner', $(this).parent()).remove(); // Remove AJAX loader

                var result = JSON.parse(data);
                if ( result ) { // Images were regenerated
                    $(this).parent().parent().removeClass('notice-info').addClass('notice-success');
                    $responseArea
                        .html( '<strong>' + result.images + ' images were scheduled for regeneration.</strong>' )
                        .show();
                    window.location.reload(true);
                    return;
                } else {
                    $(this).parent().parent().removeClass('notice-info').addClass('notice-warning');
                    $responseArea
                        .html( '<strong>There were no images to regenerate.</strong>' )
                        .show();
                }
            }
        });
    });

    // Clear all image process queues
    $('body').on('click', '#wpqd_data-reset_image_queue .button', function (e) {
        e.preventDefault();
        window.hifipixBatchImporter.resetQueue();
    });

    // Pause or resume current image generation process
    $('body').on('click', '.wpqd-progress-pauser', function (e) {
        e.preventDefault();
        window.hifipixBatchImporter.pauseResumeImport( $(this) );
    });

    // Detect settings changes that require imageset regeneration
    $('body').on('change', 'select[name="wpqd_data[crop_trim]"]', function () {
        window.hifipixBatchImporter.setNeedsRegenerate( true );
    });

    // Trigger dialog box prompt, if imageset regeneration is required
    $('body').on('click', '#wpqd-save-overlay', function (e) {
        if ( ! window.hifipixBatchImporter.needsRegenerate ) {
            $('.wpqd-settings input[name="redux_save"]').trigger('click');
            return;
        }

        e.preventDefault();
        var callback = function() {
            $('.wpqd-settings input[name="redux_save"]').trigger('click');
            window.hifipixBatchImporter.setNeedsRegenerate( false );
        };

        window.hifipixBatchImporter.displayConfirmation( callback );
    });

    // Delete the progress bar transient at top of admin page
    window.hifipixBatchImporter.dismissAdminProgress = function() {
        $.ajax({
            url: ajaxurl,
            data: {
                'action': 'wpqd_dismiss_admin_import_success'
            },
            success: function(data) {
                if ( data !== '1' ) {
                    return;
                }
                $('#wpqd-import-success').hide();
            }
        });
    };

    // Add or remove overlay div which triggers dialog box prompt
    window.hifipixBatchImporter.setNeedsRegenerate = function ( regenerate ) {
        if ( regenerate === true ) {
            this.needsRegenerate = true;
            $('.wpqd-settings .redux-action_bar').append( '<div id="wpqd-save-overlay"></div>' );
        } else {
            this.needsRegenerate = false;
            $('#wpqd-save-overlay').remove();
        }
    };

    // Show imageset regeneration confirmation dialog box
    window.hifipixBatchImporter.displayConfirmation = function( yesCallback ) {
        new $.Zebra_Dialog('Your settings have changed and by continuing we will initiate regeneration of your imagesets. Would you like to continue?', {
            'type':     'warning',
            'title':    'Regenerate Imagesets',
            'buttons':  [
                            {caption: 'Yes', callback: function() { yesCallback() }},
                            {caption: 'No', callback: function() {}} // Do nothing
                        ]
        });
    };

    // Force regenerate all images in Tools > Regenerate Images (settings page)
    window.hifipixBatchImporter.regenerateImages = function() {
        var $fieldset = $('#wpqd_data-regenerate_images'),
            $responseArea = $('<div id="regenerate_images-response"></div>');

        $fieldset.append( '<div class="wpqd-spinner"></div>' ); // AJAX loader

        $.ajax({
            url: ajaxurl,
            data: {
                'action': 'wpqd_reimport_all_ajax'
            },
            success: function(data) {
                $('#regenerate_images-response').remove(); // Clear existing feedback
                $fieldset.append($responseArea);
                $('.wpqd-spinner', $fieldset).remove(); // Remove AJAX loader

                var $responseSelector = $('#regenerate_images-response'),
                    result = JSON.parse(data);

                if ( result ) { // Images were regenerated
                    $responseSelector
                        .addClass( 'notice notice-success' )
                        .html( '<p><strong>' + result.images + ' WPQD-enabled images were scheduled for regeneration.</strong></p>' );
                    window.location.reload(true);
                    return;
                } else {
                    $responseSelector
                        .addClass( 'notice notice-warning' )
                        .html( '<p><strong>There were no WPQD-enabled images to regenerate</strong></p>' );
                }
            }
        });
    };

    // Check image import progress and display status in admin notice bar
    window.hifipixBatchImporter.checkProgress = function() {
        $.ajax({
            url: ajaxurl,
            data: {
                'action': 'wpqd_check_progress'
            },
            success: function(data) {
                var result = JSON.parse(data),
                    $container = $('#wpqd-import-success'),
                    $message = $('#wpqd-progress-message'),
                    $downloadFile = $('.wpqd-current-status .download'),
                    $currentProgress = $('.current', $message),
                    $spinner = $('#wpqd-progress-message .wpqd-spinner-inline');

                var statusMsg = '';
                if ( result.quota_exceeded && result.download_complete ) {
                    statusMsg = 'Monthly quota exceeded: some images were not processed.<br />Need more processing? <a href="https://www.wpquickdraw.com/plugins/wp-quickdraw-pro/" target="_blank">Get WP QuickDraw Pro</a>!';
                } else if ( result.download_complete ) {
                    statusMsg = 'Image set generation complete!';
                } else if ( result.download == 0 || ! result.download || result.download.length < 1 ) {
                    statusMsg = 'Queueing up images';
                } else {
                    statusMsg = result.download;
                }
                
                $currentProgress.html( result.countdown );
                $downloadFile.html( statusMsg );
                window.hifipixBatchImporter.updateAdminCharts();

                if ( result.hasOwnProperty('paused') ) {
                    if ( ! result.paused ) {
                        $spinner.show();
                    } else {
                        $spinner.hide();
                        return;
                    }
                }

                if ( ! result.download_complete ) {
                    // Keep checking the progress until the import job is finished
                    setTimeout( 
                        function() { window.hifipixBatchImporter.checkProgress() },
                        3000
                    );
                } else {
                    if ( result.quota_exceeded ) {
                        $container.addClass('notice-error').removeClass('notice-warning');
                    } else {
                        $container.addClass('notice-success').removeClass('notice-warning');
                    }

                    $container.removeClass('hide-dismiss');
                    $spinner.hide();
                    window.hifipixBatchImporter.dismissAdminProgress();
                }
            }
        });
    };

    // Update WPQD Dashboard charts
    window.hifipixBatchImporter.updateAdminCharts = function() {
        $.ajax({
            url: ajaxurl,
            data: {
                'action': 'wpqd_get_status_data'
            },
            success: function(data) {
                var result = JSON.parse(data),
                    $totalMessage = $('#wpqd-queue-totals'),
                    $currentProgress = $('.current', $totalMessage),
                    $totalCount = $('.total', $totalMessage),
                    $bandwidth = $('.wpqd-general-info .wpqd-setting.bandwidth');

                $currentProgress.html( result.totals.generated );
                $totalCount.html( result.totals.total );

                if ( $bandwidth.length > 0 ) {
                    $bandwidth.html( result.quota_percent );
                }
                
                if ( window.hifipixBatchImporter.chartPng ) {
                    var pngPoint = window.hifipixBatchImporter.chartPng.series[0].points[0],
                        pngPercent = result['image/png'].percent;
                    pngPoint.update( pngPercent );
                }

                if ( window.hifipixBatchImporter.chartJpg ) {
                    var jpgPoint = window.hifipixBatchImporter.chartJpg.series[0].points[0],
                        jpgPercent = result['image/jpeg'].percent;
                    jpgPoint.update( jpgPercent );
                }

                if ( window.hifipixBatchImporter.chartGif ) {
                    var gifPoint = window.hifipixBatchImporter.chartGif.series[0].points[0],
                        gifPercent = result['image/gif'].percent;
                    gifPoint.update( gifPercent );
                }

                if ( window.hifipixBatchImporter.progressBar ) {
                    var progressPoint = window.hifipixBatchImporter.progressBar.series[1].points[0],
                        progressPercent = result.totals.percent;
                    progressPoint.update( progressPercent );
                }
            }
        });
    };

    // Pause or resume the current image process, update admin notice bar
    window.hifipixBatchImporter.pauseResumeImport = function( $this ) {
        var $container = $this.closest('div'),
            $button = $('.wpqd-progress-pauser');

        $container.append( '<div class="wpqd-spinner"></div>' ); // AJAX loader

        $.ajax({
            url: ajaxurl,
            data: {
                'action': 'wpqd_pause_resume_import'
            },
            success: function(data) {
                var result = JSON.parse(data);
                $('.wpqd-spinner', $container).remove();
                
                if ( ! result.hasOwnProperty('paused') ) {
                    return;
                }

                if ( ! result.paused ) {
                    $button.html( 'Pause Import' );
                    window.hifipixBatchImporter.checkProgress();
                } else {
                    $button.html( 'Resume Import' );
                }
            }
        });
    };

    // Clear all currently scheduled image process queues
    window.hifipixBatchImporter.resetQueue = function() {
        var $fieldset = $('#wpqd_data-reset_image_queue'),
            $responseArea = $('<div id="reset_image_queue-response"></div>'),
            $progressDismiss = $('#wpqd-import-success .notice-dismiss');

        $fieldset.append( '<div class="wpqd-spinner"></div>' ); // AJAX loader

        if ( $progressDismiss.length > 0 ) {
            $progressDismiss.trigger( 'click' );
        }

        $.ajax({
            url: ajaxurl,
            data: {
                'action': 'wpqd_reset_queue'
            },
            success: function(data) {
                $( '#reset_image_queue-response' ).remove(); // Clear existing feedback
                $fieldset.append( $responseArea );
                $( '.wpqd-spinner', $fieldset ).remove(); // Remove AJAX loader

                var $responseSelector = $('#reset_image_queue-response'),
                    result = JSON.parse(data);

                if ( result > 0 ) { // Queues were cleared
                    $responseSelector
                        .addClass( 'notice notice-success' )
                        .html( '<p><strong>The image process queue has been reset.</strong></p>' );
                    return;
                } else {
                    $responseSelector
                        .addClass( 'notice notice-warning' )
                        .html( '<p><strong>Nothing happened because the image process queue was empty.</strong></p>' );
                }
            }
        });
    };
});
