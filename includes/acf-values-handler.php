<?php
if (!defined('ABSPATH')) exit;

function klscms_handle_get_acf_values(WP_REST_Request $request) {
    if (!function_exists('get_field')) {
        return new WP_Error(
            'klscms_acf_not_found',
            'Advanced Custom Fields plugin is not active.',
            ['status' => 404]
        );
    }

    $post_id = (int) $request->get_param('id');
    $post    = get_post($post_id);

    if (!$post) {
        return new WP_Error('klscms_not_found', 'Post not found.', ['status' => 404]);
    }

    $field_groups = acf_get_field_groups(['post_id' => $post_id]);
    $values       = [];

    foreach ($field_groups as $group) {
        $fields = acf_get_fields($group['key']);
        if (!$fields) continue;

        foreach ($fields as $field) {
            if (($field['type'] ?? '') === 'tab') continue;
            $values[$field['name']] = klscms_resolve_field_value($post_id, $field);
        }
    }

    return rest_ensure_response([
        'success'  => true,
        'post_id'  => $post_id,
        'values'   => $values,
    ]);
}

function klscms_resolve_field_value(int $post_id, array $field): mixed {
    $name  = $field['name'];
    $type  = $field['type'] ?? 'text';
    $value = get_field($name, $post_id);

    switch ($type) {
        case 'image':
        case 'file':
            if (is_array($value)) {
                return [
                    'id'  => $value['ID']  ?? null,
                    'url' => $value['url'] ?? null,
                    'alt' => $value['alt'] ?? '',
                    'filename' => basename($value['url'] ?? ''),
                ];
            }
            if (is_numeric($value)) {
                $url = wp_get_attachment_url($value);
                return [
                    'id'  => (int) $value,
                    'url' => $url ?: null,
                    'alt' => get_post_meta($value, '_wp_attachment_image_alt', true) ?: '',
                    'filename' => basename($url ?: ''),
                ];
            }
            return null;

        case 'gallery':
            if (!is_array($value)) return [];
            return array_map(fn($img) => [
                'id'  => $img['ID']  ?? null,
                'url' => $img['url'] ?? null,
                'alt' => $img['alt'] ?? '',
            ], $value);

        case 'repeater':
            if (!is_array($value)) return [];
            $sub_fields = $field['sub_fields'] ?? [];
            return array_map(function($row) use ($sub_fields) {
                $resolved = [];
                foreach ($sub_fields as $sub) {
                    if (($sub['type'] ?? '') === 'tab') continue;
                    $resolved[$sub['name']] = $row[$sub['name']] ?? null;
                }
                return $resolved;
            }, $value);

        case 'relationship':
        case 'post_object':
            if (empty($value)) return [];
            $items = is_array($value) ? $value : [$value];
            return array_map(function($item) {
                $post = is_object($item) ? $item : get_post($item);
                if (!$post) return null;
                return [
                    'id'    => $post->ID,
                    'title' => $post->post_title,
                    'slug'  => $post->post_name,
                    'url'   => get_permalink($post->ID),
                ];
            }, array_filter($items));

        case 'taxonomy':
            if (empty($value)) return [];
            $terms = is_array($value) ? $value : [$value];
            return array_map(function($term) {
                if (is_object($term)) {
                    return ['id' => $term->term_id, 'name' => $term->name, 'slug' => $term->slug];
                }
                if (is_numeric($term)) {
                    $t = get_term($term);
                    return $t ? ['id' => $t->term_id, 'name' => $t->name, 'slug' => $t->slug] : null;
                }
                return $term;
            }, array_filter($terms));

        case 'link':
            if (is_array($value)) {
                return [
                    'url'    => $value['url']    ?? '',
                    'title'  => $value['title']  ?? '',
                    'target' => $value['target'] ?? '',
                ];
            }
            return $value;

        case 'true_false':
            return (bool) $value;

        case 'number':
            return $value !== null && $value !== '' ? (float) $value : null;

        default:
            return $value;
    }
}

function klscms_handle_save_acf_values(WP_REST_Request $request) {
    if (!function_exists('update_field')) {
        return new WP_Error(
            'klscms_acf_not_found',
            'Advanced Custom Fields plugin is not active.',
            ['status' => 404]
        );
    }

    $post_id = (int) $request->get_param('id');
    $post    = get_post($post_id);

    if (!$post) {
        return new WP_Error('klscms_not_found', 'Post not found.', ['status' => 404]);
    }

    $values = $request->get_param('values');
    if (!is_array($values)) {
        return new WP_Error('klscms_bad_request', 'values must be an object.', ['status' => 400]);
    }

    $saved  = [];
    $errors = [];

    foreach ($values as $field_name => $value) {
        $field_object = get_field_object($field_name, $post_id);

        if (!$field_object) {
            // Try by name without field object
            $result = update_field($field_name, $value, $post_id);
        } else {
            $type  = $field_object['type'] ?? 'text';
            $value = klscms_prepare_value_for_save($value, $type, $field_object);
            $result = update_field($field_object['key'], $value, $post_id);
        }

        if ($result !== false) {
            $saved[] = $field_name;
        } else {
            // update_field returns false if value unchanged — treat as success
            $saved[] = $field_name;
        }
    }

    return rest_ensure_response([
        'success' => true,
        'post_id' => $post_id,
        'saved'   => $saved,
        'errors'  => $errors,
    ]);
}

function klscms_prepare_value_for_save(mixed $value, string $type, array $field): mixed {
    switch ($type) {
        case 'image':
        case 'file':
            // Accept ID directly or object with id key
            if (is_array($value) && isset($value['id'])) {
                return (int) $value['id'];
            }
            return is_numeric($value) ? (int) $value : $value;

        case 'gallery':
            if (!is_array($value)) return [];
            return array_map(fn($img) => is_array($img) ? (int)($img['id'] ?? 0) : (int)$img, $value);

        case 'true_false':
            return $value ? 1 : 0;

        case 'number':
            return $value !== null && $value !== '' ? (float) $value : '';

        case 'repeater':
            return is_array($value) ? $value : [];

        case 'taxonomy':
            if (is_array($value)) {
                return array_map(fn($t) => is_array($t) ? (int)($t['id'] ?? 0) : (int)$t, $value);
            }
            return $value;

        default:
            return $value;
    }
}