<?php
/**
 * SEO Manager
 *
 * @package FP\Esperienze\Frontend
 */

namespace FP\Esperienze\Frontend;

use FP\Esperienze\Admin\SEOSettings;
use FP\Esperienze\Data\MeetingPointManager;

defined('ABSPATH') || exit;

/**
 * SEO Manager class
 */
class SEOManager {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Only add hooks if we're on single experience pages
        add_action('wp_head', [$this, 'outputSEOTags'], 1);
        add_action('wp_footer', [$this, 'outputStructuredData'], 5);
    }
    
    /**
     * Output SEO meta tags in head
     */
    public function outputSEOTags(): void {
        if (!$this->isSingleExperience()) {
            return;
        }
        
        global $post;
        $product = wc_get_product($post->ID);
        
        if (!$product || $product->get_type() !== 'experience') {
            return;
        }
        
        if (SEOSettings::isOgTagsEnabled()) {
            $this->outputOpenGraphTags($product);
        }
        
        if (SEOSettings::isTwitterCardsEnabled()) {
            $this->outputTwitterCardTags($product);
        }
    }
    
    /**
     * Output structured data in footer
     */
    public function outputStructuredData(): void {
        if (!$this->isSingleExperience()) {
            return;
        }
        
        global $post;
        $product = wc_get_product($post->ID);
        
        if (!$product || $product->get_type() !== 'experience') {
            return;
        }
        
        $schemas = [];
        
        // Enhanced Product/Event/Trip schema
        if (SEOSettings::isEnhancedSchemaEnabled()) {
            $schemas[] = $this->getEnhancedSchema($product);
        }
        
        // FAQ schema
        if (SEOSettings::isFaqSchemaEnabled()) {
            $faq_schema = $this->getFaqSchema($product);
            if ($faq_schema) {
                $schemas[] = $faq_schema;
            }
        }
        
        // Breadcrumb schema
        if (SEOSettings::isBreadcrumbSchemaEnabled()) {
            $schemas[] = $this->getBreadcrumbSchema($product);
        }
        
        // Output all schemas
        foreach ($schemas as $schema) {
            if ($schema) {
                echo "\n<!-- FP Esperienze Schema.org -->\n";
                echo '<script type="application/ld+json">';
                echo wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                echo '</script>';
                echo "\n";
            }
        }
    }
    
    /**
     * Check if current page is single experience
     */
    private function isSingleExperience(): bool {
        return is_singular('product') && is_object(get_queried_object());
    }
    
    /**
     * Output Open Graph tags
     */
    private function outputOpenGraphTags($product): void {
        $product_id = $product->get_id();
        $title = $product->get_name();
        $description = wp_strip_all_tags($product->get_short_description() ?: $product->get_description());
        $description = wp_trim_words($description, 55, '...');
        $image_url = wp_get_attachment_image_url($product->get_image_id(), 'large');
        $url = get_permalink($product_id);
        $site_name = get_bloginfo('name');
        
        // Price information
        $adult_price = get_post_meta($product_id, '_fp_exp_adult_price', true);
        $currency = get_woocommerce_currency();
        
        echo "\n<!-- Open Graph Meta Tags -->\n";
        echo '<meta property="og:type" content="product" />' . "\n";
        echo '<meta property="og:title" content="' . esc_attr($title) . '" />' . "\n";
        echo '<meta property="og:description" content="' . esc_attr($description) . '" />' . "\n";
        echo '<meta property="og:url" content="' . esc_url($url) . '" />' . "\n";
        echo '<meta property="og:site_name" content="' . esc_attr($site_name) . '" />' . "\n";
        
        if ($image_url) {
            echo '<meta property="og:image" content="' . esc_url($image_url) . '" />' . "\n";
            echo '<meta property="og:image:alt" content="' . esc_attr($title) . '" />' . "\n";
        }
        
        if ($adult_price) {
            echo '<meta property="product:price:amount" content="' . esc_attr($adult_price) . '" />' . "\n";
            echo '<meta property="product:price:currency" content="' . esc_attr($currency) . '" />' . "\n";
        }
        
        echo '<meta property="product:availability" content="in stock" />' . "\n";
    }
    
    /**
     * Output Twitter Card tags
     */
    private function outputTwitterCardTags($product): void {
        $product_id = $product->get_id();
        $title = $product->get_name();
        $description = wp_strip_all_tags($product->get_short_description() ?: $product->get_description());
        $description = wp_trim_words($description, 55, '...');
        $image_url = wp_get_attachment_image_url($product->get_image_id(), 'large');
        
        echo "\n<!-- Twitter Card Meta Tags -->\n";
        echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
        echo '<meta name="twitter:title" content="' . esc_attr($title) . '" />' . "\n";
        echo '<meta name="twitter:description" content="' . esc_attr($description) . '" />' . "\n";
        
        if ($image_url) {
            echo '<meta name="twitter:image" content="' . esc_url($image_url) . '" />' . "\n";
            echo '<meta name="twitter:image:alt" content="' . esc_attr($title) . '" />' . "\n";
        }
    }
    
    /**
     * Get enhanced schema (Event, Trip, or Product)
     */
    private function getEnhancedSchema($product): array {
        $product_id = $product->get_id();
        
        // Determine schema type based on metadata
        $schema_type = $this->determineSchemaType($product_id);
        
        // Base schema data
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => $schema_type,
            'name' => $product->get_name(),
            'description' => wp_strip_all_tags($product->get_description()),
        ];
        
        // Add image
        $image_url = wp_get_attachment_image_url($product->get_image_id(), 'large');
        if ($image_url) {
            $schema['image'] = $image_url;
        }
        
        // Add location from meeting point
        $location_data = $this->getLocationData($product_id);
        if ($location_data) {
            $schema['location'] = $location_data;
        }
        
        // Add offers with pricing
        $offers_data = $this->getOffersData($product_id);
        if ($offers_data) {
            $schema['offers'] = $offers_data;
        }
        
        // Add schema-specific properties
        switch ($schema_type) {
            case 'Event':
                $schema = array_merge($schema, $this->getEventSpecificData($product_id));
                break;
            case 'Trip':
                $schema = array_merge($schema, $this->getTripSpecificData($product_id));
                break;
            case 'Product':
                $schema = array_merge($schema, $this->getProductSpecificData($product_id));
                break;
        }
        
        return $schema;
    }
    
    /**
     * Determine schema type based on metadata
     */
    private function determineSchemaType(int $product_id): string {
        // Check duration to determine if it's scheduled/guided
        $duration = get_post_meta($product_id, '_fp_exp_duration', true);
        
        // Check if there are schedules defined (indicating regular scheduled times)
        global $wpdb;
        $schedules_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}fp_schedules WHERE product_id = %d",
            $product_id
        ));
        
        // Check product categories or tags for tour indication
        $product_terms = wp_get_post_terms($product_id, ['product_cat', 'product_tag'], ['fields' => 'names']);
        $term_names = implode(' ', $product_terms);
        $is_tour = stripos($term_names, 'tour') !== false || stripos($term_names, 'trip') !== false;
        
        // Logic: 
        // - If has schedules and duration = Event (guided experiences with specific times)
        // - If marked as tour or trip = Trip  
        // - Otherwise = Product
        if ($is_tour) {
            return 'Trip';
        } elseif ($schedules_count > 0 && $duration) {
            return 'Event';
        } else {
            return 'Product';
        }
    }
    
    /**
     * Get location data from meeting point
     */
    private function getLocationData(int $product_id): ?array {
        $meeting_point_id = get_post_meta($product_id, '_fp_exp_meeting_point_id', true);
        if (!$meeting_point_id) {
            return null;
        }
        
        $meeting_point = MeetingPointManager::getMeetingPoint((int) $meeting_point_id);
        if (!$meeting_point) {
            return null;
        }
        
        $location = [
            '@type' => 'Place',
            'name' => $meeting_point->name,
        ];
        
        if ($meeting_point->address) {
            $location['address'] = [
                '@type' => 'PostalAddress',
                'streetAddress' => $meeting_point->address,
            ];
        }
        
        if ($meeting_point->lat && $meeting_point->lng) {
            $location['geo'] = [
                '@type' => 'GeoCoordinates',
                'latitude' => $meeting_point->lat,
                'longitude' => $meeting_point->lng,
            ];
        }
        
        return $location;
    }
    
    /**
     * Get offers data with pricing
     */
    private function getOffersData(int $product_id): array {
        $adult_price = get_post_meta($product_id, '_fp_exp_adult_price', true);
        $child_price = get_post_meta($product_id, '_fp_exp_child_price', true);
        $currency = get_woocommerce_currency();
        
        $offers = [];
        
        if ($adult_price) {
            $offers[] = [
                '@type' => 'Offer',
                'name' => __('Adult Price', 'fp-esperienze'),
                'price' => $adult_price,
                'priceCurrency' => $currency,
                'availability' => 'https://schema.org/InStock',
                'validFrom' => current_time('c'),
            ];
        }
        
        if ($child_price) {
            $offers[] = [
                '@type' => 'Offer',
                'name' => __('Child Price', 'fp-esperienze'),
                'price' => $child_price,
                'priceCurrency' => $currency,
                'availability' => 'https://schema.org/InStock',
                'validFrom' => current_time('c'),
            ];
        }
        
        // If no specific pricing, use product price
        if (empty($offers)) {
            $product = wc_get_product($product_id);
            if ($product && $product->get_price()) {
                $offers[] = [
                    '@type' => 'Offer',
                    'price' => $product->get_price(),
                    'priceCurrency' => $currency,
                    'availability' => 'https://schema.org/InStock',
                    'validFrom' => current_time('c'),
                ];
            }
        }
        
        return $offers;
    }
    
    /**
     * Get Event-specific schema data
     */
    private function getEventSpecificData(int $product_id): array {
        $data = [];
        
        // Add start date - try to get from next available slot, fallback to example
        $start_date = $this->getNextAvailableSlotDate($product_id);
        if (!$start_date) {
            // Fallback to next week as example
            $start_date = date('c', strtotime('+1 week'));
        }
        
        $data['startDate'] = $start_date;
        
        // Add duration if available
        $duration = get_post_meta($product_id, '_fp_exp_duration', true);
        if ($duration) {
            $data['duration'] = 'PT' . $duration . 'M'; // ISO 8601 duration format
        }
        
        // Add event status
        $data['eventStatus'] = 'https://schema.org/EventScheduled';
        
        // Add event attendance mode
        $data['eventAttendanceMode'] = 'https://schema.org/OfflineEventAttendanceMode';
        
        return $data;
    }
    
    /**
     * Get Trip-specific schema data
     */
    private function getTripSpecificData(int $product_id): array {
        $data = [];
        
        // Add duration if available
        $duration = get_post_meta($product_id, '_fp_exp_duration', true);
        if ($duration) {
            $data['duration'] = 'PT' . $duration . 'M';
        }
        
        return $data;
    }
    
    /**
     * Get Product-specific schema data
     */
    private function getProductSpecificData(int $product_id): array {
        return [
            'brand' => [
                '@type' => 'Brand',
                'name' => 'FP Esperienze'
            ],
        ];
    }
    
    /**
     * Get next available slot date
     */
    private function getNextAvailableSlotDate(int $product_id): ?string {
        // Try to get next available slot from schedules
        global $wpdb;
        
        // Look for schedules for this product
        $schedule = $wpdb->get_row($wpdb->prepare(
            "SELECT day_of_week, start_time, duration_minutes FROM {$wpdb->prefix}fp_schedules WHERE product_id = %d ORDER BY day_of_week ASC, start_time ASC LIMIT 1",
            $product_id
        ));
        
        if ($schedule) {
            // Calculate next occurrence of this day/time
            $current_day = (int) date('w'); // 0=Sunday, 6=Saturday
            $schedule_day = (int) $schedule->day_of_week;
            
            // Calculate days to add
            $days_to_add = ($schedule_day - $current_day + 7) % 7;
            if ($days_to_add == 0) {
                // Same day - check if time has passed
                $current_time = date('H:i:s');
                if ($current_time > $schedule->start_time) {
                    $days_to_add = 7; // Next week
                }
            }
            
            $next_date = date('Y-m-d', strtotime("+{$days_to_add} days"));
            $next_datetime = $next_date . 'T' . $schedule->start_time;
            
            return date('c', strtotime($next_datetime));
        }
        
        return null;
    }
    
    /**
     * Get FAQ schema
     */
    private function getFaqSchema($product): ?array {
        $product_id = $product->get_id();
        $faq_data = get_post_meta($product_id, '_fp_exp_faq', true);
        
        if (!$faq_data) {
            return null;
        }
        
        $faq_items = is_array($faq_data) ? $faq_data : json_decode($faq_data, true);
        
        if (!$faq_items || !is_array($faq_items)) {
            return null;
        }
        
        $main_entities = [];
        
        foreach ($faq_items as $faq) {
            if (!empty($faq['question']) && !empty($faq['answer'])) {
                $main_entities[] = [
                    '@type' => 'Question',
                    'name' => $faq['question'],
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text' => wp_strip_all_tags($faq['answer'])
                    ]
                ];
            }
        }
        
        if (empty($main_entities)) {
            return null;
        }
        
        return [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => $main_entities
        ];
    }
    
    /**
     * Get breadcrumb schema
     */
    private function getBreadcrumbSchema($product): array {
        $product_id = $product->get_id();
        $shop_page_id = wc_get_page_id('shop');
        
        $breadcrumbs = [
            [
                '@type' => 'ListItem',
                'position' => 1,
                'name' => __('Home', 'fp-esperienze'),
                'item' => home_url()
            ]
        ];
        
        if ($shop_page_id && $shop_page_id !== -1) {
            $breadcrumbs[] = [
                '@type' => 'ListItem',
                'position' => 2,
                'name' => get_the_title($shop_page_id),
                'item' => get_permalink($shop_page_id)
            ];
        }
        
        $breadcrumbs[] = [
            '@type' => 'ListItem',
            'position' => count($breadcrumbs) + 1,
            'name' => $product->get_name(),
            'item' => get_permalink($product_id)
        ];
        
        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $breadcrumbs
        ];
    }
}