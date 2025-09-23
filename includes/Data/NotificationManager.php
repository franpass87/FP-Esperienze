<?php
/**
 * Notification Manager
 *
 * @package FP\Esperienze\Data
 */

namespace FP\Esperienze\Data;

use FP\Esperienze\Data\ICSGenerator;
use FP\Esperienze\Data\MeetingPointManager;
use FP\Esperienze\REST\ICSAPI;

defined('ABSPATH') || exit;

/**
 * Notification Manager class for handling email notifications and ICS attachments
 */
class NotificationManager {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Hook into booking creation for staff notifications
        add_action('fp_esperienze_booking_created', [$this, 'handleBookingCreated'], 10, 2);
        
        // Hook into order completion for ICS attachments
        add_action('woocommerce_order_status_completed', [$this, 'attachICSToOrderEmail'], 20, 1);
        add_action('woocommerce_order_status_processing', [$this, 'attachICSToOrderEmail'], 20, 1);
    }
    
    /**
     * Handle booking created event
     *
     * @param int $product_id Product ID
     * @param string $booking_date Booking date
     */
    public function handleBookingCreated(int $product_id, string $booking_date): void {
        // Get the most recent booking for this product and date to get booking details
        global $wpdb;
        $table_name = $wpdb->prefix . 'fp_bookings';
        
        $booking = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} 
             WHERE product_id = %d AND booking_date = %s 
             AND status = 'confirmed'
             ORDER BY created_at DESC 
             LIMIT 1",
            $product_id,
            $booking_date
        ));
        
        if ($booking) {
            $this->sendStaffNotification($booking);
        }
    }
    
    /**
     * Send staff notification for new booking
     *
     * @param object $booking Booking data
     */
    public function sendStaffNotification($booking): void {
        $settings = get_option('fp_esperienze_notifications', []);
        
        // Check if staff notifications are enabled
        if (empty($settings['staff_notifications_enabled'])) {
            return;
        }
        
        // Get staff emails
        $staff_emails = $settings['staff_emails'] ?? '';
        if (empty($staff_emails)) {
            return;
        }
        
        // Parse email addresses
        $email_lines = explode("\n", $staff_emails);
        $emails = [];
        foreach ($email_lines as $email) {
            $email = trim($email);
            if (!empty($email) && is_email($email)) {
                $emails[] = $email;
            }
        }
        
        if (empty($emails)) {
            return;
        }
        
        // Get booking details
        $product = wc_get_product($booking->product_id);
        $order = null;

        if (!empty($booking->order_id)) {
            $order_candidate = wc_get_order($booking->order_id);

            if ($order_candidate instanceof \WC_Order) {
                $order = $order_candidate;
            }
        }

        $meeting_point = $booking->meeting_point_id ? MeetingPointManager::getMeetingPoint($booking->meeting_point_id) : null;

        if (!$product) {
            return;
        }

        // Build email content
        $subject = sprintf(
            __('[%s] New Booking: %s', 'fp-esperienze'),
            get_bloginfo('name'),
            $product->get_name()
        );

        $customer_details = $this->resolveCustomerDetails($booking, $order);

        $message = $this->buildStaffNotificationContent(
            $booking,
            $product,
            $order,
            $meeting_point,
            $customer_details
        );
        
        // Email headers
        $sender_name = get_option('fp_esperienze_gift_email_sender_name', get_bloginfo('name'));
        $sender_email = get_option('fp_esperienze_gift_email_sender_email', get_option('admin_email'));
        
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $sender_name . ' <' . $sender_email . '>'
        ];
        
        // Send to each staff email
        $sent_count = 0;
        foreach ($emails as $email) {
            if (wp_mail($email, $subject, $message, $headers)) {
                $sent_count++;
            }
        }
        
        // Log the notification
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(
                "Staff notification sent for booking #{$booking->id} to {$sent_count}/" .
                count($emails) .
                ' recipients'
            );
        }
    }

    /**
     * Resolve customer contact details for staff notifications.
     *
     * @param object         $booking Booking data.
     * @param \WC_Order|null $order   Order instance when available.
     *
     * @return array{name:string,email:string,phone:string}
     */
    private function resolveCustomerDetails(object $booking, ?\WC_Order $order): array {
        $name  = '';
        $email = '';
        $phone = '';

        if ($order) {
            $email = (string) $order->get_billing_email();
            $phone = (string) $order->get_billing_phone();

            $order_name = trim(
                sprintf(
                    '%s %s',
                    $order->get_billing_first_name(),
                    $order->get_billing_last_name()
                )
            );

            if ($order_name === '') {
                $order_name = (string) $order->get_formatted_billing_full_name();
            }

            if ($order_name === '') {
                $order_name = $email;
            }

            $name = trim((string) $order_name);
        }

        if ($name === '' && isset($booking->customer_name) && $booking->customer_name !== null) {
            $name = trim((string) $booking->customer_name);
        }

        if ($email === '' && isset($booking->customer_email) && $booking->customer_email !== null) {
            $email = trim((string) $booking->customer_email);
        }

        if ($phone === '' && isset($booking->customer_phone) && $booking->customer_phone !== null) {
            $phone = trim((string) $booking->customer_phone);
        }

        $customer_id = isset($booking->customer_id) ? (int) $booking->customer_id : 0;

        if ($customer_id > 0 && function_exists('get_userdata')) {
            $user = get_userdata($customer_id);

            if ($user) {
                if ($email === '' && !empty($user->user_email)) {
                    $email = (string) $user->user_email;
                }

                if ($name === '') {
                    $first_name = '';
                    $last_name  = '';

                    if (function_exists('get_user_meta')) {
                        $first_name = (string) get_user_meta($customer_id, 'first_name', true);
                        $last_name  = (string) get_user_meta($customer_id, 'last_name', true);
                    }

                    $name_candidate = trim($first_name . ' ' . $last_name);

                    if ($name_candidate === '' && !empty($user->display_name)) {
                        $name_candidate = (string) $user->display_name;
                    }

                    if ($name_candidate === '' && $email !== '') {
                        $name_candidate = $email;
                    }

                    $name = $name_candidate;
                }

                if ($phone === '' && function_exists('get_user_meta')) {
                    $phone_meta = get_user_meta($customer_id, 'billing_phone', true);

                    if (is_string($phone_meta) && $phone_meta !== '') {
                        $phone = $phone_meta;
                    }
                }
            }
        }

        return [
            'name'  => $name,
            'email' => $email,
            'phone' => $phone,
        ];
    }

    /**
     * Build staff notification email content
     *
     * @param object $booking Booking data
     * @param \WC_Product $product Product object
     * @param \WC_Order|null $order Order object
     * @param object|null $meeting_point Meeting point data
     * @param array{name:string,email:string,phone:string} $customer_details Customer details
     * @return string Email content
     */
    private function buildStaffNotificationContent(
        object $booking,
        \WC_Product $product,
        ?\WC_Order $order,
        ?object $meeting_point = null,
        array $customer_details = []
    ): string {
        $site_name = get_bloginfo('name');
        $product_name = $product->get_name();
        $customer_name = trim((string) ($customer_details['name'] ?? ''));
        $customer_email = trim((string) ($customer_details['email'] ?? ''));
        $customer_phone = trim((string) ($customer_details['phone'] ?? ''));

        // Get branding settings for consistent colors
        $branding_settings = get_option('fp_esperienze_branding', []);
        $primary_color = $branding_settings['primary_color'] ?? '#ff6b35';

        // Format booking date and time
        $booking_datetime = new \DateTime($booking->booking_date . ' ' . $booking->booking_time, wp_timezone());
        $formatted_date = wp_date(get_option('date_format'), $booking_datetime->getTimestamp());
        $formatted_time = wp_date(get_option('time_format'), $booking_datetime->getTimestamp());

        $adults = isset($booking->adults) ? (int) $booking->adults : 0;
        $children = isset($booking->children) ? (int) $booking->children : 0;
        $participants_total = isset($booking->participants) ? (int) $booking->participants : 0;

        if ($participants_total <= 0) {
            $participants_total = $adults + $children;
        }

        if ($participants_total > 0) {
            /* translators: 1: total participants, 2: adult participants, 3: child participants */
            $participants_text = sprintf(
                esc_html__('%1$d total (%2$d adults, %3$d children)', 'fp-esperienze'),
                $participants_total,
                $adults,
                $children
            );
        } else {
            /* translators: 1: adult participants, 2: child participants */
            $participants_text = sprintf(
                esc_html__('%1$d adults, %2$d children', 'fp-esperienze'),
                $adults,
                $children
            );
        }

        $not_available = esc_html__('Not available', 'fp-esperienze');
        $customer_name_display = $customer_name !== '' ? $customer_name : $not_available;
        $customer_email_display = $customer_email !== '' ? $customer_email : $not_available;

        $message = '<html><body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">';
        $message .= '<div style="max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 8px;">';

        $message .= '<h2 style="color: ' . esc_attr($primary_color) . '; text-align: center; margin-bottom: 30px;">';
        $message .= sprintf(esc_html__('New Booking - %s', 'fp-esperienze'), esc_html($product_name));
        $message .= '</h2>';
        
        $message .= '<div style="background: #f9f9f9; padding: 20px; border-radius: 5px; margin-bottom: 20px;">';
        $message .= '<h3 style="margin-top: 0; color: #333;">' . esc_html__('Booking Details', 'fp-esperienze') . '</h3>';
        $message .= '<p><strong>' . esc_html__('Experience:', 'fp-esperienze') . '</strong> ' . esc_html($product_name) . '</p>';
        $message .= '<p><strong>' . esc_html__('Date:', 'fp-esperienze') . '</strong> ' . esc_html($formatted_date) . '</p>';
        $message .= '<p><strong>' . esc_html__('Time:', 'fp-esperienze') . '</strong> ' . esc_html($formatted_time) . '</p>';
        $message .= '<p><strong>' . esc_html__('Participants:', 'fp-esperienze') . '</strong> ' . $participants_text . '</p>';

        if ($meeting_point) {
            $message .= '<p><strong>' . esc_html__('Meeting Point:', 'fp-esperienze') . '</strong> ' . esc_html($meeting_point->name);
            if ($meeting_point->address) {
                $message .= '<br><em>' . esc_html($meeting_point->address) . '</em>';
            }
            $message .= '</p>';
        }

        $message .= '<p><strong>' . esc_html__('Booking ID:', 'fp-esperienze') . '</strong> #' . esc_html($booking->id) . '</p>';
        if (!empty($booking->order_id)) {
            $message .= '<p><strong>' . esc_html__('Order ID:', 'fp-esperienze') . '</strong> #' . esc_html($booking->order_id) . '</p>';
        } else {
            $message .= '<p><strong>' . esc_html__('Order ID:', 'fp-esperienze') . '</strong> ' . $not_available . '</p>';
        }
        $message .= '</div>';

        $message .= '<div style="background: #f0f8ff; padding: 20px; border-radius: 5px; margin-bottom: 20px;">';
        $message .= '<h3 style="margin-top: 0; color: #333;">' . esc_html__('Customer Information', 'fp-esperienze') . '</h3>';
        $message .= '<p><strong>' . esc_html__('Name:', 'fp-esperienze') . '</strong> ' . esc_html($customer_name_display) . '</p>';
        $message .= '<p><strong>' . esc_html__('Email:', 'fp-esperienze') . '</strong> ' . esc_html($customer_email_display) . '</p>';

        if ($customer_phone !== '') {
            $message .= '<p><strong>' . esc_html__('Phone:', 'fp-esperienze') . '</strong> ' . esc_html($customer_phone) . '</p>';
        }

        if ($booking->customer_notes) {
            $message .= '<p><strong>' . esc_html__('Customer Notes:', 'fp-esperienze') . '</strong><br>' .
                        nl2br(esc_html($booking->customer_notes)) . '</p>';
        }
        $message .= '</div>';
        
        // Admin links
        $message .= '<div style="text-align: center; margin: 30px 0;">';
        $message .= '<a href="' . esc_url(admin_url('admin.php?page=fp-esperienze-bookings')) . '" ' .
                    'style="background: ' . esc_attr($primary_color) . '; color: white; padding: 12px 24px; text-decoration: none; ' .
                    'border-radius: 5px; display: inline-block; margin-right: 10px;">' . 
                    esc_html__('View Bookings', 'fp-esperienze') . '</a>';
        
        if ($order) {
            $message .= '<a href="' . esc_url(admin_url('post.php?post=' . $booking->order_id . '&action=edit')) . '" ' .
                        'style="background: #0073aa; color: white; padding: 12px 24px; text-decoration: none; ' .
                        'border-radius: 5px; display: inline-block;">' .
                        esc_html__('View Order', 'fp-esperienze') . '</a>';
        }
        $message .= '</div>';

        $message .= '<p style="font-size: 12px; color: #666; text-align: center; margin-top: 40px; ' .
                    'border-top: 1px solid #eee; padding-top: 20px;">';
        $message .= sprintf(esc_html__('This notification was sent automatically by %s', 'fp-esperienze'), 
                    esc_html($site_name));
        $message .= '</p>';
        
        $message .= '</div></body></html>';
        
        return $message;
    }
    
    /**
     * Attach ICS calendar to order completion emails
     *
     * @param int $order_id Order ID
     */
    public function attachICSToOrderEmail(int $order_id): void {
        $settings = get_option('fp_esperienze_notifications', []);
        
        // Check if ICS attachments are enabled
        if (empty($settings['ics_attachment_enabled'] ?? true)) {
            return;
        }
        
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        
        // Get experience bookings for this order
        global $wpdb;
        $table_name = $wpdb->prefix . 'fp_bookings';
        
        $bookings = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE order_id = %d AND status = 'confirmed'",
            $order_id
        ));
        
        if (empty($bookings)) {
            return;
        }
        
        // Generate ICS files for each booking
        $ics_files = [];
        
        foreach ($bookings as $booking) {
            $product = wc_get_product($booking->product_id);
            $meeting_point = $booking->meeting_point_id ? MeetingPointManager::getMeetingPoint($booking->meeting_point_id) : null;
            
            if ($product && $product->get_type() === 'experience') {
                $ics_content = ICSGenerator::generateBookingICS($booking, $product, $meeting_point);
                
                if (!empty($ics_content)) {
                    $filename = 'booking-' . $booking->id . '-' . sanitize_file_name($product->get_name());
                    $file_path = ICSGenerator::createICSFile($ics_content, $filename);
                    
                    if ($file_path) {
                        $ics_files[] = $file_path;
                        
                        // Generate booking token for public access
                        $token = ICSAPI::generateBookingToken($booking->id);
                        if ($token) {
                            // Add booking access info to order notes for customer reference
                            $access_url = rest_url('fp-esperienze/v1/ics/file/' . basename($file_path) . '?token=' . $token);
                            $order->add_order_note(
                                sprintf(__('Booking #%d ICS calendar access: %s', 'fp-esperienze'),
                                $booking->id, $access_url),
                                true // customer note
                            );
                        }
                    }
                }
            }
        }
        
        if (!empty($ics_files)) {
            // Hook into email attachments for this order
            add_filter('woocommerce_email_attachments', function($attachments, $email_id, $email_object) use ($ics_files, $order_id) {
                // Only attach to customer-facing emails for this specific order
                if (in_array($email_id, ['customer_processing_order', 'customer_completed_order']) &&
                    $email_object && $email_object->object && $email_object->object->get_id() == $order_id) {
                    return array_merge($attachments, $ics_files);
                }
                return $attachments;
            }, 10, 3);
        }
    }
}