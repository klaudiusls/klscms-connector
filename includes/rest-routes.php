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
