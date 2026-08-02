<?php

declare(strict_types=1);

use Core\Database;

/**
 * Phase 12 (Advanced Analytics Dashboard, CMS-049). page_id nullable + FK ON DELETE SET NULL
 * (path khong khop page nao van duoc ghi nhan - vd 404, search). Khong ENUM/khong Trigger, dung
 * portable SQL da chot o CMS-028. Hard insert-only (khong deleted_at) - day la log, khong phai
 * entity nghiep vu can soft-delete.
 */
return [
    'up' => static function (Database $db): void {
        $driver = $db->connection()->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $primaryKey = $driver === 'sqlite'
            ? 'id INTEGER PRIMARY KEY AUTOINCREMENT'
            : 'id BIGINT AUTO_INCREMENT PRIMARY KEY';

        $db->statement("CREATE TABLE analytics_views (
            {$primaryKey},
            tenant_id BIGINT NOT NULL,
            page_id BIGINT NULL,
            path VARCHAR(500) NOT NULL,
            ip_hash VARCHAR(64) NOT NULL,
            user_agent VARCHAR(255) NULL,
            referrer VARCHAR(500) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (tenant_id) REFERENCES sites(id) ON DELETE CASCADE,
            FOREIGN KEY (page_id) REFERENCES pages(id) ON DELETE SET NULL
        )");

        $db->statement('CREATE INDEX idx_analytics_views_tenant_created ON analytics_views (tenant_id, created_at)');
        $db->statement('CREATE INDEX idx_analytics_views_tenant_path ON analytics_views (tenant_id, path)');
    },
    'down' => static function (Database $db): void {
        $db->statement('DROP TABLE IF EXISTS analytics_views');
    },
];
