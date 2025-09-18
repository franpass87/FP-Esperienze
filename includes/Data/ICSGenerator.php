<?php
/**
 * ICS Calendar Generator
 *
 * @package FP\Esperienze\Data
 */

namespace FP\Esperienze\Data;

defined('ABSPATH') || exit;

/**
 * ICS Generator class for creating calendar files
 */
class ICSGenerator {
    
    /**
     * Generate ICS content for a booking
     *
     * @param object $booking Booking data
     * @param object|null $product Product object
     * @param object|null $meeting_point Meeting point data
     * @return string ICS calendar content
     */
    public static function generateBookingICS(object $booking, ?object $product = null, ?object $meeting_point = null): string {
        // Get product if not provided
        if (!$product && $booking->product_id) {
            $product = wc_get_product($booking->product_id);
        }
        
        // Get meeting point if not provided
        if (!$meeting_point && $booking->meeting_point_id) {
            $meeting_point = MeetingPointManager::getMeetingPoint($booking->meeting_point_id);
        }
        
        // Get duration from product or use default
        $duration = 60; // Default 60 minutes
        if ($product && $product->get_meta('_fp_exp_default_duration')) {
            $duration = (int) $product->get_meta('_fp_exp_default_duration');
        }
        
        // Create datetime objects
        $start_datetime = new \DateTime($booking->booking_date . ' ' . $booking->booking_time, wp_timezone());
        $end_datetime = clone $start_datetime;
        $end_datetime->add(new \DateInterval('PT' . $duration . 'M'));
        
        // Convert to UTC for ICS
        $start_utc = clone $start_datetime;
        $start_utc->setTimezone(new \DateTimeZone('UTC'));
        $end_utc = clone $end_datetime;
        $end_utc->setTimezone(new \DateTimeZone('UTC'));
        
        // Prepare event details
        $product_name = $product ? $product->get_name() : __('Experience', 'fp-esperienze');
        $summary = sprintf('%s (%d pax)', $product_name, ($booking->adults ?? 0) + ($booking->children ?? 0));
        
        $description = $product_name;
        if ($booking->adults || $booking->children) {
            $description .= '\\n' . sprintf(__('Participants: %d adults, %d children', 'fp-esperienze'), 
                $booking->adults ?? 0, $booking->children ?? 0);
        }
        if ($booking->customer_notes) {
            $description .= '\\n' . __('Notes: ', 'fp-esperienze') . str_replace(["\r\n", "\n", "\r"], '\\n', $booking->customer_notes);
        }
        
        // Location
        $location = '';
        if ($meeting_point) {
            $location = $meeting_point->name;
            if ($meeting_point->address) {
                $location .= ', ' . $meeting_point->address;
            }
        }
        
        // Generate unique UID using booking ID
        $uid = 'booking-' . $booking->id . '@' . parse_url(home_url(), PHP_URL_HOST);
        
        // Build ICS content
        $ics_content = "BEGIN:VCALENDAR\r\n";
        $ics_content .= "VERSION:2.0\r\n";
        $ics_content .= "PRODID:-//FP Esperienze//Experience Booking//EN\r\n";
        $ics_content .= "CALSCALE:GREGORIAN\r\n";
        $ics_content .= "METHOD:PUBLISH\r\n";
        $ics_content .= "BEGIN:VEVENT\r\n";
        $ics_content .= "UID:" . $uid . "\r\n";
        $ics_content .= "DTSTAMP:" . gmdate('Ymd\THis\Z') . "\r\n";
        $ics_content .= "DTSTART:" . $start_utc->format('Ymd\THis\Z') . "\r\n";
        $ics_content .= "DTEND:" . $end_utc->format('Ymd\THis\Z') . "\r\n";
        $ics_content .= "SUMMARY:" . self::escapeICS($summary) . "\r\n";
        $ics_content .= "DESCRIPTION:" . self::escapeICS($description) . "\r\n";
        
        if ($location) {
            $ics_content .= "LOCATION:" . self::escapeICS($location) . "\r\n";
        }
        
        $ics_content .= "STATUS:CONFIRMED\r\n";
        $ics_content .= "SEQUENCE:0\r\n";
        $ics_content .= "END:VEVENT\r\n";
        $ics_content .= "END:VCALENDAR\r\n";
        
        return $ics_content;
    }
    
