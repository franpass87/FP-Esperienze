<?php
/**
 * Simple build script that concatenates admin CSS sources into the
 * distributable `assets/css/admin.css` file. It preserves a banner
 * header to indicate the file is generated and ensures deterministic
 * ordering for predictable diffs.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$sourceDir = $root . '/assets/src/admin';
$destination = $root . '/assets/css/admin.css';

$orderedSources = [
    'tokens.css',
    'base.css',
    'components.css',
];

$buffer = [];

foreach ($orderedSources as $relative) {
    $path = $sourceDir . '/' . $relative;

    if (!file_exists($path)) {
        fwrite(STDERR, sprintf("[build-admin-styles] Missing source file: %s\n", $relative));
        exit(1);
    }

    $contents = trim((string) file_get_contents($path));
    $banner = sprintf("/* Source: assets/src/admin/%s */\n", $relative);
    $buffer[] = $banner . $contents;
}

$generated = "/* FP Esperienze Admin CSS – generated via scripts/build-admin-styles.php */\n\n" . implode("\n\n", $buffer) . "\n";

$result = file_put_contents($destination, $generated);

if ($result === false) {
    fwrite(STDERR, "[build-admin-styles] Failed to write admin.css\n");
    exit(1);
}

echo sprintf("[build-admin-styles] Wrote %s (%d bytes)\n", $destination, $result);
