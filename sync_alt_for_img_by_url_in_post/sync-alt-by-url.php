<?php
/**
 * Alt Text Synchronization by Image URL
 * Finds attachment ID by URL and applies alt text from media library
 * With detailed logging
 */

function sync_image_alt_by_url_with_logs() {
    // Create log file
    $log_file = WP_CONTENT_DIR . '/sync-alt-by-url-' . date('Y-m-d-H-i-s') . '.txt';
    
    function write_log($message, $log_file) {
        $timestamp = '[' . date('Y-m-d H:i:s') . '] ';
        file_put_contents($log_file, $timestamp . $message . "\n", FILE_APPEND);
        echo $timestamp . $message . "\n";
    }
    
    write_log("=== START ALT TEXT SYNCHRONIZATION ===", $log_file);
    write_log("Log file: " . $log_file, $log_file);
    
    $args = array(
        'post_type'      => array('post', 'page'),
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids'
    );
    
    $post_ids = get_posts($args);
    write_log("Found posts to process: " . count($post_ids), $log_file);
    
    $updated_count = 0;
    $processed_posts = 0;
    $total_images_updated = 0;
    $total_images_skipped = 0;
    $url_to_attachment_cache = array(); // Cache for faster lookups
    
    /**
     * Function to find attachment ID by image URL
     */
    function get_attachment_id_by_url($url, &$cache, $log_file) {
        // Check cache first
        if (isset($cache[$url])) {
            return $cache[$url];
        }
        
        global $wpdb;
        
        // Get base upload URL
        $upload_dir = wp_upload_dir();
        $base_url = $upload_dir['baseurl'];
        
        // Normalize URL (remove protocol for search)
        $url = str_replace(array('https://', 'http://'), '', $url);
        $base_url = str_replace(array('https://', 'http://'), '', $base_url);
        
        write_log("    🔍 Searching attachment by URL: $url", $log_file);
        
        // Method 1: Search by meta (for wp-image classes)
        $attachment_id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM $wpdb->postmeta 
             WHERE meta_key = '_wp_attached_file' 
             AND meta_value LIKE %s 
             LIMIT 1",
            '%' . $wpdb->esc_like(basename($url)) . '%'
        ));
        
        if ($attachment_id) {
            write_log("    ✅ Found by _wp_attached_file: ID $attachment_id", $log_file);
            $cache[$url] = $attachment_id;
            return $attachment_id;
        }
        
        // Method 2: Search in posts table (guid)
        $attachment_id = $wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM $wpdb->posts 
             WHERE guid LIKE %s 
             AND post_type = 'attachment' 
             LIMIT 1",
            '%' . $wpdb->esc_like(basename($url)) . '%'
        ));
        
        if ($attachment_id) {
            write_log("    ✅ Found by guid: ID $attachment_id", $log_file);
            $cache[$url] = $attachment_id;
            return $attachment_id;
        }
        
        // Method 3: Search by relative path
        if (strpos($url, $base_url) !== false) {
            $relative_url = str_replace($base_url, '', $url);
            $attachment_id = attachment_url_to_postid($url);
            
            if ($attachment_id) {
                write_log("    ✅ Found by attachment_url_to_postid: ID $attachment_id", $log_file);
                $cache[$url] = $attachment_id;
                return $attachment_id;
            }
        }
        
        write_log("    ❌ Attachment not found in media library", $log_file);
        $cache[$url] = false;
        return false;
    }
    
    foreach ($post_ids as $post_id) {
        $post = get_post($post_id);
        $content = $post->post_content;
        $updated = false;
        $post_images_updated = 0;
        
        write_log("", $log_file);
        write_log("--- PROCESSING POST ID: $post_id | TITLE: " . $post->post_title . " ---", $log_file);
        
        // Find all images (with URLs)
        preg_match_all('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $content, $img_matches);
        
        write_log("Found <img> tags: " . count($img_matches[0]), $log_file);
        
        if (!empty($img_matches[0])) {
            foreach ($img_matches[0] as $index => $img_tag) {
                $img_url = $img_matches[1][$index];
                
                write_log("  [IMG #" . ($index + 1) . "] URL: $img_url", $log_file);
                
                // Get attachment ID by URL
                $attachment_id = get_attachment_id_by_url($img_url, $url_to_attachment_cache, $log_file);
                
                if (!$attachment_id) {
                    write_log("    ❌ SKIPPED: Could not find attachment ID by URL", $log_file);
                    $total_images_skipped++;
                    continue;
                }
                
                // Get image information from media library
                $attachment = get_post($attachment_id);
                $media_alt = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
                
                if ($attachment) {
                    write_log("    📎 File name: " . $attachment->post_title, $log_file);
                } else {
                    write_log("    ⚠️  ERROR: Attachment with ID $attachment_id not found!", $log_file);
                    $total_images_skipped++;
                    continue;
                }
                
                // If alt text in media library is empty, skip
                if (empty($media_alt)) {
                    write_log("    ❌ SKIPPED: Alt text in media library is empty", $log_file);
                    $total_images_skipped++;
                    continue;
                }
                
                write_log("    Alt in media library: \"$media_alt\"", $log_file);
                
                // Check current alt in image
                if (preg_match('/alt=["\']([^"\']*)["\']/', $img_tag, $alt_match)) {
                    $current_alt = $alt_match[1];
                    write_log("    Current alt in post: \"$current_alt\"", $log_file);
                    
                    // Update if alt is empty or different
                    if (empty($current_alt)) {
                        write_log("    ✅ UPDATING: empty alt → \"$media_alt\"", $log_file);
                        write_log("    WAS: " . substr($img_tag, 0, 120) . "...", $log_file);
                        
                        $new_img_tag = preg_replace(
                            '/alt=["\'][^"\']*["\']/',
                            'alt="' . esc_attr($media_alt) . '"',
                            $img_tag
                        );
                        write_log("    NOW: " . substr($new_img_tag, 0, 120) . "...", $log_file);
                        
                        $content = str_replace($img_tag, $new_img_tag, $content);
                        $updated = true;
                        $post_images_updated++;
                        $total_images_updated++;
                    } elseif ($current_alt !== $media_alt) {
                        write_log("    ✅ UPDATING: \"$current_alt\" → \"$media_alt\"", $log_file);
                        write_log("    WAS: " . substr($img_tag, 0, 120) . "...", $log_file);
                        
                        $new_img_tag = preg_replace(
                            '/alt=["\'][^"\']*["\']/',
                            'alt="' . esc_attr($media_alt) . '"',
                            $img_tag
                        );
                        write_log("    NOW: " . substr($new_img_tag, 0, 120) . "...", $log_file);
                        
                        $content = str_replace($img_tag, $new_img_tag, $content);
                        $updated = true;
                        $post_images_updated++;
                        $total_images_updated++;
                    } else {
                        write_log("    ℹ️  SKIPPED: Alt already matches media library", $log_file);
                        $total_images_skipped++;
                    }
                } else {
                    write_log("    ⚠️  Alt attribute missing in tag", $log_file);
                    write_log("    ✅ ADDING: alt=\"$media_alt\"", $log_file);
                    write_log("    WAS: " . substr($img_tag, 0, 120) . "...", $log_file);
                    
                    $new_img_tag = str_replace('<img', '<img alt="' . esc_attr($media_alt) . '"', $img_tag);
                    write_log("    NOW: " . substr($new_img_tag, 0, 120) . "...", $log_file);
                    
                    $content = str_replace($img_tag, $new_img_tag, $content);
                    $updated = true;
                    $post_images_updated++;
                    $total_images_updated++;
                }
            }
        }
        
        // Save updated post content
        if ($updated) {
            $update_result = wp_update_post(array(
                'ID'           => $post_id,
                'post_content' => $content
            ));
            
            if (!is_wp_error($update_result)) {
                write_log("✅ POST SAVED: Updated images in this post: $post_images_updated", $log_file);
                $updated_count++;
            } else {
                write_log("❌ SAVE ERROR: " . $update_result->get_error_message(), $log_file);
            }
        } else {
            if (!empty($img_matches[0])) {
                write_log("ℹ️  POST UNCHANGED: No images requiring updates", $log_file);
            } else {
                write_log("ℹ️  POST SKIPPED: No images found", $log_file);
            }
        }
        
        $processed_posts++;
        
        // Show progress every 5 posts
        if ($processed_posts % 5 == 0) {
            write_log("", $log_file);
            write_log("📊 PROGRESS: Posts processed: $processed_posts | Posts updated: $updated_count | Total images updated: $total_images_updated", $log_file);
            write_log("", $log_file);
            
            if (ob_get_level() > 0) {
                ob_flush();
                flush();
            }
        }
    }
    
    // Final report
    write_log("", $log_file);
    write_log("=== SYNCHRONIZATION COMPLETED ===", $log_file);
    write_log("📊 SUMMARY STATISTICS:", $log_file);
    write_log("  • Total posts processed: $processed_posts", $log_file);
    write_log("  • Posts with updated images: $updated_count", $log_file);
    write_log("  • Total images updated: $total_images_updated", $log_file);
    write_log("  • Images skipped: $total_images_skipped", $log_file);
    write_log("  • URL→ID cache size: " . count($url_to_attachment_cache), $log_file);
    write_log("  • Log saved to: $log_file", $log_file);
    write_log("=== END ===", $log_file);
    
    echo "\n\n📝 Full log saved to: $log_file\n";
}

// Run via admin URL
add_action('admin_init', 'maybe_sync_alt_by_url');
function maybe_sync_alt_by_url() {
    if (isset($_GET['sync_alt_by_url']) && current_user_can('manage_options')) {
        sync_image_alt_by_url_with_logs();
        exit;
    }
}

// WP-CLI command
if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('sync-alt-by-url', function() {
        WP_CLI::line('Starting alt text synchronization by URL...');
        sync_image_alt_by_url_with_logs();
    });
}
?>
