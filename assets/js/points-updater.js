jQuery(document).ready(function($) {
    // Update points balance periodically
    function updatePointsBalance() {
        $.ajax({
            url: wheelPoints.ajaxurl,
            type: 'POST',
            data: {
                action: 'get_updated_points',
                nonce: wheelPoints.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('.points-value').text(response.data.points);
                    
                    // Update insufficient points message if needed
                    var pointsNeeded = parseInt($('.cost-value').text());
                    var currentPoints = parseInt(response.data.points);
                    var insufficientPoints = $('.insufficient-points');
                    
                    if (currentPoints < pointsNeeded) {
                        if (insufficientPoints.length === 0) {
                            var message = $('<div class="insufficient-points"><p>You need ' + 
                                (pointsNeeded - currentPoints) + 
                                ' more points to spin the wheel. <a href="/mycred-points-info/">Learn how to earn points</a>.</p></div>');
                            $('.wheel-points-info').append(message);
                        } else {
                            insufficientPoints.find('p').html('You need ' + 
                                (pointsNeeded - currentPoints) + 
                                ' more points to spin the wheel. <a href="/mycred-points-info/">Learn how to earn points</a>.');
                        }
                    } else {
                        insufficientPoints.remove();
                    }
                }
            }
        });
    }

    // Update points every 30 seconds
    if ($('.wheel-points-info').length) {
        setInterval(updatePointsBalance, 30000);
    }

    // Update points after wheel spin
    $(document).on('wof_after_spin', function() {
        setTimeout(updatePointsBalance, 1000);
    });
}); 