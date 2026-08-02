<?php

declare(strict_types=1);

use Core\Database;

/**
 * Phase 17 (System Settings & General Configurations, CMS-054). Bang "settings" (KHONG dat ten
 * "site_settings" - ten do da dung cho bang cot co dinh tao o CMS-042, xem
 * 2026_08_09_000001_create_site_settings_table.php - trung ten se loi SQL "table already exists").
 * Day la lop key-value TONG QUAT, bo sung cho SiteSettingsManager (cot co dinh), khong thay the.
 *
 * tenant_id NULLABLE (dung tien le audit_logs, notifications) - ho tro setting cap he thong
 * (khong gan 1 tenant cu the, vd default toan cuc). `key`/`setting_group` dat ten tranh tu khoa SQL
 * dat truoc (`key` da co tien le dung backtick tu bang permissions - CMS-028; `group` la tu khoa
 * GROUP BY nen doi thanh "setting_group" thay vi backtick moi noi).
 *
 * UNIQUE (tenant_id, key): NULL != NULL trong composite UNIQUE (ANSI SQL, ca SQLite lan MySQL) -
 * nhieu setting he thong (tenant_id NULL) co the trung key ma khong bi chan boi UNIQUE nay, dung
 * tien le da ghi nhan o bang "roles" (CMS-028) - chap nhan duoc, khong phai loi migration.
 */
return [
    'up' => static function (Database $db): void {
        $driver = $db->connection()->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $primaryKey = $driver === 'sqlite'
            ? 'id INTEGER PRIMARY KEY AUTOINCREMENT'
            : 'id BIGINT AUTO_INCREMENT PRIMARY KEY';

        $db->statement("CREATE TABLE settings (
            {$primaryKey},
            tenant_id BIGINT NULL,
            setting_group VARCHAR(50) NOT NULL DEFAULT 'general',
            `key` VARCHAR(100) NOT NULL,
            value TEXT NULL,
            is_encrypted BOOLEAN NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL,
            FOREIGN KEY (tenant_id) REFERENCES sites(id) ON DELETE CASCADE
        )");

        $db->statement('CREATE UNIQUE INDEX uq_settings_tenant_key ON settings (tenant_id, `key`)');
        $db->statement('CREATE INDEX idx_settings_tenant_group ON settings (tenant_id, setting_group)');
    },
    'down' => static function (Database $db): void {
        $db->statement('DROP TABLE IF EXISTS settings');
    },
];
