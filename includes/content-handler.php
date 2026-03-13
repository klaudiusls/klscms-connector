<?php

if (!defined('ABSPATH')) {
    exit;
}

function klscms_health(WP_REST_Request $request) {
    return [
        'status' => 'ok',
        'plugin' => 'klscms-connector',
        'version' => '0.1'
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
        'plugin_version' => '0.1',
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
    $page = isset($payload['page']) ? sanitize_text_field($payload['page']) : '';
    $fields = isset($payload['fields']) ? $payload['fields'] : [];
    if (!$page || !is_array($fields)) {
        return new WP_Error('klscms_bad_request', 'invalid payload', ['status' => 400]);
    }
    $post_id = klscms_resolve_page_by_slug($page);
    if (!$post_id) {
        return new WP_Error('klscms_not_found', 'page not found', ['status' => 404]);
    }
    foreach ($fields as $k => $v) {
        $key = sanitize_key($k);
        if (is_array($v)) {
            update_post_meta($post_id, $key, wp_json_encode($v));
        } elseif (is_string($v)) {
            $decoded = json_decode($v, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                update_post_meta($post_id, $key, wp_json_encode($decoded));
            } else {
                update_post_meta($post_id, $key, sanitize_text_field($v));
            }
        } else {
            update_post_meta($post_id, $key, $v);
        }
    }
    return ['status' => 'success'];
}
