<?php
/**
 * Admin Menu Manager
 *
 * @package FP\Esperienze\Admin
 */

namespace FP\Esperienze\Admin;

use FP\Esperienze\Data\OverrideManager;

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
        ?>
        <div class="wrap">
            <h1><?php _e('Meeting Points', 'fp-esperienze'); ?></h1>
            <p><?php _e('Meeting points management will be implemented in future updates.', 'fp-esperienze'); ?></p>
        </div>
        <?php
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