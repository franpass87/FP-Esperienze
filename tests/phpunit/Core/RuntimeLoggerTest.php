<?php
declare(strict_types=1);

namespace FP\Esperienze\Tests\Core;

use FP\Esperienze\Core\RuntimeLogger;
use PHPUnit\Framework\TestCase;

final class RuntimeLoggerTest extends TestCase
{
    protected function tearDown(): void
    {
        RuntimeLogger::flush();
        RuntimeLogger::setLogFilePath(null);
    }

    public function testManualLogIsPersistedToFile(): void
    {
        $logPath = sys_get_temp_dir() . '/fp-esperienze-tests/runtime-' . uniqid('', true) . '.log';
        if (file_exists($logPath)) {
            unlink($logPath);
        }

        RuntimeLogger::setLogFilePath($logPath);

        $file = FP_ESPERIENZE_PLUGIN_DIR . 'includes/Core/Demo.php';
        RuntimeLogger::logManual('warning', 'Test entry', $file, 42, ['foo' => 'bar']);
        RuntimeLogger::flush();

        $this->assertFileExists($logPath);
        $contents = file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $this->assertNotFalse($contents);
        $this->assertNotEmpty($contents);

        $payload = json_decode($contents[0], true);
        $this->assertIsArray($payload);
        $this->assertSame('warning', $payload['level']);
        $this->assertSame('Test entry', $payload['message']);
        $this->assertSame('includes/Core/Demo.php', $payload['file']);
        $this->assertSame(42, $payload['line']);
        $this->assertSame(['foo' => 'bar'], $payload['context']);
    }

    public function testRenderFooterOutputsRecentEntries(): void
    {
        RuntimeLogger::setLogFilePath(sys_get_temp_dir() . '/fp-esperienze-tests/runtime-overlay.log');
        RuntimeLogger::logManual('notice', 'Overlay entry', FP_ESPERIENZE_PLUGIN_DIR . 'includes/example.php', 10);

        ob_start();
        RuntimeLogger::renderFooterLog();
        $html = ob_get_clean();

        $this->assertIsString($html);
        $this->assertStringContainsString('FP Esperienze – Runtime alerts', $html);
        $this->assertStringContainsString('Overlay entry', $html);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testInitRegistersFooterHooks(): void
    {
        RuntimeLogger::setLogFilePath(sys_get_temp_dir() . '/fp-esperienze-tests/runtime-init.log');

        global $fp_esperienze_test_actions;
        $fp_esperienze_test_actions = [];

        RuntimeLogger::init();

        $hooks = array_column($fp_esperienze_test_actions, 'hook');
        $this->assertContains('admin_footer', $hooks);
        $this->assertContains('wp_footer', $hooks);
    }
}
