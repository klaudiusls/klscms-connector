<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * GET /wp-json/klscms/v1/global-settings
 * Fetch WordPress native options + KLS custom options
 */
function klscms_get_global_settings(WP_REST_Request $request) {
    return rest_ensure_response([
        'site_title'      => get_option('blogname', ''),
        'tagline'         => get_option('blogdescription', ''),
        'site_url'        => get_option('siteurl', ''),
        'admin_email'     => get_option('admin_email', ''),
        'logo_url'        => klscms_get_logo_url(),
        'favicon_url'     => get_option('klscms_favicon_url', ''),
        'primary_color'   => get_option('klscms_primary_color', '#000000'),
        'secondary_color' => get_option('klscms_secondary_color', '#ffffff'),
        'contact_email'   => get_option('klscms_contact_email', ''),
        'phone'           => get_option('klscms_phone', ''),
        'address'         => get_option('klscms_address', ''),
        'social_links'    => json_decode(get_option('klscms_social_links', '[]'), true) ?? [],
    ]);
}

/**
 * POST /wp-json/klscms/v1/global-settings
 * Save WordPress native options + KLS custom options
 */
function klscms_save_global_settings(WP_REST_Request $request) {
    $payload = $request->get_json_params();

    if (!is_array($payload)) {
        return new WP_Error('klscms_bad_request', 'Invalid payload', ['status' => 400]);
    }

    $updated = [];

    // WordPress native options
    if (isset($payload['site_title'])) {
        update_option('blogname', sanitize_text_field($payload['site_title']));
        $updated[] = 'site_title';
    }
    if (isset($payload['tagline'])) {
        update_option('blogdescription', sanitize_text_field($payload['tagline']));
        $updated[] = 'tagline';
    }
    if (isset($payload['admin_email'])) {
        update_option('admin_email', sanitize_email($payload['admin_email']));
        $updated[] = 'admin_email';
    }

    // KLS custom options — sanitize per field type
    $text_fields = ['phone', 'address'];
    foreach ($text_fields as $field) {
        if (isset($payload[$field])) {
            update_option('klscms_' . $field, sanitize_textarea_field($payload[$field]));
            $updated[] = $field;
        }
    }

    $email_fields = ['contact_email'];
    foreach ($email_fields as $field) {
        if (isset($payload[$field])) {
            update_option('klscms_' . $field, sanitize_email($payload[$field]));
            $updated[] = $field;
        }
    }

    $color_fields = ['primary_color', 'secondary_color'];
    foreach ($color_fields as $field) {
        if (isset($payload[$field])) {
            $sanitized = sanitize_hex_color($payload[$field]);
            if ($sanitized) {
                update_option('klscms_' . $field, $sanitized);
                $updated[] = $field;
            }
        }
    }

    $url_fields = ['logo_url', 'favicon_url'];
    foreach ($url_fields as $field) {
        if (isset($payload[$field])) {
            update_option('klscms_' . $field, esc_url_raw($payload[$field]));
            $updated[] = $field;
        }
    }

    // Social links — array stored as JSON
    if (isset($payload['social_links']) && is_array($payload['social_links'])) {
        update_option('klscms_social_links', wp_json_encode($payload['social_links']));
        $updated[] = 'social_links';
    }

    return rest_ensure_response([
        'success' => true,
        'updated' => $updated,
        'count'   => count($updated),
    ]);
}

/**
 * Helper: resolve logo URL dari custom_logo theme mod atau fallback ke klscms_logo_url option
 */
function klscms_get_logo_url(): string {
    $logo_id = get_theme_mod('custom_logo');
    if ($logo_id) {
        $src = wp_get_attachment_image_src($logo_id, 'full');
        if ($src) {
            return $src[0];
        }
    }
    return get_option('klscms_logo_url', '');
}