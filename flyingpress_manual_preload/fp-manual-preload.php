<?php
/*
Plugin Name: FlyingPress Manual Preload
Description: Manual and automatic FlyingPress cache preload with configurable interval
Version: 1.0
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
        $interval_days = intval($_POST['fp_interval_days']);
        
        // Validate interval
        if ($interval_days < 2) $interval_days = 2;
        if ($interval_days > 7) $interval_days = 7;
        
        update_option('fp_auto_preload_enabled', $enabled);
        update_option('fp_auto_preload_interval', $interval_days);
        
        // Reschedule cron job with new schedule
        fp_preload_reschedule_cron();
        
        echo '<div class="notice notice-success"><p><strong>Settings saved!</strong></p></div>';
    }

    // Current settings
    $enabled = get_option('fp_auto_preload_enabled', 0);
    $interval_days = get_option('fp_auto_preload_interval', 3);
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
                            <label for="fp_interval_days">Preload interval</label>
                        </th>
                        <td>
                            <select name="fp_interval_days" id="fp_interval_days" class="regular-text">
                                <?php for ($i = 2; $i <= 7; $i++): ?>
                                    <option value="<?php echo $i; ?>" <?php selected($interval_days, $i); ?>>
                                        Every <?php echo $i; ?> <?php echo fp_preload_days_word($i); ?>
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

        <!-- Info -->
        <div class="card" style="max-width: 600px; margin-top: 20px;">
            <h3>ℹ️ How it works</h3>
            <ul>
                <li><strong>Manual preload</strong> updates cache immediately, useful after site changes</li>
                <li><strong>Automatic preload</strong> lets WordPress update cache automatically on schedule</li>
                <li>Preload <strong>does not delete</strong> existing cache, it only refreshes it</li>
                <li>The process runs in the background without blocking the site</li>
            </ul>
        </div>
    </div>
    <?php
}

// Declension for "day/days"
function fp_preload_days_word($n) {
    if ($n == 1) return 'day';
    return 'days';
}

// Register custom intervals to cron
add_filter('cron_schedules', 'fp_preload_add_cron_intervals');
function fp_preload_add_cron_intervals($schedules) {
    for ($i = 2; $i <= 7; $i++) {
        $schedules["fp_every_{$i}_days"] = [
            'interval' => $i * DAY_IN_SECONDS,
            'display'  => sprintf('Every %d %s', $i, fp_preload_days_word($i))
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
        $interval_days = get_option('fp_auto_preload_interval', 3);
        $recurrence = "fp_every_{$interval_days}_days";
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

// Plugin activation
register_activation_hook(__FILE__, 'fp_preload_activate');
function fp_preload_activate() {
    if (!get_option('fp_auto_preload_interval')) {
        update_option('fp_auto_preload_interval', 3);
    }
    if (!get_option('fp_auto_preload_enabled')) {
        update_option('fp_auto_preload_enabled', 0);
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
