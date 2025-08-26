<?php
/**
 * Bookings Admin Interface
 *
 * @package FP\Esperienze\Admin
 */

namespace FP\Esperienze\Admin;

use FP\Esperienze\Booking\BookingManager;

defined('ABSPATH') || exit;

/**
 * Bookings admin class
 * 
 * Handles the admin interface for bookings management
 * 
 * @since 1.0.0
 */
class BookingsAdmin {
    
    /**
     * Booking manager instance
     * 
     * @var BookingManager
     */
    private $booking_manager;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->booking_manager = new BookingManager();
        $this->init();
    }
    
    /**
     * Initialize hooks
     */
    private function init(): void {
        add_action('admin_enqueue_scripts', [$this, 'enqueueScripts']);
        add_action('wp_ajax_fp_export_bookings_csv', [$this, 'exportBookingsCSV']);
        add_action('wp_ajax_fp_get_booking_events', [$this, 'getBookingEvents']);
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueueScripts($hook): void {
        if ($hook !== 'fp-esperienze_page_fp-esperienze-bookings') {
            return;
        }
        
        // Enqueue FullCalendar from CDN
        wp_enqueue_script(
            'fullcalendar',
            'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js',
            [],
            '6.1.10',
            true
        );
        
        // Enqueue our booking admin script
        wp_enqueue_script(
            'fp-esperienze-bookings-admin',
            FP_ESPERIENZE_PLUGIN_URL . 'assets/js/bookings-admin.js',
            ['jquery', 'fullcalendar'],
            FP_ESPERIENZE_VERSION,
            true
        );
        
        // Localize script
        wp_localize_script('fp-esperienze-bookings-admin', 'fpEsperienzeBookings', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('fp_esperienze_bookings'),
            'strings' => [
                'export' => __('Export CSV', 'fp-esperienze'),
                'filter' => __('Filter', 'fp-esperienze'),
                'loading' => __('Loading...', 'fp-esperienze'),
            ]
        ]);
        
        // Enqueue admin styles
        wp_enqueue_style(
            'fp-esperienze-bookings-admin',
            FP_ESPERIENZE_PLUGIN_URL . 'assets/css/bookings-admin.css',
            [],
            FP_ESPERIENZE_VERSION
        );
    }
    
    /**
     * Render bookings management page
     */
    public function renderBookingsPage(): void {
        $current_tab = $_GET['tab'] ?? 'list';
        
        ?>
        <div class="wrap">
            <h1><?php _e('Bookings Management', 'fp-esperienze'); ?></h1>
            
            <nav class="nav-tab-wrapper">
                <a href="<?php echo admin_url('admin.php?page=fp-esperienze-bookings&tab=list'); ?>" 
                   class="nav-tab <?php echo $current_tab === 'list' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('List View', 'fp-esperienze'); ?>
                </a>
                <a href="<?php echo admin_url('admin.php?page=fp-esperienze-bookings&tab=calendar'); ?>" 
                   class="nav-tab <?php echo $current_tab === 'calendar' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Calendar View', 'fp-esperienze'); ?>
                </a>
            </nav>
            
            <div class="fp-bookings-content">
                <?php
                switch ($current_tab) {
                    case 'calendar':
                        $this->renderCalendarView();
                        break;
                    case 'list':
                    default:
                        $this->renderListView();
                        break;
                }
                ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render list view
     */
    private function renderListView(): void {
        // Get filter parameters
        $status = $_GET['status'] ?? '';
        $product_id = absint($_GET['product_id'] ?? 0);
        $date_from = sanitize_text_field($_GET['date_from'] ?? '');
        $date_to = sanitize_text_field($_GET['date_to'] ?? '');
        $paged = absint($_GET['paged'] ?? 1);
        
        // Get bookings
        $per_page = 20;
        $offset = ($paged - 1) * $per_page;
        
        $args = [
            'status' => $status,
            'product_id' => $product_id,
            'date_from' => $date_from,
            'date_to' => $date_to,
            'limit' => $per_page,
            'offset' => $offset,
            'orderby' => 'booking_date',
            'order' => 'DESC'
        ];
        
        $bookings = $this->booking_manager->getBookings($args);
        $total_bookings = $this->booking_manager->getBookingCount($args);
        
        // Get experience products for filter
        $experience_products = wc_get_products([
            'type' => 'experience',
            'limit' => -1,
            'status' => 'publish'
        ]);
        
        ?>
        <div class="fp-bookings-filters">
            <form method="get" action="">
                <input type="hidden" name="page" value="fp-esperienze-bookings">
                <input type="hidden" name="tab" value="list">
                
                <div class="filter-row">
                    <label for="status"><?php _e('Status:', 'fp-esperienze'); ?></label>
                    <select name="status" id="status">
                        <option value=""><?php _e('All Statuses', 'fp-esperienze'); ?></option>
                        <option value="pending" <?php selected($status, 'pending'); ?>><?php _e('Pending', 'fp-esperienze'); ?></option>
                        <option value="confirmed" <?php selected($status, 'confirmed'); ?>><?php _e('Confirmed', 'fp-esperienze'); ?></option>
                        <option value="cancelled" <?php selected($status, 'cancelled'); ?>><?php _e('Cancelled', 'fp-esperienze'); ?></option>
                        <option value="refunded" <?php selected($status, 'refunded'); ?>><?php _e('Refunded', 'fp-esperienze'); ?></option>
                    </select>
                    
                    <label for="product_id"><?php _e('Experience:', 'fp-esperienze'); ?></label>
                    <select name="product_id" id="product_id">
                        <option value=""><?php _e('All Experiences', 'fp-esperienze'); ?></option>
                        <?php foreach ($experience_products as $product): ?>
                            <option value="<?php echo $product->get_id(); ?>" <?php selected($product_id, $product->get_id()); ?>>
                                <?php echo esc_html($product->get_name()); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <label for="date_from"><?php _e('From:', 'fp-esperienze'); ?></label>
                    <input type="date" name="date_from" id="date_from" value="<?php echo esc_attr($date_from); ?>">
                    
                    <label for="date_to"><?php _e('To:', 'fp-esperienze'); ?></label>
                    <input type="date" name="date_to" id="date_to" value="<?php echo esc_attr($date_to); ?>">
                    
                    <input type="submit" class="button" value="<?php _e('Filter', 'fp-esperienze'); ?>">
                    <a href="<?php echo admin_url('admin.php?page=fp-esperienze-bookings&tab=list'); ?>" class="button">
                        <?php _e('Reset', 'fp-esperienze'); ?>
                    </a>
                </div>
            </form>
            
            <div class="export-actions">
                <button type="button" id="export-csv" class="button button-secondary">
                    <?php _e('Export CSV', 'fp-esperienze'); ?>
                </button>
            </div>
        </div>
        
        <div class="fp-bookings-table">
            <?php if (empty($bookings)): ?>
                <p><?php _e('No bookings found.', 'fp-esperienze'); ?></p>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('ID', 'fp-esperienze'); ?></th>
                            <th><?php _e('Order', 'fp-esperienze'); ?></th>
                            <th><?php _e('Experience', 'fp-esperienze'); ?></th>
                            <th><?php _e('Date & Time', 'fp-esperienze'); ?></th>
                            <th><?php _e('Participants', 'fp-esperienze'); ?></th>
                            <th><?php _e('Status', 'fp-esperienze'); ?></th>
                            <th><?php _e('Customer Notes', 'fp-esperienze'); ?></th>
                            <th><?php _e('Created', 'fp-esperienze'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bookings as $booking): ?>
                            <?php
                            $product = wc_get_product($booking->product_id);
                            $order = wc_get_order($booking->order_id);
                            ?>
                            <tr>
                                <td><?php echo $booking->id; ?></td>
                                <td>
                                    <a href="<?php echo admin_url('post.php?post=' . $booking->order_id . '&action=edit'); ?>">
                                        #<?php echo $booking->order_id; ?>
                                    </a>
                                </td>
                                <td>
                                    <?php if ($product): ?>
                                        <a href="<?php echo admin_url('post.php?post=' . $product->get_id() . '&action=edit'); ?>">
                                            <?php echo esc_html($product->get_name()); ?>
                                        </a>
                                    <?php else: ?>
                                        <?php _e('Product not found', 'fp-esperienze'); ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo date('Y-m-d', strtotime($booking->booking_date)); ?><br>
                                    <small><?php echo date('H:i', strtotime($booking->booking_time)); ?></small>
                                </td>
                                <td>
                                    <?php 
                                    printf(
                                        __('%d adults, %d children', 'fp-esperienze'),
                                        $booking->adults,
                                        $booking->children
                                    );
                                    ?>
                                </td>
                                <td>
                                    <span class="booking-status status-<?php echo esc_attr($booking->status); ?>">
                                        <?php echo esc_html(ucfirst($booking->status)); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo esc_html($booking->customer_notes ?: '-'); ?>
                                </td>
                                <td>
                                    <?php echo date('Y-m-d H:i', strtotime($booking->created_at)); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <?php
                // Pagination
                $total_pages = ceil($total_bookings / $per_page);
                if ($total_pages > 1):
                    $base_url = admin_url('admin.php?page=fp-esperienze-bookings&tab=list');
                    if ($status) $base_url .= '&status=' . $status;
                    if ($product_id) $base_url .= '&product_id=' . $product_id;
                    if ($date_from) $base_url .= '&date_from=' . $date_from;
                    if ($date_to) $base_url .= '&date_to=' . $date_to;
                ?>
                <div class="tablenav">
                    <div class="tablenav-pages">
                        <?php
                        echo paginate_links([
                            'base' => $base_url . '&paged=%#%',
                            'format' => '',
                            'current' => $paged,
                            'total' => $total_pages,
                            'prev_text' => '&laquo;',
                            'next_text' => '&raquo;'
                        ]);
                        ?>
                    </div>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Render calendar view
     */
    private function renderCalendarView(): void {
        ?>
        <div id="fp-bookings-calendar"></div>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var calendarEl = document.getElementById('fp-bookings-calendar');
            var calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay'
                },
                events: function(fetchInfo, successCallback, failureCallback) {
                    // Fetch booking events
                    jQuery.post(fpEsperienzeBookings.ajaxUrl, {
                        action: 'fp_get_booking_events',
                        nonce: fpEsperienzeBookings.nonce,
                        start: fetchInfo.startStr,
                        end: fetchInfo.endStr
                    }, function(response) {
                        if (response.success) {
                            successCallback(response.data);
                        } else {
                            failureCallback();
                        }
                    });
                },
                eventClick: function(info) {
                    // Show booking details
                    alert('Booking ID: ' + info.event.id);
                }
            });
            calendar.render();
        });
        </script>
        <?php
    }
    
    /**
     * Export bookings to CSV
     */
    public function exportBookingsCSV(): void {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'fp_esperienze_bookings')) {
            wp_die('Security check failed');
        }
        
        // Check permissions
        if (!current_user_can('manage_woocommerce')) {
            wp_die('Insufficient permissions');
        }
        
        // Get all bookings
        $bookings = $this->booking_manager->getBookings(['limit' => -1]);
        
        // Set headers for CSV download
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="bookings-' . date('Y-m-d') . '.csv"');
        
        // Create CSV output
        $output = fopen('php://output', 'w');
        
        // CSV headers
        fputcsv($output, [
            'ID',
            'Order ID',
            'Product ID',
            'Product Name',
            'Booking Date',
            'Booking Time',
            'Adults',
            'Children',
            'Status',
            'Customer Notes',
            'Created At'
        ]);
        
        // CSV data
        foreach ($bookings as $booking) {
            $product = wc_get_product($booking->product_id);
            
            fputcsv($output, [
                $booking->id,
                $booking->order_id,
                $booking->product_id,
                $product ? $product->get_name() : 'Unknown',
                $booking->booking_date,
                $booking->booking_time,
                $booking->adults,
                $booking->children,
                $booking->status,
                $booking->customer_notes,
                $booking->created_at
            ]);
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Get booking events for calendar
     */
    public function getBookingEvents(): void {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'fp_esperienze_bookings')) {
            wp_send_json_error('Security check failed');
        }
        
        // Check permissions
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $start = sanitize_text_field($_POST['start']);
        $end = sanitize_text_field($_POST['end']);
        
        // Get bookings in the date range
        $bookings = $this->booking_manager->getBookings([
            'date_from' => date('Y-m-d', strtotime($start)),
            'date_to' => date('Y-m-d', strtotime($end)),
            'limit' => -1
        ]);
        
        $events = [];
        foreach ($bookings as $booking) {
            $product = wc_get_product($booking->product_id);
            
            $events[] = [
                'id' => $booking->id,
                'title' => $product ? $product->get_name() : 'Unknown Experience',
                'start' => $booking->booking_date . 'T' . $booking->booking_time,
                'backgroundColor' => $this->getStatusColor($booking->status),
                'textColor' => '#fff',
                'extendedProps' => [
                    'booking_id' => $booking->id,
                    'order_id' => $booking->order_id,
                    'adults' => $booking->adults,
                    'children' => $booking->children,
                    'status' => $booking->status
                ]
            ];
        }
        
        wp_send_json_success($events);
    }
    
    /**
     * Get color for booking status
     * 
     * @param string $status Booking status
     * @return string Color code
     */
    private function getStatusColor(string $status): string {
        switch ($status) {
            case 'confirmed':
                return '#28a745';
            case 'pending':
                return '#ffc107';
            case 'cancelled':
                return '#dc3545';
            case 'refunded':
                return '#17a2b8';
            default:
                return '#6c757d';
        }
    }
}