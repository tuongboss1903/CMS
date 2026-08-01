<?php

declare(strict_types=1);

namespace Core\Middleware;

use Closure;
use Core\Csrf;
use Core\Http\Request;
use Core\Http\Response;

/**
 * Tich hop kiem tra CSRF vao HTTP request lifecycle. Uy quyen hoan toan viec sinh/luu/so khop
 * token cho Csrf - class nay chi doc Request va quyet dinh method nao can kiem tra.
 *
 * Doc token theo dung thu tu: _token (input) -> X-CSRF-TOKEN (header) -> X-XSRF-TOKEN (header).
 * Neu gia tri doc duoc KHONG phai string (vd client gui _token[]=x tao mang) thi coi la khong co
 * token hop le va fail ngay - KHONG ep kieu (string) de tranh PHP Warning "Array to string
 * conversion".
 */
final class CsrfMiddleware implements MiddlewareInterface
{
    private const UNSAFE_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    public function __construct(private readonly Csrf $csrf)
    {
    }

    public function process(Request $request, Closure $next): Response
    {
        if (!\in_array($request->method(), self::UNSAFE_METHODS, true)) {
            return $next($request);
        }

        $submitted = $request->input('_token');

        if ($submitted === null) {
            $submitted = $request->header('X-CSRF-TOKEN');
        }

        if ($submitted === null) {
            $submitted = $request->header('X-XSRF-TOKEN');
        }

        if (!\is_string($submitted) || !$this->csrf->verify($submitted)) {
            return Response::json([
                'success' => false,
                'data' => null,
                'message' => 'CSRF token mismatch.',
                'errors' => [],
            ], 419);
        }

        return $next($request);
    }
}
