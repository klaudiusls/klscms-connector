<?php 
if (!defined('ABSPATH')) exit; 

class KLSCMS_Image_Tag extends \Elementor\Core\DynamicTags\Data_Tag { 

    public function get_name() { 
        return 'klscms-image'; 
    } 

    public function get_title() { 
        return __('KLS CMS Image', 'klscms'); 
    } 

    public function get_group() { 
        return 'klscms'; 
    } 

    public function get_categories() { 
        return [ 
            \Elementor\Modules\DynamicTags\Module::IMAGE_CATEGORY, 
        ]; 
    } 

    protected function register_controls() { 
        $this->add_control( 
            'meta_key', 
            [ 
                'label'       => __('Image Meta Key', 'klscms'), 
                'type'        => \Elementor\Controls_Manager::TEXT, 
                'placeholder' => 'kls_home_hero_1_image', 
            ] 
        ); 
    } 

    public function get_value(array $options = []) { 
        $meta_key = $this->get_settings('meta_key'); 
        if (empty($meta_key)) return []; 

        $url = get_post_meta(get_the_ID(), $meta_key, true); 
        if (empty($url)) return []; 

        // Try to get attachment ID from URL 
        $attachment_id = attachment_url_to_postid($url); 

        return [ 
            'id'  => $attachment_id ?: 0, 
            'url' => esc_url($url), 
        ]; 
    } 
} 
