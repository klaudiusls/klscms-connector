<?php
/**
 * Plugin Name: KLS CMS Connector
 * Plugin URI: https://cms.klaudiusls.com
 * Description: Connects WordPress to KLS CMS Portal via REST API.
 * Version: 1.5.0
 * Author: KLS CMS
 * Author URI: https://cms.klaudiusls.com
 * License: GPL2
 * Text Domain: klscms-connector
 */

if (!defined('ABSPATH')) {
    exit;
}

define('KLSCMS_VERSION', '1.6.0');
define('KLSCMS_PLUGIN_DIR', plugin_dir_path(__FILE__));

// Includes
require_once KLSCMS_PLUGIN_DIR . 'includes/security.php';
require_once KLSCMS_PLUGIN_DIR . 'includes/auth.php';
require_once KLSCMS_PLUGIN_DIR . 'includes/rest-routes.php';
require_once KLSCMS_PLUGIN_DIR . 'includes/content-handler.php';
require_once KLSCMS_PLUGIN_DIR . 'includes/media-handler.php';
require_once KLSCMS_PLUGIN_DIR . 'includes/submission-handler.php';
require_once KLSCMS_PLUGIN_DIR . 'services/meta-service.php';
require_once KLSCMS_PLUGIN_DIR . 'services/page-resolver.php';


// Admin Settings
if (is_admin()) {
    require_once KLSCMS_PLUGIN_DIR . 'includes/admin-settings.php';
}

// Database Installation (Run on activation)
register_activation_hook(__FILE__, 'klscms_install');

function klscms_install() {
    require_once KLSCMS_PLUGIN_DIR . 'database/install.php';
    klscms_connector_install();
}

/**
 * Register Elementor Dynamic Tags
 * Must hook into elementor/dynamic_tags/register
 * which fires after Elementor is fully loaded
 */
add_action('elementor/dynamic_tags/register', 'klscms_register_dynamic_tags');

function klscms_register_dynamic_tags($dynamic_tags_manager) {
    // Register KLS CMS group
    \Elementor\Plugin::$instance->dynamic_tags->register_group(
        'klscms',
        ['title' => 'KLS CMS']
    );

    // Load tag classes
    require_once plugin_dir_path(__FILE__) . 'elementor/class-klscms-dynamic-tag.php';
    require_once plugin_dir_path(__FILE__) . 'elementor/class-klscms-image-tag.php';

    // Register tags
    $dynamic_tags_manager->register(new KLSCMS_Dynamic_Tag());
    $dynamic_tags_manager->register(new KLSCMS_Image_Tag());
}

add_action('admin_notices', 'klscms_dependency_notices');

function klscms_dependency_notices() {
    // Only show on relevant admin pages
    $screen = get_current_screen();
    
    $elementor_active = defined('ELEMENTOR_VERSION') || class_exists('\Elementor\Plugin');
    
    $litespeed_active = class_exists('LiteSpeed\Core') || class_exists('LiteSpeed\Purge') || defined('LSCWP_V');

    // Check Elementor
    if (!$elementor_active): ?>
        <div class="notice notice-warning is-dismissible">
            <p>
                <strong>KLS CMS Connector</strong> works best with <strong>Elementor</strong> for dynamic content rendering.
                <a href="https://wordpress.org/plugins/elementor/" target="_blank" style="margin-left:8px">
                   Install Elementor →
                </a>
            </p>
        </div>
    <?php endif;

    // Check LiteSpeed Cache
    if (!$litespeed_active): ?>
        <div class="notice notice-info is-dismissible">
            <p>
                <strong>KLS CMS Connector</strong> recommends <strong>LiteSpeed Cache</strong> for automatic cache purging after content sync.
                Without it, you may need to manually clear cache after each sync.
                <a href="https://wordpress.org/plugins/litespeed-cache/" target="_blank" style="margin-left:8px">
                   Install LiteSpeed Cache →
                </a>
            </p>
        </div>
    <?php endif;

    // Both active — show success on plugin settings page only
    if ($elementor_active && $litespeed_active && isset($_GET['page']) && $_GET['page'] === 'klscms-settings'): ?>
        <div class="notice notice-success is-dismissible">
            <p>
                <strong>KLS CMS Connector</strong> — All recommended plugins are active.
                ✓ Elementor &nbsp; ✓ LiteSpeed Cache
            </p>
        </div>
    <?php endif;
}
