<?php

declare(strict_types=1);

use Core\Database;

/**
 * Phase 19 (Ecommerce MVP, CMS-056). Khong co cot tenant_id truc tiep (nhat quan tien le
 * menu_items/role_permissions - cach ly tenant qua JOIN orders.tenant_id, khong lap cot thua).
 * product_name_snapshot/unit_price luu LAI gia tri tai thoi diem mua (san pham co the doi ten/gia
 * sau do ma khong anh huong lich su don hang da co).
 */
return [
    'up' => static function (Database $db): void {
        $driver = $db->connection()->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $primaryKey = $driver === 'sqlite'
            ? 'id INTEGER PRIMARY KEY AUTOINCREMENT'
            : 'id BIGINT AUTO_INCREMENT PRIMARY KEY';

        $db->statement("CREATE TABLE order_items (
            {$primaryKey},
            order_id BIGINT NOT NULL,
            product_id BIGINT NOT NULL,
            product_variant_id BIGINT NULL,
            product_name_snapshot VARCHAR(255) NOT NULL,
            unit_price DECIMAL(12,2) NOT NULL,
            quantity INT NOT NULL,
            subtotal DECIMAL(12,2) NOT NULL,
            FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT,
            FOREIGN KEY (product_variant_id) REFERENCES product_variants(id) ON DELETE SET NULL
        )");

        $db->statement('CREATE INDEX idx_order_items_order_id ON order_items (order_id)');
    },
    'down' => static function (Database $db): void {
        $db->statement('DROP TABLE IF EXISTS order_items');
    },
];
