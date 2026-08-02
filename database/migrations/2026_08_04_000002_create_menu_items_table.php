<?php

declare(strict_types=1);

use Core\Database;

/**
 * menu_items - MVP chi 2 type: page/custom (khong post_category/product_category - module chua
 * ton tai, Owner Decision CMS-042). reference_id KHONG FK cung (chi co y nghia khi type=page,
 * validate/resolve o Controller - dung cach database-design.md da tu neu cho bang polymorphic).
 * parent_id self - dropdown, xoa Controller tu xu ly (khong dua FK CASCADE that vi SQLite test
 * khong enforce mac dinh).
 */
return [
    'up' => static function (Database $db): void {
        $driver = $db->connection()->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $primaryKey = $driver === 'sqlite'
            ? 'id INTEGER PRIMARY KEY AUTOINCREMENT'
            : 'id BIGINT AUTO_INCREMENT PRIMARY KEY';

        $db->statement("CREATE TABLE menu_items (
            {$primaryKey},
            menu_id BIGINT NOT NULL,
            parent_id BIGINT NULL,
            label VARCHAR(150) NOT NULL,
            type VARCHAR(20) NOT NULL,
            reference_id BIGINT NULL,
            url VARCHAR(500) NULL,
            target VARCHAR(20) NOT NULL DEFAULT '_self',
            sort_order INT NOT NULL DEFAULT 0,
            FOREIGN KEY (menu_id) REFERENCES menus(id) ON DELETE CASCADE,
            FOREIGN KEY (parent_id) REFERENCES menu_items(id) ON DELETE CASCADE
        )");

        $db->statement('CREATE INDEX idx_menu_items_menu_id_sort ON menu_items (menu_id, sort_order)');
    },
    'down' => static function (Database $db): void {
        $db->statement('DROP TABLE IF EXISTS menu_items');
    },
];
