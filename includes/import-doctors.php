<?php
add_action('admin_menu', function () {
    add_submenu_page(
        'edit.php?post_type=doctor',
        'Import Doctors',
        'Import Doctors',
        'manage_options',
        'fms-import-doctors',
        'fms_import_doctors_page'
    );
});

function fms_import_doctors_page() {
    echo '<h1> Import Doctors </h1> <hr>';
    echo '<div style="margin: 50px 0;"></div>';
    echo '<h2>STEP ONE</h2>';
    echo '<div class="wrap"><h3>Upload ZIP with Doctor Images</h3>';

    if (isset($_POST['upload_zip']) && check_admin_referer('fms_upload_zip')) {
        $zip = $_FILES['fms_zip_file'];
        if ($zip['error'] === 0 && pathinfo($zip['name'], PATHINFO_EXTENSION) === 'zip') {
            $upload_dir = wp_upload_dir();
            $target_dir = $upload_dir['basedir'] . '/fms-import/images';
            if (!file_exists($target_dir)) wp_mkdir_p($target_dir);

            $zip_dest = $upload_dir['basedir'] . '/fms-import/temp.zip';
            move_uploaded_file($zip['tmp_name'], $zip_dest);

            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
            $result = unzip_file($zip_dest, $target_dir);
            @unlink($zip_dest);

            if (is_wp_error($result)) {
                echo '<div class="notice notice-error"><p>Failed to unzip: ' . esc_html($result->get_error_message()) . '</p></div>';
            } else {
                echo '<div class="notice notice-success"><p>ZIP extracted successfully to <code>fms-import/images</code>.</p></div>';
            }
        } else {
            echo '<div class="notice notice-error"><p>Please upload a valid ZIP file.</p></div>';
        }
    }

    echo '<form method="post" enctype="multipart/form-data">';
    wp_nonce_field('fms_upload_zip');
    echo '<input type="file" name="fms_zip_file" accept=".zip" required>';
    echo '<br><br><input type="submit" name="upload_zip" class="button" value="Upload ZIP">';
    echo '</form></div>';

    echo '<div style="margin: 25px 0;"></div><hr><div style="margin: 25px 0;"></div>';
    echo '<h2>STEP TWO</h2>';
    echo '<div class="wrap"><h3>Import Doctors from Excel</h3>';

    if (!current_user_can('manage_options')) return;

    if (isset($_POST['submit']) && check_admin_referer('fms_import_doctors')) {
        require_once plugin_dir_path(__FILE__) . '../vendor/autoload.php';
        $file = $_FILES['fms_excel_file'];
        $log = [];
        $row_index = 2;

        if ($file['error'] === 0) {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file['tmp_name']);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray(null, true, true, true);

            $headers = array_map('trim', $rows[1]);
            unset($rows[1]);

            foreach ($rows as $index => $row) {
                $row_assoc = [];
                foreach ($headers as $col => $key) {
                    $key = strtolower(trim($key));
                    $key = preg_replace('/[^a-z0-9_]/', '_', $key);
                    $row_assoc[$key] = trim($row[$col] ?? '');
                }

                $prefix    = $row_assoc['prefix_title'] ?? '';
                $first     = $row_assoc['first_name'] ?? '';
                $last      = $row_assoc['last_name'] ?? '';
                $clinic    = $row_assoc['clinic_name'] ?? '';
                $address   = $row_assoc['address'] ?? '';
                $phone     = $row_assoc['phone'] ?? '';
                $email     = $row_assoc['email'] ?? '';
                $website   = $row_assoc['website'] ?? '';
                $facebook  = $row_assoc['facebook_url'] ?? '';
                $linkedin  = $row_assoc['linkedin_url'] ?? '';
                $instagram = $row_assoc['instagram_url'] ?? '';
                $youtube   = $row_assoc['youtube_url'] ?? '';
                $tiktok    = $row_assoc['tiktok_url'] ?? '';
                $country   = $row_assoc['country'] ?? '';
                $city      = $row_assoc['city'] ?? '';
                $image_filename = $row_assoc['image_filename'] ?? '';

                $entry_log = [];

                if (!$email || get_posts(['post_type' => 'doctor', 'meta_key' => '_fms_email', 'meta_value' => $email, 'numberposts' => 1])) {
                    $entry_log[] = "Already exists or invalid. Skipped.";
                    $log[$email] = $entry_log;
                    $row_index++;
                    continue;
                }

                $country_term = get_term_by('name', $country, 'location');
                $city_term = get_term_by('name', $city, 'location');

                if (!$country_term || !$city_term || $city_term->parent != $country_term->term_id) {
                    $entry_log[] = "Invalid country or city: {$country} / {$city}. Skipped.";
                    $log[$email] = $entry_log;
                    $row_index++;
                    continue;
                }

                $full_name = trim("$prefix $first $last") ?: $clinic;
                $post_id = wp_insert_post([
                    'post_type' => 'doctor',
                    'post_status' => 'publish',
                    'post_title' => $full_name,
                ]);

                if (is_wp_error($post_id)) {
                    $entry_log[] = "Failed to create post.";
                    $log[$email] = $entry_log;
                    $row_index++;
                    continue;
                }

                update_post_meta($post_id, '_fms_clinic', $clinic);
                update_post_meta($post_id, '_fms_address', $address);
                update_post_meta($post_id, '_fms_phone', $phone);
                update_post_meta($post_id, '_fms_email', $email);
                update_post_meta($post_id, '_fms_website', $website);
                update_post_meta($post_id, '_fms_facebook', $facebook);
                update_post_meta($post_id, '_fms_linkedin', $linkedin);
                update_post_meta($post_id, '_fms_instagram', $instagram);
                update_post_meta($post_id, '_fms_youtube', $youtube);
                update_post_meta($post_id, '_fms_tiktok', $tiktok);

                wp_set_post_terms($post_id, [$city_term->term_id], 'location', false);

                $upload_dir = wp_upload_dir();
                $images_dir = $upload_dir['basedir'] . '/fms-import/images';
                $images_url = $upload_dir['baseurl'] . '/fms-import/images';

                $prefix_parts = explode('-', $image_filename);
                $image_prefix_folder = $prefix_parts[0] ?? '';
                $search_dir = $image_prefix_folder ? $images_dir . '/' . $image_prefix_folder : $images_dir;
                $image_full_path = $search_dir . '/' . $image_filename;
                $image_full_url = $image_prefix_folder ? $images_url . '/' . $image_prefix_folder . '/' . $image_filename : $images_url . '/' . $image_filename;

                $entry_log[] = "Looking for file: $image_full_path";
                $entry_log[] = "URL would be: $image_full_url";

                if (file_exists($image_full_path)) {
                    $entry_log[] = "Image found at: $image_full_path";
                    $attach_id = fms_attach_image_to_post($image_full_path, $post_id);
                    if ($attach_id) set_post_thumbnail($post_id, $attach_id);
                } else {
                    $entry_log[] = "Image NOT found. Using placeholder.";
                    $placeholder_url = $upload_dir['baseurl'] . '/2025/06/doctor-placeholder.jpg';
                    $attach_id = fms_attach_external_image($placeholder_url, $post_id);
                    if ($attach_id) {
                        set_post_thumbnail($post_id, $attach_id);
                        $entry_log[] = "Placeholder attached.";
                    } else {
                        $entry_log[] = "Placeholder failed.";
                    }
                }

                $entry_log[] = "Successfully imported.";
                $log[$email] = $entry_log;
                $row_index++;
            }
        } else {
            $log['error'] = ["Upload error."];
        }

        echo '<h2>Import Results</h2><ul>';
        foreach ($log as $email => $entries) {
            echo '<li><strong>' . esc_html($email) . '</strong><ul>';
            foreach ($entries as $line) echo '<li>' . esc_html($line) . '</li>';
            echo '</ul></li>';
        }
        echo '</ul>';
    }

    echo '<form method="post" enctype="multipart/form-data">';
    wp_nonce_field('fms_import_doctors');
    echo '<input type="file" name="fms_excel_file" accept=".xlsx,.xls" required>';
    echo '<br><br><input type="submit" name="submit" class="button button-primary" value="Import">';
    echo '</form>';
}

