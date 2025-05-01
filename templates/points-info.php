<?php
/**
 * Template for displaying points information
 *
 * @package Wheel_Manager_BME
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

$user_id = get_current_user_id();
$available_spins = $this->get_available_spins($user_id);
?>

<div class="wheel-points-info">
    <?php if ($available_spins['spins'] > 0): ?>
        <div class="points-message">
            <?php
            printf(
                __('You have %d spins available with %d%% bonus!', 'wheel-manager-bme'),
                $available_spins['spins'],
                $available_spins['bonus']
            );
            ?>
        </div>
        <div class="points-details">
            <?php
            $total_points = $this->mycred->get_users_balance($user_id);
            printf(
                __('Total Points: %d', 'wheel-manager-bme'),
                $total_points
            );
            ?>
        </div>
    <?php else: ?>
        <div class="points-message">
            <?php _e('You need more points to spin the wheel!', 'wheel-manager-bme'); ?>
        </div>
        <div class="points-details">
            <?php
            $total_points = $this->mycred->get_users_balance($user_id);
            printf(
                __('Current Points: %d', 'wheel-manager-bme'),
                $total_points
            );
            ?>
        </div>
    <?php endif; ?>
</div>

<style>
.wheel-points-info {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    padding: 15px;
    margin-bottom: 20px;
    text-align: center;
}

.points-message {
    font-size: 18px;
    font-weight: bold;
    margin-bottom: 10px;
    color: #28a745;
}

.points-details {
    font-size: 14px;
    color: #6c757d;
}
</style> 