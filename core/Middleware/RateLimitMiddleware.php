<?php

declare(strict_types=1);

namespace Core\Middleware;

use Closure;
use Core\Http\Request;
use Core\Http\Response;
use Core\RateLimiter;

/**
 * Placeholder framework component - giu nhat quan voi cac middleware khac (Auth/Csrf/Authorization
 * deu nhan dung 1 service qua constructor) nhung KHONG tu xac dinh key/maxAttempts/decaySeconds va
 * KHONG tu goi RateLimiter::hit() (khong co co che truyen tham so per-route trong kien truc hien
 * tai - Owner Decision CMS-023). Logic rate-limit thuc te do Module tuong lai tu goi RateLimiter
 * truc tiep, biet du business context de xac dinh bucket (vd 'login:'.$ip, 'contact:'.$ip).
 */
final class RateLimitMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly RateLimiter $rateLimiter)
    {
    }

    public function process(Request $request, Closure $next): Response
    {
        return $next($request);
    }
}
