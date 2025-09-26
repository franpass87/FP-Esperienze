<?php
/**
 * Operational alerts and daily digest automation.
 *
 * @package FP\Esperienze\Admin
 */

namespace FP\Esperienze\Admin;

use DateTimeImmutable;
use FP\Esperienze\Admin\OnboardingHelper;
use WP_Error;

defined('ABSPATH') || exit;

/**
 * Manage operational alerts (email and Slack) for booking performance.
 */
class OperationalAlerts {
    public const OPTION_EMAIL_ENABLED = 'fp_esperienze_alerts_email_enabled';
    public const OPTION_EMAIL_RECIPIENTS = 'fp_esperienze_alerts_email_recipients';
    public const OPTION_SLACK_WEBHOOK = 'fp_esperienze_alerts_slack_webhook';
    public const OPTION_MIN_BOOKINGS = 'fp_esperienze_alerts_min_bookings';
    public const OPTION_LOOKBACK_DAYS = 'fp_esperienze_alerts_lookback_days';
    public const OPTION_SEND_HOUR = 'fp_esperienze_alerts_send_hour';
    public const OPTION_LAST_STATUS = 'fp_esperienze_alerts_last_status';
    public const CRON_HOOK = 'fp_esperienze_send_daily_digest';

    /**
     * Constructor.
     */
    public function __construct() {
        MenuRegistry::instance()->registerPage([
            'slug'       => 'fp-esperienze-notifications',
            'page_title' => __('Notifications & Alerts', 'fp-esperienze'),
            'menu_title' => __('Notifications & Alerts', 'fp-esperienze'),
            'capability' => 'manage_woocommerce',
            'callback'   => [$this, 'renderPage'],
            'order'      => 110,
            'aliases'    => ['fp-esperienze-operational-alerts'],
        ]);

        add_action('admin_init', [$this, 'handleFormSubmission']);
        add_action('init', [$this, 'maybeScheduleDigest']);
        add_action(self::CRON_HOOK, [$this, 'handleScheduledDigest']);
    }

    /**
     * Handle settings form submission.
     */
    public function handleFormSubmission(): void {
        if (!isset($_POST['fp_esperienze_alerts_nonce'])) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
            return;
        }

        if (!wp_verify_nonce(wp_unslash($_POST['fp_esperienze_alerts_nonce']), 'fp_esperienze_alerts_save')) {
            return;
        }

        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        $email_enabled = isset($_POST['fp_esperienze_alerts_email_enabled']) ? 1 : 0;
        update_option(self::OPTION_EMAIL_ENABLED, $email_enabled);

        $raw_recipients = sanitize_textarea_field(wp_unslash($_POST['fp_esperienze_alerts_email_recipients'] ?? ''));
        update_option(self::OPTION_EMAIL_RECIPIENTS, $raw_recipients);

        $slack_webhook = esc_url_raw(wp_unslash($_POST['fp_esperienze_alerts_slack_webhook'] ?? ''));
        update_option(self::OPTION_SLACK_WEBHOOK, $slack_webhook);

        $min_bookings = absint(wp_unslash($_POST['fp_esperienze_alerts_min_bookings'] ?? 0));
        update_option(self::OPTION_MIN_BOOKINGS, $min_bookings);

        $lookback_days = max(1, absint(wp_unslash($_POST['fp_esperienze_alerts_lookback_days'] ?? 1)));
        update_option(self::OPTION_LOOKBACK_DAYS, $lookback_days);

        $send_hour = absint(wp_unslash($_POST['fp_esperienze_alerts_send_hour'] ?? 7));
        $send_hour = max(0, min(23, $send_hour));
        update_option(self::OPTION_SEND_HOUR, $send_hour);

        if (isset($_POST['fp_esperienze_alerts_send_now'])) {
            $result = self::dispatchDigest('all', $lookback_days);
            $this->storeLastStatus($result);
            add_settings_error(
                'fp_esperienze_alerts',
                'fp_esperienze_alerts_send_now',
                esc_html($result['message']),
                $result['status'] === 'success' ? 'updated' : 'error'
            );
        } else {
            add_settings_error(
                'fp_esperienze_alerts',
                'fp_esperienze_alerts_saved',
                esc_html__('Notifications & alerts settings saved.', 'fp-esperienze'),
                'updated'
            );
        }

