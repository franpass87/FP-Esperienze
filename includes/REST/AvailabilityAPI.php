<?php
/**
 * Availability REST API
 *
 * @package FP\Esperienze\REST
 */

namespace FP\Esperienze\REST;

use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

defined('ABSPATH') || exit;

/**
 * Availability API class
 */
class AvailabilityAPI {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('rest_api_init', [$this, 'registerRoutes']);
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
        $product_id = $request->get_param('product_id');
        $date = $request->get_param('date');

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
        $date_obj = \DateTime::createFromFormat('Y-m-d', $date);
        if (!$date_obj || $date_obj->format('Y-m-d') !== $date) {
            return new WP_Error(
                'invalid_date',
                __('Invalid date format. Use YYYY-MM-DD.', 'fp-esperienze'),
                ['status' => 400]
            );
        }

        // Check if date is in the future
        $today = new \DateTime();
        if ($date_obj < $today) {
            return new WP_Error(
                'past_date',
                __('Cannot check availability for past dates.', 'fp-esperienze'),
                ['status' => 400]
            );
        }

        // Generate dummy availability slots
        $slots = $this->generateDummySlots($product, $date);

        $response_data = [
            'product_id' => $product_id,
            'date'       => $date,
            'slots'      => $slots,
            'total_slots' => count($slots),
        ];

        return new WP_REST_Response($response_data, 200);
    }

    /**
     * Generate dummy availability slots
     *
     * @param \WC_Product $product Product object
     * @param string $date Date string
     * @return array
     */
    private function generateDummySlots($product, string $date): array {
        $slots = [];
        $capacity = $product->get_capacity() ?: 10;
        $duration = $product->get_duration() ?: 60;

        // Generate slots from 9 AM to 6 PM every 2 hours
        $start_times = ['09:00', '11:00', '13:00', '15:00', '17:00'];

        foreach ($start_times as $start_time) {
            $start_datetime = new \DateTime($date . ' ' . $start_time);
            $end_datetime = clone $start_datetime;
            $end_datetime->add(new \DateInterval('PT' . $duration . 'M'));

            // Random availability (70% chance of being available)
            $is_available = rand(1, 10) <= 7;
            $booked_spots = $is_available ? rand(0, $capacity - 1) : $capacity;
            $available_spots = $capacity - $booked_spots;

            $slots[] = [
                'start_time'      => $start_datetime->format('H:i'),
                'end_time'        => $end_datetime->format('H:i'),
                'capacity'        => $capacity,
                'booked'          => $booked_spots,
                'available'       => $available_spots,
                'is_available'    => $is_available && $available_spots > 0,
                'adult_price'     => $product->get_adult_price() ?: 0,
                'child_price'     => $product->get_child_price() ?: 0,
            ];
        }

        return $slots;
    }
}