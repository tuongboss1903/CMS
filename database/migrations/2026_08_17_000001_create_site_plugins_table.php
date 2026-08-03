<?php

declare(strict_types=1);

use Core\Database;

/**
 * Dong Technical Debt #9 (core-architecture.md, ghi nhan tu CMS-012): PluginManager::boot() truoc
 * Phase 19 khong co bang nao luu trang thai bat/tat theo tenant - Application::boot() coi moi
 * plugin da discover() la "enabled" cho TOAN BO he thong. Bang nay dung CHUNG cho MOI plugin
 * tuong lai (khong rieng Ecommerce) - plugin_key khop voi 'key' trong plugin.json (xem
 * core/PluginManager.php::parseManifest()).
 */
return [
    'up' => static function (Database $db): void {
        $driver = $db->connection()->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $primaryKey = $driver === 'sqlite'
            ? 'id INTEGER PRIMARY KEY AUTOINCREMENT'
            : 'id BIGINT AUTO_INCREMENT PRIMARY KEY';

        $db->statement("CREATE TABLE site_plugins (
            {$primaryKey},
            tenant_id BIGINT NOT NULL,
            plugin_key VARCHAR(100) NOT NULL,
            is_active BOOLEAN NOT NULL DEFAULT 0,
            activated_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL,
            FOREIGN KEY (tenant_id) REFERENCES sites(id) ON DELETE CASCADE
        )");

        $db->statement('CREATE UNIQUE INDEX uq_site_plugins_tenant_key ON site_plugins (tenant_id, plugin_key)');
        $db->statement('CREATE INDEX idx_site_plugins_tenant_active ON site_plugins (tenant_id, is_active)');
    },
    'down' => static function (Database $db): void {
        $db->statement('DROP TABLE IF EXISTS site_plugins');
    },
];
