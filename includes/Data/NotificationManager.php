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
        $order = wc_get_order($booking->order_id);
        $meeting_point = $booking->meeting_point_id ? MeetingPointManager::getMeetingPoint($booking->meeting_point_id) : null;
        
        if (!$product || !$order) {
            return;
        }
        
        // Build email content
        $subject = sprintf(
            __('[%s] New Booking: %s', 'fp-esperienze'),
            get_bloginfo('name'),
            $product->get_name()
        );
        
        $message = $this->buildStaffNotificationContent($booking, $product, $order, $meeting_point);
        
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
            error_log("Staff notification sent for booking #{$booking->id} to {$sent_count}/" . count($emails) . " recipients");
        }
    }
    
    /**
     * Build staff notification email content
     *
     * @param object $booking Booking data
     * @param \WC_Product $product Product object
     * @param \WC_Order $order Order object
     * @param object|null $meeting_point Meeting point data
     * @return string Email content
     */
    private function buildStaffNotificationContent(object $booking, \WC_Product $product, \WC_Order $order, ?object $meeting_point = null): string {
        $site_name = get_bloginfo('name');
        $product_name = $product->get_name();
        $customer_name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
        $customer_email = $order->get_billing_email();
        $customer_phone = $order->get_billing_phone();
        
        // Get branding settings for consistent colors
        $branding_settings = get_option('fp_esperienze_branding', []);
        $primary_color = $branding_settings['primary_color'] ?? '#ff6b35';
        
        // Format booking date and time
        $booking_datetime = new \DateTime($booking->booking_date . ' ' . $booking->booking_time, wp_timezone());
        $formatted_date = wp_date(get_option('date_format'), $booking_datetime->getTimestamp());
        $formatted_time = wp_date(get_option('time_format'), $booking_datetime->getTimestamp());
        
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
        $message .= '<p><strong>' . esc_html__('Participants:', 'fp-esperienze') . '</strong> ' . 
                    sprintf(esc_html__('%d adults, %d children', 'fp-esperienze'), 
                    $booking->adults ?? 0, $booking->children ?? 0) . '</p>';
        
        if ($meeting_point) {
            $message .= '<p><strong>' . esc_html__('Meeting Point:', 'fp-esperienze') . '</strong> ' . esc_html($meeting_point->name);
            if ($meeting_point->address) {
                $message .= '<br><em>' . esc_html($meeting_point->address) . '</em>';
            }
            $message .= '</p>';
        }
        
        $message .= '<p><strong>' . esc_html__('Booking ID:', 'fp-esperienze') . '</strong> #' . esc_html($booking->id) . '</p>';
        $message .= '<p><strong>' . esc_html__('Order ID:', 'fp-esperienze') . '</strong> #' . esc_html($booking->order_id) . '</p>';
        $message .= '</div>';
        
        $message .= '<div style="background: #f0f8ff; padding: 20px; border-radius: 5px; margin-bottom: 20px;">';
        $message .= '<h3 style="margin-top: 0; color: #333;">' . esc_html__('Customer Information', 'fp-esperienze') . '</h3>';
        $message .= '<p><strong>' . esc_html__('Name:', 'fp-esperienze') . '</strong> ' . esc_html($customer_name) . '</p>';
        $message .= '<p><strong>' . esc_html__('Email:', 'fp-esperienze') . '</strong> ' . esc_html($customer_email) . '</p>';
        
        if ($customer_phone) {
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
        
        $message .= '<a href="' . esc_url(admin_url('post.php?post=' . $booking->order_id . '&action=edit')) . '" ' .
                    'style="background: #0073aa; color: white; padding: 12px 24px; text-decoration: none; ' .
                    'border-radius: 5px; display: inline-block;">' . 
                    esc_html__('View Order', 'fp-esperienze') . '</a>';
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