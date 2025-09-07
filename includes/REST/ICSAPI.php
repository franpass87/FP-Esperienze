<?php
/**
 * ICS REST API
 *
 * @package FP\Esperienze\REST
 */

namespace FP\Esperienze\REST;

use FP\Esperienze\Data\ICSGenerator;
use FP\Esperienze\Data\MeetingPointManager;
use FP\Esperienze\Core\CapabilityManager;

defined('ABSPATH') || exit;

/**
 * ICS API class for public calendar endpoints
 */
class ICSAPI {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Register routes immediately since this is already called from rest_api_init
        $this->registerRoutes();
    }
    
    /**
     * Register REST API routes
     */
    public function registerRoutes(): void {
        // Public endpoint for product calendar
        register_rest_route('fp-esperienze/v1', '/ics/product/(?P<product_id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'getProductICS'],
            'permission_callback' => '__return_true', // Public endpoint
            'args' => [
                'product_id' => [
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_numeric($param) && $param > 0;
                    }
                ],
                'days' => [
                    'default' => 30,
                    'validate_callback' => function($param) {
                        return is_numeric($param) && $param > 0 && $param <= 365;
                    }
                ]
            ]
        ]);
        
        // Public endpoint for user bookings calendar (requires authentication)
        register_rest_route('fp-esperienze/v1', '/ics/user/(?P<user_id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'getUserBookingsICS'],
            'permission_callback' => [$this, 'checkUserPermission'],
            'args' => [
                'user_id' => [
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_numeric($param) && $param > 0;
                    }
                ]
            ]
        ]);
        
        // Public endpoint for single booking calendar (with token)
        register_rest_route('fp-esperienze/v1', '/ics/booking/(?P<booking_id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'getBookingICS'],
            'permission_callback' => '__return_true', // Public with token validation
            'args' => [
                'booking_id' => [
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_numeric($param) && $param > 0;
                    }
                ],
                'token' => [
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_string($param) && strlen($param) === 32;
                    }
                ]
            ]
        ]);

        // Endpoint for downloading stored ICS files
        register_rest_route('fp-esperienze/v1', '/ics/file/(?P<filename>[\w\-]+\.ics)', [
            'methods'  => 'GET',
            'callback' => [$this, 'serveICSFile'],
            'permission_callback' => '__return_true',
            'args'    => [
                'filename' => [
                    'required'          => true,
                    'validate_callback' => function($param) {
                        return is_string($param) && preg_match('/^[\w\-]+\.ics$/', $param);
                    }
                ],
                'token' => [
                    'required'          => false,
                    'validate_callback' => function($param) {
                        return empty($param) || (is_string($param) && strlen($param) === 32);
                    }
                ]
            ]
        ]);
    }
    
    /**
     * Get product ICS calendar
     *
     * @param \WP_REST_Request $request Request object
     * @return \WP_REST_Response|\WP_Error
     */
    public function getProductICS(\WP_REST_Request $request) {
        $product_id = (int) $request->get_param('product_id');
        $days = (int) $request->get_param('days');
        
        // Validate product exists and is experience type
        $product = wc_get_product($product_id);
        if (!$product || $product->get_type() !== 'experience') {
            return new \WP_Error(
                'invalid_product',
                __('Product not found or not an experience.', 'fp-esperienze'),
                ['status' => 404]
            );
        }
        
        // Check if product is published
        if ($product->get_status() !== 'publish') {
            return new \WP_Error(
                'product_not_available',
                __('Product is not available.', 'fp-esperienze'),
                ['status' => 404]
            );
        }
        
        $ics_content = ICSGenerator::generateProductICS($product_id, $days);
        
        if (empty($ics_content)) {
            return new \WP_Error(
                'no_events',
                __('No events available for this product.', 'fp-esperienze'),
                ['status' => 404]
            );
        }
        
        $response = new \WP_REST_Response($ics_content);
        $response->set_headers([
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . sanitize_file_name($product->get_name()) . '.ics"',
            'Cache-Control' => 'no-cache, must-revalidate',
            'Expires' => gmdate('D, d M Y H:i:s \G\M\T', time() + 3600) // 1 hour cache
        ]);
        
        return $response;
    }
    
    /**
     * Get user bookings ICS calendar
     *
     * @param \WP_REST_Request $request Request object
     * @return \WP_REST_Response|\WP_Error
     */
    public function getUserBookingsICS(\WP_REST_Request $request) {
        $user_id = (int) $request->get_param('user_id');
        
        // Validate user exists
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return new \WP_Error(
                'invalid_user',
                __('User not found.', 'fp-esperienze'),
                ['status' => 404]
            );
        }
        
        $ics_content = ICSGenerator::generateUserBookingsICS($user_id);
        
        if (empty($ics_content)) {
            return new \WP_Error(
                'no_bookings',
                __('No bookings found for this user.', 'fp-esperienze'),
                ['status' => 404]
            );
        }
        
        $response = new \WP_REST_Response($ics_content);
        $response->set_headers([
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="my-bookings.ics"',
            'Cache-Control' => 'no-cache, must-revalidate'
        ]);
        
        return $response;
    }
    
    /**
     * Get single booking ICS calendar
     *
     * @param \WP_REST_Request $request Request object
     * @return \WP_REST_Response|\WP_Error
     */
    public function getBookingICS(\WP_REST_Request $request) {
        $booking_id = (int) $request->get_param('booking_id');
        $token = $request->get_param('token');
        
        // Validate token
        if (!$this->validateBookingToken($booking_id, $token)) {
            return new \WP_Error(
                'invalid_token',
                __('Invalid access token.', 'fp-esperienze'),
                ['status' => 403]
            );
        }
        
        // Get booking
        global $wpdb;
        $table_name = $wpdb->prefix . 'fp_bookings';
        $booking = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE id = %d AND status = 'confirmed'",
            $booking_id
        ));
        
        if (!$booking) {
            return new \WP_Error(
                'booking_not_found',
                __('Booking not found.', 'fp-esperienze'),
                ['status' => 404]
            );
        }
        
        // Get product and meeting point
        $product = wc_get_product($booking->product_id);
        $meeting_point = $booking->meeting_point_id ? MeetingPointManager::getMeetingPoint($booking->meeting_point_id) : null;
        
        $ics_content = ICSGenerator::generateBookingICS($booking, $product, $meeting_point);
        
        if (empty($ics_content)) {
            return new \WP_Error(
                'generation_failed',
                __('Failed to generate calendar.', 'fp-esperienze'),
                ['status' => 500]
            );
        }
        
        $filename = 'booking-' . $booking_id . '.ics';
        
        $response = new \WP_REST_Response($ics_content);
        $response->set_headers([
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'no-cache, must-revalidate'
        ]);

        return $response;
    }

    /**
     * Serve stored ICS file with access control
     *
     * @param \WP_REST_Request $request Request object
     * @return \WP_REST_Response|\WP_Error
     */
    public function serveICSFile(\WP_REST_Request $request) {
        $filename = basename($request->get_param('filename'));
        $token    = (string) $request->get_param('token');
        $base_dir = rtrim(realpath(FP_ESPERIENZE_ICS_DIR), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $file_path = realpath($base_dir . $filename);

        if ($file_path === false) {
            return new \WP_Error(
                'file_not_found',
                __('ICS file not found.', 'fp-esperienze'),
                ['status' => 404]
            );
        }

        if (strpos($file_path, $base_dir) !== 0) {
            return new \WP_Error(
                'forbidden',
                __('Access denied.', 'fp-esperienze'),
                ['status' => 403]
            );
        }

        if (!CapabilityManager::canManageFPEsperienze()) {
            if (preg_match('/^booking-(\d+)-/', $filename, $matches)) {
                $booking_id = (int) $matches[1];
                if (!$this->validateBookingToken($booking_id, $token)) {
                    return new \WP_Error(
                        'invalid_token',
                        __('Invalid access token.', 'fp-esperienze'),
                        ['status' => 403]
                    );
                }
            } else {
                return new \WP_Error(
                    'forbidden',
                    __('Access denied.', 'fp-esperienze'),
                    ['status' => 403]
                );
            }
        }

        $content = file_get_contents($file_path);
        $response = new \WP_REST_Response($content);
        $response->set_headers([
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'no-cache, must-revalidate'
        ]);

        return $response;
    }
    
    /**
     * Check user permission for user bookings endpoint
     *
     * @param \WP_REST_Request $request Request object
     * @return bool|\WP_Error
     */
    public function checkUserPermission(\WP_REST_Request $request) {
        $user_id = (int) $request->get_param('user_id');
        $current_user = wp_get_current_user();
        
        // Must be logged in
        if (!$current_user || !$current_user->ID) {
            return new \WP_Error(
                'not_authenticated',
                __('Authentication required.', 'fp-esperienze'),
                ['status' => 401]
            );
        }
        
        // Can access own bookings or admin can access any
        if ($current_user->ID === $user_id || CapabilityManager::canManageFPEsperienze()) {
            return true;
        }
        
        return new \WP_Error(
            'insufficient_permissions',
            __('You do not have permission to access these bookings.', 'fp-esperienze'),
            ['status' => 403]
        );
    }
    
    /**
     * Validate booking access token
     *
     * @param int $booking_id Booking ID
     * @param string $token Access token
     * @return bool
     */
    private function validateBookingToken(int $booking_id, string $token): bool {
        // Generate expected token
        $secret = get_option('fp_esperienze_gift_secret_hmac', '');
        if (empty($secret)) {
            return false;
        }
        
        $expected_token = hash_hmac('sha256', 'booking_' . $booking_id, $secret);
        $expected_token = substr($expected_token, 0, 32); // Use first 32 chars
        
        return hash_equals($expected_token, $token);
    }
    
    /**
     * Generate booking access token
     *
     * @param int $booking_id Booking ID
     * @return string|false Token or false if secret not available
     */
    public static function generateBookingToken(int $booking_id) {
        $secret = get_option('fp_esperienze_gift_secret_hmac', '');
        if (empty($secret)) {
            return false;
        }
        
        $token = hash_hmac('sha256', 'booking_' . $booking_id, $secret);
        return substr($token, 0, 32); // Use first 32 chars
    }
}