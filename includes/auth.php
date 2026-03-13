<?php

if (!defined('ABSPATH')) {
    exit;
}

function klscms_get_api_key() {
    $key = isset($_SERVER['HTTP_X_KLSCMS_KEY']) ? sanitize_text_field($_SERVER['HTTP_X_KLSCMS_KEY']) : '';
    return $key;
}

function klscms_validate_api_key($request = null) {
    $key = klscms_get_api_key();
    
    if (!$key) {
        return new WP_Error('klscms_unauthorized', 'Missing API Key', ['status' => 401]);
    }

    // 1. Rate Limit Check
    if (!klscms_check_rate_limit($key)) {
        return new WP_Error('klscms_rate_limit', 'Rate limit exceeded', ['status' => 429]);
    }

    // 2. Origin Check (Optional but recommended)
    // if (!klscms_validate_origin()) {
    //    return new WP_Error('klscms_forbidden', 'Invalid Origin', ['status' => 403]);
    // }

    // 3. Hash Validation
    // We check against the stored hash
    $stored_hash = get_option('klscms_api_key_hash');
    
    if (!$stored_hash) {
        // Fallback for migration: check legacy array
        $legacy_stored = get_option('klscms_api_keys', []);
        if (is_string($legacy_stored)) {
            $legacy_stored = json_decode($legacy_stored, true);
        }
        if (is_array($legacy_stored) && in_array($key, $legacy_stored, true)) {
            return true; // Valid legacy key
        }
        return new WP_Error('klscms_unauthorized', 'API Key not configured', ['status' => 401]);
    }

    $request_hash = hash_hmac('sha256', $key, wp_salt('auth'));
    
    if (!hash_equals($stored_hash, $request_hash)) {
        return new WP_Error('klscms_unauthorized', 'Invalid API Key', ['status' => 401]);
    }

    return true;
}
