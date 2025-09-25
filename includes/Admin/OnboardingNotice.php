<?php
/**
 * Persistent onboarding reminder for administrators.
 *
 * @package FP\Esperienze\Admin
 */

namespace FP\Esperienze\Admin;

use function absint;
use function add_query_arg;
use function admin_url;
use function current_time;
use function current_user_can;
use function esc_html;
use function esc_html__;
use function esc_url;
use function get_current_screen;
use function get_current_user_id;
use function get_user_meta;
use function is_user_logged_in;
use function remove_query_arg;
use function update_user_meta;
use function delete_user_meta;
use function wp_get_referer;
use function wp_nonce_url;
use function wp_safe_redirect;
use function wp_unslash;
use function wp_verify_nonce;

use const DAY_IN_SECONDS;
use const WEEK_IN_SECONDS;

defined('ABSPATH') || exit;

/**
 * Display onboarding progress notice across key admin screens.
 */
class OnboardingNotice {
    private const USER_META_KEY = 'fp_esperienze_onboarding_notice_dismissed_until';

    /**
     * Hook into admin lifecycle.
     */
    public function __construct() {
        add_action('admin_init', [$this, 'maybeHandleDismiss']);
        add_action('admin_notices', [$this, 'renderNotice']);
    }

    /**
     * Handle "remind me later" dismissal links.
     */
    public function maybeHandleDismiss(): void {
        if (!is_user_logged_in()) {
            return;
        }

        if (!isset($_GET['fp_esperienze_dismiss_onboarding'])) { // phpcs:ignore WordPress.Security.NonceVerification
            return;
        }

        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        $nonce = isset($_GET['_wpnonce']) ? wp_unslash($_GET['_wpnonce']) : ''; // phpcs:ignore WordPress.Security.NonceVerification
        if (!wp_verify_nonce($nonce, 'fp-esperienze-dismiss-onboarding')) {
            return;
        }

        $snooze_days = isset($_GET['snooze']) ? absint(wp_unslash($_GET['snooze'])) : 7; // phpcs:ignore WordPress.Security.NonceVerification
        if ($snooze_days <= 0) {
            $snooze_days = 7;
        }

        $timestamp = current_time('timestamp') + ($snooze_days * DAY_IN_SECONDS);
        update_user_meta(get_current_user_id(), self::USER_META_KEY, $timestamp);

        $redirect = wp_get_referer();
        if (!$redirect) {
            $redirect = admin_url();
        }

        wp_safe_redirect(remove_query_arg(['fp_esperienze_dismiss_onboarding', '_wpnonce', 'snooze'], $redirect));
        exit;
    }

    /**
     * Render the onboarding notice when tasks are still pending.
     */
    public function renderNotice(): void {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if ($screen !== null && !$this->isScreenAllowed($screen->id)) {
            return;
        }

        $dismissed_until = (int) get_user_meta(get_current_user_id(), self::USER_META_KEY, true);
        if ($dismissed_until > current_time('timestamp')) {
            return;
        }

        $summary = OnboardingHelper::getCompletionSummary();
        if ($summary['total'] === 0 || $summary['completed'] >= $summary['total']) {
            if ($dismissed_until !== 0) {
                delete_user_meta(get_current_user_id(), self::USER_META_KEY);
            }
            return;
        }

        $pending = array_filter(
            OnboardingHelper::getChecklistItems(),
            static fn($item) => empty($item['completed'])
        );

        if (empty($pending)) {
            return;
        }

        $setup_url   = admin_url('admin.php?page=fp-esperienze-setup-wizard');
        $dashboard   = admin_url('index.php');
        $dismiss_url = wp_nonce_url(
            add_query_arg(
                [
                    'fp_esperienze_dismiss_onboarding' => '1',
                    'snooze' => WEEK_IN_SECONDS / DAY_IN_SECONDS,
                ]
            ),
            'fp-esperienze-dismiss-onboarding'
        );

        echo '<div class="notice notice-warning fp-esperienze-onboarding-notice">';
        echo '<p><strong>' . esc_html__('Complete the FP Esperienze onboarding checklist', 'fp-esperienze') . '</strong></p>';
        printf(
            '<p>%s</p>',
            esc_html(
                sprintf(
                    // translators: 1: completed tasks, 2: total tasks.
                    __('You have completed %1$d of %2$d onboarding tasks. Finish the remaining items to start selling your experiences.', 'fp-esperienze'),
                    (int) $summary['completed'],
                    (int) $summary['total']
                )
            )
        );

        echo '<ul class="fp-esperienze-onboarding-list">';
        foreach ($pending as $item) {
            $label = isset($item['label']) ? $item['label'] : '';
            $description = isset($item['description']) ? $item['description'] : '';
            $action = isset($item['action']) ? $item['action'] : '';

            echo '<li>';
            echo '<strong>' . esc_html($label) . '</strong>';
            if (!empty($description)) {
                echo '<span>' . esc_html($description) . '</span>';
            }
            if (!empty($action)) {
                echo ' <a class="button-link" href="' . esc_url($action) . '">' . esc_html__('Open', 'fp-esperienze') . '</a>';
            }
            echo '</li>';
        }
        echo '</ul>';

        echo '<p class="fp-esperienze-onboarding-actions">';
        echo '<a class="button button-primary" href="' . esc_url($setup_url) . '">' . esc_html__('Launch setup wizard', 'fp-esperienze') . '</a> ';
        echo '<a class="button" href="' . esc_url($dashboard) . '">' . esc_html__('Review checklist', 'fp-esperienze') . '</a> ';
        echo '<a class="button-link" href="' . esc_url($dismiss_url) . '">' . esc_html__('Remind me later', 'fp-esperienze') . '</a>';
        echo '</p>';

        echo '</div>';
    }

    /**
     * Limit the notice to relevant admin screens.
     */
    private function isScreenAllowed(string $screen_id): bool {
        if ($screen_id === 'dashboard') {
            return true;
        }

        if ($screen_id === 'toplevel_page_fp-esperienze') {
            return true;
        }

        return str_starts_with($screen_id, 'fp-esperienze_page_');
    }
}
