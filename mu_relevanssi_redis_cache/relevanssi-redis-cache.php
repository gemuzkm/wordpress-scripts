<?php
/**
 * Plugin Name: Relevanssi Redis Cache
 * Description: Caches Relevanssi search results in Redis for better performance
 * Version: 2.2
 * Author: Your Name
 * Requires Plugins: relevanssi-premium
 */

if (!defined('ABSPATH')) {
    exit;
}

// Функция логирования
function relevanssi_cache_log($message) {
    $log_file = WP_CONTENT_DIR . '/relevanssi-cache-debug.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND);
}

relevanssi_cache_log('=== Plugin loaded v2.2 ===');

class Relevanssi_Redis_Cache {
    
    private $cache_group = 'relevanssi_search';
    private $cache_expiration = 86400; // 24 hours
    
    public function __construct() {
        relevanssi_cache_log('Constructor called');
        
        // Перехватываем поиск
        add_filter('posts_pre_query', array($this, 'intercept_search'), 10, 2);
        
        // Кешируем после поиска
        add_action('wp', array($this, 'cache_after_search'), 999);
        
        // Очистка кеша
        add_action('save_post', array($this, 'clear_cache_on_save'), 10, 2);
        
        relevanssi_cache_log('Hooks registered');
    }
    
    public function intercept_search($posts, $query) {
        if (!$query->is_search() || !$query->is_main_query()) {
            return $posts;
        }
        
        $search_query = $query->get('s');
        if (empty($search_query)) {
            return $posts;
        }
        
        relevanssi_cache_log('Search detected: ' . $search_query);
        
        $cache_key = $this->generate_cache_key($query);
        relevanssi_cache_log('Cache key: ' . $cache_key);
        
        $cached_data = wp_cache_get($cache_key, $this->cache_group);
        
        if ($cached_data !== false && is_array($cached_data)) {
            relevanssi_cache_log('CACHE HIT! Returning ' . count($cached_data['posts']) . ' posts');
            
            $query->found_posts = $cached_data['found_posts'];
            $query->max_num_pages = $cached_data['max_num_pages'];
            
            return $cached_data['posts'];
        }
        
        relevanssi_cache_log('CACHE MISS - proceeding with Relevanssi');
        
        $query->relevanssi_cache_key = $cache_key;
        
        return null;
    }
    
    public function cache_after_search() {
        global $wp_query;
        
        if (!$wp_query->is_search() || !isset($wp_query->relevanssi_cache_key)) {
            return;
        }
        
        $cache_key = $wp_query->relevanssi_cache_key;
        
        if (empty($wp_query->posts)) {
            relevanssi_cache_log('No posts to cache');
            return;
        }
        
        $cache_data = array(
            'posts' => $wp_query->posts,
            'found_posts' => $wp_query->found_posts,
            'max_num_pages' => $wp_query->max_num_pages,
            'timestamp' => time()
        );
        
        $result = wp_cache_set($cache_key, $cache_data, $this->cache_group, $this->cache_expiration);
        
        relevanssi_cache_log('Cached: ' . ($result ? 'SUCCESS' : 'FAILED') . ' (' . count($wp_query->posts) . ' posts)');
    }
    
    private function generate_cache_key($query) {
        $key_parts = array();
        
        if ($s = $query->get('s')) {
            $key_parts['s'] = sanitize_text_field($s);
        }
        
        if ($post_type = $query->get('post_type')) {
            $key_parts['post_type'] = $post_type;
        }
        
        if ($posts_per_page = $query->get('posts_per_page')) {
            $key_parts['posts_per_page'] = $posts_per_page;
        }
        
        if ($paged = $query->get('paged')) {
            $key_parts['paged'] = $paged;
        }
        
        return 'search_' . md5(serialize($key_parts));
    }
    
    public function clear_cache_on_save($post_id, $post) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (in_array($post->post_type, array('seopress_404', 'revision'))) {
            return;
        }
        
        relevanssi_cache_log('Clearing cache for post: ' . $post_id);
        
        if (function_exists('wp_cache_flush_group')) {
            wp_cache_flush_group($this->cache_group);
        } else {
            wp_cache_flush();
        }
    }
}

// Инициализация - теперь Relevanssi уже загружен
new Relevanssi_Redis_Cache();
relevanssi_cache_log('Cache plugin initialized');

// Добавить в persistent cache
add_action('init', function() {
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
                echo '<div class="notice notice-error"><p>Redis not active</p></div>';
            }
            
            echo '</div>';
        }
    );
});
