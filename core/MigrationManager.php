<?php

declare(strict_types=1);

namespace Core;

use Closure;
use Core\Migration\MigrationException;
use Core\Migration\MigrationNotFoundException;

/**
 * Quan ly schema database (khong Business Logic). Migration file tra ve mang 2 Closure
 * (['up' => Closure, 'down' => Closure]) - khong interface, khong class, khong DSL, dung
 * Decision #1 CMS-013.
 *
 * $driver truyen tu ben ngoai (Config), KHONG tu doc PDO/getAttribute() - giu MigrationManager
 * khong biet gi ve PDO, khong dung API moi cua Database (Decision #8).
 *
 * migrate()/rollback() FAIL-FAST tuyet doi - khac ModuleManager/PluginManager, vi cac buoc thay
 * doi schema co tinh tuan tu/phu thuoc, chay tiep sau khi 1 migration loi co the pha schema.
 */
final class MigrationManager
{
    private const SUPPORTED_DRIVERS = ['mysql', 'sqlite'];

    public function __construct(
        private readonly Database $database,
        private readonly string $driver,
        private readonly string $migrationsPath,
    ) {
        if (!\in_array($this->driver, self::SUPPORTED_DRIVERS, true)) {
            throw MigrationException::unsupportedDriver($this->driver);
        }
    }

    /**
     * Chay toan bo migration chua ap dung, theo thu tu ten file. Fail-fast: loi o bat ky migration
     * nao se rethrow ngay, khong chay migration tiep theo. Migration da chay truoc do trong cung
     * lan goi van giu nguyen trang thai da commit.
     *
     * @return list<string> Ten cac migration da chay thanh cong
     */
    public function migrate(): array
    {
        $this->ensureMigrationsTable();

        $discovered = $this->discover();
        $applied = $this->appliedMigrations();
        $pending = \array_diff(\array_keys($discovered), $applied);

        if ($pending === []) {
            return [];
        }

        $batch = $this->nextBatch();
        $migrated = [];

        foreach ($pending as $name) {
            $migration = $this->loadMigration($discovered[$name]);

            $this->runInTransactionIfSupported(function (Database $db) use ($migration, $name, $batch): void {
                ($migration['up'])($db);
                $this->recordMigration($name, $batch);
            });

            $migrated[] = $name;
        }

        return $migrated;
    }

    /**
     * Rollback toan bo migration thuoc batch gan nhat, theo thu tu nguoc lai luc migrate.
     * Fail-fast giong migrate().
     *
     * @return list<string> Ten cac migration da rollback thanh cong
     */
    public function rollback(): array
    {
        $this->ensureMigrationsTable();

        $batch = $this->lastBatch();

        if ($batch === null) {
            return [];
        }

        $rows = $this->database->select(
            'SELECT migration FROM migrations WHERE batch = ? ORDER BY id DESC',
            [$batch]
        );

        $discovered = $this->discover();
        $rolledBack = [];

        foreach ($rows as $row) {
            $name = (string) $row['migration'];

            if (!isset($discovered[$name])) {
                throw MigrationNotFoundException::forRollback($name);
            }

            $migration = $this->loadMigration($discovered[$name]);

            $this->runInTransactionIfSupported(function (Database $db) use ($migration, $name): void {
                ($migration['down'])($db);
                $this->deleteMigrationRecord($name);
            });

            $rolledBack[] = $name;
        }

        return $rolledBack;
    }

    /** @return list<array{name: string, applied: bool, batch: int|null}> */
    public function status(): array
    {
        $this->ensureMigrationsTable();

        $discovered = $this->discover();
        $appliedRows = $this->database->select('SELECT migration, batch FROM migrations');

        $appliedMap = [];

        foreach ($appliedRows as $row) {
            $appliedMap[(string) $row['migration']] = (int) $row['batch'];
        }

        $result = [];

        foreach (\array_keys($discovered) as $name) {
            $result[] = [
                'name' => $name,
                'applied' => isset($appliedMap[$name]),
                'batch' => $appliedMap[$name] ?? null,
            ];
        }

        return $result;
    }

    /** @return array<string, string> Ten migration => duong dan file */
    private function discover(): array
    {
        $pattern = \rtrim($this->migrationsPath, '/\\') . DIRECTORY_SEPARATOR . '*.php';
        $files = \glob($pattern) ?: [];
        \sort($files);

        $migrations = [];

        foreach ($files as $file) {
            $migrations[\pathinfo($file, PATHINFO_FILENAME)] = $file;
        }

        return $migrations;
    }

    /** @return array{up: Closure, down: Closure} */
    private function loadMigration(string $file): array
    {
        $migration = require $file;

        if (
            !\is_array($migration)
            || !isset($migration['up'], $migration['down'])
            || !($migration['up'] instanceof Closure)
            || !($migration['down'] instanceof Closure)
        ) {
            throw MigrationException::invalidFile($file);
        }

        return $migration;
    }

    /** @return list<string> */
    private function appliedMigrations(): array
    {
        $rows = $this->database->select('SELECT migration FROM migrations');

        return \array_map(static fn (array $row): string => (string) $row['migration'], $rows);
    }

    private function nextBatch(): int
    {
        $lastBatch = $this->lastBatch();

        return $lastBatch === null ? 1 : $lastBatch + 1;
    }

    private function lastBatch(): ?int
    {
        $row = $this->database->selectOne('SELECT MAX(batch) as max_batch FROM migrations');

        return $row === null || $row['max_batch'] === null ? null : (int) $row['max_batch'];
    }

    private function recordMigration(string $name, int $batch): void
    {
        $this->database->insert(
            'INSERT INTO migrations (migration, batch, executed_at) VALUES (?, ?, ?)',
            [$name, $batch, \date('Y-m-d H:i:s')]
        );
    }

    private function deleteMigrationRecord(string $name): void
    {
        $this->database->delete('DELETE FROM migrations WHERE migration = ?', [$name]);
    }

    /**
     * MySQL/MariaDB tu dong COMMIT ngam ngay khi chay DDL (CREATE/ALTER/DROP TABLE) - lam lech
     * Database::$transactionLevel voi trang thai PDO that, khien commit() that bai (khong con
     * transaction de commit) roi rollback() trong catch cung that bai (level da ve 0). SQLite
     * KHONG co hanh vi nay - DDL van tham gia transaction binh thuong, van bao ve duoc atomicity.
     * Vi vay chi boc Database::transaction() that cho SQLite; MySQL/MariaDB chay truc tiep khong
     * bao transaction (khong mat gi vi DDL o do von da khong transactional).
     */
    private function runInTransactionIfSupported(Closure $step): void
    {
        if ($this->driver === 'sqlite') {
            $this->database->transaction($step);

            return;
        }

        $step($this->database);
    }

    private function ensureMigrationsTable(): void
    {
        $sql = $this->driver === 'sqlite'
            ? 'CREATE TABLE IF NOT EXISTS migrations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                migration VARCHAR(255) NOT NULL UNIQUE,
                batch INTEGER NOT NULL,
                executed_at DATETIME NOT NULL
            )'
            : 'CREATE TABLE IF NOT EXISTS migrations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                migration VARCHAR(255) NOT NULL UNIQUE,
                batch INT NOT NULL,
                executed_at DATETIME NOT NULL
            )';

        $this->database->statement($sql);
    }
}
