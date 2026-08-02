<?php

declare(strict_types=1);

use Core\Database;

/** Bang trung gian N-N, composite PK - khong can cot id rieng. */
return [
    'up' => static function (Database $db): void {
        $db->statement('CREATE TABLE role_permissions (
            role_id BIGINT NOT NULL,
            permission_id BIGINT NOT NULL,
            PRIMARY KEY (role_id, permission_id),
            FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
            FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
        )');
    },
    'down' => static function (Database $db): void {
        $db->statement('DROP TABLE IF EXISTS role_permissions');
    },
];
