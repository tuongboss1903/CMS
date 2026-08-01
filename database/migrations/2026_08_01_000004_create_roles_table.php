<?php

declare(strict_types=1);

use Core\Database;

/** tenant_id NULL = role he thong (Admin/Editor mac dinh), co gia tri = role tuy bien rieng cua 1 site. */
return [
    'up' => static function (Database $db): void {
        $driver = $db->connection()->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $primaryKey = $driver === 'sqlite'
            ? 'id INTEGER PRIMARY KEY AUTOINCREMENT'
            : 'id BIGINT AUTO_INCREMENT PRIMARY KEY';

        $db->statement("CREATE TABLE roles (
            {$primaryKey},
            tenant_id BIGINT NULL,
            name VARCHAR(100) NOT NULL,
            is_system BOOLEAN NOT NULL DEFAULT 0,
            FOREIGN KEY (tenant_id) REFERENCES sites(id) ON DELETE CASCADE
        )");

        $db->statement('CREATE UNIQUE INDEX uq_roles_tenant_name ON roles (tenant_id, name)');
    },
    'down' => static function (Database $db): void {
        $db->statement('DROP TABLE IF EXISTS roles');
    },
];
