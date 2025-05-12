jQuery(document).ready(function($) {
    console.log('Wheel Manager BME - Initializing JavaScript');
    
    // Initialize tooltips only if jQuery UI is available
    if ($.fn.tooltip) {
        console.log('Wheel Manager BME - jQuery UI tooltip available, initializing tooltips');
        $('[data-tooltip]').tooltip();
    } else {
        console.warn('Wheel Manager BME - jQuery UI tooltip not available');
    }

    // Handle table sorting
    $('.sortable').on('click', function() {
        console.log('Wheel Manager BME - Table sort clicked');
        var column = $(this).data('column');
        var order = $(this).data('order') === 'asc' ? 'desc' : 'asc';
        console.log('Wheel Manager BME - Sorting by column:', column, 'order:', order);
        
        $.ajax({
            url: wheel_manager_bme.ajaxurl,
            type: 'POST',
            data: {
                action: 'wheel_manager_get_filtered_points',
                nonce: wheel_manager_bme.nonce,
                orderby: column,
                order: order
            },
            success: function(response) {
                console.log('Wheel Manager BME - Sort response received:', response);
                if (response.success) {
                    $('.wp-list-table tbody').html(response.data.html);
                    $('.sortable').removeClass('asc desc');
                    $('[data-column="' + column + '"]').addClass(order);
                    console.log('Wheel Manager BME - Table updated successfully');
                }
            },
            error: function(xhr, status, error) {
                console.error('Wheel Manager BME - Sort error:', error);
            }
        });
    });

    // Handle date range filter
    $('.wheel-manager-date-range').on('change', function() {
        console.log('Wheel Manager BME - Date range changed');
        var range = $(this).val();
        console.log('Wheel Manager BME - Selected range:', range);
        
        $.ajax({
            url: wheel_manager_bme.ajaxurl,
            type: 'POST',
            data: {
                action: 'wheel_manager_filter_spins',
                nonce: wheel_manager_bme.nonce,
                range: range
            },
            success: function(response) {
                console.log('Wheel Manager BME - Filter response received:', response);
                if (response.success) {
                    $('.wheel-manager-recent table tbody').html(response.data.html);
                    console.log('Wheel Manager BME - Table filtered successfully');
                }
            },
            error: function(xhr, status, error) {
                console.error('Wheel Manager BME - Filter error:', error);
            }
        });
    });

    // Handle export buttons
    $('.wheel-manager-export').on('click', function() {
        console.log('Wheel Manager BME - Export clicked');
        var type = $(this).data('type');
        console.log('Wheel Manager BME - Export type:', type);
        
        $.ajax({
            url: wheel_manager_bme.ajaxurl,
            type: 'POST',
            data: {
                action: 'wheel_manager_export_data',
                nonce: wheel_manager_bme.nonce,
                type: type
            },
            success: function(response) {
                console.log('Wheel Manager BME - Export response received:', response);
                if (response.success) {
                    window.location.href = response.data.url;
                    console.log('Wheel Manager BME - Export download initiated');
                }
            },
            error: function(xhr, status, error) {
                console.error('Wheel Manager BME - Export error:', error);
            }
        });
    });

    // Handle refresh stats
    $('.wheel-manager-refresh').on('click', function() {
        console.log('Wheel Manager BME - Refresh stats clicked');
        var $button = $(this);
        var $stats = $('.wheel-manager-stats');
        
        $button.prop('disabled', true);
        $stats.addClass('loading');
        
        $.ajax({
            url: wheel_manager_bme.ajaxurl,
            type: 'POST',
            data: {
                action: 'wheel_manager_refresh_stats',
                nonce: wheel_manager_bme.nonce
            },
            success: function(response) {
                console.log('Wheel Manager BME - Stats refresh response:', response);
                if (response.success) {
                    $('.stat-box[data-stat="total_spins"] p').text(response.data.total_spins);
                    $('.stat-box[data-stat="total_points"] p').text(response.data.total_points);
                    $('.stat-box[data-stat="active_users"] p').text(response.data.active_users);
                    console.log('Wheel Manager BME - Stats updated successfully');
                }
            },
            error: function(xhr, status, error) {
                console.error('Wheel Manager BME - Stats refresh error:', error);
            },
            complete: function() {
                $button.prop('disabled', false);
                $stats.removeClass('loading');
                console.log('Wheel Manager BME - Stats refresh completed');
            }
        });
    });

    // Handle Mabel Wheel of Fortune integration
    if (typeof wofVars !== 'undefined') {
        console.log('Wheel Manager BME - Mabel Wheel of Fortune integration detected');
        
        // Check eligibility before spin
        $(document).on('wof-lite-before-spin', function(e, wheelId) {
            console.log('Wheel Manager BME - Before spin check for wheel:', wheelId);
            $.ajax({
                url: wheel_manager_bme.ajaxurl,
                type: 'POST',
                data: {
                    action: 'wheel_manager_check_spin_eligibility',
                    nonce: wheel_manager_bme.nonce,
                    wheel_id: wheelId
                },
                success: function(response) {
                    console.log('Wheel Manager BME - Spin eligibility response:', response);
                    if (!response.success) {
                        console.warn('Wheel Manager BME - Spin not allowed:', response.data.message);
                        e.preventDefault();
                        alert(response.data.message);
                    } else {
                        console.log('Wheel Manager BME - Spin allowed');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Wheel Manager BME - Spin eligibility check error:', error);
                }
            });
        });

        // Update points after spin
        $(document).on('wof-lite-after-spin', function(e, wheelId, prize) {
            console.log('Wheel Manager BME - After spin for wheel:', wheelId, 'prize:', prize);
            $.ajax({
                url: wheel_manager_bme.ajaxurl,
                type: 'POST',
                data: {
                    action: 'wheel_manager_after_spin',
                    nonce: wheel_manager_bme.nonce,
                    wheel_id: wheelId,
                    prize: prize
                },
                success: function(response) {
                    console.log('Wheel Manager BME - After spin response:', response);
                    if (response.success) {
                        $('.wheel-manager-points-info').html(
                            '<p>Available Points: <strong>' + response.data.available_points + '</strong></p>' +
                            '<p>Available Spins: <strong>' + response.data.available_spins + '</strong></p>'
                        );
                        console.log('Wheel Manager BME - Points info updated successfully');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Wheel Manager BME - After spin error:', error);
                }
            });
        });
    } else {
        console.warn('Wheel Manager BME - Mabel Wheel of Fortune integration not detected');
    }
}); 