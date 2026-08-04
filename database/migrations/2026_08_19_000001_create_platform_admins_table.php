<?php

declare(strict_types=1);

use Core\Database;

/**
 * Identity rieng cho Super Admin (xuyen-tenant) - KHONG dung chung users/roles/user_site_roles
 * (nhung bang do thiet ke cho RBAC theo site, tenant_id NULL da mang nghia "role mac dinh dung
 * lai cho moi site", khac hoan toan khai niem "Super Admin toan he thong"). Chua co bang
 * permission rieng cho platform (YAGNI) - status hop le: active|locked.
 */
return [
    'up' => static function (Database $db): void {
        $driver = $db->connection()->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $primaryKey = $driver === 'sqlite'
            ? 'id INTEGER PRIMARY KEY AUTOINCREMENT'
            : 'id BIGINT AUTO_INCREMENT PRIMARY KEY';

        $db->statement("CREATE TABLE platform_admins (
            {$primaryKey},
            name VARCHAR(150) NOT NULL,
            email VARCHAR(190) NOT NULL,
            password VARCHAR(255) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL
        )");

        $db->statement('CREATE UNIQUE INDEX uq_platform_admins_email ON platform_admins (email)');
    },
    'down' => static function (Database $db): void {
        $db->statement('DROP TABLE IF EXISTS platform_admins');
    },
];
