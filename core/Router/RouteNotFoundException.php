<?php

declare(strict_types=1);

namespace Core\Router;

use RuntimeException;

/** Khong co route nao khop URI (bat ke method) - tuong ung HTTP 404. */
final class RouteNotFoundException extends RuntimeException
{
    public static function forUri(string $method, string $uri): self
    {
        return new self(\sprintf('Khong tim thay route cho "%s %s".', $method, $uri));
    }
}
