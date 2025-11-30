<?php
/**
 * Plugin Name: Relevanssi Redis Cache
 * Description: Caches Relevanssi search results in Redis for better performance
 * Version: 1.1
 * Author: Your Name
 */

if (!defined('ABSPATH')) {
    exit;
}

class Relevanssi_Redis_Cache {
    
    private $cache_group = 'relevanssi_search';
    private $cache_expiration = 86400; // 24 hours
    private $debug = true; // Включить для отладки
    
    public function __construct() {
        // Используем другой хук - более надёжный для Relevanssi Premium
        add_filter('relevanssi_results', array($this, 'check_and_cache_results'), 99, 1);
        
        // Очистка кеша при обновлении постов
        add_action('save_post', array($this, 'clear_cache'));
        add_action('deleted_post', array($this, 'clear_cache'));
    }
    
    /**
     * Проверяет кеш и сохраняет результаты
     */
    public function check_and_cache_results($hits) {
        global $wp_query;
        
        // Проверяем, что это поисковый запрос
        if (!is_search() || empty($wp_query->query_vars['s'])) {
            return $hits;
        }
        
        $cache_key = $this->generate_cache_key($wp_query);
        
        // Проверяем кеш
        $cached_results = wp_cache_get($cache_key, $this->cache_group);
        
        if ($cached_results !== false && is_array($cached_results)) {
            // Найдено в кеше
            $this->log("Cache HIT for key: $cache_key");
            return $cached_results['hits'];
        }
        
        // Кеша нет - сохраняем результаты
        if (!empty($hits)) {
            $cache_data = array(
                'hits' => $hits,
                'found_posts' => count($hits),
                'timestamp' => time()
            );
            
            $result = wp_cache_set($cache_key, $cache_data, $this->cache_group, $this->cache_expiration);
            
            $this->log("Cache MISS for key: $cache_key - Saved: " . ($result ? 'YES' : 'NO'));
        }
        
        return $hits;
    }
    
    /**
     * Генерация ключа кеша
     */
    private function generate_cache_key($query) {
        $key_parts = array();
        
        // Поисковый запрос
        if (!empty($query->query_vars['s'])) {
            $key_parts['s'] = sanitize_text_field($query->query_vars['s']);
        }
        
        // Тип поста
        if (!empty($query->query_vars['post_type'])) {
            $key_parts['post_type'] = $query->query_vars['post_type'];
        }
        
        // Постов на страницу
        if (!empty($query->query_vars['posts_per_page'])) {
            $key_parts['posts_per_page'] = $query->query_vars['posts_per_page'];
        }
        
        // Номер страницы
        if (!empty($query->query_vars['paged'])) {
            $key_parts['paged'] = $query->query_vars['paged'];
        }
        
        $cache_key = 'search_' . md5(serialize($key_parts));
        
        return $cache_key;
    }
    
    /**
     * Очистка кеша
     */
    public function clear_cache($post_id = null) {
        if (function_exists('wp_cache_flush_group')) {
            wp_cache_flush_group($this->cache_group);
            $this->log("Cache cleared: group flushed");
        } else {
            wp_cache_flush();
            $this->log("Cache cleared: full flush");
        }
    }
    
    /**
     * Логирование для отладки
     */
    private function log($message) {
        if ($this->debug && defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[Relevanssi Cache] ' . $message);
        }
    }
}

// Инициализация
if (function_exists('relevanssi_search') || function_exists('relevanssi_do_query')) {
    new Relevanssi_Redis_Cache();
}

/**
 * Добавить группу в persistent cache
 */
add_action('init', function() {
    if (function_exists('wp_cache_add_global_groups')) {
        wp_cache_add_global_groups(array('relevanssi_search'));
    }
}, 1);

/**
 * Кнопка очистки в админ-баре
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
 * Обработчик очистки
 */
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

/**
 * Страница статистики
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
            
            if (isset($_GET['cache_cleared'])) {
                echo '<div class="notice notice-success"><p>Search cache cleared successfully!</p></div>';
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
                echo '</tbody>';
                echo '</table>';
                
                echo '<p>&nbsp;</p>';
                echo '<h2>Search Cache Keys</h2>';
                
                // Попытка показать ключи поиска (если Redis CLI доступен)
                echo '<p>Check Redis for keys: de>redis-cli KEYS "wp:relevanssi_search:*"</code></p>';
                
                echo '<p><a href="' . wp_nonce_url(admin_url('admin-post.php?action=clear_relevanssi_cache'), 'clear_cache') . '" class="button button-primary">Clear Search Cache</a></p>';
            } else {
                echo '<div class="notice notice-error"><p>Statistics unavailable. Make sure Redis Object Cache is enabled.</p></div>';
            }
            
            echo '</div>';
        }
    );
});


