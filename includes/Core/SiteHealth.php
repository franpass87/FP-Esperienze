<?php
/**
 * Site Health integration for FP Esperienze.
 *
 * @package FP\Esperienze\Core
 */

namespace FP\Esperienze\Core;

use function esc_html;
use function esc_html__;
use function esc_url;

defined('ABSPATH') || exit;

/**
 * Adds custom Site Health tests to highlight plugin requirements.
 */
class SiteHealth {

    /**
     * Register Site Health integration.
     */
    public static function init(): void {
        if (!is_admin()) {
            return;
        }

        add_filter('site_status_tests', array(__CLASS__, 'registerTests'));
    }

    /**
     * Register the plugin specific tests.
     *
     * @param array $tests Existing tests.
     *
     * @return array Modified tests list.
     */
    public static function registerTests(array $tests): array {
        $tests['direct']['fp_esperienze_dependencies'] = array(
            'label' => esc_html__('FP Esperienze dependencies', 'fp-esperienze'),
            'test'  => array(__CLASS__, 'runDependencyTest'),
        );

        $tests['direct']['fp_esperienze_filesystem'] = array(
            'label' => esc_html__('FP Esperienze filesystem access', 'fp-esperienze'),
            'test'  => array(__CLASS__, 'runFilesystemTest'),
        );

        $tests['direct']['fp_esperienze_scheduled_events'] = array(
            'label' => esc_html__('FP Esperienze scheduled events', 'fp-esperienze'),
            'test'  => array(__CLASS__, 'runCronTest'),
        );

        return $tests;
    }

    /**
     * Check mandatory dependencies.
     *
     * @return array
     */
    public static function runDependencyTest(): array {
        $result = self::getBaseResult('fp_esperienze_dependencies');

        $issues = array();

        if (!class_exists('WooCommerce')) {
            $issues[] = esc_html__('WooCommerce must be installed and active for FP Esperienze to work.', 'fp-esperienze');
        } elseif (defined('WC_VERSION') && version_compare(WC_VERSION, '8.0', '<')) {
            $issues[] = esc_html__('WooCommerce 8.0 or newer is required.', 'fp-esperienze');
        }

        if (!file_exists(FP_ESPERIENZE_PLUGIN_DIR . 'vendor/autoload.php')) {
            $issues[] = esc_html__('Composer dependencies are missing. Run composer install --no-dev.', 'fp-esperienze');
        }

        if (!empty($issues)) {
            $result['status']      = 'critical';
            $result['description'] = self::buildDescription($issues);
            $result['actions']     = sprintf(
                '<p><a href="%s" class="button">%s</a></p>',
                esc_url(admin_url('plugins.php')),
                esc_html__('Open Plugins screen', 'fp-esperienze')
            );
        }

        return $result;
    }

    /**
     * Validate filesystem prerequisites.
     *
     * @return array
     */
    public static function runFilesystemTest(): array {
        $result = self::getBaseResult('fp_esperienze_filesystem');

        $paths = array(
            WP_CONTENT_DIR . '/fp-private' => esc_html__('The fp-private directory must be writable.', 'fp-esperienze'),
            FP_ESPERIENZE_ICS_DIR          => esc_html__('The ICS directory must be writable.', 'fp-esperienze'),
        );

        $issues = array();

        foreach ($paths as $path => $message) {
            if (!file_exists($path)) {
                // translators: 1: error message, 2: filesystem path.
                $issues[] = sprintf(esc_html__('%1$s (missing path: %2$s)', 'fp-esperienze'), $message, esc_html($path));
                continue;
            }

            if (!wp_is_writable($path)) {
                // translators: 1: error message, 2: filesystem path.
                $issues[] = sprintf(esc_html__('%1$s (not writable: %2$s)', 'fp-esperienze'), $message, esc_html($path));
            }
        }

        if (!empty($issues)) {
            $result['status']      = 'recommended';
            $result['description'] = self::buildDescription($issues);
        }

        return $result;
    }

    /**
     * Ensure scheduled events required by the plugin are present.
     *
     * @return array
     */
    public static function runCronTest(): array {
        $result = self::getBaseResult('fp_esperienze_scheduled_events');

        $events = array(
            TranslationQueue::CRON_HOOK,
            'fp_cleanup_push_tokens',
        );

        $issues = array();

        foreach ($events as $hook) {
            if (!wp_next_scheduled($hook)) {
                $issues[] = sprintf(
                    esc_html__('The %s scheduled event is missing. Visit the FP Esperienze settings page to re-schedule it.', 'fp-esperienze'),
                    esc_html($hook)
                );
            }
        }

        if (!empty($issues)) {
            $result['status']      = 'recommended';
            $result['description'] = self::buildDescription($issues);
        }

        return $result;
    }

    /**
     * Prepare a base result array compliant with Site Health expectations.
     *
     * @param string $test Test identifier.
     *
     * @return array
     */
    private static function getBaseResult(string $test): array {
        return array(
            'label'       => esc_html__('FP Esperienze', 'fp-esperienze'),
            'status'      => 'good',
            'badge'       => array(
                'label' => esc_html__('FP Esperienze', 'fp-esperienze'),
                'color' => 'blue',
            ),
            'description' => self::buildDescription(
                array(
                    esc_html__('All FP Esperienze checks passed.', 'fp-esperienze'),
                )
            ),
            'test'        => $test,
        );
    }

    /**
     * Build HTML description paragraphs from messages.
     *
     * @param array<int, string> $messages List of messages.
     *
     * @return string
     */
    private static function buildDescription(array $messages): string {
        $output = '';

        foreach ($messages as $message) {
            $output .= '<p>' . $message . '</p>';
        }

        return $output;
    }
}
