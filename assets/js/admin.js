jQuery(document).ready(function($) {
    // Initialize tooltips
    $('.wheel-manager-tooltip').tooltipster({
        theme: 'tooltipster-light',
        maxWidth: 300
    });

    // Handle table sorting
    $('.wp-list-table').tablesorter({
        sortList: [[0,0]],
        headers: {
            0: { sorter: 'text' },
            1: { sorter: 'digit' },
            2: { sorter: 'digit' },
            3: { sorter: 'digit' },
            4: { sorter: 'digit' }
        }
    });

    // Add refresh button functionality
    $('.wheel-manager-refresh').on('click', function(e) {
        e.preventDefault();
        var $button = $(this);
        var $container = $button.closest('.wheel-manager-stats');
        
        $button.addClass('updating-message');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wheel_manager_refresh_stats',
                nonce: wheel_manager_bme.nonce
            },
            success: function(response) {
                if (response.success) {
                    $container.find('.stat-box').each(function() {
                        var $box = $(this);
                        var stat = $box.data('stat');
                        if (response.data[stat]) {
                            $box.find('p').text(response.data[stat]);
                        }
                    });
                }
            },
            complete: function() {
                $button.removeClass('updating-message');
            }
        });
    });

    // Add export functionality
    $('.wheel-manager-export').on('click', function(e) {
        e.preventDefault();
        var $button = $(this);
        var type = $button.data('type');
        
        $button.addClass('updating-message');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wheel_manager_export',
                type: type,
                nonce: wheel_manager_bme.nonce
            },
            success: function(response) {
                if (response.success && response.data.url) {
                    window.location.href = response.data.url;
                }
            },
            complete: function() {
                $button.removeClass('updating-message');
            }
        });
    });

    // Add date range filter functionality
    $('.wheel-manager-date-range').on('change', function() {
        var $select = $(this);
        var $container = $select.closest('.wheel-manager-recent');
        var range = $select.val();
        
        $container.addClass('loading');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wheel_manager_filter_spins',
                range: range,
                nonce: wheel_manager_bme.nonce
            },
            success: function(response) {
                if (response.success) {
                    $container.find('tbody').html(response.data.html);
                }
            },
            complete: function() {
                $container.removeClass('loading');
            }
        });
    });
}); 