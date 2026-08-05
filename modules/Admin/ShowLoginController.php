<?php

declare(strict_types=1);

namespace Modules\Admin;

use Core\Auth;
use Core\Csrf;
use Core\Http\Request;
use Core\Http\Response;
use Core\Session;
use Core\View;

/**
 * GET /admin/login - render form dang nhap HTML. Da dang nhap -> redirect thang ve dashboard
 * (khong render lai form dang nhap - UX fix, thay cho Owner Decision CMS-045 cu). Doc loi/old
 * input tu Session::getFlash() (bo sung PRG o LoginController) thay vi luon rong.
 */
final class ShowLoginController
{
    public function __construct(
        private readonly Auth $auth,
        private readonly Csrf $csrf,
        private readonly Session $session,
        private readonly View $view,
    ) {
    }

    public function handle(Request $request): Response
    {
        if ($this->auth->check()) {
            return Response::redirect('/admin/dashboard');
        }

        $html = $this->view->render('admin.pages.login', [
            'errors' => $this->session->getFlash('errors', []),
            'old' => $this->session->getFlash('old', ['email' => '']),
            'csrf_token' => $this->csrf->token(),
        ]);

        return Response::html($html);
    }
}
