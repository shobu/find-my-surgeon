<?php
// Add meta box for extra doctor details
add_action('add_meta_boxes', 'fms_add_doctor_details_metabox');
function fms_add_doctor_details_metabox() {
    add_meta_box(
        'fms_doctor_details',
        'Doctor Details',
        'fms_doctor_details_callback',
        'doctor',
        'normal',
        'default'
    );
}

function fms_doctor_details_callback($post) {
    $clinic = get_post_meta($post->ID, '_fms_clinic', true);
    $address = get_post_meta($post->ID, '_fms_address', true);
    $phone = get_post_meta($post->ID, '_fms_phone', true);
    $email = get_post_meta($post->ID, '_fms_email', true);
    $facebook = get_post_meta($post->ID, '_fms_facebook', true);
    $linkedin = get_post_meta($post->ID, '_fms_linkedin', true);
    $instagram = get_post_meta($post->ID, '_fms_instagram', true);
    $youtube = get_post_meta($post->ID, '_fms_youtube', true);
    $tiktok = get_post_meta($post->ID, '_fms_tiktok', true);
    $website = get_post_meta($post->ID, '_fms_website', true);
    ?>

    <style>
        .fms-meta label { display: block; margin-top: 10px; font-weight: bold; }
        .fms-meta input { width: 100%; padding: 5px; }
    </style>

    <div class="fms-meta">
        <label for="fms_clinic">Clinic Name</label>
        <input type="text" name="fms_clinic" id="fms_clinic" value="<?= esc_attr($clinic) ?>">

        <label for="fms_address">Address</label>
        <input type="text" name="fms_address" id="fms_address" value="<?= esc_attr($address) ?>">

        <label for="fms_phone">Phone</label>
        <input type="text" name="fms_phone" id="fms_phone" value="<?= esc_attr($phone) ?>">

        <label for="fms_email">Email</label>
        <input type="email" name="fms_email" id="fms_email" value="<?= esc_attr($email) ?>">

        <label for="fms_facebook">Facebook URL</label>
        <input type="url" name="fms_facebook" id="fms_facebook" value="<?= esc_attr($facebook) ?>">

        <label for="fms_linkedin">LinkedIn URL</label>
        <input type="url" name="fms_linkedin" id="fms_linkedin" value="<?= esc_attr($linkedin) ?>">
        <label for="fms_instagram">Instagram URL</label>
        <input type="url" name="fms_instagram" id="fms_instagram" value="<?= esc_attr($instagram) ?>">

        <label for="fms_youtube">YouTube URL</label>
        <input type="url" name="fms_youtube" id="fms_youtube" value="<?= esc_attr($youtube) ?>">

        <label for="fms_tiktok">TikTok URL</label>
        <input type="url" name="fms_tiktok" id="fms_tiktok" value="<?= esc_attr($tiktok) ?>">

        <label for="fms_website">Website</label>
        <input type="url" name="fms_website" id="fms_website" value="<?= esc_attr($website) ?>">

    </div>
    <?php
}

// Save fields
add_action('save_post', 'fms_save_doctor_details');
function fms_save_doctor_details($post_id) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

    $fields = [
        'fms_clinic',
        'fms_address',
        'fms_phone',
        'fms_email',
        'fms_facebook',
        'fms_linkedin',
        'fms_instagram',
        'fms_youtube',
        'fms_tiktok',
        'fms_website',
    ];

    foreach ($fields as $field) {
        if (isset($_POST[$field])) {
            update_post_meta($post_id, '_' . $field, sanitize_text_field($_POST[$field]));
        }
    }
}
