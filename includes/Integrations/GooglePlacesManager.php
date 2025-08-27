<?php
/**
 * Google Places API Manager
 *
 * @package FP\Esperienze\Integrations
 */

namespace FP\Esperienze\Integrations;

defined('ABSPATH') || exit;

/**
 * Handles Google Places API integration for meeting point reviews
 */
class GooglePlacesManager {
    
    /**
     * Google Places API (New) endpoint
     */
    private const API_BASE_URL = 'https://places.googleapis.com/v1/places';
    
    /**
     * Integration settings
     */
    private array $settings;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->settings = get_option('fp_esperienze_integrations', []);
    }
    
    /**
     * Check if Google Places integration is enabled
     */
    public function isEnabled(): bool {
        return !empty($this->settings['gplaces_api_key']) && 
               !empty($this->settings['gplaces_reviews_enabled']);
    }
    
    /**
     * Get place details including reviews
     *
     * @param string $place_id Google Place ID
     * @return array|null Place details or null on error
     */
    public function getPlaceDetails(string $place_id): ?array {
        if (!$this->isEnabled() || empty($place_id)) {
            return null;
        }
        
        // Check cache first
        $cache_key = 'fp_gplaces_' . md5($place_id);
        $cached_data = get_transient($cache_key);
        
        if ($cached_data !== false) {
            return $cached_data;
        }
        
        // Make API request
        $api_key = $this->settings['gplaces_api_key'];
        $reviews_limit = max(1, min(10, absint($this->settings['gplaces_reviews_limit'] ?? 5)));
        
        $url = self::API_BASE_URL . '/' . urlencode($place_id);
        
        // Field mask for the specific data we want
        $field_mask = 'rating,userRatingCount,reviews.authorAttribution.displayName,reviews.rating,reviews.text.text,reviews.relativePublishTimeDescription';
        
        $response = $this->makeApiRequest('GET', $url, [], $api_key, $field_mask);
        
        if (is_wp_error($response)) {
            $this->logError('Places API request failed', [
                'place_id' => $place_id,
                'error' => $response->get_error_message()
            ]);
            return null;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if ($response_code !== 200) {
            $this->logError('Places API unexpected response', [
                'place_id' => $place_id,
                'code' => $response_code
            ]);
            return null;
        }
        
        $data = json_decode($response_body, true);
        
        if (!$data) {
            $this->logError('Places API invalid response format', ['place_id' => $place_id]);
            return null;
        }
        
        // Process and sanitize the response
        $place_data = $this->processPlaceData($data);
        
        // Cache the result
        $cache_ttl = max(5, min(1440, absint($this->settings['gplaces_cache_ttl'] ?? 60))) * MINUTE_IN_SECONDS;
        set_transient($cache_key, $place_data, $cache_ttl);
        
        return $place_data;
    }
    
    /**
     * Process and sanitize place data from API response
     *
     * @param array $raw_data Raw API response
     * @return array Processed place data
     */
    private function processPlaceData(array $raw_data): array {
        $place_data = [
            'rating' => null,
            'user_ratings_total' => 0,
            'reviews' => []
        ];
        
        // Rating and total count
        if (isset($raw_data['rating'])) {
            $place_data['rating'] = round(floatval($raw_data['rating']), 1);
        }
        
        if (isset($raw_data['userRatingCount'])) {
            $place_data['user_ratings_total'] = absint($raw_data['userRatingCount']);
        }
        
        // Reviews
        if (isset($raw_data['reviews']) && is_array($raw_data['reviews'])) {
            foreach ($raw_data['reviews'] as $review) {
                $processed_review = $this->processReview($review);
                if ($processed_review) {
                    $place_data['reviews'][] = $processed_review;
                }
            }
        }
        
        return $place_data;
    }
    
    /**
     * Process individual review
     *
     * @param array $review Raw review data
     * @return array|null Processed review or null if invalid
     */
    private function processReview(array $review): ?array {
        $processed = [
            'author_name' => '',
            'rating' => 0,
            'text' => '',
            'time' => ''
        ];
        
        // Author name (partial for privacy)
        if (isset($review['authorAttribution']['displayName'])) {
            $author_name = sanitize_text_field($review['authorAttribution']['displayName']);
            $processed['author_name'] = $this->partialName($author_name);
        }
        
        // Rating
        if (isset($review['rating'])) {
            $processed['rating'] = max(1, min(5, absint($review['rating'])));
        }
        
        // Review text (excerpt)
        if (isset($review['text']['text'])) {
            $text = sanitize_textarea_field($review['text']['text']);
            $processed['text'] = $this->createExcerpt($text, 150);
        }
        
        // Relative time
        if (isset($review['relativePublishTimeDescription'])) {
            $processed['time'] = sanitize_text_field($review['relativePublishTimeDescription']);
        }
        
        // Only return if we have minimum required data
        if ($processed['author_name'] && $processed['rating'] > 0) {
            return $processed;
        }
        
        return null;
    }
    
    /**
     * Create partial name for privacy (e.g., "John D.")
     *
     * @param string $full_name Full name
     * @return string Partial name
     */
    private function partialName(string $full_name): string {
        $parts = explode(' ', trim($full_name));
        
        if (count($parts) === 1) {
            // Single name - show first few chars
            return substr($parts[0], 0, 1) . '***';
        }
        
        // Multiple parts - show first name + initial of last
        $first = $parts[0];
        $last_initial = substr(end($parts), 0, 1);
        
        return $first . ' ' . $last_initial . '.';
    }
    
    /**
     * Create text excerpt
     *
     * @param string $text Full text
     * @param int $length Maximum length
     * @return string Excerpt
     */
    private function createExcerpt(string $text, int $length = 150): string {
        if (strlen($text) <= $length) {
            return $text;
        }
        
        $excerpt = substr($text, 0, $length);
        $last_space = strrpos($excerpt, ' ');
        
        if ($last_space !== false) {
            $excerpt = substr($excerpt, 0, $last_space);
        }
        
        return $excerpt . '...';
    }
    
    /**
     * Make API request to Google Places
     *
     * @param string $method HTTP method
     * @param string $url API endpoint URL
     * @param array $params Request parameters
     * @param string $api_key API key
     * @param string $field_mask Field mask for the request
     * @return array|\WP_Error Response or error
     */
    private function makeApiRequest(string $method, string $url, array $params = [], string $api_key = '', string $field_mask = ''): array|\WP_Error {
        $args = [
            'method' => $method,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Goog-Api-Key' => $api_key,
            ],
            'timeout' => 30,
        ];
        
        // Add field mask header if provided
        if (!empty($field_mask)) {
            $args['headers']['X-Goog-FieldMask'] = $field_mask;
        }
        
        if ($method === 'POST' && !empty($params)) {
            $args['body'] = wp_json_encode($params);
        } elseif ($method === 'GET' && !empty($params)) {
            $url = add_query_arg($params, $url);
        }
        
        return wp_remote_request($url, $args);
    }
    
    /**
     * Generate Google Maps profile URL for place
     *
     * @param string $place_id Google Place ID
     * @return string Maps profile URL
     */
    public function getMapsProfileUrl(string $place_id): string {
        return 'https://www.google.com/maps/place/?q=place_id:' . urlencode($place_id);
    }
    
    /**
     * Log error without exposing sensitive data
     *
     * @param string $message Error message
     * @param array $context Context data (no PII)
     */
    private function logError(string $message, array $context = []): void {
        // Remove any potentially sensitive data
        $safe_context = array_filter($context, function($key) {
            return !in_array($key, ['api_key', 'email', 'name'], true);
        }, ARRAY_FILTER_USE_KEY);
        
        error_log(sprintf(
            '[FP Esperienze - Google Places] %s: %s',
            $message,
            wp_json_encode($safe_context)
        ));
    }
}