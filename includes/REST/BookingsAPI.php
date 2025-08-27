<?php
/**
 * Bookings REST API
 *
 * @package FP\Esperienze\REST
 */

namespace FP\Esperienze\REST;

use FP\Esperienze\Booking\BookingManager;
use FP\Esperienze\Core\CapabilityManager;

defined('ABSPATH') || exit;

/**
 * Bookings REST API class
 */
class BookingsAPI {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('rest_api_init', [$this, 'registerRoutes']);
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
            ],
        ]);
    }
    
    /**
     * Check permissions for REST endpoints
     */
    public function checkPermissions(\WP_REST_Request $request): bool {
        return CapabilityManager::canManageFPEsperienze();
    }
    
    /**
     * Get bookings
     *
     * @param \WP_REST_Request $request Request object
     * @return \WP_REST_Response
     */
    public function getBookings(\WP_REST_Request $request): \WP_REST_Response {
        $filters = [];
        
        if ($request->get_param('start')) {
            $filters['date_from'] = $request->get_param('start');
        }
        
        if ($request->get_param('end')) {
            $filters['date_to'] = $request->get_param('end');
        }
        
        if ($request->get_param('status')) {
            $filters['status'] = $request->get_param('status');
        }
        
        if ($request->get_param('product_id')) {
            $filters['product_id'] = $request->get_param('product_id');
        }
        
        $bookings = BookingManager::getBookings($filters);
        
        return new \WP_REST_Response($bookings, 200);
    }
    
    /**
     * Get bookings formatted for FullCalendar
     *
     * @param \WP_REST_Request $request Request object
     * @return \WP_REST_Response
     */
    public function getBookingsForCalendar(\WP_REST_Request $request): \WP_REST_Response {
        $start_date = $request->get_param('start');
        $end_date = $request->get_param('end');
        
        $filters = [
            'date_from' => $start_date,
            'date_to' => $end_date,
        ];
        
        $bookings = BookingManager::getBookings($filters);
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
        
        return new \WP_REST_Response($events, 200);
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