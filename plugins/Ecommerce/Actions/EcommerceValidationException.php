<?php

declare(strict_types=1);

namespace Plugins\Ecommerce\Actions;

use RuntimeException;

/** Du lieu khong hop le hoac vi pham nghiep vu (het hang, gio hang rong, chuyen trang thai sai) - dung chung cho ca 4 Action cua plugin. */
final class EcommerceValidationException extends RuntimeException
{
    /** @param array<string, list<string>> $errors */
    public function __construct(string $message, private readonly array $errors = [])
    {
        parent::__construct($message);
    }

    /** @return array<string, list<string>> */
    public function errors(): array
    {
        return $this->errors;
    }
}
