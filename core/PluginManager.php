<?php

declare(strict_types=1);

namespace Core;

use Core\Plugin\CircularPluginDependencyException;
use Core\Plugin\PluginDescriptor;
use Core\Plugin\PluginException;
use Core\Plugin\PluginNotFoundException;
use Throwable;

/**
 * Doc lap hoan toan voi ModuleManager (CMS-010) - chap nhan trung lap logic (discover/
 * topological sort/circular check) de khong dung vao ModuleManager da on dinh, theo dung
 * quyet dinh da chot cho CMS-012.
 *
 * Khac ModuleManager o 2 diem co chu dich:
 * - discover() MEMOIZE trong instance (chi glob + parse 1 lan cho ca vong doi 1 PluginManager).
 * - boot() CACH LY loi tung plugin: 1 plugin co Hooks.php loi khong duoc lam crash toan bo
 *   qua trinh boot, chi ghi nhan vao failures() va tiep tuc plugin ke tiep.
 */
final class PluginManager
{
    /** @var array<string, PluginDescriptor>|null */
    private ?array $discovered = null;

    /** @var array<string, Throwable> */
    private array $failures = [];

    public function __construct(private readonly string $pluginsPath)
    {
    }

    /** @return array<string, PluginDescriptor> */
    public function discover(): array
    {
        if ($this->discovered !== null) {
            return $this->discovered;
        }

        $descriptors = [];
        $pattern = \rtrim($this->pluginsPath, '/\\') . DIRECTORY_SEPARATOR . '*' . DIRECTORY_SEPARATOR . 'plugin.json';

        foreach (\glob($pattern) ?: [] as $file) {
            $descriptor = $this->parseManifest($file);

            if (isset($descriptors[$descriptor->key])) {
                throw PluginException::duplicateKey(
                    $descriptor->key,
                    $descriptors[$descriptor->key]->path,
                    $descriptor->path
                );
            }

            $descriptors[$descriptor->key] = $descriptor;
        }

        return $this->discovered = $descriptors;
    }

    /**
     * @param list<string> $enabledKeys
     * @return list<PluginDescriptor>
     */
    public function resolveLoadOrder(array $enabledKeys): array
    {
        $descriptors = $this->discover();

        foreach ($enabledKeys as $key) {
            if (!isset($descriptors[$key])) {
                throw PluginNotFoundException::forKey($key);
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
     * Nap Hooks.php cua tung plugin da bat, theo dung thu tu dependency. Luon reset
     * failures() ve rong khi bat dau - moi lan goi la mot lan thuc thi doc lap.
     *
     * Plugin co Hooks.php nem loi duoc cach ly: loi duoc ghi vao failures(), KHONG
     * duoc re-throw, va PluginManager tiep tuc nap plugin ke tiep.
     *
     * @param list<string> $enabledKeys
     * @return list<string> Danh sach key cua cac plugin nap THANH CONG
     */
    public function boot(Hook $hook, array $enabledKeys): array
    {
        $this->failures = [];
        $loaded = [];

        foreach ($this->resolveLoadOrder($enabledKeys) as $plugin) {
            $hooksFile = \rtrim($plugin->path, '/\\') . DIRECTORY_SEPARATOR . 'Hooks.php';

            if (!\is_file($hooksFile)) {
                $loaded[] = $plugin->key;

                continue;
            }

            try {
                (function () use ($hooksFile, $hook): void {
                    require $hooksFile;
                })();

                $loaded[] = $plugin->key;
            } catch (Throwable $exception) {
                $this->failures[$plugin->key] = $exception;
            }
        }

        return $loaded;
    }

    /** @return array<string, Throwable> */
    public function getFailures(): array
    {
        return $this->failures;
    }

    /**
     * @param list<string> $enabledKeys
     * @param array<string, PluginDescriptor> $descriptors
     * @param list<PluginDescriptor> $resolved
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
            throw new CircularPluginDependencyException([...\array_keys($resolving), $key]);
        }

        $resolving[$key] = true;
        $descriptor = $descriptors[$key];

        foreach ($descriptor->dependencies as $dependencyKey) {
            if (!\in_array($dependencyKey, $enabledKeys, true)) {
                throw PluginNotFoundException::forDependency($key, $dependencyKey);
            }

            $this->visit($dependencyKey, $enabledKeys, $descriptors, $resolved, $resolvedKeys, $resolving);
        }

        unset($resolving[$key]);
        $resolvedKeys[$key] = true;
        $resolved[] = $descriptor;
    }

    private function parseManifest(string $file): PluginDescriptor
    {
        $raw = @\file_get_contents($file);

        if ($raw === false) {
            throw PluginException::cannotRead($file);
        }

        $data = \json_decode($raw, true);

        if (!\is_array($data) || !isset($data['key'], $data['name'], $data['version'])) {
            throw PluginException::invalidManifest($file);
        }

        $rawDependencies = \is_array($data['dependencies'] ?? null) ? $data['dependencies'] : [];

        return new PluginDescriptor(
            key: (string) $data['key'],
            name: (string) $data['name'],
            version: (string) $data['version'],
            author: (string) ($data['author'] ?? ''),
            description: (string) ($data['description'] ?? ''),
            dependencies: \array_map('strval', $rawDependencies),
            path: \dirname($file),
        );
    }
}
