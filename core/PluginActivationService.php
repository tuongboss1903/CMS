<?php

declare(strict_types=1);

namespace Core;

/**
 * Dong Technical Debt #9 (xem core-architecture.md, ghi nhan tu CMS-012) - lop DUY NHAT doc/ghi
 * bang site_plugins, tra ve danh sach plugin_key da bat CHO DUNG 1 tenant. Application::boot()
 * dung enabledKeysFor() thay vi PluginManager::discover() (tat ca) khi co tenant context.
 *
 * Cache-aware qua Core\Cache co san (khong tu viet cache rieng) - key theo tenant, tu invalidate
 * dung 1 key lien quan khi activate()/deactivate() (khong flush() toan bo Cache).
 *
 * Dat o core/ (khong phai Modules\Ecommerce) vi day la ha tang dung CHUNG cho MOI plugin tuong
 * lai, khong rieng Ecommerce - dung nguyen tac da phan biet Module/Plugin (framework thuan) voi
 * SiteSettingsManager/SettingManager (nghiep vu, dat trong modules/Modules).
 */
final class PluginActivationService
{
    private const CACHE_TTL_SECONDS = 300;

    public function __construct(
        private readonly Cache $cache,
        private readonly Database $database,
    ) {
    }

    public function isActive(int|string $tenantId, string $pluginKey): bool
    {
        return \in_array($pluginKey, $this->enabledKeysFor($tenantId), true);
    }

    public function activate(int|string $tenantId, string $pluginKey): void
    {
        $this->upsert($tenantId, $pluginKey, true);
        $this->cache->forget($this->cacheKey($tenantId));
    }

    public function deactivate(int|string $tenantId, string $pluginKey): void
    {
        $this->upsert($tenantId, $pluginKey, false);
        $this->cache->forget($this->cacheKey($tenantId));
    }

    /** @return list<string> */
    public function enabledKeysFor(int|string $tenantId): array
    {
        return $this->cache->remember($this->cacheKey($tenantId), self::CACHE_TTL_SECONDS, function () use ($tenantId): array {
            $rows = $this->database->select(
                'SELECT plugin_key FROM site_plugins WHERE tenant_id = ? AND is_active = 1',
                [$tenantId]
            );

            return \array_map(static fn (array $row): string => (string) $row['plugin_key'], $rows);
        });
    }

    private function upsert(int|string $tenantId, string $pluginKey, bool $isActive): void
    {
        $existing = $this->database->selectOne(
            'SELECT id FROM site_plugins WHERE tenant_id = ? AND plugin_key = ?',
            [$tenantId, $pluginKey]
        );

        if ($existing === null) {
            $this->database->insert(
                'INSERT INTO site_plugins (tenant_id, plugin_key, is_active, activated_at) VALUES (?, ?, ?, ?)',
                [$tenantId, $pluginKey, $isActive ? 1 : 0, $isActive ? \date('Y-m-d H:i:s') : null]
            );

            return;
        }

        if ($isActive) {
            $this->database->update(
                'UPDATE site_plugins SET is_active = 1, activated_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE id = ?',
                [(int) $existing['id']]
            );

            return;
        }

        $this->database->update(
            'UPDATE site_plugins SET is_active = 0, updated_at = CURRENT_TIMESTAMP WHERE id = ?',
            [(int) $existing['id']]
        );
    }

    private function cacheKey(int|string $tenantId): string
    {
        return "tenant:{$tenantId}:plugins:enabled";
    }
}
