<?php
if (!defined('ABSPATH')) exit;

function klscms_get_site_structure(WP_REST_Request $request): WP_REST_Response 
{
    $structure = [];

    // 1. PAGES — semua published pages
    $pages = get_pages([
        'post_status' => 'publish',
        'sort_column' => 'menu_order',
        'sort_order'  => 'ASC',
    ]);

    $structure['pages'] = array_map(function($page) {
        return [
            'id'       => $page->ID,
            'title'    => $page->post_title,
            'slug'     => $page->post_name,
            'template' => get_page_template_slug($page->ID) ?: 'default',
            'url'      => get_permalink($page->ID),
            'parent'   => $page->post_parent,
        ];
    }, $pages);

    // 2. POST TYPES — semua public CPT (exclude built-in yang tidak perlu)
    $post_types = get_post_types([
        'public'   => true,
        '_builtin' => false,
    ], 'objects');

    // Tambahkan 'post' (blog posts) secara eksplisit
    $builtin_types = ['post'];

    $structure['post_types'] = [];

    // Built-in post type yang relevan
    foreach ($builtin_types as $type_slug) {
        $type = get_post_type_object($type_slug);
        if ($type) {
            $structure['post_types'][] = [
                'slug'     => $type->name,
                'label'    => $type->label,
                'singular' => $type->labels->singular_name,
                'builtin'  => true,
                'supports' => get_all_post_type_supports($type->name),
            ];
        }
    }

    // Custom Post Types
    foreach ($post_types as $type) {
        // Skip attachment dan revision
        if (in_array($type->name, ['attachment', 'revision', 
                                    'nav_menu_item', 'custom_css', 
                                    'customize_changeset'])) {
            continue;
        }

        $structure['post_types'][] = [
            'slug'     => $type->name,
            'label'    => $type->label,
            'singular' => $type->labels->singular_name,
            'builtin'  => false,
            'supports' => get_all_post_type_supports($type->name),
        ];
    }

    // 3. SITE INFO
    $structure['site_info'] = [
        'name'         => get_bloginfo('name'),
        'url'          => get_site_url(),
        'wp_version'   => get_bloginfo('version'),
        'language'     => get_bloginfo('language'),
        'total_pages'  => count($structure['pages']),
        'total_cpts'   => count($structure['post_types']),
    ];

    return new WP_REST_Response($structure, 200);
}
