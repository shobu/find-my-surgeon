<?php
require_once plugin_dir_path(__FILE__) . '../includes/translations.php';

$countries = get_terms([
    'taxonomy' => 'location',
    'parent' => 0,
    'hide_empty' => false,
]);

// Detect locale from URL path
$uri = $_SERVER['REQUEST_URI'];

if (strpos($uri, '/gr') === 0) {
    $lang = 'gr';
} elseif (strpos($uri, '/de') === 0) {
    $lang = 'de';
} elseif (strpos($uri, '/it') === 0) {
    $lang = 'it';
} else {
    $lang = 'en'; // default fallback
}
$pdf_url = plugin_dir_url(__DIR__) . 'assets/pdf/questions-' . $lang . '.pdf';
?>

<div class="fms-container">
    <!-- Left Column: Filters -->
    <div class="fms-filters">
        <div class="fms-filters-inner">
            <div class="fms-form-wrapper">
                <h2><?php echo fms_t('title'); ?></h2>
                <h3><?php echo fms_t('subtitle'); ?></h3>
                <label for="fms_country"><?php echo fms_t('select_label'); ?></label>
                <div class="fms-dropdown fms-field" id="fms_country_dropdown">
                    <div class="fms-dropdown-selected"><?php echo fms_t('country'); ?></div>
                    <ul class="fms-dropdown-options">
                        <?php foreach ($countries as $country): ?>
                            <li data-value="<?= esc_attr($country->term_id) ?>"><?= esc_html($country->name) ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <input type="hidden" name="fms_country" id="fms_country" value="">
                </div>
                <div class="fms-dropdown fms-field disabled" id="fms_city_dropdown">
                    <div class="fms-dropdown-selected"><?php echo fms_t('city'); ?></div>
                    <ul class="fms-dropdown-options"></ul>
                    <input type="hidden" name="fms_city" id="fms_city" value="">
                </div>
                <button id="fms_search"><?php echo fms_t('search'); ?></button>
            </div>
            <div class="fms-download">
                <span class="fms-download-icon"></span>
                    <div>
                        <strong><a href="<?= esc_url($pdf_url) ?>" target="_blank"><?php echo fms_t('download_strong'); ?></a></strong><br>
                        <small><?php echo fms_t('download_text'); ?></small>
                    </div>
            </div>
        </div>
    </div>
    <!-- Right Column: Results -->
    <div id="doctors-results" class="fms-results">
        <!-- AJAX results go here -->
    </div>
</div>
