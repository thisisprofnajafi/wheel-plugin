(function($) {
    'use strict';

    // Initialize when document is ready
    $(document).ready(function() {
        // Add any admin-specific functionality here
        initializeAdminSettings();
    });

    function initializeAdminSettings() {
        // Example: Toggle settings sections
        $('.wheel-manager-settings .section-toggle').on('click', function(e) {
            e.preventDefault();
            $(this).next('.section-content').slideToggle();
        });

        // Example: Save settings via AJAX
        $('#wheel-manager-settings-form').on('submit', function(e) {
            e.preventDefault();
            
            var $form = $(this);
            var $submitButton = $form.find('input[type="submit"]');
            
            $submitButton.prop('disabled', true);
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: $form.serialize(),
                success: function(response) {
                    if (response.success) {
                        // Show success message
                        showNotice('success', 'Settings saved successfully.');
                    } else {
                        // Show error message
                        showNotice('error', 'Failed to save settings.');
                    }
                },
                error: function() {
                    showNotice('error', 'An error occurred while saving settings.');
                },
                complete: function() {
                    $submitButton.prop('disabled', false);
                }
            });
        });
    }

    function showNotice(type, message) {
        var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
        $('.wheel-manager-settings').prepend($notice);
        
        // Auto dismiss after 3 seconds
        setTimeout(function() {
            $notice.fadeOut(function() {
                $(this).remove();
            });
        }, 3000);
    }

})(jQuery); 