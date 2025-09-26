<?php
/**
 * Bookings REST API Controller
 *
 * @package FP\Esperienze\REST
 */

namespace FP\Esperienze\REST;

use FP\Esperienze\Booking\BookingManager;
use FP\Esperienze\Core\CapabilityManager;
use FP\Esperienze\Core\Log;
use FP\Esperienze\Data\ScheduleManager;

defined('ABSPATH') || exit;

/**
 * Bookings REST API Controller
 */
class BookingsController {

    private const DEFAULT_DAY_SPAN = 30;
    private const MAX_DAY_SPAN = 90;
    private const DEFAULT_PER_PAGE = 50;
    private const MAX_PER_PAGE = 200;

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
                ],
                'page' => [
                    'default' => 1,
                    'validate_callback' => function($param) {
                        return is_numeric($param) && (int) $param >= 1;
                    }
                ],
                'per_page' => [
                    'default' => self::DEFAULT_PER_PAGE,
                    'validate_callback' => function($param) {
                        return is_numeric($param) && (int) $param >= 1;
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
        $start_date = null;
        $end_date = null;
        $per_page = self::DEFAULT_PER_PAGE;
        $page = 1;

        try {
            $start_date = $this->sanitizeDate($request->get_param('start')) ?: date('Y-m-d');
            $end_date = $this->sanitizeDate($request->get_param('end'));

            if (!$end_date) {
                $end_date = date('Y-m-d', strtotime($start_date . sprintf(' +%d days', self::DEFAULT_DAY_SPAN)));
            }

            if (strtotime($end_date) < strtotime($start_date)) {
                $end_date = date('Y-m-d', strtotime($start_date . sprintf(' +%d days', self::DEFAULT_DAY_SPAN)));
            }

            $max_end = date('Y-m-d', strtotime($start_date . sprintf(' +%d days', self::MAX_DAY_SPAN)));
            if (strtotime($end_date) > strtotime($max_end)) {
                $end_date = $max_end;
            }

            $per_page = $this->sanitizePerPage($request->get_param('per_page'));
            $page = $this->sanitizePage($request->get_param('page'));
            $offset = ($page - 1) * $per_page;

            // Get bookings for the date range
            $bookings = BookingManager::getBookingsByDateRange($start_date, $end_date, $per_page, $offset);
            $total_bookings = BookingManager::countBookingsByDateRange($start_date, $end_date);

            // Gather unique product and order IDs
            $product_ids = [];
            $order_ids = [];
            foreach ($bookings as $booking) {
                $product_ids[] = (int) $booking->product_id;
                $order_ids[]   = (int) $booking->order_id;
            }
            $product_ids = array_unique($product_ids);
            $order_ids   = array_unique($order_ids);

            // Prefetch products and orders
            $products_map = [];
            if (!empty($product_ids)) {
                $products_map = $this->getCachedProducts($product_ids, $page, $per_page);
            }

            $orders_map = [];
            if (!empty($order_ids)) {
                $orders_map = $this->getCachedOrders($order_ids, $page, $per_page);
            }

            $events = [];

            foreach ($bookings as $booking) {
                $product = $products_map[$booking->product_id] ?? null;
                if (!$product) {
                    continue;
                }

                // Get order to retrieve customer info and total amount
                $order = $orders_map[$booking->order_id] ?? null;
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
            
            $total_pages = max(1, (int) ceil($total_bookings / $per_page));

            return rest_ensure_response([
                'events' => $events,
                'meta' => [
                    'total' => $total_bookings,
                    'page' => $page,
                    'per_page' => $per_page,
                    'total_pages' => $total_pages,
                    'has_more' => $page < $total_pages,
                    'window' => [
                        'start' => $start_date,
                        'end' => $end_date,
                    ],
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Events API error: ' . $e->getMessage(), [
                'start_date' => $start_date ?? '',
                'end_date' => $end_date ?? '',
                'page' => $page ?? null,
                'per_page' => $per_page ?? null,
            ]);

            return new \WP_Error(
                'events_fetch_failed',
                __('Failed to fetch booking events. Please try again.', 'fp-esperienze'),
                ['status' => 500]
            );
        }
    }

    /**
     * Sanitize incoming date strings.
     */
    private function sanitizeDate($value): ?string {
        if (empty($value)) {
            return null;
        }

        $value = substr((string) $value, 0, 10);
        $date = \DateTime::createFromFormat('Y-m-d', $value);

        if ($date instanceof \DateTime && $date->format('Y-m-d') === $value) {
            return $value;
        }

        return null;
    }

    /**
     * Normalize per-page request values.
     */
    private function sanitizePerPage($per_page): int {
        $per_page = (int) $per_page;

        if ($per_page <= 0) {
            $per_page = self::DEFAULT_PER_PAGE;
        }

        return min($per_page, self::MAX_PER_PAGE);
    }

    /**
     * Normalize page request values.
     */
    private function sanitizePage($page): int {
        $page = (int) $page;

        if ($page <= 0) {
            $page = 1;
        }

        return $page;
    }

    /**
     * Build a cache key for query payloads.
     */
    private function buildCacheKey(array $ids, int $page, int $per_page, string $prefix): string {
        sort($ids, SORT_NUMERIC);

        $json_ids = function_exists('wp_json_encode') ? wp_json_encode($ids) : json_encode($ids);

        return $prefix . '_' . md5((string) $json_ids . "|{$page}|{$per_page}");
    }

    /**
     * Retrieve products with caching keyed to the current request window.
     *
     * @param array<int> $product_ids Product IDs to preload
     * @param int $page Current page number
     * @param int $per_page Items per page
     * @return array<int, \WC_Product>
     */
    private function getCachedProducts(array $product_ids, int $page, int $per_page): array {
        if (empty($product_ids)) {
            return [];
        }

        $cache_group = 'fp_esperienze_events';
        $cache_key = $this->buildCacheKey($product_ids, $page, $per_page, 'products');

        if (function_exists('wp_cache_get')) {
            $cached = wp_cache_get($cache_key, $cache_group);
            if (false !== $cached && is_array($cached)) {
                return $cached;
            }
        }

        $products_map = [];
        $products = wc_get_products([
            'include' => $product_ids,
            'limit' => count($product_ids),
        ]);

        foreach ($products as $product) {
            $products_map[$product->get_id()] = $product;
        }

        if (function_exists('wp_cache_set')) {
            $expiration = defined('HOUR_IN_SECONDS') ? HOUR_IN_SECONDS : 3600;
            wp_cache_set($cache_key, $products_map, $cache_group, $expiration);
        }

        return $products_map;
    }

    /**
     * Retrieve orders with caching keyed to the current request window.
     *
     * @param array<int> $order_ids Order IDs to preload
     * @param int $page Current page number
     * @param int $per_page Items per page
     * @return array<int, \WC_Order>
     */
    private function getCachedOrders(array $order_ids, int $page, int $per_page): array {
        if (empty($order_ids)) {
            return [];
        }

        $cache_group = 'fp_esperienze_events';
        $cache_key = $this->buildCacheKey($order_ids, $page, $per_page, 'orders');

        if (function_exists('wp_cache_get')) {
            $cached = wp_cache_get($cache_key, $cache_group);
            if (false !== $cached && is_array($cached)) {
                return $cached;
            }
        }

        $orders_map = [];
        $orders = wc_get_orders([
            'include' => $order_ids,
            'limit' => count($order_ids),
        ]);

        foreach ($orders as $order) {
            $orders_map[$order->get_id()] = $order;
        }

        if (function_exists('wp_cache_set')) {
            $expiration = defined('HOUR_IN_SECONDS') ? HOUR_IN_SECONDS : 3600;
            wp_cache_set($cache_key, $orders_map, $cache_group, $expiration);
        }

        return $orders_map;
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