<?php
/**
 * Single Experience Template
 *
 * @package FP\Esperienze
 */

defined('ABSPATH') || exit;

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
                <!-- Booking Widget -->
                <div class="fp-booking-widget">
                    <h3><?php _e('Book This Experience', 'fp-esperienze'); ?></h3>
                    <div class="fp-booking-form">
                        
                        <!-- Date Picker -->
                        <div class="fp-form-field">
                            <label for="fp-date-picker"><?php _e('Select Date', 'fp-esperienze'); ?></label>
                            <input type="date" id="fp-date-picker" class="fp-date-input" min="<?php echo date('Y-m-d'); ?>" />
                        </div>

                        <!-- Time Slots -->
                        <div class="fp-form-field">
                            <label><?php _e('Available Times', 'fp-esperienze'); ?></label>
                            <div id="fp-time-slots" class="fp-time-slots">
                                <p class="fp-slots-placeholder"><?php _e('Please select a date to see available times.', 'fp-esperienze'); ?></p>
                            </div>
                        </div>

                        <!-- Language Selection -->
                        <div class="fp-form-field">
                            <label for="fp-language"><?php _e('Language', 'fp-esperienze'); ?></label>
                            <select id="fp-language" class="fp-select">
                                <option value="English"><?php _e('English', 'fp-esperienze'); ?></option>
                                <option value="Italian"><?php _e('Italian', 'fp-esperienze'); ?></option>
                                <option value="Spanish"><?php _e('Spanish', 'fp-esperienze'); ?></option>
                            </select>
                        </div>

                        <!-- Quantity Selectors -->
                        <div class="fp-form-field">
                            <label><?php _e('Participants', 'fp-esperienze'); ?></label>
                            <div class="fp-quantity-row">
                                <span><?php _e('Adults', 'fp-esperienze'); ?></span>
                                <div class="fp-quantity-controls">
                                    <button type="button" class="fp-qty-btn fp-qty-minus" data-target="fp-qty-adult">−</button>
                                    <input type="number" id="fp-qty-adult" class="fp-qty-input" value="1" min="0" max="<?php echo esc_attr($capacity ?: 10); ?>" readonly />
                                    <button type="button" class="fp-qty-btn fp-qty-plus" data-target="fp-qty-adult">+</button>
                                </div>
                                <span class="fp-price-per"><?php echo wc_price($adult_price ?: 0); ?></span>
                            </div>
                            
                            <?php if ($child_price) : ?>
                            <div class="fp-quantity-row">
                                <span><?php _e('Children', 'fp-esperienze'); ?></span>
                                <div class="fp-quantity-controls">
                                    <button type="button" class="fp-qty-btn fp-qty-minus" data-target="fp-qty-child">−</button>
                                    <input type="number" id="fp-qty-child" class="fp-qty-input" value="0" min="0" max="<?php echo esc_attr($capacity ?: 10); ?>" readonly />
                                    <button type="button" class="fp-qty-btn fp-qty-plus" data-target="fp-qty-child">+</button>
                                </div>
                                <span class="fp-price-per"><?php echo wc_price($child_price); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Total Price -->
                        <div class="fp-total-price">
                            <div class="fp-price-breakdown">
                                <div id="fp-price-details"></div>
                                <div class="fp-total-row">
                                    <strong><?php _e('Total', 'fp-esperienze'); ?>: <span id="fp-total-amount"><?php echo wc_price(0); ?></span></strong>
                                </div>
                            </div>
                        </div>

                        <!-- Add to Cart Button -->
                        <button type="button" id="fp-add-to-cart" class="fp-btn fp-btn-primary fp-btn-large" disabled>
                            <?php _e('Add to Cart', 'fp-esperienze'); ?>
                        </button>

                        <!-- Loading Indicator -->
                        <div id="fp-loading" class="fp-loading" style="display: none;">
                            <p><?php _e('Loading...', 'fp-esperienze'); ?></p>
                        </div>

                        <!-- Error Messages -->
                        <div id="fp-error-messages" class="fp-error-messages"></div>
                    </div>
                    
                    <!-- Hidden Fields -->
                    <input type="hidden" id="fp-product-id" value="<?php echo esc_attr($product->get_id()); ?>" />
                    <input type="hidden" id="fp-selected-slot" value="" />
                    <input type="hidden" id="fp-adult-price" value="<?php echo esc_attr($adult_price ?: 0); ?>" />
                    <input type="hidden" id="fp-child-price" value="<?php echo esc_attr($child_price ?: 0); ?>" />
                    <input type="hidden" id="fp-capacity" value="<?php echo esc_attr($capacity ?: 10); ?>" />
                </div>
            </div>
        </div>
    </div>
</div>

<?php get_footer(); ?>