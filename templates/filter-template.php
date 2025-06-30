<?php
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
                <h2>Find my surgeon</h2>
                <h3>Find your GalaFLEX Internal Bra™ surgeon</h3>
                <label for="fms_country">Please select your region of interest:</label>
                <div class="fms-dropdown fms-field" id="fms_country_dropdown">
                    <div class="fms-dropdown-selected">COUNTRY</div>
                    <ul class="fms-dropdown-options">
                        <?php foreach ($countries as $country): ?>
                            <li data-value="<?= esc_attr($country->term_id) ?>"><?= esc_html($country->name) ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <input type="hidden" name="fms_country" id="fms_country" value="">
                </div>
                <div class="fms-dropdown fms-field disabled" id="fms_city_dropdown">
                    <div class="fms-dropdown-selected">Select City</div>
                    <ul class="fms-dropdown-options"></ul>
                    <input type="hidden" name="fms_city" id="fms_city" value="">
                </div>
                <button id="fms_search">SEARCH</button>
            </div>
            <div class="fms-download">
                <span class="fms-download-icon"></span>
                    <div>
                        <strong><a href="<?= esc_url($pdf_url) ?>" target="_blank">Download</a></strong><br>
                        <small>the printable questions sheet<br>to ask your surgeon</small>
                    </div>
            </div>
        </div>
    </div>
    <!-- Right Column: Results -->
    <div id="doctors-results" class="fms-results">
        <!-- AJAX results go here -->
    </div>
</div>
