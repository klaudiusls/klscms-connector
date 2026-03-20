<?php

if (!defined('ABSPATH')) {
    exit;
}

function klscms_handle_media_upload(WP_REST_Request $request) {
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

function klscms_handle_get_media(WP_REST_Request $request) {
    // Auth handled by permission_callback
    
    $page     = (int) $request->get_param('page') ?: 1;
    $per_page = (int) $request->get_param('per_page') ?: 20;
    $search   = sanitize_text_field(
        $request->get_param('search') ?: ''
    );
    $type     = sanitize_text_field(
        $request->get_param('type') ?: 'image'
    );
 
    $args = [
        'post_type'      => 'attachment',
        'post_status'    => 'inherit',
        'posts_per_page' => $per_page,
        'paged'          => $page,
        'orderby'        => 'date',
        'order'          => 'DESC',
    ];
 
    // Filter by mime type
    if ($type === 'image') {
        $args['post_mime_type'] = 'image';
    } elseif ($type === 'video') {
        $args['post_mime_type'] = 'video';
    }
 
    // Search by filename or title
    if (!empty($search)) {
        $args['s'] = $search;
    }
 
    $query       = new WP_Query($args);
    $attachments = $query->posts;
    $total       = $query->found_posts;
    $total_pages = $query->max_num_pages;
 
    $media = [];
    foreach ($attachments as $attachment) {
        $url       = wp_get_attachment_url($attachment->ID);
        $meta      = wp_get_attachment_metadata($attachment->ID);
        $thumb_url = wp_get_attachment_image_url(
            $attachment->ID, 'thumbnail'
        );
 
        $media[] = [
            'id'        => $attachment->ID,
            'url'       => $url,
            'thumbnail' => $thumb_url ?: $url,
            'filename'  => basename($url),
            'title'     => $attachment->post_title,
            'alt'       => get_post_meta(
                               $attachment->ID, '_wp_attachment_image_alt', true
                           ),
            'mime_type' => $attachment->post_mime_type,
            'width'     => $meta['width'] ?? null,
            'height'    => $meta['height'] ?? null,
            'filesize'  => $meta['filesize'] ?? null,
            'date'      => $attachment->post_date,
        ];
    }
 
    return rest_ensure_response([
        'success'     => true,
        'items'       => $media,
        'total'       => $total,
        'total_pages' => $total_pages,
        'page'        => $page,
        'per_page'    => $per_page,
    ]);
}
