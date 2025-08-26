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
        add_filter('single_template', [$this, 'singleExperienceTemplate']);
        add_filter('wc_get_template', [$this, 'getTemplate'], 10, 5);
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
}