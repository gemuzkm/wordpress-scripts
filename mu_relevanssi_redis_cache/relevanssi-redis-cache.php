<?php
/**
 * Plugin Name: Relevanssi Redis Cache
 * Description: Caches Relevanssi search results in Redis
 * Version: 1.4
 * Author: Your Name
 */

if (!defined('ABSPATH')) {
    exit;
}

// Функция для прямой записи в лог
function relevanssi_cache_log($message) {
    $log_file = WP_CONTENT_DIR . '/relevanssi-cache-debug.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND);
}

relevanssi_cache_log('Plugin file loaded');

class Relevanssi_Redis_Cache {
    
    private $cache_group = 'relevanssi_search';
    private $cache_expiration = 86400;
    
    public function __construct() {
        relevanssi_cache_log('Class constructor called');
        
        add_filter('relevanssi_hits_filter', array($this, 'cache_results'), 99, 2);
        add_filter('relevanssi_results', array($this, 'cache_results_simple'), 99, 1);
        
        add_action('save_post', array($this, 'clear_cache'));
        add_action('deleted_post', array($this, 'clear_cache'));
        
        relevanssi_cache_log('Hooks registered');
    }
    
    public function cache_results($hits, $query_args) {
        relevanssi_cache_log('relevanssi_hits_filter fired with ' . count($hits) . ' results');
        
        if (empty($query_args) || !isset($query_args['s'])) {
            relevanssi_cache_log('No search query in args');
            return $hits;
        }
        
        $cache_key = $this->generate_cache_key($query_args);
        relevanssi_cache_log('Cache key: ' . $cache_key);
        
        $cached = wp_cache_get($cache_key, $this->cache_group);
        
        if ($cached !== false && is_array($cached)) {
            relevanssi_cache_log('CACHE HIT: ' . $cache_key);
            return $cached;
        }
        
        if (!empty($hits)) {
            $result = wp_cache_set($cache_key, $hits, $this->cache_group, $this->cache_expiration);
            relevanssi_cache_log('CACHE MISS: ' . $cache_key . ' - Saved: ' . ($result ? 'YES' : 'NO') . ' (' . count($hits) . ' results)');
        }
        
        return $hits;
    }
    
    public function cache_results_simple($hits) {
        global $wp_query;
        
        relevanssi_cache_log('relevanssi_results fired with ' . count($hits) . ' results');
        
        if (!isset($wp_query->query_vars['s']) || empty($wp_query->query_vars['s'])) {
            relevanssi_cache_log('No search query in wp_query');
            return $hits;
        }
        
        $cache_key = $this->generate_cache_key_from_wp_query($wp_query);
        relevanssi_cache_log('Cache key (simple): ' . $cache_key);
        
        $cached = wp_cache_get($cache_key, $this->cache_group);
        
        if ($cached !== false && is_array($cached)) {
            relevanssi_cache_log('CACHE HIT (simple): ' . $cache_key);
            return $cached;
        }
        
        if (!empty($hits)) {
            $result = wp_cache_set($cache_key, $hits, $this->cache_group, $this->cache_expiration);
            relevanssi_cache_log('CACHE MISS (simple): ' . $cache_key . ' - Saved: ' . ($result ? 'YES' : 'NO'));
        }
        
        return $hits;
    }
    
    private function generate_cache_key($query_args) {
        $key_parts = array();
        
        if (isset($query_args['s'])) {
            $key_parts['s'] = sanitize_text_field($query_args['s']);
        }
        
        if (isset($query_args['post_type'])) {
            $key_parts['post_type'] = $query_args['post_type'];
        }
        
        if (isset($query_args['posts_per_page'])) {
            $key_parts['posts_per_page'] = $query_args['posts_per_page'];
        }
        
        if (isset($query_args['paged'])) {
            $key_parts['paged'] = $query_args['paged'];
        }
        
        return 'search_' . md5(serialize($key_parts));
    }
    
    private function generate_cache_key_from_wp_query($query) {
        $key_parts = array();
        
        if (isset($query->query_vars['s'])) {
            $key_parts['s'] = sanitize_text_field($query->query_vars['s']);
        }
        
        if (isset($query->query_vars['post_type'])) {
            $key_parts['post_type'] = $query->query_vars['post_type'];
        }
        
        if (isset($query->query_vars['posts_per_page'])) {
            $key_parts['posts_per_page'] = $query->query_vars['posts_per_page'];
        }
        
        if (isset($query->query_vars['paged'])) {
            $key_parts['paged'] = $query->query_vars['paged'];
        }
        
        return 'search_' . md5(serialize($key_parts));
    }
    
    public function clear_cache($post_id = null) {
        relevanssi_cache_log('clear_cache called');
        
        if (function_exists('wp_cache_flush_group')) {
            wp_cache_flush_group($this->cache_group);
        } else {
            wp_cache_flush();
        }
    }
}

// Проверка, что Relevanssi активен
if (function_exists('relevanssi_search') || function_exists('relevanssi_do_query')) {
    new Relevanssi_Redis_Cache();
    relevanssi_cache_log('Relevanssi Cache initialized - Relevanssi is active');
} else {
    relevanssi_cache_log('ERROR: Relevanssi not found!');
}

// Добавить группу в persistent cache
add_action('init', function() {
    relevanssi_cache_log('init action fired');
    
    if (function_exists('wp_cache_add_global_groups')) {
        wp_cache_add_global_groups(array('relevanssi_search'));
        relevanssi_cache_log('Global cache group added');
    }
}, 1);

// Админ-бар
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

add_action('admin_post_clear_relevanssi_cache', function() {
    check_admin_referer('clear_cache');
    
    if (function_exists('wp_cache_flush_group')) {
        wp_cache_flush_group('relevanssi_search');
    } else {
        wp_cache_flush();
    }
    
    wp_redirect(add_query_arg('cache_cleared', '1', wp_get_referer()));
    exit;
});

// Статистика
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
            
            if (isset($_GET['cache_cleared'])) {
                echo '<div class="notice notice-success"><p>Cache cleared!</p></div>';
            }
            
            if (isset($wp_object_cache->cache_hits) && isset($wp_object_cache->cache_misses)) {
                $total = $wp_object_cache->cache_hits + $wp_object_cache->cache_misses;
                $hit_rate = $total > 0 ? round(($wp_object_cache->cache_hits / $total) * 100, 2) : 0;
                
                echo '<table class="widefat striped">';
                echo '<thead><tr><th>Metric</th><th>Value</th></tr></thead>';
                echo '<tbody>';
                echo '<tr><td>Cache Hits</td><td>' . number_format($wp_object_cache->cache_hits) . '</td></tr>';
                echo '<tr><td>Cache Misses</td><td>' . number_format($wp_object_cache->cache_misses) . '</td></tr>';
                echo '<tr><td>Hit Rate</td><td>' . $hit_rate . '%</td></tr>';
                echo '</tbody></table>';
                
                echo '<p><a href="' . wp_nonce_url(admin_url('admin-post.php?action=clear_relevanssi_cache'), 'clear_cache') . '" class="button button-primary">Clear Search Cache</a></p>';
            } else {
                echo '<div class="notice notice-error"><p>Redis Object Cache not active</p></div>';
            }
            
            echo '</div>';
        }
    );
});
