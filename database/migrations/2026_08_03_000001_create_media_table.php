<?php

declare(strict_types=1);

use Core\Database;

/**
 * MVP toi gian (Owner Decision CMS-041): chi bang media, KHONG media_folders/media_variants/
 * media_usages - chua co consumer that nao tham chieu media (pages khong co featured_image_id),
 * chua co thu vien xu ly anh trong composer.json. Hard delete (khong deleted_at).
 */
return [
    'up' => static function (Database $db): void {
        $driver = $db->connection()->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $primaryKey = $driver === 'sqlite'
            ? 'id INTEGER PRIMARY KEY AUTOINCREMENT'
            : 'id BIGINT AUTO_INCREMENT PRIMARY KEY';

        $db->statement("CREATE TABLE media (
            {$primaryKey},
            tenant_id BIGINT NOT NULL,
            file_name VARCHAR(255) NOT NULL,
            path VARCHAR(500) NOT NULL,
            mime_type VARCHAR(100) NOT NULL,
            size BIGINT NOT NULL,
            alt_text VARCHAR(255) NULL,
            title VARCHAR(255) NULL,
            caption VARCHAR(500) NULL,
            uploaded_by BIGINT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (tenant_id) REFERENCES sites(id) ON DELETE CASCADE,
            FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE RESTRICT
        )");

        $db->statement('CREATE INDEX idx_media_tenant_id ON media (tenant_id)');
    },
    'down' => static function (Database $db): void {
        $db->statement('DROP TABLE IF EXISTS media');
    },
];
