<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('rest_api_init', function () {
    register_rest_route('klscms/v1', '/health', [
        'methods' => 'GET',
        'callback' => 'klscms_health',
        'permission_callback' => '__return_true'
    ]);

    register_rest_route('klscms/v1', '/site-info', [
        'methods' => 'GET',
        'callback' => 'klscms_site_info',
        'permission_callback' => '__return_true'
    ]);

    register_rest_route('klscms/v1', '/content', [
        'methods' => 'GET',
        'callback' => 'klscms_get_content',
        'permission_callback' => 'klscms_validate_api_key',
        'args' => [
            'page' => [
                'required' => true,
                'type' => 'string',
            ],
        ],
    ]);

    register_rest_route('klscms/v1', '/save-content', [
        'methods' => 'POST',
        'callback' => 'klscms_save_content',
        'permission_callback' => 'klscms_validate_api_key',
    ]);

    register_rest_route('klscms/v1', '/upload', [
        'methods' => 'POST',
        'callback' => 'klscms_upload_media',
        'permission_callback' => 'klscms_validate_api_key',
    ]);

    register_rest_route('klscms/v1', '/submissions', [
        'methods' => 'GET',
        'callback' => 'klscms_get_submissions',
        'permission_callback' => 'klscms_validate_api_key',
    ]);
});

function klscms_health() {
    return [
        'status' => 'ok',
        'plugin' => 'klscms-connector',
        'version' => defined('KLSCMS_VERSION') ? KLSCMS_VERSION : '1.0.0'
    ];
}

function klscms_site_info() {
    return [
        'name' => get_bloginfo('name'),
        'url' => get_bloginfo('url'),
        'version' => get_bloginfo('version'),
    ];
}

function klscms_get_content($request) {
    $page = $request->get_param('page');
    // Sanitize output handled by response
    $meta = klscms_get_meta_service()->get_page_content($page);
    return $meta;
}

function klscms_save_content($request) {
    $params = $request->get_json_params();
    
    // Security: Sanitize all inputs recursively
    $sanitized_params = klscms_sanitize_request_data($params);
    
    $result = klscms_get_meta_service()->save_content($sanitized_params);
    return $result;
}

function klscms_upload_media($request) {
    // Media handler logic
    require_once plugin_dir_path(__FILE__) . 'media-handler.php';
    return klscms_handle_media_upload($request);
}

function klscms_get_submissions($request) {
    // Submission handler logic
    require_once plugin_dir_path(__FILE__) . 'submission-handler.php';
    return klscms_handle_get_submissions($request);
}
