<?php
$countries = get_terms([
    'taxonomy' => 'location',
    'parent' => 0,
    'hide_empty' => false,
]);
?>

<div class="fms-container">
    <!-- Left Column: Filters -->
    <div class="fms-filters">
        <h2>Find my surgeon</h2>
        <h3>Find your GalaFLEX Internal Bra™ surgeon</h3>

        <label for="fms_country">Please select your region of interest:</label>
        <select id="fms_country">
            <option value="">COUNTRY</option>
            <?php foreach ($countries as $country): ?>
                <option value="<?= esc_attr($country->term_id) ?>"><?= esc_html($country->name) ?></option>
            <?php endforeach; ?>
        </select>

        <select id="fms_city" disabled>
            <option value="">CITY</option>
        </select>

        <button id="fms_search">SEARCH</button>

        <div class="fms-legal">
            By clicking here, you acknowledge that you have read and you agree to the Terms and Conditions available here and accept that you have read and understood the Privacy Policy available here, in particular the collection, processing, use and disclosure of your personal data to Establishment Labs®*
        </div>
    </div>

    <!-- Right Column: Results -->
    <div id="doctors-results" class="fms-results">
        <!-- AJAX results go here -->
    </div>
</div>
