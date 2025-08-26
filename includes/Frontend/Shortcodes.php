<?php
/**
 * Shortcodes
 *
 * @package FP\Esperienze\Frontend
 */

namespace FP\Esperienze\Frontend;

defined('ABSPATH') || exit;

/**
 * Shortcodes class
 */
class Shortcodes {

    /**
     * Constructor
     */
    public function __construct() {
        add_shortcode('fp_exp_archive', [$this, 'experienceArchive']);
    }

    /**
     * Experience archive shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string
     */
    public function experienceArchive(array $atts = []): string {
        $atts = shortcode_atts([
            'posts_per_page' => 12,
            'columns'        => 3,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ], $atts, 'fp_exp_archive');

        // Query experience products
        $args = [
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => intval($atts['posts_per_page']),
            'orderby'        => sanitize_text_field($atts['orderby']),
            'order'          => strtoupper(sanitize_text_field($atts['order'])),
            'meta_query'     => [
                [
                    'key'     => '_product_type',
                    'value'   => 'experience',
                    'compare' => '='
                ]
            ]
        ];

        $products = new \WP_Query($args);

        if (!$products->have_posts()) {
            return '<p>' . __('No experiences found.', 'fp-esperienze') . '</p>';
        }

        $columns_class = 'columns-' . intval($atts['columns']);
        
        ob_start();
        ?>
        <div class="fp-experience-archive <?php echo esc_attr($columns_class); ?>">
            <div class="fp-experience-grid">
                <?php while ($products->have_posts()) : $products->the_post(); ?>
                    <?php $this->renderExperienceCard(get_the_ID()); ?>
                <?php endwhile; ?>
            </div>
        </div>
        <?php
        wp_reset_postdata();
        
        return ob_get_clean();
    }

    /**
     * Render experience card
     *
     * @param int $product_id Product ID
     */
    private function renderExperienceCard(int $product_id): void {
        $product = wc_get_product($product_id);
        if (!$product) {
            return;
        }

        $image_id = $product->get_image_id();
        $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'medium') : wc_placeholder_img_src();
        $duration = get_post_meta($product_id, '_experience_duration', true);
        $adult_price = get_post_meta($product_id, '_experience_adult_price', true);
        ?>
        <div class="fp-experience-card">
            <div class="fp-experience-image">
                <a href="<?php echo esc_url(get_permalink($product_id)); ?>">
                    <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($product->get_name()); ?>" />
                </a>
                <?php if ($duration) : ?>
                    <div class="fp-experience-duration">
                        <?php printf(__('%d min', 'fp-esperienze'), intval($duration)); ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="fp-experience-content">
                <h3 class="fp-experience-title">
                    <a href="<?php echo esc_url(get_permalink($product_id)); ?>">
                        <?php echo esc_html($product->get_name()); ?>
                    </a>
                </h3>
                
                <div class="fp-experience-excerpt">
                    <?php echo wp_kses_post(wp_trim_words($product->get_short_description(), 20)); ?>
                </div>
                
                <div class="fp-experience-meta">
                    <?php if ($adult_price) : ?>
                        <div class="fp-experience-price">
                            <?php printf(__('From %s', 'fp-esperienze'), wc_price($adult_price)); ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="fp-experience-actions">
                        <a href="<?php echo esc_url(get_permalink($product_id)); ?>" class="fp-btn fp-btn-primary">
                            <?php _e('View Details', 'fp-esperienze'); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}