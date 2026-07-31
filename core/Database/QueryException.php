<?php

declare(strict_types=1);

namespace Core\Database;

use Throwable;

/**
 * getSql()/getBindings() chi danh cho tang log/debug noi bo - khong bao gio dua vao getMessage()
 * de tranh lo du lieu nhay cam neu exception nay vo tinh hien ra response.
 */
final class QueryException extends DatabaseException
{
    /** @param array<int|string, mixed> $bindings */
    public function __construct(
        private readonly string $sql,
        private readonly array $bindings,
        string $message,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function getSql(): string
    {
        return $this->sql;
    }

    /** @return array<int|string, mixed> */
    public function getBindings(): array
    {
        return $this->bindings;
    }
}
