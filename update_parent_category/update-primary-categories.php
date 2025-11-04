<?php
/**
 * Script for bulk assignment of primary categories in SEOPress
 * For use with WP-CLI: wp eval-file update-primary-categories.php
 */

// Function for safe console output
function cli_log($message) {
    if (defined('WP_CLI') && WP_CLI) {
        WP_CLI::log($message);
    } else {
        echo $message . "\n";
    }
}

function cli_success($message) {
    if (defined('WP_CLI') && WP_CLI) {
        WP_CLI::success($message);
    } else {
        echo "[SUCCESS] " . $message . "\n";
    }
}

function cli_warning($message) {
    if (defined('WP_CLI') && WP_CLI) {
        WP_CLI::warning($message);
    } else {
        echo "[WARNING] " . $message . "\n";
    }
}

// Function to find the deepest category
function find_deepest_category($categories) {
    if (empty($categories)) {
        return null;
    }

    $deepest_category = null;
    $max_level = -1;

    foreach ($categories as $cat_id) {
        $category = get_category($cat_id);
        if (!$category || is_wp_error($category)) {
            continue;
        }

        $level = 0;
        $current_cat = $category;

        // Считаем уровень вложенности категории
        while ($current_cat->parent != 0) {
            $level++;
            $current_cat = get_category($current_cat->parent);
            if (!$current_cat || is_wp_error($current_cat)) {
                break;
            }
        }

        // Find the deepest category
        if ($level > $max_level) {
            $max_level = $level;
            $deepest_category = $cat_id;
        }
    }

    return $deepest_category;
}

// Main update function
function bulk_update_primary_categories() {
    global $wpdb;

    // Processing parameters
    $batch_size = 100;
    $offset = 0;
    $processed_count = 0;
    $updated_count = 0;
    $skipped_count = 0;

    cli_log("Starting bulk update of primary categories...");
    cli_log("Batch size: " . $batch_size);

    // Get total post count for progress
    $total_posts = wp_count_posts('post');
    $total_published = $total_posts->publish;
    cli_log("Total published posts: " . $total_published);

    do {
        // Get batch of posts
        $posts = get_posts(array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'numberposts' => $batch_size,
            'offset' => $offset,
            'fields' => 'ids',
            'suppress_filters' => false
        ));

        if (empty($posts)) {
            break;
        }

        cli_log("Processing posts " . ($offset + 1) . " - " . ($offset + count($posts)) . " of " . $total_published);

        foreach ($posts as $post_id) {
            $processed_count++;

            // Get post categories
            $categories = wp_get_post_categories($post_id);

            if (empty($categories)) {
                cli_log("  Пост {$post_id}: skipped (no categories)");
                $skipped_count++;
                continue;
            }

            // Check if primary category is already set
            $existing_primary = get_post_meta($post_id, '_seopress_robots_primary_cat', true);

            // Find the deepest category
            $deepest_category = find_deepest_category($categories);

            if (!$deepest_category) {
                cli_log("  Пост {$post_id}: skipped (could not determine deep category)");
                $skipped_count++;
                continue;
            }

            // If primary category already exists and matches the found one
            if ($existing_primary && $existing_primary == $deepest_category) {
                cli_log("  Пост {$post_id}: skipped (correct primary category already set {$deepest_category})");
                $skipped_count++;
                continue;
            }

            // Update primary category
            $result = update_post_meta($post_id, '_seopress_robots_primary_cat', $deepest_category);

            if ($result) {
                $category_name = get_category($deepest_category)->name;
                cli_log("  Пост {$post_id}: updated (primary category: {$category_name} [ID: {$deepest_category}])");
                $updated_count++;
            } else {
                cli_warning("  Пост {$post_id}: error during update");
            }
        }

        $offset += $batch_size;

        // Memory cleanup every 100 posts
        wp_cache_flush();

        // Show progress
        $progress = round(($processed_count / $total_published) * 100, 1);
        cli_log("Progress: {$progress}% ({$processed_count}/{$total_published})");

        // Small pause to reduce server load
        if (function_exists('sleep')) {
            sleep(1);
        }

    } while (count($posts) == $batch_size);

    // Final statistics
    cli_success("=== COMPLETED ===");
    cli_success("Total posts processed: " . $processed_count);
    cli_success("Updated: " . $updated_count);
    cli_success("Skipped: " . $skipped_count);
    cli_success("=================");
}

// Function for testing on a small sample
function test_primary_categories($limit = 10) {
    cli_log("Test mode: processing {$limit} posts");

    $posts = get_posts(array(
        'post_type' => 'post',
        'post_status' => 'publish',
        'numberposts' => $limit,
        'fields' => 'ids'
    ));

    foreach ($posts as $post_id) {
        $categories = wp_get_post_categories($post_id);
        $post_title = get_the_title($post_id);

        cli_log("\nПост {$post_id}: '{$post_title}'");

        if (empty($categories)) {
            cli_log("  No categories");
            continue;
        }

        cli_log("  Categories:");
        foreach ($categories as $cat_id) {
            $cat = get_category($cat_id);
            $level = 0;
            $current_cat = $cat;

            while ($current_cat->parent != 0) {
                $level++;
                $current_cat = get_category($current_cat->parent);
            }

            $indent = str_repeat("    ", $level);
            cli_log("    {$indent}- {$cat->name} [ID: {$cat_id}, Level: {$level}]");
        }

        $deepest = find_deepest_category($categories);
        if ($deepest) {
            $deepest_cat = get_category($deepest);
            cli_log("  Recommended primary: {$deepest_cat->name} [ID: {$deepest}]");
        }

        $existing = get_post_meta($post_id, '_seopress_robots_primary_cat', true);
        if ($existing) {
            $existing_cat = get_category($existing);
            cli_log("  Current primary: {$existing_cat->name} [ID: {$existing}]");
        } else {
            cli_log("  Current primary: not set");
        }
    }
}

// Check command line arguments
if (isset($args) && !empty($args)) {
    $command = $args[0];

    switch ($command) {
        case 'test':
            $limit = isset($args[1]) ? intval($args[1]) : 10;
            test_primary_categories($limit);
            break;

        case 'update':
            bulk_update_primary_categories();
            break;

        default:
            cli_log("Available commands:");
            cli_log("  test [count] - Testing on a small sample");
            cli_log("  update - Массовое обновление всех posts");
            break;
    }
} else {
    // If no arguments, run bulk update
    bulk_update_primary_categories();
}
?>