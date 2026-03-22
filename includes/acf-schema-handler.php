<?php
if (!defined('ABSPATH')) exit;

function klscms_handle_get_acf_schemas(WP_REST_Request $request) {
    if (!function_exists('acf_get_field_groups')) {
        return new WP_Error(
            'klscms_acf_not_found',
            'Advanced Custom Fields plugin is not active on this site.',
            ['status' => 404]
        );
    }

    $field_groups = acf_get_field_groups();

    if (empty($field_groups)) {
        return rest_ensure_response([
            'success'       => true,
            'field_groups'  => [],
            'total'         => 0,
        ]);
    }

    $result = [];

    foreach ($field_groups as $group) {
        $raw_fields = acf_get_fields($group['key']);
        $fields     = klscms_normalize_fields($raw_fields ?: []);
        $locations  = klscms_normalize_locations($group['location'] ?? []);

        $result[] = [
            'key'         => $group['key'],
            'title'       => $group['title'],
            'active'      => (bool) ($group['active'] ?? true),
            'position'    => $group['position'] ?? 'normal',
            'locations'   => $locations,
            'fields'      => $fields,
            'field_count' => count($fields),
        ];
    }

    return rest_ensure_response([
        'success'      => true,
        'field_groups' => $result,
        'total'        => count($result),
    ]);
}

function klscms_normalize_locations(array $location_groups): array {
    $locations = [];

    foreach ($location_groups as $group) {
        foreach ($group as $rule) {
            $locations[] = [
                'param'    => $rule['param']    ?? '',
                'operator' => $rule['operator'] ?? '==',
                'value'    => $rule['value']    ?? '',
            ];
        }
    }

    return $locations;
}

function klscms_normalize_fields(array $fields): array {
    $normalized = [];

    foreach ($fields as $field) {
        // Skip tab fields — purely UI grouping, no data
        if (($field['type'] ?? '') === 'tab') {
            continue;
        }

        $normalized_field = [
            'key'          => $field['key']          ?? '',
            'name'         => $field['name']         ?? '',
            'label'        => $field['label']        ?? '',
            'type'         => $field['type']         ?? 'text',
            'required'     => (bool) ($field['required'] ?? false),
            'instructions' => $field['instructions'] ?? '',
            'placeholder'  => $field['placeholder']  ?? '',
            'default_value'=> $field['default_value'] ?? '',
            'sub_fields'   => null,
            'choices'      => null,
            'conditional_logic' => !empty($field['conditional_logic'])
                                    ? $field['conditional_logic']
                                    : false,
        ];

        // Handle repeater & group — normalize sub_fields recursively
        if (in_array($field['type'], ['repeater', 'group', 'flexible_content'])) {
            $sub = $field['sub_fields'] ?? [];
            $normalized_field['sub_fields'] = klscms_normalize_fields($sub);
        }

        // Handle select, checkbox, radio — normalize choices
        if (in_array($field['type'], ['select', 'checkbox', 'radio', 'button_group'])) {
            $normalized_field['choices'] = $field['choices'] ?? [];
        }

        // Handle image / file — return_format
        if (in_array($field['type'], ['image', 'file'])) {
            $normalized_field['return_format'] = $field['return_format'] ?? 'array';
        }

        // Handle relationship / post_object — post_type filter
        if (in_array($field['type'], ['relationship', 'post_object'])) {
            $normalized_field['post_type'] = $field['post_type'] ?? [];
        }

        // Handle taxonomy field
        if ($field['type'] === 'taxonomy') {
            $normalized_field['taxonomy']     = $field['taxonomy']     ?? '';
            $normalized_field['field_type']   = $field['field_type']   ?? 'select';
            $normalized_field['return_format'] = $field['return_format'] ?? 'id';
        }

        // Handle number field
        if ($field['type'] === 'number') {
            $normalized_field['min']  = $field['min']  ?? '';
            $normalized_field['max']  = $field['max']  ?? '';
            $normalized_field['step'] = $field['step'] ?? '';
        }

        // Handle true_false
        if ($field['type'] === 'true_false') {
            $normalized_field['ui']            = $field['ui']            ?? 0;
            $normalized_field['default_value'] = $field['default_value'] ?? 0;
        }

        // Handle link field
        if ($field['type'] === 'link') {
            $normalized_field['return_format'] = $field['return_format'] ?? 'array';
        }

        $normalized[] = $normalized_field;
    }

    return $normalized;
}