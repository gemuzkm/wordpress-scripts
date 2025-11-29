// Добавить в functions.php или Code Snippets
function optimize_action_scheduler_logs() {
    // Хранить логи только 3 дня
    add_filter('action_scheduler_retention_period', function($period) {
        return 3 * DAY_IN_SECONDS;
    });
    
    // Увеличить размер пакета очистки
    add_filter('action_scheduler_cleanup_batch_size', function($batch_size) {
        return 200;
    });
    
    // Очищать каждые 30 минут
    add_filter('action_scheduler_cleanup_interval', function($interval) {
        return 30 * MINUTE_IN_SECONDS;
    });
    
    // Очищать все статусы кроме pending/in-progress  
    add_filter('action_scheduler_default_cleaner_statuses', function($statuses) {
        return ['complete', 'failed', 'canceled'];
    });
}
add_action('init', 'optimize_action_scheduler_logs');