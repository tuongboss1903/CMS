<?php

declare(strict_types=1);

namespace Modules\SystemAdmin;

use Core\Csrf;
use Core\Http\Request;
use Core\Http\Response;
use Core\SystemAdminAuthenticationService;
use Core\Validator;
use Core\View;

/**
 * POST /system-admin/login - khong PRG khi that bai (render lai form), redirect khi thanh cong.
 * Cung quy uoc voi Modules\Admin\LoginController.
 */
final class LoginController
{
    public function __construct(
        private readonly SystemAdminAuthenticationService $authenticationService,
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
            return $this->renderWithErrors(['auth' => ['Email hoac mat khau khong dung.']], $email);
        }

        return Response::redirect('/system-admin/sites');
    }

    /** @param array<string, list<string>> $errors */
    private function renderWithErrors(array $errors, string $email): Response
    {
        $html = $this->view->render('system_admin.pages.login', [
            'errors' => $errors,
            'old' => ['email' => $email],
            'csrf_token' => $this->csrf->token(),
        ]);

        return Response::html($html);
    }
}
