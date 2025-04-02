<?php
// === GET CITIES (children of country) ===
add_action('wp_ajax_fms_get_cities', 'fms_get_cities_callback');
add_action('wp_ajax_nopriv_fms_get_cities', 'fms_get_cities_callback');

function fms_get_cities_callback() {
    $country_id = intval($_POST['country_id']);

    if (!$country_id) {
        wp_send_json([]);
    }

    $cities = get_terms([
        'taxonomy' => 'location',
        'parent' => $country_id,
        'hide_empty' => true,
    ]);

    $results = [];

    foreach ($cities as $city) {
        $results[] = [
            'term_id' => $city->term_id,
            'name'    => $city->name,
        ];
    }

    echo json_encode($results);
    wp_die();
}

// === GET DOCTORS BY CITY (term ID) ===
add_action('wp_ajax_fms_get_doctors', 'fms_get_doctors_callback');
add_action('wp_ajax_nopriv_fms_get_doctors', 'fms_get_doctors_callback');

function fms_get_doctors_callback() {
    $city_id = intval($_POST['city_id']);

    if (!$city_id) {
        echo '<p>No city selected.</p>';
        wp_die();
    }

    $doctors = new WP_Query([
        'post_type' => 'doctor',
        'posts_per_page' => -1,
        'tax_query' => [[
            'taxonomy' => 'location',
            'field' => 'term_id',
            'terms' => $city_id,
        ]]
    ]);

    if (!$doctors->have_posts()) {
        echo '<p>No doctors found for this city.</p>';
        wp_die();
    }

    ob_start();

    while ($doctors->have_posts()) {
        $doctors->the_post();

        $clinic = get_post_meta(get_the_ID(), '_fms_clinic', true);
        $address = get_post_meta(get_the_ID(), '_fms_address', true);
        $phone = get_post_meta(get_the_ID(), '_fms_phone', true);
        $email = get_post_meta(get_the_ID(), '_fms_email', true);
        $facebook = get_post_meta(get_the_ID(), '_fms_facebook', true);
        $linkedin = get_post_meta(get_the_ID(), '_fms_linkedin', true);
        $thumb = get_the_post_thumbnail(get_the_ID(), 'medium', ['style' => 'max-width: 100px; height: auto;']);
        $instagram = get_post_meta(get_the_ID(), '_fms_instagram', true);
        $youtube = get_post_meta(get_the_ID(), '_fms_youtube', true);
        $tiktok = get_post_meta(get_the_ID(), '_fms_tiktok', true);
        $website = get_post_meta(get_the_ID(), '_fms_website', true);


        ?>
        <div class="fms-doctor">
            <?= $thumb ?>
            <div class="fms-doctor-info">
                <h3><?php the_title(); ?></h3>
                <?php if ($clinic): ?><p><strong><?= esc_html($clinic) ?></strong></p><?php endif; ?>
                <?php if ($address): ?><p><?= esc_html($address) ?></p><?php endif; ?>
                <?php if ($phone): ?><p><?= esc_html($phone) ?></p><?php endif; ?>
                <?php if ($email): ?><p><?= esc_html($email) ?></p><?php endif; ?>

                <div class="fms-doctor-socials">
                <?php if ($facebook): ?>
                    <a href="<?= esc_url($facebook) ?>" target="_blank" title="Facebook"><i class="fab fa-facebook-f"></i></a>
                <?php endif; ?>
                <?php if ($instagram): ?>
                    <a href="<?= esc_url($instagram) ?>" target="_blank" title="Instagram"><i class="fab fa-instagram"></i></a>
                <?php endif; ?>
                <?php if ($youtube): ?>
                    <a href="<?= esc_url($youtube) ?>" target="_blank" title="YouTube"><i class="fab fa-youtube"></i></a>
                <?php endif; ?>
                <?php if ($tiktok): ?>
                    <a href="<?= esc_url($tiktok) ?>" target="_blank" title="TikTok"><i class="fab fa-tiktok"></i></a>
                <?php endif; ?>
                <?php if ($linkedin): ?>
                    <a href="<?= esc_url($linkedin) ?>" target="_blank" title="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
                <?php endif; ?>
                <?php if ($website): ?>
                    <a href="<?= esc_url($website) ?>" target="_blank" title="Website"><i class="fas fa-globe"></i></a>
                <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }

    wp_reset_postdata();
    echo ob_get_clean();
    wp_die();
}
