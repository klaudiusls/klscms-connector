<?php
if (!defined('ABSPATH')) exit;

/**
 * Hook into init to register custom post types created via CMS
 */
add_action('init', 'klscms_register_dynamic_cpts', 0);

function klscms_register_dynamic_cpts() {
    $cpts = get_option('klscms_registered_cpts', []);
    
    if (!is_array($cpts)) {
        return;
    }

    foreach ($cpts as $cpt) {
        if (!isset($cpt['slug']) || !isset($cpt['label'])) {
            continue;
        }

        // Avoid re-registering if already registered by core/theme
        // Note: We check if it exists but is NOT in our own list.
        // Wait, if it's in our list, it won't be registered yet on init (unless we are late).
        // It's safe to just register it. If it was already registered by someone else, WP might throw a notice,
        // but we already prevent adding to this array if it exists.
        
        $args = [
            'labels' => [
                'name' => sanitize_text_field($cpt['label']),
                'singular_name' => sanitize_text_field($cpt['label']),
            ],
            'public' => isset($cpt['public']) ? (bool) $cpt['public'] : true,
            'supports' => isset($cpt['supports']) && is_array($cpt['supports']) ? array_map('sanitize_text_field', $cpt['supports']) : ['title', 'editor'],
            'show_in_rest' => true, // Important for block editor and REST API
        ];

        register_post_type(sanitize_key($cpt['slug']), $args);
    }
}

/**
 * GET /klscms/v1/post-types
 * Returns list of existing post types
 */
function klscms_get_post_types(WP_REST_Request $request): WP_REST_Response {
    $post_types = get_post_types([], 'objects');
    $kls_cpts = get_option('klscms_registered_cpts', []);
    if (!is_array($kls_cpts)) $kls_cpts = [];
    
    $kls_slugs = array_column($kls_cpts, 'slug');
    
    $result = [];
    
    foreach ($post_types as $slug => $type) {
        // Skip some internal WP types
        if (in_array($slug, ['revision', 'nav_menu_item', 'custom_css', 'customize_changeset', 'oembed_cache', 'user_request', 'wp_block', 'wp_template', 'wp_template_part', 'wp_global_styles', 'wp_navigation'])) {
            continue;
        }

        if ($type->_builtin) {
            $source = 'core';
        } elseif (in_array($slug, $kls_slugs)) {
            $source = 'cms';
        } else {
            $source = 'theme/plugin';
        }

        $result[] = [
            'slug' => $slug,
            'label' => $type->label,
            'source' => $source,
        ];
    }
    
    return new WP_REST_Response($result, 200);
}

/**
 * POST /klscms/v1/post-types
 * Register a new CPT from CMS
 */
function klscms_create_post_type(WP_REST_Request $request): WP_REST_Response {
    $body = $request->get_json_params();
    
    if (empty($body['slug']) || empty($body['label'])) {
        return new WP_REST_Response(['error' => 'Missing slug or label'], 400);
    }

    $slug = sanitize_key($body['slug']);
    $label = sanitize_text_field($body['label']);
    $supports = isset($body['supports']) && is_array($body['supports']) ? array_map('sanitize_text_field', $body['supports']) : ['title', 'editor'];
    $public = isset($body['public']) ? (bool) $body['public'] : true;

    // Validation
    if (strlen($slug) > 20) {
        return new WP_REST_Response(['error' => 'Slug must be 20 characters or less'], 400);
    }
    
    if (in_array($slug, ['post', 'page', 'attachment', 'revision', 'nav_menu_item'])) {
        return new WP_REST_Response(['error' => 'Reserved slug name'], 400);
    }

    // SAFE MODE: Check if exists
    if (post_type_exists($slug)) {
        // Check if it's ours
        $cpts = get_option('klscms_registered_cpts', []);
        if (!is_array($cpts)) $cpts = [];
        $is_ours = in_array($slug, array_column($cpts, 'slug'));
        
        return new WP_REST_Response([
            'status' => 'exists',
            'message' => 'Post type already exists',
            'source' => $is_ours ? 'cms' : 'theme/plugin/core'
        ], 200);
    }

    // Register dynamically
    $cpts = get_option('klscms_registered_cpts', []);
    if (!is_array($cpts)) $cpts = [];

    $new_cpt = [
        'slug' => $slug,
        'label' => $label,
        'supports' => $supports,
        'public' => $public,
        'created_by' => 'cms'
    ];

    $cpts[] = $new_cpt;
    update_option('klscms_registered_cpts', $cpts);

    // Register it immediately for this request
    klscms_register_dynamic_cpts();
    flush_rewrite_rules();

    return new WP_REST_Response([
        'status' => 'created',
        'cpt' => $new_cpt
    ], 201);
}

/**
 * DELETE /klscms/v1/post-types/{slug}
 * Delete a CPT (only if created by CMS)
 */
function klscms_delete_post_type(WP_REST_Request $request): WP_REST_Response {
    $slug = sanitize_key($request->get_param('slug'));
    
    $cpts = get_option('klscms_registered_cpts', []);
    if (!is_array($cpts)) $cpts = [];

    $index = array_search($slug, array_column($cpts, 'slug'));
    
    if ($index === false) {
        if (post_type_exists($slug)) {
            return new WP_REST_Response([
                'error' => 'Cannot delete core or theme/plugin post types'
            ], 403);
        }
        return new WP_REST_Response(['error' => 'Post type not found'], 404);
    }

    // Remove from array
    array_splice($cpts, $index, 1);
    update_option('klscms_registered_cpts', $cpts);
    
    // We cannot unregister post type easily without plugins reloading,
    // but flushing rules helps. It will disappear on next load.
    flush_rewrite_rules();

    return new WP_REST_Response([
        'status' => 'deleted',
        'message' => 'Post type removed successfully'
    ], 200);
}