    /**
     * Generate ICS content for a product (all scheduled slots)
     *
     * @param int $product_id Product ID
     * @param int $days_ahead Number of days to include (default 30)
     * @return string ICS calendar content
     */
    public static function generateProductICS(int $product_id, int $days_ahead = 30): string {
        $product = wc_get_product($product_id);
        if (!$product || $product->get_type() !== 'experience') {
            return '';
        }
        
        // Get available slots for the next X days
        $events = [];
        $current_date = new \DateTime('now', wp_timezone());
        
        for ($i = 0; $i < $days_ahead; $i++) {
            $date_str = $current_date->format('Y-m-d');
            $slots = Availability::forDay($product_id, $date_str);

            foreach ($slots as $slot) {
                $available_spots = isset($slot['available']) ? (int) $slot['available'] : 0;

                if ($available_spots <= 0) {
                    continue;
                }

                if (empty($slot['start_time']) || empty($slot['end_time'])) {
                    continue;
                }

                $events[] = [
                    'date' => $date_str,
                    'start_time' => $slot['start_time'],
                    'end_time' => $slot['end_time'],
                    'capacity' => isset($slot['capacity']) ? (int) $slot['capacity'] : 0,
                    'available' => $available_spots,
                ];
            }

            $current_date->add(new \DateInterval('P1D'));
        }
        
        if (empty($events)) {
            return '';
        }
        
        // Build ICS content
        $ics_content = "BEGIN:VCALENDAR\r\n";
        $ics_content .= "VERSION:2.0\r\n";
        $ics_content .= "PRODID:-//FP Esperienze//Experience Schedule//EN\r\n";
        $ics_content .= "CALSCALE:GREGORIAN\r\n";
        $ics_content .= "METHOD:PUBLISH\r\n";
        
        foreach ($events as $index => $event) {
            try {
                $start_datetime = new \DateTime($event['date'] . ' ' . $event['start_time'], wp_timezone());
                $end_datetime = new \DateTime($event['date'] . ' ' . $event['end_time'], wp_timezone());
            } catch (\Exception $e) {
                continue;
            }

            // Convert to UTC
            $start_utc = clone $start_datetime;
            $start_utc->setTimezone(new \DateTimeZone('UTC'));
            $end_utc = clone $end_datetime;
            $end_utc->setTimezone(new \DateTimeZone('UTC'));

            $summary = sprintf('%s (%d spots available)', $product->get_name(), $event['available']);
            $uid = 'product-' . $product_id . '-' . $event['date'] . '-' . str_replace(':', '', $event['start_time']) . '@' . parse_url(home_url(), PHP_URL_HOST);
            
            $ics_content .= "BEGIN:VEVENT\r\n";
            $ics_content .= "UID:" . $uid . "\r\n";
            $ics_content .= "DTSTAMP:" . gmdate('Ymd\THis\Z') . "\r\n";
            $ics_content .= "DTSTART:" . $start_utc->format('Ymd\THis\Z') . "\r\n";
            $ics_content .= "DTEND:" . $end_utc->format('Ymd\THis\Z') . "\r\n";
            $ics_content .= "SUMMARY:" . self::escapeICS($summary) . "\r\n";
            $ics_content .= "DESCRIPTION:" . self::escapeICS($product->get_name()) . "\r\n";
            $ics_content .= "STATUS:TENTATIVE\r\n";
            $ics_content .= "SEQUENCE:0\r\n";
            $ics_content .= "END:VEVENT\r\n";
        }
        
        $ics_content .= "END:VCALENDAR\r\n";
        
        return $ics_content;
    }
    
