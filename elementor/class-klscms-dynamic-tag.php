<?php 
if (!defined('ABSPATH')) exit; 

class KLSCMS_Dynamic_Tag extends \Elementor\Core\DynamicTags\Tag { 

    public function get_name() { 
        return 'klscms-field'; 
    } 

    public function get_title() { 
        return __('KLS CMS Field', 'klscms'); 
    } 

    public function get_group() { 
        return 'klscms'; 
    } 

    public function get_categories() { 
        return [ 
            \Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY, 
            \Elementor\Modules\DynamicTags\Module::URL_CATEGORY, 
            \Elementor\Modules\DynamicTags\Module::IMAGE_CATEGORY, 
            \Elementor\Modules\DynamicTags\Module::MEDIA_CATEGORY, 
        ]; 
    } 

    protected function register_controls() { 
        $this->add_control( 
            'meta_key', 
            [ 
                'label'       => __('Meta Key', 'klscms'), 
                'type'        => \Elementor\Controls_Manager::TEXT, 
                'placeholder' => 'kls_home_hero_1_title', 
                'description' => __('Enter the KLS CMS meta key for this field.', 'klscms'), 
            ] 
        ); 
    } 

    public function render() { 
        $meta_key = $this->get_settings('meta_key'); 
        if (empty($meta_key)) return; 

        $value = get_post_meta(get_the_ID(), $meta_key, true); 
        if (empty($value)) return; 

        // Handle image field (stored as URL) 
        if ($this->get_categories()[0] === \Elementor\Modules\DynamicTags\Module::IMAGE_CATEGORY) { 
            echo esc_url($value); 
            return; 
        } 

        echo wp_kses_post($value); 
    } 
} 
