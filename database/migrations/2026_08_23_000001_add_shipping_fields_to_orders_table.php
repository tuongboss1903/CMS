<?php

declare(strict_types=1);

use Core\Database;

/**
 * Bo sung phi ship + khoang cach giao hang vao orders - phuc vu tinh nang "giao hang trong ban
 * kinh co dinh" cua Ecommerce plugin (vd quan ca phe giao nuoc trong 5km). shipping_fee tach rieng
 * khoi total_amount (total_amount = tong tien hang + shipping_fee, snapshot tai thoi diem dat -
 * doi cau hinh ban kinh/phi sau khong anh huong don cu). shipping_distance_km luu lai de doi soat/
 * ho tro khach hang, khong dung de tinh lai phi (phi da chot trong total_amount).
 */
return [
    'up' => static function (Database $db): void {
        $db->statement('ALTER TABLE orders ADD COLUMN shipping_fee DECIMAL(12,2) NOT NULL DEFAULT 0');
        $db->statement('ALTER TABLE orders ADD COLUMN shipping_distance_km DECIMAL(6,2) NULL');
    },
    'down' => static function (Database $db): void {
        $driver = $db->connection()->getAttribute(\PDO::ATTR_DRIVER_NAME);

        if ($driver !== 'sqlite') {
            // SQLite (< 3.35) khong ho tro DROP COLUMN truc tiep - chap nhan gioi han nay cho
            // rollback local/test, dung tien le da chap nhan o migration media_folders/media_variants.
            $db->statement('ALTER TABLE orders DROP COLUMN shipping_fee');
            $db->statement('ALTER TABLE orders DROP COLUMN shipping_distance_km');
        }
    },
];
