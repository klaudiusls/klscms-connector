<?php

if (!defined('ABSPATH')) {
    exit;
}

function klscms_upload_media(WP_REST_Request $request) {
    $files = $request->get_file_params();
    if (!isset($files['file'])) {
        return new WP_Error('klscms_bad_request', 'file missing', ['status' => 400]);
    }
    $file = $files['file'];
    $overrides = ['test_form' => false];
    $movefile = wp_handle_upload($file, $overrides);
    if (!isset($movefile['url'])) {
        return new WP_Error('klscms_upload_error', 'upload failed', ['status' => 500]);
    }
    $filetype = wp_check_filetype(basename($movefile['file']), null);
    $attachment = [
        'post_mime_type' => $filetype['type'],
        'post_title' => sanitize_file_name(basename($movefile['file'])),
        'post_content' => '',
        'post_status' => 'inherit'
    ];
    $attach_id = wp_insert_attachment($attachment, $movefile['file']);
    require_once ABSPATH . 'wp-admin/includes/image.php';
    $attach_data = wp_generate_attachment_metadata($attach_id, $movefile['file']);
    wp_update_attachment_metadata($attach_id, $attach_data);
    $alt = $request->get_param('alt');
    if ($alt) {
        update_post_meta($attach_id, '_wp_attachment_image_alt', sanitize_text_field($alt));
    }
    return ['id' => $attach_id, 'url' => $movefile['url']];
}
