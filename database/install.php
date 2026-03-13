<?php

function klscms_connector_install() {
    global $wpdb;
    $table = $wpdb->prefix . 'klscms_submissions';
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE $table (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        form_id varchar(191) NOT NULL,
        data longtext NOT NULL,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY form_id (form_id)
    ) $charset_collate;";
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
    if (get_option('klscms_api_keys') === false) {
        add_option('klscms_api_keys', []);
    }
}
