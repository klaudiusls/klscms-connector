<?php

function klscms_resolve_page_by_slug(string $slug): int
{
    $post = get_page_by_path($slug, OBJECT, ['page', 'post']);
    return $post ? intval($post->ID) : 0;
}
