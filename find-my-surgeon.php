<?php
/**
 * Plugin Name: Find My Surgeon
 * Description: AJAX filter for bd surgeon
 * Version: 2.2
 * Author: Teamapp
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once plugin_dir_path(__FILE__) . 'includes/translations.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-fms-click-logger.php';

register_activation_hook(__FILE__, 'fms_plugin_activate');
function fms_plugin_activate() {
    FMS_Click_Logger::activate();
}

FMS_Click_Logger::init();

add_action('wp_enqueue_scripts', 'fms_enqueue_assets');
function fms_enqueue_assets() {
    wp_enqueue_style('fms-style', plugin_dir_url(__FILE__) . 'assets/css/style.css');
    wp_enqueue_script('fms-script', plugin_dir_url(__FILE__) . 'assets/js/filter.js', ['jquery'], null, true);
    wp_enqueue_script('fms-logging', plugin_dir_url(__FILE__) . 'assets/js/logging.js', [], null, true);

    wp_localize_script('fms-script', 'fms_strings', fms_get_js_translations());
    wp_localize_script('fms-script', 'fms_ajax_obj', [
        'ajax_url' => admin_url('admin-ajax.php'),
    ]);

    wp_localize_script('fms-logging', 'fmsLogging', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('fms_log_click'),
        'language' => fms_get_current_language(),
        'allowedEvents' => FMS_Click_Logger::get_allowed_event_types(),
    ]);

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

if (is_admin()) {
    require_once plugin_dir_path(__FILE__) . 'includes/admin/class-fms-click-logs-page.php';
    FMS_Click_Logs_Page::init();
}
