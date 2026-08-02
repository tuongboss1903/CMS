<?php

declare(strict_types=1);

use Core\Database;

/**
 * menus - moi vi tri (location_key) chi gan dung 1 Menu/site (UNIQUE tenant_id+location_key).
 * location_key la chuoi tu do (khong co Theme khai bao hop le - chua co bang chung can, YAGNI).
 */
return [
    'up' => static function (Database $db): void {
        $driver = $db->connection()->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $primaryKey = $driver === 'sqlite'
            ? 'id INTEGER PRIMARY KEY AUTOINCREMENT'
            : 'id BIGINT AUTO_INCREMENT PRIMARY KEY';

        $db->statement("CREATE TABLE menus (
            {$primaryKey},
            tenant_id BIGINT NOT NULL,
            name VARCHAR(150) NOT NULL,
            location_key VARCHAR(50) NOT NULL,
            FOREIGN KEY (tenant_id) REFERENCES sites(id) ON DELETE CASCADE
        )");

        $db->statement('CREATE UNIQUE INDEX uq_menus_tenant_location ON menus (tenant_id, location_key)');
    },
    'down' => static function (Database $db): void {
        $db->statement('DROP TABLE IF EXISTS menus');
    },
];
