<?php

// Exclude categor by result
function mysearchexclude($query) {
        if ($query->is_search && !is_admin ()) {
                $query->set('category__not_in', 413);
        }
        return $query;
}
add_filter('pre_get_posts','mysearchexclude');

?>