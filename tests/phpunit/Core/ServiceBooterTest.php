<?php
declare(strict_types=1);

namespace FP\Esperienze\Tests\Core;

use FP\Esperienze\Core\ServiceBooter;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ServiceBooterTest extends TestCase
{
    protected function setUp(): void
    {
        DummyService::$constructed = 0;
        ServiceBooterFailure::$attempts = 0;
    }

    public function testBootInstantiatesConcreteClass(): void
    {
        $booter = new ServiceBooter();
        $errors = $booter->boot([
            DummyService::class,
        ]);

        $this->assertSame([], $errors, 'No errors should be collected when bootstrapping succeeds.');
        $this->assertSame(1, DummyService::$constructed, 'Dummy service should be instantiated exactly once.');
    }

    public function testOptionalMissingClassIsSilentlySkipped(): void
    {
        $booter = new ServiceBooter();
        $errors = $booter->boot([
            [
                'class' => '\\Nonexistent\\Service',
                'optional' => true,
            ],
        ]);

        $this->assertSame([], $errors);
    }

    public function testCallableFactoryIsExecuted(): void
    {
        $flag = false;
        $booter = new ServiceBooter();
        $errors = $booter->boot([
            [
                'factory' => static function () use (&$flag): void {
                    $flag = true;
                },
            ],
        ]);

        $this->assertTrue($flag, 'Factory callback should be executed.');
        $this->assertSame([], $errors);
    }

    public function testExceptionsAreWrappedWithRuntimeInformation(): void
    {
        $booter = new ServiceBooter();
        $errors = $booter->boot([
            ServiceBooterFailure::class,
        ]);

        $this->assertCount(1, $errors);
        $this->assertInstanceOf(RuntimeException::class, $errors[0]);
        $this->assertSame(1, ServiceBooterFailure::$attempts);
        $this->assertInstanceOf(\Exception::class, $errors[0]->getPrevious());
        $this->assertStringContainsString('Failed bootstrapping', $errors[0]->getMessage());
    }

    public function testInvalidDefinitionIsReportedAsError(): void
    {
        $booter = new ServiceBooter();
        $errors = $booter->boot([
            123,
        ]);

        $this->assertCount(1, $errors);
        $this->assertInstanceOf(InvalidArgumentException::class, $errors[0]);
    }
}

final class DummyService
{
    public static int $constructed = 0;

    public function __construct()
    {
        self::$constructed++;
    }
}

final class ServiceBooterFailure
{
    public static int $attempts = 0;

    public function __construct()
    {
        self::$attempts++;
        throw new \Exception('boom');
    }
}
