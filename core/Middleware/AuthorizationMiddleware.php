<?php

declare(strict_types=1);

namespace Core\Middleware;

use Closure;
use Core\Authorization;
use Core\Http\Request;
use Core\Http\Response;

/**
 * Gate chung: chan request neu user chua duoc gan bat ky role/permission nao. KHONG tham so hoa
 * per-route (khong nhan requirement qua constructor) - kiem tra quyen CU THE cho tung hanh dong
 * la trach nhiem Controller tu goi Authorization::hasRole()/can() truc tiep.
 */
final class AuthorizationMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly Authorization $authorization)
    {
    }

    public function process(Request $request, Closure $next): Response
    {
        if ($this->authorization->roles() === [] && $this->authorization->permissions() === []) {
            return Response::json([
                'success' => false,
                'data' => null,
                'message' => 'Forbidden.',
                'errors' => [],
            ], 403);
        }

        return $next($request);
    }
}
