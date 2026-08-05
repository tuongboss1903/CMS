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
 * GET /admin/roles/{id}/permissions - xem danh sach permission da gan/chua gan cua 1 role. System
 * Role van xem duoc (Owner Decision 3 CMS-038: "View allowed"), chi hanh dong POST assign/revoke moi
 * bi chan 403 - trang nay chi khong render nut "Gan"/"Go" cho System Role. UX audit fix: bo sung
 * nut "Go" (RoleRevokePermissionController) - Owner Decision #1 CMS-047 (chi gan, khong go) da duoc
 * thay the boi yeu cau nang cap UX moi nhat.
 */
final class RoleShowPermissionsController
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
        if (!$this->authorization->can('role.assign_permission')) {
            return Response::html('403 Forbidden', 403);
        }

        $roleId = (int) $request->routeParam('id');
        $role = $this->database->selectOne('SELECT id, tenant_id, name FROM roles WHERE id = ?', [$roleId]);

        if ($role === null) {
            return Response::html('404 Not Found', 404);
        }

        if ($role['tenant_id'] !== null && (int) $role['tenant_id'] !== (int) $this->tenantManager->id()) {
            return Response::html('404 Not Found', 404);
        }

        $allPermissions = $this->database->select('SELECT id, `key`, description FROM permissions');
        $assignedRows = $this->database->select(
            'SELECT permission_id FROM role_permissions WHERE role_id = ?',
            [$roleId]
        );
        $assignedIds = \array_map(static fn (array $row): int => (int) $row['permission_id'], $assignedRows);

        $assigned = [];
        $unassigned = [];

        foreach ($allPermissions as $permission) {
            if (\in_array((int) $permission['id'], $assignedIds, true)) {
                $assigned[] = $permission;
            } else {
                $unassigned[] = $permission;
            }
        }

        $html = $this->view->render('admin.pages.roles.permissions', [
            'breadcrumb_items' => [['label' => 'Vai trò & Phân quyền', 'url' => '/admin/roles'], ['label' => 'Quản lý quyền']],
            'role' => $role,
            'isSystem' => $role['tenant_id'] === null,
            'assigned' => $assigned,
            'unassigned' => $unassigned,
            'csrf_token' => $this->csrf->token(),
        ]);

        return Response::html($html);
    }
}
