<?php
/**
 * Admin Menu Manager
 *
 * @package FP\Esperienze\Admin
 */

namespace FP\Esperienze\Admin;

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
        // Handle form submission
        if (isset($_POST['action']) && $_POST['action'] === 'add_closure' && wp_verify_nonce($_POST['_wpnonce'], 'fp_add_closure')) {
            $this->handleAddClosure();
        }
        
        if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id']) && wp_verify_nonce($_GET['_wpnonce'], 'fp_delete_closure_' . $_GET['id'])) {
            $this->handleDeleteClosure(intval($_GET['id']));
        }
        
        ?>
        <div class="wrap">
            <h1><?php _e('Global Closures Management', 'fp-esperienze'); ?></h1>
            <p><?php _e('Manage dates when ALL experiences are closed globally.', 'fp-esperienze'); ?></p>
            
            <!-- Add Closure Form -->
            <div class="postbox" style="margin-top: 20px;">
                <h2 class="hndle"><span><?php _e('Add Global Closure', 'fp-esperienze'); ?></span></h2>
                <div class="inside">
                    <form method="post" action="">
                        <?php wp_nonce_field('fp_add_closure'); ?>
                        <input type="hidden" name="action" value="add_closure" />
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="closure_date"><?php _e('Date', 'fp-esperienze'); ?></label>
                                </th>
                                <td>
                                    <input type="date" id="closure_date" name="closure_date" required />
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="closure_reason"><?php _e('Reason', 'fp-esperienze'); ?></label>
                                </th>
                                <td>
                                    <input type="text" id="closure_reason" name="closure_reason" 
                                           class="regular-text" placeholder="<?php _e('Holiday, maintenance, etc.', 'fp-esperienze'); ?>" />
                                </td>
                            </tr>
                        </table>
                        
                        <?php submit_button(__('Add Closure', 'fp-esperienze')); ?>
                    </form>
                </div>
            </div>
            
            <!-- Closures List -->
            <div class="postbox" style="margin-top: 20px;">
                <h2 class="hndle"><span><?php _e('Current Global Closures', 'fp-esperienze'); ?></span></h2>
                <div class="inside">
                    <?php $this->renderClosuresList(); ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Handle add closure form submission
     */
    private function handleAddClosure(): void {
        $date = sanitize_text_field($_POST['closure_date']);
        $reason = sanitize_text_field($_POST['closure_reason']);
        
        if (empty($date)) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>' . __('Date is required.', 'fp-esperienze') . '</p></div>';
            });
            return;
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'fp_overrides';
        
        $result = $wpdb->insert($table, [
            'product_id' => 0, // 0 means global closure
            'date' => $date,
            'is_closed' => 1,
            'reason' => $reason,
            'created_at' => current_time('mysql')
        ]);
        
        if ($result) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success"><p>' . __('Global closure added successfully.', 'fp-esperienze') . '</p></div>';
            });
        } else {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>' . __('Failed to add closure. It may already exist.', 'fp-esperienze') . '</p></div>';
            });
        }
    }

    /**
     * Handle delete closure
     */
    private function handleDeleteClosure(int $id): void {
        global $wpdb;
        $table = $wpdb->prefix . 'fp_overrides';
        
        $result = $wpdb->delete($table, [
            'id' => $id,
            'product_id' => 0 // Only allow deleting global closures
        ]);
        
        if ($result) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success"><p>' . __('Global closure deleted successfully.', 'fp-esperienze') . '</p></div>';
            });
        } else {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>' . __('Failed to delete closure.', 'fp-esperienze') . '</p></div>';
            });
        }
    }

    /**
     * Render closures list
     */
    private function renderClosuresList(): void {
        global $wpdb;
        $table = $wpdb->prefix . 'fp_overrides';
        
        $closures = $wpdb->get_results(
            "SELECT * FROM $table WHERE product_id = 0 AND is_closed = 1 ORDER BY date ASC"
        );
        
        if (empty($closures)) {
            echo '<p>' . __('No global closures configured.', 'fp-esperienze') . '</p>';
            return;
        }
        
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Date', 'fp-esperienze'); ?></th>
                    <th><?php _e('Reason', 'fp-esperienze'); ?></th>
                    <th><?php _e('Created', 'fp-esperienze'); ?></th>
                    <th><?php _e('Actions', 'fp-esperienze'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($closures as $closure): ?>
                    <tr>
                        <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($closure->date))); ?></td>
                        <td><?php echo esc_html($closure->reason ?: __('No reason specified', 'fp-esperienze')); ?></td>
                        <td><?php echo esc_html(date_i18n(get_option('datetime_format'), strtotime($closure->created_at))); ?></td>
                        <td>
                            <a href="<?php echo wp_nonce_url(
                                admin_url('admin.php?page=fp-esperienze-closures&action=delete&id=' . $closure->id),
                                'fp_delete_closure_' . $closure->id
                            ); ?>" 
                               class="button button-small"
                               onclick="return confirm('<?php _e('Are you sure you want to delete this closure?', 'fp-esperienze'); ?>')">
                                <?php _e('Delete', 'fp-esperienze'); ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
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