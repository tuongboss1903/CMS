<?php

declare(strict_types=1);

use Core\Database;

/**
 * Phase 19 (Ecommerce MVP, CMS-056). Checkout dang Guest (guest_name/guest_email nhap tay, khong
 * tao users moi) - dung tien le Comment System (Phase 14). status hop le:
 * pending|processing|completed|cancelled (validate o Action layer, khong ENUM). payment_method
 * co dinh 'cod' o MVP (khong tich hop cong thanh toan ngoai - Zero-dependency).
 */
return [
    'up' => static function (Database $db): void {
        $driver = $db->connection()->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $primaryKey = $driver === 'sqlite'
            ? 'id INTEGER PRIMARY KEY AUTOINCREMENT'
            : 'id BIGINT AUTO_INCREMENT PRIMARY KEY';

        $db->statement("CREATE TABLE orders (
            {$primaryKey},
            tenant_id BIGINT NOT NULL,
            order_number VARCHAR(40) NOT NULL,
            guest_name VARCHAR(255) NOT NULL,
            guest_email VARCHAR(255) NOT NULL,
            shipping_address TEXT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            total_amount DECIMAL(12,2) NOT NULL,
            payment_method VARCHAR(20) NOT NULL DEFAULT 'cod',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL,
            FOREIGN KEY (tenant_id) REFERENCES sites(id) ON DELETE CASCADE
        )");

        $db->statement('CREATE UNIQUE INDEX uq_orders_tenant_number ON orders (tenant_id, order_number)');
        $db->statement('CREATE INDEX idx_orders_tenant_status ON orders (tenant_id, status)');
        $db->statement('CREATE INDEX idx_orders_tenant_created ON orders (tenant_id, created_at)');
    },
    'down' => static function (Database $db): void {
        $db->statement('DROP TABLE IF EXISTS orders');
    },
];
