<?php
/**
 * Single Experience Template - GetYourGuide Style
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

// Product data
$product_id = $product->get_id();
$image_id = $product->get_image_id();
$image_url = $image_id ? wp_get_attachment_image_url($image_id, 'large') : wc_placeholder_img_src();
$gallery_ids = $product->get_gallery_image_ids();

// Meta data - use consistent _fp_exp_ prefix where available
$duration = get_post_meta($product_id, '_fp_exp_duration', true) ?: get_post_meta($product_id, '_experience_duration', true);
$capacity = get_post_meta($product_id, '_fp_exp_capacity', true) ?: get_post_meta($product_id, '_experience_capacity', true);
$languages = get_post_meta($product_id, '_fp_exp_langs', true) ?: get_post_meta($product_id, '_experience_languages', true);
$adult_price = get_post_meta($product_id, '_fp_exp_adult_price', true) ?: get_post_meta($product_id, '_experience_adult_price', true);
$child_price = get_post_meta($product_id, '_fp_exp_child_price', true) ?: get_post_meta($product_id, '_experience_child_price', true);
$faq_data = get_post_meta($product_id, '_fp_exp_faq', true);

// Get meeting point information
$meeting_point_id = get_post_meta($product_id, '_fp_exp_meeting_point_id', true);
$meeting_point = null;
if ($meeting_point_id) {
    $meeting_point = \FP\Esperienze\Data\MeetingPointManager::getMeetingPoint((int) $meeting_point_id);
}

// Get what's included/excluded data
$included = get_post_meta($product_id, '_fp_exp_included', true);
$excluded = get_post_meta($product_id, '_fp_exp_excluded', true);

// Parse language chips
$language_chips = [];
if ($languages) {
    $language_chips = array_map('trim', explode(',', $languages));
}

// Schema.org JSON-LD
$schema_data = [
    '@context' => 'https://schema.org',
    '@type' => 'Product',
    'name' => $product->get_name(),
    'description' => wp_strip_all_tags($product->get_description()),
    'brand' => [
        '@type' => 'Brand',
        'name' => 'FP Esperienze'
    ],
    'offers' => [
        '@type' => 'Offer',
        'price' => $adult_price ?: 0,
        'priceCurrency' => get_woocommerce_currency(),
        'availability' => 'https://schema.org/InStock'
    ]
];

if ($image_url) {
    $schema_data['image'] = $image_url;
}

// GA4 view_item event
$ga4_view_item = [
    'event' => 'view_item',
    'ecommerce' => [
        'currency' => get_woocommerce_currency(),
        'value' => $adult_price ?: 0,
        'items' => [
            [
                'item_id' => $product_id,
                'item_name' => $product->get_name(),
                'item_category' => 'Experience',
                'price' => $adult_price ?: 0,
                'quantity' => 1
            ]
        ]
    ]
];
?>

<!-- Schema.org JSON-LD -->
<script type="application/ld+json">
<?php echo wp_json_encode($schema_data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>
</script>

<!-- GA4 View Item Event -->
<script>
// GA4 view_item event - hookable for implementation
window.dataLayer = window.dataLayer || [];
window.dataLayer.push(<?php echo wp_json_encode($ga4_view_item, JSON_UNESCAPED_SLASHES); ?>);
// TODO: Add actual GA4 implementation in Milestone D
</script>

<div class="fp-experience-single">
    <!-- Hero Section -->
    <section class="fp-experience-hero">
        <div class="fp-hero-content">
            <?php if ($gallery_ids || $image_url) : ?>
                <div class="fp-hero-gallery">
                    <div class="fp-main-image">
                        <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($product->get_name()); ?>" />
                    </div>
                    <?php if ($gallery_ids) : ?>
                        <div class="fp-gallery-thumbs">
                            <?php foreach (array_slice($gallery_ids, 0, 4) as $gallery_id) : ?>
                                <img src="<?php echo esc_url(wp_get_attachment_image_url($gallery_id, 'thumbnail')); ?>" 
                                     alt="<?php echo esc_attr($product->get_name()); ?>" />
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <div class="fp-hero-info">
                <div class="container">
                    <h1 class="fp-experience-title"><?php echo esc_html($product->get_name()); ?></h1>
                    
                    <?php if ($product->get_short_description()) : ?>
                        <p class="fp-experience-subtitle"><?php echo wp_kses_post($product->get_short_description()); ?></p>
                    <?php endif; ?>
                    
                    <?php if ($adult_price) : ?>
                        <div class="fp-hero-price">
                            <span class="fp-price-label"><?php _e('From', 'fp-esperienze'); ?></span>
                            <span class="fp-price-amount"><?php echo wc_price($adult_price); ?></span>
                            <span class="fp-price-unit"><?php _e('per person', 'fp-esperienze'); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Trust/USP Bar -->
    <section class="fp-trust-bar">
        <div class="container">
            <div class="fp-trust-items">
                <?php if ($duration) : ?>
                    <div class="fp-trust-item">
                        <span class="fp-trust-icon">⏰</span>
                        <div class="fp-trust-content">
                            <strong><?php _e('Duration', 'fp-esperienze'); ?></strong>
                            <span><?php printf(__('%d minutes', 'fp-esperienze'), intval($duration)); ?></span>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($language_chips)) : ?>
                    <div class="fp-trust-item">
                        <span class="fp-trust-icon">🗣️</span>
                        <div class="fp-trust-content">
                            <strong><?php _e('Languages', 'fp-esperienze'); ?></strong>
                            <div class="fp-language-chips">
                                <?php foreach ($language_chips as $lang) : ?>
                                    <span class="fp-language-chip"><?php echo esc_html($lang); ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="fp-trust-item">
                    <span class="fp-trust-icon">✅</span>
                    <div class="fp-trust-content">
                        <strong><?php _e('Cancellation', 'fp-esperienze'); ?></strong>
                        <span><?php _e('Free cancellation up to 24 hours', 'fp-esperienze'); ?></span>
                    </div>
                </div>
                
                <div class="fp-trust-item">
                    <span class="fp-trust-icon">📱</span>
                    <div class="fp-trust-content">
                        <strong><?php _e('Booking', 'fp-esperienze'); ?></strong>
                        <span><?php _e('Instant confirmation', 'fp-esperienze'); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div class="container fp-experience-content">
        <div class="fp-content-layout">
            <!-- Main Content -->
            <div class="fp-main-content">
                <!-- Description -->
                <?php if ($product->get_description()) : ?>
                    <section class="fp-experience-description">
                        <h2><?php _e('About This Experience', 'fp-esperienze'); ?></h2>
                        <div class="fp-description-content">
                            <?php echo wp_kses_post($product->get_description()); ?>
                        </div>
                    </section>
                <?php endif; ?>

                <!-- What's Included / What's Not Included -->
                <section class="fp-experience-inclusions">
                    <div class="fp-inclusions-grid">
                        <div class="fp-included-section">
                            <h2><?php _e("What's Included", 'fp-esperienze'); ?></h2>
                            <ul class="fp-included-list">
                                <?php if ($included) : ?>
                                    <?php foreach (explode("\n", $included) as $item) : ?>
                                        <?php if (trim($item)) : ?>
                                            <li><?php echo esc_html(trim($item)); ?></li>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                <?php else : ?>
                                    <!-- Default items if not specified -->
                                    <li><?php _e('Professional guide', 'fp-esperienze'); ?></li>
                                    <li><?php _e('All activities as described', 'fp-esperienze'); ?></li>
                                    <li><?php _e('Small group experience', 'fp-esperienze'); ?></li>
                                <?php endif; ?>
                            </ul>
                        </div>
                        
                        <div class="fp-excluded-section">
                            <h2><?php _e("What's Not Included", 'fp-esperienze'); ?></h2>
                            <ul class="fp-excluded-list">
                                <?php if ($excluded) : ?>
                                    <?php foreach (explode("\n", $excluded) as $item) : ?>
                                        <?php if (trim($item)) : ?>
                                            <li><?php echo esc_html(trim($item)); ?></li>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                <?php else : ?>
                                    <!-- Default items if not specified -->
                                    <li><?php _e('Hotel pickup and drop-off', 'fp-esperienze'); ?></li>
                                    <li><?php _e('Food and drinks', 'fp-esperienze'); ?></li>
                                    <li><?php _e('Personal expenses', 'fp-esperienze'); ?></li>
                                    <li><?php _e('Gratuities', 'fp-esperienze'); ?></li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>
                </section>

                <!-- Meeting Point -->
                <?php if ($meeting_point) : ?>
                <section class="fp-experience-meeting-point">
                    <h2><?php _e('Meeting Point', 'fp-esperienze'); ?></h2>
                    <div class="fp-meeting-point-card">
                        <div class="fp-meeting-point-info">
                            <h3><?php echo esc_html($meeting_point->name); ?></h3>
                            <p class="fp-meeting-address">
                                <?php echo esc_html($meeting_point->address); ?>
                            </p>
                            
                            <?php if ($meeting_point->note) : ?>
                                <div class="fp-meeting-point-note">
                                    <strong><?php _e('Meeting Instructions:', 'fp-esperienze'); ?></strong>
                                    <p><?php echo wp_kses_post(nl2br(esc_html($meeting_point->note))); ?></p>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($meeting_point->lat && $meeting_point->lng) : ?>
                                <div class="fp-meeting-point-actions">
                                    <a href="https://www.google.com/maps?q=<?php echo esc_attr($meeting_point->lat . ',' . $meeting_point->lng); ?>" 
                                       target="_blank" 
                                       rel="noopener"
                                       class="fp-maps-link"
                                       aria-label="<?php _e('Open meeting point in Google Maps', 'fp-esperienze'); ?>">
                                        <?php _e('Open in Google Maps', 'fp-esperienze'); ?>
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="fp-meeting-point-map">
                            <?php if ($meeting_point->lat && $meeting_point->lng) : ?>
                                <!-- Map placeholder for future integration -->
                                <div class="fp-map-container" aria-label="<?php _e('Map showing meeting point location', 'fp-esperienze'); ?>">
                                    <div class="fp-map-placeholder">
                                        <span><?php _e('Interactive map will be displayed here', 'fp-esperienze'); ?></span>
                                    </div>
                                </div>
                            <?php else : ?>
                                <div class="fp-map-container">
                                    <div class="fp-map-placeholder fp-map-unavailable">
                                        <span><?php _e('Map coordinates not available', 'fp-esperienze'); ?></span>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </section>
                <?php endif; ?>

                <!-- FAQ Section -->
                <?php if ($faq_data) : ?>
                    <?php 
                    $faq_items = is_array($faq_data) ? $faq_data : json_decode($faq_data, true);
                    if ($faq_items && is_array($faq_items)) : 
                    ?>
                        <section class="fp-experience-faq">
                            <h2><?php _e('Frequently Asked Questions', 'fp-esperienze'); ?></h2>
                            <div class="fp-faq-accordion" role="tablist">
                                <?php foreach ($faq_items as $index => $faq) : ?>
                                    <?php if (!empty($faq['question']) && !empty($faq['answer'])) : ?>
                                        <div class="fp-faq-item">
                                            <button class="fp-faq-question" 
                                                    type="button"
                                                    role="tab"
                                                    aria-expanded="false"
                                                    aria-controls="faq-answer-<?php echo $index; ?>"
                                                    id="faq-question-<?php echo $index; ?>">
                                                <span><?php echo esc_html($faq['question']); ?></span>
                                                <span class="fp-faq-icon" aria-hidden="true">+</span>
                                            </button>
                                            <div class="fp-faq-answer" 
                                                 role="tabpanel"
                                                 id="faq-answer-<?php echo $index; ?>"
                                                 aria-labelledby="faq-question-<?php echo $index; ?>"
                                                 hidden>
                                                <div class="fp-faq-answer-content">
                                                    <?php echo wp_kses_post(wpautop($faq['answer'])); ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endif; ?>
                <?php endif; ?>

                <!-- Reviews Placeholder -->
                <section class="fp-experience-reviews">
                    <h2><?php _e('Customer Reviews', 'fp-esperienze'); ?></h2>
                    <div class="fp-reviews-disclaimer">
                        <p><em><?php _e('Reviews integration will be available in a future update. Real customer reviews from Google will be displayed here.', 'fp-esperienze'); ?></em></p>
                    </div>
                    <div class="fp-reviews-placeholder">
                        <!-- Placeholder review structure for future implementation -->
                        <div class="fp-review-summary" aria-hidden="true">
                            <div class="fp-rating-overview">
                                <span class="fp-rating-score">4.8</span>
                                <div class="fp-rating-stars">
                                    <span class="fp-stars">★★★★★</span>
                                </div>
                                <span class="fp-rating-count"><?php _e('Based on authentic reviews', 'fp-esperienze'); ?></span>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <!-- Booking Widget Sidebar -->
            <div class="fp-sidebar">
                <!-- Booking Widget -->
                <div class="fp-booking-widget" id="fp-booking-widget">
                    <div class="fp-booking-header">
                        <h3><?php _e('Book This Experience', 'fp-esperienze'); ?></h3>
                        <?php if ($adult_price) : ?>
                            <div class="fp-booking-price">
                                <span class="fp-from-label"><?php _e('From', 'fp-esperienze'); ?></span>
                                <span class="fp-price-amount"><?php echo wc_price($adult_price); ?></span>
                                <span class="fp-per-person"><?php _e('per person', 'fp-esperienze'); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="fp-booking-form">
                        <!-- Social Proof Placeholder (will be populated via JS when capacity is low) -->
                        <div id="fp-social-proof" class="fp-social-proof" style="display: none;" role="alert" aria-live="polite">
                            <span class="fp-urgency-icon">🔥</span>
                            <span class="fp-urgency-text"></span>
                        </div>
                        
                        <!-- Date Picker -->
                        <div class="fp-form-field">
                            <label for="fp-date-picker"><?php _e('Select Date', 'fp-esperienze'); ?></label>
                            <input type="date" 
                                   id="fp-date-picker" 
                                   class="fp-date-input" 
                                   min="<?php echo date('Y-m-d'); ?>"
                                   aria-describedby="fp-date-help" />
                            <small id="fp-date-help" class="fp-field-help">
                                <?php _e('Choose your preferred date', 'fp-esperienze'); ?>
                            </small>
                        </div>

                        <!-- Time Slots -->
                        <div class="fp-form-field">
                            <label><?php _e('Available Times', 'fp-esperienze'); ?></label>
                            <div id="fp-time-slots" 
                                 class="fp-time-slots" 
                                 role="radiogroup" 
                                 aria-labelledby="fp-time-slots-label">
                                <p class="fp-slots-placeholder"><?php _e('Please select a date to see available times.', 'fp-esperienze'); ?></p>
                            </div>
                        </div>

                        <!-- Language Selection -->
                        <?php if (!empty($language_chips)) : ?>
                            <div class="fp-form-field">
                                <label for="fp-language"><?php _e('Language', 'fp-esperienze'); ?></label>
                                <select id="fp-language" class="fp-select" aria-describedby="fp-language-help">
                                    <?php foreach ($language_chips as $lang) : ?>
                                        <option value="<?php echo esc_attr($lang); ?>"><?php echo esc_html($lang); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <small id="fp-language-help" class="fp-field-help">
                                    <?php _e('Experience language', 'fp-esperienze'); ?>
                                </small>
                            </div>
                        <?php endif; ?>

                        <!-- Quantity Selectors -->
                        <div class="fp-form-field">
                            <label><?php _e('Participants', 'fp-esperienze'); ?></label>
                            
                            <div class="fp-quantity-group" role="group" aria-labelledby="fp-participants-label">
                                <div class="fp-quantity-row">
                                    <div class="fp-quantity-info">
                                        <span class="fp-participant-type"><?php _e('Adults', 'fp-esperienze'); ?></span>
                                        <span class="fp-participant-age"><?php _e('Age 13+', 'fp-esperienze'); ?></span>
                                    </div>
                                    <div class="fp-quantity-controls">
                                        <button type="button" 
                                                class="fp-qty-btn fp-qty-minus" 
                                                data-target="fp-qty-adult"
                                                aria-label="<?php _e('Decrease adult count', 'fp-esperienze'); ?>">−</button>
                                        <input type="number" 
                                               id="fp-qty-adult" 
                                               class="fp-qty-input" 
                                               value="1" 
                                               min="0" 
                                               max="<?php echo esc_attr($capacity ?: 10); ?>" 
                                               readonly
                                               aria-label="<?php _e('Number of adults', 'fp-esperienze'); ?>" />
                                        <button type="button" 
                                                class="fp-qty-btn fp-qty-plus" 
                                                data-target="fp-qty-adult"
                                                aria-label="<?php _e('Increase adult count', 'fp-esperienze'); ?>">+</button>
                                    </div>
                                    <span class="fp-price-per"><?php echo wc_price($adult_price ?: 0); ?></span>
                                </div>
                                
                                <?php if ($child_price) : ?>
                                    <div class="fp-quantity-row">
                                        <div class="fp-quantity-info">
                                            <span class="fp-participant-type"><?php _e('Children', 'fp-esperienze'); ?></span>
                                            <span class="fp-participant-age"><?php _e('Age 3-12', 'fp-esperienze'); ?></span>
                                        </div>
                                        <div class="fp-quantity-controls">
                                            <button type="button" 
                                                    class="fp-qty-btn fp-qty-minus" 
                                                    data-target="fp-qty-child"
                                                    aria-label="<?php _e('Decrease child count', 'fp-esperienze'); ?>">−</button>
                                            <input type="number" 
                                                   id="fp-qty-child" 
                                                   class="fp-qty-input" 
                                                   value="0" 
                                                   min="0" 
                                                   max="<?php echo esc_attr($capacity ?: 10); ?>" 
                                                   readonly
                                                   aria-label="<?php _e('Number of children', 'fp-esperienze'); ?>" />
                                            <button type="button" 
                                                    class="fp-qty-btn fp-qty-plus" 
                                                    data-target="fp-qty-child"
                                                    aria-label="<?php _e('Increase child count', 'fp-esperienze'); ?>">+</button>
                                        </div>
                                        <span class="fp-price-per"><?php echo wc_price($child_price); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php
                        // Get extras for this product
                        $extras = \FP\Esperienze\Data\ExtraManager::getProductExtras($product_id, true);
                        if (!empty($extras)) :
                        ?>
                        <!-- Extras Selection -->
                        <div class="fp-form-field">
                            <label><?php _e('Add Extras', 'fp-esperienze'); ?></label>
                            <div class="fp-extras-list">
                                <?php foreach ($extras as $extra) : ?>
                                    <div class="fp-extra-item" 
                                         data-extra-id="<?php echo esc_attr($extra->id); ?>" 
                                         data-price="<?php echo esc_attr($extra->price); ?>"
                                         data-billing-type="<?php echo esc_attr($extra->billing_type); ?>"
                                         data-max-quantity="<?php echo esc_attr($extra->max_quantity); ?>"
                                         data-is-required="<?php echo esc_attr($extra->is_required); ?>">
                                        <div class="fp-extra-header">
                                            <div class="fp-extra-info">
                                                <strong><?php echo esc_html($extra->name); ?></strong>
                                                <span class="fp-extra-price">
                                                    <?php echo wc_price($extra->price); ?>
                                                    <?php if ($extra->billing_type === 'per_person') : ?>
                                                        <small><?php _e('per person', 'fp-esperienze'); ?></small>
                                                    <?php else : ?>
                                                        <small><?php _e('per booking', 'fp-esperienze'); ?></small>
                                                    <?php endif; ?>
                                                </span>
                                            </div>
                                            <?php if (!$extra->is_required) : ?>
                                                <label class="fp-extra-checkbox">
                                                    <input type="checkbox" 
                                                           class="fp-extra-toggle" 
                                                           value="1"
                                                           aria-describedby="extra-desc-<?php echo esc_attr($extra->id); ?>" />
                                                    <span class="checkmark"></span>
                                                </label>
                                            <?php else : ?>
                                                <span class="fp-required-badge"><?php _e('Required', 'fp-esperienze'); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <?php if ($extra->description) : ?>
                                            <p class="fp-extra-description" id="extra-desc-<?php echo esc_attr($extra->id); ?>">
                                                <?php echo esc_html($extra->description); ?>
                                            </p>
                                        <?php endif; ?>
                                        
                                        <?php if ($extra->max_quantity > 1) : ?>
                                            <div class="fp-extra-quantity <?php echo $extra->is_required ? 'fp-required' : 'fp-extra-quantity-hidden'; ?>">
                                                <label><?php _e('Quantity', 'fp-esperienze'); ?>:</label>
                                                <div class="fp-quantity-controls">
                                                    <button type="button" 
                                                            class="fp-qty-btn fp-qty-minus fp-extra-qty-minus"
                                                            aria-label="<?php printf(__('Decrease %s quantity', 'fp-esperienze'), esc_html($extra->name)); ?>">−</button>
                                                    <input type="number" 
                                                           class="fp-qty-input fp-extra-qty-input" 
                                                           value="<?php echo $extra->is_required ? '1' : '0'; ?>" 
                                                           min="<?php echo $extra->is_required ? '1' : '0'; ?>" 
                                                           max="<?php echo esc_attr($extra->max_quantity); ?>" 
                                                           readonly
                                                           aria-label="<?php printf(__('%s quantity', 'fp-esperienze'), esc_html($extra->name)); ?>" />
                                                    <button type="button" 
                                                            class="fp-qty-btn fp-qty-plus fp-extra-qty-plus"
                                                            aria-label="<?php printf(__('Increase %s quantity', 'fp-esperienze'), esc_html($extra->name)); ?>">+</button>
                                                </div>
                                            </div>
                                        <?php else : ?>
                                            <input type="hidden" 
                                                   class="fp-extra-qty-input" 
                                                   value="<?php echo $extra->is_required ? '1' : '0'; ?>" />
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Total Price -->
                        <div class="fp-total-price">
                            <div class="fp-price-breakdown">
                                <div id="fp-price-details" class="fp-price-details"></div>
                                <div class="fp-total-row">
                                    <strong>
                                        <?php _e('Total', 'fp-esperienze'); ?>: 
                                        <span id="fp-total-amount"><?php echo wc_price(0); ?></span>
                                    </strong>
                                </div>
                            </div>
                        </div>

                        <!-- Add to Cart Button -->
                        <button type="button" 
                                id="fp-add-to-cart" 
                                class="fp-btn fp-btn-primary fp-btn-large" 
                                disabled
                                aria-describedby="fp-cart-help">
                            <?php _e('Add to Cart', 'fp-esperienze'); ?>
                        </button>
                        <small id="fp-cart-help" class="fp-field-help">
                            <?php _e('Select date and participants to continue', 'fp-esperienze'); ?>
                        </small>

                        <!-- Loading Indicator -->
                        <div id="fp-loading" class="fp-loading" style="display: none;" role="status" aria-live="polite">
                            <div class="fp-loading-spinner" aria-hidden="true"></div>
                            <span><?php _e('Loading availability...', 'fp-esperienze'); ?></span>
                        </div>

                        <!-- Error Messages -->
                        <div id="fp-error-messages" 
                             class="fp-error-messages" 
                             role="alert" 
                             aria-live="assertive"></div>
                    </div>
                    
                    <!-- Hidden Fields -->
                    <input type="hidden" id="fp-product-id" value="<?php echo esc_attr($product_id); ?>" />
                    <input type="hidden" id="fp-selected-slot" value="" />
                    <input type="hidden" id="fp-adult-price" value="<?php echo esc_attr($adult_price ?: 0); ?>" />
                    <input type="hidden" id="fp-child-price" value="<?php echo esc_attr($child_price ?: 0); ?>" />
                    <input type="hidden" id="fp-capacity" value="<?php echo esc_attr($capacity ?: 10); ?>" />
                    <input type="hidden" id="fp-meeting-point-id" value="<?php echo esc_attr($meeting_point_id ?: ''); ?>" />
                </div>
                
                <!-- Sticky Widget Notice (for mobile) -->
                <div class="fp-sticky-notice">
                    <div class="fp-sticky-content">
                        <div class="fp-sticky-price">
                            <span class="fp-from"><?php _e('From', 'fp-esperienze'); ?></span>
                            <span class="fp-amount"><?php echo wc_price($adult_price ?: 0); ?></span>
                        </div>
                        <button type="button" 
                                class="fp-btn fp-btn-primary fp-show-booking"
                                onclick="document.getElementById('fp-booking-widget').scrollIntoView({behavior: 'smooth'})">
                            <?php _e('Select Date', 'fp-esperienze'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php get_footer(); ?>