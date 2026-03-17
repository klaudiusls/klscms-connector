<?php

if (!defined('ABSPATH')) {
    exit;
}

function klscms_health(WP_REST_Request $request) {
    return [
        'status' => 'ok',
        'plugin' => 'klscms-connector',
        'version' => defined('KLSCMS_VERSION') ? KLSCMS_VERSION : '1.4.0'
    ];
}

function klscms_site_info(WP_REST_Request $request) {
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
    $active = apply_filters('active_plugins', get_option('active_plugins', []));
    $elementor = in_array('elementor/elementor.php', $active, true) || class_exists('Elementor\\Plugin');
    return [
        'site_name' => get_option('blogname'),
        'wordpress_version' => get_bloginfo('version'),
        'elementor_installed' => (bool) $elementor,
        'plugin_version' => defined('KLSCMS_VERSION') ? KLSCMS_VERSION : '1.5.0',
    ];
}

function klscms_get_content(WP_REST_Request $request) {
    $page = sanitize_text_field($request->get_param('page'));
    if (!$page) {
        return new WP_Error('klscms_bad_request', 'page required', ['status' => 400]);
    }
    $post_id = klscms_resolve_page_by_slug($page);
    if (!$post_id) {
        return new WP_Error('klscms_not_found', 'page not found', ['status' => 404]);
    }
    $fields = klscms_get_fields_by_prefix($post_id, 'kls_' . $page . '_');
    return [
        'page' => $page,
        'fields' => $fields
    ];
}

function klscms_save_content(WP_REST_Request $request) {
    $payload = $request->get_json_params();
    $page_slug = isset($payload['page']) ? sanitize_text_field($payload['page']) : '';
    $fields = isset($payload['fields']) ? $payload['fields'] : [];
    
    if (!$page_slug || !is_array($fields)) {
        return new WP_Error('klscms_bad_request', 'invalid payload', ['status' => 400]);
    }
    
    $post_id = klscms_resolve_page_by_slug($page_slug);
    
    // Validate page exists
    $page = get_page_by_path($page_slug, OBJECT, 'page');
    if (!$page) {
        return new WP_Error(
            'page_not_found',
            'Page slug not found: ' . sanitize_text_field($page_slug),
            ['status' => 404]
        );
    }
    
    if (!$post_id) {
        // Double check with resolved ID just in case logic differs
        return new WP_Error('klscms_not_found', 'page not found (id resolve failed)', ['status' => 404]);
    }

    $fields_saved = 0;
    foreach ($fields as $k => $v) {
        $key = sanitize_key($k);
        $update_result = false;
        if (is_array($v)) {
            $update_result = update_post_meta($post_id, $key, wp_json_encode($v));
        } elseif (is_string($v)) {
            $decoded = json_decode($v, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $update_result = update_post_meta($post_id, $key, wp_json_encode($decoded));
            } else {
                // Use klscms_sanitize_field if available
                if (function_exists('klscms_sanitize_field')) {
                     $update_result = update_post_meta($post_id, $key, klscms_sanitize_field($v));
                } else {
                     $update_result = update_post_meta($post_id, $key, sanitize_text_field($v));
                }
            }
        } else {
            $update_result = update_post_meta($post_id, $key, $v);
        }
        
        if ($update_result !== false) {
            $fields_saved++;
        }
    }
    
    return rest_ensure_response([
        'status'       => 'success',
        'success'      => true, // Add boolean success for consistency
        'post_id'      => $post_id,
        'fields_saved' => $fields_saved,
        'page'         => $page_slug,
    ]);
}

function klscms_debug_meta(WP_REST_Request $request) {
    $page = $request->get_param('page') ?: 'contact-us';
    
    // Resolve post
    $post = get_page_by_path($page, OBJECT, ['page', 'post']);
    
    if (!$post) {
        return rest_ensure_response([
            'error'    => 'Post not found',
            'page'     => $page,
            'searched' => $page,
        ]);
    }
    
    // Get ALL meta for this post
    $all_meta = get_post_meta($post->ID);
    
    // Filter KLS meta only
    $kls_meta = [];
    foreach ($all_meta as $key => $values) {
        if (str_starts_with($key, 'kls_')) {
            $kls_meta[$key] = $values[0];
        }
    }
    
    // Test write
    $test_key    = 'kls_debug_test_' . time();
    $write_result = update_post_meta(
        $post->ID, 
        $test_key, 
        'debug_value_' . time()
    );
    $read_result = get_post_meta($post->ID, $test_key, true);
    
    // Cleanup test key
    delete_post_meta($post->ID, $test_key);
    
    return rest_ensure_response([
        'post_id'      => $post->ID,
        'post_slug'    => $post->post_name,
        'post_title'   => $post->post_title,
        'post_status'  => $post->post_status,
        'post_type'    => $post->post_type,
        'kls_meta'     => $kls_meta,
        'kls_count'    => count($kls_meta),
        'write_test'   => [
            'update_result' => $write_result,
            'read_result'   => $read_result,
            'success'       => $read_result === 'debug_value_' . (int)str_replace('kls_debug_test_', '', $test_key) // Correct logic for time comparison
        ],
        'total_meta_count' => count($all_meta),
    ]);
}

function klscms_purge_cache(WP_REST_Request $request) {
    $purged = false;
    $method = 'none';

    // LiteSpeed Cache — purge ALL (includes CSS/JS/ESI)
    if (class_exists('LiteSpeed\Purge')) {
        do_action('litespeed_purge_all');
        $purged = true;
        $method = 'litespeed_purge_all';
    }

    // LiteSpeed function fallback
    if (!$purged && function_exists('litespeed_purge_all')) {
        litespeed_purge_all();
        $purged = true;
        $method = 'litespeed_purge_all_fn';
    }

    // Clear Elementor CSS cache if active
    if (defined('ELEMENTOR_VERSION')) {
        if (class_exists('\Elementor\Plugin') && isset(\Elementor\Plugin::$instance->files_manager)) {
            \Elementor\Plugin::$instance->files_manager->clear_cache();
            $method .= '+elementor_css';
        }
    }

    // Other cache plugins fallback
    if (!$purged) {
        if (function_exists('w3tc_flush_all')) {
            w3tc_flush_all();
            $purged = true;
            $method = 'w3tc';
        } elseif (function_exists('rocket_clean_domain')) {
            rocket_clean_domain();
            $purged = true;
            $method = 'wp_rocket';
        } elseif (function_exists('wp_cache_clear_cache')) {
            wp_cache_clear_cache();
            $purged = true;
            $method = 'wp_super_cache';
        }
    }

    return rest_ensure_response([
        'purged' => $purged,
        'method' => $method,
    ]);
}
