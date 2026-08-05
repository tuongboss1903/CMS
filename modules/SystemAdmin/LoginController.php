<?php

declare(strict_types=1);

namespace Modules\SystemAdmin;

use Core\Http\Request;
use Core\Http\Response;
use Core\Security\PlatformAuditLogger;
use Core\Session;
use Core\SystemAdminAuthenticationService;
use Core\Validator;

/**
 * POST /system-admin/login - da chuyen sang PRG (Post/Redirect/Get), dong bo Modules\Admin\LoginController
 * (xem docblock day du o do). Loi/old input dua qua Session::flash(), redirect ve GET
 * /system-admin/login thay vi render lai form truc tiep tu POST.
 */
final class LoginController
{
    public function __construct(
        private readonly SystemAdminAuthenticationService $authenticationService,
        private readonly PlatformAuditLogger $platformAuditLogger,
        private readonly Session $session,
        private readonly Validator $validator,
    ) {
    }

    public function handle(Request $request): Response
    {
        $data = $request->all();
        $email = (string) ($data['email'] ?? '');

        $result = $this->validator->validate($data, [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if ($result->fails()) {
            return $this->redirectWithErrors($result->errors(), $email);
        }

        $password = (string) $data['password'];

        if (!$this->authenticationService->attempt($email, $password)) {
            $this->platformAuditLogger->log($request, 'auth.login_failed', newValues: ['email' => $email]);

            return $this->redirectWithErrors(['auth' => ['Email hoặc mật khẩu không đúng.']], $email);
        }

        $this->platformAuditLogger->log($request, 'auth.login_success');

        return Response::redirect('/system-admin/dashboard');
    }

    /** @param array<string, list<string>> $errors */
    private function redirectWithErrors(array $errors, string $email): Response
    {
        $this->session->flash('errors', $errors);
        $this->session->flash('old', ['email' => $email]);

        return Response::redirect('/system-admin/login');
    }
}
