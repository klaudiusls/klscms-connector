<?php
/**
 * Plugin Name: KLS CMS Connector
 * Plugin URI: https://cms.klaudiusls.com
 * Description: Connects WordPress to KLS CMS Portal via REST API.
 * Version: 1.2.0
 * Author: KLS CMS
 * Author URI: https://cms.klaudiusls.com
 * License: GPL2
 * Text Domain: klscms-connector
 */

if (!defined('ABSPATH')) {
    exit;
}

define('KLSCMS_VERSION', '1.2.0');
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

// Register Elementor Dynamic Tags
add_action('plugins_loaded', function() {
    if (!did_action('elementor/loaded')) {
        add_action('elementor/loaded', 'klscms_register_elementor_tags');
    } else {
        klscms_register_elementor_tags();
    }
});

function klscms_register_elementor_tags() {
    add_action('elementor/dynamic_tags/register', function($manager) {
        \Elementor\Plugin::$instance->dynamic_tags->register_group(
            'klscms', ['title' => 'KLS CMS']
        );
        require_once KLSCMS_PLUGIN_DIR . 'elementor/class-klscms-dynamic-tag.php';
        require_once KLSCMS_PLUGIN_DIR . 'elementor/class-klscms-image-tag.php';
        $manager->register(new KLSCMS_Dynamic_Tag());
        $manager->register(new KLSCMS_Image_Tag());
    });
}


