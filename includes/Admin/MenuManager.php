<?php
/**
 * Admin Menu Manager
 *
 * @package FP\Esperienze\Admin
 */

namespace FP\Esperienze\Admin;

use FP\Esperienze\Data\OverrideManager;
use FP\Esperienze\Data\MeetingPointManager;

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
        ?>
        <div class="wrap">
            <h1><?php _e('Bookings Management', 'fp-esperienze'); ?></h1>
            <p><?php _e('Booking management functionality will be implemented in future updates.', 'fp-esperienze'); ?></p>
        </div>
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
        ?>
        <div class="wrap">
            <h1><?php _e('Extras Management', 'fp-esperienze'); ?></h1>
            <p><?php _e('Extras management functionality will be implemented in future updates.', 'fp-esperienze'); ?></p>
        </div>
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
}