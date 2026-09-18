<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class FMS_Click_Logs_Table extends WP_List_Table {
    private $filters = [];
    private $metadata_cache = [];

    public function __construct($filters = []) {
        parent::__construct([
            'plural' => 'fms-click-logs',
            'singular' => 'fms-click-log',
            'ajax' => false,
        ]);
        $this->filters = $filters;
    }

    public function get_columns() {
        return [
            'clicked_at' => __('Date / Time', 'find-my-surgeon'),
            'language_code' => __('Language', 'find-my-surgeon'),
            'event_type' => __('Event Type', 'find-my-surgeon'),
            'country_label' => __('Country', 'find-my-surgeon'),
            'city_label' => __('City', 'find-my-surgeon'),
            'doctor' => __('Doctor', 'find-my-surgeon'),
            'target_url' => __('Target', 'find-my-surgeon'),
            'page_url' => __('Page URL', 'find-my-surgeon'),
            'referrer_url' => __('Referrer', 'find-my-surgeon'),
            'metadata' => __('Metadata', 'find-my-surgeon'),
        ];
    }

    protected function column_default($item, $column_name) {
        switch ($column_name) {
            case 'clicked_at':
                return esc_html($item['clicked_at']);
            case 'language_code':
                return esc_html(strtoupper($item['language_code']));
            case 'event_type':
                return esc_html($item['event_type']);
            case 'country_label':
                return $item['country_label'] ? esc_html($item['country_label']) : '&mdash;';
            case 'city_label':
                return $item['city_label'] ? esc_html($item['city_label']) : '&mdash;';
            case 'doctor':
                $parts = [];
                if (!empty($item['doctor_name_snapshot'])) {
                    $parts[] = esc_html($item['doctor_name_snapshot']);
                }
                if (!empty($item['doctor_id'])) {
                    $parts[] = sprintf(__('ID: %d', 'find-my-surgeon'), intval($item['doctor_id']));
                }
                return $parts ? implode('<br/>', $parts) : '&mdash;';
            case 'target_url':
                $meta = $this->get_metadata_array($item);
                $target = isset($meta['target_url']) ? $meta['target_url'] : '';
                return $target ? sprintf('<a href="%1$s" target="_blank" rel="noreferrer">%2$s</a>', esc_url($target), esc_html(wp_trim_words($target, 6, '…'))) : '&mdash;';
            case 'page_url':
                return $item['page_url'] ? sprintf('<a href="%1$s" target="_blank" rel="noreferrer">%2$s</a>', esc_url($item['page_url']), esc_html(wp_trim_words($item['page_url'], 8, '…'))) : '&mdash;';
            case 'referrer_url':
                return $item['referrer_url'] ? sprintf('<a href="%1$s" target="_blank" rel="noreferrer">%2$s</a>', esc_url($item['referrer_url']), esc_html(wp_trim_words($item['referrer_url'], 6, '…'))) : '&mdash;';
            case 'metadata':
                $meta = $this->get_metadata_array($item);
                if (empty($meta)) {
                    return '&mdash;';
                }
                $rows = [];
                foreach ($meta as $key => $value) {
                    if (in_array($key, ['target_url'], true)) {
                        continue;
                    }
                    if (is_array($value)) {
                        $value = wp_json_encode($value);
                    }
                    $rows[] = sprintf('<strong>%s:</strong> %s', esc_html($key), esc_html($value));
                }
                return $rows ? implode('<br/>', $rows) : '&mdash;';
            default:
                return '&mdash;';
        }
    }

    private function get_metadata_array($item) {
        $id = isset($item['id']) ? (int) $item['id'] : spl_object_id((object) $item);
        if (!isset($this->metadata_cache[$id])) {
            $data = [];
            if (!empty($item['metadata_json'])) {
                $decoded = json_decode($item['metadata_json'], true);
                if (is_array($decoded)) {
                    $data = $decoded;
                }
            }
            $this->metadata_cache[$id] = $data;
        }
        return $this->metadata_cache[$id];
    }

    public function prepare_items() {
        $per_page = 20;
        $current_page = $this->get_pagenum();
        $args = $this->filters;
        $args['paged'] = $current_page;
        $args['per_page'] = $per_page;

        $data = FMS_Click_Logger::query_logs($args);
        $total_items = FMS_Click_Logger::count_logs($args);

        $this->items = $data;
        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page' => $per_page,
        ]);
        $this->_column_headers = [$this->get_columns(), [], []];
    }
}

