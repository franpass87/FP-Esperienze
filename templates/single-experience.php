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

                <!-- Meeting Point -->
                <?php 
                $meeting_point_id = get_post_meta($product->get_id(), '_fp_exp_meeting_point_id', true);
                if ($meeting_point_id) {
                    $meeting_point = \FP\Esperienze\Data\MeetingPoint::get($meeting_point_id);
                    if ($meeting_point) :
                ?>
                <section class="fp-experience-meeting-point">
                    <h2><?php _e('Meeting Point', 'fp-esperienze'); ?></h2>
                    <div class="fp-meeting-point-info">
                        <div class="fp-meeting-point-details">
                            <h3><?php echo esc_html($meeting_point->name); ?></h3>
                            <p class="fp-meeting-address">
                                <strong><?php _e('Address:', 'fp-esperienze'); ?></strong><br>
                                <?php echo esc_html($meeting_point->address); ?>
                            </p>
                            
                            <?php if (!empty($meeting_point->note)) : ?>
                                <div class="fp-meeting-notes">
                                    <strong><?php _e('Instructions:', 'fp-esperienze'); ?></strong><br>
                                    <?php echo esc_html($meeting_point->note); ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="fp-meeting-actions">
                                <?php if ($meeting_point->latitude && $meeting_point->longitude) : ?>
                                    <a href="https://www.google.com/maps/search/?api=1&query=<?php echo urlencode($meeting_point->latitude . ',' . $meeting_point->longitude); ?>" 
                                       target="_blank" 
                                       class="fp-btn fp-btn-outline">
                                        <?php _e('Open in Google Maps', 'fp-esperienze'); ?>
                                    </a>
                                <?php elseif (!empty($meeting_point->address)) : ?>
                                    <a href="https://www.google.com/maps/search/?api=1&query=<?php echo urlencode($meeting_point->address); ?>" 
                                       target="_blank" 
                                       class="fp-btn fp-btn-outline">
                                        <?php _e('Open in Google Maps', 'fp-esperienze'); ?>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="fp-meeting-point-map">
                            <?php if ($meeting_point->latitude && $meeting_point->longitude) : ?>
                                <!-- Map placeholder - future integration with Google Maps -->
                                <div class="fp-map-placeholder" 
                                     data-lat="<?php echo esc_attr($meeting_point->latitude); ?>" 
                                     data-lng="<?php echo esc_attr($meeting_point->longitude); ?>"
                                     data-name="<?php echo esc_attr($meeting_point->name); ?>">
                                    <div class="fp-map-placeholder-content">
                                        <div class="fp-map-pin">📍</div>
                                        <p><?php _e('Map will be displayed here', 'fp-esperienze'); ?></p>
                                        <small><?php printf(__('Coordinates: %s, %s', 'fp-esperienze'), $meeting_point->latitude, $meeting_point->longitude); ?></small>
                                    </div>
                                </div>
                            <?php else : ?>
                                <div class="fp-map-placeholder">
                                    <div class="fp-map-placeholder-content">
                                        <div class="fp-map-pin">📍</div>
                                        <p><?php _e('Map location not available', 'fp-esperienze'); ?></p>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </section>
                <?php 
                    endif;
                }
                ?>

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