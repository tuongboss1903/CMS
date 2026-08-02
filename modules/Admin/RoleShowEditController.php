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
 * GET /admin/roles/{id}/edit - System Role -> 403 (khong the sua, Owner Decision 3 CMS-038).
 * Tenant Role thuoc site khac -> 404 (an danh, cung nguyen tac cross-tenant).
 */
final class RoleShowEditController
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
        if (!$this->authorization->can('role.update')) {
            return Response::html('403 Forbidden', 403);
        }

        $roleId = (int) $request->routeParam('id');
        $role = $this->database->selectOne('SELECT id, tenant_id, name FROM roles WHERE id = ?', [$roleId]);

        if ($role === null) {
            return Response::html('404 Not Found', 404);
        }

        if ($role['tenant_id'] === null) {
            return Response::html('403 Forbidden', 403);
        }

        if ((int) $role['tenant_id'] !== (int) $this->tenantManager->id()) {
            return Response::html('404 Not Found', 404);
        }

        $html = $this->view->render('admin.pages.roles.edit', [
            'role' => $role,
            'errors' => [],
            'old' => ['name' => $role['name']],
            'csrf_token' => $this->csrf->token(),
        ]);

        return Response::html($html);
    }
}
