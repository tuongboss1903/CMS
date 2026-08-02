<?php

declare(strict_types=1);

namespace Core\Database;

use InvalidArgumentException;

/**
 * Ham thuan tuy, khong luu state giua cac lan goi - khac voi kieu "static global state" da bi
 * cam trong coding-standard.md (Config ban dau). Chi dung de whitelist ten cot/bang truoc khi
 * QueryBuilder noi chuoi vao SQL, vi PDO khong the parameterize identifier (chi parameterize gia tri).
 */
final class IdentifierValidator
{
    private const IDENTIFIER_PATTERN = '/^[a-zA-Z_][a-zA-Z0-9_]*$/';

    /** @var list<string> */
    private const ALLOWED_OPERATORS = ['=', '!=', '<>', '<', '<=', '>', '>=', 'like'];

    public static function assertIdentifier(string $identifier): void
    {
        if (\preg_match(self::IDENTIFIER_PATTERN, $identifier) !== 1) {
            throw new InvalidArgumentException(\sprintf('Ten cot/bang khong hop le: "%s".', $identifier));
        }
    }

    public static function assertOperator(string $operator): void
    {
        if (!\in_array(\strtolower($operator), self::ALLOWED_OPERATORS, true)) {
            throw new InvalidArgumentException(\sprintf('Toan tu khong hop le: "%s".', $operator));
        }
    }
}
