<?php
/**
 * Enhanced Email Marketing Manager
 *
 * Advanced email marketing automation with Brevo integration and WordPress fallback.
 * Includes workflow automation, abandoned cart recovery, and review requests.
 *
 * @package FP\Esperienze\Integrations
 */

namespace FP\Esperienze\Integrations;

use FP\Esperienze\Booking\BookingManager;
use FP\Esperienze\Core\CapabilityManager;
use FP\Esperienze\Data\MeetingPointManager;

defined('ABSPATH') || exit;

/**
 * Enhanced Email Marketing Manager
 */
class EmailMarketingManager {

    /**
     * Integration settings
     */
    private array $settings;

    /**
     * Brevo manager instance
     */
    private BrevoManager $brevo_manager;

    /**
     * Constructor
     */
    public function __construct() {
        $this->settings = get_option('fp_esperienze_integrations', []);
        $this->brevo_manager = new BrevoManager();

        // Email automation hooks
        add_action('fp_booking_confirmed', [$this, 'handleBookingConfirmed'], 10, 2);
        add_action('fp_booking_completed', [$this, 'handleBookingCompleted'], 10, 2);
        add_action('woocommerce_cart_updated', [$this, 'trackCartActivity']);
        add_action('fp_send_review_request', [$this, 'sendReviewRequest'], 10, 2);
        add_action('fp_send_pre_experience_email', [$this, 'sendPreExperienceEmail'], 10, 2);
        add_action('fp_send_upselling_email', [$this, 'sendScheduledUpsellingEmail'], 10, 2);
        
        // Scheduled events
        add_action('fp_check_abandoned_carts', [$this, 'processAbandonedCarts']);
        add_action('fp_send_upselling_emails', [$this, 'processUpsellingCampaigns']);
        
        // Schedule events if not already scheduled
        if (!wp_next_scheduled('fp_check_abandoned_carts')) {
            wp_schedule_event(time(), 'hourly', 'fp_check_abandoned_carts');
        }
        if (!wp_next_scheduled('fp_send_upselling_emails')) {
            wp_schedule_event(time(), 'daily', 'fp_send_upselling_emails');
        }

        // Admin AJAX handlers
        add_action('wp_ajax_fp_test_email_system', [$this, 'ajaxTestEmailSystem']);
        add_action('wp_ajax_fp_send_campaign', [$this, 'ajaxSendCampaign']);
        add_action('wp_ajax_fp_get_email_templates', [$this, 'ajaxGetEmailTemplates']);
    }

    /**
     * Handle booking confirmation
     *
     * @param int $booking_id Booking ID
     * @param array $booking_data Booking data
     */
    public function handleBookingConfirmed(int $booking_id, array $booking_data): void {
        $this->sendBookingConfirmationEmail($booking_id, $booking_data);
        $this->schedulePreExperienceEmail($booking_id, $booking_data);
    }

    /**
     * Handle booking completion
     *
     * @param int $booking_id Booking ID
     * @param array $booking_data Booking data
     */
    public function handleBookingCompleted(int $booking_id, array $booking_data): void {
        $this->sendBookingCompletionEmail($booking_id, $booking_data);
        $this->scheduleReviewRequestEmail($booking_id, $booking_data);
        $this->scheduleUpsellingEmail($booking_id, $booking_data);
    }

    /**
     * Track cart activity for abandoned cart detection
     */
    public function trackCartActivity(): void {
        if (!WC()->cart || WC()->cart->is_empty()) {
            return;
        }

        // Check if cart contains experiences
        $has_experience = false;
        foreach (WC()->cart->get_cart() as $cart_item) {
            $product = $cart_item['data'];
            if ($product && $product->get_type() === 'experience') {
                $has_experience = true;
                break;
            }
        }

        if (!$has_experience) {
            return;
        }

        $user_id = get_current_user_id();
        $session_id = sanitize_key(WC()->session->get_customer_id());

        $cart_data = [
            'user_id' => $user_id,
            'session_id' => $session_id,
            'email' => $user_id ? get_user_meta($user_id, 'billing_email', true) : '',
            'cart_contents' => WC()->cart->get_cart_contents(),
            'cart_total' => WC()->cart->get_total('raw'),
            'last_activity' => current_time('mysql')
        ];

        set_transient('fp_cart_activity_' . ($user_id ?: $session_id), $cart_data, HOUR_IN_SECONDS * 2);
    }

