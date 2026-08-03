<?php

declare(strict_types=1);

use Core\Database;

/**
 * Phase 20 (Payment Gateway Integration & Order Notification, CMS-057). Tach khoi orders - 1 Order
 * co the co NHIEU luot thu thanh toan (COD auto-complete khong qua bang nay o luc dat hang, driver
 * online co the that bai roi thu lai). transaction_ref NULL cho phep voi COD (khong ma giao dich tu
 * cong thanh toan) - UNIQUE composite chap nhan nhieu dong NULL cung 1 (tenant_id, driver) dung
 * ngu nghia ANSI SQL "NULL != NULL" da ghi nhan tu CMS-028 (bang roles), khong phai loi migration.
 */
return [
    'up' => static function (Database $db): void {
        $driver = $db->connection()->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $primaryKey = $driver === 'sqlite'
            ? 'id INTEGER PRIMARY KEY AUTOINCREMENT'
            : 'id BIGINT AUTO_INCREMENT PRIMARY KEY';

        $db->statement("CREATE TABLE payments (
            {$primaryKey},
            tenant_id BIGINT NOT NULL,
            order_id BIGINT NOT NULL,
            driver VARCHAR(20) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            amount DECIMAL(12,2) NOT NULL,
            transaction_ref VARCHAR(100) NULL,
            raw_payload TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL,
            FOREIGN KEY (tenant_id) REFERENCES sites(id) ON DELETE CASCADE,
            FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
        )");

        $db->statement('CREATE UNIQUE INDEX uq_payments_tenant_driver_ref ON payments (tenant_id, driver, transaction_ref)');
        $db->statement('CREATE INDEX idx_payments_order_id ON payments (order_id)');
    },
    'down' => static function (Database $db): void {
        $db->statement('DROP TABLE IF EXISTS payments');
    },
];
