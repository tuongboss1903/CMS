<?php

declare(strict_types=1);

namespace Core\Validation;

use RuntimeException;

/** Nem khi $rules tham chieu 1 rule khong ton tai trong registry - loi cau hinh, khong phai loi input. */
final class ValidationException extends RuntimeException
{
    public static function unknownRule(string $ruleName): self
    {
        return new self(\sprintf('Rule "%s" chua duoc dang ky trong Validator.', $ruleName));
    }
}
