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

use FP\Esperienze\Core\CapabilityManager;

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
        $session_id = WC()->session->get_customer_id();
        
        $cart_data = [
            'user_id' => $user_id,
            'session_id' => $session_id,
            'email' => $user_id ? get_user_meta($user_id, 'billing_email', true) : '',
            'cart_contents' => WC()->cart->get_cart_contents(),
            'cart_total' => WC()->cart->get_total('raw'),
            'last_activity' => current_time('mysql')
        ];

        update_option('fp_cart_activity_' . ($user_id ?: $session_id), $cart_data);
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
            WHERE option_name LIKE 'fp_cart_activity_%'
            AND option_value LIKE %s
        ", '%"last_activity"%'));

        foreach ($abandoned_carts as $cart_option) {
            $cart_data = maybe_unserialize($cart_option->option_value);
            
            if (!$cart_data || !is_array($cart_data)) {
                continue;
            }

            if ($cart_data['last_activity'] < $abandoned_time) {
                $this->sendAbandonedCartEmail($cart_data);
                
                // Remove from tracking after sending email
                delete_option($cart_option->option_name);
            }
        }
    }

    /**
     * Process upselling campaigns
     */
    public function processUpsellingCampaigns(): void {
        global $wpdb;

        // Find customers who completed experiences 7+ days ago
        $date_threshold = date('Y-m-d', strtotime('-7 days'));

        $potential_customers = $wpdb->get_results($wpdb->prepare("
            SELECT DISTINCT o.customer_id, o.billing_email
            FROM {$wpdb->prefix}wc_orders o
            INNER JOIN {$wpdb->prefix}wc_order_items oi ON o.id = oi.order_id
            INNER JOIN {$wpdb->prefix}wc_order_itemmeta oim ON oi.order_item_id = oim.order_item_id
            WHERE o.date_created < %s
            AND o.status IN ('wc-completed')
            AND oim.meta_key = '_product_type'
            AND oim.meta_value = 'experience'
            AND NOT EXISTS (
                SELECT 1 FROM {$wpdb->prefix}wc_orders o2
                WHERE o2.customer_id = o.customer_id
                AND o2.date_created > o.date_created
                AND o2.status IN ('wc-processing', 'wc-completed')
            )
        ", $date_threshold));

        foreach ($potential_customers as $customer) {
            $this->sendUpsellingEmail($customer);
        }
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
     * Test email system (AJAX handler)
     */
    public function ajaxTestEmailSystem(): void {
        if (!CapabilityManager::currentUserCan('manage_settings')) {
            wp_die('Unauthorized', 403);
        }

        check_ajax_referer('fp_esperienze_admin', 'nonce');

        $test_email = sanitize_email($_POST['test_email'] ?? '');
        $system_type = sanitize_text_field($_POST['system_type'] ?? 'auto');

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
            wp_die('Unauthorized', 403);
        }

        check_ajax_referer('fp_esperienze_admin', 'nonce');

        $campaign_data = [
            'template' => sanitize_text_field($_POST['template'] ?? ''),
            'subject' => sanitize_text_field($_POST['subject'] ?? ''),
            'recipient_type' => sanitize_text_field($_POST['recipient_type'] ?? 'all'),
            'custom_content' => wp_kses_post($_POST['custom_content'] ?? '')
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
            wp_die('Unauthorized', 403);
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
            wp_schedule_single_event($send_time, 'fp_send_pre_experience_email', [$booking_id, $booking_data]);
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
        
        wp_schedule_single_event($send_time, 'fp_send_review_request', [$booking_id, $booking_data]);
    }

    /**
     * Schedule upselling email
     *
     * @param int $booking_id Booking ID
     * @param array $booking_data Booking data
     */
    private function scheduleUpsellingEmail(int $booking_id, array $booking_data): void {
        $send_time = time() + (7 * DAY_IN_SECONDS); // 7 days after completion
        
        wp_schedule_single_event($send_time, 'fp_send_upselling_email', [$booking_id, $booking_data]);
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
     */
    private function sendUpsellingEmail(object $customer): void {
        // Get recommended experiences based on previous purchases
        $recommended_experiences = $this->getRecommendedExperiences($customer->customer_id);

        $template_data = [
            'customer_email' => $customer->billing_email,
            'recommended_experiences' => $recommended_experiences,
            'discount_code' => $this->generateDiscountCode($customer->customer_id)
        ];

        $this->sendEmail(
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

        $response = wp_remote_post($url, [
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
        extract($data);
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