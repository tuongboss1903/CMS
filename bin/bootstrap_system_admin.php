<?php

declare(strict_types=1);

require __DIR__ . '/load_env.php';
require __DIR__ . '/../vendor/autoload.php';

use Core\Config;
use Core\Database;

/**
 * Khoi tao Super Admin dau tien - doc lap hoan toan voi bin/bootstrap.php (site/tenant admin).
 * Chi chay duoc 1 lan (kiem tra platform_admins rong truoc). Khong dua logic vao modules/SystemAdmin -
 * script doc lap, chi dung Core\Config/Core\Database, cung nguyen tac bin/bootstrap.php.
 */
$basePath = \dirname(__DIR__);

$config = new Config($basePath . '/config');
$database = new Database($config);

$name = $argv[1] ?? null;
$email = $argv[2] ?? null;
$password = $argv[3] ?? null;

if ($name === null || $email === null || $password === null) {
    \fwrite(STDERR, "Su dung: php bin/bootstrap_system_admin.php <name> <email> <password>\n");
    exit(1);
}

$existing = $database->selectOne('SELECT COUNT(*) as total FROM platform_admins');

if ($existing !== null && (int) $existing['total'] > 0) {
    \fwrite(STDERR, "Super Admin da duoc khoi tao truoc do. Chi chay duoc 1 lan.\n");
    exit(1);
}

try {
    $database->insert(
        'INSERT INTO platform_admins (name, email, password, status) VALUES (?, ?, ?, ?)',
        [$name, $email, \password_hash($password, PASSWORD_DEFAULT), 'active']
    );
} catch (\Throwable $exception) {
    \fwrite(STDERR, 'Loi: ' . $exception->getMessage() . "\n");
    exit(1);
}

echo "Tao Super Admin thanh cong.\n";
