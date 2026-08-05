<?php

declare(strict_types=1);

namespace Modules\Admin;

use Core\Authorization;
use Core\Database;
use Core\Http\Request;
use Core\Http\Response;
use Core\TenantManager;

/**
 * POST /admin/roles/{id}/permissions/{permissionId}/delete - go 1 permission khoi role. Bo sung
 * UX audit (Owner Decision #1 CMS-047 truoc day chi cho gan, khong go) - dung cung guard/redirect
 * pattern voi RoleAssignPermissionsController (cung permission "role.assign_permission", cung
 * kiem tra tenant_id null = System Role khong duoc sua). Idempotent (DELETE khong dieu kien tra ve
 * loi neu row khong ton tai).
 */
final class RoleRevokePermissionController
{
    public function __construct(
        private readonly Authorization $authorization,
        private readonly Database $database,
        private readonly TenantManager $tenantManager,
    ) {
    }

    public function handle(Request $request): Response
    {
        if (!$this->authorization->can('role.assign_permission')) {
            return Response::html('403 Forbidden', 403);
        }

        $roleId = (int) $request->routeParam('id');
        $role = $this->database->selectOne('SELECT id, tenant_id FROM roles WHERE id = ?', [$roleId]);

        if ($role === null) {
            return Response::html('404 Not Found', 404);
        }

        if ($role['tenant_id'] === null) {
            return Response::html('403 Forbidden', 403);
        }

        if ((int) $role['tenant_id'] !== (int) $this->tenantManager->id()) {
            return Response::html('404 Not Found', 404);
        }

        $permissionId = (int) $request->routeParam('permissionId');

        $this->database->delete(
            'DELETE FROM role_permissions WHERE role_id = ? AND permission_id = ?',
            [$roleId, $permissionId]
        );

        return Response::redirect("/admin/roles/{$roleId}/permissions");
    }
}
