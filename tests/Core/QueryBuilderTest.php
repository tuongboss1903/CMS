<?php

declare(strict_types=1);

namespace Tests\Core;

use Core\Config;
use Core\Database;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class QueryBuilderTest extends TestCase
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

    public function testInsertReturnsLastInsertId(): void
    {
        $id = $this->database->table('items')->insert(['tenant_id' => 1, 'name' => 'Hello', 'status' => 'draft']);

        self::assertNotSame('', $id);
    }

    public function testWhereFiltersRows(): void
    {
        $this->database->table('items')->insert(['tenant_id' => 1, 'name' => 'A', 'status' => 'draft']);
        $this->database->table('items')->insert(['tenant_id' => 2, 'name' => 'B', 'status' => 'draft']);

        $rows = $this->database->table('items')->where('tenant_id', '=', 1)->get();

        self::assertCount(1, $rows);
        self::assertSame('A', $rows[0]['name']);
    }

    public function testForTenantIsSugarForWhereTenantId(): void
    {
        $this->database->table('items')->insert(['tenant_id' => 1, 'name' => 'A', 'status' => 'draft']);
        $this->database->table('items')->insert(['tenant_id' => 2, 'name' => 'B', 'status' => 'draft']);

        $rows = $this->database->table('items')->forTenant(2)->get();

        self::assertCount(1, $rows);
        self::assertSame('B', $rows[0]['name']);
    }

    public function testWhereInFiltersRows(): void
    {
        $this->database->table('items')->insert(['tenant_id' => 1, 'name' => 'A', 'status' => 'draft']);
        $this->database->table('items')->insert(['tenant_id' => 1, 'name' => 'B', 'status' => 'published']);
        $this->database->table('items')->insert(['tenant_id' => 1, 'name' => 'C', 'status' => 'archived']);

        $rows = $this->database->table('items')->whereIn('status', ['draft', 'published'])->get();

        self::assertCount(2, $rows);
    }

    public function testFirstReturnsNullWhenNoRowMatches(): void
    {
        $row = $this->database->table('items')->where('tenant_id', '=', 999)->first();

        self::assertNull($row);
    }

    public function testOrderByLimitOffset(): void
    {
        $this->database->table('items')->insert(['tenant_id' => 1, 'name' => 'A', 'status' => 'draft']);
        $this->database->table('items')->insert(['tenant_id' => 1, 'name' => 'B', 'status' => 'draft']);
        $this->database->table('items')->insert(['tenant_id' => 1, 'name' => 'C', 'status' => 'draft']);

        $rows = $this->database->table('items')->orderBy('name', 'desc')->limit(1)->offset(1)->get();

        self::assertCount(1, $rows);
        self::assertSame('B', $rows[0]['name']);
    }

    public function testCountReturnsNumberOfMatchingRows(): void
    {
        $this->database->table('items')->insert(['tenant_id' => 1, 'name' => 'A', 'status' => 'draft']);
        $this->database->table('items')->insert(['tenant_id' => 1, 'name' => 'B', 'status' => 'draft']);

        self::assertSame(2, $this->database->table('items')->where('tenant_id', '=', 1)->count());
    }

    public function testCountReturnsZeroWhenNoRowsMatch(): void
    {
        $this->database->table('items')->insert(['tenant_id' => 1, 'name' => 'A', 'status' => 'draft']);

        self::assertSame(0, $this->database->table('items')->where('tenant_id', '=', 999)->count());
    }

    /**
     * Regression test cho bug CMS-004: count() truoc day ghi de $this->columns bang chuoi
     * "COUNT(*) as aggregate" roi tai su dung compileColumns() - lam no bi IdentifierValidator
     * tu choi (vi day la SQL expression, khong phai 1 ten cot). count() da duoc sua de tu dung SQL
     * rieng, khong di qua compileColumns()/IdentifierValidator. Test nay phai LUON pass - neu ai do
     * sau nay lam count() quay lai dung chung duong voi compileColumns(), test se fail ngay.
     */
    public function testCountDoesNotTreatAggregateExpressionAsIdentifier(): void
    {
        $this->database->table('items')->insert(['tenant_id' => 1, 'name' => 'A', 'status' => 'draft']);

        try {
            $count = $this->database->table('items')->count();
        } catch (InvalidArgumentException $exception) {
            self::fail('count() khong duoc nem InvalidArgumentException: ' . $exception->getMessage());
        }

        self::assertSame(1, $count);
    }

    public function testSelectSpecificColumnsCompilesCorrectSqlAndReturnsThem(): void
    {
        $this->database->table('items')->insert(['tenant_id' => 1, 'name' => 'A', 'status' => 'draft']);

        $this->database->enableQueryLog();
        $rows = $this->database->table('items')->select(['id', 'name'])->get();

        self::assertCount(1, $rows);
        self::assertArrayHasKey('id', $rows[0]);
        self::assertArrayHasKey('name', $rows[0]);

        $log = $this->database->getQueryLog();
        self::assertStringContainsString('SELECT `id`, `name` FROM', end($log)['sql']);
    }

    public function testOrderByWithInvalidColumnNameThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->database->table('items')->orderBy('id; DROP TABLE items;--');
    }

    public function testJoinWithInvalidTableNameThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->database->table('items')->join('other; DROP TABLE items;--', 'id', '=', 'item_id');
    }

    public function testJoinWithInvalidColumnNameThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->database->table('items')->join('other', 'id; DROP TABLE items;--', '=', 'item_id');
    }

    public function testSqlInjectionAttemptThroughWhereColumnIsBlocked(): void
    {
        $this->expectException(InvalidArgumentException::class);

        // Payload co gang thoat khoi backtick quoting cua QueryBuilder.
        $this->database->table('items')->where('status` = `status` OR `1`=`1', '=', 'x');
    }

    public function testUpdateModifiesMatchingRowsOnly(): void
    {
        $id1 = $this->database->table('items')->insert(['tenant_id' => 1, 'name' => 'A', 'status' => 'draft']);
        $id2 = $this->database->table('items')->insert(['tenant_id' => 2, 'name' => 'B', 'status' => 'draft']);

        $affected = $this->database->table('items')->where('tenant_id', '=', 1)->update(['status' => 'published']);

        self::assertSame(1, $affected);
        self::assertSame('published', $this->database->table('items')->where('id', '=', (int) $id1)->first()['status']);
        self::assertSame('draft', $this->database->table('items')->where('id', '=', (int) $id2)->first()['status']);
    }

    public function testDeleteRemovesMatchingRowsOnly(): void
    {
        $this->database->table('items')->insert(['tenant_id' => 1, 'name' => 'A', 'status' => 'draft']);
        $this->database->table('items')->insert(['tenant_id' => 2, 'name' => 'B', 'status' => 'draft']);

        $affected = $this->database->table('items')->where('tenant_id', '=', 1)->delete();

        self::assertSame(1, $affected);
        self::assertCount(1, $this->database->table('items')->get());
    }

    public function testInvalidColumnNameIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->database->table('items')->where('id; DROP TABLE items;--', '=', 1);
    }

    public function testInvalidOperatorIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->database->table('items')->where('status', 'DROP TABLE', 'draft');
    }

    public function testInvalidTableNameIsRejectedAtConstruction(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->database->table('items; DROP TABLE items;--');
    }

    public function testWhereInWithEmptyArrayMatchesNoRowsInsteadOfThrowing(): void
    {
        $this->database->table('items')->insert(['tenant_id' => 1, 'name' => 'A', 'status' => 'draft']);

        $rows = $this->database->table('items')->whereIn('status', [])->get();

        self::assertSame([], $rows);
    }

    public function testInsertWithEmptyDataThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->database->table('items')->insert([]);
    }

    public function testUpdateWithEmptyDataThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->database->table('items')->where('id', '=', 1)->update([]);
    }
}
