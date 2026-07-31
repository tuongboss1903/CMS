<?php

declare(strict_types=1);

namespace Tests\Fixtures\Http;

final class MiddlewareB extends TaggingMiddleware
{
    protected function tag(): string
    {
        return 'B';
    }
}
