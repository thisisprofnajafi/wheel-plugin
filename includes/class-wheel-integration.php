<?php
/**
 * Wheel Integration Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class Wheel_Manager_BME_Wheel_Integration {
    private static $instance = null;
    private $mycred_integration;
    private $min_points_for_spin = 10;
    private $points_for_six_spins = 50;
    private $points_for_fifteen_spins = 100;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->mycred_integration = wheel_manager_bme_mycred_integration();
        $this->init_hooks();
    }

    private function init_hooks() {
        // Add hooks for Mabel Wheel of Fortune
        add_filter('mabel_wof_lite_can_spin', array($this, 'check_spin_eligibility'), 10, 2);
        add_action('mabel_wof_lite_before_spin', array($this, 'before_spin'), 10, 2);
        add_action('mabel_wof_lite_after_spin', array($this, 'after_spin'), 10, 3);
        add_filter('mabel_wof_lite_prize_multiplier', array($this, 'apply_points_multiplier'), 10, 2);
        
        // Add custom hooks for wheel display
        add_action('mabel_wof_lite_before_display', array($this, 'before_wheel_display'), 10, 1);
        add_action('mabel_wof_lite_after_display', array($this, 'after_wheel_display'), 10, 1);
        
        // Add custom hooks for prize calculation
        add_filter('mabel_wof_lite_calculate_prize', array($this, 'calculate_final_prize'), 10, 3);

        // Add filter for wheel visibility
        add_filter('wof_active_wheels', array($this, 'filter_active_wheels'), 10, 1);

        // Add action for wheel initialization
        add_action('wp_footer', array($this, 'initialize_wheel'), 100);
    }

    /**
     * Get user's wheel history and points calculation
     */
    private function get_user_wheel_history($user_email) {
        global $wpdb;
        
        // Get user ID from email
        $user = get_user_by('email', $user_email);
        if (!$user) {
            return array(
                'total_points_won' => 0,
                'total_spins' => 0,
                'last_spin_time' => null,
                'can_spin' => false,
                'available_points' => 0,
                'points_needed' => 10,
                'adjusted_points' => 0
            );
        }
        
        $user_id = $user->ID;
        
        // Get all spins for this user
        $spins = $wpdb->get_results($wpdb->prepare(
            "SELECT segment_text, created_date 
            FROM {$wpdb->prefix}wof_optins 
            WHERE email = %s 
            ORDER BY created_date DESC",
            $user_email
        ));
        
        $total_points_won = 0;
        $total_spins = count($spins);
        $last_spin_time = $total_spins > 0 ? $spins[0]->created_date : null;
        
        // Calculate total points won
        foreach ($spins as $spin) {
            // Extract number from segment_text
            preg_match('/\d+/', $spin->segment_text, $matches);
            if (!empty($matches)) {
                $total_points_won += (int)$matches[0];
            }
        }
        
        // Get current MyCred points
        $available_points = $this->mycred_integration->get_user_available_points($user_id);
        
        // Calculate adjusted points (remove 10 points per spin divided by 2)
        $points_deducted = ($total_spins * 10) / 2;
        $adjusted_points = $available_points - $points_deducted;
        
        // Calculate if user can spin
        $can_spin = true;
        
        // Check 24-hour restriction
        if ($last_spin_time && (time() - strtotime($last_spin_time)) < 86400) {
            $can_spin = false;
        }
        
        // Check if user has enough points (10 points per spin)
        $points_needed = 10;
        if ($adjusted_points < $points_needed) {
            $can_spin = false;
        }
        
        return array(
            'user_id' => $user_id,
            'total_points_won' => $total_points_won,
            'total_spins' => $total_spins,
            'last_spin_time' => $last_spin_time,
            'can_spin' => $can_spin,
            'available_points' => $available_points,
            'adjusted_points' => $adjusted_points,
            'points_deducted' => $points_deducted,
            'points_needed' => $points_needed,
            'spins' => $spins
        );
    }

    /**
     * Get the last time the user spun the wheel
     */
    private function get_last_spin_time($user_id) {
        global $wpdb;
        
        // Get user email
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return null;
        }
        
        // Get last spin time from wof_optins table
        $last_spin = $wpdb->get_var($wpdb->prepare(
            "SELECT created_date 
            FROM {$wpdb->prefix}wof_optins 
            WHERE email = %s 
            ORDER BY created_date DESC 
            LIMIT 1",
            $user->user_email
        ));
        
        return $last_spin;
    }

    /**
     * Initialize wheel display
     */
    public function initialize_wheel() {
        // Check if already initialized
        if (defined('WHEEL_MANAGER_BME_INITIALIZED')) {
            error_log('Wheel Manager BME - Wheel already initialized, skipping');
            return;
        }

        if (!is_user_logged_in()) {
            error_log('Wheel Manager BME - User not logged in, skipping wheel initialization');
            return;
        }

        $user_id = get_current_user_id();
        $user = get_user_by('id', $user_id);
        
        // Get user's wheel history
        $wheel_history = $this->get_user_wheel_history($user->user_email);
        
        error_log('Wheel Manager BME - User wheel history: ' . print_r($wheel_history, true));
        
        if (!$wheel_history['can_spin']) {
            error_log('Wheel Manager BME - User cannot spin wheel');
            error_log('Wheel Manager BME - Last spin: ' . $wheel_history['last_spin_time']);
            error_log('Wheel Manager BME - Available points: ' . $wheel_history['available_points']);
            return;
        }

        error_log('Wheel Manager BME - User can spin wheel');
        error_log('Wheel Manager BME - Total points won: ' . $wheel_history['total_points_won']);
        error_log('Wheel Manager BME - Total spins: ' . $wheel_history['total_spins']);
        
        $available_points = $wheel_history['available_points'];
        $min_points = (int)$this->min_points_for_spin;
        
        error_log('Wheel Manager BME - Initializing wheel display for prof');
        error_log('Wheel Manager BME - User ID: ' . $user_id);
        error_log('Wheel Manager BME - Available Points: ' . $available_points . ' (type: ' . gettype($available_points) . ')');
        error_log('Wheel Manager BME - Minimum Points Required: ' . $min_points . ' (type: ' . gettype($min_points) . ')');

        if ($available_points >= $min_points) {
            error_log('Wheel Manager BME - User has sufficient points, proceeding with wheel display');
            ?>
            <script type="text/javascript">
            jQuery(document).ready(function($) {
                // Check if already initialized in JavaScript
                if (window.wheelManagerBMEInitialized) {
                    console.log('Wheel Manager BME - Already initialized in JavaScript, skipping');
                    return;
                }

                console.log('Wheel Manager BME - Setting up wheel display');
                
                // Force wheel display
                if (typeof WOF !== 'undefined') {
                    // Show wheel immediately
                    WOF.Dispatcher.subscribe('wof-before-display', function(wheel) {
                        console.log('Wheel Manager BME - Wheel before display event');
                        wheel.appeartype = 'immediately';
                        wheel.appeardelay = 4;
                        return true;
                    });

                    WOF.Dispatcher.subscribe('wof-after-display', function(wheel) {
                        console.log('Wheel Manager BME - Wheel after display event');
                    });

                    // Handle wheel closing
                    function closeWheel() {
                        console.log('Wheel Manager BME - Closing wheel');
                        $('.wof-wheel, .wof-overlay, .wof-wheels').hide();
                        // Record the close time
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'wheel_manager_bme_record_close',
                                nonce: '<?php echo wp_create_nonce('wheel_manager_bme_close'); ?>'
                            }
                        });
                    }

                    // Add click handlers for close buttons
                    $(document).on('click', '.wof-close, .wof-close-icon, .wof-btn-done, .wof-close-wrapper a', function(e) {
                        e.preventDefault();
                        closeWheel();
                    });

                    // Directly style the wheel elements
                    function applyWheelStyles() {
                        console.log('Wheel Manager BME - Applying wheel styles');
                        
                        // Get wheel elements
                        var $wheel = $('.wof-wheel');
                        var $overlay = $('.wof-overlay');
                        var $wheels = $('.wof-wheels');
                        
                        console.log('Wheel Manager BME - Found elements:', {
                            wheel: $wheel.length,
                            overlay: $overlay.length,
                            wheels: $wheels.length
                        });

                        // Apply styles directly to elements
                        if ($wheel.length) {
                            $wheel.css({
                                'display': 'block',
                                'transform': 'translateX(0%)'
                            });
                        }

                        if ($overlay.length) {
                            $overlay.css({
                                'display': 'block'
                            });
                        }

                        if ($wheels.length) {
                            $wheels.css({
                                'display': 'block',
                                'opacity': '1'
                            });
                        }
                    }

                    // Apply styles immediately
                    applyWheelStyles();

                    // Also apply styles after a short delay to ensure elements are in DOM
                    setTimeout(applyWheelStyles, 100);
                    setTimeout(applyWheelStyles, 500);
                    setTimeout(applyWheelStyles, 1000);

                    // Watch for dynamic changes
                    var observer = new MutationObserver(function(mutations) {
                        mutations.forEach(function(mutation) {
                            if (mutation.addedNodes.length) {
                                applyWheelStyles();
                            }
                        });
                    });

                    // Start observing the document body for changes
                    observer.observe(document.body, {
                        childList: true,
                        subtree: true
                    });

                    // Mark as initialized
                    window.wheelManagerBMEInitialized = true;
                } else {
                    console.log('Wheel Manager BME - WOF object not found in window');
                }
            });
            </script>
            <?php
            // Mark as initialized in PHP
            define('WHEEL_MANAGER_BME_INITIALIZED', true);
        } else {
            error_log('Wheel Manager BME - User has insufficient points');
            error_log('Wheel Manager BME - Points comparison: ' . $available_points . ' < ' . $min_points);
            error_log('Wheel Manager BME - Points difference: ' . ($min_points - $available_points) . ' points needed');
        }
    }

    /**
     * Record wheel close time
     */
    public function record_wheel_close() {
        check_ajax_referer('wheel_manager_bme_close', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error('User not logged in');
            return;
        }

        $user_id = get_current_user_id();
        global $wpdb;
        $table_name = $wpdb->prefix . 'wheel_spin_history';
        
        $wpdb->insert(
            $table_name,
            array(
                'user_id' => $user_id,
                'wheel_id' => 0,
                'points_used' => 0,
                'original_prize' => 0,
                'final_prize' => 0,
                'multiplier' => 1,
                'created_at' => current_time('mysql'),
                'action' => 'close'
            ),
            array('%d', '%d', '%f', '%f', '%f', '%f', '%s', '%s')
        );
        
        wp_send_json_success();
    }

    /**
     * Filter active wheels based on user points
     */
    public function filter_active_wheels($wheels) {
        error_log('Wheel Manager BME - Filtering active wheels');
        error_log('Wheel Manager BME - Number of wheels before filter: ' . count($wheels));

        if (!is_user_logged_in()) {
            error_log('Wheel Manager BME - User not logged in, hiding all wheels');
            return array();
        }

        $user_id = get_current_user_id();
        $available_points = $this->mycred_integration->get_user_available_points($user_id);
        error_log('Wheel Manager BME - User ID: ' . $user_id . ', Available Points: ' . $available_points);

        if ($available_points < $this->min_points_for_spin) {
            error_log('Wheel Manager BME - User has insufficient points, hiding all wheels');
            return array();
        }

        error_log('Wheel Manager BME - User has sufficient points, showing wheels');
        
        // Ensure wheel is active and visible
        foreach ($wheels as $wheel) {
            $wheel->active = 1;
            $wheel->appeartype = 'immediately';
            $wheel->appeardelay = 0;
            $wheel->usage = 'popup';
        }
        
        return $wheels;
    }

    /**
     * Check if user can spin the wheel
     */
    public function check_spin_eligibility($can_spin, $wheel_id) {
        error_log('Wheel Manager BME - Checking spin eligibility for wheel ID: ' . $wheel_id);
        
        if (!is_user_logged_in()) {
            error_log('Wheel Manager BME - User not logged in, cannot spin');
            return false;
        }

        $user_id = get_current_user_id();
        $available_points = $this->mycred_integration->get_user_available_points($user_id);
        error_log('Wheel Manager BME - User ID: ' . $user_id . ', Available Points: ' . $available_points);
        
        $can_spin = $available_points >= $this->min_points_for_spin;
        error_log('Wheel Manager BME - Can user spin? ' . ($can_spin ? 'Yes' : 'No'));
        
        return $can_spin;
    }

    /**
     * Actions before wheel spin
     */
    public function before_spin($wheel_id, $user_id) {
        error_log('Wheel Manager BME - Before spin for wheel ID: ' . $wheel_id . ', User ID: ' . $user_id);
        
        if (!is_user_logged_in()) {
            error_log('Wheel Manager BME - User not logged in, cannot spin');
            return false;
        }

        $available_points = $this->mycred_integration->get_user_available_points($user_id);
        $points_cost = $this->calculate_points_cost($available_points);
        error_log('Wheel Manager BME - Available Points: ' . $available_points . ', Points Cost: ' . $points_cost);

        if ($available_points < $points_cost) {
            error_log('Wheel Manager BME - Insufficient points for spin');
            return false;
        }

        // Deduct points
        $this->mycred_integration->deduct_points($user_id, $points_cost, 'wheel_spin');
        error_log('Wheel Manager BME - Points deducted successfully');
        return true;
    }

    /**
     * Actions after wheel spin
     */
    public function after_spin($wheel_id, $user_id, $prize) {
        error_log('Wheel Manager BME - After spin for wheel ID: ' . $wheel_id . ', User ID: ' . $user_id . ', Prize: ' . $prize);
        
        if (!is_user_logged_in()) {
            error_log('Wheel Manager BME - User not logged in, cannot process spin');
            return;
        }

        $multiplier = $this->apply_points_multiplier(1, $user_id);
        $final_prize = $prize * $multiplier;
        error_log('Wheel Manager BME - Multiplier: ' . $multiplier . ', Final Prize: ' . $final_prize);

        // Log the spin
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'wheel_spin_history',
            array(
                'user_id' => $user_id,
                'wheel_id' => $wheel_id,
                'points_used' => $this->calculate_points_cost($this->mycred_integration->get_user_available_points($user_id)),
                'original_prize' => $prize,
                'final_prize' => $final_prize,
                'multiplier' => $multiplier,
                'created_at' => current_time('mysql')
            ),
            array('%d', '%d', '%f', '%f', '%f', '%f', '%s')
        );
        error_log('Wheel Manager BME - Spin logged in database');

        // Add points to MyCred
        $this->mycred_integration->add_points_to_mycred($user_id, $final_prize);
        error_log('Wheel Manager BME - Points added to MyCred');

        return array(
            'final_prize' => $final_prize,
            'available_points' => $this->mycred_integration->get_user_available_points($user_id),
            'available_spins' => $this->mycred_integration->get_available_spins($user_id)
        );
    }

    /**
     * Apply points multiplier to prize
     */
    public function apply_points_multiplier($multiplier, $user_id) {
        if (!is_user_logged_in()) {
            return $multiplier;
        }

        $settings = get_option('wheel_manager_bme_settings', array(
            'enable_multiplier' => true,
            'multiplier_threshold' => 100,
            'multiplier_value' => 1.5
        ));

        if (!$settings['enable_multiplier']) {
            return $multiplier;
        }

        $available_points = $this->mycred_integration->get_user_available_points($user_id);
        if ($available_points >= $settings['multiplier_threshold']) {
            return $settings['multiplier_value'];
        }

        return $multiplier;
    }

    /**
     * Calculate final prize with multiplier
     */
    public function calculate_final_prize($prize, $wheel_id, $user_id) {
        if (!is_user_logged_in()) {
            return $prize;
        }

        $multiplier = $this->apply_points_multiplier(1, $user_id);
        return $prize * $multiplier;
    }

    /**
     * Calculate points cost based on available points
     */
    private function calculate_points_cost($available_points) {
        if ($available_points >= $this->points_for_fifteen_spins) {
            return $this->points_for_fifteen_spins / 15;
        } elseif ($available_points >= $this->points_for_six_spins) {
            return $this->points_for_six_spins / 6;
        }
        return $this->min_points_for_spin;
    }

    /**
     * Actions before wheel display
     */
    public function before_wheel_display($wheel_id) {
        if (!is_user_logged_in()) {
            return;
        }

        $user_id = get_current_user_id();
        
        // Add eligibility check data
        wp_localize_script('wof-wheel', 'wheel_manager_bme', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wheel_manager_bme'),
            'user_id' => $user_id
        ));

        // Add custom CSS for eligibility message
        ?>
        <style>
            .wheel-manager-message {
                padding: 10px;
                margin: 10px 0;
                border-radius: 4px;
                text-align: center;
            }
            .wheel-manager-message.error {
                background-color: #ffebee;
                color: #c62828;
                border: 1px solid #ef9a9a;
            }
            .wheel-manager-message.success {
                background-color: #e8f5e9;
                color: #2e7d32;
                border: 1px solid #a5d6a7;
            }
            .wheel-manager-points-info {
                font-size: 14px;
                margin: 10px 0;
                text-align: center;
            }
        </style>
        <?php
    }

    /**
     * Actions after wheel display
     */
    public function after_wheel_display($wheel_id) {
        if (!is_user_logged_in()) {
            return;
        }

        $user_id = get_current_user_id();
        $available_spins = $this->mycred_integration->get_available_spins($user_id);
        $available_points = $this->mycred_integration->get_user_available_points($user_id);
        ?>
        <div class="wheel-manager-points-info">
            <p>Available Points: <strong><?php echo number_format($available_points, 2); ?></strong></p>
            <p>Available Spins: <strong><?php echo $available_spins; ?></strong></p>
        </div>
        <?php
    }
}

// Initialize the wheel integration
function wheel_manager_bme_wheel_integration() {
    return Wheel_Manager_BME_Wheel_Integration::get_instance();
}

wheel_manager_bme_wheel_integration(); 