function fms_attach_image_to_post($filepath, $post_id) {
    $filetype = wp_check_filetype(basename($filepath), null);
    $upload_dir = wp_upload_dir();
    $filename = basename($filepath);
    $new_path = $upload_dir['path'] . '/' . $filename;

    copy($filepath, $new_path);
    $attachment = [
        'guid'           => $upload_dir['url'] . '/' . basename($new_path),
        'post_mime_type' => $filetype['type'],
        'post_title'     => sanitize_file_name($filename),
        'post_content'   => '',
        'post_status'    => 'inherit',
    ];

    $attach_id = wp_insert_attachment($attachment, $new_path, $post_id);
    require_once ABSPATH . 'wp-admin/includes/image.php';
    $attach_data = wp_generate_attachment_metadata($attach_id, $new_path);
    wp_update_attachment_metadata($attach_id, $attach_data);
    return $attach_id;
}

function fms_attach_external_image($url, $post_id) {
    $tmp = download_url($url);
    if (is_wp_error($tmp)) return false;

    $file_array = [
        'name'     => basename($url),
        'tmp_name' => $tmp,
    ];

    $attach_id = media_handle_sideload($file_array, $post_id);
    if (is_wp_error($attach_id)) {
        @unlink($tmp);
        return false;
    }

    return $attach_id;
}

function fms_find_image_recursive($directory, $filename) {
    if (!file_exists($directory)) return false;
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
    foreach ($iterator as $file) {
        if ($file->isFile() && strtolower($file->getFilename()) === strtolower($filename)) {
            return $file->getPathname();
        }
    }
    return false;
}
