<?php
/**
 * Dashboard widget that surfaces onboarding progress and quick actions.
 *
 * @package FP\Esperienze\Admin
 */

namespace FP\Esperienze\Admin;

use FP\Esperienze\Admin\OnboardingHelper;

defined('ABSPATH') || exit;

/**
 * Register the FP Esperienze onboarding dashboard widget.
 */
class OnboardingDashboardWidget {
    /**
     * Transient key used for seed demo notices.
     */
    private const NOTICE_KEY = 'fp_esperienze_seed_demo_notice_';

    /**
     * Constructor.
     */
    public function __construct() {
        add_action('wp_dashboard_setup', [$this, 'registerWidget']);
        add_action('admin_post_fp_esperienze_seed_demo', [$this, 'handleSeedDemo']);
        add_action('admin_notices', [$this, 'maybeDisplayNotice']);
    }

    /**
     * Register the onboarding widget.
     */
    public function registerWidget(): void {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        wp_add_dashboard_widget(
            'fp_esperienze_onboarding',
            __('FP Esperienze onboarding', 'fp-esperienze'),
            [$this, 'renderWidget']
        );
    }

    /**
     * Render widget content.
     */
    public function renderWidget(): void {
        $summary = OnboardingHelper::getCompletionSummary();
        $items = OnboardingHelper::getChecklistItems();
        $completed = (int) ($summary['completed'] ?? 0);
        $total = (int) ($summary['total'] ?? 0);
        $percentage = (float) ($summary['percentage'] ?? 0.0);
        $percentage_display = number_format_i18n($percentage, 2);

        $setup_url = admin_url('admin.php?page=fp-esperienze-setup-wizard');
        $integration_url = admin_url('admin.php?page=fp-esperienze-developer-tools');
        $alerts_url = admin_url('admin.php?page=fp-esperienze-notifications');

        $seed_nonce = wp_create_nonce('fp_esperienze_seed_demo');

        echo '<div class="fp-onboarding-widget">';
        echo '<p class="fp-onboarding-progress">' . sprintf(
            /* translators: 1: completed steps, 2: total steps, 3: percentage */
            esc_html__('Progress: %1$d of %2$d prerequisites complete (%3$s%%)', 'fp-esperienze'),
            $completed,
            $total,
            esc_html($percentage_display)
        ) . '</p>';

        echo '<ul class="fp-onboarding-checklist">';
        foreach ($items as $item) {
            $is_complete = !empty($item['completed']);
            $icon = $is_complete ? 'dashicons-yes-alt' : 'dashicons-clock';
            $status_class = $is_complete ? 'complete' : 'pending';
            $label = esc_html($item['label'] ?? '');
            $description = esc_html($item['description'] ?? '');

            echo '<li class="' . esc_attr('item-' . $status_class) . '">';
            echo '<span class="dashicons ' . esc_attr($icon) . '"></span>';
            echo '<div class="fp-onboarding-item-text">';
            echo '<strong>' . $label . '</strong>';
            if ($description !== '') {
                echo '<p>' . $description . '</p>';
            }
            if (!$is_complete && !empty($item['action'])) {
                $action_url = esc_url($item['action']);
                echo '<p><a class="button button-secondary" href="' . $action_url . '">' . esc_html__('Complete this step', 'fp-esperienze') . '</a></p>';
            }
            echo '</div>';
            echo '</li>';
        }
        echo '</ul>';

        echo '<div class="fp-onboarding-actions">';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="fp_esperienze_seed_demo" />';
        echo '<input type="hidden" name="fp_esperienze_seed_demo_nonce" value="' . esc_attr($seed_nonce) . '" />';
        echo '<button type="submit" class="button button-primary">' . esc_html__('Generate demo content', 'fp-esperienze') . '</button>';
        echo '</form>';

        $wizard_link = '<a href="' . esc_url($setup_url) . '">' . esc_html__('setup wizard', 'fp-esperienze') . '</a>';
        $guidance_text = sprintf(
            /* translators: %s: link to the setup wizard */
            __('Need guidance? Continue in the %s.', 'fp-esperienze'),
            $wizard_link
        );
        echo '<p>' . wp_kses_post($guidance_text) . '</p>';

        $integration_link = '<a href="' . esc_url($integration_url) . '">' . esc_html__('Integration Toolkit', 'fp-esperienze') . '</a>';
        $alerts_link = '<a href="' . esc_url($alerts_url) . '">' . esc_html__('Operational Alerts', 'fp-esperienze') . '</a>';
        $handoff_text = sprintf(
            /* translators: 1: link to the integration toolkit, 2: link to operational alerts */
            __('Share embeds via the %1$s or configure digests in %2$s.', 'fp-esperienze'),
            $integration_link,
            $alerts_link
        );
        echo '<p>' . wp_kses_post($handoff_text) . '</p>';

        echo '<p class="description">' . esc_html__('Automation tip: run “wp fp-esperienze operations health-check” after each deploy to verify production readiness.', 'fp-esperienze') . '</p>';
        echo '</div>';

        echo '<style>
            .fp-onboarding-widget {font-size:14px;}
            .fp-onboarding-progress {font-weight:600;margin-bottom:8px;}
            .fp-onboarding-checklist {list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:12px;}
            .fp-onboarding-checklist li {display:flex;gap:12px;padding:12px;background:#fff;border:1px solid #dcdcde;border-radius:8px;}
            .fp-onboarding-checklist li.item-complete {border-color:#00a32a;background:#f0fff4;}
            .fp-onboarding-checklist li .dashicons {font-size:20px;width:20px;height:20px;color:#2271b1;margin-top:2px;}
            .fp-onboarding-checklist li.item-complete .dashicons {color:#00a32a;}
            .fp-onboarding-item-text p {margin:4px 0 0;color:#50575e;}
            .fp-onboarding-actions {margin-top:16px;border-top:1px solid #dcdcde;padding-top:12px;}
            .fp-onboarding-actions form {margin-bottom:12px;}
        </style>';

        echo '</div>';
    }

    /**
     * Handle demo content generation from the dashboard widget.
     */
    public function handleSeedDemo(): void {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('You do not have permission to perform this action.', 'fp-esperienze'));
        }

        check_admin_referer('fp_esperienze_seed_demo', 'fp_esperienze_seed_demo_nonce');

        $result = OnboardingHelper::seedDemoContent();
        $this->storeNotice($result);

        $redirect = wp_get_referer();
        if (empty($redirect)) {
            $redirect = admin_url('index.php');
        }

        wp_safe_redirect($redirect);
        exit;
    }

    /**
     * Display the notice after attempting to seed demo content.
     */
    public function maybeDisplayNotice(): void {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || $screen->base !== 'dashboard') {
            return;
        }

        $key = $this->getNoticeKey();
        $notice = get_transient($key);
        if (!$notice || !is_array($notice)) {
            return;
        }

        delete_transient($key);

        $status = $notice['status'] ?? 'info';
        $message = $notice['message'] ?? '';
        if ($message === '') {
            return;
        }

        switch ($status) {
            case 'success':
                $class = 'notice-success';
                break;
            case 'error':
                $class = 'notice-error';
                break;
            case 'warning':
                $class = 'notice-warning';
                break;
            default:
                $class = 'notice-info';
                break;
        }

        printf('<div class="notice %1$s"><p>%2$s</p></div>', esc_attr($class), esc_html($message));
    }

    /**
     * Store a transient so the notice can be shown after redirect.
     *
     * @param array{status:string,message:string} $result Result from demo seeding.
     */
    private function storeNotice(array $result): void {
        $key = $this->getNoticeKey();
        set_transient($key, $result, MINUTE_IN_SECONDS);
    }

    /**
     * Build a unique transient key per user to avoid leaking notices.
     */
    private function getNoticeKey(): string {
        $user_id = get_current_user_id();

        return self::NOTICE_KEY . $user_id;
    }
}
