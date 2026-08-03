<?php

declare(strict_types=1);

use Core\Database;

/**
 * Phase 19 (Ecommerce MVP, CMS-056). price_override NULL = dung gia products.price (ProductService
 * tu tinh gia hieu luc, xem plugins/Ecommerce/Services/ProductService.php).
 */
return [
    'up' => static function (Database $db): void {
        $driver = $db->connection()->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $primaryKey = $driver === 'sqlite'
            ? 'id INTEGER PRIMARY KEY AUTOINCREMENT'
            : 'id BIGINT AUTO_INCREMENT PRIMARY KEY';

        $db->statement("CREATE TABLE product_variants (
            {$primaryKey},
            tenant_id BIGINT NOT NULL,
            product_id BIGINT NOT NULL,
            name VARCHAR(255) NOT NULL,
            sku VARCHAR(100) NULL,
            price_override DECIMAL(12,2) NULL,
            stock_quantity INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL,
            FOREIGN KEY (tenant_id) REFERENCES sites(id) ON DELETE CASCADE,
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
        )");

        $db->statement('CREATE INDEX idx_product_variants_tenant_product ON product_variants (tenant_id, product_id)');
    },
    'down' => static function (Database $db): void {
        $db->statement('DROP TABLE IF EXISTS product_variants');
    },
];
