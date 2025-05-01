<?php
/**
 * Template for displaying points information on the wheel
 *
 * @package Wheel_Manager_BME
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

$user_id = get_current_user_id();
if (!$user_id) {
    return;
}

$mycred = mycred();
$current_points = $mycred->get_users_balance($user_id);
$points_needed = isset($wheel_settings['points_cost']) ? absint($wheel_settings['points_cost']) : 100;
$points_reward = isset($wheel_settings['points_reward']) ? absint($wheel_settings['points_reward']) : 0;
?>

<div class="wheel-points-info">
    <div class="points-balance">
        <span class="points-label"><?php _e('Your Points Balance:', 'wheel-manager-bme'); ?></span>
        <span class="points-value"><?php echo esc_html($current_points); ?></span>
    </div>
    
    <div class="spin-cost">
        <span class="cost-label"><?php _e('Points Required to Spin:', 'wheel-manager-bme'); ?></span>
        <span class="cost-value"><?php echo esc_html($points_needed); ?></span>
    </div>

    <?php if ($points_reward > 0) : ?>
    <div class="potential-reward">
        <span class="reward-label"><?php _e('Potential Points Reward:', 'wheel-manager-bme'); ?></span>
        <span class="reward-value"><?php echo esc_html($points_reward); ?></span>
    </div>
    <?php endif; ?>

    <?php if ($current_points < $points_needed) : ?>
    <div class="insufficient-points">
        <p><?php 
            printf(
                __('You need %1$s more points to spin the wheel. <a href="%2$s">Learn how to earn points</a>.', 'wheel-manager-bme'),
                esc_html($points_needed - $current_points),
                esc_url(home_url('/mycred-points-info/'))
            ); 
        ?></p>
    </div>
    <?php endif; ?>
</div>

<style>
.wheel-points-info {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 15px;
    margin: 15px 0;
    font-size: 14px;
    line-height: 1.6;
}

.wheel-points-info > div {
    margin-bottom: 10px;
}

.wheel-points-info > div:last-child {
    margin-bottom: 0;
}

.points-label,
.cost-label,
.reward-label {
    font-weight: bold;
    margin-right: 5px;
}

.insufficient-points {
    color: #dc3545;
    font-style: italic;
}

.insufficient-points a {
    color: #0056b3;
    text-decoration: underline;
}

.insufficient-points a:hover {
    text-decoration: none;
}
</style> 