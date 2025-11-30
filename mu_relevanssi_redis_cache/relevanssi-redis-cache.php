<?php
/**
 * Plugin Name: Relevanssi Redis Cache
 * Description: Caches Relevanssi search results in Redis for better performance
 * Version: 1.0
 * Author: Your Name
 */

if (!defined('ABSPATH')) {
    exit;
}

class Relevanssi_Redis_Cache {
    
    private $cache_group = 'relevanssi_search';
    private $cache_expiration = 86400; // 24 hour (in seconds)
    
    public function __construct() {
        // Check cache BEFORE executing search
        add_filter('relevanssi_search_ok', array($this, 'check_cache'), 10, 2);
        
        // Save results AFTER executing search
        add_action('relevanssi_hits_after', array($this, 'save_to_cache'), 10, 2);
        
        // Clear cache when posts are updated
        add_action('save_post', array($this, 'clear_cache'));
        add_action('relevanssi_insert_edit', array($this, 'clear_cache'));
    }
    
    /**
     * Check if results exist in cache
     */
    public function check_cache($search_ok, $query) {
        // Generate unique cache key based on query parameters
        $cache_key = $this->generate_cache_key($query);
        
        // Try to get from cache
        $cached_results = wp_cache_get($cache_key, $this->cache_group);
        
        if ($cached_results !== false && is_array($cached_results)) {
            // Restore results from cache
            $query->posts = $cached_results['posts'];
            $query->found_posts = $cached_results['found_posts'];
            $query->post_count = $cached_results['post_count'];
            
            // Set flag that results are from cache
            $query->from_cache = true;
            
            // Return false to stop search execution
            return false;
        }
        
        // Continue with normal search
        return $search_ok;
    }
    
    /**
     * Save results to cache
     */
    public function save_to_cache($hits, $query) {
        // Don't cache if results are already from cache
        if (isset($query->from_cache) && $query->from_cache) {
            return $hits;
        }
        
        // Generate cache key
        $cache_key = $this->generate_cache_key($query);
        
        // Prepare data for caching
        $cache_data = array(
            'posts' => $hits,
            'found_posts' => isset($query->found_posts) ? $query->found_posts : count($hits),
            'post_count' => count($hits),
            'timestamp' => time()
        );
        
        // Save to Redis via WordPress Object Cache
        wp_cache_set($cache_key, $cache_data, $this->cache_group, $this->cache_expiration);
        
        return $hits;
    }
    
    /**
     * Generate unique cache key based on query parameters
     */
    private function generate_cache_key($query) {
        $key_parts = array();
        
        // Search query
        if (!empty($query->query_vars['s'])) {
            $key_parts['s'] = sanitize_text_field($query->query_vars['s']);
        }
        
        // Post type
        if (!empty($query->query_vars['post_type'])) {
            $key_parts['post_type'] = $query->query_vars['post_type'];
        }
        
        // Posts per page
        if (!empty($query->query_vars['posts_per_page'])) {
            $key_parts['posts_per_page'] = $query->query_vars['posts_per_page'];
        }
        
        // Page number (pagination)
        if (!empty($query->query_vars['paged'])) {
            $key_parts['paged'] = $query->query_vars['paged'];
        }
        
        // Categories
        if (!empty($query->query_vars['cat'])) {
            $key_parts['cat'] = $query->query_vars['cat'];
        }
        
        // Taxonomies
        if (!empty($query->query_vars['tax_query'])) {
            $key_parts['tax_query'] = serialize($query->query_vars['tax_query']);
        }
        
        // Create hash from all parameters
        $cache_key = 'search_' . md5(serialize($key_parts));
        
        return $cache_key;
    }
    
    /**
     * Clear all search cache
     */
    public function clear_cache($post_id = null) {
        // Redis Object Cache supports group flushing
        if (function_exists('wp_cache_flush_group')) {
            wp_cache_flush_group($this->cache_group);
        } else {
            // If function unavailable, use flush runtime (less efficient)
            wp_cache_flush();
        }
    }
    
