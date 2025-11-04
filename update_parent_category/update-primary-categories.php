<?php
/**
 * Скрипт для массового назначения основной категории (Primary Category) в SEOPress
 * Для использования с WP-CLI: wp eval-file update-primary-categories.php
 */

// Функция для безопасного вывода в консоль
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

// Функция для определения самой глубокой категории
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

        // Находим самую глубокую категорию
        if ($level > $max_level) {
            $max_level = $level;
            $deepest_category = $cat_id;
        }
    }

    return $deepest_category;
}

// Основная функция обновления
function bulk_update_primary_categories() {
    global $wpdb;

    // Параметры обработки
    $batch_size = 100;
    $offset = 0;
    $processed_count = 0;
    $updated_count = 0;
    $skipped_count = 0;

    cli_log("Начинаем массовое обновление основных категорий...");
    cli_log("Размер порции: " . $batch_size);

    // Получаем общее количество постов для прогресса
    $total_posts = wp_count_posts('post');
    $total_published = $total_posts->publish;
    cli_log("Всего опубликованных постов: " . $total_published);

    do {
        // Получаем порцию постов
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

        cli_log("Обрабатываем посты " . ($offset + 1) . " - " . ($offset + count($posts)) . " из " . $total_published);

        foreach ($posts as $post_id) {
            $processed_count++;

            // Получаем категории поста
            $categories = wp_get_post_categories($post_id);

            if (empty($categories)) {
                cli_log("  Пост {$post_id}: пропущен (нет категорий)");
                $skipped_count++;
                continue;
            }

            // Проверяем, уже ли установлена основная категория
            $existing_primary = get_post_meta($post_id, '_seopress_robots_primary_cat', true);

            // Находим самую глубокую категорию
            $deepest_category = find_deepest_category($categories);

            if (!$deepest_category) {
                cli_log("  Пост {$post_id}: пропущен (не удалось определить глубокую категорию)");
                $skipped_count++;
                continue;
            }

            // Если уже есть основная категория и она совпадает с найденной
            if ($existing_primary && $existing_primary == $deepest_category) {
                cli_log("  Пост {$post_id}: пропущен (уже установлена правильная основная категория {$deepest_category})");
                $skipped_count++;
                continue;
            }

            // Обновляем основную категорию
            $result = update_post_meta($post_id, '_seopress_robots_primary_cat', $deepest_category);

            if ($result) {
                $category_name = get_category($deepest_category)->name;
                cli_log("  Пост {$post_id}: обновлен (основная категория: {$category_name} [ID: {$deepest_category}])");
                $updated_count++;
            } else {
                cli_warning("  Пост {$post_id}: ошибка при обновлении");
            }
        }

        $offset += $batch_size;

        // Очистка памяти каждые 100 постов
        wp_cache_flush();

        // Показываем прогресс
        $progress = round(($processed_count / $total_published) * 100, 1);
        cli_log("Прогресс: {$progress}% ({$processed_count}/{$total_published})");

        // Небольшая пауза для снижения нагрузки на сервер
        if (function_exists('sleep')) {
            sleep(1);
        }

    } while (count($posts) == $batch_size);

    // Финальная статистика
    cli_success("=== ЗАВЕРШЕНО ===");
    cli_success("Всего обработано постов: " . $processed_count);
    cli_success("Обновлено: " . $updated_count);
    cli_success("Пропущено: " . $skipped_count);
    cli_success("=================");
}

// Функция для тестирования на небольшой выборке
function test_primary_categories($limit = 10) {
    cli_log("Тестовый режим: обработка {$limit} постов");

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
            cli_log("  Категорий нет");
            continue;
        }

        cli_log("  Категории:");
        foreach ($categories as $cat_id) {
            $cat = get_category($cat_id);
            $level = 0;
            $current_cat = $cat;

            while ($current_cat->parent != 0) {
                $level++;
                $current_cat = get_category($current_cat->parent);
            }

            $indent = str_repeat("    ", $level);
            cli_log("    {$indent}- {$cat->name} [ID: {$cat_id}, Уровень: {$level}]");
        }

        $deepest = find_deepest_category($categories);
        if ($deepest) {
            $deepest_cat = get_category($deepest);
            cli_log("  Рекомендуемая основная: {$deepest_cat->name} [ID: {$deepest}]");
        }

        $existing = get_post_meta($post_id, '_seopress_robots_primary_cat', true);
        if ($existing) {
            $existing_cat = get_category($existing);
            cli_log("  Текущая основная: {$existing_cat->name} [ID: {$existing}]");
        } else {
            cli_log("  Текущая основная: не установлена");
        }
    }
}

// Проверяем аргументы командной строки
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
            cli_log("Доступные команды:");
            cli_log("  test [количество] - Тестирование на небольшой выборке");
            cli_log("  update - Массовое обновление всех постов");
            break;
    }
} else {
    // Если нет аргументов, запускаем массовое обновление
    bulk_update_primary_categories();
}
?>