<?php
if (!defined('ABSPATH')) exit;

function klscms_handle_get_taxonomies(WP_REST_Request $request): WP_REST_Response {
    $taxonomies = get_taxonomies(['public' => true], 'objects');
    $result = [];

    foreach ($taxonomies as $tax) {
        $term_count = wp_count_terms(['taxonomy' => $tax->name, 'hide_empty' => false]);
        $result[] = [
            'name'        => $tax->name,
            'label'       => $tax->label,
            'description' => $tax->description,
            'hierarchical'=> $tax->hierarchical,
            'term_count'  => is_wp_error($term_count) ? 0 : (int) $term_count,
            'object_type' => $tax->object_type,
        ];
    }

    return rest_ensure_response([
        'success'    => true,
        'taxonomies' => $result,
        'total'      => count($result),
    ]);
}

function klscms_handle_get_terms(WP_REST_Request $request): WP_REST_Response {
    $taxonomy = sanitize_key($request->get_param('taxonomy'));
    $page     = max(1, (int) $request->get_param('page') ?: 1);
    $per_page = min(100, max(1, (int) $request->get_param('per_page') ?: 20));
    $search   = sanitize_text_field($request->get_param('search') ?: '');

    if (!taxonomy_exists($taxonomy)) {
        return new WP_Error('klscms_not_found', 'Taxonomy not found.', ['status' => 404]);
    }

    $args = [
        'taxonomy'   => $taxonomy,
        'hide_empty' => false,
        'number'     => $per_page,
        'offset'     => ($page - 1) * $per_page,
        'orderby'    => 'name',
        'order'      => 'ASC',
    ];
    if ($search) $args['search'] = $search;

    $terms       = get_terms($args);
    $total       = (int) wp_count_terms(['taxonomy' => $taxonomy, 'hide_empty' => false, 'search' => $search]);
    $total_pages = (int) ceil($total / $per_page);

    if (is_wp_error($terms)) {
        return new WP_Error('klscms_error', $terms->get_error_message(), ['status' => 500]);
    }

    $items = array_map(fn($term) => klscms_format_term($term, $taxonomy, false), $terms);

    return rest_ensure_response([
        'success'     => true,
        'taxonomy'    => $taxonomy,
        'items'       => $items,
        'total'       => $total,
        'total_pages' => $total_pages,
        'page'        => $page,
    ]);
}

function klscms_handle_get_term(WP_REST_Request $request): WP_REST_Response {
    $taxonomy = sanitize_key($request->get_param('taxonomy'));
    $term_id  = (int) $request->get_param('term_id');

    if (!taxonomy_exists($taxonomy)) {
        return new WP_Error('klscms_not_found', 'Taxonomy not found.', ['status' => 404]);
    }

    $term = get_term($term_id, $taxonomy);
    if (!$term || is_wp_error($term)) {
        return new WP_Error('klscms_not_found', 'Term not found.', ['status' => 404]);
    }

    return rest_ensure_response([
        'success' => true,
        'term'    => klscms_format_term($term, $taxonomy, true),
    ]);
}

function klscms_handle_update_term(WP_REST_Request $request): WP_REST_Response {
    $taxonomy = sanitize_key($request->get_param('taxonomy'));
    $term_id  = (int) $request->get_param('term_id');
    $body     = $request->get_json_params() ?: [];

    if (!taxonomy_exists($taxonomy)) {
        return new WP_Error('klscms_not_found', 'Taxonomy not found.', ['status' => 404]);
    }

    $term = get_term($term_id, $taxonomy);
    if (!$term || is_wp_error($term)) {
        return new WP_Error('klscms_not_found', 'Term not found.', ['status' => 404]);
    }

    // Update core term fields
    $update_args = [];
    if (!empty($body['name']))        $update_args['name']        = sanitize_text_field($body['name']);
    if (!empty($body['slug']))        $update_args['slug']        = sanitize_title($body['slug']);
    if (isset($body['description']))  $update_args['description'] = wp_kses_post($body['description']);

    if (!empty($update_args)) {
        $result = wp_update_term($term_id, $taxonomy, $update_args);
        if (is_wp_error($result)) {
            return new WP_Error('klscms_error', $result->get_error_message(), ['status' => 500]);
        }
    }

    // Update ACF fields
    if (!empty($body['acf']) && is_array($body['acf']) && function_exists('update_field')) {
        foreach ($body['acf'] as $field_name => $value) {
            update_field($field_name, $value, $taxonomy . '_' . $term_id);
        }
    }

    $updated = get_term($term_id, $taxonomy);

    return rest_ensure_response([
        'success' => true,
        'term'    => klscms_format_term($updated, $taxonomy, true),
    ]);
}

function klscms_format_term(WP_Term $term, string $taxonomy, bool $include_acf = false): array {
    $data = [
        'id'          => $term->term_id,
        'name'        => $term->name,
        'slug'        => $term->slug,
        'description' => $term->description,
        'count'       => $term->count,
        'parent'      => $term->parent,
        'taxonomy'    => $taxonomy,
    ];

    if ($include_acf && function_exists('get_fields')) {
        $acf_key = $taxonomy . '_' . $term->term_id;
        $fields  = get_fields($acf_key);
        $data['acf'] = is_array($fields) ? $fields : [];
    }

    return $data;
}