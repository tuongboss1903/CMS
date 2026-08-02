<?php

declare(strict_types=1);

use Core\Database;

/**
 * Phase 14 (Comment/Review System, CMS-051). MVP: entity_type chi 'page' kha dung that
 * (dung tien le seo_meta - CMS-043), khong parent_id (khong threaded reply - Owner Decision
 * Architecture Analysis). status: 'pending' (mac dinh)|'approved'|'rejected' - VARCHAR khong ENUM
 * (Portable SQL, CMS-028). ip_hash dung ky thuat sha256+app.key giong AnalyticsService (Phase 12),
 * khong luu IP tho.
 */
return [
    'up' => static function (Database $db): void {
        $driver = $db->connection()->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $primaryKey = $driver === 'sqlite'
            ? 'id INTEGER PRIMARY KEY AUTOINCREMENT'
            : 'id BIGINT AUTO_INCREMENT PRIMARY KEY';

        $db->statement("CREATE TABLE comments (
            {$primaryKey},
            tenant_id BIGINT NOT NULL,
            entity_type VARCHAR(20) NOT NULL,
            entity_id BIGINT NOT NULL,
            guest_name VARCHAR(150) NOT NULL,
            guest_email VARCHAR(190) NOT NULL,
            body TEXT NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            ip_hash VARCHAR(64) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL,
            FOREIGN KEY (tenant_id) REFERENCES sites(id) ON DELETE CASCADE,
            FOREIGN KEY (entity_id) REFERENCES pages(id) ON DELETE CASCADE
        )");

        $db->statement('CREATE INDEX idx_comments_tenant_entity_status ON comments (tenant_id, entity_type, entity_id, status)');
        $db->statement('CREATE INDEX idx_comments_tenant_status ON comments (tenant_id, status)');
    },
    'down' => static function (Database $db): void {
        $db->statement('DROP TABLE IF EXISTS comments');
    },
];
