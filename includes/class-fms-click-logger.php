<?php
if (!defined('ABSPATH')) {
    exit;
}

class FMS_Click_Logger {
    const TABLE_VERSION = '1.1.0';
    const OPTION_KEY = 'fms_click_log_db_version';
    const DUPLICATE_WINDOW_SECONDS = 3;
    const CAP_VIEW = 'view_fms_click_logs';
    const CAP_EXPORT = 'export_fms_click_logs';
    const ROLE_SLUG = 'fms_log_viewer';

    public static function init() {
        add_action('plugins_loaded', [__CLASS__, 'maybe_upgrade_table']);
        add_action('wp_ajax_fms_log_click', [__CLASS__, 'handle_ajax_log']);
        add_action('wp_ajax_nopriv_fms_log_click', [__CLASS__, 'handle_ajax_log']);
        add_action('admin_init', [__CLASS__, 'maybe_add_caps']);
    }

    public static function activate() {
        self::create_table();
        self::maybe_add_caps();
        self::ensure_viewer_role();
    }

    public static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'fms_click_log';
    }

    public static function get_allowed_event_types() {
        return [
            'search_button',
            'doctor_phone',
            'doctor_email',
            'doctor_linkedin',
            'doctor_instagram',
            'doctor_youtube',
            'doctor_tiktok',
            'doctor_website',
            'doctor_facebook',
            'doctor_whatsapp',
            'doctor_profile',
        ];
    }

    public static function get_supported_languages() {
        return ['en', 'gr', 'de', 'it', 'fr', 'nl'];
    }

    public static function handle_ajax_log() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'fms_log_click')) {
            wp_send_json_error(['message' => 'Invalid nonce'], 403);
        }

        $payload = self::sanitize_payload($_POST);
        if (is_wp_error($payload)) {
            wp_send_json_error(['message' => $payload->get_error_message()], 400);
        }

        if (self::is_bot_request()) {
            wp_send_json_success(['skipped' => 'bot']);
        }

        $language = self::resolve_language_code($payload['language_code'], $payload['page_url']);
        $payload['language_code'] = $language;

        if (!self::is_event_type_allowed($payload['event_type'])) {
            wp_send_json_error(['message' => 'Event type not allowed'], 400);
        }

        if (self::is_duplicate_event($payload)) {
            wp_send_json_success(['skipped' => 'duplicate']);
        }

        $logged = self::insert_log($payload);

        if (is_wp_error($logged)) {
            wp_send_json_error(['message' => $logged->get_error_message()], 500);
        }

        wp_send_json_success(['logged' => true]);
    }

    private static function sanitize_payload($data) {
        $event_type = isset($data['event_type']) ? sanitize_key(wp_unslash($data['event_type'])) : '';
        if (!$event_type) {
            return new WP_Error('invalid_event', 'Missing event type');
        }

        $doctor_id = isset($data['doctor_id']) ? absint($data['doctor_id']) : null;
        $doctor_name = isset($data['doctor_name_snapshot']) ? sanitize_text_field(wp_unslash($data['doctor_name_snapshot'])) : '';
        $language_code = isset($data['language_code']) ? sanitize_key(wp_unslash($data['language_code'])) : '';
        $page_url = isset($data['page_url']) ? esc_url_raw(wp_unslash($data['page_url'])) : '';
        $referrer_url = isset($data['referrer_url']) ? esc_url_raw(wp_unslash($data['referrer_url'])) : '';

        if (empty($page_url)) {
            $page_url = home_url('/');
        }

        $metadata = [];
        if (!empty($data['metadata'])) {
            $decoded = json_decode(wp_unslash($data['metadata']), true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $metadata = self::sanitize_metadata($decoded);
            }
        }

        $country_id = isset($data['country_id']) ? absint($data['country_id']) : null;
        $city_id = isset($data['city_id']) ? absint($data['city_id']) : null;
        $country_label = isset($data['country_label']) ? self::sanitize_location_label($data['country_label']) : '';
        $city_label = isset($data['city_label']) ? self::sanitize_location_label($data['city_label']) : '';

        if (!$country_id && isset($metadata['country_id'])) {
            $country_id = absint($metadata['country_id']);
        }
        if (!$city_id && isset($metadata['city_id'])) {
            $city_id = absint($metadata['city_id']);
        }
        if (!$country_label && isset($metadata['country_label'])) {
            $country_label = self::sanitize_location_label($metadata['country_label'], false);
        }
        if (!$city_label && isset($metadata['city_label'])) {
            $city_label = self::sanitize_location_label($metadata['city_label'], false);
        }

        return [
            'event_type' => $event_type,
            'doctor_id' => $doctor_id ?: null,
            'doctor_name_snapshot' => $doctor_name ? mb_substr($doctor_name, 0, 255) : null,
            'language_code' => $language_code,
            'page_url' => mb_substr($page_url, 0, 1000),
            'referrer_url' => $referrer_url ? mb_substr($referrer_url, 0, 1000) : null,
            'metadata_json' => !empty($metadata) ? wp_json_encode($metadata) : null,
            'country_id' => $country_id ?: null,
            'country_label' => $country_label ?: null,
            'city_id' => $city_id ?: null,
            'city_label' => $city_label ?: null,
        ];
    }

    private static function sanitize_metadata($metadata) {
        $clean = [];
        foreach ($metadata as $key => $value) {
            if (is_scalar($value)) {
                $clean[sanitize_key($key)] = sanitize_text_field((string) $value);
            } elseif (is_array($value)) {
                $clean[sanitize_key($key)] = self::sanitize_metadata($value);
            }
        }
        return $clean;
    }

    private static function is_event_type_allowed($event_type) {
        return in_array($event_type, self::get_allowed_event_types(), true);
    }

    private static function is_bot_request() {
        $ua = isset($_SERVER['HTTP_USER_AGENT']) ? strtolower(sanitize_text_field($_SERVER['HTTP_USER_AGENT'])) : '';
        if (empty($ua)) {
            return true;
        }
        $patterns = [
            'bot',
            'crawl',
            'spider',
            'slurp',
            'mediapartners-google',
            'preview',
            'wget',
            'curl',
            'python',
            'headless',
            'phantom',
            'selenium',
        ];
        foreach ($patterns as $pattern) {
            if (strpos($ua, $pattern) !== false) {
                return true;
            }
        }
        return false;
    }

    private static function is_duplicate_event($payload) {
        global $wpdb;
        $table = self::get_table_name();
        $since = date('Y-m-d H:i:s', current_time('timestamp') - self::DUPLICATE_WINDOW_SECONDS);
        $doctor_id = $payload['doctor_id'] ? $payload['doctor_id'] : 0;

        $sql = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE event_type = %s AND language_code = %s AND COALESCE(doctor_id,0) = %d AND page_url = %s AND clicked_at >= %s",
            $payload['event_type'],
            $payload['language_code'],
            $doctor_id,
            $payload['page_url'],
            $since
        );

        return intval($wpdb->get_var($sql)) > 0;
    }

    private static function insert_log($payload) {
        global $wpdb;
        $table = self::get_table_name();
        $data = [
            'event_type' => $payload['event_type'],
            'language_code' => $payload['language_code'],
            'doctor_id' => $payload['doctor_id'],
            'doctor_name_snapshot' => $payload['doctor_name_snapshot'],
            'country_id' => $payload['country_id'],
            'country_label' => $payload['country_label'],
            'city_id' => $payload['city_id'],
            'city_label' => $payload['city_label'],
            'page_url' => $payload['page_url'],
            'referrer_url' => $payload['referrer_url'],
            'metadata_json' => $payload['metadata_json'],
            'clicked_at' => current_time('mysql'),
        ];
        $formats = ['%s', '%s', '%d', '%s', '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s'];
        $result = $wpdb->insert($table, $data, $formats);
        if (false === $result) {
            return new WP_Error('db_error', 'Failed to log click');
        }
        return true;
    }

    public static function query_logs($args = []) {
        global $wpdb;
        $filters = self::normalize_filters($args);
        list($where_sql, $params) = self::build_where_clause($filters);
        $table = self::get_table_name();
        $offset = ($filters['paged'] - 1) * $filters['per_page'];

        $sql = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY clicked_at DESC LIMIT %d OFFSET %d";
        $params[] = $filters['per_page'];
        $params[] = $offset;

        $prepared = $wpdb->prepare($sql, $params);
        return $wpdb->get_results($prepared, ARRAY_A);
    }

    public static function count_logs($args = []) {
        global $wpdb;
        $filters = self::normalize_filters($args);
        list($where_sql, $params) = self::build_where_clause($filters);
        $table = self::get_table_name();

        $sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
        $prepared = $wpdb->prepare($sql, $params);
        return (int) $wpdb->get_var($prepared);
    }

    public static function export_raw_logs($args = []) {
        return self::query_logs($args + [
            'per_page' => 10000,
            'paged' => 1,
        ]);
    }

    public static function get_doctor_event_types() {
        $events = array_values(array_filter(self::get_allowed_event_types(), function ($event) {
            return $event !== 'search_button';
        }));
        return !empty($events) ? $events : ['__none__'];
    }

    public static function get_overview_metrics($args = []) {
        global $wpdb;
        $filters = self::normalize_filters($args);
        list($where_sql, $params) = self::build_where_clause($filters, [
            'ignore_event_type_filter' => true,
        ]);
        $doctor_events = self::get_doctor_event_types();
        $icon_placeholders = implode(',', array_fill(0, count($doctor_events), '%s'));

        $table = self::get_table_name();
        $summary_sql = "
            SELECT
                SUM(CASE WHEN event_type = 'search_button' THEN 1 ELSE 0 END) AS total_search,
                SUM(CASE WHEN event_type IN ({$icon_placeholders}) THEN 1 ELSE 0 END) AS total_icon,
                SUM(CASE WHEN event_type = 'doctor_website' THEN 1 ELSE 0 END) AS website_clicks,
                SUM(CASE WHEN event_type = 'doctor_phone' THEN 1 ELSE 0 END) AS phone_clicks,
                SUM(CASE WHEN event_type = 'doctor_email' THEN 1 ELSE 0 END) AS email_clicks,
                SUM(CASE WHEN event_type = 'doctor_profile' THEN 1 ELSE 0 END) AS profile_clicks
            FROM {$table}
            WHERE {$where_sql}
        ";
        $summary_args = array_merge($doctor_events, $params);
        $summary = (array) $wpdb->get_row($wpdb->prepare($summary_sql, $summary_args), ARRAY_A);

        $top_language = self::get_top_language($filters);
        $top_country = self::get_top_country($filters);
        $top_doctor = self::get_top_doctor($filters);

        return [
            'total_search' => intval($summary['total_search']),
            'total_icon' => intval($summary['total_icon']),
            'website_clicks' => intval($summary['website_clicks']),
            'phone_clicks' => intval($summary['phone_clicks']),
            'email_clicks' => intval($summary['email_clicks']),
            'profile_clicks' => intval($summary['profile_clicks']),
            'top_language' => $top_language,
            'top_country' => $top_country,
            'top_doctor' => $top_doctor,
        ];
    }

    public static function get_search_summary($args = []) {
        global $wpdb;
        $filters = self::normalize_filters($args);
        $group_meta = self::get_grouping_sql($filters['group_by']);
        list($where_sql, $params) = self::build_where_clause($filters, [
            'force_event_types' => ['search_button'],
            'ignore_event_type_filter' => true,
        ]);
        $table = self::get_table_name();

        $sql = "
            SELECT
                {$group_meta['select']} AS period_value,
                language_code,
                IFNULL(country_label, '') AS country_label,
                IFNULL(city_label, '') AS city_label,
                COUNT(*) AS search_count
            FROM {$table}
            WHERE {$where_sql}
            GROUP BY {$group_meta['group']}, language_code, IFNULL(country_label, ''), IFNULL(city_label, '')
            ORDER BY {$group_meta['order']} DESC, language_code ASC, country_label ASC, city_label ASC
        ";
        $prepared = $wpdb->prepare($sql, $params);
        $results = $wpdb->get_results($prepared, ARRAY_A);
        return [
            'rows' => $results,
            'group_label' => $group_meta['label'],
        ];
    }

    public static function get_doctor_summary($args = []) {
        global $wpdb;
        $filters = self::normalize_filters($args);
        $doctor_events = self::get_doctor_event_types();
        $group_meta = self::get_grouping_sql($filters['group_by']);
        list($where_sql, $params) = self::build_where_clause($filters, [
            'force_event_types' => $doctor_events,
            'ignore_event_type_filter' => true,
        ]);
        $table = self::get_table_name();

        $sql = "
            SELECT
                {$group_meta['select']} AS period_value,
                language_code,
                IFNULL(country_label, '') AS country_label,
                IFNULL(city_label, '') AS city_label,
                IFNULL(doctor_name_snapshot, '') AS doctor_name_snapshot,
                IFNULL(doctor_id, 0) AS doctor_id,
                SUM(CASE WHEN event_type = 'doctor_website' THEN 1 ELSE 0 END) AS website_clicks,
                SUM(CASE WHEN event_type = 'doctor_phone' THEN 1 ELSE 0 END) AS phone_clicks,
                SUM(CASE WHEN event_type = 'doctor_email' THEN 1 ELSE 0 END) AS email_clicks,
                SUM(CASE WHEN event_type = 'doctor_profile' THEN 1 ELSE 0 END) AS profile_clicks,
                COUNT(*) AS total_clicks
            FROM {$table}
            WHERE {$where_sql}
            GROUP BY {$group_meta['group']}, language_code, IFNULL(country_label, ''), IFNULL(city_label, ''), IFNULL(doctor_name_snapshot, ''), IFNULL(doctor_id, 0)
            ORDER BY {$group_meta['order']} DESC, total_clicks DESC
        ";
        $prepared = $wpdb->prepare($sql, $params);
        $results = $wpdb->get_results($prepared, ARRAY_A);
        return [
            'rows' => $results,
            'group_label' => $group_meta['label'],
        ];
    }

    public static function export_search_summary($args = []) {
        return self::get_search_summary($args);
    }

    public static function export_doctor_summary($args = []) {
        return self::get_doctor_summary($args);
    }

    private static function get_top_language($filters) {
        global $wpdb;
        list($where_sql, $params) = self::build_where_clause($filters, [
            'ignore_event_type_filter' => true,
        ]);
        $table = self::get_table_name();
        $sql = "SELECT language_code, COUNT(*) AS total FROM {$table} WHERE {$where_sql} GROUP BY language_code ORDER BY total DESC LIMIT 1";
        $row = $wpdb->get_row($wpdb->prepare($sql, $params), ARRAY_A);
        return ($row && !empty($row['language_code'])) ? strtoupper($row['language_code']) : '';
    }

    private static function get_top_country($filters) {
        global $wpdb;
        list($where_sql, $params) = self::build_where_clause($filters, [
            'force_event_types' => ['search_button'],
            'ignore_event_type_filter' => true,
            'require_country' => true,
        ]);
        $table = self::get_table_name();
        $sql = "SELECT country_label, COUNT(*) AS total FROM {$table} WHERE {$where_sql} GROUP BY country_label ORDER BY total DESC LIMIT 1";
        $row = $wpdb->get_row($wpdb->prepare($sql, $params), ARRAY_A);
        return ($row && !empty($row['country_label'])) ? $row['country_label'] : '';
    }

    private static function get_top_doctor($filters) {
        global $wpdb;
        list($where_sql, $params) = self::build_where_clause($filters, [
            'force_event_types' => self::get_doctor_event_types(),
            'ignore_event_type_filter' => true,
            'require_doctor' => true,
        ]);
        $table = self::get_table_name();
        $sql = "SELECT doctor_name_snapshot, COUNT(*) AS total FROM {$table} WHERE {$where_sql} GROUP BY doctor_name_snapshot ORDER BY total DESC LIMIT 1";
        $row = $wpdb->get_row($wpdb->prepare($sql, $params), ARRAY_A);
        return ($row && !empty($row['doctor_name_snapshot'])) ? $row['doctor_name_snapshot'] : '';
    }

    private static function normalize_filters($args = []) {
        $defaults = [
            'paged' => 1,
            'per_page' => 20,
            'language_code' => '',
            'event_type' => '',
            'date_from' => '',
            'date_to' => '',
            'doctor_search' => '',
            'country_label' => '',
            'city_label' => '',
            'group_by' => 'day',
        ];
        $filters = wp_parse_args($args, $defaults);
        $filters['paged'] = max(1, absint($filters['paged']));
        $filters['per_page'] = max(1, absint($filters['per_page']));
        $filters['language_code'] = $filters['language_code'] ? sanitize_key($filters['language_code']) : '';
        $filters['event_type'] = $filters['event_type'] ? sanitize_key($filters['event_type']) : '';
        $filters['date_from'] = self::normalize_date($filters['date_from']);
        $filters['date_to'] = self::normalize_date($filters['date_to']);
        $filters['doctor_search'] = $filters['doctor_search'] ? sanitize_text_field($filters['doctor_search']) : '';
        $filters['country_label'] = $filters['country_label'] ? sanitize_text_field($filters['country_label']) : '';
        $filters['city_label'] = $filters['city_label'] ? sanitize_text_field($filters['city_label']) : '';
        $filters['group_by'] = self::sanitize_group_by($filters['group_by']);

        return $filters;
    }

    private static function build_where_clause($filters, $options = []) {
        global $wpdb;
        $options = wp_parse_args($options, [
            'ignore_event_type_filter' => false,
            'force_event_types' => [],
            'require_country' => false,
            'require_city' => false,
            'require_doctor' => false,
        ]);

        $where = ['1=1'];
        $params = [];

        if ($filters['language_code']) {
            $where[] = 'language_code = %s';
            $params[] = $filters['language_code'];
        }
        if ($filters['date_from']) {
            $where[] = 'clicked_at >= %s';
            $params[] = $filters['date_from'] . ' 00:00:00';
        }
        if ($filters['date_to']) {
            $where[] = 'clicked_at <= %s';
            $params[] = $filters['date_to'] . ' 23:59:59';
        }
        if ($filters['doctor_search']) {
            $where[] = '(doctor_name_snapshot LIKE %s OR CAST(doctor_id AS CHAR) = %s)';
            $params[] = '%' . $wpdb->esc_like($filters['doctor_search']) . '%';
            $params[] = $filters['doctor_search'];
        }
        if ($filters['country_label']) {
            $where[] = 'LOWER(IFNULL(country_label, "")) = %s';
            $params[] = strtolower($filters['country_label']);
        }
        if ($filters['city_label']) {
            $where[] = 'LOWER(IFNULL(city_label, "")) = %s';
            $params[] = strtolower($filters['city_label']);
        }
        if ($options['require_country']) {
            $where[] = "(country_label IS NOT NULL AND country_label <> '')";
        }
        if ($options['require_city']) {
            $where[] = "(city_label IS NOT NULL AND city_label <> '')";
        }
        if ($options['require_doctor']) {
            $where[] = "(doctor_name_snapshot IS NOT NULL AND doctor_name_snapshot <> '')";
        }

        if (!empty($options['force_event_types'])) {
            $placeholders = implode(',', array_fill(0, count($options['force_event_types']), '%s'));
            $where[] = "event_type IN ({$placeholders})";
            $params = array_merge($params, $options['force_event_types']);
        } elseif (!$options['ignore_event_type_filter'] && $filters['event_type']) {
            $where[] = 'event_type = %s';
            $params[] = $filters['event_type'];
        }

        return [implode(' AND ', $where), $params];
    }

    public static function resolve_language_code($language_code = '', $page_url = '') {
        $allowed = self::get_supported_languages();

        if (!empty($page_url)) {
            $path = parse_url($page_url, PHP_URL_PATH);
            if (is_string($path) && $path !== '') {
                $segments = explode('/', trim($path, '/'));
                foreach ($segments as $segment) {
                    $segment = strtolower($segment);
                    if (in_array($segment, $allowed, true)) {
                        return $segment;
                    }
                }
            }
        }

        if (function_exists('fms_get_current_language')) {
            $lang = strtolower(fms_get_current_language());
            if (in_array($lang, $allowed, true)) {
                return $lang;
            }
        }

        return 'en';
    }

    public static function maybe_upgrade_table() {
        $installed = get_option(self::OPTION_KEY);
        if (!$installed || version_compare($installed, self::TABLE_VERSION, '<')) {
            self::create_table();
        }
    }

    public static function create_table() {
        global $wpdb;
        $table = self::get_table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            event_type varchar(50) NOT NULL,
            language_code varchar(20) NOT NULL,
            doctor_id bigint(20) unsigned NULL,
            doctor_name_snapshot varchar(255) NULL,
            country_id bigint(20) unsigned NULL,
            country_label varchar(191) NULL,
            city_id bigint(20) unsigned NULL,
            city_label varchar(191) NULL,
            page_url varchar(1000) NOT NULL,
            referrer_url varchar(1000) NULL,
            metadata_json longtext NULL,
            clicked_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY event_type (event_type),
            KEY language_code (language_code),
            KEY doctor_id (doctor_id),
            KEY country_label (country_label),
            KEY city_label (city_label),
            KEY clicked_at (clicked_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
        update_option(self::OPTION_KEY, self::TABLE_VERSION);
    }

    public static function maybe_add_caps() {
        $roles = ['administrator'];
        foreach ($roles as $role_slug) {
            $role = get_role($role_slug);
            if ($role) {
                if (!$role->has_cap(self::CAP_VIEW)) {
                    $role->add_cap(self::CAP_VIEW);
                }
                if (!$role->has_cap(self::CAP_EXPORT)) {
                    $role->add_cap(self::CAP_EXPORT);
                }
            }
        }
        self::ensure_viewer_role();
    }

    private static function ensure_viewer_role() {
        $role = get_role(self::ROLE_SLUG);
        if (!$role) {
            add_role(self::ROLE_SLUG, 'FMS Log Viewer', [
                'read' => true,
                self::CAP_VIEW => true,
                self::CAP_EXPORT => true,
            ]);
        } else {
            $role->add_cap(self::CAP_VIEW);
            $role->add_cap(self::CAP_EXPORT);
        }
    }

    private static function sanitize_location_label($value, $unslash = true) {
        if ($unslash) {
            $value = wp_unslash($value);
        }
        $value = sanitize_text_field($value);
        return $value !== '' ? mb_substr($value, 0, 191) : '';
    }

    private static function normalize_date($date) {
        $date = trim((string) $date);
        if (empty($date)) {
            return '';
        }
        $dt = DateTime::createFromFormat('Y-m-d', $date);
        return ($dt && $dt->format('Y-m-d') === $date) ? $date : '';
    }

    public static function get_group_by_options() {
        return [
            'day' => __('Day', 'find-my-surgeon'),
            'month' => __('Month', 'find-my-surgeon'),
            'year' => __('Year', 'find-my-surgeon'),
        ];
    }

    public static function get_group_by_label($group_by) {
        $options = self::get_group_by_options();
        return $options[$group_by] ?? $options['day'];
    }

    private static function sanitize_group_by($value) {
        $options = array_keys(self::get_group_by_options());
        return in_array($value, $options, true) ? $value : 'day';
    }

    private static function get_grouping_sql($group_by) {
        $label = self::get_group_by_label($group_by);
        switch ($group_by) {
            case 'month':
                return [
                    'select' => "DATE_FORMAT(clicked_at, '%Y-%m')",
                    'group' => "DATE_FORMAT(clicked_at, '%Y-%m')",
                    'order' => 'period_value',
                    'label' => $label,
                ];
            case 'year':
                return [
                    'select' => "CAST(YEAR(clicked_at) AS CHAR)",
                    'group' => "YEAR(clicked_at)",
                    'order' => 'period_value',
                    'label' => $label,
                ];
            case 'day':
            default:
                return [
                    'select' => "DATE(clicked_at)",
                    'group' => "DATE(clicked_at)",
                    'order' => 'period_value',
                    'label' => $label,
                ];
        }
    }
}