    /**
     * Process abandoned carts
     */
    public function processAbandonedCarts(): void {
        global $wpdb;

        // Find abandoned carts (inactive for 1+ hours)
        $abandoned_time = date('Y-m-d H:i:s', strtotime('-1 hour'));
        
        $abandoned_carts = $wpdb->get_results($wpdb->prepare("
            SELECT option_name, option_value
            FROM {$wpdb->options}
            WHERE option_name LIKE '_transient_fp_cart_activity_%'
            AND option_value LIKE %s
        ", '%"last_activity"%'));

        foreach ($abandoned_carts as $cart_option) {
            $transient_key = str_replace('_transient_', '', $cart_option->option_name);
            $cart_data = get_transient($transient_key);

            if (!$cart_data || !is_array($cart_data)) {
                continue;
            }

            if ($cart_data['last_activity'] < $abandoned_time) {
                $this->sendAbandonedCartEmail($cart_data);

                // Remove from tracking after sending email
                delete_transient($transient_key);
            }
        }
    }

    /**
     * Process upselling campaigns
     */
    public function processUpsellingCampaigns(): void {
        if (!function_exists('wc_get_orders')) {
            return;
        }

        $date_threshold_timestamp = current_time('timestamp') - WEEK_IN_SECONDS;

        $orders = wc_get_orders([
            'status' => ['completed'],
            'limit' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
            'date_created' => '<' . $date_threshold_timestamp,
            'return' => 'objects',
        ]);

        if (empty($orders)) {
            return;
        }

        $processed_customers = [];
        $eligible_customers = [];

        foreach ($orders as $order) {
            if (!$order instanceof \WC_Order) {
                continue;
            }

            $order_date = $order->get_date_created();
            if (!$order_date || !$this->orderContainsExperience($order)) {
                continue;
            }

            $customer_id = (int) $order->get_customer_id();
            $billing_email = $order->get_billing_email();

            if (empty($billing_email)) {
                continue;
            }

            $customer_key = $customer_id > 0
                ? 'id_' . $customer_id
                : 'email_' . strtolower($billing_email);

            if (isset($processed_customers[$customer_key])) {
                continue;
            }

            $has_recent_orders = $this->customerHasRecentOrder($order, $order_date->getTimestamp());
            $processed_customers[$customer_key] = true;

            if ($has_recent_orders) {
                continue;
            }

            $customer_name = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());

            $eligible_customers[] = (object) [
                'customer_id' => $customer_id,
                'billing_email' => $billing_email,
                'customer_name' => $customer_name,
            ];
        }

        foreach ($eligible_customers as $customer) {
            $this->sendUpsellingEmail($customer);
        }
    }

    /**
     * Check if an order contains at least one experience product.
     */
    private function orderContainsExperience(\WC_Order $order): bool {
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();

            if ($product && $product->get_type() === 'experience') {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine whether the customer has more recent orders than the provided order.
     */
    private function customerHasRecentOrder(\WC_Order $order, int $order_timestamp): bool {
        if (!function_exists('wc_get_orders')) {
            return false;
        }

        $customer_id = (int) $order->get_customer_id();
        $billing_email = $order->get_billing_email();

        $query_args = [
            'status' => ['processing', 'completed'],
            'limit' => 1,
            'orderby' => 'date',
            'order' => 'DESC',
            'date_created' => '>' . $order_timestamp,
            'return' => 'ids',
        ];

        if ($customer_id > 0) {
            $query_args['customer'] = $customer_id;
        } elseif (!empty($billing_email)) {
            $query_args['billing_email'] = $billing_email;
        } else {
            return false;
        }

        $recent_orders = wc_get_orders($query_args);

        return !empty($recent_orders);
    }

    /**
     * Send review request
     *
     * @param int $booking_id Booking ID
     * @param array $booking_data Booking data
     */
    public function sendReviewRequest(int $booking_id, array $booking_data): void {
        $template_data = [
            'booking_id' => $booking_id,
            'customer_name' => $booking_data['customer_name'] ?? '',
            'experience_name' => $booking_data['experience_name'] ?? '',
            'review_link' => $this->generateReviewLink($booking_data['product_id'] ?? 0)
        ];

        $this->sendEmail(
            'review_request',
            $booking_data['customer_email'] ?? '',
            __('How was your experience?', 'fp-esperienze'),
            $template_data
        );
    }

    /**
     * Send pre-experience reminder email.
     *
     * @param int   $booking_id   Booking ID.
     * @param array $booking_data Booking data.
     * @return bool
     */
    public function sendPreExperienceEmail(int $booking_id, array $booking_data): bool {
        $prepared_data = $this->prepareBookingEmailData($booking_id, $booking_data);

        if (!$prepared_data) {
            return false;
        }

        $customer_email = $prepared_data['customer_email'] ?? '';
        if (empty($customer_email) || !is_email($customer_email)) {
            $this->logError('Invalid customer email for pre-experience reminder', [
                'booking_id' => $booking_id,
                'order_id' => $prepared_data['order_id'] ?? null,
                'customer_email' => $customer_email,
            ]);

            return false;
        }

        $timestamp = $this->parseBookingTimestamp(
            $prepared_data['booking_date'] ?? '',
            $prepared_data['booking_time'] ?? ''
        );

        $template_data = [
            'booking_id' => $booking_id,
            'customer_name' => $prepared_data['customer_name'] ?? '',
            'experience_name' => $prepared_data['experience_name'] ?? '',
            'booking_date' => $prepared_data['booking_date'] ?? '',
            'booking_time' => $prepared_data['booking_time'] ?? '',
            'booking_datetime' => $timestamp ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $timestamp) : trim(
                ($prepared_data['booking_date'] ?? '') . ' ' . ($prepared_data['booking_time'] ?? '')
            ),
            'booking_date_formatted' => $timestamp ? date_i18n(get_option('date_format'), $timestamp) : ($prepared_data['booking_date'] ?? ''),
            'booking_time_formatted' => $timestamp ? date_i18n(get_option('time_format'), $timestamp) : ($prepared_data['booking_time'] ?? ''),
            'meeting_point' => $prepared_data['meeting_point'] ?? '',
            'meeting_point_address' => $prepared_data['meeting_point_address'] ?? '',
            'meeting_point_note' => $prepared_data['meeting_point_note'] ?? '',
            'participants' => (int) ($prepared_data['participants'] ?? 1),
            'total_amount' => $prepared_data['total_amount'] ?? 0,
            'booking_details_url' => $prepared_data['booking_details_url'] ?? '',
            'experience_url' => $prepared_data['experience_url'] ?? '',
            'customer_phone' => $prepared_data['customer_phone'] ?? '',
        ];

        return $this->sendEmail(
            'pre_experience_reminder',
            $customer_email,
            __('Reminder: Your experience is coming up', 'fp-esperienze'),
            $template_data
        );
    }

    /**
     * Send scheduled upselling email based on booking data.
     *
     * @param int   $booking_id   Booking ID.
     * @param array $booking_data Booking data.
     * @return bool
     */
    public function sendScheduledUpsellingEmail(int $booking_id, array $booking_data): bool {
        $prepared_data = $this->prepareBookingEmailData($booking_id, $booking_data);

        if (!$prepared_data) {
            return false;
        }

        $customer_email = $prepared_data['customer_email'] ?? '';
        if (empty($customer_email) || !is_email($customer_email)) {
            $this->logError('Invalid customer email for upselling reminder', [
                'booking_id' => $booking_id,
                'order_id' => $prepared_data['order_id'] ?? null,
                'customer_email' => $customer_email,
            ]);

            return false;
        }

        $customer = (object) [
            'customer_id' => (int) ($prepared_data['customer_id'] ?? 0),
            'billing_email' => $customer_email,
            'customer_name' => $prepared_data['customer_name'] ?? '',
        ];

        return $this->sendUpsellingEmail($customer);
    }

    /**
     * Test email system (AJAX handler)
     */
    public function ajaxTestEmailSystem(): void {
        if (!CapabilityManager::currentUserCan('manage_settings')) {
            wp_send_json_error(['message' => __('Unauthorized', 'fp-esperienze')], 403);
        }

        check_ajax_referer('fp_esperienze_admin', 'nonce');

        $test_email = sanitize_email(wp_unslash($_POST['test_email'] ?? ''));
        $system_type = sanitize_text_field(wp_unslash($_POST['system_type'] ?? 'auto'));

        if (!is_email($test_email)) {
            wp_send_json_error(['message' => __('Invalid email address', 'fp-esperienze')]);
            return;
        }

        $test_result = $this->testEmailSystem($test_email, $system_type);

        if ($test_result['success']) {
            wp_send_json_success($test_result);
        } else {
            wp_send_json_error($test_result);
        }
    }

    /**
     * Send email campaign (AJAX handler)
     */
    public function ajaxSendCampaign(): void {
        if (!CapabilityManager::currentUserCan('manage_campaigns')) {
            wp_send_json_error(['message' => __('Unauthorized', 'fp-esperienze')], 403);
        }

        check_ajax_referer('fp_esperienze_admin', 'nonce');

        $campaign_data = [
            'template' => sanitize_text_field(wp_unslash($_POST['template'] ?? '')),
            'subject' => sanitize_text_field(wp_unslash($_POST['subject'] ?? '')),
            'recipient_type' => sanitize_text_field(wp_unslash($_POST['recipient_type'] ?? 'all')),
            'custom_content' => wp_kses_post(wp_unslash($_POST['custom_content'] ?? ''))
        ];

        $result = $this->sendCampaign($campaign_data);

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * Get email templates (AJAX handler)
     */
    public function ajaxGetEmailTemplates(): void {
        if (!CapabilityManager::currentUserCan('view_reports')) {
            wp_send_json_error(['message' => __('Unauthorized', 'fp-esperienze')], 403);
        }

        check_ajax_referer('fp_esperienze_admin', 'nonce');

        $templates = $this->getEmailTemplates();

        wp_send_json_success(['templates' => $templates]);
    }

    /**
     * Send booking confirmation email
     *
     * @param int $booking_id Booking ID
     * @param array $booking_data Booking data
     */
    private function sendBookingConfirmationEmail(int $booking_id, array $booking_data): void {
        $template_data = [
            'booking_id' => $booking_id,
            'customer_name' => $booking_data['customer_name'] ?? '',
            'experience_name' => $booking_data['experience_name'] ?? '',
            'booking_date' => $booking_data['booking_date'] ?? '',
            'meeting_point' => $booking_data['meeting_point'] ?? '',
            'participants' => $booking_data['participants'] ?? 1,
            'total_amount' => $booking_data['total_amount'] ?? 0
        ];

        $this->sendEmail(
            'booking_confirmation',
            $booking_data['customer_email'] ?? '',
            __('Booking Confirmation', 'fp-esperienze'),
            $template_data
        );
    }

    /**
     * Send booking completion email
     *
     * @param int $booking_id Booking ID
     * @param array $booking_data Booking data
     */
    private function sendBookingCompletionEmail(int $booking_id, array $booking_data): void {
        $template_data = [
            'booking_id' => $booking_id,
            'customer_name' => $booking_data['customer_name'] ?? '',
            'experience_name' => $booking_data['experience_name'] ?? ''
        ];

        $this->sendEmail(
            'booking_completion',
            $booking_data['customer_email'] ?? '',
            __('Thank you for your experience!', 'fp-esperienze'),
            $template_data
        );
    }

    /**
     * Schedule pre-experience email
     *
     * @param int $booking_id Booking ID
     * @param array $booking_data Booking data
     */
    private function schedulePreExperienceEmail(int $booking_id, array $booking_data): void {
        $booking_date = $booking_data['booking_date'] ?? '';
        if (!$booking_date) {
            return;
        }

        $send_time = strtotime($booking_date . ' -1 day');
        
        if ($send_time > time()) {
            if (!wp_next_scheduled('fp_send_pre_experience_email', [$booking_id, $booking_data])) {
                wp_schedule_single_event($send_time, 'fp_send_pre_experience_email', [$booking_id, $booking_data]);
            }
        }
    }

    /**
     * Schedule review request email
     *
     * @param int $booking_id Booking ID
     * @param array $booking_data Booking data
     */
    private function scheduleReviewRequestEmail(int $booking_id, array $booking_data): void {
        $send_time = time() + (2 * DAY_IN_SECONDS); // 2 days after completion
        
        if (!wp_next_scheduled('fp_send_review_request', [$booking_id, $booking_data])) {
            wp_schedule_single_event($send_time, 'fp_send_review_request', [$booking_id, $booking_data]);
        }
    }

    /**
     * Schedule upselling email
     *
     * @param int $booking_id Booking ID
     * @param array $booking_data Booking data
     */
    private function scheduleUpsellingEmail(int $booking_id, array $booking_data): void {
        $send_time = time() + (7 * DAY_IN_SECONDS); // 7 days after completion

        if (!wp_next_scheduled('fp_send_upselling_email', [$booking_id, $booking_data])) {
            wp_schedule_single_event($send_time, 'fp_send_upselling_email', [$booking_id, $booking_data]);
        }
    }

    /**
     * Prepare booking data with order, product and customer information.
     *
     * @param int   $booking_id   Booking ID.
     * @param array $booking_data Booking data.
     * @return array|null
     */
    private function prepareBookingEmailData(int $booking_id, array $booking_data): ?array {
        $booking = BookingManager::getBooking($booking_id);
        if ($booking) {
            $booking_data = array_merge((array) $booking, $booking_data);
        }

        $order_id = isset($booking_data['order_id']) ? (int) $booking_data['order_id'] : 0;
        if ($order_id <= 0) {
            $this->logError('Missing order ID for booking email', ['booking_id' => $booking_id]);
            return null;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            $this->logError('Order not found for booking email', [
                'booking_id' => $booking_id,
                'order_id' => $order_id,
            ]);
            return null;
        }

        if (empty($booking_data['customer_email'])) {
            $booking_data['customer_email'] = $order->get_billing_email();
        }

        $customer_name = $booking_data['customer_name'] ?? '';
        if ($customer_name === '') {
            $customer_name = trim(sprintf('%s %s', $order->get_billing_first_name(), $order->get_billing_last_name()));
        }

        if ($customer_name === '') {
            $customer_name = $order->get_formatted_billing_full_name() ?: $order->get_billing_email();
        }

        $booking_data['customer_name'] = $customer_name;

        if (empty($booking_data['customer_phone'])) {
            $booking_data['customer_phone'] = $order->get_billing_phone();
        }

        if (empty($booking_data['total_amount'])) {
            $booking_data['total_amount'] = $order->get_total();
        }

        if (empty($booking_data['booking_details_url'])) {
            $booking_data['booking_details_url'] = $order->get_view_order_url();
        }

        if (!isset($booking_data['customer_id']) || $booking_data['customer_id'] === null || $booking_data['customer_id'] === '') {
            $booking_data['customer_id'] = $order->get_customer_id() ?: $order->get_user_id();
        }

        $product_id = isset($booking_data['product_id']) ? (int) $booking_data['product_id'] : 0;
        $product = $product_id > 0 ? wc_get_product($product_id) : null;

        if (!$product) {
            foreach ($order->get_items() as $item) {
                $item_product = $item->get_product();
                if (!$item_product || $item_product->get_type() !== 'experience') {
                    continue;
                }

                $product = $item_product;
                $product_id = $item_product->get_id();

                if (empty($booking_data['product_id'])) {
                    $booking_data['product_id'] = $product_id;
                }

                if (empty($booking_data['experience_name'])) {
                    $booking_data['experience_name'] = $item_product->get_name();
                }

                break;
            }
        }

        if ($product) {
            if (empty($booking_data['experience_name'])) {
                $booking_data['experience_name'] = $product->get_name();
            }

            if (empty($booking_data['experience_url']) && method_exists($product, 'get_permalink')) {
                $booking_data['experience_url'] = $product->get_permalink();
            }
        }

        if (empty($booking_data['participants'])) {
            $participants = (int) ($booking_data['adults'] ?? 0) + (int) ($booking_data['children'] ?? 0);
            if ($participants <= 0) {
                $participants = (int) ($booking_data['participants'] ?? 0);
            }

            $booking_data['participants'] = max(1, $participants);
        }

        if (!empty($booking_data['meeting_point_id']) && empty($booking_data['meeting_point'])) {
            $meeting_point = MeetingPointManager::getMeetingPoint((int) $booking_data['meeting_point_id']);
            if ($meeting_point) {
                $booking_data['meeting_point'] = $meeting_point->name;

                if (!empty($meeting_point->address)) {
                    $booking_data['meeting_point_address'] = $meeting_point->address;
                }

                if (!empty($meeting_point->note)) {
                    $booking_data['meeting_point_note'] = $meeting_point->note;
                }
            }
        }

        return $booking_data;
    }

    /**
     * Parse booking date and time into a timestamp.
     *
     * @param string|null $date Booking date.
     * @param string|null $time Booking time.
     * @return int|false
     */
    private function parseBookingTimestamp(?string $date, ?string $time): int|false {
        if (empty($date)) {
            return false;
        }

        $date = trim((string) $date);
        $time_string = $time !== null && $time !== '' ? trim((string) $time) : '00:00:00';

        $timestamp = strtotime($date . ' ' . $time_string);

        if ($timestamp === false) {
            $timestamp = strtotime($date);
        }

        return $timestamp ?: false;
    }

    /**
     * Send abandoned cart email
     *
     * @param array $cart_data Cart data
     */
    private function sendAbandonedCartEmail(array $cart_data): void {
        if (empty($cart_data['email'])) {
            return;
        }

        $cart_contents = $cart_data['cart_contents'] ?? [];
        $experience_names = [];

        foreach ($cart_contents as $cart_item) {
            if ($cart_item['data'] && $cart_item['data']->get_type() === 'experience') {
                $experience_names[] = $cart_item['data']->get_name();
            }
        }

        $template_data = [
            'customer_email' => $cart_data['email'],
            'experience_names' => implode(', ', $experience_names),
            'cart_total' => $cart_data['cart_total'] ?? 0,
            'recovery_link' => wc_get_cart_url() . '?recover_cart=1'
        ];

        $this->sendEmail(
            'abandoned_cart',
            $cart_data['email'],
            __('Complete your booking - Special offer inside!', 'fp-esperienze'),
            $template_data
        );
    }

    /**
     * Send upselling email
     *
     * @param object $customer Customer data
     * @return bool
     */
    private function sendUpsellingEmail(object $customer): bool {
        // Get recommended experiences based on previous purchases
        $recommended_experiences = $this->getRecommendedExperiences($customer->customer_id);

        $template_data = [
            'customer_email' => $customer->billing_email,
            'recommended_experiences' => $recommended_experiences,
            'discount_code' => $this->generateDiscountCode($customer->customer_id),
            'customer_name' => $customer->customer_name ?? ''
        ];

        return $this->sendEmail(
            'upselling',
            $customer->billing_email,
            __('Discover new amazing experiences!', 'fp-esperienze'),
            $template_data
        );
    }

    /**
     * Send email using appropriate system
     *
     * @param string $template Template name
     * @param string $to Recipient email
     * @param string $subject Email subject
     * @param array $template_data Template data
     * @return bool Success
     */
    private function sendEmail(string $template, string $to, string $subject, array $template_data): bool {
        if (empty($to) || !is_email($to)) {
            return false;
        }

        // Use Brevo if enabled and configured
        if ($this->shouldUseBrevo()) {
            return $this->sendBrevoEmail($template, $to, $subject, $template_data);
        } else {
            return $this->sendWordPressEmail($template, $to, $subject, $template_data);
        }
    }

    /**
     * Check if Brevo should be used
     *
     * @return bool
     */
    private function shouldUseBrevo(): bool {
        return $this->brevo_manager->isEnabled() && 
               !empty($this->settings['email_marketing_system']) &&
               $this->settings['email_marketing_system'] === 'brevo';
    }

    /**
     * Send email via Brevo
     *
     * @param string $template Template name
     * @param string $to Recipient email
     * @param string $subject Email subject
     * @param array $template_data Template data
     * @return bool Success
     */
    private function sendBrevoEmail(string $template, string $to, string $subject, array $template_data): bool {
        // Use Brevo transactional email API
        $brevo_template_id = $this->getBrevoTemplateId($template);
        
        if (!$brevo_template_id) {
            // Fallback to WordPress email
            return $this->sendWordPressEmail($template, $to, $subject, $template_data);
        }

        $url = 'https://api.brevo.com/v3/smtp/email';

        $body = [
            'sender' => [
                'email' => get_option('admin_email'),
                'name' => get_bloginfo('name')
            ],
            'to' => [
                ['email' => $to]
            ],
            'templateId' => $brevo_template_id,
            'params' => $template_data
        ];

        $url = wp_http_validate_url($url);
        if (!$url || !str_starts_with($url, 'https://api.brevo.com/')) {
            $this->logError('Invalid Brevo API URL', ['url' => $url]);
            return false;
        }

        $response = wp_safe_remote_post($url, [
            'headers' => [
                'api-key' => $this->settings['brevo_api_key'] ?? '',
                'Content-Type' => 'application/json'
            ],
            'body' => wp_json_encode($body),
            'timeout' => 30
        ]);

        if (is_wp_error($response)) {
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        return $response_code === 201;
    }

    /**
     * Send email via WordPress
     *
     * @param string $template Template name
     * @param string $to Recipient email
     * @param string $subject Email subject
     * @param array $template_data Template data
     * @return bool Success
     */
    private function sendWordPressEmail(string $template, string $to, string $subject, array $template_data): bool {
        $message = $this->renderEmailTemplate($template, $template_data);
        
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>'
        ];

        return wp_mail($to, $subject, $message, $headers);
    }

    /**
     * Render email template
     *
     * @param string $template Template name
     * @param array $data Template data
     * @return string Rendered HTML
     */
    private function renderEmailTemplate(string $template, array $data): string {
        $template_path = FP_ESPERIENZE_PLUGIN_DIR . 'templates/emails/' . $template . '.php';
        
        if (!file_exists($template_path)) {
            return $this->getDefaultEmailTemplate($template, $data);
        }

        ob_start();
        extract($data, EXTR_SKIP);
        include $template_path;
        return ob_get_clean();
    }

    /**
     * Get default email template
     *
     * @param string $template Template name
     * @param array $data Template data
     * @return string HTML content
     */
    private function getDefaultEmailTemplate(string $template, array $data): string {
        $site_name = get_bloginfo('name');
        $site_url = home_url();
        
        $base_style = '
            <div style="max-width: 600px; margin: 0 auto; font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
                <div style="background: #f8f9fa; padding: 30px; text-align: center;">
                    <h1 style="color: #007cba; margin: 0;">' . esc_html($site_name) . '</h1>
                </div>
                <div style="padding: 30px; background: #ffffff;">
                    {{CONTENT}}
                </div>
                <div style="background: #f8f9fa; padding: 20px; text-align: center; font-size: 14px; color: #666;">
                    <p>© ' . date('Y') . ' ' . esc_html($site_name) . ' | <a href="' . esc_url($site_url) . '" style="color: #007cba;">Visit our website</a></p>
                </div>
            </div>
        ';

        $templates = [
            'booking_confirmation' => '
                <h2 style="color: #28a745; border-bottom: 2px solid #28a745; padding-bottom: 10px;">🎉 Booking Confirmed!</h2>
                <p>Dear <strong>' . esc_html($data['customer_name'] ?? 'Customer') . '</strong>,</p>
                <p>Great news! Your booking has been confirmed. Here are the details:</p>
                <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <tr><td style="padding: 8px; font-weight: bold;">Experience:</td><td style="padding: 8px;">' . esc_html($data['experience_name'] ?? '') . '</td></tr>
                        <tr><td style="padding: 8px; font-weight: bold;">Date & Time:</td><td style="padding: 8px;">' . esc_html($data['booking_date'] ?? '') . '</td></tr>
                        <tr><td style="padding: 8px; font-weight: bold;">Meeting Point:</td><td style="padding: 8px;">' . esc_html($data['meeting_point'] ?? '') . '</td></tr>
                        <tr><td style="padding: 8px; font-weight: bold;">Participants:</td><td style="padding: 8px;">' . intval($data['participants'] ?? 1) . '</td></tr>
                        <tr><td style="padding: 8px; font-weight: bold;">Total Amount:</td><td style="padding: 8px; color: #28a745; font-weight: bold;">€' . number_format(floatval($data['total_amount'] ?? 0), 2) . '</td></tr>
                    </table>
                </div>
                <p><strong>Important:</strong> Please arrive 15 minutes before the scheduled time.</p>
                <div style="text-align: center; margin: 30px 0;">
                    <a href="' . esc_url($data['booking_details_url'] ?? '') . '" style="background: #007cba; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;">View Booking Details</a>
                </div>
                <p>We can\'t wait to see you there! 🌟</p>
            ',
            'booking_completion' => '
                <h2 style="color: #ffc107; border-bottom: 2px solid #ffc107; padding-bottom: 10px;">⭐ Thank You!</h2>
                <p>Dear <strong>' . esc_html($data['customer_name'] ?? 'Customer') . '</strong>,</p>
                <p>Thank you for choosing us for your <strong>' . esc_html($data['experience_name'] ?? 'experience') . '</strong>!</p>
                <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 8px; text-align: center; margin: 20px 0;">
                    <h3 style="margin: 0; color: white;">We hope you had an amazing time! 🎊</h3>
                </div>
                <p>Your memories from today will last a lifetime. Thank you for being part of our story!</p>
                <div style="text-align: center; margin: 30px 0;">
                    <a href="' . esc_url($data['review_link'] ?? '') . '" style="background: #ffc107; color: #333; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;">Share Your Experience</a>
                </div>
            ',
            'pre_experience_reminder' => '
                <h2 style="color: #007cba; border-bottom: 2px solid #007cba; padding-bottom: 10px;">⏳ ' . esc_html__('Your experience is almost here!', 'fp-esperienze') . '</h2>
                <p>Dear <strong>' . esc_html($data['customer_name'] ?? 'Customer') . '</strong>,</p>
                <p>' . sprintf(
                    /* translators: %s: experience name */
                    esc_html__('We are excited to welcome you to %s. Here are the details for your upcoming adventure:', 'fp-esperienze'),
                    '<strong>' . esc_html($data['experience_name'] ?? __('your upcoming experience', 'fp-esperienze')) . '</strong>'
                ) . '</p>
                <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <tr><td style="padding: 8px; font-weight: bold;">' . esc_html__('Date', 'fp-esperienze') . ':</td><td style="padding: 8px;">' . esc_html($data['booking_date_formatted'] ?? ($data['booking_date'] ?? '')) . '</td></tr>
                        <tr><td style="padding: 8px; font-weight: bold;">' . esc_html__('Time', 'fp-esperienze') . ':</td><td style="padding: 8px;">' . esc_html($data['booking_time_formatted'] ?? ($data['booking_time'] ?? '')) . '</td></tr>
                        <tr><td style="padding: 8px; font-weight: bold;">' . esc_html__('Participants', 'fp-esperienze') . ':</td><td style="padding: 8px;">' . intval($data['participants'] ?? 1) . '</td></tr>
                        ' . (!empty($data['meeting_point']) ? '<tr><td style="padding: 8px; font-weight: bold;">' . esc_html__('Meeting Point', 'fp-esperienze') . ':</td><td style="padding: 8px;">' . esc_html($data['meeting_point']) . (!empty($data['meeting_point_address']) ? '<br><span style="color:#555;">' . nl2br(esc_html($data['meeting_point_address'])) . '</span>' : '') . '</td></tr>' : '') . '
                    </table>
                </div>
                ' . (!empty($data['meeting_point_note']) ? '<p style="background: #fff3cd; padding: 15px; border-radius: 6px; border: 1px solid #ffeeba; color: #856404;">' . esc_html($data['meeting_point_note']) . '</p>' : '') . '
                <p style="margin: 20px 0;">' . esc_html__('Please arrive at least 10 minutes early so we can start on time.', 'fp-esperienze') . '</p>
                ' . (!empty($data['booking_details_url']) ? '<div style="text-align: center; margin: 30px 0;"><a href="' . esc_url($data['booking_details_url']) . '" style="background: #007cba; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;">' . esc_html__('View Booking Details', 'fp-esperienze') . '</a></div>' : '') . '
                ' . (!empty($data['experience_url']) ? '<p style="text-align: center;"><a href="' . esc_url($data['experience_url']) . '" style="color: #007cba;">' . esc_html__('View experience information', 'fp-esperienze') . '</a></p>' : '') . '
                <p>' . esc_html__('Need to make changes or have questions? Simply reply to this email and we will be happy to help.', 'fp-esperienze') . '</p>
            ',
            'abandoned_cart' => '
                <h2 style="color: #dc3545; border-bottom: 2px solid #dc3545; padding-bottom: 10px;">⏰ Don\'t Miss Out!</h2>
                <p>You left some amazing experiences in your cart...</p>
                <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;">
                    <h3 style="margin-top: 0; color: #333;">📋 Your Selected Experiences:</h3>
                    <p><strong>' . esc_html($data['experience_names'] ?? '') . '</strong></p>
                    <p style="font-size: 18px; color: #dc3545;"><strong>Total: €' . number_format(floatval($data['cart_total'] ?? 0), 2) . '</strong></p>
                </div>
                <p><strong>Hurry!</strong> These popular experiences fill up fast. Complete your booking now to secure your spot!</p>
                <div style="text-align: center; margin: 30px 0;">
                    <a href="' . esc_url($data['recovery_link'] ?? '') . '" style="background: #dc3545; color: white; padding: 15px 40px; text-decoration: none; border-radius: 5px; display: inline-block; font-size: 16px;">Complete Your Booking Now 🚀</a>
                </div>
            ',
            'review_request' => '
                <h2 style="color: #17a2b8; border-bottom: 2px solid #17a2b8; padding-bottom: 10px;">💭 How was your experience?</h2>
                <p>Dear <strong>' . esc_html($data['customer_name'] ?? 'Customer') . '</strong>,</p>
                <p>We hope you absolutely loved your <strong>' . esc_html($data['experience_name'] ?? 'experience') . '</strong>!</p>
                <div style="background: #e1f5fe; padding: 20px; border-radius: 8px; margin: 20px 0; text-align: center;">
                    <p style="font-size: 18px; margin: 0;">⭐⭐⭐⭐⭐</p>
                    <p style="margin: 10px 0 0 0;">Your feedback helps us improve and helps other travelers discover amazing experiences!</p>
                </div>
                <div style="text-align: center; margin: 30px 0;">
                    <a href="' . esc_url($data['review_link'] ?? '') . '" style="background: #17a2b8; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;">Leave a Review 📝</a>
                </div>
                <p><em>It only takes 2 minutes and means the world to us!</em></p>
            ',
            'upselling' => '
                <h2 style="color: #6f42c1; border-bottom: 2px solid #6f42c1; padding-bottom: 10px;">🌟 Discover New Adventures!</h2>
                <p>Based on your previous bookings, we\'ve handpicked some experiences you\'ll absolutely love:</p>
                <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;">
                    ' . $this->renderRecommendedExperiences($data['recommended_experiences'] ?? []) . '
                </div>
                <div style="background: #6f42c1; color: white; padding: 15px; border-radius: 8px; text-align: center; margin: 20px 0;">
                    <p style="margin: 0; font-size: 18px;">🎁 Special Offer: Use code <strong style="background: rgba(255,255,255,0.2); padding: 5px 10px; border-radius: 3px;">' . esc_html($data['discount_code'] ?? '') . '</strong> for 10% off!</p>
                </div>
                <div style="text-align: center; margin: 30px 0;">
                    <a href="' . esc_url($site_url) . '" style="background: #6f42c1; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;">Browse Experiences 🗺️</a>
                </div>
            '
        ];

        $content = $templates[$template] ?? '<p>Email content not available.</p>';
        return str_replace('{{CONTENT}}', $content, $base_style);
    }

    /**
     * Test email system
     *
     * @param string $test_email Test email address
     * @param string $system_type System type (brevo, wordpress, auto)
     * @return array Test result
     */
    private function testEmailSystem(string $test_email, string $system_type): array {
        $test_data = [
            'customer_name' => 'Test User',
            'experience_name' => 'Test Experience',
            'booking_date' => date('Y-m-d H:i'),
            'meeting_point' => 'Test Location'
        ];

        $success = false;
        $message = '';
        $system_used = '';

        if ($system_type === 'brevo' || ($system_type === 'auto' && $this->shouldUseBrevo())) {
            $success = $this->sendBrevoEmail('booking_confirmation', $test_email, 'Test Email', $test_data);
            $system_used = 'Brevo';
            $message = $success ? 'Test email sent successfully via Brevo' : 'Failed to send test email via Brevo';
        } else {
            $success = $this->sendWordPressEmail('booking_confirmation', $test_email, 'Test Email', $test_data);
            $system_used = 'WordPress';
            $message = $success ? 'Test email sent successfully via WordPress' : 'Failed to send test email via WordPress';
        }

        return [
            'success' => $success,
            'message' => $message,
            'system_used' => $system_used
        ];
    }

    /**
     * Send email campaign
     *
     * @param array $campaign_data Campaign data
     * @return array Result
     */
    private function sendCampaign(array $campaign_data): array {
        $recipients = $this->getCampaignRecipients($campaign_data['recipient_type']);
        $sent_count = 0;
        $failed_count = 0;

        foreach ($recipients as $recipient) {
            $template_data = [
                'customer_name' => $recipient['name'] ?? '',
                'custom_content' => $campaign_data['custom_content']
            ];

            $success = $this->sendEmail(
                $campaign_data['template'],
                $recipient['email'],
                $campaign_data['subject'],
                $template_data
            );

            if ($success) {
                $sent_count++;
            } else {
                $failed_count++;
            }

            // Add small delay to avoid rate limiting
            usleep(100000); // 0.1 seconds
        }

        return [
            'success' => $sent_count > 0,
            'message' => sprintf(
                __('Campaign sent: %d successful, %d failed', 'fp-esperienze'),
                $sent_count,
                $failed_count
            ),
            'sent_count' => $sent_count,
            'failed_count' => $failed_count
        ];
    }

    /**
     * Get email templates
     *
     * @return array Templates
     */
    private function getEmailTemplates(): array {
        return [
            'booking_confirmation' => [
                'name' => __('Booking Confirmation', 'fp-esperienze'),
                'description' => __('Sent when a booking is confirmed', 'fp-esperienze')
            ],
            'booking_completion' => [
                'name' => __('Booking Completion', 'fp-esperienze'),
                'description' => __('Sent when an experience is completed', 'fp-esperienze')
            ],
            'pre_experience_reminder' => [
                'name' => __('Experience Reminder', 'fp-esperienze'),
                'description' => __('Sent before the experience to remind customers about their booking', 'fp-esperienze')
            ],
            'abandoned_cart' => [
                'name' => __('Abandoned Cart Recovery', 'fp-esperienze'),
                'description' => __('Sent to recover abandoned carts', 'fp-esperienze')
            ],
            'review_request' => [
                'name' => __('Review Request', 'fp-esperienze'),
                'description' => __('Request for customer reviews', 'fp-esperienze')
            ],
            'upselling' => [
                'name' => __('Upselling Campaign', 'fp-esperienze'),
                'description' => __('Promote related experiences', 'fp-esperienze')
            ]
        ];
    }

    /**
     * Helper methods
     */

    private function getBrevoTemplateId(string $template): ?int {
        $template_mapping = [
            'booking_confirmation' => $this->settings['brevo_template_booking_confirmation'] ?? null,
            'booking_completion' => $this->settings['brevo_template_booking_completion'] ?? null,
            'pre_experience_reminder' => $this->settings['brevo_template_pre_experience'] ?? null,
            'abandoned_cart' => $this->settings['brevo_template_abandoned_cart'] ?? null,
            'review_request' => $this->settings['brevo_template_review_request'] ?? null,
            'upselling' => $this->settings['brevo_template_upselling'] ?? null
        ];

        return $template_mapping[$template] ? intval($template_mapping[$template]) : null;
    }

    private function generateReviewLink(int $product_id): string {
        return get_permalink($product_id) . '#reviews';
    }

    private function getRecommendedExperiences(int $customer_id): array {
        // Placeholder for AI-powered recommendations
        return [
            ['name' => 'Sunset Photography Tour', 'price' => 89],
            ['name' => 'Wine Tasting Experience', 'price' => 65],
            ['name' => 'Historic City Walk', 'price' => 25]
        ];
    }

    private function getCampaignRecipients(string $recipient_type): array {
        global $wpdb;

        switch ($recipient_type) {
            case 'all':
                return $wpdb->get_results("
                    SELECT DISTINCT billing_email as email, 
                           CONCAT(billing_first_name, ' ', billing_last_name) as name
                    FROM {$wpdb->prefix}wc_orders 
                    WHERE billing_email != ''
                ", ARRAY_A);

            case 'customers':
                return $wpdb->get_results("
                    SELECT DISTINCT o.billing_email as email,
                           CONCAT(o.billing_first_name, ' ', o.billing_last_name) as name
                    FROM {$wpdb->prefix}wc_orders o
                    INNER JOIN {$wpdb->prefix}wc_order_items oi ON o.id = oi.order_id
                    INNER JOIN {$wpdb->prefix}wc_order_itemmeta oim ON oi.order_item_id = oim.order_item_id
                    WHERE o.status IN ('wc-processing', 'wc-completed')
                    AND oim.meta_key = '_product_type'
                    AND oim.meta_value = 'experience'
                    AND o.billing_email != ''
                ", ARRAY_A);

            default:
                return [];
        }
    }

    /**
     * Generate discount code for customer
     *
     * @param int $customer_id Customer ID
     * @return string Discount code
     */
    private function generateDiscountCode(int $customer_id): string {
        $code = 'COMEBACK' . $customer_id . wp_rand(100, 999);
        
        // Create WooCommerce coupon
        $coupon = new \WC_Coupon();
        $coupon->set_code($code);
        $coupon->set_amount(10);
        $coupon->set_discount_type('percent');
        $coupon->set_individual_use(true);
        $coupon->set_usage_limit(1);
        $coupon->set_usage_limit_per_user(1);
        $coupon->set_date_expires(time() + (7 * DAY_IN_SECONDS)); // 7 days
        $coupon->save();
        
        return $code;
    }

    /**
     * Log error without exposing sensitive data
     *
     * @param string $message Error message
     * @param array $context Context data (no PII)
     */
    private function logError(string $message, array $context = []): void {
        unset($context['email'], $context['api_key'], $context['first_name'], $context['last_name']);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                'FP Esperienze - Email Marketing: %s %s',
                $message,
                !empty($context) ? wp_json_encode($context) : ''
            ));
        }
    }

    /**
     * Render recommended experiences HTML
     *
     * @param array $experiences Recommended experiences
     * @return string HTML
     */
    private function renderRecommendedExperiences(array $experiences): string {
        if (empty($experiences)) {
            return '<p>No recommendations available at this time.</p>';
        }
        
        $html = '<div style="margin: 20px 0;">';
        
        foreach ($experiences as $experience) {
            $html .= sprintf(
                '<div style="border: 1px solid #ddd; padding: 15px; margin: 10px 0; border-radius: 8px;">
                    <h4 style="margin: 0 0 10px 0; color: #333;">%s</h4>
                    <p style="margin: 5px 0;">%s</p>
                    <p style="margin: 10px 0 0 0; font-weight: bold; color: #6f42c1;">€%s</p>
                    <a href="%s" style="background: #6f42c1; color: white; padding: 8px 15px; text-decoration: none; border-radius: 4px; display: inline-block; margin-top: 10px;">View Details</a>
                </div>',
                esc_html($experience['name'] ?? 'Experience'),
                esc_html(wp_trim_words($experience['description'] ?? '', 20)),
                number_format(floatval($experience['price'] ?? 0), 2),
                esc_url($experience['url'] ?? '#')
            );
        }
        
        $html .= '</div>';
        
        return $html;
    }
}