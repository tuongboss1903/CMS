<?php

declare(strict_types=1);

namespace Tests\Fixtures\Http;

use Closure;
use Core\Http\Request;
use Core\Http\Response;
use Core\Middleware\MiddlewareInterface;

/** Khong bao gio goi $next() - tu tra Response ngay (short-circuit), Controller khong duoc chay. */
final class ShortCircuitMiddleware implements MiddlewareInterface
{
    public function process(Request $request, Closure $next): Response
    {
        return new Response('BLOCKED', 403);
    }
}
