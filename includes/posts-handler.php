<?php
if (!defined('ABSPATH')) exit;

/**
 * GET /klscms/v1/posts
 * Query params: type (post_type), page, per_page, status
 */
function klscms_get_posts(WP_REST_Request $request): WP_REST_Response
{
    $post_type = sanitize_key($request->get_param('type') ?? 'post');
    $page      = max(1, intval($request->get_param('page') ?? 1));
    $per_page  = min(50, max(1, 
                     intval($request->get_param('per_page') ?? 20)));
    $status    = $request->get_param('status') ?? 'any';

    // Validate post type exists
    if (!post_type_exists($post_type)) {
        return new WP_REST_Response([
            'error' => 'Post type not found: ' . $post_type
        ], 404);
    }

    $query = new WP_Query([
        'post_type'      => $post_type,
        'post_status'    => $status,
        'posts_per_page' => $per_page,
        'paged'          => $page,
        'orderby'        => 'date',
        'order'          => 'DESC',
    ]);

    $items = [];
    foreach ($query->posts as $post) {
        $items[] = klscms_format_post($post, false);
    }

    return new WP_REST_Response([
        'total'    => $query->found_posts,
        'pages'    => $query->max_num_pages,
        'page'     => $page,
        'per_page' => $per_page,
        'items'    => $items,
    ], 200);
}

/**
 * GET /klscms/v1/posts/{id}
 */
function klscms_get_post(WP_REST_Request $request): WP_REST_Response
{
    $id   = intval($request->get_param('id'));
    $post = get_post($id);

    if (!$post) {
        return new WP_REST_Response(['error' => 'Post not found'], 404);
    }

    return new WP_REST_Response(
        klscms_format_post($post, true), 200
    );
}

/**
 * POST /klscms/v1/posts
 * Body: title, content, type, status, meta (object), thumbnail_url
 */
function klscms_create_post(WP_REST_Request $request): WP_REST_Response
{
    $body      = $request->get_json_params();
    $post_type = sanitize_key($body['type'] ?? 'post');
    $status    = in_array($body['status'] ?? 'draft', 
                    ['publish', 'draft', 'private']) 
                 ? $body['status'] : 'draft';

    if (!post_type_exists($post_type)) {
        return new WP_REST_Response([
            'error' => 'Post type not found'
        ], 404);
    }

    $post_id = wp_insert_post([
        'post_title'   => sanitize_text_field($body['title'] ?? ''),
        'post_content' => wp_kses_post($body['content'] ?? ''),
        'post_type'    => $post_type,
        'post_status'  => $status,
    ], true);

    if (is_wp_error($post_id)) {
        return new WP_REST_Response([
            'error' => $post_id->get_error_message()
        ], 500);
    }

    // Save custom meta fields
    if (!empty($body['meta']) && is_array($body['meta'])) {
        foreach ($body['meta'] as $key => $value) {
            update_post_meta($post_id, 
                sanitize_key($key), 
                sanitize_text_field($value)
            );
        }

        // 1. YOAST MAPPING (TASK 5)
        if (isset($body['meta']['kls_seo_title'])) {
            update_post_meta($post_id, '_yoast_wpseo_title', sanitize_text_field($body['meta']['kls_seo_title']));
        }
        if (isset($body['meta']['kls_seo_description'])) {
            update_post_meta($post_id, '_yoast_wpseo_metadesc', sanitize_text_field($body['meta']['kls_seo_description']));
        }
        if (isset($body['meta']['kls_seo_keyword'])) {
            update_post_meta($post_id, '_yoast_wpseo_focuskw', sanitize_text_field($body['meta']['kls_seo_keyword']));
        }
        // TASK 7: Additional Keywords
        if (isset($body['meta']['kls_seo_additional_keywords'])) {
            update_post_meta($post_id, '_yoast_wpseo_focuskeywords', sanitize_text_field($body['meta']['kls_seo_additional_keywords']));
        }
    }

    // Set featured image from URL
    if (!empty($body['thumbnail_url'])) {
        klscms_set_featured_image($post_id, $body['thumbnail_url']);
    }

    // 2. FEATURED IMAGE SEO (TASK 6)
    $thumbnail_id = get_post_thumbnail_id($post_id);
    if ($thumbnail_id && !empty($body['meta'])) {
        if (isset($body['meta']['kls_featured_image_alt'])) {
            update_post_meta($thumbnail_id, '_wp_attachment_image_alt', sanitize_text_field($body['meta']['kls_featured_image_alt']));
        }

        $image_updates = ['ID' => $thumbnail_id];
        $update_needed = false;

        if (isset($body['meta']['kls_featured_image_title'])) {
            $image_updates['post_title'] = sanitize_text_field($body['meta']['kls_featured_image_title']);
            $update_needed = true;
        }
        if (isset($body['meta']['kls_featured_image_caption'])) {
            $image_updates['post_excerpt'] = wp_kses_post($body['meta']['kls_featured_image_caption']);
            $update_needed = true;
        }
        if (isset($body['meta']['kls_featured_image_description'])) {
            $image_updates['post_content'] = wp_kses_post($body['meta']['kls_featured_image_description']);
            $update_needed = true;
        }

        if ($update_needed) {
            wp_update_post($image_updates);
        }
    }

    return new WP_REST_Response([
        'success' => true,
        'id'      => $post_id,
        'post'    => klscms_format_post(get_post($post_id), true),
    ], 201);
}

