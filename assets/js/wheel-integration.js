jQuery(document).ready(function($) {
    // Check eligibility before showing wheel
    function checkEligibility() {
        if (!window.wheel_manager_bme) {
            return;
        }

        $.ajax({
            url: window.wheel_manager_bme.ajaxurl,
            type: 'POST',
            data: {
                action: 'wheel_manager_check_spin_eligibility',
                nonce: window.wheel_manager_bme.nonce,
                user_id: window.wheel_manager_bme.user_id
            },
            success: function(response) {
                if (response.success) {
                    showEligibilityMessage('success', 'You can spin the wheel!');
                    updatePointsInfo(response.data);
                } else {
                    showEligibilityMessage('error', response.data.message);
                }
            },
            error: function() {
                showEligibilityMessage('error', 'Error checking eligibility. Please try again.');
            }
        });
    }

    // Show eligibility message
    function showEligibilityMessage(type, message) {
        let messageDiv = $('.wheel-manager-message');
        if (messageDiv.length === 0) {
            messageDiv = $('<div class="wheel-manager-message"></div>');
            $('.optin-wheel-container').prepend(messageDiv);
        }
        messageDiv.removeClass('error success').addClass(type).text(message);
    }

    // Update points info
    function updatePointsInfo(data) {
        $('.wheel-manager-points-info').html(`
            <p>Available Points: <strong>${data.available_points}</strong></p>
            <p>Available Spins: <strong>${data.available_spins}</strong></p>
        `);
    }

    // Hook into Optin Wheel events
    $(document).on('optin_wheel_before_spin', function(e, wheelId) {
        if (!window.wheel_manager_bme) {
            return;
        }

        $.ajax({
            url: window.wheel_manager_bme.ajaxurl,
            type: 'POST',
            data: {
                action: 'wheel_manager_check_spin_eligibility',
                nonce: window.wheel_manager_bme.nonce,
                user_id: window.wheel_manager_bme.user_id
            },
            success: function(response) {
                if (!response.success) {
                    e.preventDefault();
                    showEligibilityMessage('error', response.data.message);
                }
            },
            error: function() {
                e.preventDefault();
                showEligibilityMessage('error', 'Error checking eligibility. Please try again.');
            }
        });
    });

    $(document).on('optin_wheel_after_spin', function(e, wheelId, prize) {
        if (!window.wheel_manager_bme) {
            return;
        }

        // Update points info after successful spin
        $.ajax({
            url: window.wheel_manager_bme.ajaxurl,
            type: 'POST',
            data: {
                action: 'wheel_manager_check_spin_eligibility',
                nonce: window.wheel_manager_bme.nonce,
                user_id: window.wheel_manager_bme.user_id
            },
            success: function(response) {
                if (response.success) {
                    updatePointsInfo(response.data);
                }
            }
        });
    });

    // Initial eligibility check
    checkEligibility();
}); 