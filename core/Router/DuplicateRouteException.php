<?php

declare(strict_types=1);

namespace Core\Router;

use RuntimeException;

/** Dang ky trung Method+URI+Domain - phai nem ngay luc dang ky (boot), khong doi toi runtime. */
final class DuplicateRouteException extends RuntimeException
{
    public static function forSignature(string $signature): self
    {
        return new self(\sprintf('Route da duoc dang ky truoc do (trung Method+URI+Domain): %s', $signature));
    }
}
