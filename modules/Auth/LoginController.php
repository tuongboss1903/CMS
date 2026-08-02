<?php

declare(strict_types=1);

namespace Modules\Auth;

use Core\Auth;
use Core\AuthenticationService;
use Core\Authorization;
use Core\Http\Request;
use Core\Http\Response;
use Core\Validator;

/**
 * POST /login - JSON API thuan (khong GET form, khong CSRF - Owner Decision CMS-034, xem
 * core-architecture.md). Message loi thong nhat cho moi truong hop that bai (sai password/email
 * khong ton tai/rate-limited/inactive) - AuthenticationService::attempt() da khong phan biet duoc
 * cac case nay, Controller khong tu suy luan them de tranh leak.
 */
final class LoginController
{
    public function __construct(
        private readonly AuthenticationService $authenticationService,
        private readonly Auth $auth,
        private readonly Authorization $authorization,
        private readonly Validator $validator,
    ) {
    }

    public function handle(Request $request): Response
    {
        $data = $request->all();

        $result = $this->validator->validate($data, [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if ($result->fails()) {
            return Response::json([
                'success' => false,
                'data' => null,
                'message' => 'Du lieu khong hop le.',
                'errors' => $result->errors(),
            ], 422);
        }

        $email = (string) $data['email'];
        $password = (string) $data['password'];

        if (!$this->authenticationService->attempt($email, $password)) {
            return Response::json([
                'success' => false,
                'data' => null,
                'message' => 'Email hoac mat khau khong dung.',
                'errors' => [],
            ], 401);
        }

        return Response::json([
            'success' => true,
            'data' => [
                'id' => $this->auth->id(),
                'email' => $this->auth->user()['email'] ?? null,
                'roles' => $this->authorization->roles(),
                'permissions' => $this->authorization->permissions(),
            ],
            'message' => '',
            'errors' => [],
        ]);
    }
}
