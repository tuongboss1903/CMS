<?php

declare(strict_types=1);

namespace Modules\SystemAdmin;

use Core\Csrf;
use Core\Http\Request;
use Core\Http\Response;
use Core\Session;
use Core\SystemAdminAuth;
use Core\View;

/**
 * GET /system-admin/login - render form dang nhap Super Admin. Da dang nhap -> redirect thang ve
 * dashboard (khong render lai form). Doc loi/old input tu Session::getFlash() - dong bo PRG voi
 * LoginController, cung fix ap dung cho Modules\Admin\ShowLoginController.
 */
final class ShowLoginController
{
    public function __construct(
        private readonly Csrf $csrf,
        private readonly Session $session,
        private readonly SystemAdminAuth $systemAdminAuth,
        private readonly View $view,
    ) {
    }

    public function handle(Request $request): Response
    {
        if ($this->systemAdminAuth->check()) {
            return Response::redirect('/system-admin/dashboard');
        }

        $html = $this->view->render('system_admin.pages.login', [
            'errors' => $this->session->getFlash('errors', []),
            'old' => $this->session->getFlash('old', ['email' => '']),
            'csrf_token' => $this->csrf->token(),
        ]);

        return Response::html($html);
    }
}
