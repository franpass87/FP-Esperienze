<?php
/**
 * Availability REST API
 *
 * @package FP\Esperienze\REST
 */

namespace FP\Esperienze\REST;

use FP\Esperienze\Data\Availability;
use FP\Esperienze\Core\RateLimiter;
use FP\Esperienze\Core\CacheManager;
use FP\Esperienze\Helpers\TimezoneHelper;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

defined('ABSPATH') || exit;

if (!class_exists('FP\\Esperienze\\Helpers\\TimezoneHelper')) {
    require_once dirname(__DIR__) . '/Helpers/TimezoneHelper.php';
}

/**
 * Availability API class
 */
class AvailabilityAPI {

    /**
     * Constructor
     */
    public function __construct() {
        // Register routes immediately since this is already called from rest_api_init
        $this->registerRoutes();
    }

    /**
     * Register REST routes
     */
    public function registerRoutes(): void {
        register_rest_route('fp-exp/v1', '/availability', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'getAvailability'],
            'permission_callback' => '__return_true',
            'args'                => [
                'product_id' => [
                    'required'          => true,
                    'validate_callback' => function($param) {
                        return is_numeric($param) && $param > 0;
                    },
                    'sanitize_callback' => 'absint',
                ],
                'date' => [
                    'required'          => true,
                    'validate_callback' => function($param) {
                        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $param);
                    },
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);
    }

    /**
     * Get availability for a product on a specific date
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error
     */
    public function getAvailability(WP_REST_Request $request) {
        // Apply rate limiting (30 requests per minute per IP)
        if (!RateLimiter::checkRateLimit('availability', 30, 60)) {
            return RateLimiter::createRateLimitResponse();
        }

        $product_id = $request->get_param('product_id');
        $date = $request->get_param('date');

        // Check cache first using CacheManager
        $cached_data = CacheManager::getAvailabilityCache($product_id, $date);
        if ($cached_data !== false) {
            $response = new WP_REST_Response($cached_data, 200);
            // Add rate limit headers
            foreach (RateLimiter::getRateLimitHeaders('availability', 30, 60) as $header => $value) {
                $response->header($header, $value);
            }
            $response->header('X-Cache', 'HIT');
            $response->header('X-Cache-Type', isset($cached_data['_prebuilt']) ? 'PREBUILT' : 'RUNTIME');
            return $response;
        }

        // Check if product exists and is an experience
        $product = wc_get_product($product_id);
        if (!$product || $product->get_type() !== 'experience') {
            return new WP_Error(
                'invalid_product',
                __('Product not found or is not an experience.', 'fp-esperienze'),
                ['status' => 404]
            );
        }

        // Validate date
        $wp_timezone = TimezoneHelper::getSiteTimezone();
        $date_obj = \DateTime::createFromFormat('Y-m-d', $date, $wp_timezone);
        if (!$date_obj || $date_obj->format('Y-m-d') !== $date) {
            return new WP_Error(
                'invalid_date',
                __('Invalid date format. Use YYYY-MM-DD.', 'fp-esperienze'),
                ['status' => 400]
            );
        }

        // Check if date is in the future
        $today = new \DateTime('now', TimezoneHelper::getSiteTimezone());
        $today->setTime(0, 0, 0); // Set to start of day
        
        if ($date_obj < $today) {
            return new WP_Error(
                'past_date',
                __('Cannot check availability for past dates.', 'fp-esperienze'),
                ['status' => 400]
            );
        }

        // Get real availability slots from database
        $slots = Availability::forDay($product_id, $date);

        $response_data = [
            'product_id' => $product_id,
            'date'       => $date,
            'slots'      => $slots,
            'total_slots' => count($slots),
        ];

        // Cache the response using CacheManager
        CacheManager::setAvailabilityCache($product_id, $date, $response_data);

        $response = new WP_REST_Response($response_data, 200);
        
        // Add rate limit headers
        foreach (RateLimiter::getRateLimitHeaders('availability', 30, 60) as $header => $value) {
            $response->header($header, $value);
        }
        $response->header('X-Cache', 'MISS');

        return $response;
    }
}