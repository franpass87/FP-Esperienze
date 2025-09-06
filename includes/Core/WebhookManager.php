<?php
/**
 * Webhook Manager
 *
 * @package FP\Esperienze\Core
 */

namespace FP\Esperienze\Core;

defined('ABSPATH') || exit;

/**
 * Webhook Manager class for handling booking event notifications
 */
class WebhookManager {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('fp_esperienze_booking_created', [$this, 'handleBookingCreated'], 10, 2);
        add_action('fp_esperienze_booking_cancelled', [$this, 'handleBookingCancelled'], 10, 2);
        add_action('fp_esperienze_booking_rescheduled', [$this, 'handleBookingRescheduled'], 10, 3);
    }

    /**
     * Handle new booking created event
     *
     * @param int $booking_id Booking ID
     * @param array $booking_data Booking data
     */
    public function handleBookingCreated(int $booking_id, array $booking_data): void {
        $webhook_url = get_option('fp_esperienze_webhook_new_booking', '');
        if (!$webhook_url) {
            return;
        }

        $payload = [
            'event' => 'booking_created',
            'booking_id' => $booking_id,
            'timestamp' => current_time('c'),
            'data' => $this->sanitizeBookingData($booking_data)
        ];

        $this->sendWebhook($webhook_url, $payload, 'booking_created_' . $booking_id);
    }

    /**
     * Handle booking cancelled event
     *
     * @param int $booking_id Booking ID
     * @param array $booking_data Booking data
     */
    public function handleBookingCancelled(int $booking_id, array $booking_data): void {
        $webhook_url = get_option('fp_esperienze_webhook_cancellation', '');
        if (!$webhook_url) {
            return;
        }

        $payload = [
            'event' => 'booking_cancelled',
            'booking_id' => $booking_id,
            'timestamp' => current_time('c'),
            'data' => $this->sanitizeBookingData($booking_data)
        ];

        $this->sendWebhook($webhook_url, $payload, 'booking_cancelled_' . $booking_id);
    }

    /**
     * Handle booking rescheduled event
     *
     * @param int $booking_id Booking ID
     * @param array $old_data Old booking data
     * @param array $new_data New booking data
     */
    public function handleBookingRescheduled(int $booking_id, array $old_data, array $new_data): void {
        $webhook_url = get_option('fp_esperienze_webhook_reschedule', '');
        if (!$webhook_url) {
            return;
        }

        $payload = [
            'event' => 'booking_rescheduled',
            'booking_id' => $booking_id,
            'timestamp' => current_time('c'),
            'old_data' => $this->sanitizeBookingData($old_data),
            'new_data' => $this->sanitizeBookingData($new_data)
        ];

        $this->sendWebhook($webhook_url, $payload, 'booking_rescheduled_' . $booking_id);
    }

    /**
     * Send webhook with retry logic
     *
     * @param string $url Webhook URL
     * @param array $payload Payload data
     * @param string $event_id Unique event ID for deduplication
     */
    private function sendWebhook(string $url, array $payload, string $event_id): void {
        $validated_url = wp_http_validate_url($url);

        if (!$validated_url || !in_array(wp_parse_url($validated_url, PHP_URL_SCHEME), ['http', 'https'], true)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('FP Esperienze: Invalid webhook URL.');
            }
            return;
        }

        // Add event ID for deduplication
        $payload['event_id'] = $event_id;

        // Sign the payload if secret is configured
        $secret = get_option('fp_esperienze_webhook_secret', '');
        $headers = [
            'Content-Type' => 'application/json',
            'User-Agent' => 'FP-Esperienze-Webhook/1.0'
        ];

        if ($secret) {
            $signature = hash_hmac('sha256', wp_json_encode($payload), $secret);
            $headers['X-FP-Signature'] = 'sha256=' . $signature;
        }

        // Send the webhook
        $response = wp_safe_remote_post($validated_url, [
            'headers' => $headers,
            'body' => wp_json_encode($payload),
            'timeout' => 10,
            'redirection' => 0,
            'httpversion' => '1.1',
            'blocking' => false // Send asynchronously
        ]);

        // If webhook fails, schedule retry
        if (is_wp_error($response)) {
            $this->scheduleRetry($validated_url, $payload, $event_id);
        } else {
            $response_code = wp_remote_retrieve_response_code($response);
            if ($response_code < 200 || $response_code >= 300) {
                $this->scheduleRetry($validated_url, $payload, $event_id);
            }
        }
    }

    /**
     * Schedule webhook retry with exponential backoff
     *
     * @param string $url Webhook URL
     * @param array $payload Payload data
     * @param string $event_id Event ID
     * @param int $attempt Attempt number (starts at 1)
     */
    private function scheduleRetry(string $url, array $payload, string $event_id, int $attempt = 1): void {
        // Maximum 5 retry attempts
        if ($attempt > 5) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('FP Esperienze: Webhook failed after 5 attempts: ' . $event_id);
            }
            return;
        }

        // Exponential backoff: 2^attempt minutes
        $delay = pow(2, $attempt) * 60; // 2, 4, 8, 16, 32 minutes

        wp_schedule_single_event(
            time() + $delay,
            'fp_esperienze_retry_webhook',
            [$url, $payload, $event_id, $attempt + 1]
        );

        // Add the retry hook if not already registered
        if (!has_action('fp_esperienze_retry_webhook', [$this, 'retryWebhook'])) {
            add_action('fp_esperienze_retry_webhook', [$this, 'retryWebhook'], 10, 4);
        }
    }

    /**
     * Retry webhook sending
     *
     * @param string $url Webhook URL
     * @param array $payload Payload data
     * @param string $event_id Event ID
     * @param int $attempt Attempt number
     */
    public function retryWebhook(string $url, array $payload, string $event_id, int $attempt): void {
        $validated_url = wp_http_validate_url($url);

        if (!$validated_url || !in_array(wp_parse_url($validated_url, PHP_URL_SCHEME), ['http', 'https'], true)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('FP Esperienze: Invalid webhook URL during retry.');
            }
            return;
        }

        // Add retry information to payload
        $payload['retry_attempt'] = $attempt;

        $secret = get_option('fp_esperienze_webhook_secret', '');
        $headers = [
            'Content-Type' => 'application/json',
            'User-Agent' => 'FP-Esperienze-Webhook/1.0'
        ];

        if ($secret) {
            $signature = hash_hmac('sha256', wp_json_encode($payload), $secret);
            $headers['X-FP-Signature'] = 'sha256=' . $signature;
        }

        $response = wp_safe_remote_post($validated_url, [
            'headers' => $headers,
            'body' => wp_json_encode($payload),
            'timeout' => 10,
            'redirection' => 0,
            'httpversion' => '1.1',
            'blocking' => true // Blocking for retries to get response
        ]);

        if (is_wp_error($response)) {
            $this->scheduleRetry($validated_url, $payload, $event_id, $attempt);
        } else {
            $response_code = wp_remote_retrieve_response_code($response);
            if ($response_code < 200 || $response_code >= 300) {
                $this->scheduleRetry($validated_url, $payload, $event_id, $attempt);
            } else {
                // Success - log if debug is enabled
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('FP Esperienze: Webhook delivered successfully on attempt ' . $attempt . ': ' . $event_id);
                }
            }
        }
    }

    /**
     * Sanitize booking data for webhook payload (remove PII if needed)
     *
     * @param array $booking_data Raw booking data
     * @return array Sanitized booking data
     */
    private function sanitizeBookingData(array $booking_data): array {
        // Get privacy settings
        $hide_pii = get_option('fp_esperienze_webhook_hide_pii', false);

        $sanitized = [
            'booking_id' => $booking_data['id'] ?? null,
            'order_id' => $booking_data['order_id'] ?? null,
            'product_id' => $booking_data['product_id'] ?? null,
            'booking_date' => $booking_data['booking_date'] ?? null,
            'booking_time' => $booking_data['booking_time'] ?? null,
            'adults' => $booking_data['adults'] ?? 0,
            'children' => $booking_data['children'] ?? 0,
            'status' => $booking_data['status'] ?? null,
            'meeting_point_id' => $booking_data['meeting_point_id'] ?? null,
            'created_at' => $booking_data['created_at'] ?? null,
            'updated_at' => $booking_data['updated_at'] ?? null
        ];

        // Include notes only if PII is not hidden
        if (!$hide_pii) {
            $sanitized['customer_notes'] = $booking_data['customer_notes'] ?? null;
            $sanitized['admin_notes'] = $booking_data['admin_notes'] ?? null;
        }

        // Remove null values
        return array_filter($sanitized, function($value) {
            return $value !== null;
        });
    }

    /**
     * Test webhook endpoint
     *
     * @param string $url Webhook URL
     * @return array Test result
     */
    public static function testWebhook(string $url): array {
        $validated_url = wp_http_validate_url($url);

        if (!$validated_url || !in_array(wp_parse_url($validated_url, PHP_URL_SCHEME), ['http', 'https'], true)) {
            return [
                'success' => false,
                'message' => __('Invalid webhook URL.', 'fp-esperienze')
            ];
        }

        $test_payload = [
            'event' => 'test',
            'timestamp' => current_time('c'),
            'message' => 'This is a test webhook from FP Esperienze'
        ];

        $secret = get_option('fp_esperienze_webhook_secret', '');
        $headers = [
            'Content-Type' => 'application/json',
            'User-Agent' => 'FP-Esperienze-Webhook/1.0'
        ];

        if ($secret) {
            $signature = hash_hmac('sha256', wp_json_encode($test_payload), $secret);
            $headers['X-FP-Signature'] = 'sha256=' . $signature;
        }

        $response = wp_safe_remote_post($validated_url, [
            'headers' => $headers,
            'body' => wp_json_encode($test_payload),
            'timeout' => 10,
            'redirection' => 0,
            'httpversion' => '1.1',
            'blocking' => true
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => $response->get_error_message()
            ];
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        return [
            'success' => $response_code >= 200 && $response_code < 300,
            'status_code' => $response_code,
            'response_body' => $response_body,
            'message' => $response_code >= 200 && $response_code < 300
                ? __('Webhook test successful', 'fp-esperienze')
                : sprintf(__('Webhook test failed with status %d', 'fp-esperienze'), $response_code)
        ];
    }

    /**
     * Get webhook statistics
     *
     * @return array Webhook statistics
     */
    public static function getWebhookStats(): array {
        global $wpdb;

        // This would require a webhook log table for full statistics
        // For now, return basic configuration status
        return [
            'new_booking_url' => get_option('fp_esperienze_webhook_new_booking', ''),
            'cancellation_url' => get_option('fp_esperienze_webhook_cancellation', ''),
            'reschedule_url' => get_option('fp_esperienze_webhook_reschedule', ''),
            'secret_configured' => !empty(get_option('fp_esperienze_webhook_secret', '')),
            'hide_pii' => get_option('fp_esperienze_webhook_hide_pii', false)
        ];
    }
}