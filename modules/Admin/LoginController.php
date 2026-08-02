<?php

declare(strict_types=1);

namespace Modules\Admin;

use Core\Auth;
use Core\AuthenticationService;
use Core\Csrf;
use Core\Http\Request;
use Core\Http\Response;
use Core\Security\AuditLogger;
use Core\Validator;
use Core\View;

/**
 * POST /admin/login - khong viet lai logic xac thuc, tai su dung nguyen AuthenticationService/Auth
 * (giong /login JSON CMS-034). Khac /login: redirect khi thanh cong, render lai form (khong PRG,
 * khong Session::flash) khi that bai - dung Owner Decision CMS-045.
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
        private readonly Csrf $csrf,
        private readonly Validator $validator,
        private readonly View $view,
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
            return $this->renderWithErrors($result->errors(), $email);
        }

        $password = (string) $data['password'];

        if (!$this->authenticationService->attempt($email, $password)) {
            $this->auditLogger->log($request, 'auth.login_failed', 'user', null, null, ['email' => $email]);

            return $this->renderWithErrors(['auth' => ['Email hoac mat khau khong dung.']], $email);
        }

        $this->auditLogger->log($request, 'auth.login_success', 'user', $this->auth->id() !== null ? (int) $this->auth->id() : null);

        return Response::redirect('/admin/dashboard');
    }

    /** @param array<string, list<string>> $errors */
    private function renderWithErrors(array $errors, string $email): Response
    {
        $html = $this->view->render('admin.pages.login', [
            'errors' => $errors,
            'old' => ['email' => $email],
            'csrf_token' => $this->csrf->token(),
        ]);

        return Response::html($html);
    }
}