    /**
     * Get cache statistics
     */
    public function get_cache_stats() {
        // This function for debugging - can be called via wp-admin
        global $wp_object_cache;
        
        if (isset($wp_object_cache->cache_hits) && isset($wp_object_cache->cache_misses)) {
            return array(
                'hits' => $wp_object_cache->cache_hits,
                'misses' => $wp_object_cache->cache_misses,
                'hit_rate' => round(($wp_object_cache->cache_hits / ($wp_object_cache->cache_hits + $wp_object_cache->cache_misses)) * 100, 2)
            );
        }
        
        return null;
    }
}

// Initialize only if Relevanssi is active
if (function_exists('relevanssi_search')) {
    new Relevanssi_Redis_Cache();
}

/**
 * Add admin bar button to clear cache
 */
add_action('admin_bar_menu', function($wp_admin_bar) {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    $wp_admin_bar->add_node(array(
        'id' => 'clear-relevanssi-cache',
        'title' => 'Clear Search Cache',
        'href' => wp_nonce_url(admin_url('admin-post.php?action=clear_relevanssi_cache'), 'clear_cache'),
    ));
}, 999);

/**
 * Handle cache clearing
 */
add_action('admin_post_clear_relevanssi_cache', function() {
    check_admin_referer('clear_cache');
    
    // Clear cache via WordPress Object Cache
    if (function_exists('wp_cache_flush_group')) {
        wp_cache_flush_group('relevanssi_search');
    } else {
        wp_cache_flush();
    }
    
    // Add success notice
    add_settings_error(
        'relevanssi_cache',
        'cache_cleared',
        'Search cache cleared successfully',
        'success'
    );
    
    wp_redirect(wp_get_referer());
    exit;
});

/**
 * Add statistics page in admin
 */
add_action('admin_menu', function() {
    add_submenu_page(
        'options-general.php',
        'Search Cache Statistics',
        'Search Cache',
        'manage_options',
        'relevanssi-cache-stats',
        function() {
            global $wp_object_cache;
            
            echo '<div class="wrap">';
            echo '<h1>Redis Cache Statistics</h1>';
            
            if (isset($wp_object_cache->cache_hits) && isset($wp_object_cache->cache_misses)) {
                $total = $wp_object_cache->cache_hits + $wp_object_cache->cache_misses;
                $hit_rate = $total > 0 ? round(($wp_object_cache->cache_hits / $total) * 100, 2) : 0;
                
                echo '<table class="widefat">';
                echo '<tr><th>Metric</th><th>Value</th></tr>';
                echo '<tr><td>Cache Hits</td><td>' . number_format($wp_object_cache->cache_hits) . '</td></tr>';
                echo '<tr><td>Cache Misses</td><td>' . number_format($wp_object_cache->cache_misses) . '</td></tr>';
                echo '<tr><td>Hit Rate</td><td>' . $hit_rate . '%</td></tr>';
                echo '</table>';
                
                echo '<p><a href="' . wp_nonce_url(admin_url('admin-post.php?action=clear_relevanssi_cache'), 'clear_cache') . '" class="button">Clear Search Cache</a></p>';
            } else {
                echo '<p>Statistics unavailable. Make sure Redis Object Cache is enabled.</p>';
            }
            
            echo '</div>';
        }
    );
});

/**
 * Add custom cache groups to Redis persistent cache
 */
add_action('plugins_loaded', function() {
    if (function_exists('wp_cache_add_global_groups')) {
        wp_cache_add_global_groups('relevanssi_search');
    }
    
    if (function_exists('wp_cache_add_non_persistent_groups')) {
        // Убедитесь, что relevanssi_search НЕ в non-persistent
        $non_persistent = array('counts', 'plugins');
        wp_cache_add_non_persistent_groups($non_persistent);
    }
}, 1);
