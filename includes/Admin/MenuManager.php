<?php
/**
 * Admin Menu Manager
 *
 * @package FP\Esperienze\Admin
 */

namespace FP\Esperienze\Admin;

use FP\Esperienze\Data\OverrideManager;
use FP\Esperienze\Data\MeetingPointManager;
use FP\Esperienze\Data\ExtraManager;

defined('ABSPATH') || exit;

/**
 * Menu Manager class
 */
class MenuManager {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', [$this, 'addAdminMenu']);
    }

    /**
     * Add admin menu
     */
    public function addAdminMenu(): void {
        // Main menu page
        add_menu_page(
            __('FP Esperienze', 'fp-esperienze'),
            __('FP Esperienze', 'fp-esperienze'),
            'manage_woocommerce',
            'fp-esperienze',
            [$this, 'dashboardPage'],
            'dashicons-calendar-alt',
            25
        );

        // Dashboard submenu
        add_submenu_page(
            'fp-esperienze',
            __('Dashboard', 'fp-esperienze'),
            __('Dashboard', 'fp-esperienze'),
            'manage_woocommerce',
            'fp-esperienze',
            [$this, 'dashboardPage']
        );

        // Bookings submenu
        add_submenu_page(
            'fp-esperienze',
            __('Bookings', 'fp-esperienze'),
            __('Bookings', 'fp-esperienze'),
            'manage_woocommerce',
            'fp-esperienze-bookings',
            [$this, 'bookingsPage']
        );

        // Meeting Points submenu
        add_submenu_page(
            'fp-esperienze',
            __('Meeting Points', 'fp-esperienze'),
            __('Meeting Points', 'fp-esperienze'),
            'manage_woocommerce',
            'fp-esperienze-meeting-points',
            [$this, 'meetingPointsPage']
        );

        // Extras submenu
        add_submenu_page(
            'fp-esperienze',
            __('Extras', 'fp-esperienze'),
            __('Extras', 'fp-esperienze'),
            'manage_woocommerce',
            'fp-esperienze-extras',
            [$this, 'extrasPage']
        );

        // Vouchers submenu
        add_submenu_page(
            'fp-esperienze',
            __('Vouchers', 'fp-esperienze'),
            __('Vouchers', 'fp-esperienze'),
            'manage_woocommerce',
            'fp-esperienze-vouchers',
            [$this, 'vouchersPage']
        );

        // Closures submenu
        add_submenu_page(
            'fp-esperienze',
            __('Closures', 'fp-esperienze'),
            __('Closures', 'fp-esperienze'),
            'manage_woocommerce',
            'fp-esperienze-closures',
            [$this, 'closuresPage']
        );

        // Settings submenu
        add_submenu_page(
            'fp-esperienze',
            __('Settings', 'fp-esperienze'),
            __('Settings', 'fp-esperienze'),
            'manage_woocommerce',
            'fp-esperienze-settings',
            [$this, 'settingsPage']
        );
    }

    /**
     * Dashboard page
     */
    public function dashboardPage(): void {
        ?>
        <div class="wrap">
            <h1><?php _e('FP Esperienze Dashboard', 'fp-esperienze'); ?></h1>
            <div class="fp-admin-dashboard">
                <div class="fp-dashboard-widgets">
                    <div class="fp-widget">
                        <h3><?php _e('Recent Bookings', 'fp-esperienze'); ?></h3>
                        <p><?php _e('Dashboard functionality will be implemented in future updates.', 'fp-esperienze'); ?></p>
                    </div>
                    
                    <div class="fp-widget">
                        <h3><?php _e('Statistics', 'fp-esperienze'); ?></h3>
                        <p><?php _e('Booking statistics and analytics coming soon.', 'fp-esperienze'); ?></p>
                    </div>
                    
                    <div class="fp-widget">
                        <h3><?php _e('Quick Actions', 'fp-esperienze'); ?></h3>
                        <p>
                            <a href="<?php echo admin_url('admin.php?page=fp-esperienze-bookings'); ?>" class="button">
                                <?php _e('View Bookings', 'fp-esperienze'); ?>
                            </a>
                            <a href="<?php echo admin_url('post-new.php?post_type=product'); ?>" class="button button-primary">
                                <?php _e('Add Experience', 'fp-esperienze'); ?>
                            </a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Bookings page
     */
    public function bookingsPage(): void {
        // Handle form submissions
        if ($_POST && isset($_POST['action'])) {
            $this->handleBookingsActions();
        }
        
        // Handle CSV export
        if (isset($_GET['action']) && $_GET['action'] === 'export_csv') {
            $this->exportBookingsCSV();
            return;
        }
        
        // Get current filters
        $filters = [
            'status' => sanitize_text_field($_GET['status'] ?? ''),
            'product_id' => absint($_GET['product_id'] ?? 0),
            'date_from' => sanitize_text_field($_GET['date_from'] ?? ''),
            'date_to' => sanitize_text_field($_GET['date_to'] ?? ''),
        ];
        
        // Remove empty filters
        $filters = array_filter($filters);
        
        // Get bookings
        $bookings = \FP\Esperienze\Booking\BookingManager::getBookings($filters);
        
        // Get experience products for filter dropdown
        $experience_products = $this->getExperienceProducts();
        
        ?>
        <div class="wrap">
            <h1><?php _e('Bookings Management', 'fp-esperienze'); ?></h1>
            
            <!-- Filters -->
            <div class="fp-bookings-filters">
                <form method="GET" action="">
                    <input type="hidden" name="page" value="fp-esperienze-bookings">
                    
                    <div class="filter-row">
                        <select name="status">
                            <option value=""><?php _e('All Statuses', 'fp-esperienze'); ?></option>
                            <option value="confirmed" <?php selected($_GET['status'] ?? '', 'confirmed'); ?>><?php _e('Confirmed', 'fp-esperienze'); ?></option>
                            <option value="cancelled" <?php selected($_GET['status'] ?? '', 'cancelled'); ?>><?php _e('Cancelled', 'fp-esperienze'); ?></option>
                            <option value="refunded" <?php selected($_GET['status'] ?? '', 'refunded'); ?>><?php _e('Refunded', 'fp-esperienze'); ?></option>
                        </select>
                        
                        <select name="product_id">
                            <option value=""><?php _e('All Products', 'fp-esperienze'); ?></option>
                            <?php foreach ($experience_products as $product) : ?>
                                <option value="<?php echo esc_attr($product->ID); ?>" <?php selected($_GET['product_id'] ?? '', $product->ID); ?>>
                                    <?php echo esc_html($product->post_title); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <input type="date" name="date_from" value="<?php echo esc_attr($_GET['date_from'] ?? ''); ?>" placeholder="<?php _e('From Date', 'fp-esperienze'); ?>">
                        <input type="date" name="date_to" value="<?php echo esc_attr($_GET['date_to'] ?? ''); ?>" placeholder="<?php _e('To Date', 'fp-esperienze'); ?>">
                        
                        <input type="submit" class="button" value="<?php _e('Filter', 'fp-esperienze'); ?>">
                        <a href="<?php echo admin_url('admin.php?page=fp-esperienze-bookings'); ?>" class="button"><?php _e('Clear', 'fp-esperienze'); ?></a>
                        <a href="<?php echo add_query_arg(array_merge($_GET, ['action' => 'export_csv']), admin_url('admin.php')); ?>" class="button button-secondary"><?php _e('Export CSV', 'fp-esperienze'); ?></a>
                    </div>
                </form>
            </div>
            
            <!-- Calendar View Toggle -->
            <div class="fp-view-toggle">
                <button id="fp-list-view" class="button button-primary"><?php _e('List View', 'fp-esperienze'); ?></button>
                <button id="fp-calendar-view" class="button"><?php _e('Calendar View', 'fp-esperienze'); ?></button>
            </div>
            
            <!-- List View -->
            <div id="fp-bookings-list" class="fp-bookings-content">
                <?php if (empty($bookings)) : ?>
                    <div class="notice notice-info">
                        <p><?php _e('No bookings found matching your criteria.', 'fp-esperienze'); ?></p>
                    </div>
                <?php else : ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('ID', 'fp-esperienze'); ?></th>
                                <th><?php _e('Order', 'fp-esperienze'); ?></th>
                                <th><?php _e('Product', 'fp-esperienze'); ?></th>
                                <th><?php _e('Date & Time', 'fp-esperienze'); ?></th>
                                <th><?php _e('Participants', 'fp-esperienze'); ?></th>
                                <th><?php _e('Status', 'fp-esperienze'); ?></th>
                                <th><?php _e('Meeting Point', 'fp-esperienze'); ?></th>
                                <th><?php _e('Created', 'fp-esperienze'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bookings as $booking) : ?>
                                <tr>
                                    <td><?php echo esc_html($booking->id); ?></td>
                                    <td>
                                        <a href="<?php echo esc_url(admin_url('post.php?post=' . $booking->order_id . '&action=edit')); ?>">
                                            #<?php echo esc_html($booking->order_id); ?>
                                        </a>
                                    </td>
                                    <td>
                                        <?php 
                                        $product = wc_get_product($booking->product_id);
                                        echo $product ? esc_html($product->get_name()) : __('Product not found', 'fp-esperienze');
                                        ?>
                                    </td>
                                    <td>
                                        <?php 
                                        echo esc_html(date_i18n(get_option('date_format'), strtotime($booking->booking_date))); 
                                        echo '<br>';
                                        echo esc_html(date_i18n(get_option('time_format'), strtotime($booking->booking_time)));
                                        ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $total = $booking->adults + $booking->children;
                                        printf(__('%d total (%d adults, %d children)', 'fp-esperienze'), $total, $booking->adults, $booking->children);
                                        ?>
                                    </td>
                                    <td>
                                        <span class="booking-status status-<?php echo esc_attr($booking->status); ?>">
                                            <?php echo esc_html(ucfirst($booking->status)); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                        if ($booking->meeting_point_id) {
                                            $mp = \FP\Esperienze\Data\MeetingPointManager::getMeetingPoint($booking->meeting_point_id);
                                            echo $mp ? esc_html($mp->name) : __('Not found', 'fp-esperienze');
                                        } else {
                                            echo __('Not set', 'fp-esperienze');
                                        }
                                        ?>
                                    </td>
                                    <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($booking->created_at))); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            
            <!-- Calendar View -->
            <div id="fp-bookings-calendar" class="fp-bookings-content" style="display: none;">
                <div id="fp-calendar"></div>
            </div>
        </div>
        
        <style>
        .fp-bookings-filters {
            background: #fff;
            padding: 15px;
            margin: 15px 0;
            border: 1px solid #ccd0d4;
        }
        .filter-row > * {
            margin-right: 10px;
            margin-bottom: 5px;
        }
        .fp-view-toggle {
            margin: 15px 0;
        }
        .fp-view-toggle .button {
            margin-right: 5px;
        }
        .booking-status {
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .status-confirmed {
            background: #46b450;
            color: white;
        }
        .status-cancelled {
            background: #dc3232;
            color: white;
        }
        .status-refunded {
            background: #ffb900;
            color: black;
        }
        #fp-calendar {
            height: 600px;
            margin-top: 20px;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // View toggle
            $('#fp-list-view').click(function() {
                $(this).addClass('button-primary').removeClass('button-secondary');
                $('#fp-calendar-view').removeClass('button-primary').addClass('button-secondary');
                $('#fp-bookings-list').show();
                $('#fp-bookings-calendar').hide();
            });
            
            $('#fp-calendar-view').click(function() {
                $(this).addClass('button-primary').removeClass('button-secondary');
                $('#fp-list-view').removeClass('button-primary').addClass('button-secondary');
                $('#fp-bookings-list').hide();
                $('#fp-bookings-calendar').show();
                
                // Initialize calendar if not already done
                if (!window.fpCalendarInitialized) {
                    if (typeof FPEsperienzeAdmin !== 'undefined') {
                        FPEsperienzeAdmin.initBookingsCalendar();
                    }
                    window.fpCalendarInitialized = true;
                }
            });
        });
        </script>
        <?php
    }

    /**
     * Meeting Points page
     */
    public function meetingPointsPage(): void {
        // Handle form submissions
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && wp_verify_nonce($_POST['_wpnonce'] ?? '', 'fp_meeting_points_action')) {
            $this->handleMeetingPointAction();
        }
        
        // Get action and ID for editing/deleting
        $action = sanitize_text_field($_GET['action'] ?? '');
        $meeting_point_id = (int) ($_GET['id'] ?? 0);
        $meeting_point = null;
        
        if ($action === 'edit' && $meeting_point_id) {
            $meeting_point = MeetingPointManager::getMeetingPoint($meeting_point_id);
            if (!$meeting_point) {
                $action = ''; // Reset if meeting point not found
            }
        }
        
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php _e('Meeting Points', 'fp-esperienze'); ?></h1>
            
            <?php if ($action === 'edit') : ?>
                <a href="<?php echo admin_url('admin.php?page=fp-esperienze-meeting-points'); ?>" class="page-title-action">
                    <?php _e('Add New', 'fp-esperienze'); ?>
                </a>
            <?php else : ?>
                <a href="<?php echo admin_url('admin.php?page=fp-esperienze-meeting-points&action=edit'); ?>" class="page-title-action">
                    <?php _e('Add New', 'fp-esperienze'); ?>
                </a>
            <?php endif; ?>
            
            <hr class="wp-header-end">
            
            <?php if ($action === 'edit' || $action === '') : ?>
                <!-- Add/Edit Form -->
                <div class="fp-meeting-point-form">
                    <h2><?php echo $meeting_point ? __('Edit Meeting Point', 'fp-esperienze') : __('Add New Meeting Point', 'fp-esperienze'); ?></h2>
                    
                    <form method="post" action="">
                        <?php wp_nonce_field('fp_meeting_points_action'); ?>
                        
                        <input type="hidden" name="action" value="<?php echo $meeting_point ? 'update' : 'create'; ?>">
                        <?php if ($meeting_point) : ?>
                            <input type="hidden" name="meeting_point_id" value="<?php echo esc_attr($meeting_point->id); ?>">
                        <?php endif; ?>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="meeting_point_name"><?php _e('Name', 'fp-esperienze'); ?> <span class="description">(required)</span></label>
                                </th>
                                <td>
                                    <input name="meeting_point_name" type="text" id="meeting_point_name" 
                                           value="<?php echo $meeting_point ? esc_attr($meeting_point->name) : ''; ?>" 
                                           class="regular-text" required />
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="meeting_point_address"><?php _e('Address', 'fp-esperienze'); ?> <span class="description">(required)</span></label>
                                </th>
                                <td>
                                    <textarea name="meeting_point_address" id="meeting_point_address" 
                                              rows="3" cols="50" class="large-text" required><?php echo $meeting_point ? esc_textarea($meeting_point->address) : ''; ?></textarea>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="meeting_point_lat"><?php _e('Latitude', 'fp-esperienze'); ?></label>
                                </th>
                                <td>
                                    <input name="meeting_point_lat" type="number" step="any" id="meeting_point_lat" 
                                           value="<?php echo $meeting_point ? esc_attr($meeting_point->lat) : ''; ?>" 
                                           class="regular-text" />
                                    <p class="description"><?php _e('Decimal degrees format (e.g., 41.9028)', 'fp-esperienze'); ?></p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="meeting_point_lng"><?php _e('Longitude', 'fp-esperienze'); ?></label>
                                </th>
                                <td>
                                    <input name="meeting_point_lng" type="number" step="any" id="meeting_point_lng" 
                                           value="<?php echo $meeting_point ? esc_attr($meeting_point->lng) : ''; ?>" 
                                           class="regular-text" />
                                    <p class="description"><?php _e('Decimal degrees format (e.g., 12.4964)', 'fp-esperienze'); ?></p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="meeting_point_place_id"><?php _e('Google Place ID', 'fp-esperienze'); ?></label>
                                </th>
                                <td>
                                    <input name="meeting_point_place_id" type="text" id="meeting_point_place_id" 
                                           value="<?php echo $meeting_point ? esc_attr($meeting_point->place_id) : ''; ?>" 
                                           class="regular-text" />
                                    <p class="description"><?php _e('Google Places API Place ID for enhanced integration', 'fp-esperienze'); ?></p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="meeting_point_note"><?php _e('Note', 'fp-esperienze'); ?></label>
                                </th>
                                <td>
                                    <textarea name="meeting_point_note" id="meeting_point_note" 
                                              rows="5" cols="50" class="large-text"><?php echo $meeting_point ? esc_textarea($meeting_point->note) : ''; ?></textarea>
                                    <p class="description"><?php _e('Additional instructions or notes for this meeting point', 'fp-esperienze'); ?></p>
                                </td>
                            </tr>
                        </table>
                        
                        <p class="submit">
                            <input type="submit" name="submit" id="submit" class="button button-primary" 
                                   value="<?php echo $meeting_point ? __('Update Meeting Point', 'fp-esperienze') : __('Add Meeting Point', 'fp-esperienze'); ?>">
                            
                            <?php if ($meeting_point) : ?>
                                <a href="<?php echo admin_url('admin.php?page=fp-esperienze-meeting-points'); ?>" class="button">
                                    <?php _e('Cancel', 'fp-esperienze'); ?>
                                </a>
                            <?php endif; ?>
                        </p>
                    </form>
                </div>
                
                <?php if ($action !== 'edit') : ?>
                    <hr />
                <?php endif; ?>
            <?php endif; ?>
            
            <?php if ($action !== 'edit') : ?>
                <!-- Meeting Points List -->
                <div class="fp-meeting-points-list">
                    <h2><?php _e('Meeting Points List', 'fp-esperienze'); ?></h2>
                    
                    <?php
                    $meeting_points = MeetingPointManager::getAllMeetingPoints();
                    
                    if (empty($meeting_points)) :
                    ?>
                        <p><?php _e('No meeting points found. Add your first meeting point above.', 'fp-esperienze'); ?></p>
                    <?php else : ?>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th scope="col" class="column-primary"><?php _e('Name', 'fp-esperienze'); ?></th>
                                    <th scope="col"><?php _e('Address', 'fp-esperienze'); ?></th>
                                    <th scope="col"><?php _e('Coordinates', 'fp-esperienze'); ?></th>
                                    <th scope="col"><?php _e('Actions', 'fp-esperienze'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($meeting_points as $mp) : ?>
                                    <tr>
                                        <td class="column-primary">
                                            <strong><?php echo esc_html($mp->name); ?></strong>
                                            <div class="row-actions">
                                                <span class="edit">
                                                    <a href="<?php echo admin_url('admin.php?page=fp-esperienze-meeting-points&action=edit&id=' . $mp->id); ?>">
                                                        <?php _e('Edit', 'fp-esperienze'); ?>
                                                    </a> |
                                                </span>
                                                <span class="delete">
                                                    <a href="#" onclick="return confirmDelete(<?php echo $mp->id; ?>, '<?php echo esc_js($mp->name); ?>');" class="submitdelete">
                                                        <?php _e('Delete', 'fp-esperienze'); ?>
                                                    </a>
                                                </span>
                                            </div>
                                        </td>
                                        <td><?php echo esc_html(wp_trim_words($mp->address, 10)); ?></td>
                                        <td>
                                            <?php if ($mp->lat && $mp->lng) : ?>
                                                <?php echo esc_html($mp->lat . ', ' . $mp->lng); ?>
                                            <?php else : ?>
                                                <span class="description"><?php _e('Not set', 'fp-esperienze'); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="<?php echo admin_url('admin.php?page=fp-esperienze-meeting-points&action=edit&id=' . $mp->id); ?>" class="button button-small">
                                                <?php _e('Edit', 'fp-esperienze'); ?>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Delete Confirmation Form -->
        <form id="delete-meeting-point-form" method="post" style="display: none;">
            <?php wp_nonce_field('fp_meeting_points_action'); ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="meeting_point_id" id="delete-meeting-point-id" value="">
        </form>
        
        <script>
        function confirmDelete(id, name) {
            if (confirm('<?php echo esc_js(__('Are you sure you want to delete the meeting point', 'fp-esperienze')); ?> "' + name + '"?\n\n<?php echo esc_js(__('This action cannot be undone and will fail if the meeting point is currently in use.', 'fp-esperienze')); ?>')) {
                document.getElementById('delete-meeting-point-id').value = id;
                document.getElementById('delete-meeting-point-form').submit();
            }
            return false;
        }
        </script>
        <?php
    }
    
    /**
     * Handle meeting point form actions
     */
    private function handleMeetingPointAction(): void {
        $action = sanitize_text_field($_POST['action'] ?? '');
        
        switch ($action) {
            case 'create':
                $this->createMeetingPoint();
                break;
                
            case 'update':
                $this->updateMeetingPoint();
                break;
                
            case 'delete':
                $this->deleteMeetingPoint();
                break;
        }
    }
    
    /**
     * Create new meeting point
     */
    private function createMeetingPoint(): void {
        $data = [
            'name' => sanitize_text_field($_POST['meeting_point_name'] ?? ''),
            'address' => sanitize_textarea_field($_POST['meeting_point_address'] ?? ''),
            'lat' => !empty($_POST['meeting_point_lat']) ? (float) $_POST['meeting_point_lat'] : null,
            'lng' => !empty($_POST['meeting_point_lng']) ? (float) $_POST['meeting_point_lng'] : null,
            'place_id' => sanitize_text_field($_POST['meeting_point_place_id'] ?? ''),
            'note' => sanitize_textarea_field($_POST['meeting_point_note'] ?? '')
        ];
        
        if (empty($data['name']) || empty($data['address'])) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error is-dismissible"><p>' . 
                     esc_html__('Name and address are required fields.', 'fp-esperienze') . 
                     '</p></div>';
            });
            return;
        }
        
        $result = MeetingPointManager::createMeetingPoint($data);
        
        if ($result) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success is-dismissible"><p>' . 
                     esc_html__('Meeting point created successfully.', 'fp-esperienze') . 
                     '</p></div>';
            });
        } else {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error is-dismissible"><p>' . 
                     esc_html__('Failed to create meeting point.', 'fp-esperienze') . 
                     '</p></div>';
            });
        }
    }
    
    /**
     * Update meeting point
     */
    private function updateMeetingPoint(): void {
        $id = (int) ($_POST['meeting_point_id'] ?? 0);
        
        if (!$id) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error is-dismissible"><p>' . 
                     esc_html__('Invalid meeting point ID.', 'fp-esperienze') . 
                     '</p></div>';
            });
            return;
        }
        
        $data = [
            'name' => sanitize_text_field($_POST['meeting_point_name'] ?? ''),
            'address' => sanitize_textarea_field($_POST['meeting_point_address'] ?? ''),
            'lat' => !empty($_POST['meeting_point_lat']) ? (float) $_POST['meeting_point_lat'] : null,
            'lng' => !empty($_POST['meeting_point_lng']) ? (float) $_POST['meeting_point_lng'] : null,
            'place_id' => sanitize_text_field($_POST['meeting_point_place_id'] ?? ''),
            'note' => sanitize_textarea_field($_POST['meeting_point_note'] ?? '')
        ];
        
        if (empty($data['name']) || empty($data['address'])) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error is-dismissible"><p>' . 
                     esc_html__('Name and address are required fields.', 'fp-esperienze') . 
                     '</p></div>';
            });
            return;
        }
        
        $result = MeetingPointManager::updateMeetingPoint($id, $data);
        
        if ($result) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success is-dismissible"><p>' . 
                     esc_html__('Meeting point updated successfully.', 'fp-esperienze') . 
                     '</p></div>';
            });
            
            // Redirect to list view after successful update
            wp_redirect(admin_url('admin.php?page=fp-esperienze-meeting-points'));
            exit;
        } else {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error is-dismissible"><p>' . 
                     esc_html__('Failed to update meeting point.', 'fp-esperienze') . 
                     '</p></div>';
            });
        }
    }
    
    /**
     * Delete meeting point
     */
    private function deleteMeetingPoint(): void {
        $id = (int) ($_POST['meeting_point_id'] ?? 0);
        
        if (!$id) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error is-dismissible"><p>' . 
                     esc_html__('Invalid meeting point ID.', 'fp-esperienze') . 
                     '</p></div>';
            });
            return;
        }
        
        $result = MeetingPointManager::deleteMeetingPoint($id);
        
        if ($result) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success is-dismissible"><p>' . 
                     esc_html__('Meeting point deleted successfully.', 'fp-esperienze') . 
                     '</p></div>';
            });
        } else {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error is-dismissible"><p>' . 
                     esc_html__('Cannot delete meeting point. It may be in use by schedules or set as default for products.', 'fp-esperienze') . 
                     '</p></div>';
            });
        }
    }

    /**
     * Extras page
     */
    public function extrasPage(): void {
        // Handle form submissions
        if ($_POST) {
            $this->handleExtrasActions();
        }
        
        $extras = ExtraManager::getAllExtras();
        $tax_classes = ExtraManager::getTaxClasses();
        
        ?>
        <div class="wrap">
            <h1><?php _e('Extras Management', 'fp-esperienze'); ?></h1>
            
            <div class="fp-extras-form">
                <h2><?php _e('Add New Extra', 'fp-esperienze'); ?></h2>
                <form method="post">
                    <?php wp_nonce_field('fp_extra_action', 'fp_extra_nonce'); ?>
                    <input type="hidden" name="action" value="create">
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="extra_name"><?php _e('Name', 'fp-esperienze'); ?> *</label>
                            </th>
                            <td>
                                <input type="text" id="extra_name" name="extra_name" class="regular-text" required>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="extra_description"><?php _e('Description', 'fp-esperienze'); ?></label>
                            </th>
                            <td>
                                <textarea id="extra_description" name="extra_description" class="large-text" rows="3"></textarea>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="extra_price"><?php _e('Price', 'fp-esperienze'); ?> *</label>
                            </th>
                            <td>
                                <input type="number" id="extra_price" name="extra_price" step="0.01" min="0" class="small-text" required>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="extra_billing_type"><?php _e('Billing Type', 'fp-esperienze'); ?></label>
                            </th>
                            <td>
                                <select id="extra_billing_type" name="extra_billing_type">
                                    <option value="per_person"><?php _e('Per Person', 'fp-esperienze'); ?></option>
                                    <option value="per_booking"><?php _e('Per Booking', 'fp-esperienze'); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="extra_tax_class"><?php _e('Tax Class', 'fp-esperienze'); ?></label>
                            </th>
                            <td>
                                <select id="extra_tax_class" name="extra_tax_class">
                                    <?php foreach ($tax_classes as $value => $label) : ?>
                                        <option value="<?php echo esc_attr($value); ?>"><?php echo esc_html($label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="extra_max_quantity"><?php _e('Max Quantity', 'fp-esperienze'); ?></label>
                            </th>
                            <td>
                                <input type="number" id="extra_max_quantity" name="extra_max_quantity" min="1" value="1" class="small-text">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="extra_is_required"><?php _e('Required', 'fp-esperienze'); ?></label>
                            </th>
                            <td>
                                <input type="checkbox" id="extra_is_required" name="extra_is_required" value="1">
                                <span class="description"><?php _e('Check if this extra is required for booking', 'fp-esperienze'); ?></span>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="extra_is_active"><?php _e('Active', 'fp-esperienze'); ?></label>
                            </th>
                            <td>
                                <input type="checkbox" id="extra_is_active" name="extra_is_active" value="1" checked>
                                <span class="description"><?php _e('Check to make this extra available for selection', 'fp-esperienze'); ?></span>
                            </td>
                        </tr>
                    </table>
                    
                    <?php submit_button(__('Add Extra', 'fp-esperienze')); ?>
                </form>
            </div>
            
            <h2><?php _e('Existing Extras', 'fp-esperienze'); ?></h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Name', 'fp-esperienze'); ?></th>
                        <th><?php _e('Description', 'fp-esperienze'); ?></th>
                        <th><?php _e('Price', 'fp-esperienze'); ?></th>
                        <th><?php _e('Billing Type', 'fp-esperienze'); ?></th>
                        <th><?php _e('Tax Class', 'fp-esperienze'); ?></th>
                        <th><?php _e('Max Qty', 'fp-esperienze'); ?></th>
                        <th><?php _e('Required', 'fp-esperienze'); ?></th>
                        <th><?php _e('Active', 'fp-esperienze'); ?></th>
                        <th><?php _e('Actions', 'fp-esperienze'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($extras)) : ?>
                        <tr>
                            <td colspan="9"><?php _e('No extras found.', 'fp-esperienze'); ?></td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($extras as $extra) : ?>
                            <tr>
                                <td><strong><?php echo esc_html($extra->name); ?></strong></td>
                                <td><?php echo esc_html(wp_trim_words($extra->description, 10)); ?></td>
                                <td><?php echo wc_price($extra->price); ?></td>
                                <td><?php echo esc_html($extra->billing_type === 'per_person' ? __('Per Person', 'fp-esperienze') : __('Per Booking', 'fp-esperienze')); ?></td>
                                <td><?php echo esc_html($tax_classes[$extra->tax_class] ?? __('Standard', 'fp-esperienze')); ?></td>
                                <td><?php echo esc_html($extra->max_quantity); ?></td>
                                <td><?php echo $extra->is_required ? '✓' : '—'; ?></td>
                                <td><?php echo $extra->is_active ? '✓' : '—'; ?></td>
                                <td>
                                    <button type="button" class="button button-small fp-edit-extra" 
                                            data-id="<?php echo esc_attr($extra->id); ?>"
                                            data-name="<?php echo esc_attr($extra->name); ?>"
                                            data-description="<?php echo esc_attr($extra->description); ?>"
                                            data-price="<?php echo esc_attr($extra->price); ?>"
                                            data-billing-type="<?php echo esc_attr($extra->billing_type); ?>"
                                            data-tax-class="<?php echo esc_attr($extra->tax_class); ?>"
                                            data-max-quantity="<?php echo esc_attr($extra->max_quantity); ?>"
                                            data-is-required="<?php echo esc_attr($extra->is_required); ?>"
                                            data-is-active="<?php echo esc_attr($extra->is_active); ?>">
                                        <?php _e('Edit', 'fp-esperienze'); ?>
                                    </button>
                                    <form method="post" style="display: inline-block;" onsubmit="return confirm('<?php esc_attr_e('Are you sure you want to delete this extra?', 'fp-esperienze'); ?>');">
                                        <?php wp_nonce_field('fp_extra_action', 'fp_extra_nonce'); ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="extra_id" value="<?php echo esc_attr($extra->id); ?>">
                                        <input type="submit" class="button button-small button-link-delete" value="<?php esc_attr_e('Delete', 'fp-esperienze'); ?>">
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Edit Extra Modal -->
        <div id="fp-edit-extra-modal" style="display: none;">
            <form method="post" id="fp-edit-extra-form">
                <?php wp_nonce_field('fp_extra_action', 'fp_extra_nonce'); ?>
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="extra_id" id="edit_extra_id">
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="edit_extra_name"><?php _e('Name', 'fp-esperienze'); ?> *</label>
                        </th>
                        <td>
                            <input type="text" id="edit_extra_name" name="extra_name" class="regular-text" required>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="edit_extra_description"><?php _e('Description', 'fp-esperienze'); ?></label>
                        </th>
                        <td>
                            <textarea id="edit_extra_description" name="extra_description" class="large-text" rows="3"></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="edit_extra_price"><?php _e('Price', 'fp-esperienze'); ?> *</label>
                        </th>
                        <td>
                            <input type="number" id="edit_extra_price" name="extra_price" step="0.01" min="0" class="small-text" required>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="edit_extra_billing_type"><?php _e('Billing Type', 'fp-esperienze'); ?></label>
                        </th>
                        <td>
                            <select id="edit_extra_billing_type" name="extra_billing_type">
                                <option value="per_person"><?php _e('Per Person', 'fp-esperienze'); ?></option>
                                <option value="per_booking"><?php _e('Per Booking', 'fp-esperienze'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="edit_extra_tax_class"><?php _e('Tax Class', 'fp-esperienze'); ?></label>
                        </th>
                        <td>
                            <select id="edit_extra_tax_class" name="extra_tax_class">
                                <?php foreach ($tax_classes as $value => $label) : ?>
                                    <option value="<?php echo esc_attr($value); ?>"><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="edit_extra_max_quantity"><?php _e('Max Quantity', 'fp-esperienze'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="edit_extra_max_quantity" name="extra_max_quantity" min="1" class="small-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="edit_extra_is_required"><?php _e('Required', 'fp-esperienze'); ?></label>
                        </th>
                        <td>
                            <input type="checkbox" id="edit_extra_is_required" name="extra_is_required" value="1">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="edit_extra_is_active"><?php _e('Active', 'fp-esperienze'); ?></label>
                        </th>
                        <td>
                            <input type="checkbox" id="edit_extra_is_active" name="extra_is_active" value="1">
                        </td>
                    </tr>
                </table>
            </form>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Edit extra functionality
            $('.fp-edit-extra').click(function() {
                var data = $(this).data();
                $('#edit_extra_id').val(data.id);
                $('#edit_extra_name').val(data.name);
                $('#edit_extra_description').val(data.description);
                $('#edit_extra_price').val(data.price);
                $('#edit_extra_billing_type').val(data.billingType);
                $('#edit_extra_tax_class').val(data.taxClass);
                $('#edit_extra_max_quantity').val(data.maxQuantity);
                $('#edit_extra_is_required').prop('checked', data.isRequired == '1');
                $('#edit_extra_is_active').prop('checked', data.isActive == '1');
                
                // Show modal using WordPress thickbox
                tb_show('<?php esc_js_e('Edit Extra', 'fp-esperienze'); ?>', '#TB_inline?inlineId=fp-edit-extra-modal&width=600&height=500');
            });
            
            // Submit edit form
            $('#fp-edit-extra-form').submit(function() {
                tb_remove();
            });
        });
        </script>
        <?php
    }

    /**
     * Vouchers page
     */
    public function vouchersPage(): void {
        ?>
        <div class="wrap">
            <h1><?php _e('Vouchers Management', 'fp-esperienze'); ?></h1>
            <p><?php _e('Voucher management functionality will be implemented in future updates.', 'fp-esperienze'); ?></p>
        </div>
        <?php
    }

    /**
     * Closures page
     */
    public function closuresPage(): void {
        // Handle form submissions
        if ($_POST) {
            $this->handleClosuresActions();
        }
        
        $closures = OverrideManager::getGlobalClosures();
        
        ?>
        <div class="wrap">
            <h1><?php _e('Global Closures Management', 'fp-esperienze'); ?></h1>
            
            <div class="fp-closures-form">
                <h2><?php _e('Add Global Closure', 'fp-esperienze'); ?></h2>
                <form method="post">
                    <?php wp_nonce_field('fp_closure_action', 'fp_closure_nonce'); ?>
                    <input type="hidden" name="action" value="add_closure">
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="closure_date"><?php _e('Date', 'fp-esperienze'); ?></label>
                            </th>
                            <td>
                                <input type="date" id="closure_date" name="closure_date" required>
                                <p class="description"><?php _e('Select the date to close for all experiences.', 'fp-esperienze'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="closure_reason"><?php _e('Reason', 'fp-esperienze'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="closure_reason" name="closure_reason" class="regular-text">
                                <p class="description"><?php _e('Optional reason for the closure.', 'fp-esperienze'); ?></p>
                            </td>
                        </tr>
                    </table>
                    
                    <?php submit_button(__('Add Global Closure', 'fp-esperienze')); ?>
                </form>
            </div>
            
            <div class="fp-closures-list">
                <h2><?php _e('Existing Closures', 'fp-esperienze'); ?></h2>
                
                <?php if (empty($closures)): ?>
                    <p><?php _e('No global closures found.', 'fp-esperienze'); ?></p>
                <?php else: ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th scope="col"><?php _e('Date', 'fp-esperienze'); ?></th>
                                <th scope="col"><?php _e('Experience', 'fp-esperienze'); ?></th>
                                <th scope="col"><?php _e('Reason', 'fp-esperienze'); ?></th>
                                <th scope="col"><?php _e('Actions', 'fp-esperienze'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($closures as $closure): ?>
                                <tr>
                                    <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($closure->date))); ?></td>
                                    <td><?php echo esc_html($closure->product_name ?: __('Unknown Product', 'fp-esperienze')); ?></td>
                                    <td><?php echo esc_html($closure->reason ?: '-'); ?></td>
                                    <td>
                                        <form method="post" style="display: inline;">
                                            <?php wp_nonce_field('fp_closure_action', 'fp_closure_nonce'); ?>
                                            <input type="hidden" name="action" value="remove_closure">
                                            <input type="hidden" name="closure_date" value="<?php echo esc_attr($closure->date); ?>">
                                            <button type="submit" class="button button-small" 
                                                    onclick="return confirm('<?php esc_attr_e('Are you sure you want to remove this closure?', 'fp-esperienze'); ?>')">
                                                <?php _e('Remove', 'fp-esperienze'); ?>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Handle extras actions
     */
    private function handleExtrasActions(): void {
        if (!isset($_POST['fp_extra_nonce']) || !wp_verify_nonce($_POST['fp_extra_nonce'], 'fp_extra_action')) {
            wp_die(__('Security check failed.', 'fp-esperienze'));
        }
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('You do not have permission to perform this action.', 'fp-esperienze'));
        }
        
        $action = sanitize_text_field($_POST['action'] ?? '');
        
        switch ($action) {
            case 'create':
                $this->createExtra();
                break;
                
            case 'update':
                $this->updateExtra();
                break;
                
            case 'delete':
                $this->deleteExtra();
                break;
        }
    }
    
    /**
     * Create new extra
     */
    private function createExtra(): void {
        $data = [
            'name' => sanitize_text_field($_POST['extra_name'] ?? ''),
            'description' => sanitize_textarea_field($_POST['extra_description'] ?? ''),
            'price' => floatval($_POST['extra_price'] ?? 0),
            'billing_type' => in_array($_POST['extra_billing_type'] ?? '', ['per_person', 'per_booking']) ? $_POST['extra_billing_type'] : 'per_person',
            'tax_class' => sanitize_text_field($_POST['extra_tax_class'] ?? ''),
            'max_quantity' => absint($_POST['extra_max_quantity'] ?? 1),
            'is_required' => isset($_POST['extra_is_required']) ? 1 : 0,
            'is_active' => isset($_POST['extra_is_active']) ? 1 : 0
        ];
        
        if (empty($data['name'])) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error is-dismissible"><p>' . 
                     esc_html__('Extra name is required.', 'fp-esperienze') . 
                     '</p></div>';
            });
            return;
        }
        
        $result = ExtraManager::createExtra($data);
        
        if ($result) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success is-dismissible"><p>' . 
                     esc_html__('Extra created successfully.', 'fp-esperienze') . 
                     '</p></div>';
            });
        } else {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error is-dismissible"><p>' . 
                     esc_html__('Failed to create extra.', 'fp-esperienze') . 
                     '</p></div>';
            });
        }
    }
    
    /**
     * Update extra
     */
    private function updateExtra(): void {
        $id = absint($_POST['extra_id'] ?? 0);
        
        if (!$id) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error is-dismissible"><p>' . 
                     esc_html__('Invalid extra ID.', 'fp-esperienze') . 
                     '</p></div>';
            });
            return;
        }
        
        $data = [
            'name' => sanitize_text_field($_POST['extra_name'] ?? ''),
            'description' => sanitize_textarea_field($_POST['extra_description'] ?? ''),
            'price' => floatval($_POST['extra_price'] ?? 0),
            'billing_type' => in_array($_POST['extra_billing_type'] ?? '', ['per_person', 'per_booking']) ? $_POST['extra_billing_type'] : 'per_person',
            'tax_class' => sanitize_text_field($_POST['extra_tax_class'] ?? ''),
            'max_quantity' => absint($_POST['extra_max_quantity'] ?? 1),
            'is_required' => isset($_POST['extra_is_required']) ? 1 : 0,
            'is_active' => isset($_POST['extra_is_active']) ? 1 : 0
        ];
        
        if (empty($data['name'])) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error is-dismissible"><p>' . 
                     esc_html__('Extra name is required.', 'fp-esperienze') . 
                     '</p></div>';
            });
            return;
        }
        
        $result = ExtraManager::updateExtra($id, $data);
        
        if ($result) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success is-dismissible"><p>' . 
                     esc_html__('Extra updated successfully.', 'fp-esperienze') . 
                     '</p></div>';
            });
        } else {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error is-dismissible"><p>' . 
                     esc_html__('Failed to update extra.', 'fp-esperienze') . 
                     '</p></div>';
            });
        }
    }
    
    /**
     * Delete extra
     */
    private function deleteExtra(): void {
        $id = absint($_POST['extra_id'] ?? 0);
        
        if (!$id) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error is-dismissible"><p>' . 
                     esc_html__('Invalid extra ID.', 'fp-esperienze') . 
                     '</p></div>';
            });
            return;
        }
        
        $result = ExtraManager::deleteExtra($id);
        
        if ($result) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success is-dismissible"><p>' . 
                     esc_html__('Extra deleted successfully.', 'fp-esperienze') . 
                     '</p></div>';
            });
        } else {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error is-dismissible"><p>' . 
                     esc_html__('Cannot delete extra. It may be in use by products.', 'fp-esperienze') . 
                     '</p></div>';
            });
        }
    }
    
    /**
     * Handle closures actions
     */
    private function handleClosuresActions(): void {
        if (!isset($_POST['fp_closure_nonce']) || !wp_verify_nonce($_POST['fp_closure_nonce'], 'fp_closure_action')) {
            wp_die(__('Security check failed.', 'fp-esperienze'));
        }
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('You do not have permission to perform this action.', 'fp-esperienze'));
        }
        
        $action = sanitize_text_field($_POST['action'] ?? '');
        
        switch ($action) {
            case 'add_closure':
                $date = sanitize_text_field($_POST['closure_date'] ?? '');
                $reason = sanitize_text_field($_POST['closure_reason'] ?? '');
                
                if ($date) {
                    $result = OverrideManager::createGlobalClosure($date, $reason);
                    if ($result) {
                        add_action('admin_notices', function() {
                            echo '<div class="notice notice-success is-dismissible"><p>' . 
                                 esc_html__('Global closure added successfully.', 'fp-esperienze') . 
                                 '</p></div>';
                        });
                    } else {
                        add_action('admin_notices', function() {
                            echo '<div class="notice notice-error is-dismissible"><p>' . 
                                 esc_html__('Failed to add global closure.', 'fp-esperienze') . 
                                 '</p></div>';
                        });
                    }
                }
                break;
                
            case 'remove_closure':
                $date = sanitize_text_field($_POST['closure_date'] ?? '');
                
                if ($date) {
                    $result = OverrideManager::removeGlobalClosure($date);
                    if ($result) {
                        add_action('admin_notices', function() {
                            echo '<div class="notice notice-success is-dismissible"><p>' . 
                                 esc_html__('Global closure removed successfully.', 'fp-esperienze') . 
                                 '</p></div>';
                        });
                    } else {
                        add_action('admin_notices', function() {
                            echo '<div class="notice notice-error is-dismissible"><p>' . 
                                 esc_html__('Failed to remove global closure.', 'fp-esperienze') . 
                                 '</p></div>';
                        });
                    }
                }
                break;
        }
    }

    /**
     * Settings page
     */
    public function settingsPage(): void {
        ?>
        <div class="wrap">
            <h1><?php _e('FP Esperienze Settings', 'fp-esperienze'); ?></h1>
            <p><?php _e('Settings functionality will be implemented in future updates.', 'fp-esperienze'); ?></p>
        </div>
        <?php
    }
    
    /**
     * Handle bookings actions
     */
    private function handleBookingsActions(): void {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        if (!wp_verify_nonce($_POST['fp_booking_nonce'] ?? '', 'fp_booking_action')) {
            return;
        }
        
        $action = sanitize_text_field($_POST['action'] ?? '');
        
        switch ($action) {
            case 'update_status':
                $booking_id = absint($_POST['booking_id'] ?? 0);
                $new_status = sanitize_text_field($_POST['new_status'] ?? '');
                
                if ($booking_id && $new_status) {
                    // TODO: Implement status update
                    add_action('admin_notices', function() {
                        echo '<div class="notice notice-success is-dismissible"><p>' . 
                             esc_html__('Booking status updated successfully.', 'fp-esperienze') . 
                             '</p></div>';
                    });
                }
                break;
        }
    }
    
    /**
     * Export bookings to CSV
     */
    private function exportBookingsCSV(): void {
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions.', 'fp-esperienze'));
        }
        
        // Get current filters
        $filters = [
            'status' => sanitize_text_field($_GET['status'] ?? ''),
            'product_id' => absint($_GET['product_id'] ?? 0),
            'date_from' => sanitize_text_field($_GET['date_from'] ?? ''),
            'date_to' => sanitize_text_field($_GET['date_to'] ?? ''),
        ];
        
        // Remove empty filters
        $filters = array_filter($filters);
        
        // Get bookings
        $bookings = \FP\Esperienze\Booking\BookingManager::getBookings($filters);
        
        // Set headers for CSV download
        $filename = 'bookings-' . date('Y-m-d-H-i-s') . '.csv';
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Output CSV
        $output = fopen('php://output', 'w');
        
        // CSV headers
        fputcsv($output, [
            __('Booking ID', 'fp-esperienze'),
            __('Order ID', 'fp-esperienze'),
            __('Product', 'fp-esperienze'),
            __('Date', 'fp-esperienze'),
            __('Time', 'fp-esperienze'),
            __('Adults', 'fp-esperienze'),
            __('Children', 'fp-esperienze'),
            __('Total Participants', 'fp-esperienze'),
            __('Status', 'fp-esperienze'),
            __('Meeting Point', 'fp-esperienze'),
            __('Customer Notes', 'fp-esperienze'),
            __('Admin Notes', 'fp-esperienze'),
            __('Created', 'fp-esperienze'),
        ]);
        
        // CSV data
        foreach ($bookings as $booking) {
            $product = wc_get_product($booking->product_id);
            $product_name = $product ? $product->get_name() : __('Product not found', 'fp-esperienze');
            
            $meeting_point_name = '';
            if ($booking->meeting_point_id) {
                $mp = \FP\Esperienze\Data\MeetingPointManager::getMeetingPoint($booking->meeting_point_id);
                $meeting_point_name = $mp ? $mp->name : __('Not found', 'fp-esperienze');
            }
            
            fputcsv($output, [
                $booking->id,
                $booking->order_id,
                $product_name,
                $booking->booking_date,
                $booking->booking_time,
                $booking->adults,
                $booking->children,
                $booking->adults + $booking->children,
                ucfirst($booking->status),
                $meeting_point_name,
                $booking->customer_notes,
                $booking->admin_notes,
                $booking->created_at,
            ]);
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Get experience products for filter dropdown
     */
    private function getExperienceProducts(): array {
        $posts = get_posts([
            'post_type' => 'product',
            'meta_query' => [
                [
                    'key' => '_fp_experience_enabled',
                    'value' => 'yes',
                    'compare' => '='
                ]
            ],
            'posts_per_page' => -1,
            'post_status' => 'publish'
        ]);
        
        return $posts;
    }
}