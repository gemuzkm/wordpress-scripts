
<?php
/**
 * Custom SEO Press Category Titles with Pagination
 * This code customizes the SEO titles for category pages based on their hierarchy
 * and includes pagination information if applicable.
 */

add_filter('seopress_titles_title', 'custom_seopress_category_title_with_pagination', 10, 1);

function custom_seopress_category_title_with_pagination($title) {
    if (!is_category()) {
        return $title;
    }

    $category   = get_queried_object();
    if (!$category || is_wp_error($category)) {
        return $title;
    }

    $cat_name           = $category->name;
    $parent_id          = $category->parent;
    $current_id         = $category->term_id;
    $site_name          = get_bloginfo('name');

    // Замените на реальные ID
    $vag_sap_parent_id  = 176; // ID категории "vag sap"
    $manual_parent_id   = 25026; // ID категории "manual"
    $body_parent_id     = 185; // ID категории "body"
    $car_fuse_box_parent_id     = 30770; // ID категории "car fuse box"
    $dtc_fault_codes_parent_id     = 30653; // ID категории "dtc fault codes"
    $engine_parent_id     = 184; // ID категории "engine"
    $guides_and_tutorials_parent_id     = 28122; // ID категории "guides & tutorials"
    $transmission_parent_id     = 182; // ID категории "transmission"
    $wiring_parent_id     = 183; // ID категории "wiring"
    $owner_car_parent_id     = 26037; // ID категории "owner car"
    $owner_motocycle_parent_id     = 26045; // ID категории "owner motocycle"
    $owner_truck_parent_id     = 28934; // ID категории "owner truck"
    $pdf_online_parent_id     = 413; // ID категории "pdf online"

    // Получаем всех предков текущей категории
    $ancestors = get_ancestors($current_id, 'category');

    // Пагинация
    $paged      = max(1, get_query_var('paged'));
    $max_pages  = intval($GLOBALS['wp_query']->max_num_pages);
    $page_text  = '';
    if ($paged > 1 && $max_pages > 1) {
        $page_text = ' | Page ' . $paged . ' of ' . $max_pages;
    }

    // Сначала проверяем корневые категории
    if (!$parent_id) {
        // Корневые категории (нет родителя)
        if ($current_id === $vag_sap_parent_id) {
            $title = 'Free VAG SSP Self Study Programs - Technical Training PDFs | ProCarManuals' . $page_text;
        } elseif ($current_id === $manual_parent_id) {
            $title = '5000+ Free Car Service Manuals & Workshop Guides - Instant Download | ProCarManuals' . $page_text;
        } elseif ($current_id === $body_parent_id) {
            $title = 'Free Auto Body Repair Manuals & Collision Guides - PDF Download | ProCarManuals' . $page_text;
        } elseif ($current_id === $car_fuse_box_parent_id) {
            $title = '1000+ Car Fuse Box Diagrams by Make & Model - Free Guides | ProCarManuals' . $page_text;
        } elseif ($current_id === $dtc_fault_codes_parent_id) {
            $title = 'Complete OBD-II Fault Codes Database - DTC Lookup & Repair Solutions | ProCarManuals' . $page_text;
        } elseif ($current_id === $engine_parent_id) {
            $title = 'Engine Workshop Repair Manuals & Overhaul Guides - Free PDF Download | ProCarManuals' . $page_text;
        } elseif ($current_id === $guides_and_tutorials_parent_id) {
            $title = 'Save $500+ DIY Car Repair Guides & Maintenance Tutorials | ProCarManuals' . $page_text;
        } elseif ($current_id === $transmission_parent_id) {
            $title = 'Professional Transmission Repair Manuals & Rebuild Guides - ATRA Collection | ProCarManuals' . $page_text;
        } elseif ($current_id === $wiring_parent_id) {
            $title = 'Complete Wiring Diagrams & Electrical System Manuals - Free Download | ProCarManuals' . $page_text;
        } elseif ($current_id === $owner_car_parent_id) {
            $title = 'Complete Owner\'s Manuals for All Car Makes & Models - Free PDFs | ProCarManuals' . $page_text;
        } elseif ($current_id === $owner_motocycle_parent_id) {
            $title = 'Complete Motorcycle Owner\'s Manuals - All Makes & Models PDF | ProCarManuals' . $page_text;
        } elseif ($current_id === $owner_truck_parent_id) {
            $title = 'Complete Truck Owner\'s Manuals - All Makes & Models PDF Download | ProCarManuals' . $page_text;
        } elseif ($current_id === $pdf_online_parent_id ) {
            $title = 'Digital Car Manuals Online - PDF Auto Guides Instant Access | ProCarManuals' . $page_text;
        }

    } elseif ($parent_id === $vag_sap_parent_id) {
        // Прямые подкатегории "vag sap"
        $title = $cat_name . ' Training Programs - Professional Service Education | ProCarManuals' . $page_text;

    } elseif (in_array($manual_parent_id, $ancestors, true)) {
        // Потомки категории "manual" (НЕ включая саму manual)
        $title = $cat_name . ' Service Manual PDF - Free Download | ProCarManuals' . $page_text;

    } elseif (in_array($body_parent_id, $ancestors, true)) {
        // Потомки категории "body" (НЕ включая саму body)
        $title = $cat_name . ' Body Repair Manual & Collision Guide - Free PDF Download | ProCarManuals' . $page_text;

    } elseif (in_array($car_fuse_box_parent_id, $ancestors, true)) {
        // Потомки категории "car fuse box" (НЕ включая саму car fuse box)
        $title = $cat_name . ' Fuse Box Diagram & Location Guide - DIY Guides | ProCarManuals' . $page_text;
    }  elseif (in_array($dtc_fault_codes_parent_id, $ancestors, true)) {
        // Потомки категории "dtc fault codes" (НЕ включая саму dtc fault codes)
        $title = $cat_name . ' DTC Fault Codes Database - OBD-II Error Code Lookup | ProCarManuals' . $page_text;
    }  elseif (in_array($engine_parent_id, $ancestors, true)) {
        // Потомки категории "engine" (НЕ включая саму engine)
        $title = $cat_name . ' Engine Repair Manual & Overhaul Guide - Workshop PDF | ProCarManuals' . $page_text;
    } elseif (in_array($guides_and_tutorials_parent_id, $ancestors, true)) {
        // Потомки категории "guides & tutories" (НЕ включая саму guides & tutorials)
        $title = $cat_name . ' Beginner DIY Car Repair Guides - Easy Step-by-Step Tutorials | ProCarManuals' . $page_text;
    } elseif (in_array($transmission_parent_id, $ancestors, true)) {
        // Потомки категории "transmission" (НЕ включая саму transmission)
        $title = $cat_name . ' Transmission Service & Repair Manuals - Professional Guides | ProCarManuals' . $page_text;
    } elseif (in_array($wiring_parent_id, $ancestors, true)) {
        // Потомки категории "wiring" (НЕ включая саму wiring)
        $title = $cat_name . ' Wiring Diagrams & Electrical System Manuals - Expert PDFs | ProCarManuals' . $page_text;
    } elseif (in_array($owner_car_parent_id, $ancestors, true)) {
        // Потомки категории "owner car" (НЕ включая саму owner car)
        $title = $cat_name . ' Owner\'s Manual & User Guide Collection - Free PDF | ProCarManuals' . $page_text;
    } elseif (in_array($owner_motocycle_parent_id, $ancestors, true)) {
        // Потомки категории "owner motocycle" (НЕ включая саму owner motocycle)
        $title = $cat_name . ' Motorcycle Owner\'s Manual & User Guide Collection - Free PDF | ProCarManuals' . $page_text;
    } elseif (in_array($owner_truck_parent_id, $ancestors, true)) {
        // Потомки категории "owner truck" (НЕ включая саму owner truck)
        $title = $cat_name . ' Truck Owner\'s Manual & Commercial Vehicle Guide - Free PDF | ProCarManuals' . $page_text;
    }

    // Иначе оставляем стандартное поведение SEOPress
    return $title;
}

?>