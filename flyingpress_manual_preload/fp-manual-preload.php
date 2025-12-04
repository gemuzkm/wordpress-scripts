<?php
/*
Plugin Name: FlyingPress Manual Preload
Description: Manual and automatic FlyingPress cache preload with configurable interval
Version: 1.1
*/

// Add page to Tools menu
add_action('admin_menu', 'fp_preload_add_menu');
function fp_preload_add_menu() {
    add_management_page(
        'FlyingPress Preload',
        'FP Preload',
        'manage_options',
        'fp-preload',
        'fp_preload_page'
    );
}

// Settings page
function fp_preload_page() {
    // Handle manual preload start
    if (isset($_POST['fp_preload_now']) && check_admin_referer('fp_preload_now_action')) {
        if (class_exists('FlyingPress\Preload')) {
            FlyingPress\Preload::preload_cache();
            echo '<div class="notice notice-success"><p><strong>Preload started!</strong> Cache is updating in the background.</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>FlyingPress is not active or the Preload class was not found.</p></div>';
        }
    }

    // Handle settings save
    if (isset($_POST['fp_preload_save']) && check_admin_referer('fp_preload_settings_action')) {
        $enabled = isset($_POST['fp_auto_preload']) ? 1 : 0;
        $interval_hours = intval($_POST['fp_interval_hours']);
        $nonce_hours = intval($_POST['fp_nonce_hours']);
        
        // Validate preload interval (12 hours to 7 days = 168 hours)
        if ($interval_hours < 12) $interval_hours = 12;
        if ($interval_hours > 168) $interval_hours = 168;
        
        // Validate nonce lifetime (12 hours to 7 days = 168 hours)
        if ($nonce_hours < 12) $nonce_hours = 12;
        if ($nonce_hours > 168) $nonce_hours = 168;
        
        update_option('fp_auto_preload_enabled', $enabled);
        update_option('fp_auto_preload_interval_hours', $interval_hours);
        update_option('fp_nonce_lifetime_hours', $nonce_hours);
        
        // Reschedule cron job with new schedule
        fp_preload_reschedule_cron();
        
        echo '<div class="notice notice-success"><p><strong>Settings saved!</strong></p></div>';
    }

    // Current settings
    $enabled = get_option('fp_auto_preload_enabled', 0);
    $interval_hours = get_option('fp_auto_preload_interval_hours', 72); // Default: 3 days
    $nonce_hours = get_option('fp_nonce_lifetime_hours', 48); // Default: 48 hours
    $next_run = wp_next_scheduled('fp_cron_preload_event');
    
    ?>
    <div class="wrap">
        <h1>FlyingPress – Cache Preload Control</h1>
        
        <!-- Manual preload -->
        <div class="card" style="max-width: 600px;">
            <h2>Manual Preload</h2>
            <p>Start an immediate update of the entire cache (without clearing existing cache).</p>
            <form method="post">
                <?php wp_nonce_field('fp_preload_now_action'); ?>
                <p><button type="submit" name="fp_preload_now" class="button button-primary button-hero">▶ Start Preload Now</button></p>
            </form>
        </div>

        <!-- Automatic preload -->
        <div class="card" style="max-width: 600px; margin-top: 20px;">
            <h2>Automatic Preload</h2>
            <form method="post">
                <?php wp_nonce_field('fp_preload_settings_action'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="fp_auto_preload">Enable automatic preload</label>
                        </th>
                        <td>
                            <input type="checkbox" name="fp_auto_preload" id="fp_auto_preload" value="1" <?php checked($enabled, 1); ?>>
                            <p class="description">Automatically update cache on schedule</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="fp_interval_hours">Preload interval</label>
                        </th>
                        <td>
                            <select name="fp_interval_hours" id="fp_interval_hours" class="regular-text">
                                <?php 
                                // Generate options: 12h, 24h (1 day), 36h, 48h (2 days), ..., 168h (7 days)
                                for ($hours = 12; $hours <= 168; $hours += 12): 
                                    $label = fp_preload_hours_label($hours);
                                ?>
                                    <option value="<?php echo $hours; ?>" <?php selected($interval_hours, $hours); ?>>
                                        <?php echo $label; ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                            <p class="description">How often to run automatic preload</p>
                        </td>
                    </tr>
                </table>

                <?php if ($enabled && $next_run): ?>
                    <div class="notice notice-info inline" style="margin: 10px 0;">
                        <p><strong>Next automatic preload:</strong> <?php echo date('Y-m-d H:i', $next_run + (get_option('gmt_offset') * 3600)); ?></p>
                    </div>
                <?php endif; ?>

                <p class="submit">
                    <button type="submit" name="fp_preload_save" class="button button-primary">Save Settings</button>
                </p>
            </form>
        </div>

        <!-- Nonce Lifetime Settings -->
        <div class="card" style="max-width: 600px; margin-top: 20px;">
            <h2>Security Settings</h2>
            <form method="post">
                <?php wp_nonce_field('fp_preload_settings_action'); ?>
                <input type="hidden" name="fp_auto_preload" value="<?php echo $enabled; ?>">
                <input type="hidden" name="fp_interval_hours" value="<?php echo $interval_hours; ?>">
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="fp_nonce_hours">Nonce lifetime</label>
                        </th>
                        <td>
                            <select name="fp_nonce_hours" id="fp_nonce_hours" class="regular-text">
                                <?php 
                                for ($hours = 12; $hours <= 168; $hours += 12): 
                                    $label = fp_preload_hours_label($hours);
                                ?>
                                    <option value="<?php echo $hours; ?>" <?php selected($nonce_hours, $hours); ?>>
                                        <?php echo $label; ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                            <p class="description">Security nonce expiration time (prevents CSRF attacks on long-lived pages). Default: 48 hours</p>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" name="fp_preload_save" class="button button-primary">Save Settings</button>
                </p>
            </form>
        </div>

        <!-- Info -->
        <div class="card" style="max-width: 600px; margin-top: 20px;">
            <h3>ℹ️ How it works</h3>
            <ul>
                <li><strong>Manual preload</strong> updates cache immediately, useful after site changes</li>
                <li><strong>Automatic preload</strong> lets WordPress update cache automatically on schedule</li>
                <li>Preload <strong>does not delete</strong> existing cache, it only refreshes it</li>
                <li>The process runs in the background without blocking the site</li>
                <li><strong>Nonce lifetime</strong> extends the security token validity for admin pages that stay open for long periods</li>
            </ul>
        </div>
    </div>
    <?php
}

// Convert hours to readable label
function fp_preload_hours_label($hours) {
    if ($hours < 24) {
        return $hours . ' hours';
    }
    
    $days = $hours / 24;
    
    // If it's a whole number of days
    if ($hours % 24 == 0) {
        return $days . ' ' . ($days == 1 ? 'day' : 'days');
    }
    
    // Otherwise show days + hours
    $full_days = floor($days);
    $remaining_hours = $hours % 24;
    return $full_days . ' ' . ($full_days == 1 ? 'day' : 'days') . ' ' . $remaining_hours . ' hours';
}

// Register custom intervals to cron
add_filter('cron_schedules', 'fp_preload_add_cron_intervals');
function fp_preload_add_cron_intervals($schedules) {
    // Generate intervals from 12 hours to 7 days (168 hours) with 12-hour step
    for ($hours = 12; $hours <= 168; $hours += 12) {
        $schedules["fp_every_{$hours}_hours"] = [
            'interval' => $hours * HOUR_IN_SECONDS,
            'display'  => 'Every ' . fp_preload_hours_label($hours)
        ];
    }
    return $schedules;
}

// Reschedule cron event
function fp_preload_reschedule_cron() {
    // Remove old event
    $timestamp = wp_next_scheduled('fp_cron_preload_event');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'fp_cron_preload_event');
    }
    
    // Schedule new if enabled
    $enabled = get_option('fp_auto_preload_enabled', 0);
    if ($enabled) {
        $interval_hours = get_option('fp_auto_preload_interval_hours', 72);
        $recurrence = "fp_every_{$interval_hours}_hours";
        wp_schedule_event(time(), $recurrence, 'fp_cron_preload_event');
    }
}

