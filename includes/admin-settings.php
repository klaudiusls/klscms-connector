<?php

if (!defined('ABSPATH')) {
    exit;
}

// Register Settings Menu
add_action('admin_menu', 'klscms_register_settings_page');

function klscms_register_settings_page() {
    add_options_page(
        'KLS CMS Connector',
        'KLS CMS',
        'manage_options',
        'klscms-settings',
        'klscms_settings_page_html'
    );
}

// Render Settings Page
function klscms_settings_page_html() {
    if (!current_user_can('manage_options')) {
        return;
    }

    // Save Logic
    if (isset($_POST['klscms_save_settings']) && check_admin_referer('klscms_settings_verify')) {
        $api_key = sanitize_text_field($_POST['klscms_api_key']);
        
        // Store Hashed Key for security
        if (!empty($api_key)) {
            $hashed_key = hash_hmac('sha256', $api_key, wp_salt('auth'));
            update_option('klscms_api_key_hash', $hashed_key);
            // We save the raw key for masking purposes if needed, but for security best practices we usually don't.
            // However, to support the "Connected" logic and potential re-display (masked), we might need to store it or just rely on hash existence.
            // The previous implementation stored only hash. 
            // The prompt implies we check `get_option('klscms_api_key', '')` but we only stored hash before.
            // Let's store the raw key in 'klscms_api_key' as well to support the masking feature requested.
            update_option('klscms_api_key', $api_key);
        } else {
            delete_option('klscms_api_key_hash');
            delete_option('klscms_api_key');
        }
        
        echo '<div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>';
    }

    // Handle disconnect action
    if (isset($_POST['klscms_disconnect']) && check_admin_referer('klscms_disconnect_action')) {
        delete_option('klscms_api_key');
        delete_option('klscms_api_key_hash');
        delete_option('klscms_portal_url');
        add_settings_error(
            'klscms_messages',
            'klscms_disconnected',
            'Disconnected successfully. You can now enter a new API key.',
            'success'
        );
    }

    $portal_url = 'https://cms.klaudiusls.com'; 
    $api_key    = get_option('klscms_api_key', '');
    // Fallback: if we have hash but no raw key (from previous version), we consider it connected but can't mask the key.
    $has_hash = get_option('klscms_api_key_hash');
    $is_connected = !empty($api_key) || !empty($has_hash);

    // Mask API key for display
    function klscms_mask_api_key($key) {
        if (empty($key)) return '********'; // Fallback if we only have hash
        $visible = substr($key, 0, 8);
        return $visible . str_repeat('•', 8);
    }

    settings_errors('klscms_messages');
    ?>
    <div class="wrap">
        <h1>KLS CMS Connector</h1>
        
        <div class="card" style="max-width: 600px; padding: 20px; margin-top: 20px;">
            <h2>Connection Settings</h2>
            <p>Configure the connection to the KLS CMS Portal.</p>
            
            <?php if ($is_connected): ?>
                <!-- MODE B: Connected -->
                <div style="background:#f0fdf4;border:1px solid #86efac;border-radius:6px;padding:16px 20px;margin-bottom:20px;display:flex;align-items:center;justify-content:space-between">
                    <div>
                        <span style="color:#16a34a;font-weight:600;font-size:14px">
                            ● Connected to KLS CMS Portal
                        </span>
                        <div style="color:#666;font-size:12px;margin-top:4px">
                            <?php echo esc_html($portal_url); ?>
                        </div>
                    </div>
                    <form method="post">
                        <?php wp_nonce_field('klscms_disconnect_action'); ?>
                        <input type="hidden" name="klscms_disconnect" value="1">
                        <button type="submit" style="background:#dc2626;color:#fff;border:none;padding:8px 16px;border-radius:4px;cursor:pointer;font-size:13px;font-weight:500" onclick="return confirm('Disconnect from KLS CMS Portal? The API key will be removed.')">
                            Disconnect
                        </button>
                    </form>
                </div>

                <table class="form-table">
                    <tr>
                        <th scope="row">Portal URL</th>
                        <td>
                            <input type="text" value="<?php echo esc_attr($portal_url); ?>" class="regular-text" disabled style="background:#f9fafb;color:#6b7280">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">API Key</th>
                        <td>
                            <input type="text" value="<?php echo esc_attr(klscms_mask_api_key($api_key)); ?>" class="regular-text" disabled style="background:#f9fafb;color:#6b7280;font-family:monospace">
                            <p class="description">To change the API key, click Disconnect first.</p>
                        </td>
                    </tr>
                </table>
            <?php else: ?>
                <!-- MODE A: Not Connected -->
                <form method="post" action="">
                    <?php wp_nonce_field('klscms_settings_verify'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">Portal URL</th>
                            <td>
                                <input type="text" class="regular-text" value="<?php echo esc_attr($portal_url); ?>" readonly disabled>
                                <p class="description">The URL of the CMS Portal managing this site.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">API Key</th>
                            <td>
                                <input type="password" name="klscms_api_key" class="regular-text" placeholder="Enter API Key">
                                <p class="description">Enter the API Key generated in the KLS CMS Portal.</p>
                            </td>
                        </tr>
                    </table>

                    <p class="submit">
                        <button type="submit" name="klscms_save_settings" class="button button-primary">Save & Connect</button>
                    </p>
                </form>
            <?php endif; ?>
        </div>

        <div class="card" style="max-width: 600px; padding: 20px; margin-top: 20px;">
            <h2>Status</h2>
            <p>
                <strong>Plugin Status:</strong> <span style="color: green;">Active</span><br>
                <strong>Connection:</strong> 
                <?php if ($is_connected): ?>
                    <span style="color: green;">Connected</span>
                <?php else: ?>
                    <span style="color: red;">Not Connected</span>
                <?php endif; ?>
            </p>
            
            <hr>
            
            <h3>Test Connection</h3>
            <button type="button" id="klscms-test-connection" class="button">Test Portal Connection</button>
            <span id="klscms-connection-result" style="margin-left: 10px; font-weight: bold;"></span>
            
            <script>
            document.getElementById('klscms-test-connection').addEventListener('click', function() {
                var btn = this;
                var result = document.getElementById('klscms-connection-result');
                btn.disabled = true;
                result.innerHTML = 'Testing...';
                
                // Simple health check to self
                fetch('<?php echo get_rest_url(null, 'klscms/v1/health'); ?>')
                .then(response => response.json())
                .then(data => {
                    if(data.status === 'ok') {
                        result.innerHTML = '<span style="color:green">Plugin API is reachable!</span>';
                    } else {
                        result.innerHTML = '<span style="color:red">Error reaching API.</span>';
                    }
                    btn.disabled = false;
                })
                .catch(err => {
                    result.innerHTML = '<span style="color:red">Connection failed.</span>';
                    btn.disabled = false;
                });
            });
            </script>
        </div>

        <hr style="margin:30px 0">
        <h3 style="color:#1d2327">Plugin Requirements</h3>
        <table class="widefat" style="max-width:600px">
        <thead>
            <tr>
            <th>Plugin</th>
            <th>Status</th>
            <th>Purpose</th>
            </tr>
        </thead>
        <tbody>
            <tr>
            <td>
                <strong>Elementor</strong>
                <div style="font-size:11px;color:#666">
                Free or Pro
                </div>
            </td>
            <td>
                <?php if (defined('ELEMENTOR_VERSION') || class_exists('\Elementor\Plugin')): ?>
                <span style="color:#16a34a;font-weight:600">
                    ✓ Active
                </span>
                <div style="font-size:11px;color:#666">
                    v<?php echo defined('ELEMENTOR_VERSION') ? ELEMENTOR_VERSION : '–'; ?>
                </div>
                <?php else: ?>
                <span style="color:#dc2626;font-weight:600">
                    ✗ Not Active
                </span>
                <div>
                    <a href="https://wordpress.org/plugins/elementor/" target="_blank" style="font-size:12px">
                    Install →
                    </a>
                </div>
                <?php endif; ?>
            </td>
            <td style="font-size:12px;color:#666">
                Required for Dynamic Tags — renders CMS content in page builder without hardcoding
            </td>
            </tr>
            <tr>
            <td>
                <strong>LiteSpeed Cache</strong>
                <div style="font-size:11px;color:#666">
                Recommended
                </div>
            </td>
            <td>
                <?php 
                $ls_active = class_exists('LiteSpeed\Core') || class_exists('LiteSpeed\Purge') || defined('LSCWP_V');
                if ($ls_active): ?>
                <span style="color:#16a34a;font-weight:600">
                    ✓ Active
                </span>
                <div style="font-size:11px;color:#666">
                    v<?php echo defined('LSCWP_V') ? LSCWP_V : '–'; ?>
                </div>
                <?php else: ?>
                <span style="color:#f59e0b;font-weight:600">
                    ⚠ Not Active
                </span>
                <div>
                    <a href="https://wordpress.org/plugins/litespeed-cache/" target="_blank" style="font-size:12px">
                    Install →
                    </a>
                </div>
                <?php endif; ?>
            </td>
            <td style="font-size:12px;color:#666">
                Recommended for auto cache purge after sync. Without this, manual cache clearing required after each content update.
            </td>
            </tr>
            <tr>
            <td>
                <strong>Elementor Pro</strong>
                <div style="font-size:11px;color:#666">
                Optional
                </div>
            </td>
            <td>
                <?php 
                $el_pro = defined('ELEMENTOR_PRO_VERSION');
                if ($el_pro): ?>
                <span style="color:#16a34a;font-weight:600">
                    ✓ Active
                </span>
                <div style="font-size:11px;color:#666">
                    v<?php echo ELEMENTOR_PRO_VERSION; ?>
                </div>
                <?php else: ?>
                <span style="color:#6b7280;font-weight:600">
                    – Not Active
                </span>
                <?php endif; ?>
            </td>
            <td style="font-size:12px;color:#666">
                Optional — Free Elementor is sufficient for Dynamic Tags. Pro unlocks additional widget types.
            </td>
            </tr>
        </tbody>
        </table>
    </div>
    <?php
}
