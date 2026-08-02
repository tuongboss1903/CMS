<?php

declare(strict_types=1);

namespace Core\Migration;

use RuntimeException;

class MigrationException extends RuntimeException
{
    public static function invalidFile(string $file): self
    {
        return new self(\sprintf(
            'Migration file khong hop le (phai return [\'up\' => Closure, \'down\' => Closure]): "%s".',
            $file
        ));
    }

    public static function unsupportedDriver(string $driver): self
    {
        return new self(\sprintf('Driver "%s" khong duoc ho tro (chi ho tro "mysql"/"sqlite").', $driver));
    }
}
