<?php

declare(strict_types=1);

namespace Modules\Admin;

use Core\Auth;
use Core\Csrf;
use Core\Database;
use Core\Http\Request;
use Core\Http\Response;
use Core\TenantManager;
use Core\View;

/**
 * GET /admin/dashboard - Auth::check() tu xu ly redirect HTML (khong AuthMiddleware - class do
 * tra JSON 401, sai voi flow HTML). Query user_count/role_count copy tu Modules\Dashboard\
 * DashboardController (khong goi lai Controller do, khong sua modules/Dashboard/*).
 */
final class DashboardController
{
    public function __construct(
        private readonly Auth $auth,
        private readonly Csrf $csrf,
        private readonly Database $database,
        private readonly TenantManager $tenantManager,
        private readonly View $view,
    ) {
    }

    public function handle(Request $request): Response
    {
        if (!$this->auth->check()) {
            return Response::redirect('/admin/login');
        }

        $siteId = $this->tenantManager->id();

        $userCount = $this->database->selectOne(
            'SELECT COUNT(*) as count FROM users
             INNER JOIN user_site_roles ON user_site_roles.user_id = users.id
             WHERE user_site_roles.site_id = ?',
            [$siteId]
        );

        $roleCount = $this->database->selectOne(
            'SELECT COUNT(*) as count FROM roles WHERE tenant_id IS NULL OR tenant_id = ?',
            [$siteId]
        );

        $html = $this->view->render('admin.pages.dashboard', [
            'user_count' => (int) $userCount['count'],
            'role_count' => (int) $roleCount['count'],
            'csrf_token' => $this->csrf->token(),
        ]);

        return Response::html($html);
    }
}