/**
 * PUT /klscms/v1/posts/{id}
 */
function klscms_update_post(WP_REST_Request $request): WP_REST_Response
{
    $id   = intval($request->get_param('id'));
    $post = get_post($id);

    if (!$post) {
        return new WP_REST_Response(['error' => 'Post not found'], 404);
    }

    $body   = $request->get_json_params();
    $status = isset($body['status']) 
              && in_array($body['status'], 
                  ['publish', 'draft', 'private']) 
              ? $body['status'] 
              : $post->post_status;

    $updated = wp_update_post([
        'ID'           => $id,
        'post_title'   => sanitize_text_field(
                            $body['title'] ?? $post->post_title),
        'post_content' => wp_kses_post(
                            $body['content'] ?? $post->post_content),
        'post_status'  => $status,
    ], true);

    if (is_wp_error($updated)) {
        return new WP_REST_Response([
            'error' => $updated->get_error_message()
        ], 500);
    }

    // Update meta fields
    if (!empty($body['meta']) && is_array($body['meta'])) {
        foreach ($body['meta'] as $key => $value) {
            update_post_meta($id, 
                sanitize_key($key), 
                sanitize_text_field($value)
            );
        }

        // 1. YOAST MAPPING (TASK 5)
        if (isset($body['meta']['kls_seo_title'])) {
            update_post_meta($id, '_yoast_wpseo_title', sanitize_text_field($body['meta']['kls_seo_title']));
        }
        if (isset($body['meta']['kls_seo_description'])) {
            update_post_meta($id, '_yoast_wpseo_metadesc', sanitize_text_field($body['meta']['kls_seo_description']));
        }
        if (isset($body['meta']['kls_seo_keyword'])) {
            update_post_meta($id, '_yoast_wpseo_focuskw', sanitize_text_field($body['meta']['kls_seo_keyword']));
        }
        // TASK 7: Additional Keywords
        if (isset($body['meta']['kls_seo_additional_keywords'])) {
            update_post_meta($id, '_yoast_wpseo_focuskeywords', sanitize_text_field($body['meta']['kls_seo_additional_keywords']));
        }
    }

    // Update featured image
    if (isset($body['thumbnail_url'])) {
        if ($body['thumbnail_url']) {
            klscms_set_featured_image($id, $body['thumbnail_url']);
        } else {
            delete_post_thumbnail($id);
        }
    }

    // 2. FEATURED IMAGE SEO (TASK 6)
    $thumbnail_id = get_post_thumbnail_id($id);
    if ($thumbnail_id && !empty($body['meta'])) {
        if (isset($body['meta']['kls_featured_image_alt'])) {
            update_post_meta($thumbnail_id, '_wp_attachment_image_alt', sanitize_text_field($body['meta']['kls_featured_image_alt']));
        }

        $image_updates = ['ID' => $thumbnail_id];
        $update_needed = false;

        if (isset($body['meta']['kls_featured_image_title'])) {
            $image_updates['post_title'] = sanitize_text_field($body['meta']['kls_featured_image_title']);
            $update_needed = true;
        }
        if (isset($body['meta']['kls_featured_image_caption'])) {
            $image_updates['post_excerpt'] = wp_kses_post($body['meta']['kls_featured_image_caption']);
            $update_needed = true;
        }
        if (isset($body['meta']['kls_featured_image_description'])) {
            $image_updates['post_content'] = wp_kses_post($body['meta']['kls_featured_image_description']);
            $update_needed = true;
        }

        if ($update_needed) {
            wp_update_post($image_updates);
        }
    }

    return new WP_REST_Response([
        'success' => true,
        'post'    => klscms_format_post(get_post($id), true),
    ], 200);
}

