<?php
/**
 * Admin Menu Manager
 *
 * @package FP\Esperienze\Admin
 */

namespace FP\Esperienze\Admin;

use Exception;
use FP\Esperienze\Data\OverrideManager;
use FP\Esperienze\Data\MeetingPointManager;
use FP\Esperienze\Data\ExtraManager;
use FP\Esperienze\Data\VoucherManager;
use FP\Esperienze\Data\Availability;
use FP\Esperienze\Data\HoldManager;
use FP\Esperienze\Booking\BookingManager;
use FP\Esperienze\PDF\Voucher_Pdf;
use FP\Esperienze\PDF\Qr;
use FP\Esperienze\Core\AssetOptimizer;
use FP\Esperienze\Admin\Settings\BrandingSettingsView;
use FP\Esperienze\Admin\Settings\Services\BrandingSettingsService;
use FP\Esperienze\Admin\Settings\Services\BookingSettingsService;
use FP\Esperienze\Admin\Settings\Services\GeneralSettingsService;
use FP\Esperienze\Admin\Settings\Services\GiftSettingsService;
use FP\Esperienze\Admin\Settings\Services\IntegrationsSettingsService;
use FP\Esperienze\Admin\Settings\Services\NotificationsSettingsService;
use FP\Esperienze\Admin\Settings\Services\WebhookSettingsService;
use FP\Esperienze\Core\CapabilityManager;
use FP\Esperienze\Core\I18nManager;
use FP\Esperienze\Core\WebhookManager;
use FP\Esperienze\Core\Log;
use FP\Esperienze\Admin\DependencyChecker;
use FP\Esperienze\Admin\OperationalAlerts;
use FP\Esperienze\Admin\UI\AdminComponents;
use FP\Esperienze\Admin\Settings\AutoTranslateSettings;
use FP\Esperienze\Admin\Settings\TranslationHelp;
use WP_Error;
use WP_REST_Response;
use WP_Screen;

defined('ABSPATH') || exit;

/**
 * Menu Manager class
 */
class MenuManager {

    private const SETTINGS_ERROR_SLUG = 'fp_esperienze_settings';

    /**
     * Cached registry instance for building the admin menu.
     */
    private MenuRegistry $menuRegistry;

    /**
     * Component-based notice queue rendered within admin pages.
     *
     * @var array<int, array<string, string|null>>
     */
    private array $queuedPageNotices = [];

    /**
     * Constructor
     */
    public function __construct() {
        $this->menuRegistry = MenuRegistry::instance();
        $this->registerMenuPages();

        add_action('admin_menu', [$this, 'addAdminMenu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminScripts']);
        add_filter('set-screen-option', [$this, 'saveScreenOption'], 10, 3);

        // Initialize Setup Wizard and System Status
        new SetupWizard();
        new SystemStatus();
        new PerformanceSettings();
        new ReportsManager();
        new SEOSettings();
        new AutoTranslateSettings();
        new TranslationHelp();
        new OperationalAlerts();
        
        // Handle setup wizard redirect
        add_action('admin_init', [$this, 'handleSetupWizardRedirect']);
        
        // AJAX handlers for booking actions
        add_action('wp_ajax_fp_get_available_slots', [$this, 'ajaxGetAvailableSlots']);
        add_action('wp_ajax_fp_reschedule_booking', [$this, 'ajaxRescheduleBooking']);
        add_action('wp_ajax_fp_cancel_booking', [$this, 'ajaxCancelBooking']);
        add_action('wp_ajax_fp_check_cancellation_rules', [$this, 'ajaxCheckCancellationRules']);
        add_action('wp_ajax_fp_test_webhook', [$this, 'ajaxTestWebhook']);
        add_action('wp_ajax_fp_cleanup_expired_holds', [$this, 'ajaxCleanupExpiredHolds']);
        add_action('wp_ajax_fp_test_meta_capi', [$this, 'ajaxTestMetaCAPI']);
        add_action('wp_ajax_fp_search_experience_products', [$this, 'ajaxSearchExperienceProducts']);
    }

    /**
     * Register canonical menu pages, separators, and legacy aliases.
     */
    private function registerMenuPages(): void {
        $registry = $this->menuRegistry;

        $registry->setTopLevel([
            'page_title' => __('FP Esperienze', 'fp-esperienze'),
            'menu_title' => __('FP Esperienze', 'fp-esperienze'),
            'capability' => CapabilityManager::MANAGE_FP_ESPERIENZE,
            'menu_slug'  => 'fp-esperienze',
            'icon_url'   => 'dashicons-calendar-alt',
            'position'   => 25,
            'callback'   => [$this, 'dashboardPage'],
        ]);

        $registry->registerSeparator('operations', [
            'label'      => __('Operations', 'fp-esperienze'),
            'order'      => 35,
            'capability' => CapabilityManager::MANAGE_FP_ESPERIENZE,
        ]);

        $registry->registerSeparator('configuration', [
            'label'      => __('Configuration', 'fp-esperienze'),
            'order'      => 95,
            'capability' => CapabilityManager::MANAGE_FP_ESPERIENZE,
        ]);

        $registry->registerPage([
            'slug'        => 'fp-esperienze',
            'page_title'  => __('FP Esperienze Overview', 'fp-esperienze'),
            'menu_title'  => __('Overview', 'fp-esperienze'),
            'capability'  => CapabilityManager::MANAGE_FP_ESPERIENZE,
            'callback'    => [$this, 'dashboardPage'],
            'order'       => 10,
        ]);

        $registry->registerPage([
            'slug'        => 'fp-esperienze-reports',
            'page_title'  => __('Reports & Insights', 'fp-esperienze'),
            'menu_title'  => __('Reports & Insights', 'fp-esperienze'),
            'capability'  => CapabilityManager::MANAGE_FP_ESPERIENZE,
            'callback'    => [$this, 'reportsPage'],
            'order'       => 20,
        ]);

        $registry->registerPage([
            'slug'        => 'fp-esperienze-bookings',
            'page_title'  => __('Bookings', 'fp-esperienze'),
            'menu_title'  => __('Bookings', 'fp-esperienze'),
            'capability'  => CapabilityManager::MANAGE_FP_ESPERIENZE,
            'callback'    => [$this, 'bookingsPage'],
            'order'       => 40,
            'load_actions'=> [[$this, 'configureBookingsScreen']],
        ]);

        $registry->registerPage([
            'slug'        => 'fp-esperienze-availability',
            'page_title'  => __('Availability & Closures', 'fp-esperienze'),
            'menu_title'  => __('Availability & Closures', 'fp-esperienze'),
            'capability'  => CapabilityManager::MANAGE_FP_ESPERIENZE,
            'callback'    => [$this, 'closuresPage'],
            'order'       => 50,
            'aliases'     => ['fp-esperienze-closures'],
        ]);

        $registry->registerPage([
            'slug'        => 'fp-esperienze-meeting-points',
            'page_title'  => __('Meeting Points', 'fp-esperienze'),
            'menu_title'  => __('Meeting Points', 'fp-esperienze'),
            'capability'  => CapabilityManager::MANAGE_FP_ESPERIENZE,
            'callback'    => [$this, 'meetingPointsPage'],
            'order'       => 60,
            'load_actions'=> [[$this, 'configureMeetingPointsScreen']],
        ]);

        $registry->registerPage([
            'slug'        => 'fp-esperienze-addons',
            'page_title'  => __('Extras & Add-ons', 'fp-esperienze'),
            'menu_title'  => __('Extras & Add-ons', 'fp-esperienze'),
            'capability'  => CapabilityManager::MANAGE_FP_ESPERIENZE,
            'callback'    => [$this, 'extrasPage'],
            'order'       => 70,
            'aliases'     => ['fp-esperienze-extras'],
            'load_actions'=> [[$this, 'configureExtrasScreen']],
        ]);

        $registry->registerPage([
            'slug'        => 'fp-esperienze-gift-vouchers',
            'page_title'  => __('Gift Vouchers', 'fp-esperienze'),
            'menu_title'  => __('Gift Vouchers', 'fp-esperienze'),
            'capability'  => CapabilityManager::MANAGE_FP_ESPERIENZE,
            'callback'    => [$this, 'vouchersPage'],
            'order'       => 80,
            'aliases'     => ['fp-esperienze-vouchers'],
        ]);

        $registry->registerPage([
            'slug'        => 'fp-esperienze-settings',
            'page_title'  => __('Settings', 'fp-esperienze'),
            'menu_title'  => __('Settings', 'fp-esperienze'),
            'capability'  => CapabilityManager::MANAGE_FP_ESPERIENZE,
            'callback'    => [$this, 'settingsPage'],
            'order'       => 100,
        ]);

        $registry->registerPage([
            'slug'        => 'fp-esperienze-performance',
            'page_title'  => __('Performance Tools', 'fp-esperienze'),
            'menu_title'  => __('Performance Tools', 'fp-esperienze'),
            'capability'  => 'manage_options',
            'callback'    => [$this, 'performancePage'],
            'order'       => 130,
        ]);

        $registry->registerPage([
            'slug'        => 'fp-esperienze-developer-tools',
            'page_title'  => __('Developer Toolkit', 'fp-esperienze'),
            'menu_title'  => __('Developer Toolkit', 'fp-esperienze'),
            'capability'  => CapabilityManager::MANAGE_FP_ESPERIENZE,
            'callback'    => [$this, 'integrationToolkitPage'],
            'order'       => 150,
            'aliases'     => ['fp-esperienze-integration-toolkit'],
        ]);
    }

    /**
     * Persist the screen options for admin list tables.
     *
     * @param mixed  $status Default status from WordPress.
     * @param string $option Screen option name.
     * @param mixed  $value  Submitted value.
     * @return mixed
     */
    public function saveScreenOption($status, string $option, $value) {
        $numeric_options = [
            'fp_bookings_per_page',
            'fp_meeting_points_per_page',
            'fp_extras_per_page',
        ];

        if (in_array($option, $numeric_options, true)) {
            $value = (int) $value;

            if ($value < 5) {
                $value = 5;
            }

            if ($value > 200) {
                $value = 200;
            }

            return $value;
        }

        return $status;
    }

    /**
     * Configure screen options and help tabs for bookings.
     */
    public function configureBookingsScreen(): void {
        add_screen_option('per_page', [
            'label'   => __('Bookings per page', 'fp-esperienze'),
            'default' => 20,
            'option'  => 'fp_bookings_per_page',
        ]);

        add_filter('screen_settings', [$this, 'renderBookingsScreenSettings'], 10, 2);

        if (
            isset($_POST['fp_bookings_columns_nonce'])
            && wp_verify_nonce(wp_unslash($_POST['fp_bookings_columns_nonce']), 'fp_save_bookings_screen_options')
        ) {
            $this->persistVisibleColumns(
                'fp_bookings_hidden_columns',
                $this->getBookingsColumnDefinitions(),
                array_map('sanitize_key', wp_unslash($_POST['fp_bookings_columns'] ?? []))
            );
        }

        $screen = get_current_screen();
        if ($screen instanceof WP_Screen) {
            $screen->add_help_tab([
                'id'      => 'fp-bookings-overview',
                'title'   => __('Managing bookings', 'fp-esperienze'),
                'content' => '<p>' . esc_html__('Use the filters, views, and bulk actions to keep reservations up to date. Select rows to update multiple bookings at once.', 'fp-esperienze') . '</p>',
            ]);

            $screen->add_help_tab([
                'id'      => 'fp-bookings-shortcuts',
                'title'   => __('Shortcuts', 'fp-esperienze'),
                'content' => '<p>' . esc_html__('Calendar view reflects the same filters. Screen Options let you hide columns you do not need on smaller displays.', 'fp-esperienze') . '</p>',
            ]);

            $screen->set_help_sidebar(
                '<p><strong>' . esc_html__('Need more help?', 'fp-esperienze') . '</strong></p>' .
                '<p><a href="https://support.fp-italia.it" target="_blank" rel="noopener noreferrer">' .
                esc_html__('Visit the FP Esperienze documentation', 'fp-esperienze') . '</a></p>'
            );
        }
    }

    /**
     * Configure screen options and help tabs for meeting points.
     */
    public function configureMeetingPointsScreen(): void {
        add_screen_option('per_page', [
            'label'   => __('Meeting points per page', 'fp-esperienze'),
            'default' => 20,
            'option'  => 'fp_meeting_points_per_page',
        ]);

        add_filter('screen_settings', [$this, 'renderMeetingPointsScreenSettings'], 10, 2);

        if (
            isset($_POST['fp_meeting_points_columns_nonce'])
            && wp_verify_nonce(wp_unslash($_POST['fp_meeting_points_columns_nonce']), 'fp_save_meeting_points_screen_options')
        ) {
            $this->persistVisibleColumns(
                'fp_meeting_points_hidden_columns',
                $this->getMeetingPointsColumnDefinitions(),
                array_map('sanitize_key', wp_unslash($_POST['fp_meeting_points_columns'] ?? []))
            );
        }

        $screen = get_current_screen();
        if ($screen instanceof WP_Screen) {
            $screen->add_help_tab([
                'id'      => 'fp-meeting-points-overview',
                'title'   => __('Managing meeting points', 'fp-esperienze'),
                'content' => '<p>' . esc_html__('Store each rendezvous point with coordinates so routing and vouchers stay accurate. Bulk delete lets you remove outdated entries quickly.', 'fp-esperienze') . '</p>',
            ]);

            $screen->set_help_sidebar(
                '<p><strong>' . esc_html__('Tips', 'fp-esperienze') . '</strong></p>' .
                '<p>' . esc_html__('Keep at least one default meeting point to populate booking summaries.', 'fp-esperienze') . '</p>'
            );
        }
    }

    /**
     * Configure screen options and help tabs for extras.
     */
    public function configureExtrasScreen(): void {
        add_screen_option('per_page', [
            'label'   => __('Extras per page', 'fp-esperienze'),
            'default' => 20,
            'option'  => 'fp_extras_per_page',
        ]);

        add_filter('screen_settings', [$this, 'renderExtrasScreenSettings'], 10, 2);

        if (
            isset($_POST['fp_extras_columns_nonce'])
            && wp_verify_nonce(wp_unslash($_POST['fp_extras_columns_nonce']), 'fp_save_extras_screen_options')
        ) {
            $this->persistVisibleColumns(
                'fp_extras_hidden_columns',
                $this->getExtrasColumnDefinitions(),
                array_map('sanitize_key', wp_unslash($_POST['fp_extras_columns'] ?? []))
            );
        }

        $screen = get_current_screen();
        if ($screen instanceof WP_Screen) {
            $screen->add_help_tab([
                'id'      => 'fp-extras-overview',
                'title'   => __('Selling extras', 'fp-esperienze'),
                'content' => '<p>' . esc_html__('Track add-ons with pricing, tax class, and availability. Use Screen Options to focus on the attributes your team updates frequently.', 'fp-esperienze') . '</p>',
            ]);

            $screen->set_help_sidebar(
                '<p><strong>' . esc_html__('Reminder', 'fp-esperienze') . '</strong></p>' .
                '<p>' . esc_html__('Update WooCommerce products linked to extras after changing required or active status.', 'fp-esperienze') . '</p>'
            );
        }
    }

    /**
     * Render screen settings controls for bookings.
     *
     * @param string    $settings Existing settings markup.
     * @param WP_Screen $screen   Current screen instance.
     * @return string
     */
    public function renderBookingsScreenSettings(string $settings, WP_Screen $screen): string {
        if ('fp-esperienze_page_fp-esperienze-bookings' !== $screen->id) {
            return $settings;
        }

        $settings .= $this->renderColumnScreenSettings(
            'fp_bookings_columns',
            'fp_bookings_columns_nonce',
            'fp_save_bookings_screen_options',
            $this->getBookingsColumnDefinitions(),
            $this->getHiddenColumns('fp_bookings_hidden_columns')
        );

        return $settings;
    }

    /**
     * Render screen settings controls for meeting points.
     */
    public function renderMeetingPointsScreenSettings(string $settings, WP_Screen $screen): string {
        if ('fp-esperienze_page_fp-esperienze-meeting-points' !== $screen->id) {
            return $settings;
        }

        $settings .= $this->renderColumnScreenSettings(
            'fp_meeting_points_columns',
            'fp_meeting_points_columns_nonce',
            'fp_save_meeting_points_screen_options',
            $this->getMeetingPointsColumnDefinitions(),
            $this->getHiddenColumns('fp_meeting_points_hidden_columns')
        );

        return $settings;
    }

    /**
     * Render screen settings controls for extras.
     */
    public function renderExtrasScreenSettings(string $settings, WP_Screen $screen): string {
        $extras_screen_ids = [
            'fp-esperienze_page_fp-esperienze-addons',
            'fp-esperienze_page_fp-esperienze-extras',
        ];

        if (!in_array($screen->id, $extras_screen_ids, true)) {
            return $settings;
        }

        $settings .= $this->renderColumnScreenSettings(
            'fp_extras_columns',
            'fp_extras_columns_nonce',
            'fp_save_extras_screen_options',
            $this->getExtrasColumnDefinitions(),
            $this->getHiddenColumns('fp_extras_hidden_columns')
        );

        return $settings;
    }

    /**
     * Render the shared column visibility screen option markup.
     */
    private function renderColumnScreenSettings(
        string $field_name,
        string $nonce_field,
        string $nonce_action,
        array $column_definitions,
        array $hidden_columns
    ): string {
        ob_start();
        ?>
        <fieldset class="screen-options-section">
            <legend><?php esc_html_e('Column visibility', 'fp-esperienze'); ?></legend>
            <?php foreach ($column_definitions as $key => $definition) : ?>
                <?php if ('cb' === $key) { continue; } ?>
                <label>
                    <input type="checkbox" name="<?php echo esc_attr($field_name); ?>[]" value="<?php echo esc_attr($key); ?>" <?php checked(!in_array($key, $hidden_columns, true)); ?> />
                    <?php echo esc_html($definition['label']); ?>
                </label><br />
            <?php endforeach; ?>
        </fieldset>
        <?php
        $output = ob_get_clean();
        $output .= wp_nonce_field($nonce_action, $nonce_field, true, false);

        return $output;
    }

    /**
     * Store the hidden column preferences for the current user.
     */
    private function persistVisibleColumns(string $option, array $column_definitions, array $visible_columns): void {
        $column_keys = array_filter(
            array_keys($column_definitions),
            static function ($key) {
                return 'cb' !== $key;
            }
        );

        $visible = array_values(array_intersect($column_keys, $visible_columns));
        $hidden  = array_values(array_diff($column_keys, $visible));

        update_user_meta(get_current_user_id(), $option, $hidden);
    }

    /**
     * Retrieve hidden columns for the current user.
     */
    private function getHiddenColumns(string $option): array {
        $hidden = get_user_meta(get_current_user_id(), $option, true);

        if (!is_array($hidden)) {
            return [];
        }

        return array_values(array_filter(array_map('sanitize_key', $hidden)));
    }

    /**
     * Resolve the preferred per-page value for the current user.
     */
    private function getPerPageValue(string $option, int $default): int {
        $per_page = get_user_option($option, get_current_user_id());

        if (false === $per_page || (int) $per_page < 1) {
            return $default;
        }

        return (int) $per_page;
    }

    /**
     * Column definitions for the bookings table.
     */
    private function getBookingsColumnDefinitions(): array {
        return [
            'cb'            => ['label' => __('Select bookings', 'fp-esperienze')],
            'id'            => ['label' => __('ID', 'fp-esperienze')],
            'order'         => ['label' => __('Order', 'fp-esperienze')],
            'product'       => ['label' => __('Product', 'fp-esperienze')],
            'datetime'      => ['label' => __('Date & Time', 'fp-esperienze')],
            'participants'  => ['label' => __('Participants', 'fp-esperienze')],
            'status'        => ['label' => __('Status', 'fp-esperienze')],
            'meeting_point' => ['label' => __('Meeting Point', 'fp-esperienze')],
            'created'       => ['label' => __('Created', 'fp-esperienze')],
            'actions'       => ['label' => __('Actions', 'fp-esperienze')],
        ];
    }

    /**
     * Column definitions for the meeting points table.
     */
    private function getMeetingPointsColumnDefinitions(): array {
        return [
            'cb'          => ['label' => __('Select meeting points', 'fp-esperienze')],
            'name'        => ['label' => __('Name', 'fp-esperienze')],
            'address'     => ['label' => __('Address', 'fp-esperienze')],
            'coordinates' => ['label' => __('Coordinates', 'fp-esperienze')],
            'actions'     => ['label' => __('Actions', 'fp-esperienze')],
        ];
    }

    /**
     * Column definitions for the extras table.
     */
    private function getExtrasColumnDefinitions(): array {
        return [
            'cb'          => ['label' => __('Select extras', 'fp-esperienze')],
            'name'        => ['label' => __('Name', 'fp-esperienze')],
            'description' => ['label' => __('Description', 'fp-esperienze')],
            'price'       => ['label' => __('Price', 'fp-esperienze')],
            'billing'     => ['label' => __('Billing Type', 'fp-esperienze')],
            'tax_class'   => ['label' => __('Tax Class', 'fp-esperienze')],
            'max_qty'     => ['label' => __('Max Qty', 'fp-esperienze')],
            'required'    => ['label' => __('Required', 'fp-esperienze')],
            'active'      => ['label' => __('Active', 'fp-esperienze')],
            'actions'     => ['label' => __('Actions', 'fp-esperienze')],
        ];
    }

    /**
     * Human readable booking status labels.
     */
    private function getBookingStatusLabels(): array {
        return [
            'confirmed' => __('Confirmed', 'fp-esperienze'),
            'pending'   => __('Pending', 'fp-esperienze'),
            'completed' => __('Completed', 'fp-esperienze'),
            'cancelled' => __('Cancelled', 'fp-esperienze'),
            'refunded'  => __('Refunded', 'fp-esperienze'),
            'no_show'   => __('No show', 'fp-esperienze'),
        ];
    }

    /**
     * Add admin menu
     */
    public function addAdminMenu(): void {
        $registry = $this->menuRegistry;
        $top_level = $registry->getTopLevel();
        $parent_slug = isset($top_level['menu_slug']) ? (string) $top_level['menu_slug'] : 'fp-esperienze';

        add_menu_page(
            $top_level['page_title'] ?? __('FP Esperienze', 'fp-esperienze'),
            $top_level['menu_title'] ?? __('FP Esperienze', 'fp-esperienze'),
            $top_level['capability'] ?? CapabilityManager::MANAGE_FP_ESPERIENZE,
            $parent_slug,
            $top_level['callback'] ?? [$this, 'dashboardPage'],
            $top_level['icon_url'] ?? 'dashicons-calendar-alt',
            $top_level['position'] ?? 25
        );

        $registered_hooks = [];

        foreach ($registry->getOrderedItems() as $entry) {
            if (($entry['type'] ?? '') === 'separator') {
                $this->injectSubmenuSeparator($parent_slug, $entry);
                continue;
            }

            $hook = add_submenu_page(
                $parent_slug,
                $entry['page_title'] ?? '',
                $entry['menu_title'] ?? '',
                $entry['capability'] ?? CapabilityManager::MANAGE_FP_ESPERIENZE,
                $entry['slug'] ?? '',
                $entry['callback'] ?? null
            );

            if (!empty($entry['slug'])) {
                $registered_hooks[$entry['slug']] = $hook;
            }

            foreach ((array) ($entry['load_actions'] ?? []) as $load_callback) {
                add_action('load-' . $hook, $load_callback);
            }

            if (!empty($entry['hidden'])) {
                remove_submenu_page($parent_slug, $entry['slug']);
            }
        }

        $this->registerAliasRedirects($parent_slug, $registered_hooks, $registry);
    }

    /**
     * Inject a submenu separator that renders as a non-clickable heading.
     */
    private function injectSubmenuSeparator(string $parent_slug, array $separator): void {
        global $submenu;

        $label = isset($separator['label']) ? (string) $separator['label'] : '';
        $capability = isset($separator['capability']) ? (string) $separator['capability'] : 'read';

        if (!isset($submenu[$parent_slug])) {
            $submenu[$parent_slug] = [];
        }

        $submenu[$parent_slug][] = [
            esc_html($label),
            $capability,
            '',
            esc_html($label),
            'wp-submenu-head',
        ];
    }

