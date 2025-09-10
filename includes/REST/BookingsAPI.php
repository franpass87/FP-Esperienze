<?php
/**
 * Bookings REST API
 *
 * @package FP\Esperienze\REST
 */

namespace FP\Esperienze\REST;

use FP\Esperienze\Booking\BookingManager;
use FP\Esperienze\Core\CapabilityManager;
use FP\Esperienze\Core\RateLimiter;

defined('ABSPATH') || exit;

/**
 * Bookings REST API class
 */
class BookingsAPI {
    
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
        register_rest_route('fp-exp/v1', '/bookings', [
            'methods' => 'GET',
            'callback' => [$this, 'getBookings'],
            'permission_callback' => [$this, 'checkPermissions'],
            'args' => [
                'start' => [
                    'type' => 'string',
                    'description' => 'Start date for calendar view (YYYY-MM-DD)',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'end' => [
                    'type' => 'string', 
                    'description' => 'End date for calendar view (YYYY-MM-DD)',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'status' => [
                    'type' => 'string',
                    'description' => 'Filter by status',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'product_id' => [
                    'type' => 'integer',
                    'description' => 'Filter by product ID',
                    'sanitize_callback' => 'absint',
                ],
                'page' => [
                    'type' => 'integer',
                    'description' => 'Page number',
                    'sanitize_callback' => 'absint',
                    'default' => 1,
                ],
                'per_page' => [
                    'type' => 'integer',
                    'description' => 'Items per page',
                    'sanitize_callback' => 'absint',
                    'default' => 20,
                ],
            ],
        ]);
        
        register_rest_route('fp-exp/v1', '/bookings/calendar', [
            'methods' => 'GET',
            'callback' => [$this, 'getBookingsForCalendar'],
            'permission_callback' => [$this, 'checkPermissions'],
            'args' => [
                'start' => [
                    'type' => 'string',
                    'required' => true,
                    'description' => 'Start date (YYYY-MM-DD)',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'end' => [
                    'type' => 'string',
                    'required' => true,
                    'description' => 'End date (YYYY-MM-DD)',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'page' => [
                    'type' => 'integer',
                    'description' => 'Page number',
                    'sanitize_callback' => 'absint',
                    'default' => 1,
                ],
                'per_page' => [
                    'type' => 'integer',
                    'description' => 'Items per page',
                    'sanitize_callback' => 'absint',
                    'default' => 20,
                ],
            ],
        ]);
    }
    
    /**
     * Check permissions for REST endpoints
     */
    public function checkPermissions(\WP_REST_Request $request) {
        if (!RateLimiter::checkRateLimit('bookings_permission', 30, 60)) {
            return RateLimiter::createRateLimitResponse();
        }

        return CapabilityManager::canManageFPEsperienze();
    }
    
    /**
     * Get bookings
     *
     * @param \WP_REST_Request $request Request object
     * @return \WP_REST_Response
     */
    public function getBookings(\WP_REST_Request $request): \WP_REST_Response {
        if (!RateLimiter::checkRateLimit('bookings', 30, 60)) {
            return RateLimiter::createRateLimitResponse();
        }

        $filters = [];

        if ($request->get_param('start')) {
            $start = $request->get_param('start');
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) {
                return new \WP_Error('rest_invalid_param', __('Invalid start date format', 'fp-esperienze'), ['status' => 400]);
            }
            $filters['date_from'] = $start;
        }

        if ($request->get_param('end')) {
            $end = $request->get_param('end');
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
                return new \WP_Error('rest_invalid_param', __('Invalid end date format', 'fp-esperienze'), ['status' => 400]);
            }
            $filters['date_to'] = $end;
        }
        
        if ($request->get_param('status')) {
            $filters['status'] = $request->get_param('status');
        }
        
        if ($request->get_param('product_id')) {
            $filters['product_id'] = $request->get_param('product_id');
        }
        
        $per_page = (int) $request->get_param('per_page') ?: 20;
        $page = (int) $request->get_param('page') ?: 1;
        if ($per_page < 1) {
            $per_page = 20;
        }
        if ($page < 1) {
            $page = 1;
        }
        $offset = ($page - 1) * $per_page;

        $bookings = BookingManager::getBookings($filters, $per_page, $offset);
        $total = BookingManager::countBookings($filters);

        $response_data = [
            'bookings' => $bookings,
            'total' => $total,
            'page' => $page,
            'per_page' => $per_page,
        ];

        $response = new \WP_REST_Response($response_data, 200);

        foreach (RateLimiter::getRateLimitHeaders('bookings', 30, 60) as $header => $value) {
            $response->header($header, $value);
        }

        return $response;
    }
    
    /**
     * Get bookings formatted for FullCalendar
     *
     * @param \WP_REST_Request $request Request object
     * @return \WP_REST_Response
     */
    public function getBookingsForCalendar(\WP_REST_Request $request): \WP_REST_Response {
        if (!RateLimiter::checkRateLimit('bookings_calendar', 30, 60)) {
            return RateLimiter::createRateLimitResponse();
        }

        $start_date = $request->get_param('start');
        $end_date = $request->get_param('end');

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date)) {
            return new \WP_Error('rest_invalid_param', __('Invalid start date format', 'fp-esperienze'), ['status' => 400]);
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {
            return new \WP_Error('rest_invalid_param', __('Invalid end date format', 'fp-esperienze'), ['status' => 400]);
        }

        $filters = [
            'date_from' => $start_date,
            'date_to' => $end_date,
        ];
        
        $per_page = (int) $request->get_param('per_page') ?: 20;
        $page = (int) $request->get_param('page') ?: 1;
        if ($per_page < 1) {
            $per_page = 20;
        }
        if ($page < 1) {
            $page = 1;
        }
        $offset = ($page - 1) * $per_page;

        $bookings = BookingManager::getBookings($filters, $per_page, $offset);
        $total = BookingManager::countBookings($filters);
        $events = [];
        
        foreach ($bookings as $booking) {
            $product = wc_get_product($booking->product_id);
            $product_name = $product ? $product->get_name() : __('Unknown Product', 'fp-esperienze');
            
            // Get order details
            $order = wc_get_order($booking->order_id);
            $customer_name = $order ? $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() : '';
            
            // Create event for FullCalendar
            $start_datetime = $booking->booking_date . 'T' . $booking->booking_time;
            
            // Calculate end time (assume 1-2 hours duration)
            $duration_hours = 2; // Default duration
            $end_datetime = date('Y-m-d\TH:i:s', strtotime($start_datetime . " +{$duration_hours} hours"));
            
            $color = $this->getStatusColor($booking->status);
            
            $events[] = [
                'id' => $booking->id,
                'title' => sprintf('%s (%d pax)', $product_name, $booking->adults + $booking->children),
                'start' => $start_datetime,
                'end' => $end_datetime,
                'color' => $color,
                'extendedProps' => [
                    'booking_id' => $booking->id,
                    'order_id' => $booking->order_id,
                    'customer_name' => $customer_name,
                    'adults' => $booking->adults,
                    'children' => $booking->children,
                    'status' => $booking->status,
                    'product_name' => $product_name,
                    'meeting_point_id' => $booking->meeting_point_id,
                ],
            ];
        }
        
        $response_data = [
            'events' => $events,
            'total' => $total,
            'page' => $page,
            'per_page' => $per_page,
        ];

        $response = new \WP_REST_Response($response_data, 200);

        foreach (RateLimiter::getRateLimitHeaders('bookings_calendar', 30, 60) as $header => $value) {
            $response->header($header, $value);
        }

        return $response;
    }
    
    /**
     * Get color for booking status
     *
     * @param string $status Status
     * @return string Color code
     */
    private function getStatusColor(string $status): string {
        switch ($status) {
            case 'confirmed':
                return '#46b450';
            case 'cancelled':
                return '#dc3232';
            case 'refunded':
                return '#ffb900';
            default:
                return '#666666';
        }
    }
}