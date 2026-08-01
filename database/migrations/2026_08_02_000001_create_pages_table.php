<?php

declare(strict_types=1);

use Core\Database;

/**
 * status hop le: draft|published|scheduled (validate o Controller layer, khong ENUM - dung
 * convention CMS-028). content luu TEXT thuan, Application layer (Controller) tu json_encode/
 * json_decode - khong dung JSON column dependency (Owner Decision CMS-040).
 */
return [
    'up' => static function (Database $db): void {
        $driver = $db->connection()->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $primaryKey = $driver === 'sqlite'
            ? 'id INTEGER PRIMARY KEY AUTOINCREMENT'
            : 'id BIGINT AUTO_INCREMENT PRIMARY KEY';

        $db->statement("CREATE TABLE pages (
            {$primaryKey},
            tenant_id BIGINT NOT NULL,
            parent_id BIGINT NULL,
            title VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL,
            content TEXT NULL,
            template VARCHAR(100) NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'draft',
            published_at TIMESTAMP NULL,
            is_homepage BOOLEAN NOT NULL DEFAULT 0,
            created_by BIGINT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL,
            deleted_at TIMESTAMP NULL,
            FOREIGN KEY (tenant_id) REFERENCES sites(id) ON DELETE CASCADE,
            FOREIGN KEY (parent_id) REFERENCES pages(id) ON DELETE SET NULL,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
        )");

        $db->statement('CREATE UNIQUE INDEX uq_pages_tenant_slug ON pages (tenant_id, slug)');
        $db->statement('CREATE INDEX idx_pages_tenant_status ON pages (tenant_id, status)');
        $db->statement('CREATE INDEX idx_pages_parent_id ON pages (parent_id)');
    },
    'down' => static function (Database $db): void {
        $db->statement('DROP TABLE IF EXISTS pages');
    },
];
