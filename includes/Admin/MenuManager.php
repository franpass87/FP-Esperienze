<?php
/**
 * Admin Menu Manager
 *
 * @package FP\Esperienze\Admin
 */

namespace FP\Esperienze\Admin;

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
        // Handle form submissions
        if (isset($_POST['action'])) {
            $this->handleExtrasFormSubmission();
        }
        
        // Get current action
        $action = $_GET['action'] ?? 'list';
        $extra_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        
        switch ($action) {
            case 'add':
                $this->renderExtrasForm();
                break;
            case 'edit':
                $this->renderExtrasForm($extra_id);
                break;
            case 'delete':
                $this->handleExtraDelete($extra_id);
                $this->renderExtrasList();
                break;
            default:
                $this->renderExtrasList();
                break;
        }
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
        ?>
        <div class="wrap">
            <h1><?php _e('Closures Management', 'fp-esperienze'); ?></h1>
            <p><?php _e('Closure management functionality will be implemented in future updates.', 'fp-esperienze'); ?></p>
        </div>
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

    /**
     * Handle extras form submission
     */
    private function handleExtrasFormSubmission(): void {
        // Verify nonce
        if (!isset($_POST['fp_extras_nonce']) || !wp_verify_nonce($_POST['fp_extras_nonce'], 'fp_extras_form')) {
            wp_die(__('Security check failed', 'fp-esperienze'));
        }

        // Check capabilities
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Insufficient permissions', 'fp-esperienze'));
        }

        $action = sanitize_text_field($_POST['action']);
        $extra_id = isset($_POST['extra_id']) ? intval($_POST['extra_id']) : 0;

        $data = [
            'name' => sanitize_text_field($_POST['name'] ?? ''),
            'description' => sanitize_textarea_field($_POST['description'] ?? ''),
            'price' => floatval($_POST['price'] ?? 0),
            'pricing_type' => sanitize_text_field($_POST['pricing_type'] ?? 'per_person'),
            'is_required' => isset($_POST['is_required']) ? 1 : 0,
            'max_quantity' => intval($_POST['max_quantity'] ?? 1),
            'tax_class' => sanitize_text_field($_POST['tax_class'] ?? ''),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ];

        if ($action === 'create_extra') {
            $result = ExtraManager::createExtra($data);
            if ($result) {
                $this->showNotice(__('Extra created successfully', 'fp-esperienze'), 'success');
            } else {
                $this->showNotice(__('Failed to create extra', 'fp-esperienze'), 'error');
            }
        } elseif ($action === 'update_extra' && $extra_id) {
            $result = ExtraManager::updateExtra($extra_id, $data);
            if ($result) {
                $this->showNotice(__('Extra updated successfully', 'fp-esperienze'), 'success');
            } else {
                $this->showNotice(__('Failed to update extra', 'fp-esperienze'), 'error');
            }
        }
    }

    /**
     * Handle extra deletion
     */
    private function handleExtraDelete(int $extra_id): void {
        // Verify nonce
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'delete_extra_' . $extra_id)) {
            wp_die(__('Security check failed', 'fp-esperienze'));
        }

        // Check capabilities
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Insufficient permissions', 'fp-esperienze'));
        }

        $result = ExtraManager::deleteExtra($extra_id);
        if ($result) {
            $this->showNotice(__('Extra deleted successfully', 'fp-esperienze'), 'success');
        } else {
            $this->showNotice(__('Failed to delete extra', 'fp-esperienze'), 'error');
        }
    }

    /**
     * Render extras list
     */
    private function renderExtrasList(): void {
        $extras = ExtraManager::getAllExtras();
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php _e('Extras Management', 'fp-esperienze'); ?></h1>
            <a href="<?php echo esc_url(admin_url('admin.php?page=fp-esperienze-extras&action=add')); ?>" class="page-title-action">
                <?php _e('Add New', 'fp-esperienze'); ?>
            </a>
            <hr class="wp-header-end">

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Name', 'fp-esperienze'); ?></th>
                        <th><?php _e('Price', 'fp-esperienze'); ?></th>
                        <th><?php _e('Type', 'fp-esperienze'); ?></th>
                        <th><?php _e('Max Qty', 'fp-esperienze'); ?></th>
                        <th><?php _e('Status', 'fp-esperienze'); ?></th>
                        <th><?php _e('Actions', 'fp-esperienze'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($extras)) : ?>
                        <tr>
                            <td colspan="6"><?php _e('No extras found.', 'fp-esperienze'); ?></td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($extras as $extra) : ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html($extra['name']); ?></strong>
                                    <?php if (!empty($extra['description'])) : ?>
                                        <div class="row-actions">
                                            <?php echo esc_html(wp_trim_words($extra['description'], 10)); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo wc_price($extra['price']); ?></td>
                                <td>
                                    <?php 
                                    echo $extra['pricing_type'] === 'per_person' 
                                        ? __('Per Person', 'fp-esperienze') 
                                        : __('Per Booking', 'fp-esperienze');
                                    ?>
                                </td>
                                <td><?php echo intval($extra['max_quantity']); ?></td>
                                <td>
                                    <?php if ($extra['is_active']) : ?>
                                        <span class="dashicons dashicons-yes-alt" style="color: green;"></span>
                                        <?php _e('Active', 'fp-esperienze'); ?>
                                    <?php else : ?>
                                        <span class="dashicons dashicons-dismiss" style="color: red;"></span>
                                        <?php _e('Inactive', 'fp-esperienze'); ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=fp-esperienze-extras&action=edit&id=' . $extra['id'])); ?>" class="button button-small">
                                        <?php _e('Edit', 'fp-esperienze'); ?>
                                    </a>
                                    <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=fp-esperienze-extras&action=delete&id=' . $extra['id']), 'delete_extra_' . $extra['id'])); ?>" 
                                       class="button button-small" 
                                       onclick="return confirm('<?php esc_attr_e('Are you sure you want to delete this extra?', 'fp-esperienze'); ?>')">
                                        <?php _e('Delete', 'fp-esperienze'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Render extras form
     */
    private function renderExtrasForm(int $extra_id = 0): void {
        $extra = null;
        $is_edit = $extra_id > 0;
        
        if ($is_edit) {
            $extra = ExtraManager::getExtra($extra_id);
            if (!$extra) {
                $this->showNotice(__('Extra not found', 'fp-esperienze'), 'error');
                $this->renderExtrasList();
                return;
            }
        }

        // Get WooCommerce tax classes
        $tax_classes = WC_Tax::get_tax_classes();
        $tax_class_options = ['' => __('Standard', 'fp-esperienze')];
        foreach ($tax_classes as $class) {
            $tax_class_options[sanitize_title($class)] = esc_html($class);
        }
        ?>
        <div class="wrap">
            <h1>
                <?php echo $is_edit ? __('Edit Extra', 'fp-esperienze') : __('Add New Extra', 'fp-esperienze'); ?>
            </h1>
            <a href="<?php echo esc_url(admin_url('admin.php?page=fp-esperienze-extras')); ?>" class="page-title-action">
                <?php _e('Back to Extras', 'fp-esperienze'); ?>
            </a>
            <hr class="wp-header-end">

            <form method="post" action="">
                <?php wp_nonce_field('fp_extras_form', 'fp_extras_nonce'); ?>
                <input type="hidden" name="action" value="<?php echo $is_edit ? 'update_extra' : 'create_extra'; ?>">
                <?php if ($is_edit) : ?>
                    <input type="hidden" name="extra_id" value="<?php echo esc_attr($extra['id']); ?>">
                <?php endif; ?>

                <table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label for="name"><?php _e('Name', 'fp-esperienze'); ?> <span class="description">(required)</span></label>
                            </th>
                            <td>
                                <input type="text" id="name" name="name" value="<?php echo esc_attr($extra['name'] ?? ''); ?>" class="regular-text" required>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="description"><?php _e('Description', 'fp-esperienze'); ?></label>
                            </th>
                            <td>
                                <textarea id="description" name="description" rows="3" class="large-text"><?php echo esc_textarea($extra['description'] ?? ''); ?></textarea>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="price"><?php _e('Price', 'fp-esperienze'); ?> <span class="description">(required)</span></label>
                            </th>
                            <td>
                                <input type="number" id="price" name="price" value="<?php echo esc_attr($extra['price'] ?? '0'); ?>" step="0.01" min="0" class="regular-text" required>
                                <p class="description"><?php printf(__('Price in %s', 'fp-esperienze'), get_woocommerce_currency()); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="pricing_type"><?php _e('Pricing Type', 'fp-esperienze'); ?></label>
                            </th>
                            <td>
                                <select id="pricing_type" name="pricing_type">
                                    <option value="per_person" <?php selected($extra['pricing_type'] ?? 'per_person', 'per_person'); ?>>
                                        <?php _e('Per Person', 'fp-esperienze'); ?>
                                    </option>
                                    <option value="per_booking" <?php selected($extra['pricing_type'] ?? '', 'per_booking'); ?>>
                                        <?php _e('Per Booking', 'fp-esperienze'); ?>
                                    </option>
                                </select>
                                <p class="description"><?php _e('Whether the price applies per person or per entire booking', 'fp-esperienze'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="max_quantity"><?php _e('Maximum Quantity', 'fp-esperienze'); ?></label>
                            </th>
                            <td>
                                <input type="number" id="max_quantity" name="max_quantity" value="<?php echo esc_attr($extra['max_quantity'] ?? '1'); ?>" min="1" class="small-text">
                                <p class="description"><?php _e('Maximum quantity that can be selected', 'fp-esperienze'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="tax_class"><?php _e('Tax Class', 'fp-esperienze'); ?></label>
                            </th>
                            <td>
                                <select id="tax_class" name="tax_class">
                                    <?php foreach ($tax_class_options as $value => $label) : ?>
                                        <option value="<?php echo esc_attr($value); ?>" <?php selected($extra['tax_class'] ?? '', $value); ?>>
                                            <?php echo esc_html($label); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Options', 'fp-esperienze'); ?></th>
                            <td>
                                <fieldset>
                                    <label>
                                        <input type="checkbox" name="is_required" value="1" <?php checked($extra['is_required'] ?? 0, 1); ?>>
                                        <?php _e('Required', 'fp-esperienze'); ?>
                                    </label>
                                    <br>
                                    <label>
                                        <input type="checkbox" name="is_active" value="1" <?php checked($extra['is_active'] ?? 1, 1); ?>>
                                        <?php _e('Active', 'fp-esperienze'); ?>
                                    </label>
                                </fieldset>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <p class="submit">
                    <input type="submit" name="submit" class="button-primary" value="<?php echo $is_edit ? __('Update Extra', 'fp-esperienze') : __('Create Extra', 'fp-esperienze'); ?>">
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * Show admin notice
     */
    private function showNotice(string $message, string $type = 'info'): void {
        add_action('admin_notices', function() use ($message, $type) {
            echo '<div class="notice notice-' . esc_attr($type) . ' is-dismissible"><p>' . esc_html($message) . '</p></div>';
        });
    }
}