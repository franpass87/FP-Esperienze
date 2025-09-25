<?php
/**
 * Onboarding helper utilities shared between the setup wizard and CLI tools.
 *
 * @package FP\Esperienze\Admin
 */

namespace FP\Esperienze\Admin;

use DateInterval;
use DateTimeImmutable;
use Exception;
use FP\Esperienze\Data\MeetingPointManager;
use FP\Esperienze\Data\ScheduleManager;

defined('ABSPATH') || exit;

/**
 * Helper responsible for onboarding related automation.
 */
class OnboardingHelper {
    /**
     * Cache checklist items for the current request.
     *
     * @var array<int, array<string, mixed>>|null
     */
    private static ?array $checklistCache = null;

    /**
     * Retrieve the onboarding checklist items with completion state.
     *
     * Each entry contains the following keys:
     * - id: machine readable identifier.
     * - label: translated human label.
     * - description: extra context for UI rendering.
     * - completed: whether the prerequisite is satisfied.
     * - count: related resource count used for display.
     * - action: optional admin URL to resolve the item.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getChecklistItems(): array {
        if (self::$checklistCache !== null) {
            return self::$checklistCache;
        }

        $meetingPoints = self::countMeetingPoints();
        $experienceProducts = self::countExperienceProducts();
        $schedules = self::countSchedules();
        $paymentGateways = self::countPaymentGateways();
        $emails = self::countEnabledEmails();

        $items = [
            [
                'id' => 'meeting_points',
                'label' => __('Meeting points configured', 'fp-esperienze'),
                'description' => __('Create at least one meeting point so customers know where to reach the guide.', 'fp-esperienze'),
                'count' => $meetingPoints,
                'completed' => $meetingPoints > 0,
                'action' => admin_url('admin.php?page=fp-esperienze-meeting-points'),
            ],
            [
                'id' => 'experience_products',
                'label' => __('Experience products published', 'fp-esperienze'),
                'description' => __('Publish an Experience product to expose your availability in the storefront.', 'fp-esperienze'),
                'count' => $experienceProducts,
                'completed' => $experienceProducts > 0,
                'action' => admin_url('post-new.php?post_type=product'),
            ],
            [
                'id' => 'active_schedules',
                'label' => __('Schedules ready for booking', 'fp-esperienze'),
                'description' => __('Configure at least one active schedule so time slots can be sold.', 'fp-esperienze'),
                'count' => $schedules,
                'completed' => $schedules > 0,
                'action' => admin_url('edit.php?post_type=product'),
            ],
            [
                'id' => 'payment_gateways',
                'label' => __('Payment methods enabled', 'fp-esperienze'),
                'description' => __('Enable at least one WooCommerce payment gateway to accept bookings.', 'fp-esperienze'),
                'count' => $paymentGateways,
                'completed' => $paymentGateways > 0,
                'action' => admin_url('admin.php?page=wc-settings&tab=checkout'),
            ],
            [
                'id' => 'email_notifications',
                'label' => __('Order emails ready to send', 'fp-esperienze'),
                'description' => __('Keep the WooCommerce customer processing/confirmation emails enabled so guests receive booking details.', 'fp-esperienze'),
                'count' => $emails,
                'completed' => $emails > 0,
                'action' => admin_url('admin.php?page=wc-settings&tab=email'),
            ],
        ];

        self::$checklistCache = $items;

        return $items;
    }

    /**
     * Provide a completion summary for UI and CLI usage.
     *
     * @return array{completed:int,total:int,percentage:float}
     */
    public static function getCompletionSummary(): array {
        $items = self::getChecklistItems();
        $total = count($items);
        $completed = 0;

        foreach ($items as $item) {
            if (isset($item['completed']) && (bool) $item['completed']) {
                $completed++;
            }
        }

        $percentage = $total > 0 ? round(($completed / $total) * 100, 2) : 0.0;

        return [
            'completed' => $completed,
            'total' => $total,
            'percentage' => $percentage,
        ];
    }

    /**
     * Seed demo content (meeting point, experience product and schedule) to
     * accelerate onboarding.
     *
     * @return array{status:string,message:string}
     */
    public static function seedDemoContent(): array {
        $has_permission = false;
        if (function_exists('current_user_can')) {
            $has_permission = current_user_can('manage_woocommerce') === true;
        }

        if (!$has_permission && !self::isCli()) {
            return [
                'status' => 'error',
                'message' => __('You do not have permission to create demo content.', 'fp-esperienze'),
            ];
        }

        $already_seeded = (int) get_option('fp_esperienze_demo_content_created') === 1;
        if ($already_seeded) {
            return [
                'status' => 'warning',
                'message' => __('Demo content was already generated. You can safely customise or delete it.', 'fp-esperienze'),
            ];
        }

        if (!self::isWooCommerceReady()) {
            return [
                'status' => 'error',
                'message' => __('WooCommerce must be active to generate demo content.', 'fp-esperienze'),
            ];
        }

        $meeting_point_id = self::ensureDemoMeetingPoint();
        if ($meeting_point_id <= 0) {
            return [
                'status' => 'error',
                'message' => __('Unable to create or reuse a meeting point for demo content.', 'fp-esperienze'),
            ];
        }

        $product_id = self::ensureDemoExperienceProduct($meeting_point_id);
        if ($product_id <= 0) {
            return [
                'status' => 'error',
                'message' => __('Failed to create the demo experience product.', 'fp-esperienze'),
            ];
        }

        self::ensureDemoSchedule($product_id, $meeting_point_id);

        update_option('fp_esperienze_demo_content_created', 1, false);

        return [
            'status' => 'success',
            'message' => __('Demo experience content created! Review it under Products → Experiences.', 'fp-esperienze'),
        ];
    }

