<?php
/**
 * Single Experience Template
 *
 * @package FP\Esperienze
 */

defined('ABSPATH') || exit;

use FP\Esperienze\Data\ExtraManager;

get_header();

global $post;
$product = wc_get_product($post->ID);

if (!$product || $product->get_type() !== 'experience') {
    get_template_part('404');
    get_footer();
    return;
}

$image_id = $product->get_image_id();
$image_url = $image_id ? wp_get_attachment_image_url($image_id, 'large') : wc_placeholder_img_src();
$duration = get_post_meta($product->get_id(), '_experience_duration', true);
$capacity = get_post_meta($product->get_id(), '_experience_capacity', true);
$languages = get_post_meta($product->get_id(), '_experience_languages', true);
$adult_price = get_post_meta($product->get_id(), '_experience_adult_price', true);
$child_price = get_post_meta($product->get_id(), '_experience_child_price', true);
$extras = ExtraManager::getProductExtras($product->get_id());
?>

<div class="fp-experience-single">
    <!-- Hero Section -->
    <section class="fp-experience-hero">
        <div class="fp-hero-image">
            <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($product->get_name()); ?>" />
        </div>
        <div class="fp-hero-content">
            <div class="container">
                <h1 class="fp-experience-title"><?php echo esc_html($product->get_name()); ?></h1>
                <div class="fp-experience-meta">
                    <?php if ($duration) : ?>
                        <span class="fp-meta-item">
                            <i class="fp-icon-clock"></i>
                            <?php printf(__('%d minutes', 'fp-esperienze'), intval($duration)); ?>
                        </span>
                    <?php endif; ?>
                    
                    <?php if ($capacity) : ?>
                        <span class="fp-meta-item">
                            <i class="fp-icon-users"></i>
                            <?php printf(__('Max %d participants', 'fp-esperienze'), intval($capacity)); ?>
                        </span>
                    <?php endif; ?>
                    
                    <?php if ($languages) : ?>
                        <span class="fp-meta-item">
                            <i class="fp-icon-language"></i>
                            <?php echo esc_html($languages); ?>
                        </span>
                    <?php endif; ?>
                </div>
                
                <?php if ($adult_price) : ?>
                    <div class="fp-experience-price">
                        <span class="fp-price-label"><?php _e('From', 'fp-esperienze'); ?></span>
                        <span class="fp-price-amount"><?php echo wc_price($adult_price); ?></span>
                        <span class="fp-price-unit"><?php _e('per person', 'fp-esperienze'); ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <div class="container fp-experience-content">
        <div class="fp-content-grid">
            <!-- Main Content -->
            <div class="fp-main-content">
                <!-- USP Section -->
                <section class="fp-experience-usp">
                    <h2><?php _e('Why Choose This Experience', 'fp-esperienze'); ?></h2>
                    <ul class="fp-usp-list">
                        <li><?php _e('Professional local guide', 'fp-esperienze'); ?></li>
                        <li><?php _e('Small group experience', 'fp-esperienze'); ?></li>
                        <li><?php _e('Skip-the-line access', 'fp-esperienze'); ?></li>
                        <li><?php _e('Instant confirmation', 'fp-esperienze'); ?></li>
                    </ul>
                </section>

                <!-- Description -->
                <section class="fp-experience-description">
                    <h2><?php _e('Description', 'fp-esperienze'); ?></h2>
                    <div class="fp-description-content">
                        <?php echo wp_kses_post($product->get_description()); ?>
                    </div>
                </section>

                <!-- What's Included -->
                <section class="fp-experience-included">
                    <h2><?php _e("What's Included", 'fp-esperienze'); ?></h2>
                    <ul class="fp-included-list">
                        <li><?php _e('Professional guide', 'fp-esperienze'); ?></li>
                        <li><?php _e('Entrance fees', 'fp-esperienze'); ?></li>
                        <li><?php _e('Photo opportunities', 'fp-esperienze'); ?></li>
                    </ul>
                </section>

                <!-- What's Not Included -->
                <section class="fp-experience-excluded">
                    <h2><?php _e("What's Not Included", 'fp-esperienze'); ?></h2>
                    <ul class="fp-excluded-list">
                        <li><?php _e('Hotel pickup and drop-off', 'fp-esperienze'); ?></li>
                        <li><?php _e('Food and drinks', 'fp-esperienze'); ?></li>
                        <li><?php _e('Gratuities', 'fp-esperienze'); ?></li>
                    </ul>
                </section>

                <!-- Reviews Placeholder -->
                <section class="fp-experience-reviews">
                    <h2><?php _e('Customer Reviews', 'fp-esperienze'); ?></h2>
                    <div class="fp-reviews-placeholder">
                        <div class="fp-review-summary">
                            <div class="fp-rating-stars">
                                <span class="fp-stars">★★★★★</span>
                                <span class="fp-rating-text">4.8 (324 reviews)</span>
                            </div>
                        </div>
                        <div class="fp-review-item">
                            <div class="fp-review-header">
                                <strong>Marco R.</strong>
                                <span class="fp-review-date">2 days ago</span>
                            </div>
                            <div class="fp-review-stars">★★★★★</div>
                            <p><?php _e('Amazing experience! The guide was very knowledgeable and friendly. Highly recommended!', 'fp-esperienze'); ?></p>
                        </div>
                    </div>
                </section>
            </div>

            <!-- Sidebar -->
            <div class="fp-sidebar">
                <!-- Booking Widget Placeholder -->
                <div class="fp-booking-widget">
                    <h3><?php _e('Book This Experience', 'fp-esperienze'); ?></h3>
                    <div class="fp-booking-form">
                        <p class="fp-booking-placeholder">
                            <?php _e('Booking widget will be implemented in future updates.', 'fp-esperienze'); ?>
                        </p>
                        
                        <!-- Extras Selection -->
                        <?php if (!empty($extras)) : ?>
                            <div class="fp-extras-section">
                                <h4><?php _e('Extras', 'fp-esperienze'); ?></h4>
                                <div class="fp-extras-list">
                                    <?php foreach ($extras as $extra) : ?>
                                        <div class="fp-extra-item" data-extra-id="<?php echo esc_attr($extra['id']); ?>" data-extra-price="<?php echo esc_attr($extra['price']); ?>" data-pricing-type="<?php echo esc_attr($extra['pricing_type']); ?>">
                                            <div class="fp-extra-header">
                                                <label class="fp-extra-label">
                                                    <input type="checkbox" 
                                                           name="experience_extras[]" 
                                                           value="<?php echo esc_attr($extra['id']); ?>"
                                                           class="fp-extra-checkbox"
                                                           <?php echo $extra['is_required'] ? 'checked disabled' : ''; ?>>
                                                    <span class="fp-extra-name"><?php echo esc_html($extra['name']); ?></span>
                                                    <span class="fp-extra-price"><?php echo wc_price($extra['price']); ?></span>
                                                    <?php if ($extra['pricing_type'] === 'per_person') : ?>
                                                        <span class="fp-extra-type"><?php _e('per person', 'fp-esperienze'); ?></span>
                                                    <?php else : ?>
                                                        <span class="fp-extra-type"><?php _e('per booking', 'fp-esperienze'); ?></span>
                                                    <?php endif; ?>
                                                </label>
                                            </div>
                                            
                                            <?php if (!empty($extra['description'])) : ?>
                                                <div class="fp-extra-description">
                                                    <small><?php echo esc_html($extra['description']); ?></small>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if ($extra['max_quantity'] > 1) : ?>
                                                <div class="fp-extra-quantity" style="display: none;">
                                                    <label>
                                                        <?php _e('Quantity:', 'fp-esperienze'); ?>
                                                        <input type="number" 
                                                               name="extra_qty_<?php echo esc_attr($extra['id']); ?>" 
                                                               class="fp-extra-quantity-input"
                                                               min="1" 
                                                               max="<?php echo esc_attr($extra['max_quantity']); ?>" 
                                                               value="1">
                                                    </label>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="fp-price-info">
                            <?php if ($adult_price) : ?>
                                <div class="fp-price-row">
                                    <span><?php _e('Adult', 'fp-esperienze'); ?></span>
                                    <span><?php echo wc_price($adult_price); ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($child_price) : ?>
                                <div class="fp-price-row">
                                    <span><?php _e('Child', 'fp-esperienze'); ?></span>
                                    <span><?php echo wc_price($child_price); ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Dynamic extras total will be added here -->
                            <div id="fp-extras-total" style="display: none;">
                                <div class="fp-price-row">
                                    <span><?php _e('Extras', 'fp-esperienze'); ?></span>
                                    <span id="fp-extras-total-amount">-</span>
                                </div>
                            </div>
                            
                            <div class="fp-price-row fp-total-price">
                                <strong>
                                    <span><?php _e('Total', 'fp-esperienze'); ?></span>
                                    <span id="fp-total-amount">
                                        <?php 
                                        $base_total = floatval($adult_price) + floatval($child_price);
                                        echo wc_price($base_total); 
                                        ?>
                                    </span>
                                </strong>
                            </div>
                        </div>
                        <button class="fp-btn fp-btn-primary fp-btn-large" disabled>
                            <?php _e('Coming Soon', 'fp-esperienze'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php get_footer(); ?>