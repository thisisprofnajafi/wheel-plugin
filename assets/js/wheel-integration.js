jQuery(document).ready(function($) {
    // Initialize tooltips only if jQuery UI is available
    if ($.fn.tooltip) {
        $('[data-tooltip]').tooltip();
    }

    // Handle table sorting
    $('.sortable').on('click', function() {
        var column = $(this).data('column');
        var order = $(this).data('order') === 'asc' ? 'desc' : 'asc';
        
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
                if (response.success) {
                    $('.wp-list-table tbody').html(response.data.html);
                    $('.sortable').removeClass('asc desc');
                    $('[data-column="' + column + '"]').addClass(order);
                }
            }
        });
    });

    // Handle date range filter
    $('.wheel-manager-date-range').on('change', function() {
        var range = $(this).val();
        
        $.ajax({
            url: wheel_manager_bme.ajaxurl,
            type: 'POST',
            data: {
                action: 'wheel_manager_filter_spins',
                nonce: wheel_manager_bme.nonce,
                range: range
            },
            success: function(response) {
                if (response.success) {
                    $('.wheel-manager-recent table tbody').html(response.data.html);
                }
            }
        });
    });

    // Handle export buttons
    $('.wheel-manager-export').on('click', function() {
        var type = $(this).data('type');
        
        $.ajax({
            url: wheel_manager_bme.ajaxurl,
            type: 'POST',
            data: {
                action: 'wheel_manager_export_data',
                nonce: wheel_manager_bme.nonce,
                type: type
            },
            success: function(response) {
                if (response.success) {
                    window.location.href = response.data.url;
                }
            }
        });
    });

    // Handle refresh stats
    $('.wheel-manager-refresh').on('click', function() {
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
                if (response.success) {
                    $('.stat-box[data-stat="total_spins"] p').text(response.data.total_spins);
                    $('.stat-box[data-stat="total_points"] p').text(response.data.total_points);
                    $('.stat-box[data-stat="active_users"] p').text(response.data.active_users);
                }
            },
            complete: function() {
                $button.prop('disabled', false);
                $stats.removeClass('loading');
            }
        });
    });

    // Handle Mabel Wheel of Fortune integration
    if (typeof wofVars !== 'undefined') {
        // Check eligibility before spin
        $(document).on('wof-lite-before-spin', function(e, wheelId) {
            $.ajax({
                url: wheel_manager_bme.ajaxurl,
                type: 'POST',
                data: {
                    action: 'wheel_manager_check_spin_eligibility',
                    nonce: wheel_manager_bme.nonce,
                    wheel_id: wheelId
                },
                success: function(response) {
                    if (!response.success) {
                        e.preventDefault();
                        alert(response.data.message);
                    }
                }
            });
        });

        // Update points after spin
        $(document).on('wof-lite-after-spin', function(e, wheelId, prize) {
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
                    if (response.success) {
                        $('.wheel-manager-points-info').html(
                            '<p>Available Points: <strong>' + response.data.available_points + '</strong></p>' +
                            '<p>Available Spins: <strong>' + response.data.available_spins + '</strong></p>'
                        );
                    }
                }
            });
        });
    }
}); 