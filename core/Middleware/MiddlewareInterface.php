<?php

declare(strict_types=1);

namespace Core\Middleware;

use Closure;
use Core\Http\Request;
use Core\Http\Response;

interface MiddlewareInterface
{
    /**
     * Goi $next($request) de di tiep (co the xu ly Request truoc - "Before", va xu ly Response
     * sau khi $next() tra ve - "After"), HOAC tu tra Response ngay khong goi $next() (short-circuit).
     */
    public function process(Request $request, Closure $next): Response;
}
