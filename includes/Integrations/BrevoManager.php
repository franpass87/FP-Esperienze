<?php
/**
 * Brevo v3 Integration Manager
 *
 * Handles automatic customer subscription to Brevo email lists when orders
 * containing experience products reach "processing" or "completed" status.
 *
 * Features:
 * - Automatic contact creation/update via Brevo v3 API
 * - Language-based list assignment (Italian/English)
 * - Error logging without exposing customer PII
 * - Toggle ON/OFF via admin settings
 *
 * @package FP\Esperienze\Integrations
 */

namespace FP\Esperienze\Integrations;

defined('ABSPATH') || exit;

/**
 * Manages Brevo email marketing integration
 */
class BrevoManager {
    
    /**
     * Integration settings
     */
    private array $settings;
    
    /**
     * Brevo API base URL
     */
    private const API_BASE_URL = 'https://api.brevo.com/v3';
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->settings = get_option('fp_esperienze_integrations', []);
        
        // Hook into order status changes (same as BookingManager)
        add_action('woocommerce_order_status_processing', [$this, 'handleOrderComplete'], 20, 1);
        add_action('woocommerce_order_status_completed', [$this, 'handleOrderComplete'], 20, 1);
    }
    
    /**
     * Check if Brevo integration is enabled
     */
    public function isEnabled(): bool {
        return !empty($this->settings['brevo_api_key']) && 
               (!empty($this->settings['brevo_list_id_it']) || !empty($this->settings['brevo_list_id_en']));
    }
    
    /**
     * Handle order completion - add customer to Brevo lists
     *
     * @param int $order_id Order ID
     */
    public function handleOrderComplete(int $order_id): void {
        if (!$this->isEnabled()) {
            return;
        }
        
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        
        // Check if order contains experience products
        $has_experience = false;
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if ($product && $product->get_type() === 'experience') {
                $has_experience = true;
                break;
            }
        }
        
        if (!$has_experience) {
            return;
        }
        
        // Extract customer data
        $customer_data = $this->extractCustomerData($order);
        if (!$customer_data) {
            return;
        }
        
        // Create/update contact in Brevo
        $contact_created = $this->createOrUpdateContact($customer_data);
        if (!$contact_created) {
            return;
        }
        
        // Add to appropriate language list
        $this->addContactToList($customer_data['email'], $customer_data['language']);
    }
    
    /**
     * Extract customer data from order
     *
     * @param \WC_Order $order Order object
     * @return array|null Customer data or null if invalid
     */
    private function extractCustomerData(\WC_Order $order): ?array {
        $email = $order->get_billing_email();
        if (empty($email) || !is_email($email)) {
            return null;
        }
        
        $first_name = $order->get_billing_first_name();
        $last_name = $order->get_billing_last_name();
        
        // Determine language from order meta or locale
        $language = $this->determineOrderLanguage($order);
        
        return [
            'email' => $email,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'language' => $language,
        ];
    }
    
    /**
     * Determine order language (IT or EN)
     *
     * @param \WC_Order $order Order object
     * @return string Language code (it|en)
     */
    private function determineOrderLanguage(\WC_Order $order): string {
        // Check order meta for language (from experience items)
        foreach ($order->get_items() as $item) {
            $lang = $item->get_meta('Language');
            if (!empty($lang)) {
                return strtolower($lang) === 'italian' ? 'it' : 'en';
            }
        }
        
        // Fallback to site locale
        $locale = get_locale();
        return substr($locale, 0, 2) === 'it' ? 'it' : 'en';
    }
    
    /**
     * Create or update contact in Brevo
     *
     * @param array $customer_data Customer data
     * @return bool Success
     */
    private function createOrUpdateContact(array $customer_data): bool {
        $url = self::API_BASE_URL . '/contacts';
        
        $body = [
            'email' => $customer_data['email'],
            'attributes' => [],
            'updateEnabled' => true, // Update if contact exists
        ];
        
        // Add name attributes if available
        if (!empty($customer_data['first_name'])) {
            $body['attributes']['FIRSTNAME'] = $customer_data['first_name'];
        }
        if (!empty($customer_data['last_name'])) {
            $body['attributes']['LASTNAME'] = $customer_data['last_name'];
        }
        
        $response = $this->makeApiRequest('POST', $url, $body);
        
        if (is_wp_error($response)) {
            $this->logError('Contact creation failed', ['error' => $response->get_error_message()]);
            return false;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code === 201 || $response_code === 204) {
            // 201 = created, 204 = updated
            return true;
        }
        
        $this->logError('Contact creation unexpected response', ['code' => $response_code]);
        return false;
    }
    
    /**
     * Add contact to language-specific list
     *
     * @param string $email Contact email
     * @param string $language Language code (it|en)
     * @return bool Success
     */
    private function addContactToList(string $email, string $language): bool {
        $list_id = $language === 'it' ? 
            $this->settings['brevo_list_id_it'] : 
            $this->settings['brevo_list_id_en'];
        
        if (empty($list_id)) {
            $this->logError('No list ID configured', ['language' => $language]);
            return false;
        }
        
        $url = self::API_BASE_URL . '/contacts/lists/' . intval($list_id) . '/contacts/add';
        
        $body = [
            'emails' => [$email]
        ];
        
        $response = $this->makeApiRequest('POST', $url, $body);
        
        if (is_wp_error($response)) {
            $this->logError('List subscription failed', ['error' => $response->get_error_message()]);
            return false;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code === 204) {
            return true;
        }
        
        $this->logError('List subscription unexpected response', ['code' => $response_code]);
        return false;
    }
    
    /**
     * Make API request to Brevo
     *
     * @param string $method HTTP method
     * @param string $url API endpoint URL
     * @param array $body Request body
     * @return array|\WP_Error Response or error
     */
    private function makeApiRequest(string $method, string $url, array $body = []): array|\WP_Error {
        $args = [
            'method' => $method,
            'headers' => [
                'api-key' => $this->settings['brevo_api_key'],
                'Content-Type' => 'application/json',
            ],
            'timeout' => 30,
        ];
        
        if (!empty($body)) {
            $args['body'] = wp_json_encode($body);
        }
        
        return wp_remote_request($url, $args);
    }
    
    /**
     * Log error without exposing sensitive data
     *
     * @param string $message Error message
     * @param array $context Context data (no PII)
     */
    private function logError(string $message, array $context = []): void {
        // Remove any potentially sensitive data
        unset($context['email'], $context['api_key'], $context['first_name'], $context['last_name']);
        
        $log_message = sprintf(
            'FP Esperienze - Brevo Integration: %s %s',
            $message,
            !empty($context) ? wp_json_encode($context) : ''
        );
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log($log_message);
        }
    }
}