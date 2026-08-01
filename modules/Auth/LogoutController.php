<?php

declare(strict_types=1);

namespace Modules\Auth;

use Core\Auth;
use Core\Http\Request;
use Core\Http\Response;

/**
 * POST /logout - JSON API thuan, idempotent: luon tra 200 du da dang nhap hay chua (Auth::logout()
 * khong throw o bat ky trang thai nao, xem Auth::logout()/Session::destroy()). Khong AuthMiddleware,
 * khong CSRF - Owner Decision CMS-035, nhat quan voi /login (chua co token-issuing flow).
 */
final class LogoutController
{
    public function __construct(private readonly Auth $auth)
    {
    }

    public function handle(Request $request): Response
    {
        $this->auth->logout();

        return Response::json([
            'success' => true,
            'data' => null,
            'message' => 'Dang xuat thanh cong.',
            'errors' => [],
        ]);
    }
}
