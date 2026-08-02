<?php

declare(strict_types=1);

namespace Core\Router;

use RuntimeException;

/** URI khop nhung khong route nao khop HTTP method - tuong ung HTTP 405 (khac 404). */
final class MethodNotAllowedException extends RuntimeException
{
    /** @param list<string> $allowedMethods */
    public function __construct(private readonly array $allowedMethods, string $method, string $uri)
    {
        parent::__construct(\sprintf(
            'Method "%s" khong duoc phep cho "%s". Cac method hop le: %s.',
            $method,
            $uri,
            \implode(', ', $allowedMethods)
        ));
    }

    /** @return list<string> */
    public function getAllowedMethods(): array
    {
        return $this->allowedMethods;
    }
}
