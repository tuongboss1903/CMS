<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Core\Config;
use Core\Database;

/**
 * Khoi tao CMS lan dau: tao Site + Admin User + System Admin Role (tenant_id NULL) + 16 permission
 * bootstrap + gan toan bo cho role Admin + lien ket user_site_roles. Chi chay DUOC 1 LAN (kiem tra
 * users rong truoc khi lam bat ky dieu gi). Toan bo nam trong 1 Database::transaction() - loi o
 * buoc nao cung rollback sach, khong de lai du lieu mo coi. Khong dua logic vao modules/Auth hoac
 * modules/User - script doc lap, chi dung Core\Config/Core\Database.
 *
 * Tien le chinh thuc (CMS-040): moi Module tuong lai can permission moi deu mo rong mang
 * $permissionKeys duoi day - khong tu tao co che seed permission rieng, vi Role Module (CMS-038)
 * chi gan permission da ton tai, khong tao permission moi.
 */
$basePath = \dirname(__DIR__);

$config = new Config($basePath . '/config');
$database = new Database($config);

$siteName = $argv[1] ?? null;
$domain = $argv[2] ?? null;
$adminName = $argv[3] ?? null;
$adminEmail = $argv[4] ?? null;
$adminPassword = $argv[5] ?? null;

if ($siteName === null || $domain === null || $adminName === null || $adminEmail === null || $adminPassword === null) {
    \fwrite(STDERR, "Su dung: php bin/bootstrap.php <site_name> <domain> <admin_name> <admin_email> <admin_password>\n");
    exit(1);
}

$existing = $database->selectOne('SELECT COUNT(*) as total FROM users');

if ($existing !== null && (int) $existing['total'] > 0) {
    \fwrite(STDERR, "CMS da duoc khoi tao truoc do. Bootstrap chi chay duoc 1 lan.\n");
    exit(1);
}

$permissionKeys = [
    'user.view', 'user.create', 'user.update', 'user.lock', 'user.assign_role',
    'role.view', 'role.create', 'role.update', 'role.delete', 'role.assign_permission',
    'dashboard.view',
    'page.view', 'page.create', 'page.update', 'page.delete', 'page.publish',
    'media.view', 'media.upload', 'media.update', 'media.delete',
    'menu.view', 'menu.create', 'menu.update', 'menu.delete',
];

try {
    $database->transaction(function (Database $db) use ($siteName, $domain, $adminName, $adminEmail, $adminPassword, $permissionKeys): void {
        $db->insert('INSERT INTO sites (name) VALUES (?)', [$siteName]);
        $siteId = (int) $db->connection()->lastInsertId();

        $db->insert('INSERT INTO site_domains (site_id, domain) VALUES (?, ?)', [$siteId, $domain]);

        $db->insert(
            'INSERT INTO users (name, email, password, status) VALUES (?, ?, ?, ?)',
            [$adminName, $adminEmail, \password_hash($adminPassword, PASSWORD_DEFAULT), 'active']
        );
        $userId = (int) $db->connection()->lastInsertId();

        $db->insert('INSERT INTO roles (tenant_id, name) VALUES (NULL, ?)', ['Admin']);
        $roleId = (int) $db->connection()->lastInsertId();

        $permissionIds = [];

        foreach ($permissionKeys as $key) {
            $db->insert('INSERT INTO permissions (`key`) VALUES (?)', [$key]);
            $permissionIds[] = (int) $db->connection()->lastInsertId();
        }

        foreach ($permissionIds as $permissionId) {
            $db->insert('INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)', [$roleId, $permissionId]);
        }

        $db->insert(
            'INSERT INTO user_site_roles (user_id, site_id, role_id) VALUES (?, ?, ?)',
            [$userId, $siteId, $roleId]
        );
    });
} catch (\Throwable $exception) {
    \fwrite(STDERR, 'Loi: ' . $exception->getMessage() . "\n");
    exit(1);
}

echo "Bootstrap thanh cong.\n";
