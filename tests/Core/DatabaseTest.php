<?php

declare(strict_types=1);

namespace Tests\Core;

use Core\Config;
use Core\Database;
use Core\Database\ConnectionException;
use Core\Database\QueryException;
use Core\Database\TransactionException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class DatabaseTest extends TestCase
{
    private Database $database;

    protected function setUp(): void
    {
        $config = new Config(__DIR__ . '/../Fixtures/config');
        $this->database = new Database($config);
        $this->database->statement(
            'CREATE TABLE items (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER, name TEXT, status TEXT)'
        );
    }

    public function testInsertSelectUpdateDeleteRoundTrip(): void
    {
        $id = $this->database->insert(
            'INSERT INTO items (tenant_id, name, status) VALUES (?, ?, ?)',
            [1, 'Hello', 'draft']
        );

        self::assertNotSame('', $id);

        $row = $this->database->selectOne('SELECT * FROM items WHERE id = ?', [$id]);
        self::assertSame('Hello', $row['name']);

        $affected = $this->database->update('UPDATE items SET status = ? WHERE id = ?', ['published', $id]);
        self::assertSame(1, $affected);

        $affected = $this->database->delete('DELETE FROM items WHERE id = ?', [$id]);
        self::assertSame(1, $affected);

        self::assertNull($this->database->selectOne('SELECT * FROM items WHERE id = ?', [$id]));
    }

    public function testTransactionCommitsOnSuccess(): void
    {
        $this->database->transaction(function (Database $db): void {
            $db->insert('INSERT INTO items (tenant_id, name, status) VALUES (?, ?, ?)', [1, 'A', 'draft']);
        });

        self::assertCount(1, $this->database->select('SELECT * FROM items'));
    }

    public function testTransactionRollsBackAutomaticallyOnException(): void
    {
        try {
            $this->database->transaction(function (Database $db): void {
                $db->insert('INSERT INTO items (tenant_id, name, status) VALUES (?, ?, ?)', [1, 'A', 'draft']);

                throw new RuntimeException('Business rule failed');
            });
            self::fail('Expected exception was not thrown.');
        } catch (RuntimeException $exception) {
            self::assertSame('Business rule failed', $exception->getMessage());
        }

        self::assertCount(0, $this->database->select('SELECT * FROM items'));
    }

    public function testNestedTransactionOnlyCommitsAtOutermostLevel(): void
    {
        $this->database->transaction(function (Database $db): void {
            $db->insert('INSERT INTO items (tenant_id, name, status) VALUES (?, ?, ?)', [1, 'Outer', 'draft']);

            $db->transaction(function (Database $inner): void {
                $inner->insert('INSERT INTO items (tenant_id, name, status) VALUES (?, ?, ?)', [1, 'Inner', 'draft']);
            });
        });

        self::assertCount(2, $this->database->select('SELECT * FROM items'));
    }

    public function testNestedTransactionRollsBackEntireOuterTransaction(): void
    {
        try {
            $this->database->transaction(function (Database $db): void {
                $db->insert('INSERT INTO items (tenant_id, name, status) VALUES (?, ?, ?)', [1, 'Outer', 'draft']);

                $db->transaction(function (Database $inner): void {
                    $inner->insert('INSERT INTO items (tenant_id, name, status) VALUES (?, ?, ?)', [1, 'Inner', 'draft']);

                    throw new RuntimeException('inner failure');
                });
            });
            self::fail('Expected exception was not thrown.');
        } catch (RuntimeException) {
            // expected
        }

        self::assertCount(0, $this->database->select('SELECT * FROM items'));
    }

    public function testCommitWithoutActiveTransactionThrows(): void
    {
        $this->expectException(TransactionException::class);

        $this->database->commit();
    }

    public function testRollbackWithoutActiveTransactionThrows(): void
    {
        $this->expectException(TransactionException::class);

        $this->database->rollback();
    }

    public function testInvalidSqlThrowsQueryExceptionCarryingSqlAndBindings(): void
    {
        try {
            $this->database->statement('SELECT * FROM non_existing_table WHERE id = ?', [1]);
            self::fail('Expected QueryException was not thrown.');
        } catch (QueryException $exception) {
            self::assertStringContainsString('non_existing_table', $exception->getSql());
            self::assertSame([1], $exception->getBindings());
        }
    }

    public function testConnectingWithUnknownConnectionNameThrowsConnectionException(): void
    {
        $config = new Config(__DIR__ . '/../Fixtures/config');
        $database = new Database($config, 'does-not-exist');

        $this->expectException(ConnectionException::class);

        $database->connection();
    }

    public function testQueryLogIsEmptyByDefaultAndPopulatedWhenEnabled(): void
    {
        self::assertSame([], $this->database->getQueryLog());

        $this->database->enableQueryLog();
        $this->database->select('SELECT * FROM items');

        self::assertCount(1, $this->database->getQueryLog());
    }
}
