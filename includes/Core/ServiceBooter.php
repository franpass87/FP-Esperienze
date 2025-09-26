<?php
/**
 * Generic service bootstrapper for orchestrating plugin modules.
 *
 * @package FP\Esperienze\Core
 */

namespace FP\Esperienze\Core;

use InvalidArgumentException;
use ReflectionClass;
use RuntimeException;
use Throwable;

use function class_exists;
use function is_array;
use function is_callable;
use function is_string;

/**
 * Provides a small utility to bootstrap plugin services in a consistent way.
 */
class ServiceBooter {
    /**
     * Bootstraps each definition and returns the collected errors.
     *
     * @param array<int, mixed> $services Service definitions.
     *
     * @return array<int, Throwable>
     */
    public function boot(array $services): array {
        $errors = [];

        foreach ($services as $service) {
            try {
                $this->bootService($service);
            } catch (Throwable $exception) {
                $errors[] = $exception;
            }
        }

        return $errors;
    }

    /**
     * Bootstraps a single service definition.
     *
     * @param mixed $service Service definition.
     *
     * @return void
     */
    private function bootService($service): void {
        $definition = $this->normaliseDefinition($service);

        if (isset($definition['condition']) && is_callable($definition['condition'])) {
            if (!call_user_func($definition['condition'])) {
                return;
            }
        }

        $label    = $definition['label'] ?? $this->describeDefinition($definition);
        $optional = (bool) ($definition['optional'] ?? false);

        try {
            if (isset($definition['factory'])) {
                $this->callFactory($definition['factory'], $definition['args'] ?? []);
                return;
            }

            if (isset($definition['class'])) {
                $this->bootClass(
                    $definition['class'],
                    $definition['method'] ?? null,
                    $definition['args'] ?? [],
                    $optional
                );
                return;
            }
        } catch (Throwable $exception) {
            $message = sprintf('Failed bootstrapping %s: %s', $label, $exception->getMessage());
            throw new RuntimeException($message, 0, $exception);
        }

        throw new InvalidArgumentException(sprintf('Invalid service definition for %s', $label));
    }

    /**
     * Normalises the provided service definition.
     *
     * @param mixed $service Raw definition.
     *
     * @return array<string, mixed>
     */
    private function normaliseDefinition($service): array {
        if (is_string($service)) {
            return [
                'class' => $service,
            ];
        }

        if (is_callable($service) && !is_array($service)) {
            return [
                'factory' => $service,
            ];
        }

        if (is_array($service)) {
            if (isset($service['factory']) && !isset($service['callable'])) {
                return $service;
            }

            if (isset($service['callable'])) {
                $service['factory'] = $service['callable'];
                unset($service['callable']);
                return $service;
            }

            if (isset($service['class'])) {
                return $service;
            }
        }

        throw new InvalidArgumentException('Unsupported service definition.');
    }

    /**
     * Bootstraps a class definition.
     *
     * @param class-string $class    Class name.
     * @param string|null  $method   Optional factory/static method.
     * @param array<int, mixed> $args Arguments passed to the constructor or method.
     * @param bool         $optional Whether to skip if the class/method is missing.
     *
     * @return void
     */
    private function bootClass(string $class, ?string $method, array $args, bool $optional): void {
        if (!class_exists($class)) {
            if ($optional) {
                return;
            }

            throw new RuntimeException(sprintf('Class %s not found', $class));
        }

        if ($method !== null) {
            if (!is_callable([$class, $method])) {
                if ($optional) {
                    return;
                }

                throw new RuntimeException(sprintf('Method %s::%s is not callable', $class, $method));
            }

            call_user_func_array([$class, $method], $args);
            return;
        }

        if (method_exists($class, 'getInstance') && is_callable([$class, 'getInstance']) && empty($args)) {
            call_user_func([$class, 'getInstance']);
            return;
        }

        $reflection = new ReflectionClass($class);
        $reflection->newInstanceArgs($args);
    }

    /**
     * Executes a factory callable.
     *
     * @param callable $factory Callable factory.
     * @param array<int, mixed> $args Factory arguments.
     *
     * @return void
     */
    private function callFactory(callable $factory, array $args): void {
        call_user_func_array($factory, $args);
    }

    /**
     * Builds a human readable description for logging.
     *
     * @param array<string, mixed> $definition Normalised definition.
     *
     * @return string
     */
    private function describeDefinition(array $definition): string {
        if (isset($definition['class'])) {
            if (isset($definition['method'])) {
                return sprintf('%s::%s', $definition['class'], $definition['method']);
            }

            return $definition['class'];
        }

        if (isset($definition['factory'])) {
            return 'callable';
        }

        return 'service';
    }
}
