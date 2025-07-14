<?php
/**
 * Plugin Name: Find My Surgeon
 * Description: AJAX filter for bd surgeon
 * Version: 1.0
 * Author: Teamapp
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_enqueue_scripts', 'fms_enqueue_assets');
function fms_enqueue_assets() {
    wp_enqueue_style('fms-style', plugin_dir_url(__FILE__) . 'assets/css/style.css');
    wp_enqueue_script('fms-script', plugin_dir_url(__FILE__) . 'assets/js/filter.js', ['jquery'], null, true);

    require_once plugin_dir_path(__FILE__) . 'includes/translations.php';
    
    wp_localize_script('fms-script', 'fms_strings', fms_get_js_translations());
    wp_localize_script('fms-script', 'fms_ajax_obj', [
        'ajax_url' => admin_url('admin-ajax.php')
    ]);
    // Na check if double loaded
    if (!wp_style_is('elementor-icons-fa-solid', 'enqueued')) {
        wp_enqueue_style('fms-fontawesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css');
    }
}

add_shortcode('find_my_surgeon', 'fms_render_filter');
function fms_render_filter() {
    ob_start();
    include plugin_dir_path(__FILE__) . 'templates/filter-template.php';
    return ob_get_clean();
}


require_once plugin_dir_path(__FILE__) . 'includes/ajax-handler.php';

require_once plugin_dir_path(__FILE__) . 'includes/cpt-doctor.php';

require_once plugin_dir_path(__FILE__) . 'includes/taxonomies.php';

require_once plugin_dir_path(__FILE__) . 'includes/meta-doctor-details.php';

require_once plugin_dir_path(__FILE__) . 'includes/import-doctors.php';