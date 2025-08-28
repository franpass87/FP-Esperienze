<?php
/**
 * Template overrides
 *
 * @package FP\Esperienze\Frontend
 */

namespace FP\Esperienze\Frontend;

defined('ABSPATH') || exit;

/**
 * Templates class
 */
class Templates {

    /**
     * Constructor
     */
    public function __construct() {
        // Use higher priority to ensure our template override works
        add_filter('single_template', [$this, 'singleExperienceTemplate'], 99);
        add_filter('wc_get_template', [$this, 'getTemplate'], 10, 5);
        
        // Additional template hooks for better compatibility
        add_filter('template_include', [$this, 'templateInclude'], 99);
        add_action('woocommerce_single_product_summary', [$this, 'addExperienceNotice'], 5);
    }

    /**
     * Override single experience template
     *
     * @param string $template Template path
     * @return string
     */
    public function singleExperienceTemplate(string $template): string {
        global $post;

        if ($post && $post->post_type === 'product') {
            $product = wc_get_product($post->ID);
            if ($product && $product->get_type() === 'experience') {
                $custom_template = FP_ESPERIENZE_PLUGIN_DIR . 'templates/single-experience.php';
                if (file_exists($custom_template)) {
                    return $custom_template;
                }
            }
        }

        return $template;
    }

    /**
     * Get template override
     *
     * @param string $template Template path
     * @param string $template_name Template name
     * @param array $args Template arguments
     * @param string $template_path Template path
     * @param string $default_path Default path
     * @return string
     */
    public function getTemplate($template, $template_name, $args, $template_path, $default_path): string {
        // Check if it's our plugin template
        if (strpos($template_name, 'fp-esperienze/') === 0) {
            $plugin_template = FP_ESPERIENZE_PLUGIN_DIR . 'templates/' . str_replace('fp-esperienze/', '', $template_name);
            if (file_exists($plugin_template)) {
                return $plugin_template;
            }
        }

        return $template;
    }
    
    /**
     * Additional template include filter for better compatibility
     *
     * @param string $template Template path
     * @return string
     */
    public function templateInclude(string $template): string {
        if (is_singular('product')) {
            global $post;
            $product = wc_get_product($post->ID);
            
            if ($product && $product->get_type() === 'experience') {
                $custom_template = FP_ESPERIENZE_PLUGIN_DIR . 'templates/single-experience.php';
                if (file_exists($custom_template)) {
                    return $custom_template;
                }
            }
        }
        
        return $template;
    }
    
    /**
     * Add experience notice to product page for debugging
     */
    public function addExperienceNotice(): void {
        global $product;
        
        if ($product && $product->get_type() === 'experience') {
            // Add a hidden debug marker to verify our hooks are working
            echo '<!-- FP Esperienze: Experience product detected -->';
        }
    }
}