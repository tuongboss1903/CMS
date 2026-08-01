<?php

declare(strict_types=1);

use Core\Database;

return [
    'up' => static function (Database $db): void {
        $driver = $db->connection()->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $primaryKey = $driver === 'sqlite'
            ? 'id INTEGER PRIMARY KEY AUTOINCREMENT'
            : 'id BIGINT AUTO_INCREMENT PRIMARY KEY';

        $db->statement("CREATE TABLE permissions (
            {$primaryKey},
            `key` VARCHAR(100) NOT NULL,
            description VARCHAR(255) NULL
        )");

        $db->statement('CREATE UNIQUE INDEX uq_permissions_key ON permissions (`key`)');
    },
    'down' => static function (Database $db): void {
        $db->statement('DROP TABLE IF EXISTS permissions');
    },
];
