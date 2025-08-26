<?php
/**
 * Availability REST API Controller
 *
 * @package FP\Esperienze\REST
 */

namespace FP\Esperienze\REST;

use WP_REST_Controller;
use WP_REST_Server;
use WP_Error;

/**
 * Handles availability REST API endpoints
 */
class AvailabilityController extends WP_REST_Controller {
    
    /**
     * Namespace
     */
    protected $namespace = 'fp-exp/v1';
    
    /**
     * Rest base
     */
    protected $rest_base = 'availability';
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }
    
    /**
     * Register routes
     */
    public function register_routes() {
        register_rest_route($this->namespace, '/' . $this->rest_base, [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_availability'],
                'permission_callback' => '__return_true', // Public endpoint
                'args'                => [
                    'product_id' => [
                        'required' => true,
                        'type'     => 'integer',
                        'sanitize_callback' => 'absint',
                    ],
                    'date' => [
                        'required' => true,
                        'type'     => 'string',
                        'pattern'  => '^\d{4}-\d{2}-\d{2}$',
                        'sanitize_callback' => 'sanitize_text_field',
                    ]
                ]
            ]
        ]);
    }
    
    /**
     * Get availability for a product on a specific date
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response|\WP_Error
     */
    public function get_availability($request) {
        $product_id = $request->get_param('product_id');
        $date = $request->get_param('date');
        
        // Validate product exists and is an experience
        $product = wc_get_product($product_id);
        if (!$product) {
            return new WP_Error('invalid_product', __('Product not found', 'fp-esperienze'), ['status' => 404]);
        }
        
        // Validate date format
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return new WP_Error('invalid_date', __('Invalid date format. Use YYYY-MM-DD', 'fp-esperienze'), ['status' => 400]);
        }
        
        // Check if date is in the past
        if (strtotime($date) < strtotime('today')) {
            return new WP_Error('past_date', __('Cannot book dates in the past', 'fp-esperienze'), ['status' => 400]);
        }
        
        // For now, return mock data - in a real implementation this would query the database
        $slots = $this->get_mock_availability($product_id, $date);
        
        return rest_ensure_response([
            'product_id' => $product_id,
            'date' => $date,
            'slots' => $slots
        ]);
    }
    
    /**
     * Get mock availability data
     * 
     * @param int $product_id
     * @param string $date
     * @return array
     */
    private function get_mock_availability($product_id, $date) {
        // Mock data for demonstration
        $base_slots = [
            ['time' => '09:00', 'capacity' => 20, 'booked' => 5],
            ['time' => '11:00', 'capacity' => 20, 'booked' => 15],
            ['time' => '14:00', 'capacity' => 20, 'booked' => 18],
            ['time' => '16:00', 'capacity' => 20, 'booked' => 20], // Sold out
            ['time' => '18:00', 'capacity' => 20, 'booked' => 2],
        ];
        
        $cutoff_hours = 2; // 2 hours cutoff
        $current_time = current_time('timestamp');
        
        $slots = [];
        foreach ($base_slots as $slot) {
            $slot_datetime = strtotime($date . ' ' . $slot['time']);
            $capacity_left = $slot['capacity'] - $slot['booked'];
            
            // Check if slot has passed cutoff time
            $is_available = ($slot_datetime - ($cutoff_hours * 3600)) > $current_time;
            
            $slots[] = [
                'time' => $slot['time'],
                'capacity' => $slot['capacity'],
                'booked' => $slot['booked'],
                'capacity_left' => $capacity_left,
                'available' => $is_available && $capacity_left > 0,
                'cutoff_passed' => !$is_available
            ];
        }
        
        return $slots;
    }
}