    /**
     * Generate ICS file for user's bookings (no PII)
     *
     * @param int $user_id User ID
     * @return string ICS calendar content
     */
    public static function generateUserBookingsICS(int $user_id): string {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'fp_bookings';
        
        // Get user's confirmed bookings
        $bookings = $wpdb->get_results($wpdb->prepare(
            "SELECT b.*, o.post_status 
             FROM {$table_name} b 
             LEFT JOIN {$wpdb->posts} o ON b.order_id = o.ID 
             WHERE b.status = 'confirmed' 
             AND o.post_status IN ('wc-processing', 'wc-completed')
             AND b.booking_date >= CURDATE()
             AND EXISTS (
                 SELECT 1 FROM {$wpdb->postmeta} 
                 WHERE post_id = b.order_id 
                 AND meta_key = '_customer_user' 
                 AND meta_value = %d
             )
             ORDER BY b.booking_date ASC, b.booking_time ASC",
            $user_id
        ));
        
        if (empty($bookings)) {
            return '';
        }
        
        // Build ICS content
        $ics_content = "BEGIN:VCALENDAR\r\n";
        $ics_content .= "VERSION:2.0\r\n";
        $ics_content .= "PRODID:-//FP Esperienze//My Bookings//EN\r\n";
        $ics_content .= "CALSCALE:GREGORIAN\r\n";
        $ics_content .= "METHOD:PUBLISH\r\n";
        
        foreach ($bookings as $booking) {
            $product = wc_get_product($booking->product_id);
            $meeting_point = $booking->meeting_point_id ? MeetingPointManager::getMeetingPoint($booking->meeting_point_id) : null;
            
            // Get duration
            $duration = 60; // Default
            if ($product && $product->get_meta('_fp_exp_default_duration')) {
                $duration = (int) $product->get_meta('_fp_exp_default_duration');
            }
            
            $start_datetime = new \DateTime($booking->booking_date . ' ' . $booking->booking_time, wp_timezone());
            $end_datetime = clone $start_datetime;
            $end_datetime->add(new \DateInterval('PT' . $duration . 'M'));
            
            // Convert to UTC
            $start_utc = clone $start_datetime;
            $start_utc->setTimezone(new \DateTimeZone('UTC'));
            $end_utc = clone $end_datetime;
            $end_utc->setTimezone(new \DateTimeZone('UTC'));
            
            $product_name = $product ? $product->get_name() : __('Experience', 'fp-esperienze');
            $summary = sprintf('%s (%d pax)', $product_name, ($booking->adults ?? 0) + ($booking->children ?? 0));
            
            // No PII in description - just basic booking info
            $description = $product_name . '\\n' . sprintf(__('Booking ID: %d', 'fp-esperienze'), $booking->id);
            
            $location = '';
            if ($meeting_point) {
                $location = $meeting_point->name;
                if ($meeting_point->address) {
                    $location .= ', ' . $meeting_point->address;
                }
            }
            
            $uid = 'booking-' . $booking->id . '@' . parse_url(home_url(), PHP_URL_HOST);
            
            $ics_content .= "BEGIN:VEVENT\r\n";
            $ics_content .= "UID:" . $uid . "\r\n";
            $ics_content .= "DTSTAMP:" . gmdate('Ymd\THis\Z') . "\r\n";
            $ics_content .= "DTSTART:" . $start_utc->format('Ymd\THis\Z') . "\r\n";
            $ics_content .= "DTEND:" . $end_utc->format('Ymd\THis\Z') . "\r\n";
            $ics_content .= "SUMMARY:" . self::escapeICS($summary) . "\r\n";
            $ics_content .= "DESCRIPTION:" . self::escapeICS($description) . "\r\n";
            
            if ($location) {
                $ics_content .= "LOCATION:" . self::escapeICS($location) . "\r\n";
            }
            
            $ics_content .= "STATUS:CONFIRMED\r\n";
            $ics_content .= "SEQUENCE:0\r\n";
            $ics_content .= "END:VEVENT\r\n";
        }
        
        $ics_content .= "END:VCALENDAR\r\n";
        
        return $ics_content;
    }
    
    /**
     * Create ICS file and return path
     *
     * @param string $content  ICS content
     * @param string $filename Filename without extension
     * @return string|\WP_Error File path or WP_Error on failure
     */
    public static function createICSFile(string $content, string $filename) {
        $ics_dir = FP_ESPERIENZE_ICS_DIR;

        global $wp_filesystem;
        require_once ABSPATH . 'wp-admin/includes/file.php';
        if (!WP_Filesystem()) {
            $msg = 'ICSGenerator: WP_Filesystem initialization failed.';
            error_log($msg);
            return new \WP_Error('fp_fs_init_failed', $msg);
        }

        // Create directory if it doesn't exist
        if (!file_exists($ics_dir)) {
            wp_mkdir_p($ics_dir);

            // Add .htaccess for security
            $htaccess_content  = "# Deny direct access to ICS files\n";
            $htaccess_content .= "<Files *.ics>\n";
            $htaccess_content .= "    Require all denied\n";
            $htaccess_content .= "</Files>\n";
            if (!$wp_filesystem->put_contents($ics_dir . '/.htaccess', $htaccess_content, FS_CHMOD_FILE)) {
                $msg = 'ICSGenerator: Failed to create .htaccess file.';
                error_log($msg);
                return new \WP_Error('fp_htaccess_write_failed', $msg);
            }
        }

        $file_path = $ics_dir . '/' . sanitize_file_name($filename) . '.ics';

        if (!$wp_filesystem->put_contents($file_path, $content, FS_CHMOD_FILE)) {
            $msg = 'ICSGenerator: Failed to write ICS file.';
            error_log($msg);
            return new \WP_Error('fp_ics_write_failed', $msg);
        }

        return $file_path;
    }
    
    /**
     * Escape text for ICS format
     *
     * @param string $text Text to escape
     * @return string Escaped text
     */
    private static function escapeICS(string $text): string {
        // Escape special characters for ICS format
        $text = str_replace(['\\', ';', ',', "\n", "\r"], ['\\\\', '\\;', '\\,', '\\n', ''], $text);
        return $text;
    }
}