<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Security Helper Functions
 */

// Rate Limiting
function klscms_check_rate_limit($key) {
    // Hash key for transient name to avoid exposing it
    $key_hash = substr(md5($key), 0, 10);
    $transient_name = 'klscms_limit_' . $key_hash;
    
    $current_count = get_transient($transient_name);
    
    if ($current_count === false) {
        set_transient($transient_name, 1, 60); // 1 minute
        return true;
    }
    
    if ($current_count >= 30) {
        return false; // Limit exceeded
    }
    
    set_transient($transient_name, $current_count + 1, 60);
    return true;
}

// Origin Validation
function klscms_validate_origin() {
    $allowed_origin = 'cms.klaudiusls.com';
    
    $origin = '';
    if (isset($_SERVER['HTTP_ORIGIN'])) {
        $origin = $_SERVER['HTTP_ORIGIN'];
    } elseif (isset($_SERVER['HTTP_X_KLSCMS_ORIGIN'])) {
        $origin = $_SERVER['HTTP_X_KLSCMS_ORIGIN'];
    } elseif (isset($_SERVER['HTTP_REFERER'])) {
        $origin = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST);
    }

    // Strip protocol for comparison if needed, or check strict string
    // Simple check: does origin contain our allowed domain?
    if (strpos($origin, $allowed_origin) !== false) {
        return true;
    }
    
    // In strict mode we might return false, but for now we log or return false
    return false;
}

// Request Sanitization
function klscms_sanitize_request_data($data) {
    if (is_array($data)) {
        foreach ($data as $key => $value) {
            $data[$key] = klscms_sanitize_request_data($value);
        }
        return $data;
    }
    
    if (is_string($data)) {
        // Allow some HTML for rich text fields, restrict others
        // For general safety, we use wp_kses_post for content fields
        // and sanitize_text_field for everything else?
        // Context matters. For now, aggressive sanitization.
        
        // If it looks like JSON, skip or decode?
        // We assume strings are text.
        return wp_kses_post($data); 
    }
    
    return $data;
}
