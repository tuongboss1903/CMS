<?php

declare(strict_types=1);

use Core\Database;

/**
 * Bo sung anh dai dien cho San pham (products.image_id -> media.id) - Ecommerce truoc gio khong
 * co bat ky lien ket hinh anh nao, Shop public/Admin list deu khong hien thi anh. KHONG FK cung
 * (dung tien le sites.plan_id/menu_items.reference_id/media.folder_id da chot o
 * database-design.md muc 7.6) - validate ton tai + thuoc dung tenant o tang Controller
 * (ProductCreateController/ProductUpdateController), tranh ALTER TABLE them constraint tren bang
 * dang co du lieu that giua chu ky song.
 */
return [
    'up' => static function (Database $db): void {
        $db->statement('ALTER TABLE products ADD COLUMN image_id BIGINT NULL');
    },
    'down' => static function (Database $db): void {
        $driver = $db->connection()->getAttribute(\PDO::ATTR_DRIVER_NAME);

        if ($driver !== 'sqlite') {
            $db->statement('ALTER TABLE products DROP COLUMN image_id');
        }
    },
];
