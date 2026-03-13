<?php

if (!defined('ABSPATH')) {
    exit;
}

function klscms_get_fields_by_prefix(int $post_id, string $prefix): array
{
    $all = get_post_meta($post_id);
    $result = [];
    foreach ($all as $k => $v) {
        if (strpos($k, $prefix) === 0) {
            $value = is_array($v) ? (count($v) ? $v[0] : '') : $v;
            $decoded = json_decode($value, true);
            $result[$k] = (json_last_error() === JSON_ERROR_NONE) ? $decoded : $value;
        }
    }
    return $result;
}

function klscms_update_fields(int $post_id, array $fields): void
{
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
}
