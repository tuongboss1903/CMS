<?php

declare(strict_types=1);

namespace Modules\Settings;

use Core\Database;
use Core\TenantManager;

/**
 * Service doc/ghi site_settings theo tenant hien tai - Owner Decision Phase 4 (chap nhan Service
 * Layer dau tien trong du an, khac tien le "Controller goi Database truc tiep" da giu xuyen suot
 * cho Page/Media/Menu/Seo). Ly do: duoc dung boi >=4 noi doc lap (SitemapController, RobotsController,
 * Public layout injection, Admin SettingUpdateController) - khac hoan toan pattern "1 Controller
 * so huu logic" cua cac Module truoc. Cache runtime (mang trong bo nho, theo tenant_id) - CHI song
 * trong 1 request/1 instance Container, khong phai Cache lop core (khong dung core/Cache.php).
 */
final class SiteSettingsManager
{
    /** @var array<string, array<string, mixed>> */
    private array $cache = [];

    public function __construct(
        private readonly Database $database,
        private readonly TenantManager $tenantManager,
    ) {
    }

    /** @return array<string, mixed> */
    public function get(): array
    {
        $tenantId = $this->tenantManager->id();
        $cacheKey = (string) $tenantId;

        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $row = $this->database->selectOne('SELECT * FROM site_settings WHERE tenant_id = ?', [$tenantId]);

        $settings = $row ?? [
            'site_name' => null,
            'site_tagline' => null,
            'default_meta_description' => null,
            'default_og_image_id' => null,
            'favicon_id' => null,
            'robots_txt_custom' => null,
        ];

        $this->cache[$cacheKey] = $settings;

        return $settings;
    }

    /** @param array<string, mixed> $fields */
    public function set(array $fields): void
    {
        $tenantId = $this->tenantManager->id();

        $existing = $this->database->selectOne('SELECT id FROM site_settings WHERE tenant_id = ?', [$tenantId]);

        if ($existing === null) {
            $columns = \array_keys($fields);
            $placeholders = \implode(',', \array_fill(0, \count($columns) + 1, '?'));

            $this->database->insert(
                'INSERT INTO site_settings (tenant_id, ' . \implode(', ', $columns) . ") VALUES ({$placeholders})",
                [$tenantId, ...\array_values($fields)]
            );
        } elseif ($fields !== []) {
            $setClauses = [];
            $bindings = [];

            foreach ($fields as $column => $value) {
                $setClauses[] = "{$column} = ?";
                $bindings[] = $value;
            }

            $bindings[] = (int) $existing['id'];

            $this->database->statement(
                'UPDATE site_settings SET ' . \implode(', ', $setClauses) . ' WHERE id = ?',
                $bindings
            );
        }

        unset($this->cache[(string) $tenantId]);
    }
}
