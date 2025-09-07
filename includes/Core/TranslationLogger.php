<?php
/**
 * Translation Logger.
 *
 * @package FP\Esperienze\Core
 */

namespace FP\Esperienze\Core;

defined('ABSPATH') || exit;

/**
 * Logs translation errors to a file in uploads directory.
 */
class TranslationLogger {
    /**
     * Log a message to the translation log file.
     *
     * @param string $message Message to log.
     */
    public static function log(string $message): void {
        if ('' === $message) {
            return;
        }

        $uploads = wp_upload_dir();
        if (empty($uploads['basedir'])) {
            return;
        }

        $file = trailingslashit($uploads['basedir']) . 'fp-esperienze-translation.log';
        $entry = sprintf('[%s] %s%s', current_time('mysql'), $message, PHP_EOL);

        // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_file_put_contents
        file_put_contents($file, $entry, FILE_APPEND | LOCK_EX);
    }
}
