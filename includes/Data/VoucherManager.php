<?php
/**
 * Voucher Manager
 *
 * @package FP\Esperienze\Data
 */

namespace FP\Esperienze\Data;

use Exception;
use FP\Esperienze\PDF\Voucher_Pdf;
use FP\Esperienze\PDF\Qr;

defined('ABSPATH') || exit;

/**
 * Voucher management class
 */
class VoucherManager {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('woocommerce_order_status_completed', [$this, 'processOrderVouchers'], 10, 1);
        add_action('fp_esperienze_send_gift_voucher', [$this, 'sendScheduledVoucher'], 10, 1);
    }
    
    /**
     * Process order for gift voucher generation
     *
     * @param int $order_id Order ID
     */
    public function processOrderVouchers($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        
        foreach ($order->get_items() as $item_id => $item) {
            $product = $item->get_product();
            if (!$product || $product->get_type() !== 'experience') {
                continue;
            }
            
            // Check if this is a gift purchase
            $is_gift = $item->get_meta('Gift Purchase');
            if ($is_gift !== __('Yes', 'fp-esperienze')) {
                continue;
            }
            
            // Generate voucher
            $this->generateVoucher($order, $item, $item_id);
        }
    }
    
    /**
     * Generate voucher for gift purchase
     *
     * @param \WC_Order $order Order object
     * @param \WC_Order_Item_Product $item Order item
     * @param int $item_id Item ID
     */
    private function generateVoucher($order, $item, $item_id) {
        global $wpdb;
        
        $product = $item->get_product();
        $table_name = $wpdb->prefix . 'fp_exp_vouchers';
        
        // Generate unique voucher code
        $voucher_code = $this->generateVoucherCode();
        
        // Determine amount type and value
        $amount_type = 'full'; // Default to full experience
        $amount = $item->get_total();
        
        // Check if product has custom gift value meta
        $gift_value_meta = get_post_meta($product->get_id(), '_fp_exp_gift_value', true);
        if (!empty($gift_value_meta)) {
            $amount_type = 'value';
            $amount = floatval($gift_value_meta);
        }
        
        // Calculate expiration date
        $exp_months = get_option('fp_esperienze_gift_default_exp_months', 12);
        $expires_on = date('Y-m-d', strtotime('+' . $exp_months . ' months'));
        
        // Get gift data from order item meta
        $recipient_name = $item->get_meta('Recipient Name');
        $recipient_email = $item->get_meta('Recipient Email');
        $sender_name = $item->get_meta('Sender Name');
        $gift_message = $item->get_meta('Gift Message');
        $send_date = $item->get_meta('Send Date');
        
        // Insert voucher into database
        $voucher_data = [
            'code' => $voucher_code,
            'product_id' => $product->get_id(),
            'amount_type' => $amount_type,
            'amount' => $amount,
            'recipient_name' => $recipient_name,
            'recipient_email' => $recipient_email,
            'message' => $gift_message,
            'expires_on' => $expires_on,
            'status' => 'active',
            'order_id' => $order->get_id(),
            'order_item_id' => $item_id,
            'sender_name' => $sender_name,
            'send_date' => ($send_date === 'immediate') ? null : $send_date,
            'sent_at' => null,
            'created_at' => current_time('mysql'),
        ];
        
        $result = $wpdb->insert($table_name, $voucher_data);
        
        if ($result === false) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('FP Esperienze: Failed to create voucher for order ' . $order->get_id());
            }
            return;
        }
        
        $voucher_id = $wpdb->insert_id;
        $voucher_data['id'] = $voucher_id;
        
        // Generate PDF
        try {
            $pdf_path = Voucher_Pdf::generate($voucher_data);
            
            // Update voucher with PDF path
            $wpdb->update(
                $table_name,
                ['pdf_path' => $pdf_path],
                ['id' => $voucher_id]
            );
            
            // Schedule or send email
            if ($send_date === 'immediate' || empty($send_date)) {
                $this->sendVoucherEmail($voucher_data, $pdf_path, $order);
            } else {
                // Schedule email for future date
                $send_timestamp = strtotime($send_date . ' 09:00:00');
                if (!wp_next_scheduled('fp_esperienze_send_gift_voucher', [$voucher_id])) {
                    wp_schedule_single_event($send_timestamp, 'fp_esperienze_send_gift_voucher', [$voucher_id]);
                }
            }
            
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('FP Esperienze: Failed to generate PDF for voucher ' . $voucher_code . ': ' . $e->getMessage());
            }
        }
    }
    
    /**
     * Generate unique voucher code using cryptographically secure random_bytes
     *
     * @return string Voucher code
     */
    private function generateVoucherCode() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'fp_exp_vouchers';
        
        do {
            // Generate 12-character code using random_bytes for better security
            $bytes = random_bytes(9); // 9 bytes = 12 base32 chars
            $code = strtoupper(substr(str_replace(['=', '/', '+'], ['', '', ''], base64_encode($bytes)), 0, 12));
            
            // Remove confusing characters for better readability
            $code = str_replace(['0', 'O', 'I', '1'], ['9', 'P', 'J', '2'], $code);
            
            // Check uniqueness
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name WHERE code = %s",
                $code
            ));
        } while ($exists > 0);
        
        return $code;
    }
    
    /**
     * Send voucher email
     *
     * @param array $voucher_data Voucher data
     * @param string $pdf_path PDF file path
     * @param \WC_Order $order Order object
     * @return bool True if both emails sent successfully
     */
    private function sendVoucherEmail($voucher_data, $pdf_path, $order): bool {
        $sender_name = get_option('fp_esperienze_gift_email_sender_name', get_bloginfo('name'));
        $sender_email = get_option('fp_esperienze_gift_email_sender_email', get_option('admin_email'));
        
        // Email to recipient
        $recipient_subject = sprintf(
            __('You have received a gift voucher from %s', 'fp-esperienze'),
            $sender_name
        );
        
        $recipient_message = $this->buildRecipientEmailContent($voucher_data);
        
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $sender_name . ' <' . $sender_email . '>'
        ];
        
        // Validate email addresses before sending
        if (!is_email($voucher_data['recipient_email'])) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("FP Esperienze: Invalid recipient email: " . $voucher_data['recipient_email']);
            }
            return false;
        }
        
        if (!is_email($order->get_billing_email())) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("FP Esperienze: Invalid buyer email: " . $order->get_billing_email());
            }
            return false;
        }
        
        $attachments = [$pdf_path];
        
        // Send email to recipient with error handling
        $recipient_sent = wp_mail(
            $voucher_data['recipient_email'],
            $recipient_subject,
            $recipient_message,
            $headers,
            $attachments
        );
        
        if (!$recipient_sent && defined('WP_DEBUG') && WP_DEBUG) {
            error_log("FP Esperienze: Failed to send voucher email to recipient: " . $voucher_data['recipient_email']);
        }
        
        // Email to buyer (order customer)
        $buyer_subject = sprintf(
            __('Gift voucher sent: %s', 'fp-esperienze'),
            $voucher_data['code']
        );
        
        $buyer_message = $this->buildBuyerEmailContent($voucher_data, $order);
        
        $buyer_sent = wp_mail(
            $order->get_billing_email(),
            $buyer_subject,
            $buyer_message,
            $headers,
            $attachments
        );
        
        if (!$buyer_sent && defined('WP_DEBUG') && WP_DEBUG) {
            error_log("FP Esperienze: Failed to send voucher confirmation email to buyer: " . $order->get_billing_email());
        }
        
        // Update sent timestamp only if emails were successful
        if ($recipient_sent && $buyer_sent) {
            global $wpdb;
            $wpdb->update(
                $wpdb->prefix . 'fp_exp_vouchers',
                ['sent_at' => current_time('mysql')],
                ['id' => $voucher_data['id']]
            );
        }
        
        return $recipient_sent && $buyer_sent;
    }
    
    /**
     * Build recipient email content
     *
     * @param array $voucher_data Voucher data
     * @return string Email content
     */
    private function buildRecipientEmailContent($voucher_data) {
        $site_name = get_bloginfo('name');
        $product = wc_get_product($voucher_data['product_id']);
        $product_name = $product ? $product->get_name() : __('Experience', 'fp-esperienze');
        
        // Get branding settings for consistent colors
        $branding_settings = get_option('fp_esperienze_branding', []);
        $primary_color = $branding_settings['primary_color'] ?? '#ff6b35';
        
        $message = '<html><body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">';
        $message .= '<div style="max-width: 600px; margin: 0 auto; padding: 20px;">';
        
        $message .= '<h1 style="color: ' . esc_attr($primary_color) . '; text-align: center;">' . esc_html__('You have received a gift voucher!', 'fp-esperienze') . '</h1>';
        
        $message .= '<p>' . sprintf(
            esc_html__('Hi %s,', 'fp-esperienze'),
            '<strong>' . esc_html($voucher_data['recipient_name']) . '</strong>'
        ) . '</p>';
        
        if (!empty($voucher_data['sender_name'])) {
            $message .= '<p>' . sprintf(
                esc_html__('%s has sent you a gift voucher for an amazing experience!', 'fp-esperienze'),
                '<strong>' . esc_html($voucher_data['sender_name']) . '</strong>'
            ) . '</p>';
        } else {
            $message .= '<p>' . esc_html__('You have received a gift voucher for an amazing experience!', 'fp-esperienze') . '</p>';
        }
        
        if (!empty($voucher_data['message'])) {
            $message .= '<div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid ' . esc_attr($primary_color) . ';">';
            $message .= '<h3 style="margin-top: 0; color: ' . esc_attr($primary_color) . ';">' . esc_html__('Personal Message:', 'fp-esperienze') . '</h3>';
            $message .= '<p style="margin-bottom: 0; font-style: italic;">' . nl2br(esc_html($voucher_data['message'])) . '</p>';
            $message .= '</div>';
        }
        
        $message .= '<div style="background: white; border: 2px solid ' . esc_attr($primary_color) . '; border-radius: 8px; padding: 20px; text-align: center; margin: 20px 0;">';
        $message .= '<h2 style="color: ' . esc_attr($primary_color) . '; margin: 0 0 10px 0;">' . esc_html($product_name) . '</h2>';
        $message .= '<p style="font-size: 24px; font-weight: bold; background: ' . esc_attr($primary_color) . '; color: white; padding: 10px; border-radius: 4px; letter-spacing: 2px; margin: 10px 0;">' . esc_html($voucher_data['code']) . '</p>';
        $message .= '<p style="margin: 0; color: #666;">' . sprintf(
            esc_html__('Valid until: %s', 'fp-esperienze'),
            date_i18n(get_option('date_format'), strtotime($voucher_data['expires_on']))
        ) . '</p>';
        $message .= '</div>';
        
        $message .= '<p>' . sprintf(
            esc_html__('To redeem your voucher, visit our website and use the code above during checkout. You can also present the attached PDF with the QR code.', 'fp-esperienze')
        ) . '</p>';
        
        $message .= '<p style="text-align: center; margin: 30px 0;">';
        $message .= '<a href="' . esc_url(home_url()) . '" style="background: ' . esc_attr($primary_color) . '; color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; font-weight: bold;">' . 
                    esc_html__('Book Your Experience', 'fp-esperienze') . '</a>';
        $message .= '</p>';
        
        $message .= '<p style="font-size: 12px; color: #666; text-align: center; margin-top: 40px; border-top: 1px solid #eee; padding-top: 20px;">';
        $message .= sprintf(esc_html__('This email was sent by %s', 'fp-esperienze'), esc_html($site_name));
        $message .= '</p>';
        
        $message .= '</div></body></html>';
        
        return $message;
    }
    
    /**
     * Build buyer email content
     *
     * @param array $voucher_data Voucher data
     * @param \WC_Order $order Order object
     * @return string Email content
     */
    private function buildBuyerEmailContent($voucher_data, $order) {
        $site_name = get_bloginfo('name');
        $product = wc_get_product($voucher_data['product_id']);
        $product_name = $product ? $product->get_name() : __('Experience', 'fp-esperienze');
        
        // Get branding settings for consistent colors
        $branding_settings = get_option('fp_esperienze_branding', []);
        $primary_color = $branding_settings['primary_color'] ?? '#ff6b35';
        
        $message = '<html><body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">';
        $message .= '<div style="max-width: 600px; margin: 0 auto; padding: 20px;">';
        
        $message .= '<h1 style="color: ' . esc_attr($primary_color) . '; text-align: center;">' . esc_html__('Gift voucher confirmation', 'fp-esperienze') . '</h1>';
        
        $message .= '<p>' . sprintf(
            esc_html__('Hi %s,', 'fp-esperienze'),
            '<strong>' . esc_html($order->get_billing_first_name()) . '</strong>'
        ) . '</p>';
        
        $message .= '<p>' . esc_html__('Your gift voucher has been successfully created and sent!', 'fp-esperienze') . '</p>';
        
        $message .= '<div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;">';
        $message .= '<h3 style="margin-top: 0; color: ' . esc_attr($primary_color) . ';">' . esc_html__('Gift Details:', 'fp-esperienze') . '</h3>';
        $message .= '<p><strong>' . esc_html__('Recipient:', 'fp-esperienze') . '</strong> ' . esc_html($voucher_data['recipient_name']) . ' (' . esc_html($voucher_data['recipient_email']) . ')</p>';
        $message .= '<p><strong>' . esc_html__('Experience:', 'fp-esperienze') . '</strong> ' . esc_html($product_name) . '</p>';
        $message .= '<p><strong>' . esc_html__('Voucher Code:', 'fp-esperienze') . '</strong> ' . esc_html($voucher_data['code']) . '</p>';
        $message .= '<p><strong>' . esc_html__('Valid Until:', 'fp-esperienze') . '</strong> ' . date_i18n(get_option('date_format'), strtotime($voucher_data['expires_on'])) . '</p>';
        $message .= '</div>';
        
        $message .= '<p>' . esc_html__('A copy of the voucher PDF is attached to this email for your records.', 'fp-esperienze') . '</p>';
        
        $message .= '<p style="font-size: 12px; color: #666; text-align: center; margin-top: 40px; border-top: 1px solid #eee; padding-top: 20px;">';
        $message .= sprintf(esc_html__('This email was sent by %s', 'fp-esperienze'), esc_html($site_name));
        $message .= '</p>';
        
        $message .= '</div></body></html>';
        
        return $message;
    }
    
    /**
     * Create a voucher manually (public API)
     *
     * @param array $voucher_data {
     *     Voucher data array
     *     @type int    $product_id      Product ID
     *     @type string $amount_type     'full' or 'value'
     *     @type float  $amount          Amount value
     *     @type string $recipient_name  Recipient name
     *     @type string $recipient_email Recipient email
     *     @type string $message         Optional message
     *     @type string $expires_on      Expiration date (Y-m-d format)
     * }
     * @return array Result with success status, voucher ID, and code
     */
    public static function createVoucher(array $voucher_data): array {
        global $wpdb;
        
        $result = [
            'success' => false,
            'voucher_id' => 0,
            'voucher_code' => '',
            'message' => ''
        ];
        
        // Validate required fields
        $required_fields = ['product_id', 'recipient_name', 'recipient_email', 'expires_on'];
        foreach ($required_fields as $field) {
            if (empty($voucher_data[$field])) {
                $result['message'] = sprintf(__('Missing required field: %s', 'fp-esperienze'), $field);
                return $result;
            }
        }
        
        // Validate product exists and is experience type
        $product = wc_get_product($voucher_data['product_id']);
        if (!$product || $product->get_type() !== 'experience') {
            $result['message'] = __('Invalid product ID or product is not an experience.', 'fp-esperienze');
            return $result;
        }
        
        // Validate email
        if (!is_email($voucher_data['recipient_email'])) {
            $result['message'] = __('Invalid recipient email address.', 'fp-esperienze');
            return $result;
        }
        
        // Validate expiration date
        $expires_on = $voucher_data['expires_on'];
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $expires_on) || strtotime($expires_on) === false) {
            $result['message'] = __('Invalid expiration date format. Use Y-m-d format.', 'fp-esperienze');
            return $result;
        }
        
        // Set defaults
        $amount_type = $voucher_data['amount_type'] ?? 'full';
        $amount = $voucher_data['amount'] ?? ($amount_type === 'full' ? $product->get_price() : 0);
        $message = $voucher_data['message'] ?? '';
        
        // Generate unique voucher code
        $instance = new self();
        $voucher_code = $instance->generateVoucherCode();
        
        // Insert voucher into database
        $table_name = $wpdb->prefix . 'fp_exp_vouchers';
        $inserted = $wpdb->insert(
            $table_name,
            [
                'code' => $voucher_code,
                'product_id' => (int) $voucher_data['product_id'],
                'amount_type' => $amount_type,
                'amount' => (float) $amount,
                'recipient_name' => sanitize_text_field($voucher_data['recipient_name']),
                'recipient_email' => sanitize_email($voucher_data['recipient_email']),
                'message' => sanitize_textarea_field($message),
                'expires_on' => $expires_on,
                'status' => 'active',
                'created_at' => current_time('mysql'),
                'order_id' => 0 // Manual voucher, no associated order
            ],
            [
                '%s', // code
                '%d', // product_id
                '%s', // amount_type
                '%f', // amount
                '%s', // recipient_name
                '%s', // recipient_email
                '%s', // message
                '%s', // expires_on
                '%s', // status
                '%s', // created_at
                '%d'  // order_id
            ]
        );
        
        if ($inserted === false) {
            $result['message'] = __('Failed to create voucher in database.', 'fp-esperienze');
            return $result;
        }
        
        $voucher_id = $wpdb->insert_id;
        
        $result['success'] = true;
        $result['voucher_id'] = $voucher_id;
        $result['voucher_code'] = $voucher_code;
        $result['message'] = __('Voucher created successfully.', 'fp-esperienze');
        
        // Log the creation
        do_action('fp_esperienze_voucher_created', $voucher_id, $voucher_data);
        
        return $result;
    }

    /**
     * Send scheduled voucher
     *
     * @param int $voucher_id Voucher ID
     */
    public function sendScheduledVoucher($voucher_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'fp_exp_vouchers';
        
        $voucher = $wpdb->get_row($wpdb->prepare(
            "SELECT id, code, product_id, amount_type, amount, recipient_name, recipient_email, sender_name, message, pdf_path, expires_on, status, order_id, order_item_id, send_date, sent_at, created_at FROM $table_name WHERE id = %d",
            $voucher_id
        ), ARRAY_A);
        
        if (!$voucher) {
            return;
        }
        
        $order = wc_get_order($voucher['order_id']);
        if (!$order) {
            return;
        }
        
        $this->sendVoucherEmail($voucher, $voucher['pdf_path'], $order);
    }
    
    /**
     * Get voucher by code
     *
     * @param string $code Voucher code
     * @return array|null Voucher data
     */
    public static function getVoucherByCode($code) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'fp_exp_vouchers';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT id, code, product_id, amount_type, amount, recipient_name, recipient_email, sender_name, message, pdf_path, expires_on, status, order_id, order_item_id, send_date, sent_at, created_at FROM $table_name WHERE code = %s",
            $code
        ), ARRAY_A);
    }
    
    /**
     * Get voucher by ID
     *
     * @param int $id Voucher ID
     * @return array|null Voucher data
     */
    public static function getVoucherById($id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'fp_exp_vouchers';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT id, code, product_id, amount_type, amount, recipient_name, recipient_email, sender_name, message, pdf_path, expires_on, status, order_id, order_item_id, send_date, sent_at, created_at FROM $table_name WHERE id = %d",
            $id
        ), ARRAY_A);
    }
    
    /**
     * Redeem voucher
     *
     * @param string $code Voucher code
     * @return bool Success status
     */
    public static function redeemVoucher($code) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'fp_exp_vouchers';
        
        $voucher = self::getVoucherByCode($code);
        if (!$voucher || $voucher['status'] !== 'active') {
            return false;
        }
        
        // Check expiration
        if (strtotime($voucher['expires_on']) < time()) {
            $wpdb->update(
                $table_name,
                ['status' => 'expired'],
                ['id' => $voucher['id']]
            );
            return false;
        }
        
        // Mark as redeemed
        $result = $wpdb->update(
            $table_name,
            ['status' => 'redeemed'],
            ['id' => $voucher['id']]
        );
        
        return $result !== false;
    }
    
    /**
     * Validate voucher for redemption
     *
     * @param string $code Voucher code
     * @param int $product_id Product ID to validate against
     * @param string $payload Optional QR code payload for HMAC validation
     * @return array Validation result with success status and voucher data
     */
    public static function validateVoucherForRedemption(string $code, ?int $product_id = null, ?string $payload = null) {
        $result = [
            'success' => false,
            'message' => '',
            'voucher' => null,
            'discount_amount' => 0,
            'discount_type' => 'none'
        ];
        
        // Get voucher by code
        $voucher = self::getVoucherByCode($code);
        if (!$voucher) {
            $result['message'] = __('Invalid voucher code.', 'fp-esperienze');
            return $result;
        }
        
        // Check voucher status
        if ($voucher['status'] !== 'active') {
            switch ($voucher['status']) {
                case 'redeemed':
                    $result['message'] = __('This voucher has already been used.', 'fp-esperienze');
                    break;
                case 'expired':
                    $result['message'] = __('This voucher has expired.', 'fp-esperienze');
                    break;
                case 'void':
                    $result['message'] = __('This voucher has been voided.', 'fp-esperienze');
                    break;
                default:
                    $result['message'] = __('This voucher is not valid.', 'fp-esperienze');
            }
            return $result;
        }
        
        // Check expiration
        if (strtotime($voucher['expires_on']) < time()) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'fp_exp_vouchers';
            $wpdb->update(
                $table_name,
                ['status' => 'expired'],
                ['id' => $voucher['id']]
            );
            $result['message'] = __('This voucher has expired.', 'fp-esperienze');
            return $result;
        }
        
        // Validate HMAC signature if payload provided
        if ($payload) {
            $verification = Qr::verifyPayload($payload);
            if (!$verification) {
                $result['message'] = __('Invalid voucher signature.', 'fp-esperienze');
                return $result;
            }
            
            // Verify code matches
            if ($verification['VC'] !== $code) {
                $result['message'] = __('Voucher code mismatch.', 'fp-esperienze');
                return $result;
            }
        }
        
        // Check product compatibility
        if ($product_id && $voucher['product_id'] && $voucher['product_id'] != $product_id) {
            $product = wc_get_product($voucher['product_id']);
            $product_name = $product ? $product->get_name() : __('Unknown Product', 'fp-esperienze');
            $result['message'] = sprintf(
                __('This voucher is only valid for "%s".', 'fp-esperienze'),
                $product_name
            );
            return $result;
        }
        
        // Determine discount type and amount
        $discount_type = $voucher['amount_type'] === 'full' ? 'percentage' : 'fixed_cart';
        $discount_amount = $voucher['amount_type'] === 'full' ? 100 : floatval($voucher['amount']);
        
        $result['success'] = true;
        $result['voucher'] = $voucher;
        $result['discount_amount'] = $discount_amount;
        $result['discount_type'] = $discount_type;
        $result['message'] = __('Voucher is valid and ready to apply.', 'fp-esperienze');
        
        return $result;
    }
    
    /**
     * Rollback voucher redemption (for cancelled/refunded orders)
     *
     * @param string $code Voucher code
     * @return bool Success status
     */
    public static function rollbackVoucherRedemption($code) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'fp_exp_vouchers';
        
        $voucher = self::getVoucherByCode($code);
        if (!$voucher || $voucher['status'] !== 'redeemed') {
            return false;
        }
        
        // Mark as active again
        $result = $wpdb->update(
            $table_name,
            ['status' => 'active'],
            ['id' => $voucher['id']]
        );
        
        return $result !== false;
    }
}