<?php
/**
 * Settings update result DTO.
 *
 * @package FP\Esperienze\Admin\Settings\Services
 */

namespace FP\Esperienze\Admin\Settings\Services;

final class SettingsUpdateResult
{
    /**
     * @param bool              $success  Whether the update was successful.
     * @param array<int,string> $messages Informational or success messages.
     * @param array<int,string> $errors   Error messages to surface.
     */
    public function __construct(
        private bool $success,
        private array $messages = [],
        private array $errors = []
    ) {
    }

    public static function success(array $messages = []): self
    {
        return new self(true, $messages, []);
    }

    public static function failure(array $errors): self
    {
        return new self(false, [], $errors);
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * @return array<int,string>
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    /**
     * @return array<int,string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    public function merge(self $other): self
    {
        return new self(
            $this->success && $other->success,
            array_merge($this->messages, $other->messages),
            array_merge($this->errors, $other->errors)
        );
    }
}
