(function($) {
    'use strict';

    // Initialize when document is ready
    $(document).ready(function() {
        initializePointsConversion();
        initializeWheelIntegration();
    });

    function initializePointsConversion() {
        $('.convert-points-btn').on('click', function(e) {
            e.preventDefault();
            const points = $(this).data('points');
            convertPoints(points);
        });
    }

    function convertPoints(points) {
        $.ajax({
            url: wheelManagerBME.ajaxurl,
            type: 'POST',
            data: {
                action: 'convert_points_to_spins',
                points: points,
                nonce: wheelManagerBME.nonce
            },
            beforeSend: function() {
                // Show loading state
                $('.convert-points-btn').prop('disabled', true);
            },
            success: function(response) {
                if (response.success) {
                    // Update stats display
                    updateStats(response.data);
                    showNotification('success', response.data.message);
                } else {
                    showNotification('error', response.data.message);
                }
            },
            error: function() {
                showNotification('error', 'An error occurred. Please try again.');
            },
            complete: function() {
                // Remove loading state
                $('.convert-points-btn').prop('disabled', false);
            }
        });
    }

    function initializeWheelIntegration() {
        // Listen for WP Optin Wheel events
        $(document).on('wof_before_spin', function(e, data) {
            // Verify if user has available spins
            checkSpinAvailability();
        });

        $(document).on('wof_after_spin', function(e, data) {
            // Handle spin result
            handleSpinResult(data);
        });
    }

    function checkSpinAvailability() {
        const availableSpins = parseInt($('.stats-card .stats-value').eq(1).text());
        if (availableSpins <= 0) {
            showNotification('error', 'You need to convert points to get spins!');
            return false;
        }
        return true;
    }

    function handleSpinResult(data) {
        if (!data || !data.prize) return;

        $.ajax({
            url: wheelManagerBME.ajaxurl,
            type: 'POST',
            data: {
                action: 'record_spin_result',
                prize: data.prize,
                nonce: wheelManagerBME.nonce
            },
            success: function(response) {
                if (response.success) {
                    updateStats(response.data);
                }
            }
        });
    }

    function updateStats(data) {
        if (data.total_points) {
            $('.stats-card .stats-value').eq(0).text(data.total_points);
        }
        if (data.available_spins) {
            $('.stats-card .stats-value').eq(1).text(data.available_spins);
        }
        if (data.total_chances) {
            $('.stats-card .stats-value').eq(2).text(data.total_chances);
        }
    }

    function showNotification(type, message) {
        const notificationClass = type === 'success' ? 'success' : 'error';
        const $notification = $(`
            <div class="wheel-manager-notification ${notificationClass}">
                <p>${message}</p>
            </div>
        `);

        // Remove existing notifications
        $('.wheel-manager-notification').remove();

        // Add new notification
        $('.wheel-manager-dashboard').prepend($notification);

        // Auto remove after 3 seconds
        setTimeout(function() {
            $notification.fadeOut(function() {
                $(this).remove();
            });
        }, 3000);
    }

})(jQuery); 