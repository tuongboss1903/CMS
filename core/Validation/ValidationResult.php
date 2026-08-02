<?php

declare(strict_types=1);

namespace Core\Validation;

final class ValidationResult
{
    /** @param array<string, list<string>> $errors */
    public function __construct(private readonly array $errors)
    {
    }

    public function passes(): bool
    {
        return $this->errors === [];
    }

    public function fails(): bool
    {
        return !$this->passes();
    }

    /** @return array<string, list<string>> */
    public function errors(): array
    {
        return $this->errors;
    }

    public function firstError(string $field): ?string
    {
        return $this->errors[$field][0] ?? null;
    }
}
