<?php

declare(strict_types=1);

namespace Core;

use Psr\Container\NotFoundExceptionInterface;

final class BindingNotFoundException extends ContainerException implements NotFoundExceptionInterface
{
    public static function forId(string $id): self
    {
        return new self(sprintf('Khong tim thay binding hoac class "%s" trong Container.', $id));
    }
}
