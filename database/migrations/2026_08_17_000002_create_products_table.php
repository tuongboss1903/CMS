<?php

declare(strict_types=1);

use Core\Database;

/**
 * Phase 19 (Ecommerce MVP, CMS-056). status hop le: draft|published|archived (validate o
 * Controller/Action layer, khong ENUM - dung convention CMS-028). category la 1 field VARCHAR don
 * (khong bang product_categories rieng) - Owner Decision YAGNI o Architecture Analysis, chua co
 * yeu cau cay danh muc long nhau.
 */
return [
    'up' => static function (Database $db): void {
        $driver = $db->connection()->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $primaryKey = $driver === 'sqlite'
            ? 'id INTEGER PRIMARY KEY AUTOINCREMENT'
            : 'id BIGINT AUTO_INCREMENT PRIMARY KEY';

        $db->statement("CREATE TABLE products (
            {$primaryKey},
            tenant_id BIGINT NOT NULL,
            name VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL,
            description TEXT NULL,
            category VARCHAR(100) NULL,
            price DECIMAL(12,2) NOT NULL,
            sku VARCHAR(100) NULL,
            stock_quantity INT NOT NULL DEFAULT 0,
            status VARCHAR(20) NOT NULL DEFAULT 'draft',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL,
            deleted_at TIMESTAMP NULL,
            FOREIGN KEY (tenant_id) REFERENCES sites(id) ON DELETE CASCADE
        )");

        $db->statement('CREATE UNIQUE INDEX uq_products_tenant_slug ON products (tenant_id, slug)');
        $db->statement('CREATE INDEX idx_products_tenant_status ON products (tenant_id, status)');
    },
    'down' => static function (Database $db): void {
        $db->statement('DROP TABLE IF EXISTS products');
    },
];
