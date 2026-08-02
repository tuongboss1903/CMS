<?php

declare(strict_types=1);

namespace Core;

use Closure;
use Core\Database\ConnectionException;
use Core\Database\QueryException;
use Core\Database\TransactionException;
use PDO;
use PDOException;
use PDOStatement;
use Throwable;

/**
 * Class DUY NHAT trong he thong duoc phep cham PDO truc tiep. Khong ORM - Repository/Service
 * luon di qua day hoac qua QueryBuilder (ban than QueryBuilder cung goi lai class nay de thuc thi).
 *
 * $connectionName cho phep sau nay tao nhieu Database tro toi cau hinh ket noi khac nhau (multiple
 * connections, hoac 1 connection rieng theo tenant neu chuyen sang Database-per-Tenant) ma khong
 * doi API - hien tai chi 'default' duoc dung.
 */
final class Database
{
    private ?PDO $pdo = null;
    private int $transactionLevel = 0;
    private bool $transactionMarkedForRollback = false;
    private bool $queryLogEnabled = false;

    /** @var list<array{sql: string, bindings: array<int|string, mixed>, time_ms: float}> */
    private array $queryLog = [];

    /** @var list<Closure(array{sql: string, bindings: array<int|string, mixed>, time_ms: float}): void> */
    private array $queryListeners = [];

    public function __construct(
        private readonly Config $config,
        private readonly string $connectionName = 'default',
    ) {
    }

    public function connection(): PDO
    {
        if ($this->pdo === null) {
            $this->pdo = $this->connect();
        }

        return $this->pdo;
    }

    public function table(string $table): QueryBuilder
    {
        return new QueryBuilder($this, $table);
    }

    /** @param array<int|string, mixed> $bindings */
    public function statement(string $sql, array $bindings = []): PDOStatement
    {
        $start = \microtime(true);

        try {
            $stmt = $this->connection()->prepare($sql);
            $stmt->execute($bindings);
        } catch (PDOException $exception) {
            throw new QueryException($sql, $bindings, $exception->getMessage(), $exception);
        }

        $this->recordQuery($sql, $bindings, (\microtime(true) - $start) * 1000);

        return $stmt;
    }

    /**
     * @param array<int|string, mixed> $bindings
     * @return list<array<string, mixed>>
     */
    public function select(string $sql, array $bindings = []): array
    {
        /** @var list<array<string, mixed>> */
        return $this->statement($sql, $bindings)->fetchAll();
    }

    /**
     * @param array<int|string, mixed> $bindings
     * @return array<string, mixed>|null
     */
    public function selectOne(string $sql, array $bindings = []): ?array
    {
        $row = $this->statement($sql, $bindings)->fetch();

        return $row === false ? null : $row;
    }

    /** @param array<int|string, mixed> $bindings */
    public function insert(string $sql, array $bindings = []): string
    {
        $this->statement($sql, $bindings);

        return $this->connection()->lastInsertId();
    }

    /** @param array<int|string, mixed> $bindings */
    public function update(string $sql, array $bindings = []): int
    {
        return $this->statement($sql, $bindings)->rowCount();
    }

    /** @param array<int|string, mixed> $bindings */
    public function delete(string $sql, array $bindings = []): int
    {
        return $this->statement($sql, $bindings)->rowCount();
    }

    public function beginTransaction(): void
    {
        if ($this->transactionLevel === 0) {
            try {
                $this->connection()->beginTransaction();
            } catch (PDOException $exception) {
                throw new TransactionException('Khong the bat dau transaction: ' . $exception->getMessage(), 0, $exception);
            }

            $this->transactionMarkedForRollback = false;
        }

        $this->transactionLevel++;
    }

