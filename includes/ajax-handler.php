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

    wp_send_json($results);
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
        echo '<div class="fms-results-wrapper no-results"><p>No doctors found for this city.</p></div>';
        wp_die();
    }

    ob_start();
    echo '<div class="fms-results-wrapper has-results">';
    while ($doctors->have_posts()) {
        $doctors->the_post();

        $clinic   = get_post_meta(get_the_ID(), '_fms_clinic', true);
        $address  = get_post_meta(get_the_ID(), '_fms_address', true);
        $phone    = get_post_meta(get_the_ID(), '_fms_phone', true);
        $email    = get_post_meta(get_the_ID(), '_fms_email', true);
        $facebook = get_post_meta(get_the_ID(), '_fms_facebook', true);
        $post_id  = get_the_ID();
        $title    = get_the_title();
        $linkedin = get_post_meta($post_id, '_fms_linkedin', true);
        $instagram = get_post_meta(get_the_ID(), '_fms_instagram', true);
        $youtube   = get_post_meta(get_the_ID(), '_fms_youtube', true);
        $tiktok    = get_post_meta(get_the_ID(), '_fms_tiktok', true);
        $website   = get_post_meta(get_the_ID(), '_fms_website', true);

        if (has_post_thumbnail()) {
            $thumb = wp_get_attachment_image(get_post_thumbnail_id(get_the_ID()), 'medium', false, ['class' => 'doctor-thumb']);
        } else {
            $placeholder_url = plugin_dir_url(dirname(__DIR__)) . '/find-my-surgeon/assets/images/doctor-placeholder.jpg';
            $thumb = '<img src="' . esc_url($placeholder_url) . '" alt="Doctor placeholder" class="doctor-thumb" />';
        }

        ?>
        <div class="fms-doctor">
            <div class="doctor-thumb-wrapper">
                <?= $thumb ?>
            </div>
            <div class="doctor-info">
                <?php if ($website): ?>
                    <h3 class="doctor-name">
                    <a href="<?= esc_url($website) ?>" target="_blank"
                    class="fms-doctor-link"
                    data-doctor-id="<?= esc_attr($post_id) ?>"
                    data-doctor-name="<?= esc_attr($title) ?>"
                    data-fms-event-type="doctor_profile"
                    data-target-url="<?= esc_url($website) ?>">
                        <?= get_the_title(); ?>
                    </a>
                    </h3>
                <?php else: ?>
                    <h3 class="doctor-name"><?= get_the_title(); ?></h3>
                <?php endif; ?>

                <?php if ($clinic): ?>
                    <p class="doctor-clinic"><strong><?= esc_html($clinic) ?></strong></p>
                <?php endif; ?>

                <?php if ($address): ?>
                    <p class="doctor-address"><?= esc_html($address) ?></p>
                <?php endif; ?>

                <div class="doctor-contact">
                    <?php if ($phone): ?>
                    <a href="tel:<?= esc_attr($phone) ?>"
                    class="fms-social-icon"
                    data-type="phone"
                    data-doctor-id="<?= esc_attr($post_id) ?>"
                    data-doctor-name="<?= esc_attr($title) ?>"
                    data-fms-event-type="doctor_phone"
                    data-target-url="tel:<?= esc_attr($phone) ?>">
                        <i class="fas fa-phone"></i>
                    </a>
                    <?php endif; ?>
                    <?php if ($email): ?>
                    <a href="mailto:<?= esc_attr($email) ?>"
                    class="fms-social-icon"
                    data-type="email"
                    data-doctor-id="<?= esc_attr($post_id) ?>"
                    data-doctor-name="<?= esc_attr($title) ?>"
                    data-fms-event-type="doctor_email"
                    data-target-url="mailto:<?= esc_attr($email) ?>">
                        <i class="fas fa-envelope"></i>
                    </a>
                    <?php endif; ?>
                </div>

                <div class="doctor-socials">
                    <?php if ($linkedin): ?>
                    <a href="<?= esc_url($linkedin) ?>" target="_blank"
                    class="fms-social-icon"
                    data-type="linkedin"
                    data-doctor-id="<?= esc_attr($post_id) ?>"
                    data-doctor-name="<?= esc_attr($title) ?>"
                    data-fms-event-type="doctor_linkedin"
                    data-target-url="<?= esc_url($linkedin) ?>">
                    <i class="fab fa-linkedin-in"></i>
                    </a>
                    <?php endif; ?>
                    <?php if ($instagram): ?>
                    <a href="<?= esc_url($instagram) ?>" target="_blank"
                    class="fms-social-icon"
                    data-type="instagram"
                    data-doctor-id="<?= esc_attr($post_id) ?>"
                    data-doctor-name="<?= esc_attr($title) ?>"
                    data-fms-event-type="doctor_instagram"
                    data-target-url="<?= esc_url($instagram) ?>">
                    <i class="fab fa-instagram"></i>
                    </a>
                    <?php endif; ?>
                    <?php if ($youtube): ?>
                    <a href="<?= esc_url($youtube) ?>" target="_blank"
                    class="fms-social-icon"
                    data-type="youtube"
                    data-doctor-id="<?= esc_attr($post_id) ?>"
                    data-doctor-name="<?= esc_attr($title) ?>"
                    data-fms-event-type="doctor_youtube"
                    data-target-url="<?= esc_url($youtube) ?>">
                    <i class="fab fa-youtube"></i>
                    </a>
                    <?php endif; ?>
                    <?php if ($tiktok): ?>
                    <a href="<?= esc_url($tiktok) ?>" target="_blank"
                    class="fms-social-icon"
                    data-type="tiktok"
                    data-doctor-id="<?= esc_attr($post_id) ?>"
                    data-doctor-name="<?= esc_attr($title) ?>"
                    data-fms-event-type="doctor_tiktok"
                    data-target-url="<?= esc_url($tiktok) ?>">
                    <i class="fab fa-tiktok"></i>
                    </a>
                    <?php endif; ?>
                    <?php if ($website): ?>
                    <a href="<?= esc_url($website) ?>" target="_blank"
                    class="fms-social-icon"
                    data-type="site"
                    data-doctor-id="<?= esc_attr($post_id) ?>"
                    data-doctor-name="<?= esc_attr($title) ?>"
                    data-fms-event-type="doctor_website"
                    data-target-url="<?= esc_url($website) ?>">
                    <i class="fas fa-globe"></i>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }

    echo '</div>';
    wp_reset_postdata();
    echo ob_get_clean();
    wp_die();
}
