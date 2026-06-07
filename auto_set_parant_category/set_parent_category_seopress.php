/**
 * Автоматически устанавливает основную категорию SEOPress
 * при сохранении/обновлении поста
 */
add_action('save_post', 'auto_set_seopress_primary_category', 20, 2);

function auto_set_seopress_primary_category($post_id, $post) {
    // Защита от автосохранений, ревизий и не-постов
    if (
        defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ||
        wp_is_post_revision($post_id) ||
        $post->post_type !== 'post' ||
        $post->post_status !== 'publish'
    ) {
        return;
    }

    $categories = wp_get_post_categories($post_id);

    if (empty($categories)) {
        return;
    }

    $deepest_cat_id = null;
    $max_level = -1;

    foreach ($categories as $cat_id) {
        $level = 0;
        $current = get_category($cat_id);

        while ($current && !is_wp_error($current) && $current->parent != 0) {
            $level++;
            $current = get_category($current->parent);
        }

        if ($level > $max_level) {
            $max_level = $level;
            $deepest_cat_id = $cat_id;
        }
    }

    if (!$deepest_cat_id) {
        return;
    }

    // Не перезаписываем, если уже стоит правильная категория
    $existing = get_post_meta($post_id, '_seopress_robots_primary_cat', true);
    if ($existing == $deepest_cat_id) {
        return;
    }

    update_post_meta($post_id, '_seopress_robots_primary_cat', $deepest_cat_id);
}