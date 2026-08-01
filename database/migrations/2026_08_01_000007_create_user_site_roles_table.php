<?php

declare(strict_types=1);

use Core\Database;

/** 1 user chi co dung 1 role tren 1 site tai 1 thoi diem (uq_user_site_roles). */
return [
    'up' => static function (Database $db): void {
        $driver = $db->connection()->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $primaryKey = $driver === 'sqlite'
            ? 'id INTEGER PRIMARY KEY AUTOINCREMENT'
            : 'id BIGINT AUTO_INCREMENT PRIMARY KEY';

        $db->statement("CREATE TABLE user_site_roles (
            {$primaryKey},
            user_id BIGINT NOT NULL,
            site_id BIGINT NOT NULL,
            role_id BIGINT NOT NULL,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE,
            FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE RESTRICT
        )");

        $db->statement('CREATE UNIQUE INDEX uq_user_site_roles ON user_site_roles (user_id, site_id)');
        $db->statement('CREATE INDEX idx_user_site_roles_site_id ON user_site_roles (site_id)');
    },
    'down' => static function (Database $db): void {
        $db->statement('DROP TABLE IF EXISTS user_site_roles');
    },
];
