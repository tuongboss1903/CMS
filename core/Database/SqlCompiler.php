<?php

declare(strict_types=1);

namespace Core\Database;

/**
 * @internal CHI duoc goi tu Core\QueryBuilder - Repository/Service KHONG duoc goi truc tiep class
 * nay. QueryBuilder la Public API duy nhat cua Database Layer (cung voi Core\Database); SqlCompiler
 * la chi tiet implementation noi bo, co the doi chu ky/gop lai bat ky luc nao ma khong anh huong
 * code ben ngoai. Khong doc Config, khong truy cap Database/PDO, khong quan ly Transaction - CHI
 * bien dich chuoi SQL, khong Business Logic.
 *
 * Ham thuan tuy (khong state) chuyen cac phan cua 1 query (columns, joins, wheres,
 * order/limit/offset) thanh chuoi SQL + bindings. Tach rieng khoi QueryBuilder de:
 * (1) giu QueryBuilder duoi 300 dong (coding-standard.md), (2) tach bach "giu state cua 1 query"
 * khoi "bien dich SQL" (Single Responsibility). Khac voi kieu "static global state" da bi cam -
 * khong co property nao, moi method la ham thuan tuy nhan tham so, tra ve gia tri, khong side-effect.
 */
final class SqlCompiler
{
    public static function quoteIdentifier(string $identifier): string
    {
        IdentifierValidator::assertIdentifier($identifier);

        return '`' . $identifier . '`';
    }

    /** @param list<string> $columns */
    public static function compileColumns(array $columns): string
    {
        if ($columns === ['*']) {
            return '*';
        }

        return \implode(', ', \array_map(
            static fn (string $column): string => $column === '*' ? '*' : self::quoteIdentifier($column),
            $columns
        ));
    }

    /** @param list<array{table: string, first: string, operator: string, second: string}> $joins */
    public static function compileJoins(array $joins): string
    {
        $sql = '';

        foreach ($joins as $join) {
            $sql .= \sprintf(
                ' INNER JOIN %s ON %s %s %s',
                self::quoteIdentifier($join['table']),
                self::quoteIdentifier($join['first']),
                $join['operator'],
                self::quoteIdentifier($join['second'])
            );
        }

        return $sql;
    }

    /**
     * @param list<array{column: string, operator: string, value: mixed}> $wheres
     * @return array{0: string, 1: list<mixed>}
     */
    public static function compileWheres(array $wheres): array
    {
        if ($wheres === []) {
            return ['', []];
        }

        $bindings = [];
        $clauses = [];

        foreach ($wheres as $where) {
            if ($where['operator'] === 'in') {
                /** @var list<mixed> $values */
                $values = $where['value'];

                if ($values === []) {
                    // IN () la loi cu phap SQL - mang rong nghia la "khong co gia tri nao khop",
                    // thay bang dieu kien luon sai thay vi crash.
                    $clauses[] = '1 = 0';

                    continue;
                }

                $placeholders = \implode(', ', \array_fill(0, \count($values), '?'));
                $clauses[] = \sprintf('%s IN (%s)', self::quoteIdentifier($where['column']), $placeholders);
                \array_push($bindings, ...$values);

                continue;
            }

            $clauses[] = \sprintf('%s %s ?', self::quoteIdentifier($where['column']), $where['operator']);
            $bindings[] = $where['value'];
        }

        return [' WHERE ' . \implode(' AND ', $clauses), $bindings];
    }

    /** @param list<array{column: string, direction: string}> $orders */
    public static function compileOrderLimitOffset(array $orders, ?int $limit, ?int $offset): string
    {
        $sql = '';

        if ($orders !== []) {
            $sql .= ' ORDER BY ' . \implode(', ', \array_map(
                static fn (array $order): string => self::quoteIdentifier($order['column']) . ' ' . \strtoupper($order['direction']),
                $orders
            ));
        }

        if ($limit !== null) {
            $sql .= ' LIMIT ' . $limit;
        }

        if ($offset !== null) {
            $sql .= ' OFFSET ' . $offset;
        }

        return $sql;
    }
}
