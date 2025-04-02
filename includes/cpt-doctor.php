<?php
// Register Custom Post Type: Doctors
add_action('init', 'fms_register_doctor_cpt');
function fms_register_doctor_cpt() {
    $labels = [
        'name' => 'Doctors',
        'singular_name' => 'Doctor',
        'menu_name' => 'Doctors',
        'name_admin_bar' => 'Doctor',
        'add_new' => 'Add New',
        'add_new_item' => 'Add New Doctor',
        'edit_item' => 'Edit Doctor',
        'new_item' => 'New Doctor',
        'view_item' => 'View Doctor',
        'search_items' => 'Search Doctors',
        'not_found' => 'No doctors found',
        'not_found_in_trash' => 'No doctors found in Trash',
        'all_items' => 'All Doctors',
        'archives' => 'Doctor Archives',
    ];

    $args = [
        'label' => 'Doctors',
        'labels' => $labels,
        'public' => true,
        'has_archive' => false,
        'rewrite' => ['slug' => 'doctor'],
        'supports' => ['title', 'editor', 'thumbnail'],
        'menu_position' => 5,
        'menu_icon' => 'dashicons-id',
        'show_in_rest' => true,
    ];

    register_post_type('doctor', $args);
}
