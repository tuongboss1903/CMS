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
 * GET /admin/roles - copy logic tu Modules\Role\ListRolesController (khong goi lai Controller
 * do). "system" xac dinh qua tenant_id IS NULL (Owner Decision #3 CMS-047), khong dung is_system.
 */
final class RoleListController
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
        if (!$this->authorization->can('role.view')) {
            return Response::html('403 Forbidden', 403);
        }

        $rows = $this->database->select(
            'SELECT id, tenant_id, name FROM roles WHERE tenant_id IS NULL OR tenant_id = ?',
            [$this->tenantManager->id()]
        );

        $roles = \array_map(static function (array $role): array {
            return [
                'id' => (int) $role['id'],
                'name' => $role['name'],
                'system' => $role['tenant_id'] === null,
            ];
        }, $rows);

        $html = $this->view->render('admin.pages.roles.list', [
            'breadcrumb_items' => [['label' => 'Vai trò & Phân quyền']],
            'roles' => $roles,
            'csrf_token' => $this->csrf->token(),
        ]);

        return Response::html($html);
    }
}
