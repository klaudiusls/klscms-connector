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

    register_rest_route('klscms/v1', '/media', [
        [
            'methods'             => 'GET',
            'callback'            => 'klscms_get_media',
            'permission_callback' => 'klscms_validate_api_key',
        ],
        [
            'methods'             => 'POST',
            'callback'            => 'klscms_upload_media',
            'permission_callback' => 'klscms_validate_api_key',
        ],
    ]);

    register_rest_route('klscms/v1', '/posts/(?P<type>[a-zA-Z0-9_-]+)/(?P<id>\d+)/acf-values', [
        [
            'methods'             => 'GET',
            'callback'            => 'klscms_get_acf_values',
            'permission_callback' => 'klscms_validate_api_key',
            'args'                => [
                'type' => ['required' => true, 'sanitize_callback' => 'sanitize_key'],
                'id'   => ['required' => true, 'validate_callback' => fn($v) => is_numeric($v) && $v > 0],
            ],
        ],
        [
            'methods'             => 'POST',
            'callback'            => 'klscms_save_acf_values',
            'permission_callback' => 'klscms_validate_api_key',
            'args'                => [
                'type'   => ['required' => true, 'sanitize_callback' => 'sanitize_key'],
                'id'     => ['required' => true, 'validate_callback' => fn($v) => is_numeric($v) && $v > 0],
                'values' => ['required' => true],
            ],
        ],
    ]);

    register_rest_route('klscms/v1', '/acf-schemas', [
        [
            'methods'             => 'GET',
            'callback'            => 'klscms_get_acf_schemas',
            'permission_callback' => 'klscms_validate_api_key',
        ],
    ]);

    register_rest_route('klscms/v1', '/media/(?P<id>\d+)', [
        [
            'methods'             => 'DELETE',
            'callback'            => 'klscms_delete_media',
            'permission_callback' => 'klscms_validate_api_key',
            'args'                => [
                'id' => [
                    'required'          => true,
                    'validate_callback' => fn($v) => is_numeric($v) && $v > 0,
                ],
            ],
        ],
    ]);

    register_rest_route('klscms/v1', '/submissions', [
        'methods' => 'GET',
        'callback' => 'klscms_get_submissions',
        'permission_callback' => 'klscms_validate_api_key',
    ]);

    register_rest_route('klscms/v1', '/debug-meta', [
        'methods' => 'GET',
        'callback' => 'klscms_debug_meta',
        'permission_callback' => 'klscms_validate_api_key',
    ]);

    register_rest_route('klscms/v1', '/purge-cache', [
        'methods' => 'POST',
        'callback' => 'klscms_purge_cache',
        'permission_callback' => 'klscms_validate_api_key',
    ]);

    register_rest_route('klscms/v1', '/global-settings', [
        [
            'methods'             => 'GET',
            'callback'            => 'klscms_get_global_settings',
            'permission_callback' => 'klscms_validate_api_key',
        ],
        [
            'methods'             => 'POST',
            'callback'            => 'klscms_save_global_settings',
            'permission_callback' => 'klscms_validate_api_key',
        ],
    ]);

    register_rest_route('klscms/v1', '/site-structure', [
        'methods'             => 'GET',
        'callback'            => 'klscms_get_site_structure',
        'permission_callback' => 'klscms_validate_api_key',
    ]);

    // CPT Management
    register_rest_route('klscms/v1', '/post-types', [
        [
            'methods'             => 'GET',
            'callback'            => 'klscms_get_post_types',
            'permission_callback' => 'klscms_validate_api_key',
        ],
        [
            'methods'             => 'POST',
            'callback'            => 'klscms_create_post_type',
            'permission_callback' => 'klscms_validate_api_key',
        ],
    ]);

    register_rest_route('klscms/v1', '/post-types/(?P<slug>[a-zA-Z0-9_-]+)', [
        [
            'methods'             => 'DELETE',
            'callback'            => 'klscms_delete_post_type',
            'permission_callback' => 'klscms_validate_api_key',
        ],
    ]);

    // Posts CRUD
    register_rest_route('klscms/v1', '/posts', [
        [
            'methods'             => 'GET',
            'callback'            => 'klscms_get_posts',
            'permission_callback' => 'klscms_validate_api_key',
        ],
        [
            'methods'             => 'POST',
            'callback'            => 'klscms_create_post',
            'permission_callback' => 'klscms_validate_api_key',
        ],
    ]);

    register_rest_route('klscms/v1', '/posts/(?P<id>\d+)', [
        [
            'methods'             => 'GET',
            'callback'            => 'klscms_get_post',
            'permission_callback' => 'klscms_validate_api_key',
        ],
        [
            'methods'             => 'PUT',
            'callback'            => 'klscms_update_post',
            'permission_callback' => 'klscms_validate_api_key',
        ],
        [
            'methods'             => 'DELETE',
            'callback'            => 'klscms_delete_post',
            'permission_callback' => 'klscms_validate_api_key',
        ],
    ]);
});

function klscms_upload_media($request) {
    // Media handler logic
    require_once plugin_dir_path(__FILE__) . 'media-handler.php';
    return klscms_handle_media_upload($request);
}

function klscms_get_media($request) {
    require_once plugin_dir_path(__FILE__) . 'media-handler.php';
    return klscms_handle_get_media($request);
}

function klscms_delete_media($request) {
    require_once plugin_dir_path(__FILE__) . 'media-handler.php';
    return klscms_handle_delete_media($request);
}

function klscms_get_acf_schemas($request) {
    require_once plugin_dir_path(__FILE__) . 'acf-schema-handler.php';
    return klscms_handle_get_acf_schemas($request);
}

function klscms_get_acf_values($request) {
    require_once plugin_dir_path(__FILE__) . 'acf-values-handler.php';
    return klscms_handle_get_acf_values($request);
}

function klscms_save_acf_values($request) {
    require_once plugin_dir_path(__FILE__) . 'acf-values-handler.php';
    return klscms_handle_save_acf_values($request);
}

function klscms_get_submissions($request) {
    // Submission handler logic
    require_once plugin_dir_path(__FILE__) . 'submission-handler.php';
    return klscms_handle_get_submissions($request);
}
