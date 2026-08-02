<?php

declare(strict_types=1);

require __DIR__ . '/load_env.php';
require __DIR__ . '/../vendor/autoload.php';

use Core\Config;
use Core\Database;

/**
 * Them 1 Tenant moi (Site + Domain) vao he thong da khoi tao, tai su dung Admin User + System
 * Admin Role da co san (roles.tenant_id IS NULL - dung chung moi tenant, dung Role Model da xac
 * nhan tu CMS-037/core-architecture.md). KHONG sua bin/bootstrap.php (da test, chi chay duoc 1
 * lan - kiem tra users rong). Day la script rieng biet cho tinh huong "them Site thu 2 tro di"
 * (Phase 7 Architecture Analysis - Fork phat hien bootstrap.php se tu choi chay lan 2).
 */
$basePath = \dirname(__DIR__);
$config = new Config($basePath . '/config');
$database = new Database($config);

$siteName = $argv[1] ?? null;
$domain = $argv[2] ?? null;

if ($siteName === null || $domain === null) {
    \fwrite(STDERR, "Su dung: php bin/add_site.php <site_name> <domain>\n");
    exit(1);
}

$existingDomain = $database->selectOne('SELECT id FROM site_domains WHERE domain = ?', [$domain]);

if ($existingDomain !== null) {
    \fwrite(STDERR, "Domain '{$domain}' da duoc su dung boi 1 Site khac.\n");
    exit(1);
}

$adminRole = $database->selectOne("SELECT id FROM roles WHERE tenant_id IS NULL AND name = 'Admin'");

if ($adminRole === null) {
    \fwrite(STDERR, "Chua co System Admin Role. Hay chay 'php bin/bootstrap.php' truoc de khoi tao he thong.\n");
    exit(1);
}

$admin = $database->selectOne('SELECT id FROM users ORDER BY id ASC LIMIT 1');

if ($admin === null) {
    \fwrite(STDERR, "Chua co Admin User nao. Hay chay 'php bin/bootstrap.php' truoc.\n");
    exit(1);
}

$roleId = (int) $adminRole['id'];
$adminId = (int) $admin['id'];

try {
    $siteId = $database->transaction(function (Database $db) use ($siteName, $domain, $adminId, $roleId): int {
        $db->insert('INSERT INTO sites (name) VALUES (?)', [$siteName]);
        $newSiteId = (int) $db->connection()->lastInsertId();

        $db->insert('INSERT INTO site_domains (site_id, domain) VALUES (?, ?)', [$newSiteId, $domain]);

        $db->insert(
            'INSERT INTO user_site_roles (user_id, site_id, role_id) VALUES (?, ?, ?)',
            [$adminId, $newSiteId, $roleId]
        );

        return $newSiteId;
    });
} catch (\Throwable $exception) {
    \fwrite(STDERR, 'Loi: ' . $exception->getMessage() . "\n");
    exit(1);
}

echo "Da tao Site moi '{$siteName}' (domain: {$domain}, site_id: {$siteId}). Admin hien co da duoc gan quyen truy cap Site nay.\n";
echo "Buoc tiep theo: them '{$domain}' vao file hosts local, roi chay 'php bin/seed_demo.php {$domain} restaurant' de tao du lieu mau.\n";
