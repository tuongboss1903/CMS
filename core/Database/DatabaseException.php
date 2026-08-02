<?php

declare(strict_types=1);

namespace Core\Database;

use RuntimeException;

/**
 * Loi goc cua Database Layer - khong nem truc tiep, luon nem 1 trong cac subclass cu the.
 */
class DatabaseException extends RuntimeException
{
}
