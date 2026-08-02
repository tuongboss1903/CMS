<?php

declare(strict_types=1);

use Core\Database;

/** status hop le: active|locked|pending (validate o Service layer sau). Khong hash password o day. */
return [
    'up' => static function (Database $db): void {
        $driver = $db->connection()->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $primaryKey = $driver === 'sqlite'
            ? 'id INTEGER PRIMARY KEY AUTOINCREMENT'
            : 'id BIGINT AUTO_INCREMENT PRIMARY KEY';

        $db->statement("CREATE TABLE users (
            {$primaryKey},
            name VARCHAR(150) NOT NULL,
            email VARCHAR(190) NOT NULL,
            password VARCHAR(255) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL
        )");

        $db->statement('CREATE UNIQUE INDEX uq_users_email ON users (email)');
        $db->statement('CREATE INDEX idx_users_status ON users (status)');
    },
    'down' => static function (Database $db): void {
        $db->statement('DROP TABLE IF EXISTS users');
    },
];