    /**
     * Build aggregated booking statistics for the provided time frame.
     *
     * @param int $days Number of days to include (defaults to 1).
     * @return array<string, mixed>
     */
    public static function getDailyReportData(int $days = 1): array {
        $days = max(1, $days);

        $end = new DateTimeImmutable(current_time('mysql'));
        $start = $end->sub(new DateInterval('P' . $days . 'D'));

        global $wpdb;
        $table = $wpdb->prefix . 'fp_bookings';

        $table_exists = $wpdb->get_var(
            $wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))
        );

        if ($table_exists !== $table) {
            return [
                'range_start' => $start->format('Y-m-d'),
                'range_end' => $end->format('Y-m-d'),
                'by_day' => [],
                'overall' => [
                    'total_bookings' => 0,
                    'participants' => 0,
                    'revenue' => 0.0,
                    'by_status' => [],
                ],
            ];
        }

        $results = (array) $wpdb->get_results(
            $wpdb->prepare(
                "SELECT DATE(booking_date) AS day, status, COUNT(*) AS total, SUM(participants) AS participants, SUM(total_amount) AS revenue
                 FROM {$table}
                 WHERE booking_date >= %s
                 GROUP BY day, status
                 ORDER BY day DESC",
                $start->format('Y-m-d')
            ),
            'ARRAY_A'
        );

        $by_day = [];
        $overall = [
            'total_bookings' => 0,
            'participants' => 0,
            'revenue' => 0.0,
            'by_status' => [],
        ];

        foreach ($results as $row) {
            $day = $row['day'];
            $status = $row['status'] ?? 'unknown';
            $total = (int) ($row['total'] ?? 0);
            $participants = (int) ($row['participants'] ?? 0);
            $revenue = (float) ($row['revenue'] ?? 0.0);

            if (!isset($by_day[$day])) {
                $by_day[$day] = [
                    'statuses' => [],
                    'participants' => 0,
                    'revenue' => 0.0,
                    'bookings' => 0,
                ];
            }

            $by_day[$day]['statuses'][$status] = (
                $by_day[$day]['statuses'][$status] ?? 0
            ) + $total;
            $by_day[$day]['participants'] += $participants;
            $by_day[$day]['revenue'] += $revenue;
            $by_day[$day]['bookings'] += $total;

            $overall['total_bookings'] += $total;
            $overall['participants'] += $participants;
            $overall['revenue'] += $revenue;
            $overall['by_status'][$status] = (
                $overall['by_status'][$status] ?? 0
            ) + $total;
        }

        return [
            'range_start' => $start->format('Y-m-d'),
            'range_end' => $end->format('Y-m-d'),
            'by_day' => $by_day,
            'overall' => $overall,
        ];
    }

    /**
     * Count meeting points defined in the catalog.
     */
    private static function countMeetingPoints(): int {
        if (!class_exists(MeetingPointManager::class)) {
            return 0;
        }

        $meeting_points = MeetingPointManager::getAllMeetingPoints(false);

        return count($meeting_points);
    }

    /**
     * Count published experience products.
     */
    private static function countExperienceProducts(): int {
        if (!function_exists('wc_get_products')) {
            return 0;
        }

        $products = wc_get_products([
            'type' => 'experience',
            'limit' => -1,
            'status' => ['publish', 'draft', 'pending'],
            'return' => 'ids',
        ]);

        return is_array($products) ? count($products) : 0;
    }

    /**
     * Count the number of active schedules.
     */
    private static function countSchedules(): int {
        global $wpdb;
        $table = $wpdb->prefix . 'fp_schedules';

        $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE is_active = 1");

        return $count;
    }

    /**
     * Count enabled payment gateways.
     */
    private static function countPaymentGateways(): int {
        if (!self::isWooCommerceReady()) {
            return 0;
        }

        try {
            $gateways = WC()->payment_gateways()->get_available_payment_gateways();
        } catch (Exception $e) {
            return 0;
        }

        return count($gateways);
    }

    /**
     * Count enabled transactional emails that cover booking confirmations.
     */
    private static function countEnabledEmails(): int {
        if (!class_exists('WC_Emails')) {
            return 0;
        }

        $emails = \WC_Emails::instance()->get_emails();
        $count = 0;

        foreach ($emails as $email) {
            if (!$email->is_enabled()) {
                continue;
            }

            $identifier = method_exists($email, 'get_id') ? $email->get_id() : '';

            if (in_array($identifier, ['customer_processing_order', 'customer_completed_order'], true)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Ensure WooCommerce is active and functional in the current context.
     */
    private static function isWooCommerceReady(): bool {
        return function_exists('WC');
    }

    /**
     * Detect whether the current execution context is WP-CLI.
     */
    private static function isCli(): bool {
        if (!defined('WP_CLI')) {
            return false;
        }

        $cli_flag = constant('WP_CLI');

        return $cli_flag === true;
    }

    /**
     * Create or reuse a meeting point suitable for demo data.
     *
     * @return int Meeting point ID or 0 on failure.
     */
    private static function ensureDemoMeetingPoint(): int {
        $existing = MeetingPointManager::getAllMeetingPoints(false);
        if ($existing !== []) {
            $first = reset($existing);
            if (is_object($first) && isset($first->id)) {
                return (int) $first->id;
            }
        }

        $data = [
            'name' => __('Demo Waterfront Pier', 'fp-esperienze'),
            'address' => __('Riva degli Schiavoni, 30122 Venezia VE, Italy', 'fp-esperienze'),
            'lat' => 45.4335,
            'lng' => 12.3462,
            'note' => __('Meet the guide under the blue flag 15 minutes before departure.', 'fp-esperienze'),
        ];

        $meeting_point_id = MeetingPointManager::createMeetingPoint($data);

        return is_int($meeting_point_id) ? $meeting_point_id : 0;
    }

    /**
     * Create or reuse a demo experience product.
     *
     * @param int $meeting_point_id Meeting point ID.
     * @return int Product ID or 0 on failure.
     */
    private static function ensureDemoExperienceProduct(int $meeting_point_id): int {
        $existing = get_page_by_title('Sunset in Venice – Demo Experience', 'OBJECT', 'product');
        if ($existing instanceof \WP_Post) {
            wp_set_object_terms($existing->ID, 'experience', 'product_type');

            /** @var int $existing_id */
            $existing_id = $existing->ID;

            return $existing_id;
        }

        /** @var int|\WP_Error $product_result */
        $product_result = wp_insert_post([
            'post_title' => 'Sunset in Venice – Demo Experience',
            'post_type' => 'product',
            'post_status' => 'publish',
            'post_content' => __('<p>Experience a two-hour guided walk through the Venetian lagoon as the sun sets, featuring live storytelling, tasting of local cicchetti, and hidden viewpoints perfect for photos.</p>', 'fp-esperienze'),
            'post_excerpt' => __('Guided sunset walk with tasting and live storytelling.', 'fp-esperienze'),
        ]);

        if (is_wp_error($product_result)) {
            return 0;
        }

        /** @var int $product_id */
        $product_id = $product_result;
        if ($product_id <= 0) {
            return 0;
        }

        wp_set_object_terms($product_id, 'experience', 'product_type');

        update_post_meta($product_id, '_virtual', 'yes');
        update_post_meta($product_id, '_sold_individually', 'no');
        update_post_meta($product_id, '_manage_stock', 'no');
        update_post_meta($product_id, '_regular_price', '79');
        update_post_meta($product_id, '_price', '79');
        update_post_meta($product_id, '_fp_experience_type', 'experience');
        update_post_meta($product_id, '_fp_exp_included', __('Local guide, two cicchetti tastings, lagoon ferry ticket.', 'fp-esperienze'));
        update_post_meta($product_id, '_fp_exp_excluded', __('Hotel pickup, personal expenses, travel insurance.', 'fp-esperienze'));
        update_post_meta($product_id, '_experience_duration', 120);
        update_post_meta($product_id, '_fp_exp_cutoff_minutes', 120);

        // Flag the preferred meeting point for the default schedule builder UI.
        update_post_meta($product_id, '_fp_default_meeting_point_id', $meeting_point_id);

        return $product_id;
    }

    /**
     * Ensure a recurring schedule exists for the demo experience.
     *
     * @param int $product_id Product ID.
     * @param int $meeting_point_id Meeting point ID.
     */
    private static function ensureDemoSchedule(int $product_id, int $meeting_point_id): void {
        global $wpdb;
        $table = $wpdb->prefix . 'fp_schedules';
        $existing = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE product_id = %d",
                $product_id
            )
        );

        if ($existing > 0) {
            return;
        }

        ScheduleManager::createSchedule([
            'product_id' => $product_id,
            'schedule_type' => 'recurring',
            'day_of_week' => 5, // Friday
            'start_time' => '18:00:00',
            'duration_min' => 120,
            'capacity' => 18,
            'lang' => 'en',
            'meeting_point_id' => $meeting_point_id,
            'price_adult' => 79,
            'price_child' => 39,
        ]);

        ScheduleManager::createSchedule([
            'product_id' => $product_id,
            'schedule_type' => 'recurring',
            'day_of_week' => 6, // Saturday
            'start_time' => '18:30:00',
            'duration_min' => 120,
            'capacity' => 20,
            'lang' => 'it',
            'meeting_point_id' => $meeting_point_id,
            'price_adult' => 79,
            'price_child' => 39,
        ]);
    }

}
