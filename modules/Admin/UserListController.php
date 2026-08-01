<?php

declare(strict_types=1);

namespace Modules\Admin;

use Core\Authorization;
use Core\Csrf;
use Core\Database;
use Core\Http\Request;
use Core\Http\Response;
use Core\TenantManager;
use Core\View;

/**
 * GET /admin/users - copy logic tu Modules\User\ListUsersController (khong goi lai Controller
 * do). Them SELECT roles de render dropdown "Gan role" ngay trong list.php (khong co route/form
 * rieng cho Assign Role - dung route table da duyet CMS-046).
 */
final class UserListController
{
    public function __construct(
        private readonly Authorization $authorization,
        private readonly Csrf $csrf,
        private readonly Database $database,
        private readonly TenantManager $tenantManager,
        private readonly View $view,
    ) {
    }

    public function handle(Request $request): Response
    {
        if (!$this->authorization->can('user.view')) {
            return Response::html('403 Forbidden', 403);
        }

        $siteId = $this->tenantManager->id();

        $users = $this->database->select(
            'SELECT users.id, users.name, users.email, users.status
             FROM users
             INNER JOIN user_site_roles ON user_site_roles.user_id = users.id
             WHERE user_site_roles.site_id = ?',
            [$siteId]
        );

        $roles = $this->database->select(
            'SELECT id, name FROM roles WHERE tenant_id IS NULL OR tenant_id = ?',
            [$siteId]
        );

        $html = $this->view->render('admin.pages.users.list', [
            'users' => $users,
            'roles' => $roles,
            'csrf_token' => $this->csrf->token(),
        ]);

        return Response::html($html);
    }
}
