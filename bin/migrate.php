<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Core\Config;
use Core\Database;
use Core\MigrationManager;

$basePath = \dirname(__DIR__);

$config = new Config($basePath . '/config');
$database = new Database($config);
$driver = (string) $config->get('database.default', 'mysql');

$migrationManager = new MigrationManager($database, $driver, $basePath . '/database/migrations');

$action = $argv[1] ?? null;

try {
    switch ($action) {
        case 'migrate':
            $result = $migrationManager->migrate();
            echo $result === []
                ? "Khong co migration nao can chay.\n"
                : "Da migrate:\n" . \implode("\n", $result) . "\n";
            break;

        case 'rollback':
            $result = $migrationManager->rollback();
            echo $result === []
                ? "Khong co migration nao de rollback.\n"
                : "Da rollback:\n" . \implode("\n", $result) . "\n";
            break;

        case 'status':
            foreach ($migrationManager->status() as $row) {
                $state = $row['applied'] ? \sprintf('applied (batch %d)', $row['batch']) : 'pending';
                echo \sprintf("%s - %s\n", $row['name'], $state);
            }
            break;

        default:
            \fwrite(STDERR, "Su dung: php bin/migrate.php migrate|rollback|status\n");
            exit(1);
    }
} catch (Throwable $exception) {
    \fwrite(STDERR, 'Loi: ' . $exception->getMessage() . "\n");
    exit(1);
}
