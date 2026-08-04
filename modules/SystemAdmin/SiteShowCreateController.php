<?php

declare(strict_types=1);

namespace Modules\SystemAdmin;

use Core\Csrf;
use Core\Http\Request;
use Core\Http\Response;
use Core\SystemAdminAuth;
use Core\View;

/** GET /system-admin/sites/create - form tao site moi (kem domain chinh). */
final class SiteShowCreateController
{
    public function __construct(
        private readonly SystemAdminAuth $auth,
        private readonly Csrf $csrf,
        private readonly View $view,
    ) {
    }

    public function handle(Request $request): Response
    {
        if (!$this->auth->check()) {
            return Response::redirect('/system-admin/login');
        }

        $html = $this->view->render('system_admin.pages.sites.create', [
            'errors' => [],
            'old' => ['name' => '', 'domain' => ''],
            'csrf_token' => $this->csrf->token(),
            'current_user_name' => $this->auth->admin()['name'] ?? null,
        ]);

        return Response::html($html);
    }
}
