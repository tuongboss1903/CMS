<?php

declare(strict_types=1);

namespace Tests\Fixtures\Http;

final class MiddlewareA extends TaggingMiddleware
{
    protected function tag(): string
    {
        return 'A';
    }
}
