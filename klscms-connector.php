
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
