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
     * @return bool|\WP_Error True on success, WP_Error on failure.
     */
    public static function log(string $message) {
        if ('' === $message) {
            return true;
        }

        if (!get_option('fp_lt_enable_log')) {
            return true;
        }

        $uploads = wp_upload_dir();
        if (empty($uploads['basedir'])) {
            return true;
        }

        $file  = trailingslashit($uploads['basedir']) . 'fp-esperienze-translation.log';
        $entry = sprintf('[%s] %s%s', current_time('mysql'), $message, PHP_EOL);

        global $wp_filesystem;
        require_once ABSPATH . 'wp-admin/includes/file.php';
        if (!WP_Filesystem()) {
            $msg = 'TranslationLogger: WP_Filesystem initialization failed.';
            error_log($msg);
            return new \WP_Error('fp_fs_init_failed', $msg);
        }

        if (!$wp_filesystem->put_contents($file, $entry, FS_CHMOD_FILE)) {
            $msg = 'TranslationLogger: Failed to write log file.';
            error_log($msg);
            return new \WP_Error('fp_log_write_failed', $msg);
        }

        return true;
    }
}
