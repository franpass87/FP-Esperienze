<?php
/**
 * Bookings REST API Controller
 *
 * @package FP\Esperienze\REST
 */

namespace FP\Esperienze\REST;

use FP\Esperienze\Data\BookingManager;
use FP\Esperienze\Core\CapabilityManager;
use FP\Esperienze\Core\Log;
use FP\Esperienze\Data\ScheduleManager;

defined('ABSPATH') || exit;

/**
 * Bookings REST API Controller
 */
class BookingsController {

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
        register_rest_route('fp-esperienze/v1', '/events', [
            'methods' => 'GET',
            'callback' => [$this, 'getEvents'],
            'permission_callback' => [$this, 'checkPermissions'],
            'args' => [
                'start' => [
                    'default' => '',
                    'validate_callback' => function($param) {
                        return empty($param) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $param);
                    }
                ],
                'end' => [
                    'default' => '',
                    'validate_callback' => function($param) {
                        return empty($param) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $param);
                    }
                ]
            ]
        ]);
    }

    /**
     * Check permissions for events endpoint
     *
     * @param \WP_REST_Request $request Request object
     * @return bool|\WP_Error
     */
    public function checkPermissions(\WP_REST_Request $request) {
        if (!CapabilityManager::canManageFPEsperienze()) {
            return new \WP_Error(
                'insufficient_permissions',
                __('You do not have permission to access bookings data.', 'fp-esperienze'),
                ['status' => 403]
            );
        }
        
        return true;
    }

    /**
     * Get events for calendar display
     *
     * @param \WP_REST_Request $request Request object
     * @return \WP_REST_Response|\WP_Error
     */
    public function getEvents(\WP_REST_Request $request) {
        $start_time = microtime(true);
        
        try {
            $start_date = $request->get_param('start') ?: date('Y-m-d');
            $end_date = $request->get_param('end') ?: date('Y-m-d', strtotime('+30 days'));

            // Get bookings for the date range
            $bookings = BookingManager::getBookingsByDateRange($start_date, $end_date);
            
            $events = [];
            
            foreach ($bookings as $booking) {
                $product = wc_get_product($booking->product_id);
                if (!$product) {
                    continue;
                }
                
                // Get order to retrieve customer info and total amount
                $order = wc_get_order($booking->order_id);
                $customer_name = '';
                $customer_email = '';
                $total_amount = 0;
                
                if ($order) {
                    $customer_name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
                    $customer_email = $order->get_billing_email();
                    
                    // Get order item to calculate total amount for this booking
                    $order_item = $order->get_item($booking->order_item_id);
                    if ($order_item) {
                        $total_amount = $order_item->get_total();
                    }
                }
                
                $events[] = [
                    'id' => $booking->id,
                    'title' => $product->get_name(),
                    'start' => $booking->booking_date . 'T' . $booking->booking_time,
                    'end' => $this->calculateEndTime($booking->booking_date, $booking->booking_time, $product),
                    'backgroundColor' => $this->getStatusColor($booking->status),
                    'borderColor' => $this->getStatusColor($booking->status),
                    'extendedProps' => [
                        'booking_id' => $booking->id,
                        'product_id' => $booking->product_id,
                        'status' => $booking->status,
                        'participants' => $booking->adults + $booking->children,
                        'adult_count' => $booking->adults,
                        'child_count' => $booking->children,
                        'customer_name' => trim($customer_name),
                        'customer_email' => $customer_email,
                        'total_amount' => $total_amount
                    ]
                ];
            }
            
            Log::performance('Bookings Events API', $start_time);
            
            return rest_ensure_response([
                'events' => $events,
                'total' => count($events)
            ]);
            
        } catch (\Exception $e) {
            Log::error('Events API error: ' . $e->getMessage(), [
                'start_date' => $start_date ?? '',
                'end_date' => $end_date ?? ''
            ]);
            
            return new \WP_Error(
                'events_fetch_failed',
                __('Failed to fetch booking events. Please try again.', 'fp-esperienze'),
                ['status' => 500]
            );
        }
    }

    /**
     * Calculate end time for booking event
     *
     * @param string $date Booking date
     * @param string $time Booking time
     * @param \WC_Product $product Product object
     * @return string End time in ISO format
     */
    private function calculateEndTime(string $date, string $time, \WC_Product $product): string {
        $day_of_week = (int) date('w', strtotime($date));
        $duration = 60;
        $schedules = ScheduleManager::getSchedulesForDay($product->get_id(), $day_of_week);
        foreach ($schedules as $schedule) {
            if (substr($schedule->start_time, 0, 5) === substr($time, 0, 5)) {
                $duration = (int) ($schedule->duration_min ?: 60);
                break;
            }
        }
        
        $start_datetime = \DateTime::createFromFormat('Y-m-d H:i:s', $date . ' ' . $time);
        $end_datetime = clone $start_datetime;
        $end_datetime->add(new \DateInterval('PT' . intval($duration) . 'M'));
        
        return $end_datetime->format('Y-m-d\TH:i:s');
    }

    /**
     * Get color for booking status
     *
     * @param string $status Booking status
     * @return string Color code
     */
    private function getStatusColor(string $status): string {
        switch ($status) {
            case 'confirmed':
                return '#28a745';
            case 'pending':
                return '#ffc107';
            case 'cancelled':
                return '#dc3545';
            case 'completed':
                return '#17a2b8';
            default:
                return '#6c757d';
        }
    }
}