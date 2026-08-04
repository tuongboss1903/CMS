<?php

declare(strict_types=1);

use Core\Database;

/**
 * Goi dich vu (Subscription Plan) - Super Admin tao/sua, gan cho site qua sites.plan_id (cot da
 * co san tu migration create_sites_table, truoc day khong FK cung vi bang nay chua ton tai -
 * Owner Decision CMS-028, gio van GIU KHONG FK cung theo dung quyet dinh do, tranh ALTER TABLE
 * them constraint tren sites o giua chu ky song). max_users/max_storage_mb/max_products NULL =
 * khong gioi han. is_active = false chi an goi khoi danh sach gan MOI cho site, KHONG anh huong
 * site dang dung goi do (tranh mo coi plan_id khi xoa/an goi).
 */
return [
    'up' => static function (Database $db): void {
        $driver = $db->connection()->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $primaryKey = $driver === 'sqlite'
            ? 'id INTEGER PRIMARY KEY AUTOINCREMENT'
            : 'id BIGINT AUTO_INCREMENT PRIMARY KEY';

        $db->statement("CREATE TABLE plans (
            {$primaryKey},
            `key` VARCHAR(50) NOT NULL,
            name VARCHAR(150) NOT NULL,
            price_vnd BIGINT NOT NULL DEFAULT 0,
            billing_cycle VARCHAR(20) NOT NULL DEFAULT 'monthly',
            max_users INT NULL,
            max_storage_mb INT NULL,
            max_products INT NULL,
            is_active BOOLEAN NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL
        )");

        $db->statement('CREATE UNIQUE INDEX uq_plans_key ON plans (`key`)');
    },
    'down' => static function (Database $db): void {
        $db->statement('DROP TABLE IF EXISTS plans');
    },
];
