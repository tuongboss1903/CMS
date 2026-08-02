<?php

declare(strict_types=1);

use Core\Database;

/**
 * status hop le: active|maintenance|suspended (validate o Service layer sau, khong ENUM - SQLite
 * khong ho tro cu phap ENUM(...) trong CREATE TABLE). plan_id/theme_active khong FK cung (Owner
 * Decision CMS-028: plans chua ton tai; theme_active - filesystem la nguon su that, xem ThemeManager).
 */
return [
    'up' => static function (Database $db): void {
        $driver = $db->connection()->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $primaryKey = $driver === 'sqlite'
            ? 'id INTEGER PRIMARY KEY AUTOINCREMENT'
            : 'id BIGINT AUTO_INCREMENT PRIMARY KEY';

        $db->statement("CREATE TABLE sites (
            {$primaryKey},
            name VARCHAR(150) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            plan_id BIGINT NULL,
            theme_active VARCHAR(100) NULL,
            storage_used_bytes BIGINT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL
        )");

        $db->statement('CREATE INDEX idx_sites_status ON sites (status)');
        $db->statement('CREATE INDEX idx_sites_plan_storage ON sites (plan_id, storage_used_bytes)');
    },
    'down' => static function (Database $db): void {
        $db->statement('DROP TABLE IF EXISTS sites');
    },
];
