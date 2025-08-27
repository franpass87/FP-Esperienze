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
        
        // Initialize Setup Wizard and System Status
        new SetupWizard();
        new SystemStatus();
        
        // Handle setup wizard redirect
        add_action('admin_init', [$this, 'handleSetupWizardRedirect']);
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
     * Handle setup wizard redirect on first activation
     */
    public function handleSetupWizardRedirect(): void {
        // Only redirect on admin pages, not AJAX or REST requests
        if (!is_admin() || wp_doing_ajax() || wp_doing_cron() || 
            (defined('REST_REQUEST') && REST_REQUEST)) {
            return;
        }

        // Check if we should redirect to setup wizard
        if (get_transient('fp_esperienze_activation_redirect')) {
            delete_transient('fp_esperienze_activation_redirect');
            
            // Don't redirect if setup is already complete
            $setup_wizard = new SetupWizard();
            if (!$setup_wizard->isSetupComplete()) {
                wp_redirect(admin_url('admin.php?page=fp-esperienze-setup-wizard'));
                exit;
            }
        }
    }

    /**
     * Dashboard page
     */
    public function dashboardPage(): void {
        ?>
        <div class="wrap">
            <h1><?php _e('FP Esperienze Dashboard', 'fp-esperienze'); ?></h1>
            
            <?php if (isset($_GET['setup']) && $_GET['setup'] === 'complete') : ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php _e('Setup wizard completed successfully! Your experience booking system is ready to use.', 'fp-esperienze'); ?></p>
                </div>
            <?php endif; ?>
            
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
        global $wpdb;
        
        // Handle actions
        if ($_POST && current_user_can('manage_woocommerce')) {
            $this->handleVoucherActions();
        }
        
        // Pagination setup
        $per_page = 20;
        $current_page = max(1, absint($_GET['paged'] ?? 1));
        $offset = ($current_page - 1) * $per_page;
        
        // Get filters
        $status_filter = sanitize_text_field($_GET['status'] ?? '');
        $product_filter = absint($_GET['product_id'] ?? 0);
        $date_from = sanitize_text_field($_GET['date_from'] ?? '');
        $date_to = sanitize_text_field($_GET['date_to'] ?? '');
        $search = sanitize_text_field($_GET['search'] ?? '');
        
        // Build query
        $table_name = $wpdb->prefix . 'fp_exp_vouchers';
        $where_conditions = ['1=1'];
        $query_params = [];
        
        if (!empty($status_filter)) {
            $where_conditions[] = 'status = %s';
            $query_params[] = $status_filter;
        }
        
        if (!empty($product_filter)) {
            $where_conditions[] = 'product_id = %d';
            $query_params[] = $product_filter;
        }
        
        if (!empty($date_from)) {
            $where_conditions[] = 'created_at >= %s';
            $query_params[] = $date_from . ' 00:00:00';
        }
        
        if (!empty($date_to)) {
            $where_conditions[] = 'created_at <= %s';
            $query_params[] = $date_to . ' 23:59:59';
        }
        
        if (!empty($search)) {
            $where_conditions[] = '(code LIKE %s OR recipient_name LIKE %s OR recipient_email LIKE %s)';
            $search_term = '%' . $wpdb->esc_like($search) . '%';
            $query_params = array_merge($query_params, [$search_term, $search_term, $search_term]);
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        // Get total count
        $total_query = "SELECT COUNT(*) FROM $table_name WHERE $where_clause";
        if (!empty($query_params)) {
            $total_items = $wpdb->get_var($wpdb->prepare($total_query, ...$query_params));
        } else {
            $total_items = $wpdb->get_var($total_query);
        }
        
        // Get vouchers
        $vouchers_query = "SELECT * FROM $table_name WHERE $where_clause ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $all_params = array_merge($query_params, [$per_page, $offset]);
        $vouchers = $wpdb->get_results($wpdb->prepare($vouchers_query, ...$all_params));
        
        // Calculate pagination
        $total_pages = ceil($total_items / $per_page);
        
        // Get experience products for filter
        $experience_products = $this->getExperienceProducts();
        
        ?>
        <div class="wrap">
            <h1><?php _e('Gift Vouchers', 'fp-esperienze'); ?></h1>
            
            <!-- Enhanced Filters -->
            <div class="tablenav top">
                <form method="get" style="display: inline-block;">
                    <input type="hidden" name="page" value="fp-esperienze-vouchers">
                    
                    <select name="status">
                        <option value=""><?php _e('All Statuses', 'fp-esperienze'); ?></option>
                        <option value="active" <?php selected($status_filter, 'active'); ?>><?php _e('Active', 'fp-esperienze'); ?></option>
                        <option value="redeemed" <?php selected($status_filter, 'redeemed'); ?>><?php _e('Redeemed', 'fp-esperienze'); ?></option>
                        <option value="expired" <?php selected($status_filter, 'expired'); ?>><?php _e('Expired', 'fp-esperienze'); ?></option>
                        <option value="void" <?php selected($status_filter, 'void'); ?>><?php _e('Void', 'fp-esperienze'); ?></option>
                    </select>
                    
                    <select name="product_id">
                        <option value=""><?php _e('All Products', 'fp-esperienze'); ?></option>
                        <?php foreach ($experience_products as $product): ?>
                            <option value="<?php echo esc_attr($product->ID); ?>" <?php selected($product_filter, $product->ID); ?>>
                                <?php echo esc_html($product->post_title); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <input type="date" 
                           name="date_from" 
                           value="<?php echo esc_attr($date_from); ?>" 
                           placeholder="<?php esc_attr_e('From date', 'fp-esperienze'); ?>">
                    
                    <input type="date" 
                           name="date_to" 
                           value="<?php echo esc_attr($date_to); ?>" 
                           placeholder="<?php esc_attr_e('To date', 'fp-esperienze'); ?>">
                    
                    <input type="text" 
                           name="search" 
                           value="<?php echo esc_attr($search); ?>" 
                           placeholder="<?php esc_attr_e('Search vouchers...', 'fp-esperienze'); ?>">
                    
                    <input type="submit" class="button" value="<?php esc_attr_e('Filter', 'fp-esperienze'); ?>">
                    
                    <?php if (!empty($status_filter) || !empty($product_filter) || !empty($date_from) || !empty($date_to) || !empty($search)): ?>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=fp-esperienze-vouchers')); ?>" class="button"><?php _e('Clear', 'fp-esperienze'); ?></a>
                    <?php endif; ?>
                </form>
                
                <div class="alignright">
                    <span class="displaying-num"><?php printf(__('%d items', 'fp-esperienze'), $total_items); ?></span>
                </div>
            </div>
            
            <!-- Bulk Actions Form -->
            <form id="vouchers-form" method="post">
                <?php wp_nonce_field('bulk_voucher_action', 'bulk_nonce'); ?>
                
                <div class="tablenav top">
                    <div class="alignleft actions bulkactions">
                        <select name="bulk_action" id="bulk-action-selector-top">
                            <option value=""><?php _e('Bulk actions', 'fp-esperienze'); ?></option>
                            <option value="bulk_void"><?php _e('Void', 'fp-esperienze'); ?></option>
                            <option value="bulk_resend"><?php _e('Resend emails', 'fp-esperienze'); ?></option>
                            <option value="bulk_extend"><?php _e('Extend expiration', 'fp-esperienze'); ?></option>
                        </select>
                        
                        <div id="bulk-extend-options" style="display: none; margin-top: 5px;">
                            <input type="number" name="bulk_extend_months" min="1" max="60" value="12" style="width: 60px;">
                            <label><?php _e('months', 'fp-esperienze'); ?></label>
                        </div>
                        
                        <input type="submit" class="button action" value="<?php esc_attr_e('Apply', 'fp-esperienze'); ?>" onclick="return confirmBulkAction();">
                    </div>
                </div>

                <!-- Vouchers Table -->
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <td class="manage-column column-cb check-column">
                                <input id="cb-select-all-1" type="checkbox">
                            </td>
                            <th><?php _e('Code', 'fp-esperienze'); ?></th>
                            <th><?php _e('Product', 'fp-esperienze'); ?></th>
                            <th><?php _e('Recipient', 'fp-esperienze'); ?></th>
                            <th><?php _e('Value', 'fp-esperienze'); ?></th>
                            <th><?php _e('Status', 'fp-esperienze'); ?></th>
                            <th><?php _e('Expires', 'fp-esperienze'); ?></th>
                            <th><?php _e('Created', 'fp-esperienze'); ?></th>
                            <th><?php _e('Actions', 'fp-esperienze'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($vouchers)): ?>
                            <tr>
                                <td colspan="9" style="text-align: center; padding: 20px;">
                                    <?php _e('No vouchers found.', 'fp-esperienze'); ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($vouchers as $voucher): ?>
                                <?php
                                $product = wc_get_product($voucher->product_id);
                                $product_name = $product ? $product->get_name() : __('Product not found', 'fp-esperienze');
                                
                                $status_class = '';
                                switch ($voucher->status) {
                                    case 'active':
                                        $status_class = 'color: #46b450;';
                                        break;
                                    case 'redeemed':
                                        $status_class = 'color: #00a0d2;';
                                        break;
                                    case 'expired':
                                        $status_class = 'color: #dc3232;';
                                        break;
                                    case 'void':
                                        $status_class = 'color: #666;';
                                        break;
                                }
                                
                                $value_display = $voucher->amount_type === 'full' 
                                    ? __('Full Experience', 'fp-esperienze')
                                    : wc_price($voucher->amount);
                                ?>
                                <tr>
                                    <th scope="row" class="check-column">
                                        <input type="checkbox" name="voucher_ids[]" value="<?php echo esc_attr($voucher->id); ?>">
                                    </th>
                                    <td>
                                        <strong><?php echo esc_html($voucher->code); ?></strong>
                                    </td>
                                    <td>
                                        <?php echo esc_html($product_name); ?>
                                        <?php if ($voucher->order_id): ?>
                                            <br><small><a href="<?php echo esc_url(admin_url('post.php?post=' . $voucher->order_id . '&action=edit')); ?>">
                                                <?php printf(__('Order #%d', 'fp-esperienze'), $voucher->order_id); ?>
                                            </a></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?php echo esc_html($voucher->recipient_name); ?></strong>
                                        <br><small><?php echo esc_html($voucher->recipient_email); ?></small>
                                        <?php if (!empty($voucher->sender_name)): ?>
                                            <br><small><?php printf(__('From: %s', 'fp-esperienze'), esc_html($voucher->sender_name)); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $value_display; ?></td>
                                    <td>
                                        <span style="<?php echo esc_attr($status_class); ?>font-weight: 600;">
                                            <?php echo esc_html(ucfirst($voucher->status)); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($voucher->expires_on))); ?>
                                        <?php if (strtotime($voucher->expires_on) < time() && $voucher->status === 'active'): ?>
                                            <br><small style="color: #dc3232;"><?php _e('Expired', 'fp-esperienze'); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($voucher->created_at))); ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($voucher->pdf_path) && file_exists($voucher->pdf_path)): ?>
                                            <a href="<?php echo esc_url(admin_url('admin.php?page=fp-esperienze-vouchers&action=download_pdf&voucher_id=' . $voucher->id)); ?>" 
                                               class="button button-small"><?php _e('Download PDF', 'fp-esperienze'); ?></a>
                                            <button type="button" 
                                                    class="button button-small fp-copy-pdf-link" 
                                                    data-voucher-id="<?php echo esc_attr($voucher->id); ?>"
                                                    title="<?php esc_attr_e('Copy PDF link', 'fp-esperienze'); ?>">
                                                <?php _e('Copy Link', 'fp-esperienze'); ?>
                                            </button>
                                            <br>
                                        <?php endif; ?>
                                        
                                        <?php if ($voucher->status === 'active'): ?>
                                            <div class="fp-voucher-actions" style="margin-top: 4px;">
                                                <!-- Resend Email -->
                                                <form method="post" style="display: inline-block; margin-right: 4px;">
                                                    <?php wp_nonce_field('fp_voucher_action', 'fp_voucher_nonce'); ?>
                                                    <input type="hidden" name="action" value="resend_voucher">
                                                    <input type="hidden" name="voucher_id" value="<?php echo esc_attr($voucher->id); ?>">
                                                    <input type="submit" 
                                                           class="button button-small" 
                                                           value="<?php esc_attr_e('Resend', 'fp-esperienze'); ?>"
                                                           onclick="return confirm('<?php esc_js_e('Resend voucher email?', 'fp-esperienze'); ?>')">
                                                </form>
                                                
                                                <!-- Extend Expiration -->
                                                <form method="post" style="display: inline-block; margin-right: 4px;">
                                                    <?php wp_nonce_field('fp_voucher_action', 'fp_voucher_nonce'); ?>
                                                    <input type="hidden" name="action" value="extend_voucher">
                                                    <input type="hidden" name="voucher_id" value="<?php echo esc_attr($voucher->id); ?>">
                                                    <input type="number" name="extend_months" min="1" max="60" value="12" style="width: 50px;" title="<?php esc_attr_e('Months to extend', 'fp-esperienze'); ?>">
                                                    <input type="submit" 
                                                           class="button button-small" 
                                                           value="<?php esc_attr_e('Extend', 'fp-esperienze'); ?>"
                                                           onclick="return confirm('<?php esc_js_e('Extend voucher expiration?', 'fp-esperienze'); ?>')">
                                                </form>
                                                
                                                <!-- Void Voucher -->
                                                <form method="post" style="display: inline-block;">
                                                    <?php wp_nonce_field('fp_voucher_action', 'fp_voucher_nonce'); ?>
                                                    <input type="hidden" name="action" value="void_voucher">
                                                    <input type="hidden" name="voucher_id" value="<?php echo esc_attr($voucher->id); ?>">
                                                    <input type="submit" 
                                                           class="button button-small button-link-delete" 
                                                           value="<?php esc_attr_e('Void', 'fp-esperienze'); ?>"
                                                           onclick="return confirm('<?php esc_js_e('Are you sure you want to void this voucher?', 'fp-esperienze'); ?>')">
                                                </form>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </form>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="tablenav bottom">
                    <div class="tablenav-pages">
                        <span class="displaying-num"><?php printf(__('%d items', 'fp-esperienze'), $total_items); ?></span>
                        <span class="pagination-links">
                            <?php
                            $page_links = paginate_links([
                                'base' => add_query_arg('paged', '%#%'),
                                'format' => '',
                                'prev_text' => '&laquo;',
                                'next_text' => '&raquo;',
                                'total' => $total_pages,
                                'current' => $current_page,
                                'type' => 'array'
                            ]);
                            
                            if ($page_links) {
                                echo implode("\n", $page_links);
                            }
                            ?>
                        </span>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- JavaScript for enhanced UX -->
        <script>
        jQuery(document).ready(function($) {
            // Handle "select all" checkbox
            $('#cb-select-all-1').click(function() {
                $('input[name="voucher_ids[]"]').prop('checked', this.checked);
            });
            
            // Show/hide bulk extend options
            $('#bulk-action-selector-top').change(function() {
                if ($(this).val() === 'bulk_extend') {
                    $('#bulk-extend-options').show();
                } else {
                    $('#bulk-extend-options').hide();
                }
            });
            
            // Copy PDF link functionality
            $('.fp-copy-pdf-link').click(function() {
                var voucherId = $(this).data('voucher-id');
                var downloadUrl = '<?php echo admin_url('admin.php?page=fp-esperienze-vouchers&action=download_pdf&voucher_id='); ?>' + voucherId;
                
                // Copy to clipboard
                navigator.clipboard.writeText(downloadUrl).then(function() {
                    alert('<?php esc_js_e('PDF link copied to clipboard!', 'fp-esperienze'); ?>');
                }).catch(function() {
                    // Fallback for older browsers
                    var textArea = document.createElement('textarea');
                    textArea.value = downloadUrl;
                    document.body.appendChild(textArea);
                    textArea.select();
                    document.execCommand('copy');
                    document.body.removeChild(textArea);
                    alert('<?php esc_js_e('PDF link copied to clipboard!', 'fp-esperienze'); ?>');
                });
            });
        });
        
        function confirmBulkAction() {
            var action = document.getElementById('bulk-action-selector-top').value;
            var selectedItems = document.querySelectorAll('input[name="voucher_ids[]"]:checked').length;
            
            if (!action) {
                alert('<?php esc_js_e('Please select an action.', 'fp-esperienze'); ?>');
                return false;
            }
            
            if (selectedItems === 0) {
                alert('<?php esc_js_e('Please select at least one voucher.', 'fp-esperienze'); ?>');
                return false;
            }
            
            var message = '';
            switch (action) {
                case 'bulk_void':
                    message = '<?php esc_js_e('Are you sure you want to void the selected vouchers?', 'fp-esperienze'); ?>';
                    break;
                case 'bulk_resend':
                    message = '<?php esc_js_e('Are you sure you want to resend emails for the selected vouchers?', 'fp-esperienze'); ?>';
                    break;
                case 'bulk_extend':
                    var months = document.querySelector('input[name="bulk_extend_months"]').value;
                    message = '<?php esc_js_e('Are you sure you want to extend the selected vouchers by', 'fp-esperienze'); ?>' + ' ' + months + ' <?php esc_js_e('months?', 'fp-esperienze'); ?>';
                    break;
            }
            
            return confirm(message);
        }
        </script>
        <?php
    }
    
    /**
     * Handle voucher actions
     */
    private function handleVoucherActions(): void {
        // Check permissions first
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('You do not have permission to perform this action.', 'fp-esperienze'));
        }
        
        // Handle bulk actions
        if (isset($_POST['bulk_action']) && !empty($_POST['voucher_ids'])) {
            $this->handleBulkVoucherActions();
            return;
        }
        
        // Handle individual actions
        if (!wp_verify_nonce($_POST['fp_voucher_nonce'] ?? '', 'fp_voucher_action')) {
            return;
        }
        
        $action = sanitize_text_field($_POST['action'] ?? '');
        $voucher_id = absint($_POST['voucher_id'] ?? 0);
        
        switch ($action) {
            case 'void_voucher':
                $this->voidVoucher($voucher_id);
                break;
            case 'resend_voucher':
                $this->resendVoucherEmail($voucher_id);
                break;
            case 'extend_voucher':
                $extend_months = absint($_POST['extend_months'] ?? 0);
                $this->extendVoucher($voucher_id, $extend_months);
                break;
        }
        
        // Handle GET actions (PDF download, copy link)
        if (isset($_GET['action'])) {
            $get_action = sanitize_text_field($_GET['action']);
            $voucher_id = absint($_GET['voucher_id'] ?? 0);
            
            switch ($get_action) {
                case 'download_pdf':
                    if ($voucher_id) {
                        $this->downloadVoucherPdf($voucher_id);
                    }
                    break;
                case 'copy_pdf_link':
                    if ($voucher_id) {
                        $this->copyPdfLink($voucher_id);
                    }
                    break;
            }
        }
    }
    
    /**
     * Void a voucher
     */
    private function voidVoucher($voucher_id): void {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'fp_exp_vouchers';
        
        $result = $wpdb->update(
            $table_name,
            ['status' => 'void'],
            ['id' => $voucher_id]
        );
        
        if ($result !== false) {
            // Log the action
            $this->logVoucherAction($voucher_id, 'void', 'Voucher voided by admin');
            
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success is-dismissible"><p>' . 
                     esc_html__('Voucher voided successfully.', 'fp-esperienze') . 
                     '</p></div>';
            });
        } else {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error is-dismissible"><p>' . 
                     esc_html__('Failed to void voucher.', 'fp-esperienze') . 
                     '</p></div>';
            });
        }
    }
    
    /**
     * Resend voucher email
     */
    private function resendVoucherEmail($voucher_id): void {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'fp_exp_vouchers';
        
        $voucher = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $voucher_id
        ), ARRAY_A);
        
        if (!$voucher) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error is-dismissible"><p>' . 
                     esc_html__('Voucher not found.', 'fp-esperienze') . 
                     '</p></div>';
            });
            return;
        }
        
        // Get order
        $order = wc_get_order($voucher['order_id']);
        if (!$order) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error is-dismissible"><p>' . 
                     esc_html__('Associated order not found.', 'fp-esperienze') . 
                     '</p></div>';
            });
            return;
        }
        
        try {
            // Regenerate PDF if it doesn't exist
            $pdf_path = $voucher['pdf_path'];
            if (empty($pdf_path) || !file_exists($pdf_path)) {
                $pdf_path = \FP\Esperienze\PDF\Voucher_Pdf::generate($voucher);
                
                // Update voucher with new PDF path
                $wpdb->update(
                    $table_name,
                    ['pdf_path' => $pdf_path],
                    ['id' => $voucher_id]
                );
            }
            
            // Send email using VoucherManager
            $voucher_manager = new \FP\Esperienze\Data\VoucherManager();
            $reflection = new ReflectionClass($voucher_manager);
            $method = $reflection->getMethod('sendVoucherEmail');
            $method->setAccessible(true);
            $method->invoke($voucher_manager, $voucher, $pdf_path, $order);
            
            // Update sent timestamp
            $wpdb->update(
                $table_name,
                ['sent_at' => current_time('mysql')],
                ['id' => $voucher_id]
            );
            
            // Log the action
            $this->logVoucherAction($voucher_id, 'resend', 'Email resent by admin');
            
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success is-dismissible"><p>' . 
                     esc_html__('Voucher email resent successfully.', 'fp-esperienze') . 
                     '</p></div>';
            });
            
        } catch (Exception $e) {
            error_log('FP Esperienze: Failed to resend voucher email: ' . $e->getMessage());
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error is-dismissible"><p>' . 
                     esc_html__('Failed to resend voucher email.', 'fp-esperienze') . 
                     '</p></div>';
            });
        }
    }
    
    /**
     * Extend voucher expiration
     */
    private function extendVoucher($voucher_id, $extend_months): void {
        global $wpdb;
        
        if ($extend_months <= 0) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error is-dismissible"><p>' . 
                     esc_html__('Invalid extension period.', 'fp-esperienze') . 
                     '</p></div>';
            });
            return;
        }
        
        $table_name = $wpdb->prefix . 'fp_exp_vouchers';
        
        // Get current voucher
        $voucher = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $voucher_id
        ));
        
        if (!$voucher) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error is-dismissible"><p>' . 
                     esc_html__('Voucher not found.', 'fp-esperienze') . 
                     '</p></div>';
            });
            return;
        }
        
        // Calculate new expiration date
        $current_expiry = strtotime($voucher->expires_on);
        $new_expiry = strtotime("+{$extend_months} months", $current_expiry);
        $new_expiry_date = date('Y-m-d', $new_expiry);
        
        $result = $wpdb->update(
            $table_name,
            ['expires_on' => $new_expiry_date],
            ['id' => $voucher_id]
        );
        
        if ($result !== false) {
            // Log the action
            $this->logVoucherAction($voucher_id, 'extend', "Extended by {$extend_months} months to {$new_expiry_date}");
            
            add_action('admin_notices', function() use ($new_expiry_date) {
                echo '<div class="notice notice-success is-dismissible"><p>' . 
                     sprintf(esc_html__('Voucher expiration extended to %s.', 'fp-esperienze'), date_i18n(get_option('date_format'), strtotime($new_expiry_date))) . 
                     '</p></div>';
            });
        } else {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error is-dismissible"><p>' . 
                     esc_html__('Failed to extend voucher expiration.', 'fp-esperienze') . 
                     '</p></div>';
            });
        }
    }
    
    /**
     * Handle bulk voucher actions
     */
    private function handleBulkVoucherActions(): void {
        if (!wp_verify_nonce($_POST['bulk_nonce'] ?? '', 'bulk_voucher_action')) {
            return;
        }
        
        $action = sanitize_text_field($_POST['bulk_action']);
        $voucher_ids = array_map('absint', $_POST['voucher_ids']);
        
        if (empty($voucher_ids)) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error is-dismissible"><p>' . 
                     esc_html__('No vouchers selected.', 'fp-esperienze') . 
                     '</p></div>';
            });
            return;
        }
        
        $processed = 0;
        $failed = 0;
        
        switch ($action) {
            case 'bulk_void':
                foreach ($voucher_ids as $voucher_id) {
                    try {
                        $this->voidVoucher($voucher_id);
                        $processed++;
                    } catch (Exception $e) {
                        $failed++;
                    }
                }
                break;
                
            case 'bulk_resend':
                foreach ($voucher_ids as $voucher_id) {
                    try {
                        $this->resendVoucherEmail($voucher_id);
                        $processed++;
                    } catch (Exception $e) {
                        $failed++;
                    }
                }
                break;
                
            case 'bulk_extend':
                $extend_months = absint($_POST['bulk_extend_months'] ?? 0);
                if ($extend_months > 0) {
                    foreach ($voucher_ids as $voucher_id) {
                        try {
                            $this->extendVoucher($voucher_id, $extend_months);
                            $processed++;
                        } catch (Exception $e) {
                            $failed++;
                        }
                    }
                }
                break;
        }
        
        if ($processed > 0) {
            add_action('admin_notices', function() use ($processed, $action) {
                $message = '';
                switch ($action) {
                    case 'bulk_void':
                        $message = sprintf(_n('%d voucher voided.', '%d vouchers voided.', $processed, 'fp-esperienze'), $processed);
                        break;
                    case 'bulk_resend':
                        $message = sprintf(_n('%d voucher email resent.', '%d voucher emails resent.', $processed, 'fp-esperienze'), $processed);
                        break;
                    case 'bulk_extend':
                        $message = sprintf(_n('%d voucher extended.', '%d vouchers extended.', $processed, 'fp-esperienze'), $processed);
                        break;
                }
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($message) . '</p></div>';
            });
        }
        
        if ($failed > 0) {
            add_action('admin_notices', function() use ($failed) {
                echo '<div class="notice notice-error is-dismissible"><p>' . 
                     sprintf(esc_html__('%d vouchers failed to process.', 'fp-esperienze'), $failed) . 
                     '</p></div>';
            });
        }
    }
    
    /**
     * Copy PDF link
     */
    private function copyPdfLink($voucher_id): void {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'fp_exp_vouchers';
        
        $voucher = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $voucher_id
        ));
        
        if (!$voucher || empty($voucher->pdf_path) || !file_exists($voucher->pdf_path)) {
            wp_die(__('PDF not found.', 'fp-esperienze'));
        }
        
        // Return the PDF download URL as JSON for JavaScript to handle
        $download_url = admin_url('admin.php?page=fp-esperienze-vouchers&action=download_pdf&voucher_id=' . $voucher_id);
        
        wp_send_json_success(['url' => $download_url]);
    }
    
    /**
     * Log voucher action for audit trail
     */
    private function logVoucherAction($voucher_id, $action, $description): void {
        global $wpdb;
        
        $current_user = wp_get_current_user();
        $user_info = $current_user->display_name . ' (' . $current_user->user_login . ')';
        
        // For now, we'll use WordPress's built-in logging
        // In a production environment, you might want a dedicated audit table
        error_log(sprintf(
            'FP Esperienze Voucher Action: ID=%d, Action=%s, User=%s, Description=%s',
            $voucher_id,
            $action,
            $user_info,
            $description
        ));
        
        // Also add to order notes if voucher is associated with an order
        $voucher = $wpdb->get_row($wpdb->prepare(
            "SELECT order_id FROM {$wpdb->prefix}fp_exp_vouchers WHERE id = %d",
            $voucher_id
        ));
        
        if ($voucher && $voucher->order_id) {
            $order = wc_get_order($voucher->order_id);
            if ($order) {
                $order->add_order_note(sprintf(
                    __('Voucher %s: %s by %s', 'fp-esperienze'),
                    $action,
                    $description,
                    $user_info
                ));
            }
        }
    }
    
    /**
     * Download voucher PDF
     */
    private function downloadVoucherPdf($voucher_id): void {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'fp_exp_vouchers';
        
        $voucher = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $voucher_id
        ));
        
        if (!$voucher || empty($voucher->pdf_path) || !file_exists($voucher->pdf_path)) {
            wp_die(__('PDF not found.', 'fp-esperienze'));
        }
        
        $filename = 'voucher-' . $voucher->code . '.pdf';
        
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($voucher->pdf_path));
        
        readfile($voucher->pdf_path);
        exit;
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
        // Handle form submissions
        if ($_POST && current_user_can('manage_options')) {
            $this->handleSettingsSubmission();
        }
        
        // Get current tab
        $current_tab = sanitize_text_field($_GET['tab'] ?? 'gift');
        
        // Get current settings
        $gift_exp_months = get_option('fp_esperienze_gift_default_exp_months', 12);
        $gift_logo = get_option('fp_esperienze_gift_pdf_logo', '');
        $gift_brand_color = get_option('fp_esperienze_gift_pdf_brand_color', '#ff6b35');
        $gift_sender_name = get_option('fp_esperienze_gift_email_sender_name', get_bloginfo('name'));
        $gift_sender_email = get_option('fp_esperienze_gift_email_sender_email', get_option('admin_email'));
        $gift_terms = get_option('fp_esperienze_gift_terms', __('This voucher is valid for one experience booking. Please present the QR code when redeeming.', 'fp-esperienze'));
        $gift_secret = get_option('fp_esperienze_gift_secret_hmac', '');
        
        // Get integrations settings
        $integrations = get_option('fp_esperienze_integrations', []);
        $ga4_measurement_id = $integrations['ga4_measurement_id'] ?? '';
        $ga4_ecommerce = !empty($integrations['ga4_ecommerce']);
        $gads_conversion_id = $integrations['gads_conversion_id'] ?? '';
        $meta_pixel_id = $integrations['meta_pixel_id'] ?? '';
        $meta_capi_enabled = !empty($integrations['meta_capi_enabled']);
        $brevo_api_key = $integrations['brevo_api_key'] ?? '';
        $brevo_list_id_it = $integrations['brevo_list_id_it'] ?? '';
        $brevo_list_id_en = $integrations['brevo_list_id_en'] ?? '';
        $gplaces_api_key = $integrations['gplaces_api_key'] ?? '';
        $gplaces_reviews_enabled = !empty($integrations['gplaces_reviews_enabled']);
        $gplaces_reviews_limit = absint($integrations['gplaces_reviews_limit'] ?? 5);
        $gplaces_cache_ttl = absint($integrations['gplaces_cache_ttl'] ?? 60);
        $gbp_client_id = $integrations['gbp_client_id'] ?? '';
        $gbp_client_secret = $integrations['gbp_client_secret'] ?? '';
        
        // Consent Mode v2 settings
        $consent_mode_enabled = !empty($integrations['consent_mode_enabled']);
        $consent_cookie_name = $integrations['consent_cookie_name'] ?? 'marketing_consent';
        $consent_js_function = $integrations['consent_js_function'] ?? '';
        
        ?>
        <div class="wrap">
            <h1><?php _e('FP Esperienze Settings', 'fp-esperienze'); ?></h1>
            
            <h2 class="nav-tab-wrapper">
                <a href="<?php echo admin_url('admin.php?page=fp-esperienze-settings&tab=gift'); ?>" class="nav-tab <?php echo $current_tab === 'gift' ? 'nav-tab-active' : ''; ?>"><?php _e('Gift Vouchers', 'fp-esperienze'); ?></a>
                <a href="<?php echo admin_url('admin.php?page=fp-esperienze-settings&tab=integrations'); ?>" class="nav-tab <?php echo $current_tab === 'integrations' ? 'nav-tab-active' : ''; ?>"><?php _e('Integrations', 'fp-esperienze'); ?></a>
            </h2>
            
            <form method="post" action="">
                <?php wp_nonce_field('fp_settings_nonce', 'fp_settings_nonce'); ?>
                <input type="hidden" name="settings_tab" value="<?php echo esc_attr($current_tab); ?>" />
                
                <?php if ($current_tab === 'gift') : ?>
                <div class="tab-content">
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="gift_default_exp_months"><?php _e('Default Expiration (months)', 'fp-esperienze'); ?></label>
                            </th>
                            <td>
                                <input type="number" 
                                       id="gift_default_exp_months" 
                                       name="gift_default_exp_months" 
                                       value="<?php echo esc_attr($gift_exp_months); ?>" 
                                       min="1" 
                                       max="60" 
                                       class="small-text" />
                                <p class="description"><?php _e('How many months gift vouchers should be valid for by default.', 'fp-esperienze'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="gift_pdf_logo"><?php _e('PDF Logo URL', 'fp-esperienze'); ?></label>
                            </th>
                            <td>
                                <input type="url" 
                                       id="gift_pdf_logo" 
                                       name="gift_pdf_logo" 
                                       value="<?php echo esc_attr($gift_logo); ?>" 
                                       class="regular-text" />
                                <button type="button" class="button" onclick="selectMediaFile('gift_pdf_logo')"><?php _e('Select Image', 'fp-esperienze'); ?></button>
                                <p class="description"><?php _e('Logo to display on gift voucher PDFs.', 'fp-esperienze'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="gift_pdf_brand_color"><?php _e('Brand Color', 'fp-esperienze'); ?></label>
                            </th>
                            <td>
                                <input type="color" 
                                       id="gift_pdf_brand_color" 
                                       name="gift_pdf_brand_color" 
                                       value="<?php echo esc_attr($gift_brand_color); ?>" />
                                <p class="description"><?php _e('Primary color for gift voucher PDFs.', 'fp-esperienze'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="gift_email_sender_name"><?php _e('Email Sender Name', 'fp-esperienze'); ?></label>
                            </th>
                            <td>
                                <input type="text" 
                                       id="gift_email_sender_name" 
                                       name="gift_email_sender_name" 
                                       value="<?php echo esc_attr($gift_sender_name); ?>" 
                                       class="regular-text" />
                                <p class="description"><?php _e('Name used in the "From" field of gift voucher emails.', 'fp-esperienze'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="gift_email_sender_email"><?php _e('Email Sender Address', 'fp-esperienze'); ?></label>
                            </th>
                            <td>
                                <input type="email" 
                                       id="gift_email_sender_email" 
                                       name="gift_email_sender_email" 
                                       value="<?php echo esc_attr($gift_sender_email); ?>" 
                                       class="regular-text" />
                                <p class="description"><?php _e('Email address used in the "From" field of gift voucher emails.', 'fp-esperienze'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="gift_terms"><?php _e('Terms & Conditions', 'fp-esperienze'); ?></label>
                            </th>
                            <td>
                                <textarea id="gift_terms" 
                                          name="gift_terms" 
                                          rows="4" 
                                          class="large-text"><?php echo esc_textarea($gift_terms); ?></textarea>
                                <p class="description"><?php _e('Terms and conditions text displayed on gift voucher PDFs.', 'fp-esperienze'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="gift_secret_hmac"><?php _e('HMAC Secret Key', 'fp-esperienze'); ?></label>
                            </th>
                            <td>
                                <code style="background: #f1f1f1; padding: 8px; display: block; margin-bottom: 8px; word-break: break-all;"><?php echo esc_html(substr($gift_secret, 0, 10) . '...' . substr($gift_secret, -10)); ?></code>
                                <button type="button" 
                                        class="button" 
                                        onclick="if(confirm('<?php esc_js_e('Are you sure? This will invalidate all existing QR codes!', 'fp-esperienze'); ?>')) { document.getElementById('regenerate_secret').value = '1'; }"><?php _e('Regenerate Secret', 'fp-esperienze'); ?></button>
                                <input type="hidden" id="regenerate_secret" name="regenerate_secret" value="0" />
                                <p class="description"><?php _e('Secret key used to sign QR codes for security. Regenerating will invalidate existing QR codes!', 'fp-esperienze'); ?></p>
                            </td>
                        </tr>
                    </table>
                    
                    <?php submit_button(__('Save Settings', 'fp-esperienze')); ?>
                </div>
                <?php endif; ?>
                
                <?php if ($current_tab === 'integrations') : ?>
                <div class="tab-content">
                    <table class="form-table">
                        <!-- GA4 Section -->
                        <tr>
                            <th colspan="2"><h3><?php _e('Google Analytics 4', 'fp-esperienze'); ?></h3></th>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="ga4_measurement_id"><?php _e('Measurement ID', 'fp-esperienze'); ?></label>
                            </th>
                            <td>
                                <input type="text" 
                                       id="ga4_measurement_id" 
                                       name="ga4_measurement_id" 
                                       value="<?php echo esc_attr($ga4_measurement_id); ?>" 
                                       placeholder="G-XXXXXXXXXX"
                                       class="regular-text" />
                                <p class="description"><?php _e('Your Google Analytics 4 Measurement ID (starts with G-).', 'fp-esperienze'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="ga4_ecommerce"><?php _e('Enhanced eCommerce', 'fp-esperienze'); ?></label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" 
                                           id="ga4_ecommerce" 
                                           name="ga4_ecommerce" 
                                           value="1" 
                                           <?php checked($ga4_ecommerce); ?> />
                                    <?php _e('Enable enhanced eCommerce tracking (recommended)', 'fp-esperienze'); ?>
                                </label>
                                <p class="description"><?php _e('Track purchase events and conversion data for better analytics.', 'fp-esperienze'); ?></p>
                            </td>
                        </tr>
                        
                        <!-- Google Ads Section -->
                        <tr>
                            <th colspan="2"><h3><?php _e('Google Ads', 'fp-esperienze'); ?></h3></th>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="gads_conversion_id"><?php _e('Conversion ID', 'fp-esperienze'); ?></label>
                            </th>
                            <td>
                                <input type="text" 
                                       id="gads_conversion_id" 
                                       name="gads_conversion_id" 
                                       value="<?php echo esc_attr($gads_conversion_id); ?>" 
                                       placeholder="AW-XXXXXXXXXX"
                                       class="regular-text" />
                                <p class="description"><?php _e('Your Google Ads Conversion ID (starts with AW-). Configure conversion actions in Google Ads dashboard.', 'fp-esperienze'); ?></p>
                            </td>
                        </tr>
                        
                        <!-- Meta Pixel Section -->
                        <tr>
                            <th colspan="2"><h3><?php _e('Meta Pixel (Facebook)', 'fp-esperienze'); ?></h3></th>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="meta_pixel_id"><?php _e('Pixel ID', 'fp-esperienze'); ?></label>
                            </th>
                            <td>
                                <input type="text" 
                                       id="meta_pixel_id" 
                                       name="meta_pixel_id" 
                                       value="<?php echo esc_attr($meta_pixel_id); ?>" 
                                       placeholder="123456789012345"
                                       class="regular-text" />
                                <p class="description"><?php _e('Your Meta (Facebook) Pixel ID number.', 'fp-esperienze'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="meta_capi_enabled"><?php _e('Conversions API', 'fp-esperienze'); ?></label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" 
                                           id="meta_capi_enabled" 
                                           name="meta_capi_enabled" 
                                           value="1" 
                                           <?php checked($meta_capi_enabled); ?> />
                                    <?php _e('Enable Conversions API and event deduplication (placeholder)', 'fp-esperienze'); ?>
                                </label>
                                <p class="description"><?php _e('Advanced feature for server-side tracking and better data accuracy. Implementation coming in future version.', 'fp-esperienze'); ?></p>
                            </td>
                        </tr>
                        
                        <!-- Consent Mode v2 Section -->
                        <tr>
                            <th colspan="2"><h3><?php _e('Consent Mode v2', 'fp-esperienze'); ?></h3></th>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="consent_mode_enabled"><?php _e('Enable Consent Mode', 'fp-esperienze'); ?></label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" 
                                           id="consent_mode_enabled" 
                                           name="consent_mode_enabled" 
                                           value="1" 
                                           <?php checked($consent_mode_enabled); ?> />
                                    <?php _e('Use Consent Mode v2 for tracking compliance', 'fp-esperienze'); ?>
                                </label>
                                <p class="description"><?php _e('When enabled, GA4 and Meta Pixel events only fire if marketing consent is granted. Requires integration with a Consent Management Platform (CMP).', 'fp-esperienze'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="consent_cookie_name"><?php _e('Consent Cookie Name', 'fp-esperienze'); ?></label>
                            </th>
                            <td>
                                <input type="text" 
                                       id="consent_cookie_name" 
                                       name="consent_cookie_name" 
                                       value="<?php echo esc_attr($consent_cookie_name); ?>" 
                                       placeholder="marketing_consent"
                                       class="regular-text" />
                                <p class="description"><?php _e('Name of the cookie that stores marketing consent status (should contain "true" or "1" for granted).', 'fp-esperienze'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="consent_js_function"><?php _e('Consent JavaScript Function', 'fp-esperienze'); ?></label>
                            </th>
                            <td>
                                <input type="text" 
                                       id="consent_js_function" 
                                       name="consent_js_function" 
                                       value="<?php echo esc_attr($consent_js_function); ?>" 
                                       placeholder="window.myCMP.getMarketingConsent"
                                       class="regular-text" />
                                <p class="description"><?php _e('Optional: JavaScript function path that returns boolean consent status. Use either this OR cookie name, not both.', 'fp-esperienze'); ?></p>
                            </td>
                        </tr>
                        
                        <!-- Brevo Section -->
                        <tr>
                            <th colspan="2"><h3><?php _e('Brevo (Email Marketing)', 'fp-esperienze'); ?></h3></th>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="brevo_api_key"><?php _e('API Key v3', 'fp-esperienze'); ?></label>
                            </th>
                            <td>
                                <input type="password" 
                                       id="brevo_api_key" 
                                       name="brevo_api_key" 
                                       value="<?php echo esc_attr($brevo_api_key); ?>" 
                                       class="regular-text" />
                                <p class="description"><?php _e('Your Brevo API key v3 for email list management.', 'fp-esperienze'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="brevo_list_id_it"><?php _e('List ID (Italian)', 'fp-esperienze'); ?></label>
                            </th>
                            <td>
                                <input type="number" 
                                       id="brevo_list_id_it" 
                                       name="brevo_list_id_it" 
                                       value="<?php echo esc_attr($brevo_list_id_it); ?>" 
                                       class="small-text" />
                                <p class="description"><?php _e('Brevo list ID for Italian customers.', 'fp-esperienze'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="brevo_list_id_en"><?php _e('List ID (English)', 'fp-esperienze'); ?></label>
                            </th>
                            <td>
                                <input type="number" 
                                       id="brevo_list_id_en" 
                                       name="brevo_list_id_en" 
                                       value="<?php echo esc_attr($brevo_list_id_en); ?>" 
                                       class="small-text" />
                                <p class="description"><?php _e('Brevo list ID for English customers.', 'fp-esperienze'); ?></p>
                            </td>
                        </tr>
                        
                        <!-- Google Places Section -->
                        <tr>
                            <th colspan="2"><h3><?php _e('Google Places API', 'fp-esperienze'); ?></h3></th>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="gplaces_api_key"><?php _e('API Key', 'fp-esperienze'); ?></label>
                            </th>
                            <td>
                                <input type="text" 
                                       id="gplaces_api_key" 
                                       name="gplaces_api_key" 
                                       value="<?php echo esc_attr($gplaces_api_key); ?>" 
                                       class="regular-text" />
                                <p class="description"><?php _e('Google Places API key for retrieving reviews and location data.', 'fp-esperienze'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="gplaces_reviews_enabled"><?php _e('Display Reviews', 'fp-esperienze'); ?></label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" 
                                           id="gplaces_reviews_enabled" 
                                           name="gplaces_reviews_enabled" 
                                           value="1" 
                                           <?php checked($gplaces_reviews_enabled); ?> />
                                    <?php _e('Show Google reviews on Meeting Point pages', 'fp-esperienze'); ?>
                                </label>
                                <p class="description"><?php _e('Display Google reviews for meeting points when available.', 'fp-esperienze'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="gplaces_reviews_limit"><?php _e('Reviews Limit', 'fp-esperienze'); ?></label>
                            </th>
                            <td>
                                <input type="number" 
                                       id="gplaces_reviews_limit" 
                                       name="gplaces_reviews_limit" 
                                       value="<?php echo esc_attr($gplaces_reviews_limit); ?>" 
                                       min="1" 
                                       max="10" 
                                       class="small-text" />
                                <p class="description"><?php _e('Maximum number of reviews to display (1-10).', 'fp-esperienze'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="gplaces_cache_ttl"><?php _e('Cache TTL (minutes)', 'fp-esperienze'); ?></label>
                            </th>
                            <td>
                                <input type="number" 
                                       id="gplaces_cache_ttl" 
                                       name="gplaces_cache_ttl" 
                                       value="<?php echo esc_attr($gplaces_cache_ttl); ?>" 
                                       min="5" 
                                       max="1440" 
                                       class="small-text" />
                                <p class="description"><?php _e('How long to cache Google Places data (5-1440 minutes).', 'fp-esperienze'); ?></p>
                            </td>
                        </tr>
                        
                        <!-- Google Business Profile Section -->
                        <tr>
                            <th colspan="2"><h3><?php _e('Google Business Profile API (Optional)', 'fp-esperienze'); ?></h3></th>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="gbp_client_id"><?php _e('OAuth Client ID', 'fp-esperienze'); ?></label>
                            </th>
                            <td>
                                <input type="text" 
                                       id="gbp_client_id" 
                                       name="gbp_client_id" 
                                       value="<?php echo esc_attr($gbp_client_id); ?>" 
                                       class="regular-text" 
                                       placeholder="<?php esc_attr_e('Coming soon - OAuth integration', 'fp-esperienze'); ?>" 
                                       disabled />
                                <p class="description"><?php _e('Google OAuth Client ID for Business Profile access (placeholder for future implementation).', 'fp-esperienze'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="gbp_client_secret"><?php _e('OAuth Client Secret', 'fp-esperienze'); ?></label>
                            </th>
                            <td>
                                <input type="password" 
                                       id="gbp_client_secret" 
                                       name="gbp_client_secret" 
                                       value="<?php echo esc_attr($gbp_client_secret); ?>" 
                                       class="regular-text" 
                                       placeholder="<?php esc_attr_e('Coming soon - OAuth integration', 'fp-esperienze'); ?>" 
                                       disabled />
                                <p class="description"><?php _e('Google OAuth Client Secret (keep secure) - placeholder for future implementation.', 'fp-esperienze'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label><?php _e('Requirements', 'fp-esperienze'); ?></label>
                            </th>
                            <td>
                                <p class="description">
                                    <strong><?php _e('Note:', 'fp-esperienze'); ?></strong> 
                                    <?php _e('You must be the verified owner of the Google Business Profile to use this feature. OAuth integration will be implemented in a future version.', 'fp-esperienze'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                    
                    <?php submit_button(__('Save Integrations', 'fp-esperienze')); ?>
                </div>
                <?php endif; ?>
            </form>
        </div>
        
        <script>
        function selectMediaFile(inputId) {
            var frame = wp.media({
                title: '<?php esc_js_e('Select Logo', 'fp-esperienze'); ?>',
                button: {
                    text: '<?php esc_js_e('Use This Image', 'fp-esperienze'); ?>'
                },
                multiple: false
            });
            
            frame.on('select', function() {
                var attachment = frame.state().get('selection').first().toJSON();
                document.getElementById(inputId).value = attachment.url;
            });
            
            frame.open();
        }
        </script>
        <?php
    }
    
    /**
     * Handle settings form submission
     */
    private function handleSettingsSubmission(): void {
        if (!wp_verify_nonce($_POST['fp_settings_nonce'] ?? '', 'fp_settings_nonce')) {
            wp_die(__('Security check failed.', 'fp-esperienze'));
        }
        
        $tab = sanitize_text_field($_POST['settings_tab'] ?? 'gift');
        
        if ($tab === 'gift') {
            // Update gift settings
            $settings = [
                'fp_esperienze_gift_default_exp_months' => absint($_POST['gift_default_exp_months'] ?? 12),
                'fp_esperienze_gift_pdf_logo' => esc_url_raw($_POST['gift_pdf_logo'] ?? ''),
                'fp_esperienze_gift_pdf_brand_color' => sanitize_hex_color($_POST['gift_pdf_brand_color'] ?? '#ff6b35'),
                'fp_esperienze_gift_email_sender_name' => sanitize_text_field($_POST['gift_email_sender_name'] ?? ''),
                'fp_esperienze_gift_email_sender_email' => sanitize_email($_POST['gift_email_sender_email'] ?? ''),
                'fp_esperienze_gift_terms' => sanitize_textarea_field($_POST['gift_terms'] ?? ''),
            ];
            
            foreach ($settings as $key => $value) {
                update_option($key, $value);
            }
            
            // Regenerate HMAC secret if requested
            if (!empty($_POST['regenerate_secret'])) {
                update_option('fp_esperienze_gift_secret_hmac', wp_generate_password(32, false));
            }
            
        } elseif ($tab === 'integrations') {
            // Update integrations settings
            $integrations = [
                'ga4_measurement_id' => sanitize_text_field($_POST['ga4_measurement_id'] ?? ''),
                'ga4_ecommerce' => !empty($_POST['ga4_ecommerce']),
                'gads_conversion_id' => sanitize_text_field($_POST['gads_conversion_id'] ?? ''),
                'meta_pixel_id' => sanitize_text_field($_POST['meta_pixel_id'] ?? ''),
                'meta_capi_enabled' => !empty($_POST['meta_capi_enabled']),
                'brevo_api_key' => sanitize_text_field($_POST['brevo_api_key'] ?? ''),
                'brevo_list_id_it' => absint($_POST['brevo_list_id_it'] ?? 0),
                'brevo_list_id_en' => absint($_POST['brevo_list_id_en'] ?? 0),
                'gplaces_api_key' => sanitize_text_field($_POST['gplaces_api_key'] ?? ''),
                'gplaces_reviews_enabled' => !empty($_POST['gplaces_reviews_enabled']),
                'gplaces_reviews_limit' => max(1, min(10, absint($_POST['gplaces_reviews_limit'] ?? 5))),
                'gplaces_cache_ttl' => max(5, min(1440, absint($_POST['gplaces_cache_ttl'] ?? 60))),
                'gbp_client_id' => sanitize_text_field($_POST['gbp_client_id'] ?? ''),
                'gbp_client_secret' => sanitize_text_field($_POST['gbp_client_secret'] ?? ''),
                // Consent Mode v2 settings
                'consent_mode_enabled' => !empty($_POST['consent_mode_enabled']),
                'consent_cookie_name' => sanitize_text_field($_POST['consent_cookie_name'] ?? 'marketing_consent'),
                'consent_js_function' => sanitize_text_field($_POST['consent_js_function'] ?? ''),
            ];
            
            // Store all integrations in a single option
            update_option('fp_esperienze_integrations', $integrations);
        }
        
        add_action('admin_notices', function() {
            echo '<div class="notice notice-success is-dismissible"><p>' . 
                 esc_html__('Settings saved successfully!', 'fp-esperienze') . 
                 '</p></div>';
        });
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