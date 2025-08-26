<?php
/**
 * Meeting Points Admin Manager
 *
 * @package FP\Esperienze\Admin
 */

namespace FP\Esperienze\Admin;

use FP\Esperienze\Data\MeetingPoint;

defined('ABSPATH') || exit;

/**
 * Meeting Points admin manager
 */
class MeetingPointsManager {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_post_fp_add_meeting_point', [$this, 'handleAdd']);
        add_action('admin_post_fp_edit_meeting_point', [$this, 'handleEdit']);
        add_action('admin_post_fp_delete_meeting_point', [$this, 'handleDelete']);
    }

    /**
     * Render meeting points page
     */
    public function render(): void {
        $action = $_GET['action'] ?? 'list';
        $id = (int) ($_GET['id'] ?? 0);

        switch ($action) {
            case 'add':
                $this->renderAddForm();
                break;
            case 'edit':
                $this->renderEditForm($id);
                break;
            case 'delete':
                $this->renderDeleteConfirm($id);
                break;
            default:
                $this->renderList();
                break;
        }
    }

    /**
     * Render meeting points list
     */
    private function renderList(): void {
        $page = (int) ($_GET['paged'] ?? 1);
        $per_page = 20;
        $offset = ($page - 1) * $per_page;

        $meeting_points = MeetingPoint::getAll([
            'limit' => $per_page,
            'offset' => $offset
        ]);

        $total_items = MeetingPoint::getCount();
        $total_pages = ceil($total_items / $per_page);

        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php _e('Meeting Points', 'fp-esperienze'); ?></h1>
            <a href="<?php echo admin_url('admin.php?page=fp-esperienze-meeting-points&action=add'); ?>" class="page-title-action">
                <?php _e('Add New', 'fp-esperienze'); ?>
            </a>
            <hr class="wp-header-end">

            <?php $this->renderMessages(); ?>

            <div class="tablenav top">
                <div class="alignleft actions bulkactions">
                    <label for="bulk-action-selector-top" class="screen-reader-text"><?php _e('Select bulk action', 'fp-esperienze'); ?></label>
                </div>
                <?php if ($total_pages > 1) : ?>
                    <div class="tablenav-pages">
                        <span class="displaying-num"><?php printf(_n('%s item', '%s items', $total_items, 'fp-esperienze'), number_format_i18n($total_items)); ?></span>
                        <?php
                        echo paginate_links([
                            'base' => add_query_arg('paged', '%#%'),
                            'format' => '',
                            'prev_text' => __('&laquo;'),
                            'next_text' => __('&raquo;'),
                            'total' => $total_pages,
                            'current' => $page
                        ]);
                        ?>
                    </div>
                <?php endif; ?>
            </div>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th scope="col" class="manage-column column-name"><?php _e('Name', 'fp-esperienze'); ?></th>
                        <th scope="col" class="manage-column column-address"><?php _e('Address', 'fp-esperienze'); ?></th>
                        <th scope="col" class="manage-column column-coordinates"><?php _e('Coordinates', 'fp-esperienze'); ?></th>
                        <th scope="col" class="manage-column column-place-id"><?php _e('Place ID', 'fp-esperienze'); ?></th>
                        <th scope="col" class="manage-column column-actions"><?php _e('Actions', 'fp-esperienze'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($meeting_points)) : ?>
                        <tr class="no-items">
                            <td class="colspanchange" colspan="5">
                                <?php _e('No meeting points found.', 'fp-esperienze'); ?>
                            </td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($meeting_points as $point) : ?>
                            <tr>
                                <td class="column-name">
                                    <strong><?php echo esc_html($point->name); ?></strong>
                                    <?php if (!empty($point->note)) : ?>
                                        <br><small class="description"><?php echo esc_html(wp_trim_words($point->note, 10)); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td class="column-address">
                                    <?php echo esc_html(wp_trim_words($point->address, 8)); ?>
                                </td>
                                <td class="column-coordinates">
                                    <?php if ($point->latitude && $point->longitude) : ?>
                                        <?php echo esc_html($point->latitude . ', ' . $point->longitude); ?>
                                    <?php else : ?>
                                        <span class="description"><?php _e('Not set', 'fp-esperienze'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="column-place-id">
                                    <?php if (!empty($point->place_id)) : ?>
                                        <code><?php echo esc_html($point->place_id); ?></code>
                                    <?php else : ?>
                                        <span class="description"><?php _e('Not set', 'fp-esperienze'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="column-actions">
                                    <div class="row-actions">
                                        <span class="edit">
                                            <a href="<?php echo admin_url('admin.php?page=fp-esperienze-meeting-points&action=edit&id=' . $point->id); ?>">
                                                <?php _e('Edit', 'fp-esperienze'); ?>
                                            </a>
                                        </span>
                                        <?php if (!MeetingPoint::isInUse($point->id)) : ?>
                                            | <span class="delete">
                                                <a href="<?php echo admin_url('admin.php?page=fp-esperienze-meeting-points&action=delete&id=' . $point->id); ?>" class="submitdelete">
                                                    <?php _e('Delete', 'fp-esperienze'); ?>
                                                </a>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php if ($total_pages > 1) : ?>
                <div class="tablenav bottom">
                    <div class="tablenav-pages">
                        <span class="displaying-num"><?php printf(_n('%s item', '%s items', $total_items, 'fp-esperienze'), number_format_i18n($total_items)); ?></span>
                        <?php
                        echo paginate_links([
                            'base' => add_query_arg('paged', '%#%'),
                            'format' => '',
                            'prev_text' => __('&laquo;'),
                            'next_text' => __('&raquo;'),
                            'total' => $total_pages,
                            'current' => $page
                        ]);
                        ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render add form
     */
    private function renderAddForm(): void {
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php _e('Add Meeting Point', 'fp-esperienze'); ?></h1>
            <a href="<?php echo admin_url('admin.php?page=fp-esperienze-meeting-points'); ?>" class="page-title-action">
                <?php _e('Back to list', 'fp-esperienze'); ?>
            </a>
            <hr class="wp-header-end">

            <?php $this->renderMessages(); ?>

            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                <?php wp_nonce_field('fp_add_meeting_point', 'fp_meeting_point_nonce'); ?>
                <input type="hidden" name="action" value="fp_add_meeting_point">

                <table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label for="name"><?php _e('Name', 'fp-esperienze'); ?> <span class="description">(required)</span></label>
                            </th>
                            <td>
                                <input type="text" id="name" name="name" class="regular-text" required>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="address"><?php _e('Address', 'fp-esperienze'); ?> <span class="description">(required)</span></label>
                            </th>
                            <td>
                                <textarea id="address" name="address" class="large-text" rows="3" required></textarea>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="latitude"><?php _e('Latitude', 'fp-esperienze'); ?></label>
                            </th>
                            <td>
                                <input type="number" id="latitude" name="latitude" class="regular-text" step="0.00000001" min="-90" max="90">
                                <p class="description"><?php _e('Decimal degrees format (e.g., 41.9028)', 'fp-esperienze'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="longitude"><?php _e('Longitude', 'fp-esperienze'); ?></label>
                            </th>
                            <td>
                                <input type="number" id="longitude" name="longitude" class="regular-text" step="0.00000001" min="-180" max="180">
                                <p class="description"><?php _e('Decimal degrees format (e.g., 12.4964)', 'fp-esperienze'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="place_id"><?php _e('Google Places ID', 'fp-esperienze'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="place_id" name="place_id" class="regular-text">
                                <p class="description"><?php _e('Google Places API Place ID for enhanced map integration', 'fp-esperienze'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="note"><?php _e('Notes', 'fp-esperienze'); ?></label>
                            </th>
                            <td>
                                <textarea id="note" name="note" class="large-text" rows="4"></textarea>
                                <p class="description"><?php _e('Additional instructions or notes for participants', 'fp-esperienze'); ?></p>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <?php submit_button(__('Add Meeting Point', 'fp-esperienze')); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render edit form
     *
     * @param int $id Meeting point ID
     */
    private function renderEditForm(int $id): void {
        $meeting_point = MeetingPoint::get($id);

        if (!$meeting_point) {
            wp_die(__('Meeting point not found.', 'fp-esperienze'));
        }

        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php _e('Edit Meeting Point', 'fp-esperienze'); ?></h1>
            <a href="<?php echo admin_url('admin.php?page=fp-esperienze-meeting-points'); ?>" class="page-title-action">
                <?php _e('Back to list', 'fp-esperienze'); ?>
            </a>
            <hr class="wp-header-end">

            <?php $this->renderMessages(); ?>

            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                <?php wp_nonce_field('fp_edit_meeting_point', 'fp_meeting_point_nonce'); ?>
                <input type="hidden" name="action" value="fp_edit_meeting_point">
                <input type="hidden" name="id" value="<?php echo esc_attr($meeting_point->id); ?>">

                <table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label for="name"><?php _e('Name', 'fp-esperienze'); ?> <span class="description">(required)</span></label>
                            </th>
                            <td>
                                <input type="text" id="name" name="name" class="regular-text" value="<?php echo esc_attr($meeting_point->name); ?>" required>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="address"><?php _e('Address', 'fp-esperienze'); ?> <span class="description">(required)</span></label>
                            </th>
                            <td>
                                <textarea id="address" name="address" class="large-text" rows="3" required><?php echo esc_textarea($meeting_point->address); ?></textarea>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="latitude"><?php _e('Latitude', 'fp-esperienze'); ?></label>
                            </th>
                            <td>
                                <input type="number" id="latitude" name="latitude" class="regular-text" step="0.00000001" min="-90" max="90" value="<?php echo esc_attr($meeting_point->latitude); ?>">
                                <p class="description"><?php _e('Decimal degrees format (e.g., 41.9028)', 'fp-esperienze'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="longitude"><?php _e('Longitude', 'fp-esperienze'); ?></label>
                            </th>
                            <td>
                                <input type="number" id="longitude" name="longitude" class="regular-text" step="0.00000001" min="-180" max="180" value="<?php echo esc_attr($meeting_point->longitude); ?>">
                                <p class="description"><?php _e('Decimal degrees format (e.g., 12.4964)', 'fp-esperienze'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="place_id"><?php _e('Google Places ID', 'fp-esperienze'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="place_id" name="place_id" class="regular-text" value="<?php echo esc_attr($meeting_point->place_id); ?>">
                                <p class="description"><?php _e('Google Places API Place ID for enhanced map integration', 'fp-esperienze'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="note"><?php _e('Notes', 'fp-esperienze'); ?></label>
                            </th>
                            <td>
                                <textarea id="note" name="note" class="large-text" rows="4"><?php echo esc_textarea($meeting_point->note); ?></textarea>
                                <p class="description"><?php _e('Additional instructions or notes for participants', 'fp-esperienze'); ?></p>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <?php submit_button(__('Update Meeting Point', 'fp-esperienze')); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render delete confirmation
     *
     * @param int $id Meeting point ID
     */
    private function renderDeleteConfirm(int $id): void {
        $meeting_point = MeetingPoint::get($id);

        if (!$meeting_point) {
            wp_die(__('Meeting point not found.', 'fp-esperienze'));
        }

        if (MeetingPoint::isInUse($id)) {
            wp_die(__('Cannot delete meeting point that is in use by experiences or schedules.', 'fp-esperienze'));
        }

        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php _e('Delete Meeting Point', 'fp-esperienze'); ?></h1>
            <hr class="wp-header-end">

            <div class="notice notice-warning">
                <p><?php _e('Are you sure you want to delete this meeting point? This action cannot be undone.', 'fp-esperienze'); ?></p>
            </div>

            <table class="form-table">
                <tbody>
                    <tr>
                        <th scope="row"><?php _e('Name', 'fp-esperienze'); ?></th>
                        <td><?php echo esc_html($meeting_point->name); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Address', 'fp-esperienze'); ?></th>
                        <td><?php echo esc_html($meeting_point->address); ?></td>
                    </tr>
                </tbody>
            </table>

            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="display: inline;">
                <?php wp_nonce_field('fp_delete_meeting_point', 'fp_meeting_point_nonce'); ?>
                <input type="hidden" name="action" value="fp_delete_meeting_point">
                <input type="hidden" name="id" value="<?php echo esc_attr($meeting_point->id); ?>">
                <?php submit_button(__('Yes, Delete', 'fp-esperienze'), 'delete', 'submit', false); ?>
            </form>
            
            <a href="<?php echo admin_url('admin.php?page=fp-esperienze-meeting-points'); ?>" class="button">
                <?php _e('Cancel', 'fp-esperienze'); ?>
            </a>
        </div>
        <?php
    }

    /**
     * Handle add meeting point
     */
    public function handleAdd(): void {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'fp-esperienze'));
        }

        if (!wp_verify_nonce($_POST['fp_meeting_point_nonce'] ?? '', 'fp_add_meeting_point')) {
            wp_die(__('Security check failed.', 'fp-esperienze'));
        }

        $data = [
            'name' => $_POST['name'] ?? '',
            'address' => $_POST['address'] ?? '',
            'latitude' => $_POST['latitude'] ?? '',
            'longitude' => $_POST['longitude'] ?? '',
            'place_id' => $_POST['place_id'] ?? '',
            'note' => $_POST['note'] ?? '',
        ];

        $id = MeetingPoint::create($data);

        if ($id) {
            $redirect_url = add_query_arg([
                'page' => 'fp-esperienze-meeting-points',
                'message' => 'created'
            ], admin_url('admin.php'));
        } else {
            $redirect_url = add_query_arg([
                'page' => 'fp-esperienze-meeting-points',
                'action' => 'add',
                'message' => 'error'
            ], admin_url('admin.php'));
        }

        wp_redirect($redirect_url);
        exit;
    }

    /**
     * Handle edit meeting point
     */
    public function handleEdit(): void {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'fp-esperienze'));
        }

        if (!wp_verify_nonce($_POST['fp_meeting_point_nonce'] ?? '', 'fp_edit_meeting_point')) {
            wp_die(__('Security check failed.', 'fp-esperienze'));
        }

        $id = (int) ($_POST['id'] ?? 0);
        $data = [
            'name' => $_POST['name'] ?? '',
            'address' => $_POST['address'] ?? '',
            'latitude' => $_POST['latitude'] ?? '',
            'longitude' => $_POST['longitude'] ?? '',
            'place_id' => $_POST['place_id'] ?? '',
            'note' => $_POST['note'] ?? '',
        ];

        $success = MeetingPoint::update($id, $data);

        if ($success) {
            $redirect_url = add_query_arg([
                'page' => 'fp-esperienze-meeting-points',
                'message' => 'updated'
            ], admin_url('admin.php'));
        } else {
            $redirect_url = add_query_arg([
                'page' => 'fp-esperienze-meeting-points',
                'action' => 'edit',
                'id' => $id,
                'message' => 'error'
            ], admin_url('admin.php'));
        }

        wp_redirect($redirect_url);
        exit;
    }

    /**
     * Handle delete meeting point
     */
    public function handleDelete(): void {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'fp-esperienze'));
        }

        if (!wp_verify_nonce($_POST['fp_meeting_point_nonce'] ?? '', 'fp_delete_meeting_point')) {
            wp_die(__('Security check failed.', 'fp-esperienze'));
        }

        $id = (int) ($_POST['id'] ?? 0);
        $success = MeetingPoint::delete($id);

        if ($success) {
            $redirect_url = add_query_arg([
                'page' => 'fp-esperienze-meeting-points',
                'message' => 'deleted'
            ], admin_url('admin.php'));
        } else {
            $redirect_url = add_query_arg([
                'page' => 'fp-esperienze-meeting-points',
                'message' => 'error'
            ], admin_url('admin.php'));
        }

        wp_redirect($redirect_url);
        exit;
    }

    /**
     * Render admin messages
     */
    private function renderMessages(): void {
        $message = $_GET['message'] ?? '';

        switch ($message) {
            case 'created':
                echo '<div class="notice notice-success is-dismissible"><p>' . __('Meeting point created successfully.', 'fp-esperienze') . '</p></div>';
                break;
            case 'updated':
                echo '<div class="notice notice-success is-dismissible"><p>' . __('Meeting point updated successfully.', 'fp-esperienze') . '</p></div>';
                break;
            case 'deleted':
                echo '<div class="notice notice-success is-dismissible"><p>' . __('Meeting point deleted successfully.', 'fp-esperienze') . '</p></div>';
                break;
            case 'error':
                echo '<div class="notice notice-error is-dismissible"><p>' . __('An error occurred. Please try again.', 'fp-esperienze') . '</p></div>';
                break;
        }
    }
}