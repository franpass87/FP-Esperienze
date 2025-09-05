<?php
/**
 * Meta Conversions API Manager
 *
 * @package FP\Esperienze\Integrations
 */

namespace FP\Esperienze\Integrations;

defined('ABSPATH') || exit;

/**
 * Handles server-side Meta Conversions API tracking
 */
class MetaCAPIManager {
    
    /**
     * Meta Conversions API endpoint
     */
    private const API_ENDPOINT = 'https://graph.facebook.com/v18.0/';
    
    /**
     * Integration settings
     */
    private array $settings;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->settings = get_option('fp_esperienze_integrations', []);
        
        // Hook into WooCommerce events for server-side tracking
        add_action('woocommerce_checkout_order_processed', [$this, 'trackPurchaseEvent'], 20, 1);
    }
    
    /**
     * Check if Meta CAPI is enabled and properly configured
     */
    public function isEnabled(): bool {
        return !empty($this->settings['meta_capi_enabled']) &&
               !empty($this->settings['meta_pixel_id']) &&
               !empty($this->settings['meta_access_token']) &&
               !empty($this->settings['meta_dataset_id']);
    }
    
    /**
     * Track purchase event via Conversions API
     */
    public function trackPurchaseEvent(int $order_id): void {
        if (!$this->isEnabled()) {
            return;
        }
        
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        
        // Check if order contains experience products
        $has_experience = false;
        $content_ids = [];
        $total_value = 0;
        
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if ($product && $product->get_type() === 'experience') {
                $has_experience = true;
                $content_ids[] = $product->get_id();
                $total_value += floatval($item->get_total());
            }
        }
        
        if (!$has_experience) {
            return;
        }
        
        // Prepare event data
        $event_data = [
            'value' => $total_value,
            'currency' => $order->get_currency(),
            'content_ids' => $content_ids,
            'content_type' => 'product',
            'num_items' => count($content_ids),
        ];
        
        // Generate event ID for deduplication with frontend tracking
        $event_id = 'purchase_' . $order_id . '_' . time();
        
        // Send to Meta Conversions API
        $this->sendEvent('Purchase', $event_data, $order, $event_id);
    }
    
    /**
     * Send event to Meta Conversions API
     */
    public function sendEvent(string $event_name, array $custom_data, $order = null, ?string $event_id = null): bool {
        if (!$this->isEnabled()) {
            return false;
        }
        
        $customer_data = [];
        
        // Extract customer information if order is provided
        if ($order) {
            $customer_data = [
                'em' => hash('sha256', strtolower(trim($order->get_billing_email()))),
                'ph' => $order->get_billing_phone() ? hash('sha256', preg_replace('/[^0-9]/', '', $order->get_billing_phone())) : null,
                'fn' => hash('sha256', strtolower(trim($order->get_billing_first_name()))),
                'ln' => hash('sha256', strtolower(trim($order->get_billing_last_name()))),
                'ct' => hash('sha256', strtolower(trim($order->get_billing_city()))),
                'st' => hash('sha256', strtolower(trim($order->get_billing_state()))),
                'zp' => hash('sha256', strtolower(trim($order->get_billing_postcode()))),
                'country' => hash('sha256', strtolower(trim($order->get_billing_country()))),
            ];
            
            // Remove null values
            $customer_data = array_filter($customer_data);
        }
        
        // Prepare the event payload
        $event_data = [
            'event_name' => $event_name,
            'event_time' => time(),
            'action_source' => 'website',
            'event_source_url' => home_url(),
            'user_data' => $customer_data,
            'custom_data' => $custom_data,
        ];
        
        if ($event_id) {
            $event_data['event_id'] = $event_id;
        }
        
        // Add client user agent and IP if available
        if (!empty($_SERVER['HTTP_USER_AGENT'])) {
            $event_data['user_data']['client_user_agent'] = $_SERVER['HTTP_USER_AGENT'];
        }
        
        if (!empty($_SERVER['REMOTE_ADDR'])) {
            $event_data['user_data']['client_ip_address'] = $_SERVER['REMOTE_ADDR'];
        }
        
        $payload = [
            'data' => [$event_data],
            'access_token' => $this->settings['meta_access_token'],
        ];
        
        // Send to Meta Conversions API
        $endpoint = self::API_ENDPOINT . $this->settings['meta_dataset_id'] . '/events';
        
        $response = wp_remote_post($endpoint, [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode($payload),
            'timeout' => 30,
        ]);
        
        if (is_wp_error($response)) {
            error_log('FP Esperienze Meta CAPI Error: ' . $response->get_error_message());
            return false;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($status_code === 200) {
            $result = json_decode($body, true);
            if (isset($result['events_received']) && $result['events_received'] > 0) {
                error_log('FP Esperienze Meta CAPI Success: ' . $event_name . ' event sent for order ' . ($order ? $order->get_id() : 'unknown'));
                return true;
            }
        }
        
        error_log('FP Esperienze Meta CAPI Failed: Status ' . $status_code . ', Response: ' . $body);
        return false;
    }
    
    /**
     * Test Meta Conversions API connection
     */
    public function testConnection(): array {
        if (!$this->isEnabled()) {
            return [
                'success' => false,
                'message' => __('Meta Conversions API is not properly configured.', 'fp-esperienze')
            ];
        }
        
        // Send a test event
        $test_data = [
            'value' => 10.00,
            'currency' => 'EUR',
            'content_ids' => ['test'],
            'content_type' => 'product',
        ];
        
        $test_payload = [
            'data' => [
                [
                    'event_name' => 'Purchase',
                    'event_time' => time(),
                    'action_source' => 'website',
                    'event_source_url' => home_url(),
                    'test_event_code' => 'TEST12345', // This marks it as a test event
                    'user_data' => [
                        'em' => hash('sha256', 'test@example.com'),
                    ],
                    'custom_data' => $test_data,
                ]
            ],
            'access_token' => $this->settings['meta_access_token'],
        ];
        
        $endpoint = self::API_ENDPOINT . $this->settings['meta_dataset_id'] . '/events';
        
        $response = wp_remote_post($endpoint, [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode($test_payload),
            'timeout' => 30,
        ]);
        
        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => __('Connection failed: ', 'fp-esperienze') . $response->get_error_message()
            ];
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($status_code === 200) {
            $result = json_decode($body, true);
            if (isset($result['events_received']) && $result['events_received'] > 0) {
                return [
                    'success' => true,
                    'message' => __('Meta Conversions API connection successful!', 'fp-esperienze'),
                    'events_received' => $result['events_received']
                ];
            }
        }
        
        return [
            'success' => false,
            'message' => __('API call failed. Status: ', 'fp-esperienze') . $status_code . ', Response: ' . $body
        ];
    }
}