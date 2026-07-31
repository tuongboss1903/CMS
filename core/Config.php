<?php

declare(strict_types=1);

namespace Core;

/**
 * Nap toan bo file config/*.php va cung cap truy cap qua dot notation.
 * Instance-based (khong static) de tuan thu "No Global Variable" / "Dependency Injection"
 * trong coding-standard.md - duoc khoi tao 1 lan o public/index.php roi truyen (inject)
 * vao cac class core khac (Database, Cache...) qua constructor.
 */
final class Config
{
    /** @var array<string, mixed> */
    private array $items = [];

    public function __construct(private readonly string $configPath)
    {
        $this->loadAll();
    }

    private function loadAll(): void
    {
        $files = glob(rtrim($this->configPath, '/\\') . '/*.php') ?: [];

        foreach ($files as $file) {
            $key = basename($file, '.php');
            $this->items[$key] = require $file;
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->items;

        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    public function has(string $key): bool
    {
        $sentinel = new \stdClass();

        return $this->get($key, $sentinel) !== $sentinel;
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return $this->items;
    }
}
