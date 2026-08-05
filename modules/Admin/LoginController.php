<?php

declare(strict_types=1);

namespace Modules\Admin;

use Core\Auth;
use Core\AuthenticationService;
use Core\Http\Request;
use Core\Http\Response;
use Core\Security\AuditLogger;
use Core\Session;
use Core\Validator;

/**
 * POST /admin/login - khong viet lai logic xac thuc, tai su dung nguyen AuthenticationService/Auth
 * (giong /login JSON CMS-034). Da chuyen sang PRG (Post/Redirect/Get) - loi/old input dua qua
 * Session::flash(), redirect ve GET /admin/login (ShowLoginController doc lai flash) thay vi render
 * lai form truc tiep tu POST - tranh F5 gui lai form, dung quy uoc "Redirect kem Flash Message" da
 * chot o core-architecture.md (khong can Csrf/View o Controller nay nua).
 *
 * Phase 16 (Security & Audit Log, CMS-053): ghi "auth.login_success"/"auth.login_failed". Luu y
 * o nhanh that bai, Session chua co "auth.user_id" (dang nhap chua thanh cong) nen AuditLogger tu
 * ghi user_id = NULL - dung thiet ke migration (nullable).
 */
final class LoginController
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly AuthenticationService $authenticationService,
        private readonly Auth $auth,
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
            $this->auditLogger->log($request, 'auth.login_failed', 'user', null, null, ['email' => $email]);

            return $this->redirectWithErrors(['auth' => ['Email hoặc mật khẩu không đúng.']], $email);
        }

        $this->auditLogger->log($request, 'auth.login_success', 'user', $this->auth->id() !== null ? (int) $this->auth->id() : null);

        return Response::redirect('/admin/dashboard');
    }

    /** @param array<string, list<string>> $errors */
    private function redirectWithErrors(array $errors, string $email): Response
    {
        $this->session->flash('errors', $errors);
        $this->session->flash('old', ['email' => $email]);

        return Response::redirect('/admin/login');
    }
}