        $this->maybeScheduleDigest(true);
    }

    /**
     * Render settings page markup.
     */
    public function renderPage(): void {
        settings_errors('fp_esperienze_alerts');

        $email_enabled = (bool) get_option(self::OPTION_EMAIL_ENABLED, false);
        $email_recipients = get_option(self::OPTION_EMAIL_RECIPIENTS, get_option('admin_email'));
        $slack_webhook = get_option(self::OPTION_SLACK_WEBHOOK, '');
        $min_bookings = (int) get_option(self::OPTION_MIN_BOOKINGS, 1);
        $lookback_days = (int) get_option(self::OPTION_LOOKBACK_DAYS, 1);
        $send_hour = (int) get_option(self::OPTION_SEND_HOUR, 7);
        $last_status = get_option(self::OPTION_LAST_STATUS, []);

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Notifications & Alerts', 'fp-esperienze'); ?></h1>
            <p class="description">
                <?php esc_html_e('Receive automated booking digests and alerts when activity drops below expectations.', 'fp-esperienze'); ?>
            </p>

            <form method="post" action="">
                <?php wp_nonce_field('fp_esperienze_alerts_save', 'fp_esperienze_alerts_nonce'); ?>

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label for="fp_esperienze_alerts_email_enabled"><?php esc_html_e('Email digest', 'fp-esperienze'); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" name="fp_esperienze_alerts_email_enabled" id="fp_esperienze_alerts_email_enabled" value="1" <?php checked($email_enabled); ?>>
                                <?php esc_html_e('Send a daily digest email to the specified recipients.', 'fp-esperienze'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('Separate multiple recipients with commas or line breaks.', 'fp-esperienze'); ?></p>
                            <textarea name="fp_esperienze_alerts_email_recipients" id="fp_esperienze_alerts_email_recipients" rows="3" class="large-text"><?php echo esc_textarea($email_recipients); ?></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="fp_esperienze_alerts_slack_webhook"><?php esc_html_e('Slack webhook URL', 'fp-esperienze'); ?></label>
                        </th>
                        <td>
                            <input type="url" name="fp_esperienze_alerts_slack_webhook" id="fp_esperienze_alerts_slack_webhook" value="<?php echo esc_attr($slack_webhook); ?>" class="regular-text" placeholder="https://hooks.slack.com/services/...">
                            <p class="description"><?php esc_html_e('Optional: send the digest to a Slack channel via incoming webhook.', 'fp-esperienze'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="fp_esperienze_alerts_min_bookings"><?php esc_html_e('Minimum bookings threshold', 'fp-esperienze'); ?></label>
                        </th>
                        <td>
                            <input type="number" name="fp_esperienze_alerts_min_bookings" id="fp_esperienze_alerts_min_bookings" min="0" value="<?php echo esc_attr($min_bookings); ?>" class="small-text">
                            <p class="description"><?php esc_html_e('Trigger a warning when total bookings within the period fall below this number.', 'fp-esperienze'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="fp_esperienze_alerts_lookback_days"><?php esc_html_e('Lookback window (days)', 'fp-esperienze'); ?></label>
                        </th>
                        <td>
                            <input type="number" name="fp_esperienze_alerts_lookback_days" id="fp_esperienze_alerts_lookback_days" min="1" max="30" value="<?php echo esc_attr($lookback_days); ?>" class="small-text">
                            <p class="description"><?php esc_html_e('Include bookings from the last N days in the digest.', 'fp-esperienze'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="fp_esperienze_alerts_send_hour"><?php esc_html_e('Send time (hour)', 'fp-esperienze'); ?></label>
                        </th>
                        <td>
                            <input type="number" name="fp_esperienze_alerts_send_hour" id="fp_esperienze_alerts_send_hour" min="0" max="23" value="<?php echo esc_attr($send_hour); ?>" class="small-text">
                            <p class="description"><?php esc_html_e('Uses the site timezone. The digest runs daily at the selected hour.', 'fp-esperienze'); ?></p>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" name="fp_esperienze_alerts_save" class="button button-primary"><?php esc_html_e('Save changes', 'fp-esperienze'); ?></button>
                    <button type="submit" name="fp_esperienze_alerts_send_now" class="button"><?php esc_html_e('Send digest now', 'fp-esperienze'); ?></button>
                </p>
            </form>

            <?php if (!empty($last_status) && is_array($last_status)) : ?>
                <h2><?php esc_html_e('Last dispatch', 'fp-esperienze'); ?></h2>
                <p>
                    <strong><?php echo esc_html($last_status['timestamp'] ?? ''); ?></strong><br>
                    <?php echo esc_html($last_status['message'] ?? ''); ?>
                </p>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Schedule or unschedule the digest cron event.
     *
     * @param bool $force_reschedule Force rescheduling even if already planned.
     */
    public function maybeScheduleDigest(bool $force_reschedule = false): void {
        $enabled = $this->isDigestEnabled();
        $scheduled = wp_next_scheduled(self::CRON_HOOK);

        if ($enabled) {
            if ($scheduled && $force_reschedule) {
                wp_unschedule_event($scheduled, self::CRON_HOOK);
                $scheduled = false;
            }

            if (!$scheduled) {
                wp_schedule_event($this->calculateNextRunTimestamp(), 'daily', self::CRON_HOOK);
            }
        } elseif ($scheduled) {
            wp_unschedule_event($scheduled, self::CRON_HOOK);
        }
    }

    /**
     * Handle scheduled digest execution.
     */
    public function handleScheduledDigest(): void {
        $lookback = (int) get_option(self::OPTION_LOOKBACK_DAYS, 1);
        $result = self::dispatchDigest('all', $lookback);
        $this->storeLastStatus($result);

        if ($result['status'] !== 'success') {
            error_log('[FP Esperienze] Failed to dispatch daily digest: ' . $result['message']); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        }
    }

    /**
     * Dispatch the digest to the configured channels.
     *
     * @param string $channel      Channel to use: email, slack, or all.
     * @param int    $lookbackDays Number of days to include in the report.
     *
     * @return array{status:string,message:string}
     */
    public static function dispatchDigest(string $channel = 'all', int $lookbackDays = 1): array {
        $lookbackDays = max(1, $lookbackDays);
        $report = OnboardingHelper::getDailyReportData($lookbackDays);
        $overall = $report['overall'];
        $currency = get_option('woocommerce_currency', 'EUR');
        $formattedRevenue = self::formatCurrency($overall['revenue'], $currency);

        $summaryLine = sprintf(
            /* translators: 1: total bookings, 2: participants, 3: formatted revenue */
            __('Total bookings: %1$d (%2$d participants) – Revenue: %3$s', 'fp-esperienze'),
            (int) $overall['total_bookings'],
            (int) $overall['participants'],
            $formattedRevenue
        );

        $lines = [
            sprintf(
                /* translators: %s: formatted date range */
                __('FP Esperienze digest for %s', 'fp-esperienze'),
                self::formatRange($report['range_start'], $report['range_end'])
            ),
            $summaryLine,
            ''
        ];

        foreach ($report['by_day'] as $day => $data) {
            $dayRevenue = self::formatCurrency($data['revenue'] ?? 0, $currency);
            $lines[] = sprintf(
                '%s · %d %s · %s',
                wp_date(get_option('date_format', 'Y-m-d'), strtotime($day)),
                (int) ($data['bookings'] ?? 0),
                _n('booking', 'bookings', (int) ($data['bookings'] ?? 0), 'fp-esperienze'),
                $dayRevenue
            );
        }

        $minBookings = (int) get_option(self::OPTION_MIN_BOOKINGS, 0);
        if ($minBookings > 0 && (int) $overall['total_bookings'] < $minBookings) {
            $lines[] = '';
            $lines[] = sprintf(
                /* translators: %d: minimum bookings threshold */
                __('⚠️ Alert: total bookings below the configured threshold of %d.', 'fp-esperienze'),
                $minBookings
            );
        }

        $body = implode("\n", $lines);
        $sentChannels = [];
        $channel = strtolower($channel);

        if (in_array($channel, ['all', 'email'], true) && self::isEmailEnabled()) {
            $sent = self::sendEmailDigest($body);
            if ($sent instanceof WP_Error) {
                return [
                    'status' => 'error',
                    'message' => $sent->get_error_message(),
                ];
            }

            if ($sent) {
                $sentChannels[] = 'email';
            }
        }

        if (in_array($channel, ['all', 'slack'], true) && self::hasSlackWebhook()) {
            $sent = self::sendSlackDigest($body, $summaryLine);
            if ($sent instanceof WP_Error) {
                return [
                    'status' => 'error',
                    'message' => $sent->get_error_message(),
                ];
            }

            if ($sent) {
                $sentChannels[] = 'slack';
            }
        }

        if ($sentChannels === []) {
            return [
                'status' => 'warning',
                'message' => __('No delivery channels configured. Update the operational alert settings.', 'fp-esperienze'),
            ];
        }

        return [
            'status' => 'success',
            'message' => sprintf(
                /* translators: %s: list of channels */
                __('Digest dispatched via: %s', 'fp-esperienze'),
                implode(', ', $sentChannels)
            ),
        ];
    }

    /**
     * Determine if any delivery channel is enabled.
     */
    private function isDigestEnabled(): bool {
        return self::isEmailEnabled() || self::hasSlackWebhook();
    }

    /**
     * Store last dispatch status for display in the UI.
     *
     * @param array<string,string> $result Result payload.
     */
    private function storeLastStatus(array $result): void {
        $timezone = wp_timezone();
        $now = new DateTimeImmutable('now', $timezone);

        update_option(
            self::OPTION_LAST_STATUS,
            [
                'timestamp' => $now->format('Y-m-d H:i'),
                'message' => $result['message'] ?? '',
                'status' => $result['status'] ?? '',
            ]
        );
    }

    /**
     * Format a revenue amount respecting WooCommerce formatting when available.
     */
    private static function formatCurrency(float $amount, string $currency): string {
        if (function_exists('wc_price')) {
            return trim(wp_strip_all_tags(wc_price($amount, ['currency' => $currency])));
        }

        return sprintf('%s %.2f', $currency, $amount);
    }

    /**
     * Format date range for display.
     */
    private static function formatRange(string $start, string $end): string {
        $format = get_option('date_format', 'Y-m-d');
        $startDate = wp_date($format, strtotime($start));
        $endDate = wp_date($format, strtotime($end));

        if ($startDate === $endDate) {
            return $startDate;
        }

        return $startDate . ' → ' . $endDate;
    }

    /**
     * Send digest email.
     *
     * @param string $body Email body.
     *
     * @return bool|WP_Error
     */
    private static function sendEmailDigest(string $body) {
        $recipients = self::getEmailRecipients();
        if ($recipients === []) {
            return new WP_Error('fp_esperienze_alerts_email', __('No email recipients configured.', 'fp-esperienze'));
        }

        $subject = __('FP Esperienze · Daily booking digest', 'fp-esperienze');
        $headers = ['Content-Type: text/plain; charset=UTF-8'];

        return wp_mail($recipients, $subject, $body, $headers);
    }

    /**
     * Send digest to Slack webhook.
     *
     * @param string $body        Full digest body.
     * @param string $summaryLine Summary used for the Slack message.
     *
     * @return bool|WP_Error
     */
    private static function sendSlackDigest(string $body, string $summaryLine) {
        $webhook = get_option(self::OPTION_SLACK_WEBHOOK, '');
        if ($webhook === '') {
            return new WP_Error('fp_esperienze_alerts_slack', __('Slack webhook URL is missing.', 'fp-esperienze'));
        }

        $payload = [
            'text' => $summaryLine,
            'blocks' => [
                [
                    'type' => 'section',
                    'text' => [
                        'type' => 'mrkdwn',
                        'text' => "*" . __('FP Esperienze · Daily booking digest', 'fp-esperienze') . "*\n" . $body,
                    ],
                ],
            ],
        ];

        $response = wp_remote_post(
            $webhook,
            [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'body' => wp_json_encode($payload),
                'timeout' => 10,
            ]
        );

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code < 200 || $code >= 300) {
            return new WP_Error('fp_esperienze_alerts_slack_http', __('Slack webhook returned a non-success status code.', 'fp-esperienze'));
        }

        return true;
    }

    /**
     * Check if email delivery is enabled.
     */
    private static function isEmailEnabled(): bool {
        return (bool) get_option(self::OPTION_EMAIL_ENABLED, false);
    }

    /**
     * Check if a Slack webhook is configured.
     */
    private static function hasSlackWebhook(): bool {
        $webhook = get_option(self::OPTION_SLACK_WEBHOOK, '');

        return is_string($webhook) && $webhook !== '';
    }

    /**
     * Retrieve array of sanitized email recipients.
     *
     * @return array<int,string>
     */
    private static function getEmailRecipients(): array {
        $raw = get_option(self::OPTION_EMAIL_RECIPIENTS, '');
        if (!is_string($raw) || $raw === '') {
            return [];
        }

        $pieces = preg_split('/[\n,]+/', $raw);
        if (!is_array($pieces)) {
            return [];
        }

        $emails = [];
        foreach ($pieces as $piece) {
            $piece = trim($piece);
            if ($piece === '' || !is_email($piece)) {
                continue;
            }
            $emails[] = $piece;
        }

        return array_unique($emails);
    }

    /**
     * Return the list of delivery channels currently enabled.
     *
     * @return array<int,string>
     */
    public static function getEnabledChannels(): array {
        $channels = [];

        if (self::isEmailEnabled()) {
            $channels[] = 'email';
        }

        if (self::hasSlackWebhook()) {
            $channels[] = 'slack';
        }

        return $channels;
    }

    /**
     * Retrieve the last digest dispatch status stored in the database.
     *
     * @return array<string,string>
     */
    public static function getLastStatus(): array {
        $status = get_option(self::OPTION_LAST_STATUS, []);

        return is_array($status) ? $status : [];
    }

    /**
     * Determine when the next digest run is scheduled.
     */
    public static function getNextDigestTimestamp(): ?int {
        $timestamp = wp_next_scheduled(self::CRON_HOOK);

        if ($timestamp === false) {
            return null;
        }

        return (int) $timestamp;
    }

    /**
     * Calculate timestamp for the next digest run using site timezone.
     */
    private function calculateNextRunTimestamp(): int {
        $hour = (int) get_option(self::OPTION_SEND_HOUR, 7);
        $timezone = wp_timezone();
        $now = new DateTimeImmutable('now', $timezone);
        $next = $now->setTime($hour, 0, 0);

        if ($next <= $now) {
            $next = $next->modify('+1 day');
        }

        return $next->getTimestamp();
    }
}
