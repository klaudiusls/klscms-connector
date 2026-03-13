<?php

function klscms_get_submissions(WP_REST_Request $request) {
    global $wpdb;
    $table = $wpdb->prefix . 'klscms_submissions';
    $form_id = $request->get_param('form_id');
    $page = max(1, intval($request->get_param('page')));
    $limit = 20;
    $offset = ($page - 1) * $limit;
    $where = '';
    $params = [];
    if ($form_id) {
        $where = 'WHERE form_id = %s';
        $params[] = sanitize_text_field($form_id);
    }
    $total = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table $where", $params));
    $rows = $wpdb->get_results($wpdb->prepare("SELECT id, form_id, data, created_at FROM $table $where ORDER BY id DESC LIMIT %d OFFSET %d", array_merge($params, [$limit, $offset])), ARRAY_A);
    return ['items' => $rows, 'page' => $page, 'total' => intval($total)];
}
