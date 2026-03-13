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
            // We verify the raw key against the hash, but we don't store raw key if we want max security.
            // However, to display it back or for simple validation we might need a strategy.
            // For this implementation: we store the HASH for validation.
            // We store a masked version or just empty for display.
        } else {
            delete_option('klscms_api_key_hash');
        }
        
        echo '<div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>';
    }

    $portal_url = 'https://cms.klaudiusls.com'; 
    $has_key = get_option('klscms_api_key_hash') ? true : false;

    ?>
    <div class="wrap">
        <h1>KLS CMS Connector</h1>
        
        <div class="card" style="max-width: 600px; padding: 20px; margin-top: 20px;">
            <h2>Connection Settings</h2>
            <p>Configure the connection to the KLS CMS Portal.</p>
            
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
                            <input type="password" name="klscms_api_key" class="regular-text" placeholder="<?php echo $has_key ? 'Key is set (hidden)' : 'Enter API Key'; ?>">
                            <p class="description">Enter the API Key generated in the KLS CMS Portal.</p>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" name="klscms_save_settings" class="button button-primary">Save Settings</button>
                </p>
            </form>
        </div>

        <div class="card" style="max-width: 600px; padding: 20px; margin-top: 20px;">
            <h2>Status</h2>
            <p>
                <strong>Plugin Status:</strong> <span style="color: green;">Active</span><br>
                <strong>Key Configured:</strong> 
                <?php if ($has_key): ?>
                    <span style="color: green;">Yes</span>
                <?php else: ?>
                    <span style="color: red;">No</span>
                <?php endif; ?>
            </p>
            
            <hr>
            
            <h3>Test Connection</h3>
            <button type="button" id="klscms-test-connection" class="button">Test Portal Connection</button>
            <span id="klscms-connection-result" style="margin-left: 10px; font-weight: bold;"></span>
            
            <script>
            jQuery(document).ready(function($) {
                $('#klscms-test-connection').on('click', function() {
                    var $btn = $(this);
                    var $res = $('#klscms-connection-result');
                    
                    $btn.prop('disabled', true).text('Testing...');
                    $res.text('').css('color', 'black');
                    
                    // Simple ping to Portal Health Check (if portal allows CORS/public ping)
                    // Or check local plugin health
                    $.get('<?php echo get_rest_url(null, 'klscms/v1/health'); ?>')
                        .done(function(data) {
                            if (data.status === 'ok') {
                                $res.text('Connected (Plugin Active)').css('color', 'green');
                            } else {
                                $res.text('Plugin Error').css('color', 'red');
                            }
                        })
                        .fail(function() {
                            $res.text('Connection Failed').css('color', 'red');
                        })
                        .always(function() {
                            $btn.prop('disabled', false).text('Test Portal Connection');
                        });
                });
            });
            </script>
        </div>
    </div>
    <?php
}