/**
 * DELETE /klscms/v1/posts/{id}
 * Moves to trash (not permanent delete)
 */
function klscms_delete_post(WP_REST_Request $request): WP_REST_Response
{
    $id   = intval($request->get_param('id'));
    $post = get_post($id);

    if (!$post) {
        return new WP_REST_Response(['error' => 'Post not found'], 404);
    }

    $trashed = wp_trash_post($id);

    if (!$trashed) {
        return new WP_REST_Response([
            'error' => 'Failed to trash post'
        ], 500);
    }

    return new WP_REST_Response([
        'success' => true,
        'message' => 'Post moved to trash',
    ], 200);
}

/**
 * Helper: format post untuk response
 */
function klscms_format_post(WP_Post $post, 
                             bool $include_meta = false): array
{
    $data = [
        'id'           => $post->ID,
        'title'        => $post->post_title,
        'slug'         => $post->post_name,
        'status'       => $post->post_status,
        'type'         => $post->post_type,
        'date'         => $post->post_date,
        'modified'     => $post->post_modified,
        'excerpt'      => $post->post_excerpt,
        'thumbnail'    => get_the_post_thumbnail_url(
                            $post->ID, 'medium') ?: null,
        'permalink'    => get_permalink($post->ID),
    ];

    if ($include_meta) {
        $data['content'] = $post->post_content;
        // Get all custom meta (exclude internal WP meta)
        $all_meta = get_post_meta($post->ID);
        $custom_meta = [];
        foreach ($all_meta as $key => $values) {
            // Skip WP internal meta keys
            if (strpos($key, '_') === 0) continue;
            $custom_meta[$key] = count($values) === 1 
                                 ? $values[0] 
                                 : $values;
        }
        $data['meta'] = $custom_meta;
    }

    return $data;
}

/**
 * Helper: set featured image from URL
 */
function klscms_set_featured_image(int $post_id, 
                                    string $url): void
{
    // Check if image already uploaded (by source URL)
    $existing = get_posts([
        'post_type'  => 'attachment',
        'meta_key'   => '_klscms_source_url',
        'meta_value' => $url,
        'fields'     => 'ids',
    ]);

    if (!empty($existing)) {
        set_post_thumbnail($post_id, $existing[0]);
        return;
    }

    // Download and attach image
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $attachment_id = media_sideload_image($url, $post_id, 
                                          null, 'id');

    if (!is_wp_error($attachment_id)) {
        set_post_thumbnail($post_id, $attachment_id);
        update_post_meta($attachment_id, '_klscms_source_url', $url);
    }
}
