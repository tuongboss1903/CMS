<?php

declare(strict_types=1);

use Core\Database;

/**
 * seo_meta - moi entity chi co dung 1 ban ghi SEO (UNIQUE tenant_id+entity_type+entity_id).
 * MVP chi entity_type='page' kha dung that (post/product chua ton tai - Owner Decision CMS-043).
 * schema_data luu TEXT thuan (JSON string), Application layer tu json_encode/json_decode - dung
 * nguyen Owner Decision CMS-040 da ap dung cho pages.content, khong dung JSON column.
 */
return [
    'up' => static function (Database $db): void {
        $driver = $db->connection()->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $primaryKey = $driver === 'sqlite'
            ? 'id INTEGER PRIMARY KEY AUTOINCREMENT'
            : 'id BIGINT AUTO_INCREMENT PRIMARY KEY';

        $db->statement("CREATE TABLE seo_meta (
            {$primaryKey},
            tenant_id BIGINT NOT NULL,
            entity_type VARCHAR(20) NOT NULL,
            entity_id BIGINT NOT NULL,
            title VARCHAR(255) NULL,
            description VARCHAR(500) NULL,
            canonical VARCHAR(500) NULL,
            og_image_id BIGINT NULL,
            schema_type VARCHAR(50) NULL,
            schema_data TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL,
            FOREIGN KEY (tenant_id) REFERENCES sites(id) ON DELETE CASCADE,
            FOREIGN KEY (og_image_id) REFERENCES media(id) ON DELETE SET NULL
        )");

        $db->statement('CREATE UNIQUE INDEX uq_seo_meta_entity ON seo_meta (tenant_id, entity_type, entity_id)');
    },
    'down' => static function (Database $db): void {
        $db->statement('DROP TABLE IF EXISTS seo_meta');
    },
];
