<?php

declare(strict_types=1);

namespace Core;

/**
 * Giu state "tenant hien tai" trong pham vi 1 request - khong resolve domain->tenant, khong
 * Database, khong Session, khong Request. Container da la 1-per-request nen state tu nhien
 * khong ro ri giua cac request ma khong can co che don dep nao.
 */
final class TenantManager
{
    private int|string|null $id = null;

    /** @var array<string, mixed> */
    private array $data = [];

    /** @param array<string, mixed> $data */
    public function setCurrent(int|string $tenantId, array $data = []): void
    {
        $this->id = $tenantId;
        $this->data = $data;
    }

    public function check(): bool
    {
        return $this->id !== null;
    }

    public function id(): int|string|null
    {
        return $this->id;
    }

    /** @return array<string, mixed>|null */
    public function current(): ?array
    {
        if (!$this->check()) {
            return null;
        }

        return $this->data;
    }
}
