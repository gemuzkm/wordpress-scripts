<?php

// Softly sort category archive posts by title in ascending order
function sort_category_by_title($query) {
    if ($query->is_category() && $query->is_main_query()) {
        $query->set('orderby', 'title');
        $query->set('order', 'ASC');
    }
}
add_action('pre_get_posts', 'sort_category_by_title');

?>