    /**
     * Register legacy slug aliases that redirect to canonical menu pages.
     *
     * @param array<string, string> $registered_hooks
     */
    private function registerAliasRedirects(string $parent_slug, array $registered_hooks, MenuRegistry $registry): void {
        foreach ($registry->getAliases() as $legacy_slug => $canonical_slug) {
            $page = $registry->getPage($canonical_slug);

            if ($page === null) {
                continue;
            }

            $alias_hook = add_submenu_page(
                $parent_slug,
                $page['page_title'] ?? '',
                $page['menu_title'] ?? '',
                $page['capability'] ?? CapabilityManager::MANAGE_FP_ESPERIENZE,
                $legacy_slug,
                function () use ($legacy_slug, $registry): void {
                    $registry->redirectAlias($legacy_slug);
                }
            );

            if (isset($registered_hooks[$canonical_slug])) {
                $canonical_hook = $registered_hooks[$canonical_slug];
                add_action('load-' . $alias_hook, static function () use ($canonical_hook): void {
                    do_action('load-' . $canonical_hook);
                });
            }

            remove_submenu_page($parent_slug, $legacy_slug);
        }
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
                wp_safe_redirect(admin_url('admin.php?page=fp-esperienze-setup-wizard'));
                exit;
            }
        }
    }

    /**
     * Enqueue admin scripts and styles with localization
     *
     * @param string $hook Current admin page hook
     */
    public function enqueueAdminScripts(string $hook): void {
        // Only enqueue on FP Esperienze admin pages
        if (strpos($hook, 'fp-esperienze') === false) {
            return;
        }

        // Enqueue jQuery and other dependencies
        wp_enqueue_script('jquery');
        wp_enqueue_script('thickbox');
        wp_enqueue_style('thickbox');

        wp_enqueue_script(
            'select2',
            'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js',
            ['jquery'],
            '4.1.0',
            true
        );
        wp_enqueue_style(
            'select2',
            'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css',
            [],
            '4.1.0'
        );
        $product_search = AssetOptimizer::getAssetInfo('js', 'product-search', 'assets/js/product-search.js');
        wp_enqueue_script(
            'fp-admin-product-search',
            $product_search['url'],
            ['jquery', 'select2'],
            $product_search['version'],
            true
        );
        
        // Enqueue bookings calendar script for bookings page
        if (strpos($hook, 'fp-esperienze-bookings') !== false) {
            // Enqueue FullCalendar dependencies
            wp_enqueue_script('moment');
            wp_enqueue_script(
                'fullcalendar',
                'https://cdn.jsdelivr.net/npm/fullcalendar@3.10.5/dist/fullcalendar.min.js',
                ['jquery', 'moment'],
                '3.10.5',
                true
            );
            wp_enqueue_style(
                'fullcalendar',
                'https://cdn.jsdelivr.net/npm/fullcalendar@3.10.5/dist/fullcalendar.min.css',
                [],
                '3.10.5'
            );
            
            $admin_bookings = AssetOptimizer::getAssetInfo('js', 'admin-bookings', 'assets/js/admin-bookings.js');
            wp_enqueue_script(
                'fp-admin-bookings',
                $admin_bookings['url'],
                ['jquery', 'fullcalendar'],
                $admin_bookings['version'],
                true
            );
        }

        if (
            strpos($hook, 'fp-esperienze-developer-tools') !== false
            || strpos($hook, 'fp-esperienze-integration-toolkit') !== false
        ) {
            $integration_toolkit = AssetOptimizer::getAssetInfo('js', 'integration-toolkit', 'assets/js/integration-toolkit.js');
            wp_enqueue_script(
                'fp-integration-toolkit',
                $integration_toolkit['url'],
                [],
                $integration_toolkit['version'],
                true
            );
        }

        if (strpos($hook, 'fp-esperienze-gift-vouchers') !== false) {
            $admin_vouchers = AssetOptimizer::getAssetInfo('js', 'admin-vouchers', 'assets/js/admin-vouchers.js');
            wp_enqueue_script(
                'fp-admin-vouchers',
                $admin_vouchers['url'],
                ['jquery'],
                $admin_vouchers['version'],
                true
            );
        }

        if (strpos($hook, 'fp-esperienze-availability') !== false) {
            $admin_closures = AssetOptimizer::getAssetInfo('js', 'admin-closures', 'assets/js/admin-closures.js');
            wp_enqueue_script(
                'fp-admin-closures',
                $admin_closures['url'],
                [],
                $admin_closures['version'],
                true
            );
        }

        // Localize scripts with translatable strings
        wp_localize_script('jquery', 'fpEsperienzeAdmin', [
            'i18n' => [
                'editExtra' => __('Edit Extra', 'fp-esperienze'),
                'pdfLinkCopied' => __('PDF link copied to clipboard!', 'fp-esperienze'),
                'selectAction' => __('Please select an action.', 'fp-esperienze'),
                'selectVouchers' => __('Please select at least one voucher.', 'fp-esperienze'),
                'confirmVoid' => __('Are you sure you want to void the selected vouchers?', 'fp-esperienze'),
                'confirmResend' => __('Are you sure you want to resend emails for the selected vouchers?', 'fp-esperienze'),
                'confirmExtend' => __('Are you sure you want to extend the selected vouchers by', 'fp-esperienze'),
                'months' => __('months?', 'fp-esperienze'),
                'confirmRegenerateSecret' => __('Are you sure? This will invalidate all existing QR codes!', 'fp-esperienze'),
                'invalidExtendMonths' => __('Please enter a number of months greater than zero.', 'fp-esperienze'),
                'selectLogo' => __('Select Logo', 'fp-esperienze'),
                'useThisImage' => __('Use This Image', 'fp-esperienze'),
                'enterWebhookUrl' => __('Please enter a webhook URL first.', 'fp-esperienze'),
                'testing' => __('Testing...', 'fp-esperienze'),
                'webhookTestSuccess' => __('Webhook test successful!', 'fp-esperienze'),
                'status' => __('Status:', 'fp-esperienze'),
                'webhookTestFailed' => __('Webhook test failed:', 'fp-esperienze'),
                'requestFailed' => __('Request failed. Please try again.', 'fp-esperienze'),
                'generateNewSecret' => __('Generate a new webhook secret? This will invalidate the current secret.', 'fp-esperienze'),
                'cleanupHolds' => __('Clean up expired holds now?', 'fp-esperienze'),
                'cleaning' => __('Cleaning...', 'fp-esperienze'),
                'cleanupCompleted' => __('Cleanup completed!', 'fp-esperienze'),
                'cleanedUp' => __('Cleaned up:', 'fp-esperienze'),
                'holds' => __('holds', 'fp-esperienze'),
                'cleanupFailed' => __('Cleanup failed:', 'fp-esperienze'),
                'confirmDeleteClosure' => __('Remove the selected closure?', 'fp-esperienze'),
                'resendVoucherEmail' => __('Resend voucher email?', 'fp-esperienze'),
                'extendVoucherExpiration' => __('Extend voucher expiration?', 'fp-esperienze'),
                'voidVoucher' => __('Are you sure you want to void this voucher?', 'fp-esperienze'),
                'selectTimeSlot' => __('Select time slot', 'fp-esperienze'),
                'loadingEvents' => __('Loading events...', 'fp-esperienze'),
                'errorFetchingEvents' => __('There was an error while fetching events. Please try again.', 'fp-esperienze'),
                'errorLoadingTimeSlots' => __('Error loading time slots', 'fp-esperienze'),
                'errorReschedulingBooking' => __('Error rescheduling booking', 'fp-esperienze'),
                'errorCheckingCancellationRules' => __('Error checking cancellation rules', 'fp-esperienze'),
                'thisBookingCannotBeCancelled' => __('This booking cannot be cancelled.', 'fp-esperienze'),
                'errorCancellingBooking' => __('Error cancelling booking', 'fp-esperienze'),
                'confirmCancelBooking' => __('Are you sure you want to cancel this booking?', 'fp-esperienze'),
                'spotsAvailable' => __('spots available', 'fp-esperienze'),
                'confirmDeleteMeetingPoint' => __('Are you sure you want to delete the meeting point', 'fp-esperienze'),
                'actionCannotBeUndone' => __('This action cannot be undone and will fail if the meeting point is currently in use.', 'fp-esperienze'),
                'confirmDeleteExtra' => __('Are you sure you want to delete the extra "%s"? This action cannot be undone.', 'fp-esperienze')
            ],
            'ajax_url' => admin_url('admin-ajax.php'),
            'rest_url' => rest_url('fp-esperienze/v1/'),
            'nonce' => wp_create_nonce('fp_esperienze_admin'),
            'calendar_page_size' => 50,
            'calendar_max_pages' => 20,
        ]);
    }

    /**
     * Dashboard page
     */
    public function dashboardPage(): void {
        // Get dashboard statistics
        $stats = $this->getDashboardStatistics();
        $recent_bookings = $this->getRecentBookings(5);

        $metrics = [
            [
                'label' => __('Total Bookings', 'fp-esperienze'),
                'value' => number_format_i18n(absint($stats['total_bookings'] ?? 0)),
            ],
            [
                'label' => __('This Month', 'fp-esperienze'),
                'value' => number_format_i18n(absint($stats['month_bookings'] ?? 0)),
            ],
            [
                'label' => __('Upcoming', 'fp-esperienze'),
                'value' => number_format_i18n(absint($stats['upcoming_bookings'] ?? 0)),
            ],
            [
                'label' => __('Active Vouchers', 'fp-esperienze'),
                'value' => number_format_i18n(absint($stats['active_vouchers'] ?? 0)),
            ],
        ];

        ?>
        <?php AdminComponents::skipLink('fp-admin-main-content'); ?>
        <div class="wrap fp-admin-page" id="fp-admin-main-content" tabindex="-1">
            <?php
            AdminComponents::pageHeader([
                'title'   => __('FP Esperienze Dashboard', 'fp-esperienze'),
                'lead'    => __('Monitor bookings momentum, voucher usage, and outstanding setup steps for your experience business.', 'fp-esperienze'),
                'actions' => [
                    [
                        'label'   => __('View Reports', 'fp-esperienze'),
                        'url'     => admin_url('admin.php?page=fp-esperienze-reports'),
                        'variant' => 'secondary',
                    ],
                    [
                        'label'   => __('Manage Settings', 'fp-esperienze'),
                        'url'     => admin_url('admin.php?page=fp-esperienze-settings'),
                        'variant' => 'secondary',
                    ],
                ],
            ]);
            ?>

            <div class="fp-admin-stack">
                <?php if (isset($_GET['setup']) && $_GET['setup'] === 'complete') : ?>
                    <?php
                    AdminComponents::notice([
                        'type'    => 'success',
                        'title'   => __('Setup complete', 'fp-esperienze'),
                        'message' => __('Setup wizard completed successfully. You can now start accepting bookings.', 'fp-esperienze'),
                    ]);
                    ?>
                <?php endif; ?>

                <?php if (!empty($metrics)) : ?>
                    <div class="fp-admin-metric-grid" role="list">
                        <?php foreach ($metrics as $metric) : ?>
                            <div class="fp-admin-card fp-admin-card--metric" role="listitem">
                                <span class="fp-admin-helper-text"><?php echo esc_html($metric['label']); ?></span>
                                <span class="fp-admin-metric__value"><?php echo esc_html($metric['value']); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="fp-admin-grid fp-admin-grid--sidebar">
                    <div class="fp-admin-stack">
                        <?php
                        AdminComponents::openCard([
                            'title' => __('Recent Bookings', 'fp-esperienze'),
                            'meta'  => [
                                sprintf(__('Last %d entries', 'fp-esperienze'), count($recent_bookings)),
                            ],
                        ]);
                        ?>
                        <?php if (!empty($recent_bookings)) : ?>
                            <table class="fp-admin-table fp-admin-table--striped">
                                <thead>
                                    <tr>
                                        <th><?php esc_html_e('Experience', 'fp-esperienze'); ?></th>
                                        <th><?php esc_html_e('Guest & time', 'fp-esperienze'); ?></th>
                                        <th><?php esc_html_e('Status', 'fp-esperienze'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_bookings as $booking) :
                                        $product = wc_get_product($booking->product_id);
                                        $product_name = $product ? $product->get_name() : __('Unknown Experience', 'fp-esperienze');
                                        ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo esc_html($product_name); ?></strong>
                                            </td>
                                            <td>
                                                <span class="fp-admin-helper-text">
                                                    <?php echo esc_html($booking->customer_name); ?>
                                                </span><br>
                                                <span>
                                                    <?php echo esc_html(\fp_esperienze_wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($booking->booking_date . ' ' . $booking->booking_time))); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="booking-status status-<?php echo esc_attr($booking->status); ?>">
                                                    <?php echo esc_html(ucfirst($booking->status)); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <div class="fp-admin-card__footer">
                                <a class="button button-secondary" href="<?php echo esc_url(admin_url('admin.php?page=fp-esperienze-bookings')); ?>">
                                    <?php esc_html_e('View all bookings', 'fp-esperienze'); ?>
                                </a>
                            </div>
                        <?php else : ?>
                            <p class="fp-admin-helper-text"><?php esc_html_e('No bookings yet. Create your first experience to start accepting reservations.', 'fp-esperienze'); ?></p>
                        <?php endif; ?>
                        <?php AdminComponents::closeCard(); ?>
                    </div>

                    <div class="fp-admin-stack">
                        <?php
                        AdminComponents::openCard([
                            'title' => __('Quick actions', 'fp-esperienze'),
                            'muted' => true,
                        ]);
                        ?>
                        <div class="fp-admin-quick-actions">
                            <a class="button button-primary" href="<?php echo esc_url(admin_url('post-new.php?post_type=product')); ?>">
                                <?php esc_html_e('Add new experience', 'fp-esperienze'); ?>
                            </a>
                            <a class="button button-secondary" href="<?php echo esc_url(admin_url('admin.php?page=fp-esperienze-bookings')); ?>">
                                <?php esc_html_e('Manage bookings', 'fp-esperienze'); ?>
                            </a>
                            <a class="button button-secondary" href="<?php echo esc_url(admin_url('admin.php?page=fp-esperienze-gift-vouchers')); ?>">
                                <?php esc_html_e('Create voucher', 'fp-esperienze'); ?>
                            </a>
                            <a class="button button-secondary" href="<?php echo esc_url(admin_url('admin.php?page=fp-esperienze-reports')); ?>">
                                <?php esc_html_e('View reports', 'fp-esperienze'); ?>
                            </a>
                        </div>
                        <?php AdminComponents::closeCard(); ?>

                        <?php
                        AdminComponents::openCard([
                            'title' => __('Optional dependencies', 'fp-esperienze'),
                            'muted' => true,
                        ]);
                        $this->renderDependencyStatusWidget();
                        AdminComponents::closeCard();
                        ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Handle meeting point form actions.
     */
    private function handleMeetingPointAction(): void {
        $action = sanitize_text_field(wp_unslash($_POST['action'] ?? ''));

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

            case 'bulk-delete':
                $ids = array_map('absint', (array) wp_unslash($_POST['meeting_point_ids'] ?? []));
                $ids = array_filter($ids);
                $bulk_action = sanitize_text_field(wp_unslash($_POST['bulk_action'] ?? ''));

                if ('delete' !== $bulk_action || empty($ids)) {
                    add_action('admin_notices', function() {
                        echo '<div class="notice notice-warning is-dismissible"><p>' .
                             esc_html__('Select meeting points and the delete action before applying bulk changes.', 'fp-esperienze') .
                             '</p></div>';
                    });
                    break;
                }

                $this->bulkDeleteMeetingPoints($ids);
                break;
        }
    }

    /**
     * Create new meeting point.
     */
    private function createMeetingPoint(): void {
        $data = [
            'name'     => sanitize_text_field(wp_unslash($_POST['meeting_point_name'] ?? '')),
            'address'  => sanitize_textarea_field(wp_unslash($_POST['meeting_point_address'] ?? '')),
            'lat'      => !empty($_POST['meeting_point_lat']) ? (float) wp_unslash($_POST['meeting_point_lat']) : null,
            'lng'      => !empty($_POST['meeting_point_lng']) ? (float) wp_unslash($_POST['meeting_point_lng']) : null,
            'place_id' => sanitize_text_field(wp_unslash($_POST['meeting_point_place_id'] ?? '')),
            'note'     => sanitize_textarea_field(wp_unslash($_POST['meeting_point_note'] ?? '')),
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
     * Update meeting point.
     */
    private function updateMeetingPoint(): void {
        $id = absint(wp_unslash($_POST['meeting_point_id'] ?? 0));

        if (!$id) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error is-dismissible"><p>' .
                     esc_html__('Invalid meeting point ID.', 'fp-esperienze') .
                     '</p></div>';
            });
            return;
        }

        $data = [
            'name'     => sanitize_text_field(wp_unslash($_POST['meeting_point_name'] ?? '')),
            'address'  => sanitize_textarea_field(wp_unslash($_POST['meeting_point_address'] ?? '')),
            'lat'      => !empty($_POST['meeting_point_lat']) ? (float) wp_unslash($_POST['meeting_point_lat']) : null,
            'lng'      => !empty($_POST['meeting_point_lng']) ? (float) wp_unslash($_POST['meeting_point_lng']) : null,
            'place_id' => sanitize_text_field(wp_unslash($_POST['meeting_point_place_id'] ?? '')),
            'note'     => sanitize_textarea_field(wp_unslash($_POST['meeting_point_note'] ?? '')),
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

            wp_safe_redirect(admin_url('admin.php?page=fp-esperienze-meeting-points'));
            exit;
        }

        add_action('admin_notices', function() {
            echo '<div class="notice notice-error is-dismissible"><p>' .
                 esc_html__('Failed to update meeting point.', 'fp-esperienze') .
                 '</p></div>';
        });
    }

    /**
     * Delete meeting point.
     */
    private function deleteMeetingPoint(): void {
        $id = absint(wp_unslash($_POST['meeting_point_id'] ?? 0));

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
     * Bulk delete meeting points.
     */
    private function bulkDeleteMeetingPoints(array $ids): void {
        if (empty($ids)) {
            return;
        }

        $deleted = 0;
        $failed  = 0;

        foreach ($ids as $id) {
            if (MeetingPointManager::deleteMeetingPoint($id)) {
                $deleted++;
            } else {
                $failed++;
            }
        }

        if ($deleted > 0) {
            add_action('admin_notices', function () use ($deleted) {
                echo '<div class="notice notice-success is-dismissible"><p>' .
                     sprintf(esc_html__('%d meeting points deleted.', 'fp-esperienze'), $deleted) .
                     '</p></div>';
            });
        }

        if ($failed > 0) {
            add_action('admin_notices', function () use ($failed) {
                echo '<div class="notice notice-error is-dismissible"><p>' .
                     sprintf(esc_html__('%d meeting points could not be deleted.', 'fp-esperienze'), $failed) .
                     '</p></div>';
            });
        }
    }

    /**
     * Bookings page
     */
    public function bookingsPage(): void {
        // Handle form submissions
        if (!empty($_POST) && isset($_POST['fp_booking_nonce'])) {
            $this->handleBookingsActions();
        }
        
        // Handle CSV export
        if (isset($_GET['action']) && sanitize_text_field($_GET['action']) === 'export_csv') {
            $response = $this->exportBookingsCSV();
            if (is_wp_error($response)) {
                wp_die($response->get_error_message(), '', ['response' => 500]);
            }

            foreach ($response->get_headers() as $name => $value) {
                header("$name: $value");
            }

            echo $response->get_data();
            return;
        }
        
        // Create nonce for CSV export
        $export_nonce = wp_create_nonce('fp_export_bookings');

        $status_filter  = sanitize_text_field(wp_unslash($_GET['status'] ?? ''));
        $product_filter = absint(wp_unslash($_GET['product_id'] ?? 0));
        $date_from      = sanitize_text_field(wp_unslash($_GET['date_from'] ?? ''));
        $date_to        = sanitize_text_field(wp_unslash($_GET['date_to'] ?? ''));

        $filters = [];
        if ($status_filter !== '') {
            $filters['status'] = $status_filter;
        }
        if ($product_filter > 0) {
            $filters['product_id'] = $product_filter;
        }
        if ($date_from !== '') {
            $filters['date_from'] = $date_from;
        }
        if ($date_to !== '') {
            $filters['date_to'] = $date_to;
        }

        $query_filters = $filters;
        unset($query_filters['status']);

        $all_bookings = BookingManager::getBookings($query_filters);
        $status_labels = $this->getBookingStatusLabels();

        $status_counts = [
            'all' => count($all_bookings),
        ];

        foreach (array_keys($status_labels) as $status_key) {
            $status_counts[$status_key] = 0;
        }

        foreach ($all_bookings as $booking_item) {
            $status_key = isset($booking_item->status) ? (string) $booking_item->status : '';

            if (!isset($status_counts[$status_key])) {
                $status_counts[$status_key] = 0;
            }

            if ($status_key !== '') {
                $status_counts[$status_key]++;
            }
        }

        $current_view = $status_filter !== '' ? $status_filter : 'all';

        $filtered_bookings = $all_bookings;
        if ('all' !== $current_view && '' !== $current_view) {
            $filtered_bookings = array_values(
                array_filter(
                    $filtered_bookings,
                    static function ($booking_item) use ($current_view) {
                        return isset($booking_item->status) && (string) $booking_item->status === $current_view;
                    }
                )
            );
        }

        $total_items  = count($filtered_bookings);
        $per_page     = $this->getPerPageValue('fp_bookings_per_page', 20);
        $current_page = max(1, absint(wp_unslash($_GET['paged'] ?? 1)));
        $total_pages  = max(1, (int) ceil($total_items / max(1, $per_page)));

        if ($current_page > $total_pages) {
            $current_page = $total_pages;
        }

        $offset = ($current_page - 1) * $per_page;

        if ($offset < 0) {
            $offset = 0;
        }

        $bookings = array_slice($filtered_bookings, $offset, $per_page);

        $selected_product_id = $product_filter;
        // Get experience products for filter dropdown
        $experience_products = $this->getExperienceProducts($selected_product_id);

        $query_args = [];
        foreach (wp_unslash($_GET) as $key => $value) {
            if (is_array($value)) {
                continue;
            }

            if ($value === '') {
                continue;
            }

            $query_args[$key] = sanitize_text_field($value);
        }

        $query_args['page'] = 'fp-esperienze-bookings';

        $view_base_args = $query_args;
        unset($view_base_args['status'], $view_base_args['paged']);
        $view_base_url = add_query_arg($view_base_args, admin_url('admin.php'));

        $views = [];
        $views['all'] = [
            'key'     => 'all',
            'label'   => __('All', 'fp-esperienze'),
            'count'   => $status_counts['all'] ?? $total_items,
            'url'     => $view_base_url,
            'current' => 'all' === $current_view,
        ];

        foreach ($status_labels as $status_key => $label) {
            $views[$status_key] = [
                'key'     => $status_key,
                'label'   => $label,
                'count'   => $status_counts[$status_key] ?? 0,
                'url'     => add_query_arg('status', $status_key, $view_base_url),
                'current' => $current_view === $status_key,
            ];
        }

        $export_url = add_query_arg(
            array_merge(
                $query_args,
                [
                    'action'   => 'export_csv',
                    '_wpnonce' => $export_nonce,
                ]
            ),
            admin_url('admin.php')
        );

        $pagination_args = $query_args;
        unset($pagination_args['action'], $pagination_args['_wpnonce']);
        $pagination_base_url = add_query_arg($pagination_args, admin_url('admin.php'));
        $pagination_links = paginate_links([
            'base'      => add_query_arg('paged', '%#%', $pagination_base_url),
            'format'    => '',
            'current'   => $current_page,
            'total'     => $total_pages,
            'prev_text' => __('&laquo; Previous', 'fp-esperienze'),
            'next_text' => __('Next &raquo;', 'fp-esperienze'),
        ]);

        $page_result_count = count($bookings);
        $booking_columns = $this->getBookingsColumnDefinitions();
        $hidden_booking_columns = $this->getHiddenColumns('fp_bookings_hidden_columns');
        $visible_booking_columns = count($booking_columns) - count($hidden_booking_columns);
        ?>
        <?php AdminComponents::skipLink('fp-admin-main-content'); ?>
        <div class="wrap fp-admin-page" id="fp-admin-main-content" tabindex="-1">
            <?php
            AdminComponents::pageHeader([
                'title'   => __('Bookings Management', 'fp-esperienze'),
                'lead'    => __('Filter reservations, export reports, and manage changes from one place.', 'fp-esperienze'),
                'actions' => [
                    [
                        'label'   => __('Export CSV', 'fp-esperienze'),
                        'url'     => $export_url,
                        'variant' => 'secondary',
                    ],
                ],
            ]);
            ?>

            <div class="fp-admin-stack">
                <?php
                AdminComponents::openCard([
                    'title' => __('Filter bookings', 'fp-esperienze'),
                ]);
                ?>
                <form method="get" action="" class="fp-admin-form fp-admin-form--filters">
                    <input type="hidden" name="page" value="fp-esperienze-bookings" />
                    <?php
                    AdminComponents::formRow([
                        'label' => __('Status', 'fp-esperienze'),
                        'for'   => 'fp-booking-status',
                    ], function () use ($status_filter, $status_labels) {
                        ?>
                        <select id="fp-booking-status" name="status">
                            <option value=""><?php esc_html_e('All statuses', 'fp-esperienze'); ?></option>
                            <?php foreach ($status_labels as $status_key => $status_label) : ?>
                                <option value="<?php echo esc_attr($status_key); ?>" <?php selected($status_filter, $status_key); ?>>
                                    <?php echo esc_html($status_label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php
                    });

                    AdminComponents::formRow([
                        'label' => __('Experience', 'fp-esperienze'),
                        'for'   => 'fp-booking-product',
                    ], function () use ($experience_products, $selected_product_id) {
                        ?>
                        <select id="fp-booking-product" name="product_id" class="fp-product-search" data-placeholder="<?php esc_attr_e('All products', 'fp-esperienze'); ?>">
                            <option value=""><?php esc_html_e('All products', 'fp-esperienze'); ?></option>
                            <?php foreach ($experience_products as $product) : ?>
                                <option value="<?php echo esc_attr($product->ID); ?>" <?php selected($selected_product_id, $product->ID); ?>>
                                    <?php echo esc_html($product->post_title); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php
                    });

                    AdminComponents::formRow([
                        'label' => __('From date', 'fp-esperienze'),
                        'for'   => 'fp-booking-date-from',
                    ], function () use ($date_from) {
                        ?>
                        <input type="date" id="fp-booking-date-from" name="date_from" value="<?php echo esc_attr($date_from); ?>" />
                        <?php
                    });

                    AdminComponents::formRow([
                        'label' => __('To date', 'fp-esperienze'),
                        'for'   => 'fp-booking-date-to',
                    ], function () use ($date_to) {
                        ?>
                        <input type="date" id="fp-booking-date-to" name="date_to" value="<?php echo esc_attr($date_to); ?>" />
                        <?php
                    });
                    ?>

                    <div class="fp-admin-form__actions">
                        <button type="submit" class="button button-primary"><?php esc_html_e('Apply filters', 'fp-esperienze'); ?></button>
                        <a class="button button-secondary" href="<?php echo esc_url(admin_url('admin.php?page=fp-esperienze-bookings')); ?>">
                            <?php esc_html_e('Clear filters', 'fp-esperienze'); ?>
                        </a>
                    </div>
                </form>
                <?php AdminComponents::closeCard(); ?>

                <?php
                AdminComponents::openCard([
                    'title' => __('Bookings', 'fp-esperienze'),
                    'meta'  => [
                        sprintf(__('Showing %1$d of %2$d results', 'fp-esperienze'), $page_result_count, $total_items),
                    ],
                ]);
                ?>
                <?php if (!empty($views)) : ?>
                    <ul class="subsubsub">
                        <?php
                        $view_links = [];
                        foreach ($views as $view) {
                            $view_links[] = sprintf(
                                '<li class="%1$s"><a href="%2$s"%3$s>%4$s <span class="count">(%5$s)</span></a></li>',
                                esc_attr($view['key']),
                                esc_url($view['url']),
                                $view['current'] ? ' class="current"' : '',
                                esc_html($view['label']),
                                esc_html(number_format_i18n((int) $view['count']))
                            );
                        }

                        echo implode(' | ', $view_links); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                        ?>
                    </ul>
                <?php endif; ?>

                <div class="fp-admin-view-toggle" role="group" aria-label="<?php esc_attr_e('Change bookings view', 'fp-esperienze'); ?>">
                    <button id="fp-list-view" class="button button-primary" type="button"><?php esc_html_e('List view', 'fp-esperienze'); ?></button>
                    <button id="fp-calendar-view" class="button button-secondary" type="button"><?php esc_html_e('Calendar view', 'fp-esperienze'); ?></button>
                </div>

                <div id="fp-bookings-list" class="fp-bookings-content">
                    <?php if ($total_items === 0) : ?>
                        <?php
                        AdminComponents::notice([
                            'type'    => 'info',
                            'title'   => __('No bookings found', 'fp-esperienze'),
                            'message' => __('Adjust your filters or check back after new reservations arrive.', 'fp-esperienze'),
                        ]);
                        ?>
                    <?php else : ?>
                        <form method="post" class="fp-admin-table-form">
                            <?php wp_nonce_field('fp_booking_action', 'fp_booking_nonce'); ?>
                            <input type="hidden" name="action" value="bulk_update_status" />
                            <input type="hidden" name="paged" value="<?php echo esc_attr($current_page); ?>" />

                            <div class="tablenav top">
                                <div class="alignleft actions bulkactions">
                                    <label class="screen-reader-text" for="bulk-action-selector-top"><?php esc_html_e('Select bulk action', 'fp-esperienze'); ?></label>
                                    <select name="bulk_status" id="bulk-action-selector-top">
                                        <option value=""><?php esc_html_e('Bulk actions', 'fp-esperienze'); ?></option>
                                        <?php foreach ($status_labels as $status_key => $label) : ?>
                                            <option value="<?php echo esc_attr($status_key); ?>"><?php echo esc_html(sprintf(__('Mark as %s', 'fp-esperienze'), $label)); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="button action"><?php esc_html_e('Apply', 'fp-esperienze'); ?></button>
                                </div>
                                <?php if (!empty($pagination_links)) : ?>
                                    <div class="tablenav-pages"><?php echo wp_kses_post($pagination_links); ?></div>
                                <?php endif; ?>
                                <br class="clear" />
                            </div>

                            <table class="wp-list-table widefat fixed striped table-view-list fp-bookings-table">
                                <thead>
                                    <tr>
                                        <td id="cb" class="manage-column column-cb check-column">
                                            <input type="checkbox" id="cb-select-all-1" />
                                        </td>
                                        <?php foreach ($booking_columns as $column_key => $column_definition) : ?>
                                            <?php if ('cb' === $column_key || in_array($column_key, $hidden_booking_columns, true)) { continue; } ?>
                                            <th scope="col" class="manage-column column-<?php echo esc_attr($column_key); ?>">
                                                <?php echo esc_html($column_definition['label']); ?>
                                            </th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($bookings as $booking) : ?>
                                        <tr>
                                            <th scope="row" class="check-column">
                                                <input type="checkbox" name="booking_ids[]" value="<?php echo esc_attr($booking->id); ?>" />
                                            </th>
                                            <?php foreach ($booking_columns as $column_key => $column_definition) : ?>
                                                <?php if ('cb' === $column_key || in_array($column_key, $hidden_booking_columns, true)) { continue; } ?>
                                                <td data-colname="<?php echo esc_attr($column_definition['label']); ?>" class="column-<?php echo esc_attr($column_key); ?>">
                                                    <?php
                                                    switch ($column_key) {
                                                        case 'id':
                                                            echo esc_html($booking->id);
                                                            break;
                                                        case 'order':
                                                            if (!empty($booking->order_id)) {
                                                                $order_url = admin_url('post.php?post=' . $booking->order_id . '&action=edit');
                                                                printf('<a href="%1$s">#%2$s</a>', esc_url($order_url), esc_html($booking->order_id));
                                                            } else {
                                                                esc_html_e('N/A', 'fp-esperienze');
                                                            }
                                                            break;
                                                        case 'product':
                                                            $product = wc_get_product($booking->product_id);
                                                            $product_name = $product ? $product->get_name() : __('Product not found', 'fp-esperienze');
                                                            echo esc_html($product_name);
                                                            break;
                                                        case 'datetime':
                                                            echo esc_html(\fp_esperienze_wp_date(get_option('date_format'), strtotime($booking->booking_date)));
                                                            echo '<br />';
                                                            echo esc_html(\fp_esperienze_wp_date(get_option('time_format'), strtotime($booking->booking_time)));
                                                            break;
                                                        case 'participants':
                                                            $total = (int) ($booking->adults + $booking->children);
                                                            printf(
                                                                esc_html__('%1$d total (%2$d adults, %3$d children)', 'fp-esperienze'),
                                                                $total,
                                                                (int) $booking->adults,
                                                                (int) $booking->children
                                                            );
                                                            break;
                                                        case 'status':
                                                            $status_key = isset($booking->status) ? (string) $booking->status : '';
                                                            $status_label = $status_labels[$status_key] ?? ucfirst($status_key);
                                                            ?>
                                                            <span class="booking-status status-<?php echo esc_attr($status_key); ?>">
                                                                <?php echo esc_html($status_label); ?>
                                                            </span>
                                                            <?php
                                                            break;
                                                        case 'meeting_point':
                                                            if (!empty($booking->meeting_point_id)) {
                                                                $mp = MeetingPointManager::getMeetingPoint($booking->meeting_point_id);
                                                                echo $mp ? esc_html($mp->name) : esc_html__('Not found', 'fp-esperienze');
                                                            } else {
                                                                esc_html_e('Not set', 'fp-esperienze');
                                                            }
                                                            break;
                                                        case 'created':
                                                            echo esc_html(\fp_esperienze_wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($booking->created_at)));
                                                            break;
                                                        case 'actions':
                                                            if (isset($booking->status) && 'confirmed' === $booking->status) {
                                                                ?>
                                                                <button type="button" class="button button-small fp-reschedule-booking" data-booking-id="<?php echo esc_attr($booking->id); ?>" data-product-id="<?php echo esc_attr($booking->product_id); ?>" data-current-date="<?php echo esc_attr($booking->booking_date); ?>" data-current-time="<?php echo esc_attr($booking->booking_time); ?>">
                                                                    <?php esc_html_e('Reschedule', 'fp-esperienze'); ?>
                                                                </button>
                                                                <button type="button" class="button button-small fp-cancel-booking" data-booking-id="<?php echo esc_attr($booking->id); ?>">
                                                                    <?php esc_html_e('Cancel', 'fp-esperienze'); ?>
                                                                </button>
                                                                <?php
                                                            } else {
                                                                echo '<span class="description">' . esc_html__('No actions available', 'fp-esperienze') . '</span>';
                                                            }
                                                            break;
                                                    }
                                                    ?>
                                                </td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td class="manage-column column-cb check-column">
                                            <input type="checkbox" id="cb-select-all-2" />
                                        </td>
                                        <?php foreach ($booking_columns as $column_key => $column_definition) : ?>
                                            <?php if ('cb' === $column_key || in_array($column_key, $hidden_booking_columns, true)) { continue; } ?>
                                            <th scope="col" class="manage-column column-<?php echo esc_attr($column_key); ?>">
                                                <?php echo esc_html($column_definition['label']); ?>
                                            </th>
                                        <?php endforeach; ?>
                                    </tr>
                                </tfoot>
                            </table>

                            <div class="tablenav bottom">
                                <?php if (!empty($pagination_links)) : ?>
                                    <div class="tablenav-pages"><?php echo wp_kses_post($pagination_links); ?></div>
                                <?php endif; ?>
                                <br class="clear" />
                            </div>
                        </form>
                    <?php endif; ?>
                </div>

                <div id="fp-bookings-calendar" class="fp-bookings-content fp-hidden">
                    <div id="fp-calendar"></div>
                </div>

                <?php AdminComponents::closeCard(); ?>
            </div>

            <!-- Reschedule Modal -->
            <div id="fp-reschedule-modal">
                <div class="fp-modal-content">
                    <span class="fp-modal-close">&times;</span>
                    <h3><?php _e('Reschedule Booking', 'fp-esperienze'); ?></h3>
                    <form id="fp-reschedule-form">
                        <?php wp_nonce_field('fp_reschedule_booking', 'fp_reschedule_nonce'); ?>
                        <input type="hidden" id="reschedule-booking-id" name="booking_id" value="">
                        <input type="hidden" id="reschedule-product-id" name="product_id" value="">
                        
                        <p>
                            <label for="reschedule-date"><?php _e('New Date:', 'fp-esperienze'); ?></label>
                            <input type="date" id="reschedule-date" name="new_date" required>
                        </p>
                        
                        <p>
                            <label for="reschedule-time"><?php _e('New Time Slot:', 'fp-esperienze'); ?></label>
                            <select id="reschedule-time" name="new_time" required>
                                <option value=""><?php _e('Select time slot', 'fp-esperienze'); ?></option>
                            </select>
                        </p>
                        
                        <p>
                            <label for="reschedule-notes"><?php _e('Admin Notes:', 'fp-esperienze'); ?></label>
                            <textarea id="reschedule-notes" name="admin_notes" rows="3" placeholder="<?php _e('Optional notes about the reschedule...', 'fp-esperienze'); ?>"></textarea>
                        </p>
                        
                        <p>
                            <button type="submit" class="button button-primary"><?php _e('Reschedule Booking', 'fp-esperienze'); ?></button>
                            <button type="button" class="button fp-modal-close"><?php _e('Cancel', 'fp-esperienze'); ?></button>
                        </p>
                    </form>
                </div>
            </div>
            
            <!-- Cancel Modal -->
            <div id="fp-cancel-modal">
                <div class="fp-modal-content">
                    <span class="fp-modal-close">&times;</span>
                    <h3><?php _e('Cancel Booking', 'fp-esperienze'); ?></h3>
                    <div id="fp-cancel-info"></div>
                    <form id="fp-cancel-form" class="fp-hidden">
                        <?php wp_nonce_field('fp_cancel_booking', 'fp_cancel_nonce'); ?>
                        <input type="hidden" id="cancel-booking-id" name="booking_id" value="">
                        
                        <p>
                            <label for="cancel-reason"><?php _e('Cancellation Reason:', 'fp-esperienze'); ?></label>
                            <textarea id="cancel-reason" name="cancel_reason" rows="3" placeholder="<?php _e('Reason for cancellation...', 'fp-esperienze'); ?>"></textarea>
                        </p>
                        
                        <p>
                            <button type="submit" class="button button-primary"><?php _e('Confirm Cancellation', 'fp-esperienze'); ?></button>
                            <button type="button" class="button fp-modal-close"><?php _e('Cancel', 'fp-esperienze'); ?></button>
                        </p>
                    </form>
                </div>
            </div>
        </div>
        

        
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
            
            // Reschedule booking
            $('.fp-reschedule-booking').click(function() {
                var bookingId = $(this).data('booking-id');
                var productId = $(this).data('product-id');
                var currentDate = $(this).data('current-date');
                var currentTime = $(this).data('current-time');
                
                $('#reschedule-booking-id').val(bookingId);
                $('#reschedule-product-id').val(productId);
                $('#reschedule-date').val(currentDate);
                
                // Clear previous time slots
                $('#reschedule-time').html('<option value=""><?php _e('Select time slot', 'fp-esperienze'); ?></option>');
                
                $('#fp-reschedule-modal').show();
            });
            
            // Load time slots when date changes
            $('#reschedule-date').change(function() {
                var date = $(this).val();
                var productId = $('#reschedule-product-id').val();
                
                if (!date || !productId) return;
                
                // Load available slots for the selected date
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'fp_get_available_slots',
                        product_id: productId,
                        date: date,
                        nonce: '<?php echo wp_create_nonce('fp_get_slots'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            var timeSelect = $('#reschedule-time');
                            timeSelect.html('<option value="">' + fpEsperienzeAdmin.i18n.selectTimeSlot + '</option>');
                            
                            $.each(response.data.slots, function(index, slot) {
                                if (slot.is_available) {
                                    timeSelect.append('<option value="' + slot.start_time + '">' + 
                                        slot.start_time + ' - ' + slot.end_time + 
                                        ' (' + slot.available + ' ' + fpEsperienzeAdmin.i18n.spotsAvailable + ')</option>');
                                }
                            });
                        } else {
                            alert(response.data.message || fpEsperienzeAdmin.i18n.errorLoadingTimeSlots);
                        }
                    },
                    error: function() {
                        alert(fpEsperienzeAdmin.i18n.errorLoadingTimeSlots);
                    }
                });
            });
            
            // Handle reschedule form submission
            $('#fp-reschedule-form').submit(function(e) {
                e.preventDefault();
                
                var formData = $(this).serialize();
                formData += '&action=fp_reschedule_booking';
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: formData,
                    success: function(response) {
                        if (response.success) {
                            alert(response.data.message);
                            location.reload();
                        } else {
                            alert(response.data.message || fpEsperienzeAdmin.i18n.errorReschedulingBooking);
                        }
                    },
                    error: function() {
                        alert(fpEsperienzeAdmin.i18n.errorReschedulingBooking);
                    }
                });
            });
            
            // Cancel booking
            $('.fp-cancel-booking').click(function() {
                var bookingId = $(this).data('booking-id');
                $('#cancel-booking-id').val(bookingId);
                
                // Check cancellation rules
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'fp_check_cancellation_rules',
                        booking_id: bookingId,
                        nonce: '<?php echo wp_create_nonce('fp_check_cancel'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            var info = '<p>' + response.data.message + '</p>';
                            if (!response.data.can_cancel) {
                                info += '<p><strong>' + fpEsperienzeAdmin.i18n.thisBookingCannotBeCancelled + '</strong></p>';
                                $('#fp-cancel-form').hide();
                            } else {
                                $('#fp-cancel-form').show();
                            }
                            $('#fp-cancel-info').html(info);
                        } else {
                            $('#fp-cancel-info').html('<p><strong>' + fpEsperienzeAdmin.i18n.errorCheckingCancellationRules + '</strong></p>');
                            $('#fp-cancel-form').hide();
                        }
                        $('#fp-cancel-modal').show();
                    },
                    error: function() {
                        alert(fpEsperienzeAdmin.i18n.errorCheckingCancellationRules);
                    }
                });
            });
            
            // Handle cancel form submission
            $('#fp-cancel-form').submit(function(e) {
                e.preventDefault();
                
                if (!confirm(fpEsperienzeAdmin.i18n.confirmCancelBooking)) {
                    return;
                }
                
                var formData = $(this).serialize();
                formData += '&action=fp_cancel_booking';
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: formData,
                    success: function(response) {
                        if (response.success) {
                            alert(response.data.message);
                            location.reload();
                        } else {
                            alert(response.data.message || fpEsperienzeAdmin.i18n.errorCancellingBooking);
                        }
                    },
                    error: function() {
                        alert(fpEsperienzeAdmin.i18n.errorCancellingBooking);
                    }
                });
            });
            
            // Close modals
            $('.fp-modal-close').click(function() {
                $(this).closest('[id$="-modal"]').hide();
            });
            
            // Close modals when clicking outside
            $(window).click(function(event) {
                if ($(event.target).is('#fp-reschedule-modal')) {
                    $('#fp-reschedule-modal').hide();
                }
                if ($(event.target).is('#fp-cancel-modal')) {
                    $('#fp-cancel-modal').hide();
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
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['wp_screen_options'])) {
            if (!CapabilityManager::verifyAdminAction('fp_meeting_points_action', '_wpnonce')) {
                wp_die(__('Security check failed.', 'fp-esperienze'));
            }
            $this->handleMeetingPointAction();
        }

        $action = sanitize_text_field(wp_unslash($_GET['action'] ?? ''));
        $meeting_point_id = absint(wp_unslash($_GET['id'] ?? 0));
        $meeting_point = null;

        if ($action === 'edit' && $meeting_point_id) {
            $meeting_point = MeetingPointManager::getMeetingPoint($meeting_point_id);
            if (!$meeting_point) {
                $action = '';
            }
        }

        $meeting_points = MeetingPointManager::getAllMeetingPoints();

        $search_term = sanitize_text_field(wp_unslash($_GET['s'] ?? ''));
        $view_filter = sanitize_text_field(wp_unslash($_GET['view'] ?? 'all'));
        $allowed_views = ['all', 'with_coords', 'without_coords'];
        if (!in_array($view_filter, $allowed_views, true)) {
            $view_filter = 'all';
        }

        $search_filtered = $meeting_points;
        if ($search_term !== '') {
            $needle = strtolower($search_term);
            $search_filtered = array_filter(
                $meeting_points,
                static function ($point) use ($needle) {
                    $haystack = strtolower(($point->name ?? '') . ' ' . ($point->address ?? ''));
                    return strpos($haystack, $needle) !== false;
                }
            );
        }

        $with_coordinates = array_filter(
            $search_filtered,
            static function ($point) {
                return !empty($point->lat) && !empty($point->lng);
            }
        );
        $total_search_filtered = count($search_filtered);

        $view_counts = [
            'all'            => $total_search_filtered,
            'with_coords'    => count($with_coordinates),
            'without_coords' => $total_search_filtered - count($with_coordinates),
        ];

        $filtered_points = $search_filtered;
        if ('with_coords' === $view_filter) {
            $filtered_points = $with_coordinates;
        } elseif ('without_coords' === $view_filter) {
            $filtered_points = array_filter(
                $search_filtered,
                static function ($point) {
                    return empty($point->lat) || empty($point->lng);
                }
            );
        }

        $filtered_points = array_values($filtered_points);
        $total_filtered = count($filtered_points);
        $per_page = $this->getPerPageValue('fp_meeting_points_per_page', 20);
        $current_page = max(1, absint(wp_unslash($_GET['paged'] ?? 1)));
        $total_pages = max(1, (int) ceil($total_filtered / max(1, $per_page)));
        if ($current_page > $total_pages) {
            $current_page = $total_pages;
        }
        $offset = ($current_page - 1) * $per_page;
        if ($offset < 0) {
            $offset = 0;
        }

        $paged_meeting_points = array_slice($filtered_points, $offset, $per_page);
        $page_count = count($paged_meeting_points);

        $query_args = [
            'page' => 'fp-esperienze-meeting-points',
        ];
        if ($search_term !== '') {
            $query_args['s'] = $search_term;
        }
        if ('all' !== $view_filter) {
            $query_args['view'] = $view_filter;
        }

        $view_base_args = $query_args;
        unset($view_base_args['view'], $view_base_args['paged']);
        $view_base_url = add_query_arg($view_base_args, admin_url('admin.php'));

        $views = [];
        $views['all'] = [
            'key'     => 'all',
            'label'   => __('All', 'fp-esperienze'),
            'count'   => $view_counts['all'] ?? 0,
            'url'     => $view_base_url,
            'current' => 'all' === $view_filter,
        ];
        $views['with_coords'] = [
            'key'     => 'with-coordinates',
            'label'   => __('With coordinates', 'fp-esperienze'),
            'count'   => $view_counts['with_coords'] ?? 0,
            'url'     => add_query_arg('view', 'with_coords', $view_base_url),
            'current' => 'with_coords' === $view_filter,
        ];
        $views['without_coords'] = [
            'key'     => 'without-coordinates',
            'label'   => __('Missing coordinates', 'fp-esperienze'),
            'count'   => $view_counts['without_coords'] ?? 0,
            'url'     => add_query_arg('view', 'without_coords', $view_base_url),
            'current' => 'without_coords' === $view_filter,
        ];

        $pagination_base_url = add_query_arg($query_args, admin_url('admin.php'));
        $pagination_links = paginate_links([
            'base'      => add_query_arg('paged', '%#%', $pagination_base_url),
            'format'    => '',
            'current'   => $current_page,
            'total'     => $total_pages,
            'prev_text' => __('&laquo; Previous', 'fp-esperienze'),
            'next_text' => __('Next &raquo;', 'fp-esperienze'),
        ]);

        $meeting_columns = $this->getMeetingPointsColumnDefinitions();
        $hidden_meeting_columns = $this->getHiddenColumns('fp_meeting_points_hidden_columns');
        $visible_meeting_columns = count($meeting_columns) - count($hidden_meeting_columns);

        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php _e('Meeting Points', 'fp-esperienze'); ?></h1>

            <?php if ($action === 'edit') : ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=fp-esperienze-meeting-points')); ?>" class="page-title-action">
                    <?php _e('Add New', 'fp-esperienze'); ?>
                </a>
            <?php else : ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=fp-esperienze-meeting-points&action=edit')); ?>" class="page-title-action">
                    <?php _e('Add New', 'fp-esperienze'); ?>
                </a>
            <?php endif; ?>

            <hr class="wp-header-end">

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
                            </td>
                        </tr>
                    </table>

                    <?php submit_button($meeting_point ? __('Update Meeting Point', 'fp-esperienze') : __('Add Meeting Point', 'fp-esperienze')); ?>
                </form>
            </div>

            <h2><?php _e('Existing Meeting Points', 'fp-esperienze'); ?></h2>

            <form method="get">
                <input type="hidden" name="page" value="fp-esperienze-meeting-points" />
                <?php if ('all' !== $view_filter) : ?>
                    <input type="hidden" name="view" value="<?php echo esc_attr($view_filter); ?>" />
                <?php endif; ?>
                <p class="search-box">
                    <label class="screen-reader-text" for="meeting-point-search-input"><?php esc_html_e('Search meeting points', 'fp-esperienze'); ?></label>
                    <input type="search" id="meeting-point-search-input" name="s" value="<?php echo esc_attr($search_term); ?>" />
                    <input type="submit" class="button" value="<?php esc_attr_e('Search meeting points', 'fp-esperienze'); ?>" />
                </p>
            </form>

            <?php if (!empty($views)) : ?>
                <ul class="subsubsub">
                    <?php
                    $view_links = [];
                    foreach ($views as $view) {
                        $view_links[] = sprintf(
                            '<li class="%1$s"><a href="%2$s"%3$s>%4$s <span class="count">(%5$s)</span></a></li>',
                            esc_attr($view['key']),
                            esc_url($view['url']),
                            $view['current'] ? ' class="current"' : '',
                            esc_html($view['label']),
                            esc_html(number_format_i18n((int) $view['count']))
                        );
                    }

                    echo implode(' | ', $view_links); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    ?>
                </ul>
            <?php endif; ?>

            <form method="post" id="fp-meeting-points-table-form">
                <?php wp_nonce_field('fp_meeting_points_action'); ?>
                <input type="hidden" name="action" value="bulk-delete" />

                <div class="tablenav top">
                    <div class="alignleft actions bulkactions">
                        <label class="screen-reader-text" for="bulk-action-selector-top"><?php esc_html_e('Select bulk action', 'fp-esperienze'); ?></label>
                        <select name="bulk_action" id="bulk-action-selector-top">
                            <option value=""><?php esc_html_e('Bulk actions', 'fp-esperienze'); ?></option>
                            <option value="delete"><?php esc_html_e('Delete', 'fp-esperienze'); ?></option>
                        </select>
                        <button type="submit" class="button action"><?php esc_html_e('Apply', 'fp-esperienze'); ?></button>
                    </div>
                    <?php if (!empty($pagination_links)) : ?>
                        <div class="tablenav-pages"><?php echo wp_kses_post($pagination_links); ?></div>
                    <?php endif; ?>
                    <br class="clear" />
                </div>

                <table class="wp-list-table widefat fixed striped table-view-list">
                    <thead>
                        <tr>
                            <td id="cb" class="manage-column column-cb check-column">
                                <input type="checkbox" id="cb-select-all-1" />
                            </td>
                            <?php foreach ($meeting_columns as $column_key => $column_definition) : ?>
                                <?php if ('cb' === $column_key || in_array($column_key, $hidden_meeting_columns, true)) { continue; } ?>
                                <th scope="col" class="manage-column column-<?php echo esc_attr($column_key); ?>">
                                    <?php echo esc_html($column_definition['label']); ?>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($page_count === 0) : ?>
                            <tr class="no-items">
                                <td class="colspanchange" colspan="<?php echo esc_attr($visible_meeting_columns); ?>">
                                    <?php esc_html_e('No meeting points match the current filters.', 'fp-esperienze'); ?>
                                </td>
                            </tr>
                        <?php else : ?>
                            <?php foreach ($paged_meeting_points as $point) : ?>
                                <tr>
                                    <th scope="row" class="check-column">
                                        <input type="checkbox" name="meeting_point_ids[]" value="<?php echo esc_attr($point->id); ?>" />
                                    </th>
                                    <?php foreach ($meeting_columns as $column_key => $column_definition) : ?>
                                        <?php if ('cb' === $column_key || in_array($column_key, $hidden_meeting_columns, true)) { continue; } ?>
                                        <td data-colname="<?php echo esc_attr($column_definition['label']); ?>" class="column-<?php echo esc_attr($column_key); ?>">
                                            <?php
                                            switch ($column_key) {
                                                case 'name':
                                                    ?>
                                                    <strong><?php echo esc_html($point->name); ?></strong>
                                                    <div class="row-actions">
                                                        <span class="edit">
                                                            <a href="<?php echo esc_url(admin_url('admin.php?page=fp-esperienze-meeting-points&action=edit&id=' . $point->id)); ?>">
                                                                <?php esc_html_e('Edit', 'fp-esperienze'); ?>
                                                            </a> |
                                                        </span>
                                                        <span class="delete">
                                                            <a href="#" onclick="return confirmDelete(<?php echo esc_js($point->id); ?>, '<?php echo esc_js($point->name); ?>');" class="submitdelete">
                                                                <?php esc_html_e('Delete', 'fp-esperienze'); ?>
                                                            </a>
                                                        </span>
                                                    </div>
                                                    <?php
                                                    break;
                                                case 'address':
                                                    echo esc_html(wp_trim_words($point->address, 15));
                                                    break;
                                                case 'coordinates':
                                                    if (!empty($point->lat) && !empty($point->lng)) {
                                                        echo esc_html($point->lat . ', ' . $point->lng);
                                                    } else {
                                                        esc_html_e('Not set', 'fp-esperienze');
                                                    }
                                                    break;
                                                case 'actions':
                                                    ?>
                                                    <a href="<?php echo esc_url(admin_url('admin.php?page=fp-esperienze-meeting-points&action=edit&id=' . $point->id)); ?>" class="button button-small">
                                                        <?php esc_html_e('Edit', 'fp-esperienze'); ?>
                                                    </a>
                                                    <?php
                                                    break;
                                            }
                                            ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td class="manage-column column-cb check-column">
                                <input type="checkbox" id="cb-select-all-2" />
                            </td>
                            <?php foreach ($meeting_columns as $column_key => $column_definition) : ?>
                                <?php if ('cb' === $column_key || in_array($column_key, $hidden_meeting_columns, true)) { continue; } ?>
                                <th scope="col" class="manage-column column-<?php echo esc_attr($column_key); ?>">
                                    <?php echo esc_html($column_definition['label']); ?>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </tfoot>
                </table>

                <div class="tablenav bottom">
                    <?php if (!empty($pagination_links)) : ?>
                        <div class="tablenav-pages"><?php echo wp_kses_post($pagination_links); ?></div>
                    <?php endif; ?>
                    <br class="clear" />
                </div>
            </form>

            <form id="delete-meeting-point-form" method="post" style="display: none;">
                <?php wp_nonce_field('fp_meeting_points_action'); ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="meeting_point_id" id="delete-meeting-point-id" value="">
            </form>

            <script>
            function confirmDelete(id, name) {
                if (confirm(fpEsperienzeAdmin.i18n.confirmDeleteMeetingPoint + ' "' + name + '"?

' + fpEsperienzeAdmin.i18n.actionCannotBeUndone)) {
                    document.getElementById('delete-meeting-point-id').value = id;
                    document.getElementById('delete-meeting-point-form').submit();
                }
                return false;
            }
            </script>
        </div>
        <?php
    }

    public function extrasPage(): void {
        // Handle form submissions
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fp_extra_nonce'])) {
            $this->handleExtrasActions();
        }
        $extras = ExtraManager::getAllExtras();
        $tax_classes = ExtraManager::getTaxClasses();

        $search_term = sanitize_text_field(wp_unslash($_GET['s'] ?? ''));
        $view_filter = sanitize_text_field(wp_unslash($_GET['view'] ?? 'all'));
        $allowed_views = ['all', 'active', 'inactive', 'required'];
        if (!in_array($view_filter, $allowed_views, true)) {
            $view_filter = 'all';
        }

        $search_filtered = $extras;
        if ($search_term !== '') {
            $needle = strtolower($search_term);
            $search_filtered = array_filter(
                $extras,
                static function ($extra) use ($needle) {
                    $haystack = strtolower(($extra->name ?? '') . ' ' . ($extra->description ?? ''));
                    return strpos($haystack, $needle) !== false;
                }
            );
        }

        $view_counts = [
            'all'      => count($search_filtered),
            'active'   => count(array_filter($search_filtered, static fn($extra) => !empty($extra->is_active))),
            'inactive' => count(array_filter($search_filtered, static fn($extra) => empty($extra->is_active))),
            'required' => count(array_filter($search_filtered, static fn($extra) => !empty($extra->is_required))),
        ];

        $filtered_extras = $search_filtered;
        if ('active' === $view_filter) {
            $filtered_extras = array_filter($search_filtered, static fn($extra) => !empty($extra->is_active));
        } elseif ('inactive' === $view_filter) {
            $filtered_extras = array_filter($search_filtered, static fn($extra) => empty($extra->is_active));
        } elseif ('required' === $view_filter) {
            $filtered_extras = array_filter($search_filtered, static fn($extra) => !empty($extra->is_required));
        }

        $filtered_extras = array_values($filtered_extras);
        $total_filtered = count($filtered_extras);
        $per_page = $this->getPerPageValue('fp_extras_per_page', 20);
        $current_page = max(1, absint(wp_unslash($_GET['paged'] ?? 1)));
        $total_pages = max(1, (int) ceil($total_filtered / max(1, $per_page)));
        if ($current_page > $total_pages) {
            $current_page = $total_pages;
        }
        $offset = ($current_page - 1) * $per_page;
        if ($offset < 0) {
            $offset = 0;
        }

        $paged_extras = array_slice($filtered_extras, $offset, $per_page);
        $page_count = count($paged_extras);

        $query_args = [
            'page' => 'fp-esperienze-addons',
        ];
        if ($search_term !== '') {
            $query_args['s'] = $search_term;
        }
        if ('all' !== $view_filter) {
            $query_args['view'] = $view_filter;
        }

        $view_base_args = $query_args;
        unset($view_base_args['view'], $view_base_args['paged']);
        $view_base_url = add_query_arg($view_base_args, admin_url('admin.php'));

        $views = [
            'all'      => [
                'key'     => 'all',
                'label'   => __('All', 'fp-esperienze'),
                'count'   => $view_counts['all'] ?? 0,
                'url'     => $view_base_url,
                'current' => 'all' === $view_filter,
            ],
            'active'   => [
                'key'     => 'active',
                'label'   => __('Active', 'fp-esperienze'),
                'count'   => $view_counts['active'] ?? 0,
                'url'     => add_query_arg('view', 'active', $view_base_url),
                'current' => 'active' === $view_filter,
            ],
            'inactive' => [
                'key'     => 'inactive',
                'label'   => __('Inactive', 'fp-esperienze'),
                'count'   => $view_counts['inactive'] ?? 0,
                'url'     => add_query_arg('view', 'inactive', $view_base_url),
                'current' => 'inactive' === $view_filter,
            ],
            'required' => [
                'key'     => 'required',
                'label'   => __('Required', 'fp-esperienze'),
                'count'   => $view_counts['required'] ?? 0,
                'url'     => add_query_arg('view', 'required', $view_base_url),
                'current' => 'required' === $view_filter,
            ],
        ];

        $pagination_base_url = add_query_arg($query_args, admin_url('admin.php'));
        $pagination_links = paginate_links([
            'base'      => add_query_arg('paged', '%#%', $pagination_base_url),
            'format'    => '',
            'current'   => $current_page,
            'total'     => $total_pages,
            'prev_text' => __('&laquo; Previous', 'fp-esperienze'),
            'next_text' => __('Next &raquo;', 'fp-esperienze'),
        ]);

        $extras_columns = $this->getExtrasColumnDefinitions();
        $hidden_extras_columns = $this->getHiddenColumns('fp_extras_hidden_columns');
        $visible_extras_columns = count($extras_columns) - count($hidden_extras_columns);

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

            <form method="get">
                <input type="hidden" name="page" value="fp-esperienze-addons" />
                <?php if ('all' !== $view_filter) : ?>
                    <input type="hidden" name="view" value="<?php echo esc_attr($view_filter); ?>" />
                <?php endif; ?>
                <p class="search-box">
                    <label class="screen-reader-text" for="extra-search-input"><?php esc_html_e('Search extras', 'fp-esperienze'); ?></label>
                    <input type="search" id="extra-search-input" name="s" value="<?php echo esc_attr($search_term); ?>" />
                    <input type="submit" class="button" value="<?php esc_attr_e('Search extras', 'fp-esperienze'); ?>" />
                </p>
            </form>

            <?php if (!empty($views)) : ?>
                <ul class="subsubsub">
                    <?php
                    $view_links = [];
                    foreach ($views as $view_key => $view) {
                        $view_links[] = sprintf(
                            '<li class="%1$s"><a href="%2$s"%3$s>%4$s <span class="count">(%5$s)</span></a></li>',
                            esc_attr($view['key']),
                            esc_url($view['url']),
                            $view['current'] ? ' class="current"' : '',
                            esc_html($view['label']),
                            esc_html(number_format_i18n((int) $view['count']))
                        );
                    }

                    echo implode(' | ', $view_links); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    ?>
                </ul>
            <?php endif; ?>

            <form method="post" id="fp-extras-bulk-form">
                <?php wp_nonce_field('fp_extra_action', 'fp_extra_nonce'); ?>
                <input type="hidden" name="action" value="bulk-delete">

                <div class="tablenav top">
                    <div class="alignleft actions bulkactions">
                        <label class="screen-reader-text" for="bulk-action-selector-top"><?php esc_html_e('Select bulk action', 'fp-esperienze'); ?></label>
                        <select name="bulk_action" id="bulk-action-selector-top">
                            <option value=""><?php esc_html_e('Bulk actions', 'fp-esperienze'); ?></option>
                            <option value="delete"><?php esc_html_e('Delete', 'fp-esperienze'); ?></option>
                        </select>
                        <button type="submit" class="button action"><?php esc_html_e('Apply', 'fp-esperienze'); ?></button>
                    </div>
                    <?php if (!empty($pagination_links)) : ?>
                        <div class="tablenav-pages"><?php echo wp_kses_post($pagination_links); ?></div>
                    <?php endif; ?>
                    <br class="clear" />
                </div>

                <table class="wp-list-table widefat fixed striped table-view-list">
                <thead>
                    <tr>
                        <td id="cb" class="manage-column column-cb check-column">
                            <input type="checkbox" id="cb-select-all-1" />
                        </td>
                        <?php foreach ($extras_columns as $column_key => $column_definition) : ?>
                            <?php if ('cb' === $column_key || in_array($column_key, $hidden_extras_columns, true)) { continue; } ?>
                            <th scope="col" class="manage-column column-<?php echo esc_attr($column_key); ?>">
                                <?php echo esc_html($column_definition['label']); ?>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($page_count === 0) : ?>
                        <tr class="no-items">
                            <td class="colspanchange" colspan="<?php echo esc_attr($visible_extras_columns); ?>"><?php esc_html_e('No extras match the current filters.', 'fp-esperienze'); ?></td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($paged_extras as $extra) : ?>
                            <tr>
                                <th scope="row" class="check-column">
                                    <input type="checkbox" name="extra_ids[]" value="<?php echo esc_attr($extra->id); ?>" />
                                </th>
                                <?php foreach ($extras_columns as $column_key => $column_definition) : ?>
                                    <?php if ('cb' === $column_key || in_array($column_key, $hidden_extras_columns, true)) { continue; } ?>
                                    <td data-colname="<?php echo esc_attr($column_definition['label']); ?>" class="column-<?php echo esc_attr($column_key); ?>">
                                        <?php
                                        switch ($column_key) {
                                            case 'name':
                                                echo '<strong>' . esc_html($extra->name) . '</strong>';
                                                break;
                                            case 'description':
                                                echo esc_html(wp_trim_words($extra->description, 15));
                                                break;
                                            case 'price':
                                                if (function_exists('wc_price')) {
                                                    echo wp_kses_post(wc_price((float) $extra->price));
                                                } else {
                                                    echo esc_html(number_format_i18n((float) $extra->price, 2));
                                                }
                                                break;
                                            case 'billing':
                                                echo esc_html($extra->billing_type === 'per_person' ? __('Per Person', 'fp-esperienze') : __('Per Booking', 'fp-esperienze'));
                                                break;
                                            case 'tax_class':
                                                echo esc_html($tax_classes[$extra->tax_class] ?? __('Standard', 'fp-esperienze'));
                                                break;
                                            case 'max_qty':
                                                echo esc_html(number_format_i18n((int) $extra->max_quantity));
                                                break;
                                            case 'required':
                                                echo !empty($extra->is_required)
                                                    ? esc_html__('Yes', 'fp-esperienze')
                                                    : esc_html__('No', 'fp-esperienze');
                                                break;
                                            case 'active':
                                                echo !empty($extra->is_active)
                                                    ? esc_html__('Active', 'fp-esperienze')
                                                    : esc_html__('Inactive', 'fp-esperienze');
                                                break;
                                            case 'actions':
                                                ?>
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
                                                <button type="button"
                                                        class="button button-small button-link-delete fp-delete-extra"
                                                        data-extra-id="<?php echo esc_attr($extra->id); ?>"
                                                        data-extra-name="<?php echo esc_attr($extra->name); ?>">
                                                    <?php esc_html_e('Delete', 'fp-esperienze'); ?>
                                                </button>
                                                <?php
                                                break;
                                        }
                                        ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td class="manage-column column-cb check-column">
                            <input type="checkbox" id="cb-select-all-2" />
                        </td>
                        <?php foreach ($extras_columns as $column_key => $column_definition) : ?>
                            <?php if ('cb' === $column_key || in_array($column_key, $hidden_extras_columns, true)) { continue; } ?>
                            <th scope="col" class="manage-column column-<?php echo esc_attr($column_key); ?>">
                                <?php echo esc_html($column_definition['label']); ?>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </tfoot>
                </table>

                <div class="tablenav bottom">
                    <?php if (!empty($pagination_links)) : ?>
                        <div class="tablenav-pages"><?php echo wp_kses_post($pagination_links); ?></div>
                    <?php endif; ?>
                    <br class="clear" />
                </div>
            </form>

            <form id="fp-extras-delete-form" method="post" style="display: none;">
                <?php wp_nonce_field('fp_extra_action', 'fp_extra_nonce'); ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="extra_id" id="fp-delete-extra-id" value="">
            </form>
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
                tb_show(fpEsperienzeAdmin.i18n.editExtra, '#TB_inline?inlineId=fp-edit-extra-modal&width=600&height=500');
            });

            // Submit edit form
            $('#fp-edit-extra-form').submit(function() {
                tb_remove();
            });

            // Delete extra functionality
            $('.fp-delete-extra').on('click', function() {
                var extraId = $(this).data('extraId');
                var extraName = $(this).data('extraName') || '';
                var template = fpEsperienzeAdmin.i18n.confirmDeleteExtra || 'Are you sure you want to delete this extra?';
                var message = template.replace('%s', extraName);

                if (confirm(message)) {
                    $('#fp-delete-extra-id').val(extraId);
                    $('#fp-extras-delete-form').trigger('submit');
                }
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
        if ($_POST && CapabilityManager::canManageFPEsperienze()) {
            $this->handleVoucherActions();
        }
        
        // Pagination setup
        $per_page = 20;
        $current_page = max(1, absint(wp_unslash($_GET['paged'] ?? 1)));
        $offset = ($current_page - 1) * $per_page;
        
        // Get filters
        $status_filter = sanitize_text_field(wp_unslash($_GET['status'] ?? ''));
        $product_filter = absint(wp_unslash($_GET['product_id'] ?? 0));
        $date_from = sanitize_text_field(wp_unslash($_GET['date_from'] ?? ''));
        $date_to = sanitize_text_field(wp_unslash($_GET['date_to'] ?? ''));
        $search = sanitize_text_field(wp_unslash($_GET['search'] ?? ''));
        
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
            // Use prepared statement even when no parameters to ensure security
            $total_items = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}fp_exp_vouchers WHERE 1=1"));
        }
        
        // Get vouchers
        $vouchers_query = "SELECT id, order_id, voucher_code, amount, recipient_name, recipient_email, sender_name, sender_email, message, status, created_at, expires_at FROM $table_name WHERE $where_clause ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $all_params = array_merge($query_params, [$per_page, $offset]);
        $vouchers = $wpdb->get_results($wpdb->prepare($vouchers_query, ...$all_params));
        
        // Calculate pagination
        $total_pages = ceil($total_items / $per_page);
        
        // Get experience products for filter
        $experience_products = $this->getExperienceProducts($product_filter);
        
        ?>
        <?php AdminComponents::skipLink('fp-admin-main-content'); ?>
        <div class="wrap fp-admin-page" id="fp-admin-main-content" tabindex="-1">
            <?php
            AdminComponents::pageHeader([
                'title' => __('Gift Vouchers', 'fp-esperienze'),
                'lead'  => __('Monitor voucher usage, resend delivery emails, and keep expirations aligned with your policies.', 'fp-esperienze'),
                'actions' => [
                    [
                        'label'   => __('Open reports', 'fp-esperienze'),
                        'url'     => admin_url('admin.php?page=fp-esperienze-reports'),
                        'variant' => 'secondary',
                    ],
                ],
            ]);
            ?>

            <div class="fp-admin-stack">
                <?php
                AdminComponents::openCard([
                    'title' => __('Filter vouchers', 'fp-esperienze'),
                ]);
                ?>
                <form method="get" class="fp-admin-form fp-admin-form--filters">
                    <input type="hidden" name="page" value="fp-esperienze-gift-vouchers" />
                    <?php
                    AdminComponents::formRow([
                        'label' => __('Status', 'fp-esperienze'),
                        'for'   => 'fp-voucher-status',
                    ], function () use ($status_filter) {
                        ?>
                        <select id="fp-voucher-status" name="status">
                            <option value=""><?php esc_html_e('All statuses', 'fp-esperienze'); ?></option>
                            <option value="active" <?php selected($status_filter, 'active'); ?>><?php esc_html_e('Active', 'fp-esperienze'); ?></option>
                            <option value="redeemed" <?php selected($status_filter, 'redeemed'); ?>><?php esc_html_e('Redeemed', 'fp-esperienze'); ?></option>
                            <option value="expired" <?php selected($status_filter, 'expired'); ?>><?php esc_html_e('Expired', 'fp-esperienze'); ?></option>
                            <option value="void" <?php selected($status_filter, 'void'); ?>><?php esc_html_e('Void', 'fp-esperienze'); ?></option>
                        </select>
                        <?php
                    });

                    AdminComponents::formRow([
                        'label' => __('Experience', 'fp-esperienze'),
                        'for'   => 'fp-voucher-product',
                    ], function () use ($experience_products, $product_filter) {
                        ?>
                        <select id="fp-voucher-product" name="product_id" class="fp-product-search" data-placeholder="<?php esc_attr_e('All products', 'fp-esperienze'); ?>">
                            <option value=""><?php esc_html_e('All products', 'fp-esperienze'); ?></option>
                            <?php foreach ($experience_products as $product) : ?>
                                <option value="<?php echo esc_attr($product->ID); ?>" <?php selected($product_filter, $product->ID); ?>>
                                    <?php echo esc_html($product->post_title); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php
                    });

                    AdminComponents::formRow([
                        'label' => __('From date', 'fp-esperienze'),
                        'for'   => 'fp-voucher-date-from',
                    ], function () use ($date_from) {
                        ?>
                        <input type="date" id="fp-voucher-date-from" name="date_from" value="<?php echo esc_attr($date_from); ?>" />
                        <?php
                    });

                    AdminComponents::formRow([
                        'label' => __('To date', 'fp-esperienze'),
                        'for'   => 'fp-voucher-date-to',
                    ], function () use ($date_to) {
                        ?>
                        <input type="date" id="fp-voucher-date-to" name="date_to" value="<?php echo esc_attr($date_to); ?>" />
                        <?php
                    });

                    AdminComponents::formRow([
                        'label' => __('Search', 'fp-esperienze'),
                        'for'   => 'fp-voucher-search',
                        'description' => __('Code, recipient name or email', 'fp-esperienze'),
                    ], function () use ($search) {
                        ?>
                        <input type="search" id="fp-voucher-search" name="search" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Search vouchers…', 'fp-esperienze'); ?>" />
                        <?php
                    });

                    ?>
                    <div class="fp-admin-form__actions">
                        <button type="submit" class="button button-primary"><?php esc_html_e('Apply filters', 'fp-esperienze'); ?></button>
                        <a class="button button-secondary" href="<?php echo esc_url(admin_url('admin.php?page=fp-esperienze-gift-vouchers')); ?>"><?php esc_html_e('Reset', 'fp-esperienze'); ?></a>
                    </div>
                </form>
                <?php AdminComponents::closeCard(); ?>

                <?php
                $voucher_count_label = sprintf(
                    _n('%d voucher', '%d vouchers', (int) $total_items, 'fp-esperienze'),
                    (int) $total_items
                );

                AdminComponents::openCard([
                    'title' => __('Voucher catalogue', 'fp-esperienze'),
                    'meta'  => [$voucher_count_label],
                ]);
                ?>
                <form method="post" class="fp-admin-table-form fp-admin-vouchers-form">
                    <?php wp_nonce_field('bulk_voucher_action', 'bulk_nonce'); ?>
                    <input type="hidden" name="page" value="fp-esperienze-gift-vouchers" />
                    <?php
                    AdminComponents::toolbar([
                        'title' => __('Bulk actions', 'fp-esperienze'),
                        'aria_label' => __('Voucher bulk tools', 'fp-esperienze'),
                        'start' => [
                            function () {
                                ?>
                                <label class="screen-reader-text" for="fp-voucher-bulk-action"><?php esc_html_e('Select bulk action', 'fp-esperienze'); ?></label>
                                <select id="fp-voucher-bulk-action" name="bulk_action">
                                    <option value=""><?php esc_html_e('Bulk actions', 'fp-esperienze'); ?></option>
                                    <option value="bulk_void"><?php esc_html_e('Void vouchers', 'fp-esperienze'); ?></option>
                                    <option value="bulk_resend"><?php esc_html_e('Resend emails', 'fp-esperienze'); ?></option>
                                    <option value="bulk_extend"><?php esc_html_e('Extend expiration', 'fp-esperienze'); ?></option>
                                </select>
                                <span class="fp-admin-bulk-extend" id="fp-voucher-bulk-extend" hidden>
                                    <label class="screen-reader-text" for="fp-voucher-bulk-extend-months"><?php esc_html_e('Months to extend', 'fp-esperienze'); ?></label>
                                    <input type="number" id="fp-voucher-bulk-extend-months" name="bulk_extend_months" class="small-text" min="1" max="60" value="12" />
                                    <span class="fp-admin-helper-text"><?php esc_html_e('months', 'fp-esperienze'); ?></span>
                                </span>
                                <?php
                            },
                        ],
                        'end' => [
                            function () use ($total_pages, $current_page, $total_items) {
                                ?>
                                <span class="fp-admin-helper-text"><?php echo esc_html(sprintf(__('Page %1$d of %2$d • %3$d total', 'fp-esperienze'), max(1, (int) $current_page), max(1, (int) $total_pages), (int) $total_items)); ?></span>
                                <?php
                            },
                        ],
                    ]);
                    ?>
                    <div class="fp-admin-table-actions">
                        <button type="submit" class="button button-secondary" data-action="submit-bulk"><?php esc_html_e('Run action', 'fp-esperienze'); ?></button>
                    </div>

                    <table class="wp-list-table widefat fixed striped fp-admin-table fp-admin-table--striped">
                        <thead>
                            <tr>
                                <td class="manage-column column-cb check-column">
                                    <input type="checkbox" id="fp-vouchers-select-all" />
                                </td>
                                <th scope="col"><?php esc_html_e('Code', 'fp-esperienze'); ?></th>
                                <th scope="col"><?php esc_html_e('Product', 'fp-esperienze'); ?></th>
                                <th scope="col"><?php esc_html_e('Recipient', 'fp-esperienze'); ?></th>
                                <th scope="col"><?php esc_html_e('Value', 'fp-esperienze'); ?></th>
                                <th scope="col"><?php esc_html_e('Status', 'fp-esperienze'); ?></th>
                                <th scope="col"><?php esc_html_e('Expires', 'fp-esperienze'); ?></th>
                                <th scope="col"><?php esc_html_e('Created', 'fp-esperienze'); ?></th>
                                <th scope="col"><?php esc_html_e('Actions', 'fp-esperienze'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($vouchers)) : ?>
                                <tr>
                                    <td colspan="9">
                                        <?php
                                        AdminComponents::notice([
                                            'type'    => 'info',
                                            'title'   => __('No vouchers found', 'fp-esperienze'),
                                            'message' => __('Adjust the filters or create a voucher from the storefront checkout.', 'fp-esperienze'),
                                        ]);
                                        ?>
                                    </td>
                                </tr>
                            <?php else : ?>
                                <?php foreach ($vouchers as $voucher) :
                                    $product      = wc_get_product($voucher->product_id);
                                    $product_name = $product ? $product->get_name() : __('Product not found', 'fp-esperienze');
                                    $value_display = $voucher->amount_type === 'full'
                                        ? __('Full experience', 'fp-esperienze')
                                        : wc_price($voucher->amount);
                                    $status_key = (string) $voucher->status;
                                    $status_variant = [
                                        'active'   => 'success',
                                        'redeemed' => 'info',
                                        'expired'  => 'warning',
                                        'void'     => 'danger',
                                    ][$status_key] ?? 'warning';
                                    $download_url = admin_url('admin.php?page=fp-esperienze-gift-vouchers&action=download_pdf&voucher_id=' . (int) $voucher->id);
                                    ?>
                                    <tr>
                                        <th scope="row" class="check-column">
                                            <input type="checkbox" name="voucher_ids[]" value="<?php echo esc_attr((string) $voucher->id); ?>" />
                                        </th>
                                        <td>
                                            <strong><?php echo esc_html($voucher->code); ?></strong>
                                        </td>
                                        <td>
                                            <span><?php echo esc_html($product_name); ?></span>
                                            <?php if (!empty($voucher->order_id)) : ?>
                                                <br />
                                                <a class="fp-admin-helper-text" href="<?php echo esc_url(admin_url('post.php?post=' . (int) $voucher->order_id . '&action=edit')); ?>">
                                                    <?php printf(esc_html__('Order #%d', 'fp-esperienze'), (int) $voucher->order_id); ?>
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong><?php echo esc_html($voucher->recipient_name); ?></strong><br />
                                            <span class="fp-admin-helper-text"><?php echo esc_html($voucher->recipient_email); ?></span>
                                            <?php if (!empty($voucher->sender_name)) : ?>
                                                <br />
                                                <span class="fp-admin-helper-text"><?php printf(esc_html__('From: %s', 'fp-esperienze'), esc_html($voucher->sender_name)); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo wp_kses_post($value_display); ?></td>
                                        <td>
                                            <span class="fp-admin-badge fp-admin-badge--<?php echo esc_attr($status_variant); ?>">
                                                <?php echo esc_html(ucfirst($status_key)); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span><?php echo esc_html(\fp_esperienze_wp_date(get_option('date_format'), strtotime((string) $voucher->expires_on))); ?></span>
                                            <?php if (strtotime((string) $voucher->expires_on) < time() && $status_key === 'active') : ?>
                                                <br />
                                                <span class="fp-admin-helper-text fp-admin-helper-text--danger"><?php esc_html_e('Expired', 'fp-esperienze'); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo esc_html(\fp_esperienze_wp_date(get_option('date_format'), strtotime((string) $voucher->created_at))); ?></td>
                                        <td>
                                            <div class="fp-admin-table-actions">
                                                <?php if (!empty($voucher->pdf_path) && file_exists($voucher->pdf_path)) : ?>
                                                    <a class="button button-secondary button-small" href="<?php echo esc_url($download_url); ?>">
                                                        <?php esc_html_e('Download PDF', 'fp-esperienze'); ?>
                                                    </a>
                                                    <button type="button" class="button button-small fp-voucher-copy" data-download-url="<?php echo esc_url($download_url); ?>">
                                                        <?php esc_html_e('Copy link', 'fp-esperienze'); ?>
                                                    </button>
                                                <?php endif; ?>

                                                <?php if ($status_key === 'active') : ?>
                                                    <form method="post" class="fp-voucher-action-form" data-confirm="resendVoucherEmail">
                                                        <?php wp_nonce_field('fp_voucher_action', 'fp_voucher_nonce'); ?>
                                                        <input type="hidden" name="action" value="resend_voucher" />
                                                        <input type="hidden" name="voucher_id" value="<?php echo esc_attr((string) $voucher->id); ?>" />
                                                        <button type="submit" class="button button-secondary button-small"><?php esc_html_e('Resend', 'fp-esperienze'); ?></button>
                                                    </form>

                                                    <form method="post" class="fp-voucher-action-form" data-confirm="extendVoucherExpiration">
                                                        <?php wp_nonce_field('fp_voucher_action', 'fp_voucher_nonce'); ?>
                                                        <input type="hidden" name="action" value="extend_voucher" />
                                                        <input type="hidden" name="voucher_id" value="<?php echo esc_attr((string) $voucher->id); ?>" />
                                                        <label class="screen-reader-text" for="fp-extend-<?php echo esc_attr((string) $voucher->id); ?>"><?php esc_html_e('Months to extend', 'fp-esperienze'); ?></label>
                                                        <input type="number" id="fp-extend-<?php echo esc_attr((string) $voucher->id); ?>" name="extend_months" class="small-text" min="1" max="60" value="12" />
                                                        <button type="submit" class="button button-secondary button-small"><?php esc_html_e('Extend', 'fp-esperienze'); ?></button>
                                                    </form>

                                                    <form method="post" class="fp-voucher-action-form" data-confirm="voidVoucher">
                                                        <?php wp_nonce_field('fp_voucher_action', 'fp_voucher_nonce'); ?>
                                                        <input type="hidden" name="action" value="void_voucher" />
                                                        <input type="hidden" name="voucher_id" value="<?php echo esc_attr((string) $voucher->id); ?>" />
                                                        <button type="submit" class="button button-link-delete button-small"><?php esc_html_e('Void', 'fp-esperienze'); ?></button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </form>

                <?php
                if ($total_pages > 1) {
                    $page_links = paginate_links([
                        'base'      => add_query_arg('paged', '%#%'),
                        'format'    => '',
                        'current'   => max(1, (int) $current_page),
                        'total'     => max(1, (int) $total_pages),
                        'prev_text' => __('Previous', 'fp-esperienze'),
                        'next_text' => __('Next', 'fp-esperienze'),
                        'type'      => 'array',
                    ]);

                    if (!empty($page_links)) {
                        echo '<nav class="fp-admin-pagination" aria-label="' . esc_attr__('Voucher pagination', 'fp-esperienze') . '">';
                        foreach ($page_links as $link) {
                            echo wp_kses_post($link);
                        }
                        echo '</nav>';
                    }
                }

                AdminComponents::closeCard();
                ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Handle voucher actions
     */
    private function handleVoucherActions(): void {
        // Check permissions first
        if (!CapabilityManager::canManageFPEsperienze()) {
            wp_die(__('You do not have permission to perform this action.', 'fp-esperienze'));
        }
        
        // Handle bulk actions
        if (isset($_POST['bulk_action']) && !empty($_POST['voucher_ids'])) {
            $this->handleBulkVoucherActions();
            return;
        }
        
        // Handle individual actions
        if (!wp_verify_nonce(wp_unslash($_POST['fp_voucher_nonce'] ?? ''), 'fp_voucher_action')) {
            wp_die(__('Security check failed.', 'fp-esperienze'));
        }
        
        $action = sanitize_text_field(wp_unslash($_POST['action'] ?? ''));
        $voucher_id = absint(wp_unslash($_POST['voucher_id'] ?? 0));
        
        switch ($action) {
            case 'void_voucher':
                $this->voidVoucher($voucher_id);
                break;
            case 'resend_voucher':
                $this->resendVoucherEmail($voucher_id);
                break;
            case 'extend_voucher':
                $extend_months = absint(wp_unslash($_POST['extend_months'] ?? 0));
                $this->extendVoucher($voucher_id, $extend_months);
                break;
        }
        
        // Handle GET actions (PDF download, copy link)
        if (isset($_GET['action'])) {
            $get_action = sanitize_text_field(wp_unslash($_GET['action']));
            $voucher_id = absint(wp_unslash($_GET['voucher_id'] ?? 0));
            
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
            "SELECT order_id, voucher_code, amount, recipient_name, recipient_email, sender_name, sender_email, message, expires_at FROM $table_name WHERE id = %d",
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
                $pdf_path = Voucher_Pdf::generate($voucher);
                
                // Update voucher with new PDF path
                $wpdb->update(
                    $table_name,
                    ['pdf_path' => $pdf_path],
                    ['id' => $voucher_id]
                );
            }
            
            // Send email using VoucherManager
            $voucher_manager = new VoucherManager();
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
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('FP Esperienze: Failed to resend voucher email: ' . $e->getMessage());
            }
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
            "SELECT id, order_id, voucher_code, amount, recipient_name, recipient_email, sender_name, sender_email, message, status, created_at, expires_at FROM $table_name WHERE id = %d",
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
                     sprintf(esc_html__('Voucher expiration extended to %s.', 'fp-esperienze'), \fp_esperienze_wp_date(get_option('date_format'), strtotime($new_expiry_date))) . 
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
        if (!wp_verify_nonce(wp_unslash($_POST['bulk_nonce'] ?? ''), 'bulk_voucher_action')) {
            return;
        }

        $action = sanitize_text_field(wp_unslash($_POST['bulk_action']));
        $voucher_ids = array_map('absint', wp_unslash($_POST['voucher_ids']));
        
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
                $extend_months = absint(wp_unslash($_POST['bulk_extend_months'] ?? 0));
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
            "SELECT pdf_path FROM $table_name WHERE id = %d",
            $voucher_id
        ));
        
        if (!$voucher || empty($voucher->pdf_path) || !file_exists($voucher->pdf_path)) {
            wp_die(__('PDF not found.', 'fp-esperienze'));
        }
        
        // Return the PDF download URL as JSON for JavaScript to handle
        $download_url = admin_url('admin.php?page=fp-esperienze-gift-vouchers&action=download_pdf&voucher_id=' . $voucher_id);
        
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
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                'FP Esperienze Voucher Action: ID=%d, Action=%s, User=%s, Description=%s',
                $voucher_id,
                $action,
                $user_info,
                $description
            ));
        }
        
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
            "SELECT voucher_code, pdf_path FROM $table_name WHERE id = %d",
            $voucher_id
        ));

        if (!$voucher || empty($voucher->pdf_path)) {
            wp_die(__('PDF not found.', 'fp-esperienze'));
        }

        $real_path = realpath($voucher->pdf_path);
        $uploads   = wp_upload_dir();
        $basedir   = trailingslashit($uploads['basedir']);

        if (!$real_path || strpos($real_path, $basedir) !== 0) {
            wp_die(__('PDF not found.', 'fp-esperienze'));
        }

        $filename = 'voucher-' . $voucher->voucher_code . '.pdf';

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($real_path));

        readfile($real_path);
        exit;
    }

    /**
     * Queue a notice to be rendered inside the current admin page.
     */
    private function queuePageNotice(string $message, string $type = 'info', ?string $title = null): void {
        $message = trim($message);

        if ($message === '') {
            return;
        }

        $variant = in_array($type, ['info', 'success', 'warning', 'danger'], true) ? $type : 'info';

        $this->queuedPageNotices[] = [
            'message' => $message,
            'type'    => $variant,
            'title'   => $title !== null && $title !== '' ? $title : null,
        ];
    }

    /**
     * Render queued component notices and reset the stack.
     */
    private function renderQueuedPageNotices(): void {
        if ($this->queuedPageNotices === []) {
            return;
        }

        foreach ($this->queuedPageNotices as $notice) {
            AdminComponents::notice([
                'type'    => $notice['type'] ?? 'info',
                'message' => esc_html((string) ($notice['message'] ?? '')),
                'title'   => isset($notice['title']) && $notice['title'] !== null
                    ? esc_html((string) $notice['title'])
                    : null,
            ]);
        }

        $this->queuedPageNotices = [];
    }

    /**
     * Closures page
     */
    public function closuresPage(): void {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handleClosuresActions();
        }

        $closures = OverrideManager::getGlobalClosures();
        $closure_count = count($closures);
        $meta = [];

        if ($closure_count > 0) {
            $meta[] = sprintf(
                _n('%s closure scheduled', '%s closures scheduled', $closure_count, 'fp-esperienze'),
                number_format_i18n($closure_count)
            );
        }

        ?>
        <?php AdminComponents::skipLink('fp-admin-main-content'); ?>
        <div class="wrap fp-admin-page" id="fp-admin-main-content" tabindex="-1">
            <?php
            AdminComponents::pageHeader([
                'title' => __('Availability & Closures', 'fp-esperienze'),
                'lead'  => __('Schedule global downtime for every published experience and keep the removal history transparent for operators.', 'fp-esperienze'),
                'meta'  => $meta,
                'actions' => [
                    [
                        'label'   => __('View bookings calendar', 'fp-esperienze'),
                        'url'     => admin_url('admin.php?page=fp-esperienze-bookings'),
                        'variant' => 'secondary',
                    ],
                ],
            ]);
            ?>

            <div class="fp-admin-stack">
                <?php $this->renderQueuedPageNotices(); ?>

                <?php
                AdminComponents::openCard([
                    'title' => __('Add global closure', 'fp-esperienze'),
                ]);
                ?>
                <form method="post" class="fp-admin-form" novalidate>
                    <?php wp_nonce_field('fp_closure_action', 'fp_closure_nonce'); ?>
                    <input type="hidden" name="action" value="add_closure" />

                    <?php
                    AdminComponents::formRow(
                        [
                            'label'       => __('Date', 'fp-esperienze'),
                            'for'         => 'fp-closure-date',
                            'required'    => true,
                            'description' => __('Select the calendar date to close for all experiences.', 'fp-esperienze'),
                        ],
                        '<input type="date" id="fp-closure-date" name="closure_date" required>'
                    );

                    AdminComponents::formRow(
                        [
                            'label'       => __('Reason', 'fp-esperienze'),
                            'for'         => 'fp-closure-reason',
                            'description' => __('Optional note that will appear in integrations and operator exports.', 'fp-esperienze'),
                        ],
                        '<input type="text" id="fp-closure-reason" name="closure_reason" class="regular-text">'
                    );
                    ?>

                    <div class="fp-admin-form__actions">
                        <?php submit_button(__('Add Global Closure', 'fp-esperienze'), 'primary', 'submit', false); ?>
                    </div>
                </form>
                <?php AdminComponents::closeCard(); ?>

                <?php
                AdminComponents::openCard([
                    'title' => __('Scheduled closures', 'fp-esperienze'),
                    'meta'  => $meta,
                    'muted' => $closure_count === 0,
                ]);
                ?>

                <?php if ($closure_count === 0) : ?>
                    <?php
                    AdminComponents::notice([
                        'type'    => 'info',
                        'message' => __('No global closures are scheduled. Use the form above to block availability on specific dates.', 'fp-esperienze'),
                    ]);
                    ?>
                <?php else : ?>
                    <table class="fp-admin-table fp-admin-table--striped">
                        <caption class="screen-reader-text"><?php esc_html_e('List of scheduled global closures', 'fp-esperienze'); ?></caption>
                        <thead>
                            <tr>
                                <th scope="col"><?php esc_html_e('Date', 'fp-esperienze'); ?></th>
                                <th scope="col"><?php esc_html_e('Experience', 'fp-esperienze'); ?></th>
                                <th scope="col"><?php esc_html_e('Reason', 'fp-esperienze'); ?></th>
                                <th scope="col" class="column-actions"><?php esc_html_e('Actions', 'fp-esperienze'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($closures as $closure) :
                                $raw_date = $closure->date ?? '';
                                $timestamp = $raw_date !== '' ? strtotime($raw_date) : false;
                                $date_display = $timestamp ? \fp_esperienze_wp_date(get_option('date_format'), $timestamp) : $raw_date;
                                $datetime_attr = $timestamp
                                    ? (function_exists('wp_date') ? wp_date('Y-m-d', $timestamp) : date_i18n('Y-m-d', $timestamp))
                                    : $raw_date;
                                $experience_name = $closure->product_name ?: __('Unknown Product', 'fp-esperienze');
                                $aria_label = sprintf(
                                    __('Remove closure scheduled on %1$s for %2$s', 'fp-esperienze'),
                                    $date_display,
                                    $experience_name
                                );
                                ?>
                                <tr>
                                    <td>
                                        <?php if ($date_display !== '') : ?>
                                            <time datetime="<?php echo esc_attr($datetime_attr); ?>"><?php echo esc_html($date_display); ?></time>
                                        <?php else : ?>
                                            <?php echo esc_html($raw_date); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo esc_html($experience_name); ?></td>
                                    <td><?php echo $closure->reason !== '' ? esc_html($closure->reason) : '&mdash;'; ?></td>
                                    <td>
                                        <form method="post" class="fp-admin-inline-form" data-fp-closure-remove>
                                            <?php wp_nonce_field('fp_closure_action', 'fp_closure_nonce'); ?>
                                            <input type="hidden" name="action" value="remove_closure" />
                                            <input type="hidden" name="closure_date" value="<?php echo esc_attr($raw_date); ?>" />
                                            <button type="submit" class="button button-link-delete" aria-label="<?php echo esc_attr($aria_label); ?>">
                                                <?php esc_html_e('Remove', 'fp-esperienze'); ?>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

                <?php AdminComponents::closeCard(); ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Handle extras actions
     */
    private function handleExtrasActions(): void {
        if (!isset($_POST['fp_extra_nonce']) || !wp_verify_nonce(wp_unslash($_POST['fp_extra_nonce']), 'fp_extra_action')) {
            wp_die(__('Security check failed.', 'fp-esperienze'));
        }

        if (!CapabilityManager::canManageFPEsperienze()) {
            wp_die(__('You do not have permission to perform this action.', 'fp-esperienze'));
        }
        
        $action = sanitize_text_field(wp_unslash($_POST['action'] ?? ''));

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

            case 'bulk-delete':
                $bulk_action = sanitize_text_field(wp_unslash($_POST['bulk_action'] ?? ''));
                if ('delete' !== $bulk_action) {
                    add_action('admin_notices', static function () {
                        echo '<div class="notice notice-warning is-dismissible"><p>' .
                             esc_html__('Please choose Delete from the bulk actions dropdown before applying.', 'fp-esperienze') .
                             '</p></div>';
                    });
                    break;
                }

                $raw_ids = isset($_POST['extra_ids']) ? (array) wp_unslash($_POST['extra_ids']) : [];
                $this->bulkDeleteExtras($raw_ids);
                break;
        }
    }

    /**
     * Bulk delete extras by ID.
     *
     * @param array $raw_ids Array of raw IDs from the request.
     * @return void
     */
    private function bulkDeleteExtras(array $raw_ids): void {
        $ids = array_values(array_unique(array_filter(array_map('absint', $raw_ids))));

        if (empty($ids)) {
            add_action('admin_notices', static function () {
                echo '<div class="notice notice-warning is-dismissible"><p>' .
                     esc_html__('Select at least one extra before applying the bulk delete action.', 'fp-esperienze') .
                     '</p></div>';
            });
            return;
        }

        $deleted = 0;

        foreach ($ids as $id) {
            if ($id > 0 && ExtraManager::deleteExtra($id)) {
                $deleted++;
            }
        }

        if ($deleted > 0) {
            $deleted_message = sprintf(
                _n('%d extra deleted successfully.', '%d extras deleted successfully.', $deleted, 'fp-esperienze'),
                $deleted
            );

            add_action('admin_notices', static function () use ($deleted_message) {
                echo '<div class="notice notice-success is-dismissible"><p>' .
                     esc_html($deleted_message) .
                     '</p></div>';
            });
        }

        if ($deleted < count($ids)) {
            $failed = count($ids) - $deleted;

            $failed_message = sprintf(
                _n(
                    '%d extra could not be deleted. It may still be assigned to products.',
                    '%d extras could not be deleted. They may still be assigned to products.',
                    $failed,
                    'fp-esperienze'
                ),
                $failed
            );

            add_action('admin_notices', static function () use ($failed_message) {
                echo '<div class="notice notice-error is-dismissible"><p>' .
                     esc_html($failed_message) .
                     '</p></div>';
            });
        }
    }

    /**
     * Create new extra
     */
    private function createExtra(): void {
        $data = [
            'name' => sanitize_text_field(wp_unslash($_POST['extra_name'] ?? '')),
            'description' => sanitize_textarea_field(wp_unslash($_POST['extra_description'] ?? '')),
            'price' => floatval(wp_unslash($_POST['extra_price'] ?? 0)),
            'billing_type' => in_array(wp_unslash($_POST['extra_billing_type'] ?? ''), ['per_person', 'per_booking']) ? wp_unslash($_POST['extra_billing_type'] ?? '') : 'per_person',
            'tax_class' => sanitize_text_field(wp_unslash($_POST['extra_tax_class'] ?? '')),
            'max_quantity' => absint(wp_unslash($_POST['extra_max_quantity'] ?? 1)),
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
        $id = absint(wp_unslash($_POST['extra_id'] ?? 0));
        
        if (!$id) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error is-dismissible"><p>' . 
                     esc_html__('Invalid extra ID.', 'fp-esperienze') . 
                     '</p></div>';
            });
            return;
        }
        
        $data = [
            'name' => sanitize_text_field(wp_unslash($_POST['extra_name'] ?? '')),
            'description' => sanitize_textarea_field(wp_unslash($_POST['extra_description'] ?? '')),
            'price' => floatval(wp_unslash($_POST['extra_price'] ?? 0)),
            'billing_type' => in_array(wp_unslash($_POST['extra_billing_type'] ?? ''), ['per_person', 'per_booking']) ? wp_unslash($_POST['extra_billing_type'] ?? '') : 'per_person',
            'tax_class' => sanitize_text_field(wp_unslash($_POST['extra_tax_class'] ?? '')),
            'max_quantity' => absint(wp_unslash($_POST['extra_max_quantity'] ?? 1)),
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
        $id = absint(wp_unslash($_POST['extra_id'] ?? 0));
        
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
        if (!isset($_POST['fp_closure_nonce']) || !wp_verify_nonce(wp_unslash($_POST['fp_closure_nonce']), 'fp_closure_action')) {
            wp_die(__('Security check failed.', 'fp-esperienze'));
        }

        if (!CapabilityManager::canManageFPEsperienze()) {
            wp_die(__('You do not have permission to perform this action.', 'fp-esperienze'));
        }

        $action = sanitize_text_field(wp_unslash($_POST['action'] ?? ''));

        switch ($action) {
            case 'add_closure':
                $date = sanitize_text_field(wp_unslash($_POST['closure_date'] ?? ''));
                $reason = sanitize_text_field(wp_unslash($_POST['closure_reason'] ?? ''));

                if ($date === '') {
                    $this->queuePageNotice(__('Select a date before adding a global closure.', 'fp-esperienze'), 'warning');
                    break;
                }

                $result = OverrideManager::createGlobalClosure($date, $reason);

                if ($result) {
                    $this->queuePageNotice(__('Global closure added successfully.', 'fp-esperienze'), 'success');
                } else {
                    $this->queuePageNotice(
                        __('No closures were created. Ensure at least one experience product is published.', 'fp-esperienze'),
                        'warning'
                    );
                }
                break;

            case 'remove_closure':
                $date = sanitize_text_field(wp_unslash($_POST['closure_date'] ?? ''));

                if ($date === '') {
                    $this->queuePageNotice(__('The selected closure is missing a date. Please try again.', 'fp-esperienze'), 'warning');
                    break;
                }

                $result = OverrideManager::removeGlobalClosure($date);

                if ($result) {
                    $this->queuePageNotice(__('Global closure removed successfully.', 'fp-esperienze'), 'success');
                } else {
                    $this->queuePageNotice(__('Failed to remove the selected closure. Please try again.', 'fp-esperienze'), 'danger');
                }
                break;

            default:
                $this->queuePageNotice(__('Unsupported closure action.', 'fp-esperienze'), 'warning');
                break;
        }
    }

    /**
     * Settings page
     */
    public function settingsPage(): void {
        // Handle form submissions
        if ($_POST) {
            $this->handleSettingsSubmission();
        }
        
        // Get current tab
        $current_tab = sanitize_text_field(wp_unslash($_GET['tab'] ?? 'general'));
        
        // Get general settings
        $archive_page_id    = get_option('fp_esperienze_archive_page_id', 0);
        $wpml_auto_send     = (bool) get_option('fp_esperienze_wpml_auto_send', false);
        
        // Get current settings
        $gift_exp_months = get_option('fp_esperienze_gift_default_exp_months', 12);
        $gift_logo = get_option('fp_esperienze_gift_pdf_logo', '');
        $gift_brand_color = get_option('fp_esperienze_gift_pdf_brand_color', '#ff6b35');
        $gift_sender_name = get_option('fp_esperienze_gift_email_sender_name', get_bloginfo('name'));
        $gift_sender_email = get_option('fp_esperienze_gift_email_sender_email', get_option('admin_email'));
        $gift_terms = get_option('fp_esperienze_gift_terms', __('This voucher is valid for one experience booking. Please present the QR code when redeeming.', 'fp-esperienze'));
        $gift_secret = get_option('fp_esperienze_gift_secret_hmac', '');
        
        // Get holds/booking settings
        $enable_holds = get_option('fp_esperienze_enable_holds', 1);
        $hold_duration = get_option('fp_esperienze_hold_duration_minutes', 15);
        
        // Get integrations settings
        $integrations = get_option('fp_esperienze_integrations', []);
        $ga4_measurement_id = $integrations['ga4_measurement_id'] ?? '';
        $ga4_ecommerce = !empty($integrations['ga4_ecommerce']);
        $gads_conversion_id = $integrations['gads_conversion_id'] ?? '';
        $gads_purchase_label = $integrations['gads_purchase_label'] ?? '';
        $meta_pixel_id = $integrations['meta_pixel_id'] ?? '';
        $meta_capi_enabled = !empty($integrations['meta_capi_enabled']);
        $meta_access_token = $integrations['meta_access_token'] ?? '';
        $meta_dataset_id = $integrations['meta_dataset_id'] ?? '';
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
        
        // Get notification settings
        $notifications = get_option('fp_esperienze_notifications', []);
        $staff_emails = $notifications['staff_emails'] ?? '';
        $staff_notifications_enabled = !empty($notifications['staff_notifications_enabled']);
        $ics_attachment_enabled = !empty($notifications['ics_attachment_enabled'] ?? true);
        
        // Get webhook settings
        $webhook_new_booking = get_option('fp_esperienze_webhook_new_booking', '');
        $webhook_cancellation = get_option('fp_esperienze_webhook_cancellation', '');
        $webhook_reschedule = get_option('fp_esperienze_webhook_reschedule', '');
        $webhook_secret = get_option('fp_esperienze_webhook_secret', '');
        $webhook_hide_pii = get_option('fp_esperienze_webhook_hide_pii', false);
        
        // Get branding settings
        $branding_settings = get_option('fp_esperienze_branding', []);
        $primary_font = $branding_settings['primary_font'] ?? 'inherit';
        $heading_font = $branding_settings['heading_font'] ?? 'inherit';
        $primary_color = $branding_settings['primary_color'] ?? '#ff6b35';
        $secondary_color = $branding_settings['secondary_color'] ?? '#2c3e50';

        $branding_view = new BrandingSettingsView();
        
        ?>
        <div class="wrap">
            <h1><?php _e('FP Esperienze Settings', 'fp-esperienze'); ?></h1>
            
            <h2 class="nav-tab-wrapper">
                <a href="<?php echo admin_url('admin.php?page=fp-esperienze-settings&tab=general'); ?>" class="nav-tab <?php echo $current_tab === 'general' ? 'nav-tab-active' : ''; ?>"><?php _e('General', 'fp-esperienze'); ?></a>
                <a href="<?php echo admin_url('admin.php?page=fp-esperienze-settings&tab=booking'); ?>" class="nav-tab <?php echo $current_tab === 'booking' ? 'nav-tab-active' : ''; ?>"><?php _e('Booking', 'fp-esperienze'); ?></a>
                <a href="<?php echo admin_url('admin.php?page=fp-esperienze-settings&tab=branding'); ?>" class="nav-tab <?php echo $current_tab === 'branding' ? 'nav-tab-active' : ''; ?>"><?php _e('Branding', 'fp-esperienze'); ?></a>
                <a href="<?php echo admin_url('admin.php?page=fp-esperienze-settings&tab=gift'); ?>" class="nav-tab <?php echo $current_tab === 'gift' ? 'nav-tab-active' : ''; ?>"><?php _e('Gift Vouchers', 'fp-esperienze'); ?></a>
                <a href="<?php echo admin_url('admin.php?page=fp-esperienze-settings&tab=notifications'); ?>" class="nav-tab <?php echo $current_tab === 'notifications' ? 'nav-tab-active' : ''; ?>"><?php _e('Notifications', 'fp-esperienze'); ?></a>
                <a href="<?php echo admin_url('admin.php?page=fp-esperienze-settings&tab=integrations'); ?>" class="nav-tab <?php echo $current_tab === 'integrations' ? 'nav-tab-active' : ''; ?>"><?php _e('Integrations', 'fp-esperienze'); ?></a>
                <a href="<?php echo admin_url('admin.php?page=fp-esperienze-settings&tab=webhooks'); ?>" class="nav-tab <?php echo $current_tab === 'webhooks' ? 'nav-tab-active' : ''; ?>"><?php _e('Webhooks', 'fp-esperienze'); ?></a>
                <a href="<?php echo admin_url('admin.php?page=fp-esperienze-settings&tab=autotranslate'); ?>" class="nav-tab <?php echo $current_tab === 'autotranslate' ? 'nav-tab-active' : ''; ?>"><?php _e('Auto Translate', 'fp-esperienze'); ?></a>
            </h2>

            <form method="post" action="<?php echo $current_tab === 'autotranslate' ? 'options.php' : ''; ?>">
                <?php
                if ($current_tab === 'autotranslate') {
                    settings_fields('fp_lt_settings');
                } else {
                    wp_nonce_field('fp_settings_nonce', 'fp_settings_nonce');
                    echo '<input type="hidden" name="settings_tab" value="' . esc_attr($current_tab) . '" />';
                }
                ?>

                <?php
                settings_errors(self::SETTINGS_ERROR_SLUG);
                if ($current_tab === 'autotranslate') {
                    settings_errors('fp_lt_settings');
                }
                ?>

                <?php if ($current_tab === 'general') : ?>
                <div class="tab-content">
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="archive_page_id"><?php _e('Archive Page', 'fp-esperienze'); ?></label>
                            </th>
                            <td>
                                <?php
                                $pages = get_pages();
                                echo '<select id="archive_page_id" name="archive_page_id" class="regular-text">';
                                echo '<option value="0">' . esc_html__('Select a page', 'fp-esperienze') . '</option>';
                                foreach ($pages as $page) {
                                    $selected = selected($archive_page_id, $page->ID, false);
                                    echo '<option value="' . esc_attr($page->ID) . '" ' . $selected . '>' . esc_html($page->post_title) . '</option>';
                                }
                                echo '</select>';
                                ?>
                                <p class="description"><?php _e('Select the page to use as the experience archive. This is used for WPML/Polylang URL translation.', 'fp-esperienze'); ?></p>
                            </td>
                        </tr>
                        
                        <?php if (I18nManager::isMultilingualActive()) : ?>
                        <tr>
                            <th scope="row"><?php _e('Multilingual Plugin', 'fp-esperienze'); ?></th>
                            <td>
                                <p>
                                    <strong><?php echo esc_html(ucfirst(I18nManager::getActivePlugin())); ?></strong> 
                                    <?php _e('detected and active', 'fp-esperienze'); ?>
                                </p>
                                <p class="description">
                                    <?php _e('The plugin will automatically filter experiences by language and provide translated meeting point data.', 'fp-esperienze'); ?>
                                </p>
                            </td>
                        </tr>
                        <?php else : ?>
                        <tr>
                            <th scope="row"><?php _e('Multilingual Support', 'fp-esperienze'); ?></th>
                            <td>
                                <p><?php _e('No multilingual plugin detected.', 'fp-esperienze'); ?></p>
                                <p class="description">
                                    <?php _e('Install WPML or Polylang to enable multilingual features including translated meeting points and language-filtered archives.', 'fp-esperienze'); ?>
                                </p>
                            </td>
                        </tr>
                        <?php endif; ?>

                        <?php if (I18nManager::getActivePlugin() === 'wpml') : ?>
                        <tr>
                            <th scope="row">
                                <label for="wpml_auto_send"><?php _e('Auto-send to WPML', 'fp-esperienze'); ?></label>
                            </th>
                            <td>
                                <input type="checkbox" id="wpml_auto_send" name="wpml_auto_send" value="1" <?php checked($wpml_auto_send); ?> />
                                <p class="description"><?php _e('Automatically create WPML translation jobs when saving experiences or meeting points.', 'fp-esperienze'); ?></p>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </table>

                    <?php submit_button(__('Save Settings', 'fp-esperienze')); ?>
                </div>
                <?php endif; ?>
                
                <?php if ($current_tab === 'branding') : ?>
                <div class="tab-content">
                    <h3><?php _e('Typography & Colors', 'fp-esperienze'); ?></h3>
                    <p><?php _e('Configure fonts and colors for your experience booking system.', 'fp-esperienze'); ?></p>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="primary_font"><?php _e('Primary Font', 'fp-esperienze'); ?></label>
                            </th>
                            <td>
                                <?php echo $branding_view->renderFontSelect('primary_font', $primary_font, $branding_view->getPrimaryFontOptions()); ?>
                                <p class="description"><?php _e('Primary font used for body text in experience displays.', 'fp-esperienze'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="heading_font"><?php _e('Heading Font', 'fp-esperienze'); ?></label>
                            </th>
                            <td>
                                <?php echo $branding_view->renderFontSelect('heading_font', $heading_font, $branding_view->getHeadingFontOptions()); ?>
                                <p class="description"><?php _e('Font used for headings and titles in experience displays.', 'fp-esperienze'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="primary_color"><?php _e('Primary Color', 'fp-esperienze'); ?></label>
                            </th>
                            <td>
                                <input type="color" 
                                       id="primary_color" 
                                       name="primary_color" 
                                       value="<?php echo esc_attr($primary_color); ?>" />
                                <input type="text" 
                                       id="primary_color_text" 
                                       value="<?php echo esc_attr($primary_color); ?>" 
                                       class="regular-text" 
                                       placeholder="#ff6b35" />
                                <p class="description"><?php _e('Primary brand color used for buttons, highlights, and accents.', 'fp-esperienze'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="secondary_color"><?php _e('Secondary Color', 'fp-esperienze'); ?></label>
                            </th>
                            <td>
                                <input type="color" 
                                       id="secondary_color" 
                                       name="secondary_color" 
                                       value="<?php echo esc_attr($secondary_color); ?>" />
                                <input type="text" 
                                       id="secondary_color_text" 
                                       value="<?php echo esc_attr($secondary_color); ?>" 
                                       class="regular-text" 
                                       placeholder="#2c3e50" />
                                <p class="description">
                                    <?php _e('Secondary color used for text elements and darker accents. Should contrast well with the primary color.', 'fp-esperienze'); ?>
                                    <br><small><?php _e('Suggested combinations: Blue (#3498db) + Dark Blue (#2c3e50), Green (#27ae60) + Dark Green (#1e7e34), Purple (#9b59b6) + Dark Purple (#6f42c1)', 'fp-esperienze'); ?></small>
                                </p>
                            </td>
                        </tr>
                    </table>
                    
                    <h3><?php _e('Font Preview', 'fp-esperienze'); ?></h3>
                    <div id="fp-font-preview" style="border: 1px solid #ddd; padding: 20px; margin: 20px 0; background: #fff;">
                        <h2 id="fp-preview-heading" style="margin: 0 0 10px 0;"><?php _e('Experience Title Preview', 'fp-esperienze'); ?></h2>
                        <p id="fp-preview-text" style="margin: 0;"><?php _e('This is how your body text will appear in experience descriptions and details.', 'fp-esperienze'); ?></p>
                    </div>
                    
                    <?php submit_button(__('Save Branding Settings', 'fp-esperienze')); ?>
                </div>
                <?php endif; ?>
                
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
                                        onclick="if(confirm(fpEsperienzeAdmin.i18n.confirmRegenerateSecret)) { document.getElementById('regenerate_secret').value = '1'; }"><?php _e('Regenerate Secret', 'fp-esperienze'); ?></button>
                                <input type="hidden" id="regenerate_secret" name="regenerate_secret" value="0" />
                                <p class="description"><?php _e('Secret key used to sign QR codes for security. Regenerating will invalidate existing QR codes!', 'fp-esperienze'); ?></p>
                            </td>
                        </tr>
                    </table>
                    
                    <?php submit_button(__('Save Settings', 'fp-esperienze')); ?>
                </div>
                <?php endif; ?>
                
                <?php if ($current_tab === 'booking') : ?>
                <div class="tab-content">
                    <h3><?php _e('Capacity Management', 'fp-esperienze'); ?></h3>
                    <p><?php _e('Configure optimistic locking and capacity hold settings for better overbooking prevention.', 'fp-esperienze'); ?></p>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="enable_holds"><?php _e('Enable Capacity Holds', 'fp-esperienze'); ?></label>
                            </th>
                            <td>
                                <input type="checkbox" 
                                       id="enable_holds" 
                                       name="enable_holds" 
                                       value="1" 
                                       <?php checked($enable_holds, 1); ?> />
                                <p class="description"><?php _e('Enable optimistic locking system that temporarily reserves spots when users add experiences to cart. When disabled, atomic capacity checks are used instead.', 'fp-esperienze'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="hold_duration"><?php _e('Hold Duration (minutes)', 'fp-esperienze'); ?></label>
                            </th>
                            <td>
                                <input type="number" 
                                       id="hold_duration" 
                                       name="hold_duration" 
                                       value="<?php echo esc_attr($hold_duration); ?>" 
                                       min="5" 
                                       max="60" 
                                       class="small-text" />
                                <p class="description"><?php _e('How long spots should be held in the cart before expiring. Recommended: 15 minutes.', 'fp-esperienze'); ?></p>
                            </td>
                        </tr>
                    </table>
                    
                    <h3><?php _e('Hold Statistics', 'fp-esperienze'); ?></h3>
                    <?php
                    global $wpdb;
                    $holds_table = $wpdb->prefix . 'fp_exp_holds';
                    $active_holds = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `{$holds_table}` WHERE expires_at > NOW()"));
                    $expired_holds = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `{$holds_table}` WHERE expires_at <= NOW()"));
                    ?>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Active Holds', 'fp-esperienze'); ?></th>
                            <td><strong><?php echo esc_html($active_holds ?: 0); ?></strong></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Expired Holds (to cleanup)', 'fp-esperienze'); ?></th>
                            <td>
                                <strong><?php echo esc_html($expired_holds ?: 0); ?></strong>
                                <?php if ($expired_holds > 0) : ?>
                                    <button type="button" class="button button-secondary" onclick="cleanupExpiredHolds()"><?php _e('Cleanup Now', 'fp-esperienze'); ?></button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Next Cleanup', 'fp-esperienze'); ?></th>
                            <td>
                                <?php
                                $next_cleanup = wp_next_scheduled('fp_esperienze_cleanup_holds');
                                if ($next_cleanup) {
                                    echo esc_html(\fp_esperienze_wp_date(get_option('date_format') . ' ' . get_option('time_format'), $next_cleanup));
                                } else {
                                    _e('Not scheduled', 'fp-esperienze');
                                }
                                ?>
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
                        
                        <tr>
                            <th scope="row">
                                <label for="gads_purchase_label"><?php _e('Purchase Conversion Label', 'fp-esperienze'); ?></label>
                            </th>
                            <td>
                                <input type="text" 
                                       id="gads_purchase_label" 
                                       name="gads_purchase_label" 
                                       value="<?php echo esc_attr($gads_purchase_label); ?>" 
                                       placeholder="AbCdEfGhIjKlMnOp"
                                       class="regular-text" />
                                <p class="description"><?php _e('Conversion label for purchase events (found in Google Ads conversion action settings).', 'fp-esperienze'); ?></p>
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
                                    <?php _e('Enable server-side Meta Conversions API tracking', 'fp-esperienze'); ?>
                                </label>
                                <p class="description"><?php _e('Server-side tracking for improved data accuracy and iOS 14.5+ compliance.', 'fp-esperienze'); ?></p>
                            </td>
                        </tr>
                        
                        <tr class="meta-capi-settings" <?php echo !$meta_capi_enabled ? 'style="display:none;"' : ''; ?>>
                            <th scope="row">
                                <label for="meta_access_token"><?php _e('Access Token', 'fp-esperienze'); ?></label>
                            </th>
                            <td>
                                <input type="password" 
                                       id="meta_access_token" 
                                       name="meta_access_token" 
                                       value="<?php echo esc_attr($meta_access_token); ?>" 
                                       class="regular-text" />
                                <p class="description"><?php _e('Meta Conversions API access token (generate in Facebook Business Manager).', 'fp-esperienze'); ?></p>
                            </td>
                        </tr>
                        
                        <tr class="meta-capi-settings" <?php echo !$meta_capi_enabled ? 'style="display:none;"' : ''; ?>>
                            <th scope="row">
                                <label for="meta_dataset_id"><?php _e('Dataset ID', 'fp-esperienze'); ?></label>
                            </th>
                            <td>
                                <input type="text" 
                                       id="meta_dataset_id" 
                                       name="meta_dataset_id" 
                                       value="<?php echo esc_attr($meta_dataset_id); ?>" 
                                       class="regular-text" />
                                <button type="button" class="button" onclick="testMetaCAPI()"><?php _e('Test Connection', 'fp-esperienze'); ?></button>
                                <p class="description"><?php _e('Meta Conversions API dataset ID (found in Events Manager).', 'fp-esperienze'); ?></p>
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
                        <?php if (defined('FP_ESP_SHOW_EXPERIMENTAL') && FP_ESP_SHOW_EXPERIMENTAL) : ?>
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
                        <?php else : ?>
                        <tr>
                            <th colspan="2">
                                <h3><?php _e('Google Business Profile API', 'fp-esperienze'); ?></h3>
                                <p style="margin: 0; padding: 10px; background: #f0f6fc; border-left: 4px solid #0073aa; font-style: italic; color: #666;">
                                    <strong><?php _e('Roadmap Feature:', 'fp-esperienze'); ?></strong> 
                                    <?php _e('Google Business Profile integration is planned for a future release. This will allow automatic posting of experiences and enhanced review management.', 'fp-esperienze'); ?>
                                </p>
                            </th>
                        </tr>
                        <?php endif; ?>
                    </table>
                    
                    <script>
                    function testMetaCAPI() {
                        const button = event.target;
                        const originalText = button.textContent;
                        const metaPixelId = document.getElementById('meta_pixel_id').value;
                        const metaAccessToken = document.getElementById('meta_access_token').value;
                        const metaDatasetId = document.getElementById('meta_dataset_id').value;
                        
                        if (!metaPixelId || !metaAccessToken || !metaDatasetId) {
                            alert('<?php _e('Please fill in all Meta Conversions API fields before testing.', 'fp-esperienze'); ?>');
                            return;
                        }
                        
                        button.textContent = '<?php _e('Testing...', 'fp-esperienze'); ?>';
                        button.disabled = true;
                        
                        // Create result div if it doesn't exist
                        let resultDiv = document.getElementById('meta-capi-test-result');
                        if (!resultDiv) {
                            resultDiv = document.createElement('div');
                            resultDiv.id = 'meta-capi-test-result';
                            resultDiv.style.marginTop = '10px';
                            button.parentNode.appendChild(resultDiv);
                        }
                        
                        const data = new FormData();
                        data.append('action', 'fp_test_meta_capi');
                        data.append('nonce', '<?php echo wp_create_nonce('fp_test_meta_capi'); ?>');
                        
                        fetch(ajaxurl, {
                            method: 'POST',
                            body: data
                        })
                        .then(response => response.json())
                        .then(result => {
                            if (result.success) {
                                const text = document.createTextNode(result.data.message);
                                const wrapper = document.createElement('div');
                                wrapper.className = 'notice notice-success inline';
                                wrapper.appendChild(document.createElement('p')).appendChild(text);
                                resultDiv.innerHTML = '';
                                resultDiv.appendChild(wrapper);
                            } else {
                                const text = document.createTextNode(result.data.message);
                                const wrapper = document.createElement('div');
                                wrapper.className = 'notice notice-error inline';
                                wrapper.appendChild(document.createElement('p')).appendChild(text);
                                resultDiv.innerHTML = '';
                                resultDiv.appendChild(wrapper);
                            }
                        })
                        .catch(error => {
                            const text = document.createTextNode(error.message);
                            const wrapper = document.createElement('div');
                            wrapper.className = 'notice notice-error inline';
                            wrapper.appendChild(document.createElement('p')).appendChild(text);
                            resultDiv.innerHTML = '';
                            resultDiv.appendChild(wrapper);
                        })
                        .finally(() => {
                            button.textContent = originalText;
                            button.disabled = false;
                        });
                    }
                    
                    // Toggle Meta CAPI settings visibility
                    jQuery(document).ready(function($) {
                        $('#meta_capi_enabled').change(function() {
                            if ($(this).is(':checked')) {
                                $('.meta-capi-settings').show();
                            } else {
                                $('.meta-capi-settings').hide();
                            }
                        });
                    });
                    </script>
                    
                    <?php submit_button(__('Save Integrations', 'fp-esperienze')); ?>
                </div>
                
                <?php elseif ($current_tab === 'notifications') : ?>
                <div class="tab-content">
                    <h3><?php _e('Booking Notifications', 'fp-esperienze'); ?></h3>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="staff_notifications_enabled"><?php _e('Staff Notifications', 'fp-esperienze'); ?></label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" 
                                           id="staff_notifications_enabled" 
                                           name="staff_notifications_enabled" 
                                           value="1" 
                                           <?php checked($staff_notifications_enabled); ?> />
                                    <?php _e('Send email notifications to staff when new bookings are made', 'fp-esperienze'); ?>
                                </label>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="staff_emails"><?php _e('Staff Email Addresses', 'fp-esperienze'); ?></label>
                            </th>
                            <td>
                                <textarea id="staff_emails" 
                                          name="staff_emails" 
                                          rows="5" 
                                          class="large-text" 
                                          placeholder="admin@example.com&#10;manager@example.com&#10;staff@example.com"><?php echo esc_textarea($staff_emails); ?></textarea>
                                <p class="description">
                                    <?php _e('Enter one email address per line. These emails will receive notifications when new bookings are created.', 'fp-esperienze'); ?>
                                </p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="ics_attachment_enabled"><?php _e('ICS Calendar Attachments', 'fp-esperienze'); ?></label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" 
                                           id="ics_attachment_enabled" 
                                           name="ics_attachment_enabled" 
                                           value="1" 
                                           <?php checked($ics_attachment_enabled); ?> />
                                    <?php _e('Attach ICS calendar files to order completion emails', 'fp-esperienze'); ?>
                                </label>
                                <p class="description">
                                    <?php _e('When enabled, customers will receive an ICS calendar file attachment with their booking details in order confirmation emails.', 'fp-esperienze'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                    
                    <?php include FP_ESPERIENZE_PLUGIN_DIR . 'templates/admin/settings/notifications-ics-endpoints.php'; ?>
                    
                    <?php submit_button(__('Save Notification Settings', 'fp-esperienze')); ?>
                </div>
                
                <?php endif; ?>
                
                <?php if ($current_tab === 'webhooks') : ?>
                <div class="tab-content">
                    <h3><?php _e('Webhook Configuration', 'fp-esperienze'); ?></h3>
                    <p><?php _e('Configure webhook URLs to receive real-time notifications about booking events.', 'fp-esperienze'); ?></p>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="webhook_new_booking"><?php _e('New Booking URL', 'fp-esperienze'); ?></label>
                            </th>
                            <td>
                                <input type="url" 
                                       id="webhook_new_booking" 
                                       name="webhook_new_booking" 
                                       value="<?php echo esc_attr($webhook_new_booking); ?>" 
                                       class="regular-text" />
                                <button type="button" class="button" onclick="testWebhook('webhook_new_booking')"><?php _e('Test', 'fp-esperienze'); ?></button>
                                <p class="description"><?php _e('Webhook URL called when a new booking is created.', 'fp-esperienze'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="webhook_cancellation"><?php _e('Cancellation URL', 'fp-esperienze'); ?></label>
                            </th>
                            <td>
                                <input type="url" 
                                       id="webhook_cancellation" 
                                       name="webhook_cancellation" 
                                       value="<?php echo esc_attr($webhook_cancellation); ?>" 
                                       class="regular-text" />
                                <button type="button" class="button" onclick="testWebhook('webhook_cancellation')"><?php _e('Test', 'fp-esperienze'); ?></button>
                                <p class="description"><?php _e('Webhook URL called when a booking is cancelled.', 'fp-esperienze'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="webhook_reschedule"><?php _e('Reschedule URL', 'fp-esperienze'); ?></label>
                            </th>
                            <td>
                                <input type="url" 
                                       id="webhook_reschedule" 
                                       name="webhook_reschedule" 
                                       value="<?php echo esc_attr($webhook_reschedule); ?>" 
                                       class="regular-text" />
                                <button type="button" class="button" onclick="testWebhook('webhook_reschedule')"><?php _e('Test', 'fp-esperienze'); ?></button>
                                <p class="description"><?php _e('Webhook URL called when a booking is rescheduled.', 'fp-esperienze'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="webhook_secret"><?php _e('Webhook Secret', 'fp-esperienze'); ?></label>
                            </th>
                            <td>
                                <input type="text" 
                                       id="webhook_secret" 
                                       name="webhook_secret" 
                                       value="<?php echo esc_attr($webhook_secret); ?>" 
                                       class="regular-text" />
                                <button type="button" class="button" onclick="generateWebhookSecret()"><?php _e('Generate New', 'fp-esperienze'); ?></button>
                                <p class="description"><?php _e('Secret key used to sign webhook payloads with HMAC-SHA256. Use X-FP-Signature header to verify authenticity.', 'fp-esperienze'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="webhook_hide_pii"><?php _e('Hide Personal Information', 'fp-esperienze'); ?></label>
                            </th>
                            <td>
                                <input type="checkbox" 
                                       id="webhook_hide_pii" 
                                       name="webhook_hide_pii" 
                                       value="1" 
                                       <?php checked($webhook_hide_pii); ?> />
                                <label for="webhook_hide_pii"><?php _e('Exclude customer notes and personal data from webhook payloads', 'fp-esperienze'); ?></label>
                                <p class="description"><?php _e('Enable for GDPR compliance when sending data to third-party services.', 'fp-esperienze'); ?></p>
                            </td>
                        </tr>
                    </table>
                    
                    <h3><?php _e('Webhook Payload Format', 'fp-esperienze'); ?></h3>
                    <p><?php _e('Webhooks send JSON payloads with the following structure:', 'fp-esperienze'); ?></p>
                    <pre style="background: #f9f9f9; padding: 15px; border: 1px solid #ddd; overflow-x: auto;"><code>{
  "event": "booking_created|booking_cancelled|booking_rescheduled",
  "booking_id": 123,
  "timestamp": "2024-01-15T10:30:00+00:00",
  "event_id": "unique_event_identifier_for_deduplication",
  "data": {
    "booking_id": 123,
    "order_id": 456,
    "product_id": 789,
    "booking_date": "2024-01-20",
    "booking_time": "10:00:00",
    "adults": 2,
    "children": 1,
    "status": "confirmed",
    "meeting_point_id": 1,
    "created_at": "2024-01-15T10:30:00",
    "updated_at": "2024-01-15T10:30:00"
  }
}</code></pre>
                    
                    <h3><?php _e('Retry Policy', 'fp-esperienze'); ?></h3>
                    <p><?php _e('Failed webhooks are retried up to 5 times with exponential backoff: 2, 4, 8, 16, 32 minutes.', 'fp-esperienze'); ?></p>
                    
                    <?php submit_button(__('Save Webhook Settings', 'fp-esperienze')); ?>
                </div>
                
                <?php endif; ?>

                <?php if ($current_tab === 'autotranslate') : ?>
                <div class="tab-content">
                    <?php
                    do_settings_sections('fp_lt_settings');
                    submit_button(__('Save Settings', 'fp-esperienze'));
                    ?>
                </div>
                <?php endif; ?>
            </form>
        </div>
        
        <script>
        function selectMediaFile(inputId) {
            var frame = wp.media({
                title: fpEsperienzeAdmin.i18n.selectLogo,
                button: {
                    text: fpEsperienzeAdmin.i18n.useThisImage
                },
                multiple: false
            });
            
            frame.on('select', function() {
                var attachment = frame.state().get('selection').first().toJSON();
                document.getElementById(inputId).value = attachment.url;
            });
            
            frame.open();
        }
        
        function testWebhook(inputId) {
            var url = document.getElementById(inputId).value;
            if (!url) {
                alert(fpEsperienzeAdmin.i18n.enterWebhookUrl);
                return;
            }
            
            var button = event.target;
            var originalText = button.textContent;
            button.textContent = fpEsperienzeAdmin.i18n.testing;
            button.disabled = true;
            
            jQuery.post(ajaxurl, {
                action: 'fp_test_webhook',
                webhook_url: url,
                nonce: '<?php echo wp_create_nonce('fp_test_webhook'); ?>'
            }, function(response) {
                button.textContent = originalText;
                button.disabled = false;
                
                if (response.success) {
                    alert(fpEsperienzeAdmin.i18n.webhookTestSuccess + '\\n' + 
                          fpEsperienzeAdmin.i18n.status + ' ' + response.data.status_code);
                } else {
                    alert(fpEsperienzeAdmin.i18n.webhookTestFailed + '\\n' + response.data.message);
                }
            }).fail(function() {
                button.textContent = originalText;
                button.disabled = false;
                alert(fpEsperienzeAdmin.i18n.requestFailed);
            });
        }
        
        function generateWebhookSecret() {
            if (confirm(fpEsperienzeAdmin.i18n.generateNewSecret)) {
                var secret = '';
                var characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
                for (var i = 0; i < 32; i++) {
                    secret += characters.charAt(Math.floor(Math.random() * characters.length));
                }
                document.getElementById('webhook_secret').value = secret;
            }
        }
        
        function cleanupExpiredHolds() {
            if (!confirm(fpEsperienzeAdmin.i18n.cleanupHolds)) {
                return;
            }
            
            var button = event.target;
            var originalText = button.textContent;
            button.textContent = fpEsperienzeAdmin.i18n.cleaning;
            button.disabled = true;
            
            jQuery.post(ajaxurl, {
                action: 'fp_cleanup_expired_holds',
                nonce: '<?php echo wp_create_nonce('fp_cleanup_holds'); ?>'
            }, function(response) {
                button.textContent = originalText;
                button.disabled = false;
                
                if (response.success) {
                    alert(fpEsperienzeAdmin.i18n.cleanupCompleted + '\\n' + 
                          fpEsperienzeAdmin.i18n.cleanedUp + ' ' + response.data.count + ' ' + fpEsperienzeAdmin.i18n.holds);
                    location.reload(); // Refresh to update statistics
                } else {
                    alert(fpEsperienzeAdmin.i18n.cleanupFailed + '\\n' + response.data.message);
                }
            }).fail(function() {
                button.textContent = originalText;
                button.disabled = false;
                alert(fpEsperienzeAdmin.i18n.requestFailed);
            });
        }
        
        // Toggle Meta CAPI settings visibility
        jQuery(document).ready(function($) {
            $('#meta_capi_enabled').change(function() {
                if ($(this).is(':checked')) {
                    $('.meta-capi-settings').show();
                } else {
                    $('.meta-capi-settings').hide();
                }
            });
        });
        
        function testMetaCAPI() {
            var button = event.target;
            var originalText = button.textContent;
            button.textContent = fpEsperienzeAdmin.i18n.testing;
            button.disabled = true;
            
            jQuery.post(ajaxurl, {
                action: 'fp_test_meta_capi',
                nonce: '<?php echo wp_create_nonce('fp_test_meta_capi'); ?>'
            }, function(response) {
                button.textContent = originalText;
                button.disabled = false;
                
                if (response.success) {
                    alert('Meta Conversions API test successful!\\n' + response.data.message);
                } else {
                    alert('Meta Conversions API test failed:\\n' + response.data.message);
                }
            }).fail(function() {
                button.textContent = originalText;
                button.disabled = false;
                alert(fpEsperienzeAdmin.i18n.requestFailed);
            });
        }
        
        // Branding settings functionality
        jQuery(document).ready(function($) {
            // Sync color picker with text input
            $('#primary_color').on('change', function() {
                $('#primary_color_text').val($(this).val());
                updateColorPreview();
            });
            
            $('#primary_color_text').on('change', function() {
                var color = $(this).val();
                if (color.match(/^#[0-9a-fA-F]{6}$/)) {
                    $('#primary_color').val(color);
                }
                updateColorPreview();
            });
            
            $('#secondary_color').on('change', function() {
                $('#secondary_color_text').val($(this).val());
                updateColorPreview();
            });
            
            $('#secondary_color_text').on('change', function() {
                var color = $(this).val();
                if (color.match(/^#[0-9a-fA-F]{6}$/)) {
                    $('#secondary_color').val(color);
                }
                updateColorPreview();
            });
            
            // Update font preview when fonts change
            $('#primary_font, #heading_font').on('change', function() {
                updateFontPreview();
            });
            
            function updateFontPreview() {
                var primaryFont = $('#primary_font').val();
                var headingFont = $('#heading_font').val();
                
                $('#fp-preview-text').css('font-family', primaryFont === 'inherit' ? '' : primaryFont);
                $('#fp-preview-heading').css('font-family', headingFont === 'inherit' ? '' : headingFont);
            }
            
            function updateColorPreview() {
                var primaryColor = $('#primary_color').val();
                var secondaryColor = $('#secondary_color').val();
                
                $('#fp-preview-heading').css('color', secondaryColor);
                $('#fp-font-preview').css('border-color', primaryColor);
            }
            
            // Initialize preview on page load
            updateFontPreview();
            updateColorPreview();
        });
        </script>
        <?php
    }
    
    /**
     * Handle settings form submission
     */
    private function handleSettingsSubmission(): void {
        if (!CapabilityManager::canManageFPEsperienze()) {
            wp_die(__('You do not have permission to perform this action.', 'fp-esperienze'));
        }

        $nonce = wp_unslash($_POST['fp_settings_nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'fp_settings_nonce')) {
            wp_die(__('Security check failed.', 'fp-esperienze'));
        }

        $tab = sanitize_key(wp_unslash($_POST['settings_tab'] ?? 'general'));

        $services = [
            'general' => new GeneralSettingsService(),
            'branding' => new BrandingSettingsService(),
            'gift' => new GiftSettingsService(),
            'booking' => new BookingSettingsService(),
            'integrations' => new IntegrationsSettingsService(),
            'notifications' => new NotificationsSettingsService(),
            'webhooks' => new WebhookSettingsService(),
        ];

        if (!isset($services[$tab])) {
            add_settings_error(
                self::SETTINGS_ERROR_SLUG,
                'fp_settings_invalid_tab',
                __('Invalid settings tab submitted.', 'fp-esperienze'),
                'error'
            );

            return;
        }

        /** @var array<string,mixed> $request */
        $request = wp_unslash($_POST);

        $result = $services[$tab]->handle($request);

        foreach ($result->getErrors() as $index => $error) {
            add_settings_error(
                self::SETTINGS_ERROR_SLUG,
                sprintf('fp_settings_error_%s_%d', $tab, $index),
                $error,
                'error'
            );
        }

        if ($result->isSuccess()) {
            $messages = $result->getMessages();
            if (empty($messages)) {
                $messages[] = __('Settings saved successfully!', 'fp-esperienze');
            }

            foreach ($messages as $index => $message) {
                add_settings_error(
                    self::SETTINGS_ERROR_SLUG,
                    sprintf('fp_settings_saved_%s_%d', $tab, $index),
                    $message,
                    'updated'
                );
            }

            return;
        }

        if (empty($result->getErrors())) {
            add_settings_error(
                self::SETTINGS_ERROR_SLUG,
                sprintf('fp_settings_failed_%s', $tab),
                __('Unable to save settings. Please try again.', 'fp-esperienze'),
                'error'
            );
        }
    }
    
    /**
     * Handle bookings actions
     */
    private function handleBookingsActions(): void {
        if (!CapabilityManager::canManageFPEsperienze()) {
            wp_die(__('You do not have permission to perform this action.', 'fp-esperienze'));
        }

        if (!wp_verify_nonce(wp_unslash($_POST['fp_booking_nonce'] ?? ''), 'fp_booking_action')) {
            wp_die(__('Security check failed.', 'fp-esperienze'));
        }
        
        $action = sanitize_text_field(wp_unslash($_POST['action'] ?? ''));

        switch ($action) {
            case 'update_status':
                $booking_id = absint(wp_unslash($_POST['booking_id'] ?? 0));
                $new_status = sanitize_text_field(wp_unslash($_POST['new_status'] ?? ''));

                $allowed_statuses = array_keys($this->getBookingStatusLabels());
                if (!in_array($new_status, $allowed_statuses, true)) {
                    add_action('admin_notices', function() {
                        echo '<div class="notice notice-error is-dismissible"><p>' .
                             esc_html__('Invalid booking status.', 'fp-esperienze') .
                             '</p></div>';
                    });
                    break;
                }

                if ($booking_id && $new_status) {
                    $success = $this->updateBookingStatusRecord($booking_id, $new_status);

                    if ($success) {
                        add_action('admin_notices', function() {
                            echo '<div class="notice notice-success is-dismissible"><p>' .
                                 esc_html__('Booking status updated successfully.', 'fp-esperienze') .
                                 '</p></div>';
                        });
                    } else {
                        add_action('admin_notices', function() {
                            echo '<div class="notice notice-error is-dismissible"><p>' .
                                 esc_html__('Failed to update booking status.', 'fp-esperienze') .
                                 '</p></div>';
                        });
                    }
                }
                break;

            case 'bulk_update_status':
                $booking_ids = array_map('absint', (array) wp_unslash($_POST['booking_ids'] ?? []));
                $booking_ids = array_filter($booking_ids);
                $new_status  = sanitize_text_field(wp_unslash($_POST['bulk_status'] ?? ''));

                $allowed_statuses = array_keys($this->getBookingStatusLabels());
                if (!in_array($new_status, $allowed_statuses, true)) {
                    add_action('admin_notices', function() {
                        echo '<div class="notice notice-error is-dismissible"><p>' .
                             esc_html__('Select a valid status before applying bulk actions.', 'fp-esperienze') .
                             '</p></div>';
                    });
                    break;
                }

                if (empty($booking_ids)) {
                    add_action('admin_notices', function() {
                        echo '<div class="notice notice-warning is-dismissible"><p>' .
                             esc_html__('Select at least one booking to update.', 'fp-esperienze') .
                             '</p></div>';
                    });
                    break;
                }

                $updated = 0;
                $failed  = 0;

                foreach ($booking_ids as $booking_id) {
                    if ($this->updateBookingStatusRecord($booking_id, $new_status)) {
                        $updated++;
                    } else {
                        $failed++;
                    }
                }

                if ($updated > 0) {
                    $status_label = $this->getBookingStatusLabels()[$new_status] ?? $new_status;
                    add_action('admin_notices', function () use ($updated, $status_label) {
                        echo '<div class="notice notice-success is-dismissible"><p>' .
                             sprintf(
                                 esc_html__('%1$d bookings marked as %2$s.', 'fp-esperienze'),
                                 $updated,
                                 esc_html($status_label)
                             ) .
                             '</p></div>';
                    });
                }

                if ($failed > 0) {
                    add_action('admin_notices', function () use ($failed) {
                        echo '<div class="notice notice-error is-dismissible"><p>' .
                             sprintf(
                                 esc_html__('%d bookings could not be updated.', 'fp-esperienze'),
                                 $failed
                             ) .
                             '</p></div>';
                    });
                }

                break;
        }
    }

    /**
     * Update the booking status for a single booking record.
     */
    private function updateBookingStatusRecord(int $booking_id, string $new_status): bool {
        global $wpdb;

        $table_bookings = $wpdb->prefix . 'fp_bookings';
        $booking        = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT order_id, order_item_id FROM {$table_bookings} WHERE id = %d",
                $booking_id
            )
        );

        if (!$booking) {
            return false;
        }

        $booking_manager = new BookingManager();

        return (bool) $booking_manager->updateBookingStatus(
            $booking->order_id,
            $booking->order_item_id,
            $new_status
        );
    }

    /**
     * Sanitize CSV row values to prevent formula injection.
     *
     * @param array $row Row data.
     * @return array Sanitized row.
     */
    private function sanitizeCsvRow(array $row): array {
        return array_map(
            static function ($field) {
                if (is_string($field) && preg_match('/^[=+\-@]/', $field)) {
                    return "'" . $field;
                }
                return $field;
            },
            $row
        );
    }

    /**
     * Export bookings to CSV
     */
    private function exportBookingsCSV(): WP_REST_Response|WP_Error {
        if (!CapabilityManager::canManageFPEsperienze()) {
            return new WP_Error('rest_forbidden', __('Insufficient permissions.', 'fp-esperienze'), ['status' => 403]);
        }

        check_admin_referer('fp_export_bookings');

        // Get current filters
        $filters = [
            'status' => sanitize_text_field(wp_unslash($_GET['status'] ?? '')),
            'product_id' => absint(wp_unslash($_GET['product_id'] ?? 0)),
            'date_from' => sanitize_text_field(wp_unslash($_GET['date_from'] ?? '')),
            'date_to' => sanitize_text_field(wp_unslash($_GET['date_to'] ?? '')),
        ];

        // Remove empty filters
        $filters = array_filter($filters);

        // Get bookings
        $bookings = BookingManager::getBookings($filters);

        $output = fopen('php://temp', 'r+');
        if ($output === false) {
            return new WP_Error('csv_open_failed', __('Unable to create temporary file.', 'fp-esperienze'));
        }

        // CSV headers
        fputcsv($output, $this->sanitizeCsvRow([
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
        ]));

        // CSV data
        foreach ($bookings as $booking) {
            $product = wc_get_product($booking->product_id);
            $product_name = $product ? $product->get_name() : __('Product not found', 'fp-esperienze');

            $meeting_point_name = '';
            if ($booking->meeting_point_id) {
                $mp = MeetingPointManager::getMeetingPoint($booking->meeting_point_id);
                $meeting_point_name = $mp ? $mp->name : __('Not found', 'fp-esperienze');
            }

            fputcsv($output, $this->sanitizeCsvRow([
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
            ]));
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        if ($csv === false) {
            return new WP_Error('csv_read_failed', __('Unable to read CSV data.', 'fp-esperienze'));
        }

        $filename = 'bookings-' . date('Y-m-d-H-i-s') . '.csv';
        $response = new WP_REST_Response($csv);
        $response->header('Content-Type', 'text/csv');
        $response->header('Content-Disposition', 'attachment; filename="' . $filename . '"');

        return $response;
    }
    
    /**
     * Get experience products for filter dropdown
     */
    private function getExperienceProducts(int $include_id = 0): array {
        $args = [
            'post_type' => 'product',
            'meta_query' => [
                [
                    'key' => '_fp_experience_enabled',
                    'value' => 'yes',
                    'compare' => '='
                ]
            ],
            'posts_per_page' => 50,
            'orderby' => 'title',
            'order' => 'ASC',
            'post_status' => 'publish',
            'no_found_rows' => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false
        ];

        $query = new \WP_Query($args);
        $posts = $query->posts;

        if ($include_id) {
            $found = false;
            foreach ($posts as $p) {
                if ((int) $p->ID === $include_id) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $included = get_post($include_id);
                if ($included && get_post_meta($included->ID, '_fp_experience_enabled', true) === 'yes') {
                    $posts[] = $included;
                    usort($posts, function($a, $b) {
                        return strcmp($a->post_title, $b->post_title);
                    });
                }
            }
        }

        return $posts;
    }
    
    /**
     * Performance page
     */
    public function performancePage(): void {
        $performance_settings = new PerformanceSettings();
        $performance_settings->renderPage();
    }
    
    /**
     * Reports page
     */
    public function reportsPage(): void {
        if (!CapabilityManager::canManageFPEsperienze()) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'fp-esperienze'));
        }

        // Handle form submissions for export
        if (isset($_POST['action']) && $_POST['action'] === 'export_report') {
            if (!wp_verify_nonce(wp_unslash($_POST['fp_report_nonce'] ?? ''), 'fp_export_report')) {
                wp_die(__('Security check failed.', 'fp-esperienze'));
            }

            $format = sanitize_text_field(wp_unslash($_POST['export_format'] ?? 'csv'));
            $filters = [
                'date_from' => sanitize_text_field(wp_unslash($_POST['date_from'] ?? '')),
                'date_to' => sanitize_text_field(wp_unslash($_POST['date_to'] ?? '')),
                'product_id' => absint(wp_unslash($_POST['product_id'] ?? 0)),
                'meeting_point_id' => absint(wp_unslash($_POST['meeting_point_id'] ?? 0)),
                'language' => sanitize_text_field(wp_unslash($_POST['language'] ?? ''))
            ];

            $reports_manager = new ReportsManager();
            $reports_manager->exportReportData($format, array_filter($filters));
            return;
        }

        // Get filter options
        $selected_product_id = absint(wp_unslash($_GET['product_id'] ?? 0));
        $experience_products = $this->getExperienceProducts($selected_product_id);
        $meeting_points = MeetingPointManager::getAllMeetingPoints();

        // Include the reports template
        include FP_ESPERIENZE_PLUGIN_DIR . 'templates/admin/reports.php';
    }

    /**
     * AJAX search for experience products
     */
    public function ajaxSearchExperienceProducts(): void {
        if (!CapabilityManager::canManageFPEsperienze()) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'fp-esperienze')]);
        }

        if (!check_ajax_referer('fp_esperienze_admin', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security check failed.', 'fp-esperienze')]);
        }

        $term = sanitize_text_field(wp_unslash($_GET['q'] ?? ''));
        $page = max(1, absint(wp_unslash($_GET['page'] ?? 1)));

        $query = new \WP_Query([
            'post_type' => 'product',
            'meta_query' => [
                [
                    'key' => '_fp_experience_enabled',
                    'value' => 'yes',
                    'compare' => '='
                ]
            ],
            's' => $term,
            'search_columns' => ['post_title'],
            'posts_per_page' => 50,
            'paged' => $page,
            'orderby' => 'title',
            'order' => 'ASC',
            'post_status' => 'publish',
            'no_found_rows' => false,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ]);

        $results = [];
        foreach ($query->posts as $post) {
            $results[] = [
                'id' => $post->ID,
                'text' => $post->post_title,
            ];
        }

        wp_send_json([
            'results' => $results,
            'pagination' => ['more' => $query->max_num_pages > $page],
        ]);
    }

    /**
     * AJAX handler: Get available slots for a date
     */
    public function ajaxGetAvailableSlots(): void {
        $start_time = microtime(true);
        
        if (!CapabilityManager::canManageFPEsperienze()) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'fp-esperienze')]);
        }
        
        if (!check_ajax_referer('fp_get_slots', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security check failed.', 'fp-esperienze')]);
        }
        
        $product_id = absint(wp_unslash($_POST['product_id'] ?? 0));
        $date = sanitize_text_field(wp_unslash($_POST['date'] ?? ''));
        
        if (!$product_id || !$date) {
            wp_send_json_error(['message' => __('Invalid parameters.', 'fp-esperienze')]);
        }
        
        $slots = Availability::getSlotsForDate($product_id, $date);
        
        Log::performance('AJAX GetAvailableSlots', $start_time);
        
        wp_send_json_success(['slots' => $slots]);
    }
    
    /**
     * AJAX handler: Reschedule booking
     */
    public function ajaxRescheduleBooking(): void {
        $start_time = microtime(true);
        
        if (!CapabilityManager::canManageFPEsperienze()) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'fp-esperienze')]);
        }
        
        if (!check_ajax_referer('fp_reschedule_booking', 'fp_reschedule_nonce', false)) {
            wp_send_json_error(['message' => __('Security check failed.', 'fp-esperienze')]);
        }
        
        $booking_id = absint(wp_unslash($_POST['booking_id'] ?? 0));
        $new_date = sanitize_text_field(wp_unslash($_POST['new_date'] ?? ''));
        $new_time = sanitize_text_field(wp_unslash($_POST['new_time'] ?? '')) . ':00'; // Add seconds
        $admin_notes = sanitize_textarea_field(wp_unslash($_POST['admin_notes'] ?? ''));
        
        if (!$booking_id || !$new_date || !$new_time) {
            wp_send_json_error(['message' => __('Invalid parameters.', 'fp-esperienze')]);
        }
        
        $result = BookingManager::rescheduleBooking($booking_id, $new_date, $new_time, $admin_notes);
        
        Log::performance('AJAX RescheduleBooking', $start_time);
        
        if ($result['success']) {
            wp_send_json_success(['message' => $result['message']]);
        } else {
            wp_send_json_error(['message' => $result['message']]);
        }
    }
    
    /**
     * AJAX handler: Check cancellation rules
     */
    public function ajaxCheckCancellationRules(): void {
        if (!CapabilityManager::canManageFPEsperienze()) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'fp-esperienze')]);
        }
        
        if (!check_ajax_referer('fp_check_cancel', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security check failed.', 'fp-esperienze')]);
        }
        
        $booking_id = absint(wp_unslash($_POST['booking_id'] ?? 0));
        
        if (!$booking_id) {
            wp_send_json_error(['message' => __('Invalid booking ID.', 'fp-esperienze')]);
        }
        
        $result = BookingManager::checkCancellationRules($booking_id);
        
        wp_send_json_success($result);
    }
    
    /**
     * AJAX handler: Cancel booking
     */
    public function ajaxCancelBooking(): void {
        if (!CapabilityManager::canManageFPEsperienze()) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'fp-esperienze')]);
        }
        
        if (!check_ajax_referer('fp_cancel_booking', 'fp_cancel_nonce', false)) {
            wp_send_json_error(['message' => __('Security check failed.', 'fp-esperienze')]);
        }
        
        $booking_id = absint(wp_unslash($_POST['booking_id'] ?? 0));
        $cancel_reason = sanitize_textarea_field(wp_unslash($_POST['cancel_reason'] ?? ''));
        
        if (!$booking_id) {
            wp_send_json_error(['message' => __('Invalid booking ID.', 'fp-esperienze')]);
        }
        
        // Update booking status to cancelled
        global $wpdb;
        $table_name = $wpdb->prefix . 'fp_bookings';
        
        $booking = BookingManager::getBooking($booking_id);
        if (!$booking) {
            wp_send_json_error(['message' => __('Booking not found.', 'fp-esperienze')]);
        }
        
        $admin_notes = $booking->admin_notes ? $booking->admin_notes . "\n" : '';
        $admin_notes .= sprintf(__('Cancelled by admin. Reason: %s', 'fp-esperienze'), $cancel_reason);
        
        $result = $wpdb->update(
            $table_name,
            [
                'status' => 'cancelled',
                'admin_notes' => $admin_notes,
                'updated_at' => current_time('mysql')
            ],
            ['id' => $booking_id],
            ['%s', '%s', '%s'],
            ['%d']
        );
        
        if ($result === false) {
            wp_send_json_error(['message' => __('Failed to cancel booking.', 'fp-esperienze')]);
        }
        
        // Trigger cache invalidation
        do_action('fp_esperienze_booking_cancelled', $booking->product_id, $booking->booking_date);
        
        wp_send_json_success(['message' => __('Booking cancelled successfully.', 'fp-esperienze')]);
    }
    
    /**
     * AJAX handler: Test webhook
     */
    public function ajaxTestWebhook(): void {
        if (!CapabilityManager::canManageFPEsperienze()) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'fp-esperienze')]);
        }
        
        if (!check_ajax_referer('fp_test_webhook', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security check failed.', 'fp-esperienze')]);
        }
        
        $webhook_url = esc_url_raw(wp_unslash($_POST['webhook_url'] ?? ''));
        $validated_url = wp_http_validate_url($webhook_url);

        if (!$validated_url || !in_array(wp_parse_url($validated_url, PHP_URL_SCHEME), ['http', 'https'], true)) {
            wp_send_json_error(['message' => __('Invalid webhook URL.', 'fp-esperienze')]);
        }

        $result = WebhookManager::testWebhook($validated_url);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * AJAX handler: Cleanup expired holds
     */
    public function ajaxCleanupExpiredHolds(): void {
        if (!CapabilityManager::canManageFPEsperienze()) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'fp-esperienze')]);
        }
        
        if (!check_ajax_referer('fp_cleanup_holds', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security check failed.', 'fp-esperienze')]);
        }
        
        $count = HoldManager::cleanupExpiredHolds();
        
        wp_send_json_success(['count' => $count, 'message' => sprintf(__('Cleaned up %d expired holds', 'fp-esperienze'), $count)]);
    }
    
    /**
     * AJAX handler: Test Meta Conversions API
     */
    public function ajaxTestMetaCAPI(): void {
        if (!CapabilityManager::canManageFPEsperienze()) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'fp-esperienze')]);
        }
        
        if (!check_ajax_referer('fp_test_meta_capi', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security check failed.', 'fp-esperienze')]);
        }
        
        $meta_capi = new \FP\Esperienze\Integrations\MetaCAPIManager();
        $result = $meta_capi->testConnection();
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * Get dashboard statistics
     */
    private function getDashboardStatistics(): array {
        global $wpdb;
        
        // Total bookings
        $total_bookings = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}fp_bookings WHERE status != 'cancelled'"
        );
        
        // This month bookings
        $month_start = date('Y-m-01');
        $month_end = date('Y-m-t');
        $month_bookings = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}fp_bookings 
                 WHERE status != 'cancelled' 
                 AND booking_date >= %s AND booking_date <= %s",
                $month_start,
                $month_end
            )
        );
        
        // Upcoming bookings (from today onwards)
        $today = date('Y-m-d');
        $upcoming_bookings = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}fp_bookings 
                 WHERE status != 'cancelled' 
                 AND booking_date >= %s",
                $today
            )
        );
        
        // Active vouchers (not expired and not used)
        $active_vouchers = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}fp_exp_vouchers 
                 WHERE expires_on >= %s AND used_at IS NULL",
                $today
            )
        );
        
        return [
            'total_bookings' => intval($total_bookings) ?: 0,
            'month_bookings' => intval($month_bookings) ?: 0,
            'upcoming_bookings' => intval($upcoming_bookings) ?: 0,
            'active_vouchers' => intval($active_vouchers) ?: 0,
        ];
    }
    
    /**
     * Get recent bookings for dashboard
     */
    private function getRecentBookings(int $limit = 5): array {
        global $wpdb;
        
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, order_id, product_id, customer_name, customer_email, booking_date, status, created_at FROM {$wpdb->prefix}fp_bookings 
                 WHERE status != 'cancelled' 
                 ORDER BY created_at DESC 
                 LIMIT %d",
                $limit
            )
        );
        
        return $results ?: [];
    }
    
    /**
     * Get status color for booking status
     */
    private function getStatusColor(string $status): string {
        $colors = [
            'confirmed' => '#28a745',
            'pending' => '#ffc107',
            'cancelled' => '#dc3545',
            'completed' => '#007cba',
            'refunded' => '#6c757d',
        ];

        return $colors[$status] ?? '#6c757d';
    }

    /**
     * Render optional dependency status widget for the dashboard sidebar.
     */
    private function renderDependencyStatusWidget(): void {
        $dependencies = DependencyChecker::checkAll();

        if (empty($dependencies)) {
            echo '<p class="fp-admin-helper-text">' . esc_html__('No optional dependencies detected.', 'fp-esperienze') . '</p>';
            return;
        }

        $missing = array_filter($dependencies, static function ($dependency) {
            return empty($dependency['available']);
        });

        echo '<ul class="fp-admin-dependency-list">';

        foreach ($dependencies as $dependency) {
            $available = ! empty($dependency['available']);
            $status_key = isset($dependency['status']) ? (string) $dependency['status'] : '';

            switch ($status_key) {
                case 'success':
                    $variant = 'success';
                    break;
                case 'warning':
                    $variant = 'warning';
                    break;
                case 'danger':
                case 'error':
                    $variant = 'danger';
                    break;
                default:
                    $variant = $available ? 'success' : 'warning';
                    break;
            }

            $status_label = $available
                ? __('Available', 'fp-esperienze')
                : __('Missing', 'fp-esperienze');

            $name = isset($dependency['name']) ? (string) $dependency['name'] : '';
            $description = isset($dependency['description']) ? (string) $dependency['description'] : '';
            $impact = isset($dependency['impact']) ? (string) $dependency['impact'] : '';

            echo '<li class="fp-admin-dependency">';
            echo '<div class="fp-admin-dependency__header">';
            printf(
                '<span class="fp-admin-badge fp-admin-badge--%1$s">%2$s</span>',
                esc_attr($variant),
                esc_html($status_label)
            );

            if ($name !== '') {
                printf('<span class="fp-admin-dependency__name">%s</span>', esc_html($name));
            }
            echo '</div>';

            if ($description !== '') {
                printf('<p class="fp-admin-helper-text">%s</p>', esc_html($description));
            }

            if (! $available && $impact !== '') {
                printf('<p class="fp-admin-helper-text fp-admin-dependency__impact">%s</p>', esc_html($impact));
            }

            echo '</li>';
        }

        echo '</ul>';

        if (! empty($missing)) {
            $instructions = DependencyChecker::getInstallationInstructions();

            if (! empty($instructions)) {
                echo '<div class="fp-admin-helper-text fp-admin-dependency__instructions">' . wp_kses_post($instructions) . '</div>';
            }

            return;
        }

        echo '<p class="fp-admin-helper-text fp-admin-dependency__success">' . esc_html__('All optional dependencies are installed. Great job!', 'fp-esperienze') . '</p>';
    }

    /**
     * Integration toolkit page with ready-to-copy snippets.
     */
    public function integrationToolkitPage(): void {
        $site_url = esc_url_raw(home_url());
        $experience_id = $this->getExampleExperienceProductId();
        $experience_token = $experience_id !== null ? (string) $experience_id : '{{experience_id}}';
        $widget_endpoint = trailingslashit($site_url) . 'wp-json/fp-exp/v1/widget/iframe/' . $experience_token;
        $iframe_title = __('Book your experience', 'fp-esperienze');

        $embedSnippet = <<<HTML
<div class="fp-esperienze-widget-wrapper" style="max-width:680px;margin:0 auto;">
    <iframe
        src="{$widget_endpoint}?theme=light"
        width="100%"
        height="680"
        loading="lazy"
        style="border:1px solid var(--wp--preset--color--light-gray,#dcdcde);border-radius:16px;"
        title="{$iframe_title}"
        allow="payment"
    ></iframe>
</div>
HTML;

        $postMessageSnippet = <<<HTML
<script>
window.addEventListener('message', function(event) {
    if (!event.data || (event.data.type !== 'fp_widget_ready' && event.data.type !== 'fp_widget_height_change')) {
        return;
    }

    var frame = document.getElementById('fp-esperienze-widget');
    if (frame && event.data.height) {
        frame.style.height = event.data.height + 'px';
    }
});
</script>

<iframe
    id="fp-esperienze-widget"
    src="{$widget_endpoint}?theme=light"
    style="width:100%;height:640px;border:0;border-radius:16px;box-shadow:0 15px 45px rgba(0,0,0,0.08);"
    title="{$iframe_title}"
    loading="lazy"
></iframe>
HTML;

        $cssSnippet = <<<CSS
:root {
    --fp-esperienze-widget-font-family: 'Inter', sans-serif;
    --fp-esperienze-widget-radius: 16px;
    --fp-esperienze-widget-primary: #ff6b35;
    --fp-esperienze-widget-primary-contrast: #ffffff;
    --fp-esperienze-widget-surface: #ffffff;
    --fp-esperienze-widget-surface-alt: #f5f7fa;
}
CSS;

        ?>
        <div class="wrap fp-esperienze-developer-tools">
            <h1><?php esc_html_e('Developer Toolkit', 'fp-esperienze'); ?></h1>
            <p class="description" style="max-width: 720px;">
                <?php esc_html_e('Share your booking widget with partners, resellers, or microsites using the copy-ready recipes below.', 'fp-esperienze'); ?>
            </p>

            <?php if ($experience_id === null) : ?>
                <div class="notice notice-warning">
                    <p><?php esc_html_e('No experience products were found. Replace {{experience_id}} in the snippets with the product ID you want to promote.', 'fp-esperienze'); ?></p>
                </div>
            <?php else : ?>
                <div class="notice notice-info">
                    <p><?php printf(esc_html__('Using experience #%d as an example. Swap the ID if you prefer another product.', 'fp-esperienze'), (int) $experience_id); ?></p>
                </div>
            <?php endif; ?>

            <h2><?php esc_html_e('Quick embed', 'fp-esperienze'); ?></h2>
            <p>
                <?php
                printf(
                    esc_html__('Paste the following HTML snippet into any CMS (WordPress, Webflow, Squarespace, HubSpot). It points to %s.', 'fp-esperienze'),
                    esc_html($widget_endpoint)
                );
                ?>
            </p>
            <textarea readonly id="fp-integration-embed" class="large-text code" rows="10"><?php echo esc_textarea($embedSnippet); ?></textarea>
            <p>
                <button type="button"
                    class="button button-secondary fp-integration-copy"
                    data-target="fp-integration-embed"
                    data-default-label="<?php echo esc_attr__('Copy embed code', 'fp-esperienze'); ?>"
                    data-copied-label="<?php echo esc_attr__('Copied!', 'fp-esperienze'); ?>"
                    data-fallback-label="<?php echo esc_attr__('Copy failed', 'fp-esperienze'); ?>"
                ><?php esc_html_e('Copy embed code', 'fp-esperienze'); ?></button>
            </p>
            <p style="max-width: 720px;">
                <?php esc_html_e('Append query parameters to adjust theming or the thank-you URL, for example ?theme=dark or ?return_url=https://partner.com/thanks.', 'fp-esperienze'); ?>
            </p>

            <hr />

            <h2><?php esc_html_e('Auto-height integration', 'fp-esperienze'); ?></h2>
            <p><?php esc_html_e('When embedding in a headless or external site, use this script to keep the iframe height in sync with the widget content.', 'fp-esperienze'); ?></p>
            <textarea readonly id="fp-integration-autoheight" class="large-text code" rows="16"><?php echo esc_textarea($postMessageSnippet); ?></textarea>
            <p>
                <button type="button"
                    class="button button-secondary fp-integration-copy"
                    data-target="fp-integration-autoheight"
                    data-default-label="<?php echo esc_attr__('Copy auto-height snippet', 'fp-esperienze'); ?>"
                    data-copied-label="<?php echo esc_attr__('Copied!', 'fp-esperienze'); ?>"
                    data-fallback-label="<?php echo esc_attr__('Copy failed', 'fp-esperienze'); ?>"
                ><?php esc_html_e('Copy auto-height snippet', 'fp-esperienze'); ?></button>
            </p>

            <div class="fp-integration-events" style="max-width: 720px;">
                <h3><?php esc_html_e('Widget events', 'fp-esperienze'); ?></h3>
                <ul class="ul-disc">
                    <li><code>fp_widget_ready</code> — <?php esc_html_e('initial dimensions available', 'fp-esperienze'); ?></li>
                    <li><code>fp_widget_height_change</code> — <?php esc_html_e('widget height changed; update iframe height', 'fp-esperienze'); ?></li>
                    <li><code>fp_widget_checkout</code> — <?php esc_html_e('customer moved to checkout (contains the checkout URL)', 'fp-esperienze'); ?></li>
                    <li><code>fp_widget_booking_success</code> — <?php esc_html_e('booking completed with the WooCommerce order ID', 'fp-esperienze'); ?></li>
                </ul>
            </div>

            <hr />

            <h2><?php esc_html_e('Theme tokens', 'fp-esperienze'); ?></h2>
            <p><?php esc_html_e('Drop these CSS variables into a global stylesheet to align the widget with your brand guidelines.', 'fp-esperienze'); ?></p>
            <textarea readonly id="fp-integration-theme" class="large-text code" rows="10"><?php echo esc_textarea($cssSnippet); ?></textarea>
            <p>
                <button type="button"
                    class="button button-secondary fp-integration-copy"
                    data-target="fp-integration-theme"
                    data-default-label="<?php echo esc_attr__('Copy CSS variables', 'fp-esperienze'); ?>"
                    data-copied-label="<?php echo esc_attr__('Copied!', 'fp-esperienze'); ?>"
                    data-fallback-label="<?php echo esc_attr__('Copy failed', 'fp-esperienze'); ?>"
                ><?php esc_html_e('Copy CSS variables', 'fp-esperienze'); ?></button>
            </p>

            <p class="description" style="max-width: 720px;">
                <?php
                printf(
                    wp_kses(
                        /* translators: %s: link to widget integration guide */
                        __('Need more recipes? Consult the <a href="%s" target="_blank" rel="noopener noreferrer">Widget Integration Guide</a>.', 'fp-esperienze'),
                        [
                            'a' => [
                                'href' => [],
                                'target' => [],
                                'rel' => [],
                            ],
                        ]
                    ),
                    esc_url(plugins_url('WIDGET_INTEGRATION_GUIDE.md', FP_ESPERIENZE_PLUGIN_FILE))
                );
                ?>
            </p>
        </div>
        <?php
    }

    /**
     * Attempt to grab a recently created experience product for pre-filled snippets.
     */
    private function getExampleExperienceProductId(): ?int {
        if (!function_exists('wc_get_products')) {
            return null;
        }

        $products = wc_get_products([
            'type' => 'experience',
            'status' => ['publish', 'pending', 'draft'],
            'limit' => 1,
            'orderby' => 'date',
            'order' => 'DESC',
            'return' => 'ids',
        ]);

        if (is_array($products) && !empty($products)) {
            return (int) $products[0];
        }

        return null;
    }
}
