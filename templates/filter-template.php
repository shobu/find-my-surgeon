<?php
require_once plugin_dir_path(__FILE__) . '../includes/translations.php';

$countries = get_terms([
    'taxonomy' => 'location',
    'parent' => 0,
    'hide_empty' => false,
]);

// fms_get_current_language() is defined once, in includes/translations.php,
// which is required at the top of this file. It used to be duplicated here;
// removed to avoid the two copies drifting apart (the duplicate was dead
// code anyway, guarded by function_exists()).

function fms_get_pdf_url($basename = 'questions') {
    $lang = fms_get_current_language();

    $map = [
        'gr' => "assets/pdf/{$basename}-gr.pdf",
        'en' => "assets/pdf/{$basename}-en.pdf",
        'it' => "assets/pdf/{$basename}-it.pdf",
        'de' => "assets/pdf/{$basename}-de.pdf",
        'fr' => "assets/pdf/{$basename}-fr.pdf",
        'nl' => "assets/pdf/{$basename}-nl.pdf",
    ];

    $rel_path  = $map[$lang] ?? $map['en'];
    $base_url  = plugin_dir_url(__DIR__);
    $base_path = plugin_dir_path(__DIR__);

    if (!file_exists($base_path . $rel_path)) {
        $rel_path = $map['en'];
    }

    return $base_url . $rel_path;
}

$pdf_url = fms_get_pdf_url();

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
                <button id="fms_search" data-fms-event-type="search_button"><?php echo fms_t('search'); ?></button>
                <?php 
                    $lang = fms_get_current_language();
                    if ($lang == 'de') { ?>
                <div class="fms-disclaimer">
                    Die Chirurgensuche enthält Daten von Ärzten, die GalaFLEX™ Scaffold nutzen und hier genannt werden möchten.
                    Diese Liste ist keine vollständige Liste aller Chirurgen in Deutschland, die einen solchen Eingriff vornehmen können.
                    Diese Liste stellt keine Arztempfehlung dar. Im Zweifel wenden Sie sich als Patientin an Ihren Frauenarzt.
                    Weitere Chirurgen nehmen wir gerne auf. Bitte wenden Sie sich als Chirurg an SURGERY-GSA-Marketing@bd.com.
                </div>
                <?php } ?>
            </div>
            <div class="fms-download">
                <span class="fms-download-icon"></span>
                    <div>
                        <strong><a href="<?= esc_url($pdf_url) ?>" target="_blank"><?php echo fms_t('download_strong'); ?></a></strong>
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
