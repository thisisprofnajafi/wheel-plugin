(function($) {
    'use strict';

    // Initialize when document is ready
    $(document).ready(function() {
        // Listen for wheel spin completion event from WP Optin Wheel
        $(document).on('wp_optin_wheel_spin_complete', function(event, data) {
            if (data && data.prize) {
                awardPoints(data.prize);
            }
        });
    });

    // Function to award points via AJAX
    function awardPoints(prizeData) {
        if (!prizeData.points) {
            return;
        }

        $.ajax({
            url: wheelManagerBME.ajax_url,
            type: 'POST',
            data: {
                action: 'wheel_manager_award_points',
                nonce: wheelManagerBME.nonce,
                points: prizeData.points
            },
            success: function(response) {
                if (response.success) {
                    console.log('Points awarded:', response.data.message);
                    // Trigger custom event for other plugins/themes
                    $(document).trigger('wheel_manager_points_awarded', [response.data]);
                } else {
                    console.error('Failed to award points:', response.data.message);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', error);
            }
        });
    }

})(jQuery); 