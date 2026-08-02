<?php

declare(strict_types=1);

require __DIR__ . '/load_env.php';
require __DIR__ . '/../vendor/autoload.php';

use Core\Config;

/**
 * Tao database MySQL (CREATE DATABASE IF NOT EXISTS) theo dung config/database.php - chi ho tro
 * driver mysql (kien truc hien tai). Ket noi KHONG kem dbname (dbname co the chua ton tai).
 * Script rieng biet, khong dung Core\Database (class do luon ket noi kem dbname).
 */
$basePath = \dirname(__DIR__);
$config = new Config($basePath . '/config');

$connectionName = (string) $config->get('database.default', 'mysql');
$settings = $config->get("database.connections.{$connectionName}");

if (!\is_array($settings) || ($settings['driver'] ?? 'mysql') !== 'mysql') {
    \fwrite(STDERR, "Script nay chi ho tro driver 'mysql'.\n");
    exit(1);
}

$dsn = \sprintf(
    'mysql:host=%s;port=%s;charset=%s',
    $settings['host'] ?? '127.0.0.1',
    $settings['port'] ?? 3306,
    $settings['charset'] ?? 'utf8mb4'
);

try {
    $pdo = new PDO($dsn, $settings['username'] ?? null, $settings['password'] ?? null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    $database = (string) ($settings['database'] ?? 'cms_db');
    $charset = (string) ($settings['charset'] ?? 'utf8mb4');
    $collation = (string) ($settings['collation'] ?? 'utf8mb4_unicode_ci');

    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$database}` CHARACTER SET {$charset} COLLATE {$collation}");

    echo "Da tao (hoac da co san) database '{$database}'.\n";
} catch (Throwable $exception) {
    \fwrite(STDERR, 'Loi: ' . $exception->getMessage() . "\n");
    exit(1);
}