    /**
     * Chi thuc su COMMIT khi ve toi level 0. Neu 1 transaction long ben trong da rollback()
     * (danh dau qua $transactionMarkedForRollback), level 0 se ROLLBACK thay vi commit va nem
     * TransactionException - khong bao gio commit "nham" du code goi la commit().
     */
    public function commit(): void
    {
        if ($this->transactionLevel === 0) {
            throw new TransactionException('Khong co transaction nao dang mo de commit.');
        }

        $this->transactionLevel--;

        if ($this->transactionLevel > 0) {
            return;
        }

        $wasMarkedForRollback = $this->transactionMarkedForRollback;

        try {
            if ($wasMarkedForRollback) {
                $this->connection()->rollBack();
            } else {
                $this->connection()->commit();
            }
        } catch (PDOException $exception) {
            throw new TransactionException('Loi khi ket thuc transaction: ' . $exception->getMessage(), 0, $exception);
        } finally {
            $this->transactionMarkedForRollback = false;
        }

        if ($wasMarkedForRollback) {
            throw new TransactionException(
                'Transaction da bi rollback boi 1 nested transaction truoc do; commit() khong duoc thuc hien.'
            );
        }
    }

    public function rollback(): void
    {
        if ($this->transactionLevel === 0) {
            throw new TransactionException('Khong co transaction nao dang mo de rollback.');
        }

        $this->transactionMarkedForRollback = true;
        $this->transactionLevel--;

        if ($this->transactionLevel > 0) {
            return;
        }

        try {
            $this->connection()->rollBack();
        } catch (PDOException $exception) {
            throw new TransactionException('Loi khi rollback transaction: ' . $exception->getMessage(), 0, $exception);
        } finally {
            $this->transactionMarkedForRollback = false;
        }
    }

    /**
     * API chinh duoc khuyen dung cho moi Service co thao tac ghi >= 2 buoc (dung database-design.md
     * muc 6.5). Rollback TU DONG khi $callback nem bat ky Throwable nao - loi goc luon duoc rethrow
     * nguyen ven, khong bi nuot.
     */
    public function transaction(Closure $callback): mixed
    {
        $this->beginTransaction();

        try {
            $result = $callback($this);
            $this->commit();

            return $result;
        } catch (Throwable $exception) {
            $this->rollback();

            throw $exception;
        }
    }

    public function enableQueryLog(): void
    {
        $this->queryLogEnabled = true;
    }

    /** @return list<array{sql: string, bindings: array<int|string, mixed>, time_ms: float}> */
    public function getQueryLog(): array
    {
        return $this->queryLog;
    }

    /**
     * Diem mo cho Logger/Hook that (chua trien khai o CMS-004 - core/Hook.php la task CMS-008,
     * khong tao phu thuoc nguoc vao 1 class chua ton tai). Logger/Hook that se goi ham nay o task sau.
     *
     * @param Closure(array{sql: string, bindings: array<int|string, mixed>, time_ms: float}): void $listener
     */
    public function onQueryExecuted(Closure $listener): void
    {
        $this->queryListeners[] = $listener;
    }

    private function connect(): PDO
    {
        $name = $this->connectionName === 'default'
            ? (string) $this->config->get('database.default', 'mysql')
            : $this->connectionName;

        $settings = $this->config->get("database.connections.{$name}");

        if (!\is_array($settings)) {
            throw new ConnectionException(\sprintf('Khong tim thay cau hinh ket noi database "%s".', $name));
        }

        try {
            return new PDO(
                $this->buildDsn($settings),
                $settings['username'] ?? null,
                $settings['password'] ?? null,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } catch (PDOException $exception) {
            throw new ConnectionException('Khong the ket noi database: ' . $exception->getMessage(), 0, $exception);
        }
    }

    /** @param array<string, mixed> $settings */
    private function buildDsn(array $settings): string
    {
        $driver = $settings['driver'] ?? 'mysql';

        if ($driver === 'sqlite') {
            return 'sqlite:' . ($settings['database'] ?? ':memory:');
        }

        return \sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $settings['host'] ?? '127.0.0.1',
            $settings['port'] ?? 3306,
            $settings['database'] ?? '',
            $settings['charset'] ?? 'utf8mb4'
        );
    }

    /** @param array<int|string, mixed> $bindings */
    private function recordQuery(string $sql, array $bindings, float $timeMs): void
    {
        $entry = ['sql' => $sql, 'bindings' => $bindings, 'time_ms' => $timeMs];

        if ($this->queryLogEnabled) {
            $this->queryLog[] = $entry;
        }

        foreach ($this->queryListeners as $listener) {
            $listener($entry);
        }
    }
}
