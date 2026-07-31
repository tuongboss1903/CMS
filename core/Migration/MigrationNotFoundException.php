<?php

declare(strict_types=1);

namespace Core\Migration;

final class MigrationNotFoundException extends MigrationException
{
    public static function forRollback(string $migration): self
    {
        return new self(\sprintf(
            'Khong the rollback "%s" - file migration khong con ton tai tren disk.',
            $migration
        ));
    }
}
