<?php

declare(strict_types=1);

namespace Core\Middleware;

use Closure;
use Core\Auth;
use Core\Http\Request;
use Core\Http\Response;

/** Chan request chua dang nhap o tang HTTP - uy quyen hoan toan viec kiem tra cho Auth::check(). */
final class AuthMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly Auth $auth)
    {
    }

    public function process(Request $request, Closure $next): Response
    {
        if (!$this->auth->check()) {
            return Response::json([
                'success' => false,
                'data' => null,
                'message' => 'Unauthenticated.',
                'errors' => [],
            ], 401);
        }

        return $next($request);
    }
}