// Cron event handler
add_action('fp_cron_preload_event', 'fp_preload_run_cron');
function fp_preload_run_cron() {
    if (class_exists('FlyingPress\Preload')) {
        FlyingPress\Preload::preload_cache();
    }
}

// Extend nonce lifetime based on settings
add_filter('nonce_life', 'fp_preload_nonce_lifetime');
function fp_preload_nonce_lifetime() {
    $nonce_hours = get_option('fp_nonce_lifetime_hours', 48);
    return $nonce_hours * HOUR_IN_SECONDS;
}

// Plugin activation
register_activation_hook(__FILE__, 'fp_preload_activate');
function fp_preload_activate() {
    if (!get_option('fp_auto_preload_interval_hours')) {
        update_option('fp_auto_preload_interval_hours', 72); // Default: 3 days
    }
    if (!get_option('fp_auto_preload_enabled')) {
        update_option('fp_auto_preload_enabled', 0);
    }
    if (!get_option('fp_nonce_lifetime_hours')) {
        update_option('fp_nonce_lifetime_hours', 48); // Default: 48 hours
    }
}

// Plugin deactivation
register_deactivation_hook(__FILE__, 'fp_preload_deactivate');
function fp_preload_deactivate() {
    $timestamp = wp_next_scheduled('fp_cron_preload_event');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'fp_cron_preload_event');
    }
}
