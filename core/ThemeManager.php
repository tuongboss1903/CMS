<?php

declare(strict_types=1);

namespace Core;

use Core\Theme\ThemeDescriptor;
use Core\Theme\ThemeException;

/**
 * Discover theme qua "theme.json" duoi $themesPath - CHI discovery/metadata, khong render (thuoc
 * View), khong biet theme nao "active" (business state, thuoc ThemeService/TenantManager tuong
 * lai). Khong memoize - filesystem la source of truth, theme co the doi runtime, moi discover()
 * la 1 lan doc doc lap (khac PluginManager, giong ModuleManager).
 */
final class ThemeManager
{
    public function __construct(private readonly string $themesPath)
    {
    }

    /** @return array<string, ThemeDescriptor> */
    public function discover(): array
    {
        $descriptors = [];
        $pattern = \rtrim($this->themesPath, '/\\') . DIRECTORY_SEPARATOR . '*' . DIRECTORY_SEPARATOR . 'theme.json';

        foreach (\glob($pattern) ?: [] as $file) {
            $descriptor = $this->parseManifest($file);
            $descriptors[$descriptor->key] = $descriptor;
        }

        return $descriptors;
    }

    public function find(string $key): ?ThemeDescriptor
    {
        return $this->discover()[$key] ?? null;
    }

    private function parseManifest(string $file): ThemeDescriptor
    {
        $raw = @\file_get_contents($file);

        if ($raw === false) {
            throw ThemeException::cannotRead($file);
        }

        $data = \json_decode($raw, true);

        if (!\is_array($data) || !isset($data['key'], $data['name'], $data['version'])) {
            throw ThemeException::invalidManifest($file);
        }

        return new ThemeDescriptor(
            key: (string) $data['key'],
            name: (string) $data['name'],
            version: (string) $data['version'],
            screenshot: (string) ($data['screenshot'] ?? ''),
            path: \dirname($file),
        );
    }
}
