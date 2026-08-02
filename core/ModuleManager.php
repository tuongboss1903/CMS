<?php

declare(strict_types=1);

namespace Core;

use Core\Module\CircularModuleDependencyException;
use Core\Module\ModuleDescriptor;
use Core\Module\ModuleException;
use Core\Module\ModuleNotFoundException;

/**
 * Discover module qua "module.json" duoi $modulesPath, resolve thu tu load theo dependency
 * (topological sort, phat hien circular - CUNG MO HINH voi Container::resolve() o CMS-003), va
 * load "routes.php" cua tung module da bat vao Router.
 *
 * KHONG tu query Database de biet module nao "bat" cho tenant nao - Phase 2 (bang settings/
 * site_modules) chua ton tai. Nhan $enabledKeys tu ben ngoai (tuong lai: TenantManager/Settings) -
 * giu dung nguyen tac core trung lap da ap dung xuyen suot (Database/View/Cache).
 */
final class ModuleManager
{
    public function __construct(private readonly string $modulesPath)
    {
    }

    /** @return array<string, ModuleDescriptor> */
    public function discover(): array
    {
        $descriptors = [];
        $pattern = \rtrim($this->modulesPath, '/\\') . DIRECTORY_SEPARATOR . '*' . DIRECTORY_SEPARATOR . 'module.json';

        foreach (\glob($pattern) ?: [] as $file) {
            $descriptor = $this->parseManifest($file);
            $descriptors[$descriptor->key] = $descriptor;
        }

        return $descriptors;
    }

    /**
     * Nap "routes.php" cua tung module trong $enabledKeys, theo dung thu tu dependency.
     *
     * @param list<string> $enabledKeys
     * @return list<string> Danh sach key module da nap, theo dung thu tu (phuc vu log/debug)
     */
    public function boot(Router $router, array $enabledKeys): array
    {
        $loaded = [];

        foreach ($this->resolveLoadOrder($enabledKeys) as $module) {
            $routesFile = \rtrim($module->path, '/\\') . DIRECTORY_SEPARATOR . 'routes.php';

            if (\is_file($routesFile)) {
                (function () use ($routesFile, $router): void {
                    require $routesFile;
                })();
            }

            $loaded[] = $module->key;
        }

        return $loaded;
    }

    /**
     * @param list<string> $enabledKeys
     * @return list<ModuleDescriptor>
     */
    public function resolveLoadOrder(array $enabledKeys): array
    {
        $descriptors = $this->discover();

        foreach ($enabledKeys as $key) {
            if (!isset($descriptors[$key])) {
                throw ModuleNotFoundException::forKey($key);
            }
        }

        $resolved = [];
        $resolvedKeys = [];
        $resolving = [];

        foreach ($enabledKeys as $key) {
            $this->visit($key, $enabledKeys, $descriptors, $resolved, $resolvedKeys, $resolving);
        }

        return $resolved;
    }

    /**
     * @param list<string> $enabledKeys
     * @param array<string, ModuleDescriptor> $descriptors
     * @param list<ModuleDescriptor> $resolved
     * @param array<string, true> $resolvedKeys
     * @param array<string, true> $resolving
     */
    private function visit(
        string $key,
        array $enabledKeys,
        array $descriptors,
        array &$resolved,
        array &$resolvedKeys,
        array &$resolving,
    ): void {
        if (isset($resolvedKeys[$key])) {
            return;
        }

        if (isset($resolving[$key])) {
            throw new CircularModuleDependencyException([...\array_keys($resolving), $key]);
        }

        $resolving[$key] = true;
        $descriptor = $descriptors[$key];

        foreach ($descriptor->dependencies as $dependencyKey) {
            if (!\in_array($dependencyKey, $enabledKeys, true)) {
                throw ModuleNotFoundException::forDependency($key, $dependencyKey);
            }

            $this->visit($dependencyKey, $enabledKeys, $descriptors, $resolved, $resolvedKeys, $resolving);
        }

        unset($resolving[$key]);
        $resolvedKeys[$key] = true;
        $resolved[] = $descriptor;
    }

    private function parseManifest(string $file): ModuleDescriptor
    {
        $raw = @\file_get_contents($file);

        if ($raw === false) {
            throw ModuleException::cannotRead($file);
        }

        $data = \json_decode($raw, true);

        if (!\is_array($data) || !isset($data['key'], $data['name'], $data['version'])) {
            throw ModuleException::invalidManifest($file);
        }

        /** @var list<mixed> $rawDependencies */
        $rawDependencies = \is_array($data['dependencies'] ?? null) ? $data['dependencies'] : [];

        return new ModuleDescriptor(
            key: (string) $data['key'],
            name: (string) $data['name'],
            version: (string) $data['version'],
            dependencies: \array_map('strval', $rawDependencies),
            path: \dirname($file),
        );
    }
}