class FMS_Click_Logs_Page {
    public static function init() {
        add_action('admin_menu', [__CLASS__, 'register_menu']);
        add_action('admin_post_fms_export_logs', [__CLASS__, 'handle_export_request']);
    }

    public static function register_menu() {
        add_menu_page(
            __('FMS Click Logs', 'find-my-surgeon'),
            __('FMS Click Logs', 'find-my-surgeon'),
            FMS_Click_Logger::CAP_VIEW,
            'fms-click-logs',
            [__CLASS__, 'render_page'],
            'dashicons-analytics',
            56
        );
    }

    private static function get_filters() {
        return self::sanitize_filters_from_array($_GET);
    }

    public static function handle_export_request() {
        if (!current_user_can(FMS_Click_Logger::CAP_EXPORT)) {
            wp_die(__('You do not have permission to export logs.', 'find-my-surgeon'), 403);
        }
        if (!isset($_POST['fms_export_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['fms_export_nonce'])), 'fms_export_logs')) {
            wp_die(__('Invalid export nonce.', 'find-my-surgeon'));
        }

        $context = isset($_POST['fms_export_context']) ? sanitize_key(wp_unslash($_POST['fms_export_context'])) : 'raw';
        $filters = self::sanitize_filters_from_array($_POST);

        $filename_suffix = gmdate('Y-m-d');
        $rows = [];
        $headers = [];
        $filename = '';
        $callback = null;

        switch ($context) {
            case 'search_summary':
                $summary = FMS_Click_Logger::export_search_summary($filters);
                $rows = $summary['rows'];
                $period_label = $summary['group_label'];
                $headers = [$period_label, 'Language', 'Country', 'City', 'Search Count'];
                $filename = "fms-search-summary-{$filename_suffix}.csv";
                $callback = function ($row) {
                    return [
                        $row['period_value'],
                        strtoupper($row['language_code']),
                        $row['country_label'],
                        $row['city_label'],
                        $row['search_count'],
                    ];
                };
                break;
            case 'doctor_summary':
                $summary = FMS_Click_Logger::export_doctor_summary($filters);
                $rows = $summary['rows'];
                $period_label = $summary['group_label'];
                $headers = [$period_label, 'Language', 'Country', 'City', 'Doctor', 'Doctor ID', 'Website Clicks', 'Phone Clicks', 'Email Clicks', 'Profile Clicks', 'Total Clicks'];
                $filename = "fms-doctor-summary-{$filename_suffix}.csv";
                $callback = function ($row) {
                    return [
                        $row['period_value'],
                        strtoupper($row['language_code']),
                        $row['country_label'],
                        $row['city_label'],
                        $row['doctor_name_snapshot'],
                        $row['doctor_id'],
                        $row['website_clicks'],
                        $row['phone_clicks'],
                        $row['email_clicks'],
                        $row['profile_clicks'],
                        $row['total_clicks'],
                    ];
                };
                break;
            case 'raw':
            default:
                $rows = FMS_Click_Logger::export_raw_logs($filters);
                $headers = ['ID', 'Event Type', 'Language', 'Country', 'City', 'Doctor ID', 'Doctor Name', 'Target URL', 'Page URL', 'Referrer URL', 'Metadata', 'Date/Time'];
                $filename = "fms-click-log-{$filename_suffix}.csv";
                $callback = function ($row) {
                    return [
                        $row['id'],
                        $row['event_type'],
                        strtoupper($row['language_code']),
                        $row['country_label'],
                        $row['city_label'],
                        $row['doctor_id'],
                        $row['doctor_name_snapshot'],
                        self::extract_target_from_metadata($row['metadata_json']),
                        $row['page_url'],
                        $row['referrer_url'],
                        $row['metadata_json'],
                        $row['clicked_at'],
                    ];
                };
                break;
        }

        if (!$callback) {
            wp_die(__('Invalid export context.', 'find-my-surgeon'));
        }

        if (ob_get_length()) {
            ob_end_clean();
        }
        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');
        fputcsv($output, $headers);
        foreach ($rows as $row) {
            fputcsv($output, $callback($row));
        }
        fclose($output);
        exit;
    }

    private static function sanitize_filters_from_array($source) {
        return [
            'language_code' => isset($source['language_code']) ? sanitize_key(wp_unslash($source['language_code'])) : '',
            'event_type' => isset($source['event_type']) ? sanitize_key(wp_unslash($source['event_type'])) : '',
            'date_from' => isset($source['date_from']) ? sanitize_text_field(wp_unslash($source['date_from'])) : '',
            'date_to' => isset($source['date_to']) ? sanitize_text_field(wp_unslash($source['date_to'])) : '',
            'doctor_search' => isset($source['doctor_search']) ? sanitize_text_field(wp_unslash($source['doctor_search'])) : '',
            'country_label' => isset($source['country_label']) ? sanitize_text_field(wp_unslash($source['country_label'])) : '',
            'city_label' => isset($source['city_label']) ? sanitize_text_field(wp_unslash($source['city_label'])) : '',
            'group_by' => isset($source['group_by']) ? sanitize_key(wp_unslash($source['group_by'])) : 'day',
        ];
    }

    private static function extract_target_from_metadata($metadata_json) {
        if (empty($metadata_json)) {
            return '';
        }
        $decoded = json_decode($metadata_json, true);
        if (is_array($decoded) && isset($decoded['target_url'])) {
            return $decoded['target_url'];
        }
        return '';
    }

    public static function render_page() {
        if (!current_user_can(FMS_Click_Logger::CAP_VIEW)) {
            wp_die(__('You do not have permission to view this page.', 'find-my-surgeon'));
        }

        $filters = self::get_filters();

        $overview = FMS_Click_Logger::get_overview_metrics($filters);
        $search_summary = FMS_Click_Logger::get_search_summary($filters);
        $doctor_summary = FMS_Click_Logger::get_doctor_summary($filters);

        $table = new FMS_Click_Logs_Table($filters);
        $table->prepare_items();

        $languages = FMS_Click_Logger::get_supported_languages();
        $event_types = FMS_Click_Logger::get_allowed_event_types();
        $group_by_options = FMS_Click_Logger::get_group_by_options();
        ?>
        <div class="wrap fms-click-logs-wrap">
            <h1><?php esc_html_e('Find My Surgeon Reporting', 'find-my-surgeon'); ?></h1>

            <form method="get" class="fms-log-filters">
                <input type="hidden" name="page" value="fms-click-logs" />
                <div class="filters-row">
                    <label>
                        <span><?php esc_html_e('Language', 'find-my-surgeon'); ?></span>
                        <select name="language_code">
                            <option value=""><?php esc_html_e('All', 'find-my-surgeon'); ?></option>
                            <?php foreach ($languages as $lang) : ?>
                                <option value="<?php echo esc_attr($lang); ?>" <?php selected($filters['language_code'], $lang); ?>><?php echo esc_html(strtoupper($lang)); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>
                        <span><?php esc_html_e('Country', 'find-my-surgeon'); ?></span>
                        <input type="text" name="country_label" value="<?php echo esc_attr($filters['country_label']); ?>" placeholder="<?php esc_attr_e('e.g. Greece', 'find-my-surgeon'); ?>" />
                    </label>
                    <label>
                        <span><?php esc_html_e('City', 'find-my-surgeon'); ?></span>
                        <input type="text" name="city_label" value="<?php echo esc_attr($filters['city_label']); ?>" placeholder="<?php esc_attr_e('e.g. Athens', 'find-my-surgeon'); ?>" />
                    </label>
                    <label>
                        <span><?php esc_html_e('Doctor', 'find-my-surgeon'); ?></span>
                        <input type="text" name="doctor_search" value="<?php echo esc_attr($filters['doctor_search']); ?>" placeholder="<?php esc_attr_e('Name or ID', 'find-my-surgeon'); ?>" />
                    </label>
                    <label>
                        <span><?php esc_html_e('Date From', 'find-my-surgeon'); ?></span>
                        <input type="date" name="date_from" value="<?php echo esc_attr($filters['date_from']); ?>" />
                    </label>
                    <label>
                        <span><?php esc_html_e('Date To', 'find-my-surgeon'); ?></span>
                        <input type="date" name="date_to" value="<?php echo esc_attr($filters['date_to']); ?>" />
                    </label>
                    <label>
                        <span><?php esc_html_e('Event Type (raw log)', 'find-my-surgeon'); ?></span>
                        <select name="event_type">
                            <option value=""><?php esc_html_e('All', 'find-my-surgeon'); ?></option>
                            <?php foreach ($event_types as $event) : ?>
                                <option value="<?php echo esc_attr($event); ?>" <?php selected($filters['event_type'], $event); ?>><?php echo esc_html($event); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>
                        <span><?php esc_html_e('Group By', 'find-my-surgeon'); ?></span>
                        <select name="group_by">
                            <?php foreach ($group_by_options as $value => $label) : ?>
                                <option value="<?php echo esc_attr($value); ?>" <?php selected($filters['group_by'], $value); ?>><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>
                <p class="description"><?php esc_html_e('Language filters the interface language, while Country/City filter the selected search context. Event Type only impacts the raw log table. Use Group By to adjust summary granularity.', 'find-my-surgeon'); ?></p>
                <?php submit_button(__('Apply Filters', 'find-my-surgeon'), 'primary', 'submit', false); ?>
                <a class="button" href="<?php echo esc_url(remove_query_arg(['language_code','country_label','city_label','doctor_search','date_from','date_to','event_type','group_by','fms_export','fms_export_nonce'])); ?>"><?php esc_html_e('Reset Filters', 'find-my-surgeon'); ?></a>
            </form>

            <div class="fms-summary-cards">
                <?php self::render_summary_card(__('Total Search Clicks', 'find-my-surgeon'), $overview['total_search']); ?>
                <?php self::render_summary_card(__('Total Icon Clicks', 'find-my-surgeon'), $overview['total_icon']); ?>
                <?php self::render_summary_card(__('Website Clicks', 'find-my-surgeon'), $overview['website_clicks']); ?>
                <?php self::render_summary_card(__('Phone Clicks', 'find-my-surgeon'), $overview['phone_clicks']); ?>
                <?php self::render_summary_card(__('Email Clicks', 'find-my-surgeon'), $overview['email_clicks']); ?>
                <?php self::render_summary_card(__('Profile Clicks', 'find-my-surgeon'), $overview['profile_clicks']); ?>
                <?php if (!empty($overview['top_language'])) : self::render_summary_card(__('Top Language', 'find-my-surgeon'), $overview['top_language']); endif; ?>
                <?php if (!empty($overview['top_country'])) : self::render_summary_card(__('Top Country', 'find-my-surgeon'), $overview['top_country']); endif; ?>
                <?php if (!empty($overview['top_doctor'])) : self::render_summary_card(__('Top Doctor', 'find-my-surgeon'), $overview['top_doctor']); endif; ?>
            </div>

            <h2><?php esc_html_e('Search Summary', 'find-my-surgeon'); ?></h2>
            <?php self::render_search_summary($search_summary); ?>
            <?php self::render_export_form('search_summary', $filters, __('Export Search Summary CSV', 'find-my-surgeon')); ?>

            <h2><?php esc_html_e('Doctor Interaction Summary', 'find-my-surgeon'); ?></h2>
            <?php self::render_doctor_summary($doctor_summary); ?>
            <?php self::render_export_form('doctor_summary', $filters, __('Export Doctor Summary CSV', 'find-my-surgeon')); ?>

            <h2><?php esc_html_e('Raw Log (Detailed View)', 'find-my-surgeon'); ?></h2>
            <form method="get" class="fms-raw-log-form">
                <input type="hidden" name="page" value="fms-click-logs" />
                <?php foreach ($filters as $key => $value) : ?>
                    <input type="hidden" name="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr($value); ?>" />
                <?php endforeach; ?>
                <?php $table->display(); ?>
            </form>
            <?php self::render_export_form('raw', $filters, __('Export Raw Log CSV', 'find-my-surgeon')); ?>
        </div>
        <style>
            .fms-click-logs-wrap .filters-row {
                display: flex;
                flex-wrap: wrap;
                gap: 16px;
                margin-bottom: 8px;
            }
            .fms-click-logs-wrap .filters-row label {
                display: flex;
                flex-direction: column;
                min-width: 180px;
            }
            .fms-summary-cards {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
                gap: 12px;
                margin: 24px 0;
            }
            .fms-summary-card {
                background: #fff;
                border: 1px solid #dcdcdc;
                border-radius: 4px;
                padding: 16px;
                text-align: center;
            }
            .fms-summary-card h3 {
                margin: 0 0 8px;
                font-size: 13px;
                text-transform: uppercase;
                color: #555;
            }
            .fms-summary-card .fms-summary-value {
                font-size: 20px;
                font-weight: 600;
            }
        </style>
        <?php
    }

    private static function render_summary_card($label, $value) {
        $is_numeric = is_numeric($value);
        $display = $is_numeric ? number_format_i18n((int) $value) : $value;
        ?>
        <div class="fms-summary-card">
            <h3><?php echo esc_html($label); ?></h3>
            <div class="fms-summary-value"><?php echo esc_html($display); ?></div>
        </div>
        <?php
    }

    private static function render_search_summary($summary) {
        $rows = $summary['rows'];
        $label = $summary['group_label'];
        if (empty($rows)) {
            echo '<p>' . esc_html__('No search activity found for this filter set.', 'find-my-surgeon') . '</p>';
            return;
        }
        echo '<table class="widefat striped"><thead><tr>';
        $headers = [$label, __('Language', 'find-my-surgeon'), __('Country', 'find-my-surgeon'), __('City', 'find-my-surgeon'), __('Search Count', 'find-my-surgeon')];
        foreach ($headers as $header) {
            echo '<th>' . esc_html($header) . '</th>';
        }
        echo '</tr></thead><tbody>';
        foreach ($rows as $row) {
            echo '<tr>';
            echo '<td>' . esc_html($row['period_value']) . '</td>';
            echo '<td>' . esc_html(strtoupper($row['language_code'])) . '</td>';
            echo '<td>' . ($row['country_label'] ? esc_html($row['country_label']) : '&mdash;') . '</td>';
            echo '<td>' . ($row['city_label'] ? esc_html($row['city_label']) : '&mdash;') . '</td>';
            echo '<td>' . esc_html(number_format_i18n($row['search_count'])) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }

    private static function render_doctor_summary($summary) {
        $rows = $summary['rows'];
        $label = $summary['group_label'];
        if (empty($rows)) {
            echo '<p>' . esc_html__('No doctor interactions found for this filter set.', 'find-my-surgeon') . '</p>';
            return;
        }
        echo '<table class="widefat striped"><thead><tr>';
        $headers = [$label, __('Language', 'find-my-surgeon'), __('Country', 'find-my-surgeon'), __('City', 'find-my-surgeon'), __('Doctor', 'find-my-surgeon'), __('Website', 'find-my-surgeon'), __('Phone', 'find-my-surgeon'), __('Email', 'find-my-surgeon'), __('Profile', 'find-my-surgeon'), __('Total', 'find-my-surgeon')];
        foreach ($headers as $header) {
            echo '<th>' . esc_html($header) . '</th>';
        }
        echo '</tr></thead><tbody>';
        foreach ($rows as $row) {
            echo '<tr>';
            echo '<td>' . esc_html($row['period_value']) . '</td>';
            echo '<td>' . esc_html(strtoupper($row['language_code'])) . '</td>';
            echo '<td>' . ($row['country_label'] ? esc_html($row['country_label']) : '&mdash;') . '</td>';
            echo '<td>' . ($row['city_label'] ? esc_html($row['city_label']) : '&mdash;') . '</td>';
            echo '<td>' . ($row['doctor_name_snapshot'] ? esc_html($row['doctor_name_snapshot']) : '&mdash;') . '</td>';
            echo '<td>' . esc_html(number_format_i18n($row['website_clicks'])) . '</td>';
            echo '<td>' . esc_html(number_format_i18n($row['phone_clicks'])) . '</td>';
            echo '<td>' . esc_html(number_format_i18n($row['email_clicks'])) . '</td>';
            echo '<td>' . esc_html(number_format_i18n($row['profile_clicks'])) . '</td>';
            echo '<td>' . esc_html(number_format_i18n($row['total_clicks'])) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }

    private static function render_export_form($context, $filters, $label) {
        ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="fms-export-form" style="margin: 12px 0;">
            <input type="hidden" name="action" value="fms_export_logs" />
            <input type="hidden" name="fms_export_context" value="<?php echo esc_attr($context); ?>" />
            <?php foreach ($filters as $key => $value) : ?>
                <input type="hidden" name="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr($value); ?>" />
            <?php endforeach; ?>
            <?php wp_nonce_field('fms_export_logs', 'fms_export_nonce'); ?>
            <?php submit_button($label, 'secondary', '', false); ?>
        </form>
        <?php
    }
}
