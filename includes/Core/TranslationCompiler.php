<?php
/**
 * Runtime compilation of translation files.
 *
 * @package FP\Esperienze\Core
 */

namespace FP\Esperienze\Core;

use PO;
use MO;

defined('ABSPATH') || exit;

/**
 * Ensures `.mo` binaries exist for the bundled `.po` files.
 */
class TranslationCompiler
{
    /**
     * Compile outdated or missing `.mo` files for the plugin translations.
     */
    public static function ensureMoFiles(): void
    {
        if (!defined('FP_ESPERIENZE_PLUGIN_DIR')) {
            return;
        }

        $sourceDir = self::normalizePath(FP_ESPERIENZE_PLUGIN_DIR . 'languages');
        if (!is_dir($sourceDir)) {
            return;
        }

        $poFiles = glob($sourceDir . '/*.po');
        if (!$poFiles) {
            return;
        }

        if (!self::loadPomoDependencies()) {
            return;
        }

        $targetDir = self::determineTargetDirectory($sourceDir);
        if ($targetDir === null) {
            error_log('FP Esperienze: Unable to locate a writable directory for compiled translations.');
            return;
        }

        foreach ($poFiles as $poFile) {
            $basename = basename($poFile, '.po');
            $locale = self::extractLocaleFromBasename($basename);
            if ($locale === null) {
                continue;
            }

            $moFile = $targetDir . 'fp-esperienze-' . $locale . '.mo';
            if (!self::shouldCompile($poFile, $moFile)) {
                continue;
            }

            if (!self::compile($poFile, $moFile)) {
                error_log('FP Esperienze: Failed to compile translation file ' . basename($poFile));
            }
        }
    }

    /**
     * Ensure the WordPress POMO classes are available.
     */
    private static function loadPomoDependencies(): bool
    {
        if (!class_exists(PO::class)) {
            $poPath = ABSPATH . WPINC . '/pomo/po.php';
            if (!file_exists($poPath)) {
                return false;
            }

            require_once $poPath;
        }

        if (!class_exists(MO::class)) {
            $moPath = ABSPATH . WPINC . '/pomo/mo.php';
            if (!file_exists($moPath)) {
                return false;
            }

            require_once $moPath;
        }

        return class_exists(PO::class) && class_exists(MO::class);
    }

    /**
     * Determine where compiled translations should be written.
     */
    private static function determineTargetDirectory(string $fallbackDir): ?string
    {
        $candidates = [];

        if (defined('WP_LANG_DIR')) {
            $candidates[] = self::normalizePath(WP_LANG_DIR . '/plugins');
        }

        $candidates[] = $fallbackDir;

        foreach ($candidates as $directory) {
            if (self::ensureDirectory($directory)) {
                return $directory;
            }
        }

        return null;
    }

    /**
     * Create the directory if needed and confirm it is writable.
     */
    private static function ensureDirectory(string $directory): bool
    {
        if (!is_dir($directory)) {
            if (function_exists('wp_mkdir_p')) {
                if (!wp_mkdir_p($directory)) {
                    return false;
                }
            } elseif (!mkdir($directory, 0755, true) && !is_dir($directory)) {
                return false;
            }
        }

        if (function_exists('wp_is_writable')) {
            return wp_is_writable($directory);
        }

        return is_writable($directory);
    }

    /**
     * Decide whether a `.po` file requires recompilation.
     */
    private static function shouldCompile(string $poFile, string $moFile): bool
    {
        if (!file_exists($moFile)) {
            return true;
        }

        return filemtime($poFile) > filemtime($moFile);
    }

    /**
     * Compile the provided `.po` file into the target `.mo` file.
     */
    private static function compile(string $poFile, string $moFile): bool
    {
        $po = new PO();
        if (!$po->import_from_file($poFile)) {
            return false;
        }

        $mo = new MO();
        foreach ($po->entries as $entry) {
            $mo->add_entry($entry);
        }

        foreach ($po->headers as $header => $value) {
            $mo->set_header($header, $value);
        }

        $tempFile = self::createTemporaryFile($moFile);
        if (!$tempFile) {
            return false;
        }

        $exported = $mo->export_to_file($tempFile);
        if (!$exported) {
            self::deleteIfExists($tempFile);
            return false;
        }

        if ($tempFile === $moFile) {
            return true;
        }

        if (!@rename($tempFile, $moFile)) {
            self::deleteIfExists($tempFile);
            return false;
        }

        return true;
    }

    /**
     * Attempt to create a temporary file in the destination directory.
     */
    private static function createTemporaryFile(string $target): ?string
    {
        $directory = dirname($target);

        if (function_exists('wp_tempnam')) {
            $temp = wp_tempnam(basename($target));
            if ($temp !== false) {
                return $temp;
            }
        }

        $temp = tempnam($directory, 'mo');
        if ($temp !== false) {
            return $temp;
        }

        return is_writable($directory) ? $target : null;
    }

    /**
     * Remove a file if it exists.
     */
    private static function deleteIfExists(string $path): void
    {
        if (file_exists($path)) {
            @unlink($path);
        }
    }

    /**
     * Extract the locale portion from a translation filename.
     */
    private static function extractLocaleFromBasename(string $basename): ?string
    {
        $prefix = 'fp-esperienze-';
        if (strpos($basename, $prefix) !== 0) {
            return null;
        }

        $locale = substr($basename, strlen($prefix));

        return $locale !== '' ? $locale : null;
    }

    /**
     * Ensure paths always end with a directory separator.
     */
    private static function normalizePath(string $path): string
    {
        return rtrim($path, '/\\') . '/';
    }
}
