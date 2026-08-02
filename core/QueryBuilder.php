<?php

declare(strict_types=1);

namespace Core;

use Core\Database\IdentifierValidator;
use Core\Database\SqlCompiler;
use InvalidArgumentException;

/**
 * Chi dung SQL + bindings, khong tu goi PDO - luon giao lai cho Database (mục 4, thiet ke CMS-004).
 * Instance dung 1 lan cho 1 query (Database::table() luon tra ve instance MOI) - khong duoc tai su
 * dung 1 QueryBuilder cho nhieu query khac nhau vi state (wheres, joins...) se lan sang nhau.
 * Bien dich SQL (columns/joins/wheres/order-limit-offset) uy quyen cho SqlCompiler - class nay
 * chi giu state 1 query + cung cap API fluent.
 */
final class QueryBuilder
{
    /** @var list<string> */
    private array $columns = ['*'];

    /** @var list<array{column: string, operator: string, value: mixed}> */
    private array $wheres = [];

    /** @var list<array{table: string, first: string, operator: string, second: string}> */
    private array $joins = [];

    /** @var list<array{column: string, direction: string}> */
    private array $orders = [];

    private ?int $limitValue = null;
    private ?int $offsetValue = null;

    public function __construct(
        private readonly Database $database,
        private readonly string $table,
    ) {
        IdentifierValidator::assertIdentifier($table);
    }

    /** @param list<string> $columns */
    public function select(array $columns): static
    {
        foreach ($columns as $column) {
            if ($column !== '*') {
                IdentifierValidator::assertIdentifier($column);
            }
        }

        $this->columns = $columns;

        return $this;
    }

    public function where(string $column, string $operator, mixed $value): static
    {
        IdentifierValidator::assertIdentifier($column);
        IdentifierValidator::assertOperator($operator);

        $this->wheres[] = ['column' => $column, 'operator' => $operator, 'value' => $value];

        return $this;
    }

    /** @param list<mixed> $values */
    public function whereIn(string $column, array $values): static
    {
        IdentifierValidator::assertIdentifier($column);

        $this->wheres[] = ['column' => $column, 'operator' => 'in', 'value' => $values];

        return $this;
    }

    /**
     * Sugar cho where('tenant_id', '=', $tenantId) - khong chua business logic, khong tu suy doan
     * tenant hien tai (caller phai truyen ro $tenantId).
     */
    public function forTenant(int $tenantId): static
    {
        return $this->where('tenant_id', '=', $tenantId);
    }

    public function join(string $table, string $first, string $operator, string $second): static
    {
        IdentifierValidator::assertIdentifier($table);
        IdentifierValidator::assertIdentifier($first);
        IdentifierValidator::assertIdentifier($second);
        IdentifierValidator::assertOperator($operator);

        $this->joins[] = ['table' => $table, 'first' => $first, 'operator' => $operator, 'second' => $second];

        return $this;
    }

    public function orderBy(string $column, string $direction = 'asc'): static
    {
        IdentifierValidator::assertIdentifier($column);

        $direction = \strtolower($direction);

        if (!\in_array($direction, ['asc', 'desc'], true)) {
            throw new InvalidArgumentException(\sprintf('Huong sap xep khong hop le: "%s".', $direction));
        }

        $this->orders[] = ['column' => $column, 'direction' => $direction];

        return $this;
    }

    public function limit(int $n): static
    {
        $this->limitValue = $n;

        return $this;
    }

    public function offset(int $n): static
    {
        $this->offsetValue = $n;

        return $this;
    }

    /** @return list<array<string, mixed>> */
    public function get(): array
    {
        [$sql, $bindings] = $this->compileSelect();

        return $this->database->select($sql, $bindings);
    }

    /** @return array<string, mixed>|null */
    public function first(): ?array
    {
        $this->limitValue = 1;

        [$sql, $bindings] = $this->compileSelect();

        return $this->database->selectOne($sql, $bindings);
    }

    /**
     * KHONG di qua SqlCompiler::compileColumns() - "COUNT(*) as aggregate" la SQL expression co
     * dinh trong code (khong bao gio chua du lieu tu ben ngoai), khong phai 1 identifier can
     * whitelist. compileColumns() chi danh cho ten cot nguoi dung truyen qua select().
     */
    public function count(): int
    {
        $sql = \sprintf('SELECT COUNT(*) as aggregate FROM %s', SqlCompiler::quoteIdentifier($this->table));
        $sql .= SqlCompiler::compileJoins($this->joins);

        [$whereSql, $bindings] = SqlCompiler::compileWheres($this->wheres);
        $sql .= $whereSql;

        $row = $this->database->selectOne($sql, $bindings);

        return (int) ($row['aggregate'] ?? 0);
    }

    /** @param array<string, mixed> $data */
    public function insert(array $data): string
    {
        if ($data === []) {
            throw new InvalidArgumentException('insert() can it nhat 1 cot du lieu.');
        }

        $columns = \array_keys($data);

        foreach ($columns as $column) {
            IdentifierValidator::assertIdentifier($column);
        }

        $placeholders = \implode(', ', \array_fill(0, \count($columns), '?'));

        $sql = \sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            SqlCompiler::quoteIdentifier($this->table),
            \implode(', ', \array_map(SqlCompiler::quoteIdentifier(...), $columns)),
            $placeholders
        );

        return $this->database->insert($sql, \array_values($data));
    }

    /** @param array<string, mixed> $data */
    public function update(array $data): int
    {
        if ($data === []) {
            throw new InvalidArgumentException('update() can it nhat 1 cot du lieu.');
        }

        foreach (\array_keys($data) as $column) {
            IdentifierValidator::assertIdentifier($column);
        }

        $assignments = \implode(', ', \array_map(
            static fn (string $column): string => SqlCompiler::quoteIdentifier($column) . ' = ?',
            \array_keys($data)
        ));

        [$whereSql, $whereBindings] = SqlCompiler::compileWheres($this->wheres);

        $sql = \sprintf('UPDATE %s SET %s%s', SqlCompiler::quoteIdentifier($this->table), $assignments, $whereSql);

        return $this->database->update($sql, [...\array_values($data), ...$whereBindings]);
    }

    public function delete(): int
    {
        [$whereSql, $whereBindings] = SqlCompiler::compileWheres($this->wheres);

        $sql = \sprintf('DELETE FROM %s%s', SqlCompiler::quoteIdentifier($this->table), $whereSql);

        return $this->database->delete($sql, $whereBindings);
    }

    /** @return array{0: string, 1: list<mixed>} */
    private function compileSelect(): array
    {
        $sql = \sprintf(
            'SELECT %s FROM %s',
            SqlCompiler::compileColumns($this->columns),
            SqlCompiler::quoteIdentifier($this->table)
        );
        $sql .= SqlCompiler::compileJoins($this->joins);

        [$whereSql, $bindings] = SqlCompiler::compileWheres($this->wheres);
        $sql .= $whereSql;
        $sql .= SqlCompiler::compileOrderLimitOffset($this->orders, $this->limitValue, $this->offsetValue);

        return [$sql, $bindings];
    }
}
